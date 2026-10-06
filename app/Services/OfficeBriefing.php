<?php

namespace App\Services;

use App\Mail\OfficeBriefingMail;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\OfficeAlert;
use App\Models\OfficeAlertState;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OfficeBriefing
{
    public function kycSubmitted(Client $client): OfficeAlert
    {
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

    public function runScheduled(): array
    {
        $raised = [];

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

    public function chaseUnacked(int $hours = 4): int
    {
        if (! Schema::hasTable('office_alerts')) {
            return 0;
        }

        $cutoff = now()->subHours($hours);
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
        if (! $user->isAdmin() || ! Schema::hasTable('office_alerts')) {
            return ['unread' => 0, 'critical' => 0, 'items' => [], 'toasts' => [], 'popup' => null];
        }

        $this->ensureToday();

        $items = $this->openFor($user);
        $active = $items->where('snoozed', false);
        $toasts = $active->where('severity', OfficeAlert::SEVERITY_INFO)->values();
        $popup = $active->first(function ($row) {
            return $row['requires_ack'] && $row['severity'] === OfficeAlert::SEVERITY_CRITICAL && ! $row['popup_shown'];
        });

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

        Shipment::query()->open()->attention('stale', 2)->limit(30)->get()
            ->each(function (Shipment $shipment) use (&$raised) {
                $number = $shipment->shipment_number ?: '#'.$shipment->id;
                $raised[] = $this->raise([
                    'event_key' => 'shipment.stale',
                    'fingerprint' => 'shipment.stale:'.$shipment->id.':'.now()->toDateString(),
                    'title' => $number.' has had no tracking note in 48 hours',
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

        return $raised;
    }

    private function inTransitDigest(): ?OfficeAlert
    {
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
        if (! Schema::hasTable('users')) {
            return;
        }

        User::query()
            ->get()
            ->filter(fn (User $user) => $user->watchesTeam($alert->team) && filled($user->email))
            ->each(function (User $user) use ($alert) {
                try {
                    Mail::to($user->email)->send(new OfficeBriefingMail($alert));
                } catch (\Throwable $e) {
                    // Mail is best-effort: a log driver or a down SMTP must not break the inbox.
                }
            });

        $alert->emailed_at = now();
        $alert->save();
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
