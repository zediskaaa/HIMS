<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivationCancellationReason;
use App\Enums\Permission;
use App\Enums\UserDepartment;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Services\UserAccountService;
use App\Services\SupplierInvitationService;
use App\Support\AuthenticationContext;
use App\Support\SuperAdminPasswordConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly SupplierInvitationService $supplierInvitations,
    ) {}

    /**
     * Every action here is administrator-only. Declaring it on the controller
     * rather than the route group means a new method cannot be added without
     * inheriting the guard.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return ['auth:web,admin,super_admin', 'can:'.Permission::ManageUsers->value];
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
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                fn ($q) => $q->where('status', '!=', UserStatus::Archived->value)
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $countRow = User::query()
            ->selectRaw('SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) AS total_count', [UserStatus::Archived->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND email_verified_at IS NOT NULL THEN 1 ELSE 0 END) AS active_count', [UserStatus::Active->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND email_verified_at IS NOT NULL AND role IN (?, ?) THEN 1 ELSE 0 END) AS administrator_count', [
                UserStatus::Active->value,
                UserRole::Administrator->value,
                UserRole::SuperAdministrator->value,
            ])
            ->first();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::options(),
            'createRoles' => $this->accounts->assignableRoles($request->user()),
            'statuses' => UserStatus::filterOptions(),
            'departments' => UserDepartment::options(),
            'filters' => $request->only(['search', 'role', 'status']),
            'counts' => [
                'total' => (int) $countRow->total_count,
                'active' => (int) $countRow->active_count,
                'administrators' => (int) $countRow->administrator_count,
            ],
            'manageableAccountIds' => $users->getCollection()
                ->filter(fn (User $user) => $this->accounts->canManage($request->user(), $user))
                ->modelKeys(),
            'unlockableAccountIds' => $users->getCollection()
                ->filter(fn (User $user) => $this->accounts->canUnlock($request->user(), $user))
                ->modelKeys(),
        ]);
    }

    public function create(): View
    {
        return $this->index(request());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->accounts->create($request->validated(), $request->user());

        return redirect()
            ->route(AuthenticationContext::administrationRoute('users.index'))
            ->with('success', 'Account created as Pending Activation. The user must activate it and create their own password from the login page.');
    }

    public function show(User $user): View
    {
        $user->load('activationCancelledBy');

        return view('admin.users.show', [
            'user' => $user,
            'canManage' => $this->accounts->canManage(request()->user(), $user),
            'canUnlock' => $this->accounts->canUnlock(request()->user(), $user),
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
        abort_unless($this->accounts->canManage(request()->user(), $user), 403);

        return $this->index(request())->with([
            'editUser' => $user,
            'editRoles' => $this->accounts->assignableRoles(request()->user(), $user),
            'editDepartments' => UserDepartment::optionsIncluding($user->department),
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
            ->route(AuthenticationContext::administrationRoute('users.index'))
            ->with('success', sprintf("%s's account was updated.", $user->name));
    }

    public function confirmPassword(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor?->isSuperAdministrator()) {
            abort(403, 'Only a Super Administrator can verify this action.');
        }

        $request->validate([
            'current_password' => ['required', 'string'],
        ], [
            'current_password.required' => 'Current password is required.',
        ]);

        if (! Hash::check($request->string('current_password')->toString(), $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $token = SuperAdminPasswordConfirmation::issueToken($request, $actor);

        return response()->json([
            'status' => 'confirmed',
            'token' => $token,
        ]);
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->accounts->canManage($request->user(), $user), 403);

        if ($request->user()?->isSuperAdministrator() && $user->isActive()) {
            SuperAdminPasswordConfirmation::validate($request, $request->user());
        }

        $updated = $this->accounts->toggleStatus($user, $request->user());

        if ($updated->isPendingActivation()) {
            $updated->notify(new AccountCreated);

            return back()->with('success', sprintf(
                'A new activation invitation was sent to %s.',
                $updated->email,
            ));
        }

        return redirect()
            ->back()
            ->with('success', $updated->isActive()
                ? sprintf("%s's account was reactivated successfully.", $updated->name)
                : sprintf("%s's account was deactivated.", $updated->name));
    }

    public function cancelInvitation(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->accounts->canManage($request->user(), $user), 403);

        $validated = $request->validateWithBag('cancelActivation', [
            'cancellation_reason' => ['required', Rule::enum(ActivationCancellationReason::class)],
            'cancellation_details' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:cancellation_reason,'.ActivationCancellationReason::Other->value,
            ],
            'cancel_user_id' => ['required', 'integer', 'in:'.$user->getKey()],
        ], [
            'cancellation_reason.required' => 'Select a reason for cancelling activation.',
            'cancellation_details.required_if' => 'Additional details are required when Other is selected.',
        ]);

        $details = filled($validated['cancellation_details'] ?? null)
            ? trim($validated['cancellation_details'])
            : null;
        $cancelled = $this->accounts->cancelInvitation(
            $user,
            $request->user(),
            ActivationCancellationReason::from($validated['cancellation_reason']),
            $details,
        );
        $noticeSent = $this->accounts->sendCancellationNotice($cancelled, $request->user());

        if (! $noticeSent) {
            return back()->with('warning', sprintf(
                "%s's activation was cancelled, but the email could not be sent. Use Resend Cancellation Notice to try again.",
                $cancelled->name,
            ));
        }

        return back()->with('success', sprintf("%s's account activation was cancelled.", $cancelled->name));
    }

    public function resendCancellationNotice(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->accounts->canManage($request->user(), $user), 403);

        if (! $this->accounts->sendCancellationNotice($user, $request->user(), resend: true)) {
            return back()->with('warning', 'The cancellation notice could not be sent. Please try again later.');
        }

        return back()->with('success', sprintf('The cancellation notice was sent to %s.', $user->email));
    }

    public function unlock(Request $request, User $user): RedirectResponse
    {
        $unlocked = $this->accounts->unlock($user, $request->user());

        return redirect()
            ->back()
            ->with('success', sprintf('%s can now attempt to sign in again.', $unlocked->name));
    }

    public function resendVerification(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->accounts->canManage($request->user(), $user), 403);

        $redirect = $request->boolean('return_to_supplier') && $user->supplier_id !== null
            ? redirect()
                ->route('inventory.suppliers.show', $user->supplier_id)
                ->withFragment('supplier-portal-access')
            : redirect()->back();

        if ($user->hasVerifiedEmail()) {
            return $redirect->with('success', sprintf('%s is already verified.', $user->name));
        }

        if ($user->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['Cancelled activations cannot receive a verification email. Re-invite the user first.'],
            ]);
        }

        if ($user->isPendingActivation()) {
            $supplierInvitation = $user->supplierInvitation;
            if ($supplierInvitation?->canResend()) {
                $delivered = $this->supplierInvitations->resend($supplierInvitation, $request->user());

                return $redirect->with($delivered ? 'success' : 'warning', $delivered
                    ? sprintf('A new supplier invitation was sent to %s.', $user->email)
                    : 'The supplier invitation could not be delivered. The pending account was preserved for retry.');
            }

            $user->notify(new AccountCreated);

            return $redirect->with('success', sprintf('A new activation email was sent to %s.', $user->email));
        }

        $user->sendEmailVerificationNotification();

        return $redirect->with('success', sprintf('A new verification email was sent to %s.', $user->email));
    }
}
