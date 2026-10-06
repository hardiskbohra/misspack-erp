<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\OfficeAlert;
use App\Models\OfficeAlertState;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The office's briefing: a fact that somebody on the floor has to act on.
 *
 * Toasts (info) show once. Follow-ups (attention) stay until dismissed.
 * Critical items stay on screen until the person marks them read.
 */
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
        ]);
    }

    public function runScheduled(): array
    {
        $raised = [];

        if (class_exists(Shipment::class) && Schema::hasTable('shipments')) {
            $raised[] = $this->inTransitDigest();
            $raised[] = $this->holdDigest();
        }

        if (class_exists(CashflowEntry::class) && Schema::hasTable('cashflow_entries')) {
            $raised[] = $this->pendingCashflowDigest();
        }

        return array_values(array_filter($raised));
    }

    public function payload(User $user): array
    {
        if (! $user->isAdmin() || ! Schema::hasTable('office_alerts')) {
            return ['unread' => 0, 'critical' => 0, 'items' => [], 'toasts' => [], 'popup' => null];
        }

        $this->ensureToday();

        $items = $this->openFor($user);
        $toasts = $items->where('severity', OfficeAlert::SEVERITY_INFO)->values();
        $popup = $items->first(fn ($row) => $row['requires_ack'] && $row['severity'] === OfficeAlert::SEVERITY_CRITICAL)
            ?? $items->first(fn ($row) => $row['severity'] === OfficeAlert::SEVERITY_ATTENTION);

        return [
            'unread' => $items->count(),
            'critical' => $items->where('severity', OfficeAlert::SEVERITY_CRITICAL)->count(),
            'items' => $items->values()->all(),
            'toasts' => $toasts->all(),
            'popup' => $popup,
            'ack_url' => route('office-alerts.ack', ['office_alert' => '__id__']),
            'seen_url' => route('office-alerts.seen', ['office_alert' => '__id__']),
        ];
    }

    public function openFor(User $user): Collection
    {
        $states = OfficeAlertState::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('office_alert_id');

        return OfficeAlert::query()
            ->where('event_key', '!=', 'briefing.ran')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'attention' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->filter(function (OfficeAlert $alert) use ($states) {
                $state = $states->get($alert->id);
                if ($alert->requires_ack) {
                    return ! $state?->acked_at;
                }

                return ! $state?->seen_at;
            })
            ->map(fn (OfficeAlert $alert) => $this->present($alert))
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
        $state->save();
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
            'severity' => OfficeAlert::SEVERITY_CRITICAL,
            'requires_ack' => true,
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
            'body' => 'Customs hold or delay — chase the forwarder and leave a tracking note so the file is not silent.',
            'severity' => OfficeAlert::SEVERITY_CRITICAL,
            'requires_ack' => true,
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

    private function raise(array $data): OfficeAlert
    {
        return OfficeAlert::query()->firstOrCreate(
            ['fingerprint' => $data['fingerprint']],
            $data
        );
    }

    private function state(OfficeAlert $alert, User $user): OfficeAlertState
    {
        return OfficeAlertState::query()->firstOrCreate(
            ['office_alert_id' => $alert->id, 'user_id' => $user->id]
        );
    }

    private function present(OfficeAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'title' => $alert->title,
            'body' => $alert->body,
            'severity' => $alert->severity,
            'severity_label' => $alert->severityLabel(),
            'requires_ack' => $alert->requires_ack,
            'team' => $alert->team,
            'action_url' => $alert->action_url,
            'action_label' => $alert->action_label ?: 'Open',
            'when' => optional($alert->created_at)->diffForHumans(),
        ];
    }
}
