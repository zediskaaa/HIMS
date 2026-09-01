<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthenticationPanel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(Request $request): View
    {
        return view('auth.forgot-password', [
            'panel' => $this->panel($request),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $panel = $this->panel($request);
        $account = User::query()
            ->active()
            ->where('email', $request->string('email'))
            ->first();

        if ($account !== null && ! $panel->accepts($account->role)) {
            $correctPanel = AuthenticationPanel::forRole($account->role);

            return back()
                ->withInput($request->only('email'))
                ->with('wrong_panel', $panel->wrongPanelAlert($correctPanel));
        }

        $status = Password::sendResetLink([
            'email' => $request->string('email')->toString(),
            'status' => UserStatus::Active->value,
            fn (Builder $query) => $query->whereIn('role', $panel->roleValues()),
        ]);

        // Unknown and inactive accounts deliberately receive the same response
        // as a valid request. Only the owner of a matching inbox can continue.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return back()->with('status', 'If an eligible account matches that email, a password reset link has been sent.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    private function panel(Request $request): AuthenticationPanel
    {
        return AuthenticationPanel::from(
            (string) $request->route('auth_panel', AuthenticationPanel::Staff->value)
        );
    }
}
