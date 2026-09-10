<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller implements HasMiddleware
{
    private const SUGGESTION_LIMIT = 8;

    private const SUGGESTIONS_PER_SOURCE = 3;

    private const SUGGESTION_SCAN_LIMIT = 24;

    /**
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        return ['auth:web,admin,super_admin', 'can:'.Permission::ViewAuditTrail->value];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'target' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'actor_role' => ['nullable', Rule::enum(UserRole::class)],
            'category' => ['nullable', Rule::in(array_keys(AuditAction::categories()))],
            'module' => ['nullable', Rule::in(array_keys(AuditAction::modules()))],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'outcome' => ['nullable', Rule::in(['success', 'failure'])],
            'source' => ['nullable', Rule::in(['user', 'system', 'scheduled_job', 'integration'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $searchTerm = trim($filters['search'] ?? '');
        $targetTerm = trim($filters['target'] ?? '');
        $matchingActions = $this->matchingActionValues($searchTerm);
        $categoryActions = $this->actionValuesFor('category', $filters['category'] ?? null);
        $moduleActions = $this->actionValuesFor('module', $filters['module'] ?? null);
        $fromLocal = filled($filters['date_from'] ?? null)
            ? CarbonImmutable::parse($filters['date_from'], config('app.timezone'))->startOfDay()
            : null;
        $toLocal = filled($filters['date_to'] ?? null)
            ? CarbonImmutable::parse($filters['date_to'], config('app.timezone'))->endOfDay()
            : null;

        $logs = AuditLog::query()
            ->when($searchTerm !== '', function ($query) use ($matchingActions, $searchTerm): void {
                $term = '%'.$searchTerm.'%';

                $query->where(function ($q) use ($matchingActions, $term): void {
                    $q->where('actor_name', 'like', $term)
                        ->orWhere('actor_employee_id', 'like', $term)
                        ->orWhere('target_name', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('old_values->email', 'like', $term)
                        ->orWhere('new_values->email', 'like', $term)
                        ->orWhere('ip_address', 'like', $term);

                    if ($matchingActions !== []) {
                        $q->orWhereIn('action', $matchingActions);
                    }
                });
            })
            ->when($targetTerm !== '', function ($query) use ($targetTerm): void {
                $term = '%'.$targetTerm.'%';
                $query->where(fn ($target) => $target
                    ->where('target_name', 'like', $term)
                    ->orWhere('target_reference', 'like', $term)
                    ->orWhere('target_id', 'like', $term));
            })
            ->when(filled($filters['actor_id'] ?? null), fn ($query) => $query->where('user_id', $filters['actor_id']))
            ->when(filled($filters['actor_role'] ?? null), fn ($query) => $query->where('actor_role', $filters['actor_role']))
            ->when(filled($filters['category'] ?? null), fn ($query) => $query->where(
                fn ($categoryQuery) => $categoryQuery
                    ->where('event_category', $filters['category'])
                    ->orWhere(fn ($legacy) => $legacy->whereNull('event_category')->whereIn('action', $categoryActions)),
            ))
            ->when(filled($filters['module'] ?? null), fn ($query) => $query->where(
                fn ($moduleQuery) => $moduleQuery
                    ->where('module', $filters['module'])
                    ->orWhere(fn ($legacy) => $legacy->whereNull('module')->whereIn('action', $moduleActions)),
            ))
            ->when(filled($filters['action'] ?? null), fn ($query) => $query->where('action', $filters['action']))
            ->when(filled($filters['outcome'] ?? null), fn ($query) => $query->where('outcome', $filters['outcome']))
            ->when(filled($filters['source'] ?? null), fn ($query) => $query->where('source', $filters['source']))
            ->when($fromLocal !== null, fn ($query) => $this->whereAuditTime($query, '>=', $fromLocal))
            ->when($toLocal !== null, fn ($query) => $this->whereAuditTime($query, '<=', $toLocal))
            ->orderByRaw('CASE WHEN occurred_at_utc IS NULL THEN 1 ELSE 0 END')
            ->latest('occurred_at_utc')
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditAction::options(),
            'categories' => AuditAction::categories(),
            'modules' => AuditAction::modules(),
            'roles' => UserRole::options(),
            'actors' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
            'outcomes' => ['success' => 'Success', 'failure' => 'Failure'],
            'sources' => [
                'user' => 'User',
                'system' => 'System',
                'scheduled_job' => 'Scheduled job',
                'integration' => 'Integration',
            ],
            'filters' => $filters,
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit-logs.show', ['log' => $auditLog]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
        ]);
        $term = trim($validated['query'] ?? '');

        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $suggestions = [];
        $seen = [];
        $seenIdentities = [];
        $categoryCounts = [];
        $addSuggestion = function (
            ?string $value,
            string $category,
            ?string $identity = null,
        ) use (&$suggestions, &$seen, &$seenIdentities, &$categoryCounts): void {
            $value = trim((string) $value);
            $key = mb_strtolower($value);
            $identityKey = $identity === null ? null : mb_strtolower($identity);

            if (
                $value === ''
                || isset($seen[$key])
                || ($identityKey !== null && isset($seenIdentities[$identityKey]))
                || ($categoryCounts[$category] ?? 0) >= self::SUGGESTIONS_PER_SOURCE
                || count($suggestions) >= self::SUGGESTION_LIMIT
            ) {
                return;
            }

            $seen[$key] = true;
            if ($identityKey !== null) {
                $seenIdentities[$identityKey] = true;
            }
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $suggestions[] = [
                'value' => $value,
                'category' => $category,
            ];
        };

        $prefix = $term.'%';
        $sources = [
            [
                'column' => 'actor_name',
                'category' => 'Performed by',
                'identity_columns' => ['user_id'],
                'identity' => fn (AuditLog $log) => $log->user_id === null ? null : 'person:'.$log->user_id,
            ],
            [
                'column' => 'target_name',
                'category' => 'Target',
                'identity_columns' => ['target_type', 'target_id'],
                'identity' => function (AuditLog $log): ?string {
                    if ($log->target_type === null || $log->target_id === null) {
                        return null;
                    }

                    return $log->target_type === User::class
                        ? 'person:'.$log->target_id
                        : 'target:'.$log->target_type.':'.$log->target_id;
                },
            ],
            [
                'column' => 'actor_employee_id',
                'category' => 'Employee ID',
                'identity_columns' => ['user_id'],
                'identity' => fn (AuditLog $log) => $log->user_id === null ? null : 'employee:'.$log->user_id,
            ],
            [
                'column' => 'ip_address',
                'category' => 'IP address',
                'identity_columns' => [],
                'identity' => fn (AuditLog $log) => null,
            ],
        ];

        foreach ($sources as $source) {
            $column = $source['column'];

            AuditLog::query()
                ->whereNotNull($column)
                ->where($column, 'like', $prefix)
                ->latest('id')
                ->limit(self::SUGGESTION_SCAN_LIMIT)
                ->get(array_merge([$column], $source['identity_columns']))
                ->each(fn (AuditLog $log) => $addSuggestion(
                    $log->getAttribute($column),
                    $source['category'],
                    $source['identity']($log),
                ));
        }

        AuditLog::query()
            ->where(fn ($query) => $query
                ->where('old_values->email', 'like', $prefix)
                ->orWhere('new_values->email', 'like', $prefix))
            ->latest('id')
            ->limit(self::SUGGESTION_SCAN_LIMIT)
            ->get(['target_type', 'target_id', 'old_values', 'new_values'])
            ->each(function (AuditLog $log) use ($addSuggestion, $term): void {
                $identity = $log->target_type !== null && $log->target_id !== null
                    ? 'email:'.$log->target_type.':'.$log->target_id
                    : null;

                foreach ([$log->new_values['email'] ?? null, $log->old_values['email'] ?? null] as $email) {
                    if (is_string($email) && Str::startsWith(Str::lower($email), Str::lower($term))) {
                        $addSuggestion($email, 'Email', $identity);
                    }
                }
            });

        $matchingActions = $this->matchingActionValues($term);

        if ($matchingActions !== []) {
            $existingActions = AuditLog::query()
                ->whereIn('action', $matchingActions)
                ->distinct()
                ->pluck('action')
                ->map(fn (AuditAction|string $action) => $action instanceof AuditAction ? $action->value : $action)
                ->all();

            foreach (AuditAction::cases() as $action) {
                if (in_array($action->value, $existingActions, true)) {
                    $addSuggestion($action->label(), 'Action');
                }
            }
        }

        return response()->json(['data' => $suggestions]);
    }

    /**
     * @return list<string>
     */
    private function matchingActionValues(string $term): array
    {
        if ($term === '') {
            return [];
        }

        return collect(AuditAction::cases())
            ->filter(fn (AuditAction $action) => Str::contains(
                Str::lower($action->label()),
                Str::lower($term),
            ))
            ->map(fn (AuditAction $action) => $action->value)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function actionValuesFor(string $method, ?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        return collect(AuditAction::cases())
            ->filter(fn (AuditAction $action) => $value === $action->{$method}())
            ->map(fn (AuditAction $action) => $action->value)
            ->values()
            ->all();
    }

    private function whereAuditTime($query, string $operator, CarbonImmutable $localBoundary): void
    {
        $query->where(function ($timeQuery) use ($operator, $localBoundary): void {
            $timeQuery->where('occurred_at_utc', $operator, $localBoundary->utc()->format('Y-m-d H:i:s.u'))
                ->orWhere(function ($legacy) use ($operator, $localBoundary): void {
                    $legacy->whereNull('occurred_at_utc')
                        ->where('created_at', $operator, $localBoundary->format('Y-m-d H:i:s'));
                });
        });
    }
}
