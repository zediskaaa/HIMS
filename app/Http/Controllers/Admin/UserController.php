<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserDepartment;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class UserController extends Controller implements HasMiddleware
{
    public function __construct(private readonly UserAccountService $accounts) {}

    /**
     * Every action here is administrator-only. Declaring it on the controller
     * rather than the route group means a new method cannot be added without
     * inheriting the guard.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return ['auth', 'can:'.Permission::ManageUsers->value];
    }

    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';

                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('surname', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('middle_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('employee_id', 'like', $term)
                    ->orWhere('department', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::options(),
            'statuses' => UserStatus::options(),
            'filters' => $request->only(['search', 'role', 'status']),
            'counts' => [
                'total' => User::count(),
                'active' => User::active()->count(),
                'administrators' => User::administrators()->active()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => UserRole::cases(),
            'departments' => UserDepartment::options(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->accounts->create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', sprintf('%s was added as %s.', $user->name, $user->role->label()));
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user,
            'recentMovements' => $user->stockMovements()
                ->with(['item', 'fromLocation', 'toLocation'])
                ->latest('moved_at')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::options(),
            'departments' => UserDepartment::optionsIncluding($user->department),
        ]);
    }

    /**
     * The service throws a ValidationException when an edit would lock the
     * system out, which Laravel turns into a redirect back with the message.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->accounts->update($user, $request->validated(), $request->user());

        return redirect()
            ->route('admin.users.index')
            ->with('success', sprintf("%s's account was updated.", $user->name));
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $updated = $this->accounts->toggleStatus($user, $request->user());

        return redirect()
            ->back()
            ->with('success', sprintf(
                '%s is now %s.',
                $updated->name,
                $updated->status->label()
            ));
    }
}
