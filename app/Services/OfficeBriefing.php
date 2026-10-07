<?php

namespace App\Services;

use App\Mail\OfficeBriefingMail;
use App\Models\CashflowEntry;
use App\Models\CashflowRecurrenceOccurrence;
use App\Models\CashflowRecurrenceRule;
use App\Models\Client;
use App\Models\OfficeAlert;
use App\Models\OfficeAlertState;
use App\Models\OfficeSetting;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OfficeBriefing
{
    public function kycSubmitted(Client $client): ?OfficeAlert
    {
        if (! $this->sourceOn('kyc')) {
            return null;
        }

        $name = $client->company_name ?: $client->client_number ?: 'A client';

        return $this->raise([
            'event_key' => 'kyc.submitted',
            'fingerprint' => 'kyc.submitted:'.$client->id.':'.optional($client->kyc_submitted_at)->timestamp,
            'title' => $name.' submitted KYC',
            'body' => 'Review the form and approve, reject, or send it back. Sales should follow up the same day.',
            'severity' => OfficeAlert::SEVERITY_CRITICAL,
            'requires_ack' => true,
            'team' => 'sales',
            'action_url' => Route::has('clients.show') ? route('clients.show', [$client, 'tab' => 'kyc']) : null,
            'action_label' => 'Open KYC',
            'subject_type' => Client::class,
            'subject_id' => $client->id,
        ], email: true);
    }

    public function shipmentStatusChanged(Shipment $shipment, ?string $from = null): ?OfficeAlert
    {
        if (! $this->sourceOn('shipment_exception')) {
            return null;
        }

        if (in_array($shipment->status, [Shipment::STATUS_CUSTOM_HOLD, Shipment::STATUS_DELAYED], true)) {
            $label = $shipment->statusLabel();
            $number = $shipment->shipment_number ?: '#'.$shipment->id;

            return $this->raise([
                'event_key' => 'shipment.exception',
                'fingerprint' => 'shipment.exception:'.$shipment->id.':'.$shipment->status,
                'title' => $number.' is '.$label,
                'body' => 'This file needs a tracking note today — chase the forwarder so the shipment is not silent.',
                'severity' => OfficeAlert::SEVERITY_CRITICAL,
                'requires_ack' => true,
                'team' => 'operations',
                'action_url' => Route::has('shipments.show') ? route('shipments.show', $shipment) : null,
                'action_label' => 'Open shipment',
                'subject_type' => Shipment::class,
                'subject_id' => $shipment->id,
            ], email: true);
        }

        return null;
    }

    /**
     * A recurring payment has reached its effective date: the office asked for
     * it to be approved, so this is the ask.
     *
     * One alert per occurrence, fingerprinted on the occurrence's id, which is
     * what makes "the notification goes out once" true in two places at once:
     * the plan stamps `notified_at` so it does not ask again, and this
     * `firstOrCreate` means even a second call cannot raise a second alert.
     * The email is the loud half (the office asked to be told *when the approval
     * is required*, which is a push, not a page to remember to open); the alert
     * stays in the briefing list until somebody acknowledges it.
     *
     * Severity is `attention` and not `critical`: money falling due is the
     * system working, and a desk that treats every standing payment as an
     * emergency learns to ignore the colour that means emergency.
     */
    public function recurringNeedsApproval(CashflowRecurrenceOccurrence $occurrence): ?OfficeAlert
    {
        if (! $this->sourceOn('recurring_due')) {
            return null;
        }

        $rule = $occurrence->rule ?: CashflowRecurrenceRule::find($occurrence->cashflow_recurrence_rule_id);

        if (! $rule) {
            return null;
        }

        $amount = \App\Helpers\CommonHelper::amount((float) $rule->amount, $rule->currency ?: 'INR');
        $date = $occurrence->effective_date?->format('d M Y') ?: '—';
        $number = (int) $occurrence->sequence;

        return $this->raise([
            'event_key' => 'cashflow.recurring_due',
            'fingerprint' => 'cashflow.recurring_due:'.$occurrence->id,
            'title' => $rule->title.' — approval required',
            'body' => $amount.' ('.$rule->transaction_type.', '.$rule->frequencyLabel().') is due '
                .$date.', occurrence #'.$number.'. Approve it to post the entry, or skip this date.',
            'severity' => OfficeAlert::SEVERITY_ATTENTION,
            'requires_ack' => true,
            'team' => 'accounts',
            'action_url' => Route::has('cashflows.recurring.show') ? route('cashflows.recurring.show', $rule) : null,
            'action_label' => 'Review and approve',
            'subject_type' => CashflowRecurrenceRule::class,
            'subject_id' => $rule->id,
        ], email: true);
    }

    /**
     * Take back the asks a rule raised: the other half of deleting one.
     *
     * The alert is the office being told that a date needs an answer. When the
     * rule goes, the date goes with it — and an alert whose "Review and approve"
     * button opens a rule nobody can open is worse than no alert at all, because
     * a bell that cannot be answered is a bell people stop reading. Alerts are
     * this class's fact (it raises every one of them), so this class is where
     * they are withdrawn; the per-user seen/acknowledged rows cascade with them.
     *
     * Guarded like every other writer here: the briefing is installed by a
     * migration of its own, and a rule is deletable on a database where it has
     * not run yet.
     */
    public function withdrawRecurringRule(CashflowRecurrenceRule $rule): int
    {
        if (! class_exists(OfficeAlert::class) || ! Schema::hasTable('office_alerts')) {
            return 0;
        }

        return OfficeAlert::query()
            ->where('subject_type', CashflowRecurrenceRule::class)
            ->where('subject_id', $rule->id)
            ->delete();
    }

    public function runScheduled(): array
    {
        $raised = [];

        if (! $this->settings()['enabled']) {
            return [];
        }

        if (class_exists(Shipment::class) && Schema::hasTable('shipments')) {
            $raised[] = $this->inTransitDigest();
            $raised[] = $this->holdDigest();
            $raised = array_merge($raised, $this->shipmentExceptions());
        }

        if (class_exists(CashflowEntry::class) && Schema::hasTable('cashflow_entries')) {
            $raised[] = $this->pendingCashflowDigest();
        }

        return array_values(array_filter($raised));
    }

    public function chaseUnacked(?int $hours = null): int
    {
        $settings = $this->settings();
        if (! $settings['enabled'] || ! $settings['emails'] || ! $settings['chase_emails']) {
            return 0;
        }

        if (! Schema::hasTable('office_alerts')) {
            return 0;
        }

        $hours = $hours ?? (int) $settings['chase_hours'];
        $cutoff = now()->subHours(max(1, $hours));
        $sent = 0;

        OfficeAlert::query()
            ->where('requires_ack', true)
            ->whereNull('acked_at')
            ->where('event_key', '!=', 'briefing.ran')
            ->where('created_at', '<=', $cutoff)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('emailed_at')->orWhere('emailed_at', '<=', $cutoff);
            })
            ->get()
            ->each(function (OfficeAlert $alert) use (&$sent) {
                $this->emailWatchers($alert);
                $sent++;
            });

        return $sent;
    }

    public function payload(User $user): array
    {
        if (! $user->isAdmin() || ! Schema::hasTable('office_alerts') || ! $this->settings()['enabled']) {
            return ['unread' => 0, 'critical' => 0, 'items' => [], 'toasts' => [], 'popup' => null];
        }

        $this->ensureToday();

        $items = $this->openFor($user);
        $active = $items->where('snoozed', false);
        $toasts = $active->where('severity', OfficeAlert::SEVERITY_INFO)->values();
        $popup = $this->settings()['popups']
            ? $active->first(function ($row) {
                return $row['requires_ack'] && $row['severity'] === OfficeAlert::SEVERITY_CRITICAL && ! $row['popup_shown'];
            })
            : null;

        return [
            'unread' => $active->count(),
            'critical' => $active->where('severity', OfficeAlert::SEVERITY_CRITICAL)->count(),
            'items' => $items->values()->all(),
            'toasts' => $toasts->all(),
            'popup' => $popup,
        ];
    }

    public function openFor(User $user): Collection
    {
        $states = OfficeAlertState::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('office_alert_id');

        return OfficeAlert::query()
            ->with('ackedBy')
            ->where('event_key', '!=', 'briefing.ran')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'attention' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->filter(fn (OfficeAlert $alert) => $user->watchesTeam($alert->team))
            ->filter(function (OfficeAlert $alert) use ($states) {
                $state = $states->get($alert->id);
                if ($alert->requires_ack && $alert->acked_at) {
                    return false;
                }
                if (! $alert->requires_ack && $state?->seen_at) {
                    return false;
                }
                if ($state?->snoozed_until && $state->snoozed_until->isFuture()) {
                    return true;
                }

                return true;
            })
            ->take(40)
            ->map(fn (OfficeAlert $alert) => $this->present($alert, $states->get($alert->id)))
            ->values();
    }

    public function markSeen(OfficeAlert $alert, User $user): void
    {
        $state = $this->state($alert, $user);
        if (! $state->seen_at) {
            $state->seen_at = now();
            $state->save();
        }
        if (! $alert->requires_ack && ! $state->acked_at) {
            $state->acked_at = now();
            $state->save();
        }
    }

    public function markAcked(OfficeAlert $alert, User $user): void
    {
        $state = $this->state($alert, $user);
        $state->seen_at = $state->seen_at ?: now();
        $state->acked_at = now();
        $state->snoozed_until = null;
        $state->save();

        if ($alert->requires_ack && ! $alert->acked_at) {
            $alert->acked_at = now();
            $alert->acked_by = $user->id;
            $alert->save();
        }
    }

    public function snooze(OfficeAlert $alert, User $user, string $until): void
    {
        $when = match ($until) {
            '1h' => now()->addHour(),
            '4h' => now()->addHours(4),
            'tomorrow' => now()->hour < 9
                ? now()->setTime(9, 0)
                : now()->addDay()->setTime(9, 0),
            default => now()->addHours(4),
        };

        $state = $this->state($alert, $user);
        $state->seen_at = $state->seen_at ?: now();
        $state->popup_at = $state->popup_at ?: now();
        $state->snoozed_until = $when;
        $state->save();
    }

    public function markPopupShown(OfficeAlert $alert, User $user): void
    {
        $state = $this->state($alert, $user);
        if (! $state->popup_at) {
            $state->popup_at = now();
            $state->save();
        }
    }

    private function shipmentExceptions(): array
    {
        $raised = [];

        Shipment::query()->open()->whereIn('status', [
            Shipment::STATUS_CUSTOM_HOLD, Shipment::STATUS_DELAYED,
        ])->get()->each(function (Shipment $shipment) use (&$raised) {
            $raised[] = $this->shipmentStatusChanged($shipment);
        });

        if ($this->sourceOn('shipment_overdue')) {
        Shipment::query()->open()->attention('overdue')->limit(30)->get()
            ->each(function (Shipment $shipment) use (&$raised) {
                $number = $shipment->shipment_number ?: '#'.$shipment->id;
                $raised[] = $this->raise([
                    'event_key' => 'shipment.overdue',
                    'fingerprint' => 'shipment.overdue:'.$shipment->id.':'.optional($shipment->eta_date)->toDateString(),
                    'title' => $number.' missed its ETA',
                    'body' => 'ETA was '.optional($shipment->eta_date)->format('d M Y').'. Update tracking or the date so the file is honest.',
                    'severity' => OfficeAlert::SEVERITY_CRITICAL,
                    'requires_ack' => true,
                    'team' => 'operations',
                    'action_url' => Route::has('shipments.show') ? route('shipments.show', $shipment) : null,
                    'action_label' => 'Open shipment',
                    'subject_type' => Shipment::class,
                    'subject_id' => $shipment->id,
                ], email: true);
            });
        }

        $staleDays = max(1, (int) $this->settings()['stale_days']);
        if ($this->sourceOn('shipment_stale')) {
        Shipment::query()->open()->attention('stale', $staleDays)->limit(30)->get()
            ->each(function (Shipment $shipment) use (&$raised, $staleDays) {
                $number = $shipment->shipment_number ?: '#'.$shipment->id;
                $hours = $staleDays * 24;
                $raised[] = $this->raise([
                    'event_key' => 'shipment.stale',
                    'fingerprint' => 'shipment.stale:'.$shipment->id.':'.now()->toDateString(),
                    'title' => $number.' has had no tracking note in '.$hours.' hours',
                    'body' => 'Leave a note on the file even if nothing moved — silence is the exception.',
                    'severity' => OfficeAlert::SEVERITY_CRITICAL,
                    'requires_ack' => true,
                    'team' => 'operations',
                    'action_url' => Route::has('shipments.show') ? route('shipments.show', $shipment) : null,
                    'action_label' => 'Open shipment',
                    'subject_type' => Shipment::class,
                    'subject_id' => $shipment->id,
                ], email: true);
            });
        }

        return $raised;
    }

    private function inTransitDigest(): ?OfficeAlert
    {
        if (! $this->sourceOn('digest_in_transit')) {
            return null;
        }

        $rows = Shipment::query()
            ->open()
            ->where('status', Shipment::STATUS_IN_TRANSIT)
            ->orderBy('eta_date')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $names = $rows->take(5)->map(fn ($row) => $row->shipment_number ?: ('#'.$row->id))->implode(', ');
        $more = $rows->count() > 5 ? ' and '.($rows->count() - 5).' more' : '';

        return $this->raise([
            'event_key' => 'shipment.in_transit.daily',
            'fingerprint' => 'shipment.in_transit.daily:'.now()->toDateString(),
            'title' => $rows->count().' shipment'.($rows->count() === 1 ? '' : 's').' in transit',
            'body' => 'Update tracking today for '.$names.$more.'. Operations should leave a note on each file.',
            'severity' => OfficeAlert::SEVERITY_ATTENTION,
            'requires_ack' => false,
            'team' => 'operations',
            'action_url' => Route::has('shipments.index') ? route('shipments.index', ['status' => Shipment::STATUS_IN_TRANSIT]) : null,
            'action_label' => 'Open in-transit list',
        ]);
    }

    private function holdDigest(): ?OfficeAlert
    {
        if (! $this->sourceOn('digest_hold')) {
            return null;
        }

        $count = Shipment::query()->attention('hold')->count();
        if ($count === 0) {
            return null;
        }

        return $this->raise([
            'event_key' => 'shipment.hold.daily',
            'fingerprint' => 'shipment.hold.daily:'.now()->toDateString(),
            'title' => $count.' shipment'.($count === 1 ? '' : 's').' on hold or delayed',
            'body' => 'The list is the digest. Each file also has its own briefing until someone owns it.',
            'severity' => OfficeAlert::SEVERITY_ATTENTION,
            'requires_ack' => false,
            'team' => 'operations',
            'action_url' => Route::has('shipments.index') ? route('shipments.index', ['attention' => 'hold']) : null,
            'action_label' => 'Open holds',
        ]);
    }

    private function pendingCashflowDigest(): ?OfficeAlert
    {
        if (! $this->sourceOn('digest_cashflow')) {
            return null;
        }

        $count = CashflowEntry::query()->where('accounting_status', 'pending')->count();
        if ($count === 0) {
            return null;
        }

        return $this->raise([
            'event_key' => 'cashflow.pending.daily',
            'fingerprint' => 'cashflow.pending.daily:'.now()->toDateString(),
            'title' => $count.' cashflow '.Str::plural('entry', $count).' still pending',
            'body' => 'Book or reconcile the rows that are still pending so month-end is not a chase.',
            'severity' => OfficeAlert::SEVERITY_INFO,
            'requires_ack' => false,
            'team' => 'accounts',
            'action_url' => Route::has('cashflows.index') ? route('cashflows.index', ['accounting_status' => 'pending']) : null,
            'action_label' => 'Open pending',
        ]);
    }

    private function ensureToday(): void
    {
        $marker = $this->raise([
            'event_key' => 'briefing.ran',
            'fingerprint' => 'briefing.ran:'.now()->toDateString(),
            'title' => 'Daily briefing',
            'body' => 'Scheduled briefings have run for today.',
            'severity' => OfficeAlert::SEVERITY_INFO,
            'requires_ack' => false,
        ]);

        if ($marker->wasRecentlyCreated) {
            $this->runScheduled();
        }
    }

    private function raise(array $data, bool $email = false): OfficeAlert
    {
        $alert = OfficeAlert::query()->firstOrCreate(
            ['fingerprint' => $data['fingerprint']],
            $data
        );

        if ($email && $alert->wasRecentlyCreated && $alert->requires_ack) {
            $this->emailWatchers($alert);
        }

        return $alert;
    }

    private function emailWatchers(OfficeAlert $alert): void
    {
        if (! $this->settings()['emails']) {
            return;
        }

        $addresses = collect();

        if (Schema::hasTable('users')) {
            $addresses = User::query()
                ->get()
                ->filter(fn (User $user) => $user->watchesTeam($alert->team) && filled($user->email))
                ->pluck('email');
        }

        $extra = collect(preg_split('/[\s,;]+/', (string) $this->settings()['extra_emails']))
            ->filter();

        $addresses->merge($extra)->unique()->filter()->each(function ($email) use ($alert) {
            try {
                Mail::to($email)->send(new OfficeBriefingMail($alert));
            } catch (\Throwable $e) {
                // Mail is best-effort: a log driver or a down SMTP must not break the inbox.
            }
        });

        $alert->emailed_at = now();
        $alert->save();
    }

    private function settings(): array
    {
        return OfficeSetting::briefings();
    }

    private function sourceOn(string $source): bool
    {
        $settings = $this->settings();

        return $settings['enabled'] && ($settings['sources'][$source] ?? true);
    }

    private function state(OfficeAlert $alert, User $user): OfficeAlertState
    {
        return OfficeAlertState::query()->firstOrCreate(
            ['office_alert_id' => $alert->id, 'user_id' => $user->id]
        );
    }

    private function present(OfficeAlert $alert, ?OfficeAlertState $state): array
    {
        $snoozed = $state?->snoozed_until && $state->snoozed_until->isFuture();

        return [
            'id' => $alert->id,
            'title' => $alert->title,
            'body' => $alert->body,
            'severity' => $alert->severity,
            'severity_label' => $alert->severityLabel(),
            'requires_ack' => $alert->requires_ack,
            'team' => $alert->team,
            'team_label' => OfficeAlert::teamLabel($alert->team),
            'action_url' => $alert->action_url,
            'action_label' => $alert->action_label ?: 'Open',
            'when' => optional($alert->created_at)->diffForHumans(),
            'shared_ack' => (bool) $alert->requires_ack,
            'snoozed' => (bool) $snoozed,
            'snoozed_until' => $snoozed ? $state->snoozed_until->format('d M, H:i') : null,
            'popup_shown' => (bool) $state?->popup_at,
        ];
    }
}
