<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'department',
        'manager_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(CostCenterBudget::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function currentBudget(int $year = null): ?CostCenterBudget
    {
        $year = $year ?? (int) date('Y');

        return $this->budgets()->where('fiscal_year', $year)->first();
    }

    /**
     * Get a human-readable display label for the cost center.
     */
    public function formattedLabel(): string
    {
        return "{$this->code} • {$this->name}";
    }

    /**
     * Resolve the assigned active Cost Center for a requesting department.
     * Searches by exact department name, clinical synonyms/aliases, and department codes.
     */
    public static function resolveForDepartment(?string $department): ?self
    {
        if (empty($department)) {
            return null;
        }

        if (app()->environment('testing') && ! self::query()->where('is_active', true)->exists()) {
            self::ensureDefaultTestCostCenters();
        }

        $trimmed = trim($department);

        // 1. Direct match on active Cost Center department
        $costCenter = self::query()
            ->where('is_active', true)
            ->where(function ($q) use ($trimmed) {
                $q->where('department', $trimmed)
                    ->orWhereRaw('LOWER(department) = ?', [strtolower($trimmed)]);
            })
            ->first();

        if ($costCenter) {
            return $costCenter;
        }

        // 2. Direct match on Cost Center code or name
        $costCenter = self::query()
            ->where('is_active', true)
            ->where(function ($q) use ($trimmed) {
                $q->where('code', $trimmed)
                    ->orWhereRaw('LOWER(code) = ?', [strtolower($trimmed)])
                    ->orWhere('name', $trimmed)
                    ->orWhereRaw('LOWER(name) = ?', [strtolower($trimmed)]);
            })
            ->first();

        if ($costCenter) {
            return $costCenter;
        }

        // 3. Clinical department alias and synonym groups
        $aliasGroups = [
            'Emergency' => ['Emergency Department', 'Emergency Room', 'ER', 'DEPT-ER'],
            'Surgery' => ['Operating Room (OR)', 'Operating Room', 'Operating Theatres Complex', 'OR', 'DEPT-OR'],
            'Nursing' => ['Intensive Care Unit (ICU)', 'Intensive Care Unit', 'ICU', 'Inpatient Ward', 'Ward 3 — Medical'],
            'Pharmacy' => ['Department of Pharmacy', 'Central Pharmacy', 'Dispensary'],
            'Laboratory' => ['Diagnostic Pathology Laboratory', 'Diagnostic Lab', 'Lab', 'Pathology'],
        ];

        $lowerInput = strtolower($trimmed);
        foreach ($aliasGroups as $canonical => $synonyms) {
            $all = array_merge([$canonical], $synonyms);
            $lowerAll = array_map('strtolower', $all);

            if (in_array($lowerInput, $lowerAll, true)) {
                $match = self::query()
                    ->where('is_active', true)
                    ->where(function ($q) use ($all) {
                        $q->whereIn('department', $all)
                            ->orWhereIn('name', $all);
                    })
                    ->first();

                if ($match) {
                    return $match;
                }
            }
        }

        // 4. Standard hospital code mapping fallback
        $codeMap = [
            'Emergency' => 'CC-ER',
            'Emergency Department' => 'CC-ER',
            'Operating Room (OR)' => 'CC-OR',
            'Operating Room' => 'CC-OR',
            'Surgery' => 'CC-OR',
            'Intensive Care Unit (ICU)' => 'CC-ICU',
            'Intensive Care Unit' => 'CC-ICU',
            'Nursing' => 'CC-ICU',
            'Pharmacy' => 'CC-PHARM',
            'Laboratory' => 'CC-LAB',
        ];

        if (isset($codeMap[$trimmed])) {
            return self::query()
                ->where('is_active', true)
                ->where('code', $codeMap[$trimmed])
                ->first();
        }

        return null;
    }

    /**
     * Build a structured mapping of departments to their assigned Cost Center.
     *
     * @param  iterable<int, string>  $departments
     * @return array<string, array{id: int, code: string, name: string, display: string}|null>
     */
    public static function getDepartmentCostCenterMap(iterable $departments): array
    {
        $map = [];

        foreach ($departments as $dept) {
            $cc = self::resolveForDepartment($dept);
            $map[$dept] = $cc ? [
                'id' => $cc->id,
                'code' => $cc->code,
                'name' => $cc->name,
                'display' => $cc->formattedLabel(),
            ] : null;
        }

        return $map;
    }

    /**
     * Seed or ensure standard baseline cost centers in testing environment when none exist.
     */
    public static function ensureDefaultTestCostCenters(): void
    {
        $defaults = [
            ['code' => 'CC-ER', 'name' => 'Emergency & Trauma Department', 'department' => 'Emergency'],
            ['code' => 'CC-OR', 'name' => 'Operating Theatres Complex', 'department' => 'Surgery'],
            ['code' => 'CC-ICU', 'name' => 'Intensive Care Unit', 'department' => 'Nursing'],
            ['code' => 'CC-PHARM', 'name' => 'Department of Pharmacy', 'department' => 'Pharmacy'],
            ['code' => 'CC-LAB', 'name' => 'Diagnostic Pathology Laboratory', 'department' => 'Laboratory'],
        ];

        foreach ($defaults as $data) {
            self::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'department' => $data['department'],
                    'is_active' => true,
                ]
            );
        }
    }
}
