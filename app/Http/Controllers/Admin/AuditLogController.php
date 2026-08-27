<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        return ['auth', 'can:'.Permission::ViewAuditTrail->value];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $logs = AuditLog::query()
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $term = '%'.trim($filters['search']).'%';

                $query->where(fn ($q) => $q
                    ->where('actor_name', 'like', $term)
                    ->orWhere('actor_employee_id', 'like', $term)
                    ->orWhere('target_name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('ip_address', 'like', $term));
            })
            ->when(filled($filters['action'] ?? null), fn ($query) => $query->where('action', $filters['action']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->where(
                'created_at',
                '>=',
                CarbonImmutable::parse($filters['date_from'], config('app.timezone'))->startOfDay(),
            ))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->where(
                'created_at',
                '<=',
                CarbonImmutable::parse($filters['date_to'], config('app.timezone'))->endOfDay(),
            ))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditAction::options(),
            'filters' => $filters,
        ]);
    }
}
