<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ArchiveController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ArchiveService $archiveService,
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewArchive->value, only: ['index']),
            new Middleware('can:'.Permission::ManageArchive->value, only: [
                'archiveItem', 'unarchiveItem',
                'archiveSupplier', 'unarchiveSupplier',
                'archiveUser', 'unarchiveUser',
            ]),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['type', 'search', 'archive_date_from', 'archive_date_to', 'archived_by']);
        $records = $this->archiveService->getArchivedRecords($filters, 15);
        $counts = $this->archiveService->getArchiveCounts();

        $archivedByUsers = User::query()
            ->whereIn('id', function ($query): void {
                $query->select('archived_by')->from('inventory_items')->whereNotNull('archived_by')
                    ->union(DB::table('suppliers')->select('archived_by')->whereNotNull('archived_by'))
                    ->union(DB::table('users')->select('archived_by')->whereNotNull('archived_by'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        return view('admin.archive.index', [
            'records' => $records,
            'counts' => $counts,
            'filters' => $filters,
            'currentType' => $filters['type'] ?? 'all',
            'archivedByUsers' => $archivedByUsers,
        ]);
    }

    public function archiveItem(Request $request, InventoryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = filled($validated['reason'] ?? null) ? trim((string) $validated['reason']) : null;
        $this->archiveService->archiveItem($item, $request->user(), $reason);

        return back()->with('success', "Inventory item '{$item->name}' has been archived and removed from active lists.");
    }

    public function unarchiveItem(Request $request, InventoryItem $item): RedirectResponse
    {
        $this->archiveService->unarchiveItem($item, $request->user());

        return back()->with('success', "Inventory item '{$item->name}' has been successfully restored to the active catalog.");
    }

    public function archiveSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = filled($validated['reason'] ?? null) ? trim((string) $validated['reason']) : null;
        $this->archiveService->archiveSupplier($supplier, $request->user(), $reason);

        return back()->with('success', "Supplier '{$supplier->name}' has been archived from active procurement.");
    }

    public function unarchiveSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->archiveService->unarchiveSupplier($supplier, $request->user());

        return back()->with('success', "Supplier '{$supplier->name}' has been successfully restored to active status.");
    }

    public function archiveUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = filled($validated['reason'] ?? null) ? trim((string) $validated['reason']) : null;
        $this->archiveService->archiveUser($user, $request->user(), $reason);

        return back()->with('success', "User account for '{$user->name}' has been archived and deactivated.");
    }

    public function unarchiveUser(Request $request, User $user): RedirectResponse
    {
        $this->archiveService->unarchiveUser($user, $request->user());

        return back()->with('success', "User account for '{$user->name}' has been restored to active status.");
    }
}
