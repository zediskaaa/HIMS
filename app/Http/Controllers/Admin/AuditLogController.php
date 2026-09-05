<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
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
        return ['auth:web,admin,super_admin', 'super-admin'];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $searchTerm = trim($filters['search'] ?? '');
        $matchingActions = $this->matchingActionValues($searchTerm);

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
}
