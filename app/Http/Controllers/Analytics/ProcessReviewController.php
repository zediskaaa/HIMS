<?php

namespace App\Http\Controllers\Analytics;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\DpriReferencePrice;
use App\Models\KpiProcessReview;
use App\Models\ProcessRecommendation;
use App\Services\Analytics\ProcessReviewService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProcessReviewController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewProcessReviews->value, only: ['index', 'show', 'dpriIndex']),
            new Middleware('can:'.Permission::CreateProcessReview->value, only: ['create', 'store', 'update', 'submit', 'checkAvailability', 'storeDpri']),
            new Middleware('can:'.Permission::ApproveProcessReview->value, only: ['approve', 'reject']),
            new Middleware('can:'.Permission::ImplementProcessReview->value, only: ['implementRecommendation']),
        ];
    }

    public function __construct(protected ProcessReviewService $reviewService) {}

    public function index(Request $request): View
    {
        $query = KpiProcessReview::query()
            ->with(['evaluator', 'approver'])
            ->withCount(['supplierScorecards', 'procurementSavingsLogs', 'inventoryShrinkageReports', 'processRecommendations']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('review_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $reviews = $query->latest()->paginate(12)->withQueryString();

        $stats = [
            'total_reviews' => KpiProcessReview::count(),
            'approved_count' => KpiProcessReview::where('status', 'approved')->count(),
            'pending_approval' => KpiProcessReview::where('status', 'submitted')->count(),
            'active_recommendations' => ProcessRecommendation::where('status', 'pending')->count(),
        ];

        return view('reviews.index', compact('reviews', 'stats'));
    }

    public function create(): View
    {
        $defaultEnd = today()->toDateString();
        $defaultStart = today()->subDays(60)->toDateString();

        return view('reviews.create', compact('defaultStart', 'defaultEnd'));
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $start = Carbon::parse($request->string('period_start'));
        $end = Carbon::parse($request->string('period_end'));

        $availability = $this->reviewService->checkDataAvailability($start, $end);

        return response()->json($availability);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'qualitative_context' => ['nullable', 'string'],
            'executive_summary' => ['nullable', 'string'],
        ]);

        try {
            $review = $this->reviewService->createReview($validated, $request->user());

            return redirect()
                ->route('reviews.show', $review)
                ->with('status', "Process Review {$review->review_number} generated successfully with evidence-based findings.");
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function show(KpiProcessReview $review): View
    {
        $review->load([
            'evaluator',
            'approver',
            'supplierScorecards.supplier',
            'procurementSavingsLogs.inventoryItem',
            'inventoryShrinkageReports.inventoryItem',
            'inventoryShrinkageReports.storageLocation',
            'processRecommendations.implementedBy',
        ]);

        return view('reviews.show', compact('review'));
    }

    public function update(Request $request, KpiProcessReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'qualitative_context' => ['nullable', 'string'],
            'executive_summary' => ['nullable', 'string'],
        ]);

        try {
            $this->reviewService->updateContext($review, $validated, $request->user());

            return redirect()
                ->route('reviews.show', $review)
                ->with('status', 'Review narrative updated successfully.');
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function submit(Request $request, KpiProcessReview $review): RedirectResponse
    {
        try {
            $this->reviewService->submitForApproval($review, $request->user());

            return redirect()
                ->route('reviews.show', $review)
                ->with('status', 'Process review submitted for BAC and Hospital Administration review.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function approve(Request $request, KpiProcessReview $review): RedirectResponse
    {
        try {
            $this->reviewService->approve($review, $request->user());

            return redirect()
                ->route('reviews.show', $review)
                ->with('status', 'Process review officially approved and signed off under Maker-Checker governance.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function reject(Request $request, KpiProcessReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->reviewService->reject($review, $request->user(), $validated['rejection_reason']);

            return redirect()
                ->route('reviews.show', $review)
                ->with('status', 'Process review rejected and returned to draft with formal feedback.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function implementRecommendation(Request $request, ProcessRecommendation $recommendation): RedirectResponse
    {
        $validated = $request->validate([
            'implementation_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reviewService->implementRecommendation(
            $recommendation,
            $request->user(),
            $validated['implementation_notes'] ?? null
        );

        return redirect()
            ->route('reviews.show', $recommendation->processReview)
            ->with('status', 'Corrective recommendation marked as successfully implemented.');
    }

    public function dpriIndex(Request $request): View
    {
        $query = DpriReferencePrice::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('drug_name', 'like', "%{$search}%")
                    ->orWhere('pndf_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('edition_year')) {
            $query->where('edition_year', $request->integer('edition_year'));
        }

        $referencePrices = $query->orderBy('drug_name')->paginate(20)->withQueryString();
        $years = DpriReferencePrice::select('edition_year')->distinct()->orderByDesc('edition_year')->pluck('edition_year');

        return view('reviews.dpri_index', compact('referencePrices', 'years'));
    }

    public function storeDpri(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pndf_code' => ['required', 'string', 'max:64'],
            'drug_name' => ['required', 'string', 'max:255'],
            'dosage_form_strength' => ['nullable', 'string', 'max:255'],
            'unit_of_measure' => ['required', 'string', 'max:64'],
            'ceiling_price' => ['required', 'numeric', 'min:0'],
            'edition_year' => ['required', 'integer', 'min:2020', 'max:2035'],
            'notes' => ['nullable', 'string'],
        ]);

        DpriReferencePrice::updateOrCreate(
            ['pndf_code' => $validated['pndf_code'], 'edition_year' => $validated['edition_year']],
            $validated
        );

        return redirect()
            ->route('reviews.dpri')
            ->with('status', "DPRI reference price for {$validated['drug_name']} registered successfully.");
    }
}
