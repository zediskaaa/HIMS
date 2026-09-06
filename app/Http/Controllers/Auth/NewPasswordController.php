<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NotCurrentPassword;
use App\Rules\PasswordStandard;
use App\Support\AuthenticationPanel;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $panel = $this->panel($request);
        $account = User::query()->where('email', $request->string('email'))->first();

        if ($account !== null && ! $panel->accepts($account->role)) {
            return $this->wrongPanelRedirect($panel, $account);
        }

        return view('auth.reset-password', [
            'request' => $request,
            'panel' => $panel,
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', new PasswordStandard],
        ], [
            'password.confirmed' => PasswordStandard::CONFIRMATION_MESSAGE,
        ]);

        $panel = $this->panel($request);
        $account = User::query()->where('email', $request->string('email'))->first();

        if ($account !== null && ! $panel->accepts($account->role)) {
            return $this->wrongPanelRedirect($panel, $account);
        }

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            [
                ...$request->only('email', 'password', 'password_confirmation', 'token'),
                fn (Builder $query) => $query->whereIn('role', $panel->roleValues()),
            ],
            function (User $user) use ($request) {
                Validator::make(
                    ['password' => $request->password],
                    ['password' => [new NotCurrentPassword($user)]]
                )->validate();

                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route($panel->loginRoute())->with('status', __($status));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => in_array($status, [Password::INVALID_USER, Password::INVALID_TOKEN], true)
                    ? 'This password reset link is invalid or has expired.'
                    : __($status),
            ]);
    }

    private function panel(Request $request): AuthenticationPanel
    {
        return AuthenticationPanel::from(
            (string) $request->route('auth_panel', AuthenticationPanel::Staff->value)
        );
    }

    private function wrongPanelRedirect(
        AuthenticationPanel $currentPanel,
        User $account,
    ): RedirectResponse {
        $correctPanel = AuthenticationPanel::forRole($account->role);

        return redirect()
            ->route($correctPanel->passwordRequestRoute())
            ->with('wrong_panel', $currentPanel->wrongPanelAlert($correctPanel));
    }
}
