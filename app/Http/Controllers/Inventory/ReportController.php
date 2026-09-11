<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\InventoryReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports & Analytics.
 *
 * Split out of InventoryController, which held reports() alongside the other
 * read-only screens as a one-line `return view(...)` with no data behind it.
 * This screen has real aggregation behind it, so it follows the same shape as
 * DemandForecastController: a thin controller over a service that owns the
 * arithmetic, which is also what makes the figures testable on their own.
 */
class ReportController extends Controller implements HasMiddleware
{
    public function __construct(private readonly InventoryReportService $reports) {}

    /**
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewReports->value),
        ];
    }

    public function index(Request $request): View
    {
        $periodParam = $request->input('period');
        $fromParam = $request->input('from');
        $toParam = $request->input('to');

        $customFrom = null;
        $customTo = null;
        $days = max(1, min(365, (int) $request->integer('days', InventoryReportService::DEFAULT_PERIOD_DAYS)));

        if ($periodParam === 'custom' && $fromParam && $toParam) {
            try {
                $parsedFrom = Carbon::parse($fromParam)->startOfDay();
                $parsedTo = Carbon::parse($toParam)->endOfDay();
                if ($parsedFrom->lte($parsedTo) && $parsedTo->lte(now()->endOfDay())) {
                    $customFrom = $parsedFrom;
                    $customTo = $parsedTo;
                }
            } catch (\Throwable) {
            }
        } elseif ($periodParam === '1') {
            $days = 1;
        } elseif ($periodParam === '7') {
            $days = 7;
        } elseif ($periodParam === '30') {
            $days = 30;
        } elseif ($periodParam === '90') {
            $days = 90;
        } elseif ($periodParam === '365') {
            $days = 365;
        } elseif ($periodParam === 'all') {
            $customFrom = Carbon::createFromTimestamp(0);
            $customTo = now();
        }

        return view('inventory.reports.index', [
            ...$this->reports->build($days, $customFrom, $customTo),
            'periodOptions' => InventoryReportService::PERIOD_OPTIONS,
            'reportTypes' => InventoryReportService::REPORT_TYPES,
            'exportFormats' => InventoryReportService::EXPORT_FORMATS,
            'categories' => ItemCategory::orderBy('name')->get(['id', 'name', 'code']),
            'locations' => StorageLocation::active()->orderBy('name')->get(['id', 'name', 'code']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'movementTypes' => MovementType::cases(),
            'currentPeriod' => $periodParam ?? ($request->has('days') ? (string) $days : '30'),
            'currentFrom' => $fromParam,
            'currentTo' => $toParam,
        ]);
    }

    /**
     * Generate and export custom filtered reports across all inventory modules.
     */
    public function generate(Request $request): View|JsonResponse|StreamedResponse|Response
    {
        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(array_keys(InventoryReportService::REPORT_TYPES))],
            'format' => ['required', 'string', Rule::in(['pdf', 'print', 'excel', 'csv', 'json'])],
            'period' => ['nullable', 'string', Rule::in(['1', '7', '30', '90', '365', 'all', 'custom'])],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'from' => ['nullable', 'required_if:period,custom', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'category_id' => ['nullable', 'integer', 'exists:item_categories,id'],
            'storage_location_id' => ['nullable', 'integer', 'exists:storage_locations,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'movement_type' => ['nullable', 'string', Rule::enum(MovementType::class)],
            'status' => ['nullable', 'string', Rule::in(['all', 'in_stock', 'low_stock', 'out_of_stock'])],
            'sort_by' => ['nullable', 'string', Rule::in(['date', 'value', 'units', 'items', 'name', 'status', 'orders', 'utilisation', 'movements', 'amount', 'supplier', 'fulfilment'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ], [
            'from.before_or_equal' => 'The start date cannot be a future date.',
            'to.before_or_equal' => 'The end date cannot be a future date.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
            'from.required_if' => 'Please provide a start date for the custom date range.',
            'to.required_if' => 'Please provide an end date for the custom date range.',
        ]);

        // Financial role boundary check
        if (in_array($validated['report_type'], ['procurement_expense', 'spend_by_supplier'], true)) {
            if (! $request->user()->can(Permission::ViewProcurementSensitiveData->value)) {
                abort(403, 'You do not have permission to view procurement financial reports.');
            }
        }

        $report = $this->reports->generateReport($validated, $request->user());

        return match ($validated['format']) {
            'json' => $this->reports->exportJson($report),
            'csv' => $this->reports->exportCsv($report),
            'excel' => response()->view('inventory.reports.export-excel', [
                'report' => $report,
            ], 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="hims-'.$report['meta']['report_type'].'-'.now()->format('Ymd_His').'.xls"',
            ]),
            default => view('inventory.reports.export-print', [
                'report' => $report,
                'autoPrint' => (bool) $request->boolean('auto_print', true),
            ]),
        };
    }
}

