<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'event' => trim((string) $request->query('event')),
            'outcome' => trim((string) $request->query('outcome')),
            'actor_type' => trim((string) $request->query('actor_type')),
            'from' => trim((string) $request->query('from')),
            'to' => trim((string) $request->query('to')),
        ];
        foreach (['from','to'] as $dateKey) {
            if ($filters[$dateKey] !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dateKey])) $filters[$dateKey] = '';
        }
        $logs = AuditLog::query()
            ->when($filters['search'], fn ($query, $value) => $query->where(fn ($nested) => $nested
                ->where('actor_name', 'like', "%{$value}%")->orWhere('actor_identifier', 'like', "%{$value}%")
                ->orWhere('route_name', 'like', "%{$value}%")->orWhere('event', 'like', "%{$value}%")))
            ->when($filters['event'], fn ($query, $value) => $query->where('event', $value))
            ->when(in_array($filters['outcome'], ['success','failed','denied'], true), fn ($query) => $query->where('outcome', $filters['outcome']))
            ->when($filters['actor_type'], fn ($query, $value) => $query->where('actor_type', $value))
            ->when($filters['from'], fn ($query, $value) => $query->whereDate('occurred_at', '>=', $value))
            ->when($filters['to'], fn ($query, $value) => $query->whereDate('occurred_at', '<=', $value))
            ->latest('occurred_at')->latest('id')->paginate(30)->withQueryString();

        return view('audit.index', [
            'logs' => $logs, 'filters' => $filters,
            'events' => AuditLog::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'actorTypes' => AuditLog::query()->whereNotNull('actor_type')->distinct()->orderBy('actor_type')->pluck('actor_type'),
            'users' => User::query()->where('role', '!=', User::ROLE_EMPLOYEE)->orderBy('name')->get(),
        ]);
    }
}
