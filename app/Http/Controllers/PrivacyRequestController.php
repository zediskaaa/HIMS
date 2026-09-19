<?php

namespace App\Http\Controllers;

use App\Services\Privacy\PrivacyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;

class PrivacyRequestController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PrivacyRequestService $privacyRequestService
    ) {}

    /**
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        return ['auth:web,admin,super_admin'];
    }

    /**
     * Submit a formal Data Subject Request under RA 10173.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'request_type' => ['required', 'string', Rule::in(['access', 'correction', 'erasure', 'objection'])],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'request_type.required' => 'Please select the type of data subject request.',
            'request_type.in' => 'The selected request type is not valid under RA 10173 provisions.',
            'details.required' => 'Please provide specific details regarding your request.',
            'details.min' => 'Please provide at least 10 characters describing your request.',
            'details.max' => 'The request description cannot exceed 2,000 characters.',
        ]);

        $privacyRequest = $this->privacyRequestService->submitRequest(
            user: $request->user(),
            type: $validated['request_type'],
            details: $validated['details']
        );

        $message = sprintf(
            'Your Data Subject Request (#%s) has been submitted to the Data Protection Officer under RA 10173.',
            $privacyRequest->ticket_number
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'ticket_number' => $privacyRequest->ticket_number,
            ]);
        }

        return back()->with('status', $message);
    }
}
