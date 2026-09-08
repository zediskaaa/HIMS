<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\View\View;

/**
 * Renders the access-control matrix: every role against every ability.
 *
 * This is a read-only view of App\Enums\UserRole::permissions() rather than a
 * second copy of the rules — the point is that the matrix cannot drift from
 * what the gates actually enforce, because it is generated from the same enum
 * the gates are registered from. If a permission is added or moved, this screen
 * changes with it and nothing here needs editing.
 *
 * It exists so the access rules can be reviewed by reading one screen instead
 * of by opening twelve controllers.
 */
class PermissionMatrixController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        return ['auth:web,admin,super_admin', 'can:'.Permission::ManageUsers->value];
    }

    public function index(): View
    {
        $roles = UserRole::cases();

        // Headcount per role, so the matrix reads against real accounts rather
        // than in the abstract — a role nobody holds is worth noticing.
        $accountCounts = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('admin.permissions.index', [
            'roles' => $roles,
            'modules' => Permission::byModule(),
            'totalPermissions' => count(Permission::cases()),
            'accountCounts' => $accountCounts,
            'permissionCounts' => collect($roles)
                ->mapWithKeys(fn (UserRole $role) => [$role->value => count($role->permissions())])
                ->all(),
        ]);
    }
}
