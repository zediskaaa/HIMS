<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\InventoryController;
use Illuminate\View\View;

/**
 * Dedicated entry point for the Super Admin panel.
 *
 * It intentionally reuses the current operations dashboard data and view so
 * Super Admin starts with exactly the Administrator experience. This class is
 * the seam where a distinct panel can be introduced later.
 */
class DashboardController extends Controller
{
    public function __invoke(InventoryController $dashboard): View
    {
        return $dashboard->index()->with('superAdminPanel', true);
    }
}
