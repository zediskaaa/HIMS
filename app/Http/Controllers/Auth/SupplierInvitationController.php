<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SupplierInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierInvitationController extends Controller
{
    public function __invoke(Request $request, string $token, SupplierInvitationService $invitations): RedirectResponse
    {
        $user = $invitations->acceptLink($token);
        if ($user === null) {
            return redirect()->route('activation.start')->withErrors([
                'email' => 'This supplier invitation is invalid, expired, revoked, or already accepted. Ask the hospital to resend it.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget('account_activation');
        $request->session()->put('account_activation.verified_user_id', $user->getKey());

        return redirect()->route('activation.password')
            ->with('status', 'Supplier invitation accepted. Create your password to continue onboarding.');
    }
}
