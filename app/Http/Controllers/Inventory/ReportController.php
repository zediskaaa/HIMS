<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

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
        // Clamped rather than validated: the value arrives from a <select> on
        // the page, and a nonsense query string should re-render the default
        // window rather than throw an error at somebody reading a report.
        $days = max(1, min(365, (int) $request->integer('days', InventoryReportService::DEFAULT_PERIOD_DAYS)));

        return view('inventory.reports.index', [
            ...$this->reports->build($days),
            'periodOptions' => InventoryReportService::PERIOD_OPTIONS,
        ]);
    }
}
