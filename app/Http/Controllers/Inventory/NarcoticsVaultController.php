<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\PdeaDangerousDrugsRegister;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\Warehouse\NarcoticsVaultService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NarcoticsVaultController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly NarcoticsVaultService $vaultService,
    ) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::AccessNarcoticsVault->value),
        ];
    }

    public function index(Request $request): View
    {
        $vaultLocations = StorageLocation::where('is_narcotics_vault', true)->get();
        $dangerousDrugItems = InventoryItem::where('regulatory_category', 'DANGEROUS_DRUG')->orderBy('name')->get();

        $vaultBalances = ItemStockLevel::whereIn('storage_location_id', $vaultLocations->pluck('id'))
            ->with(['item', 'batch', 'location'])
            ->get();

        $query = PdeaDangerousDrugsRegister::with(['item', 'batch', 'location', 'custodian', 'witnessPharmacist'])
            ->latest('id');

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }
        if ($request->filled('spf')) {
            $query->where('pdea_spf_number', 'like', "%{$request->spf}%");
        }

        $entries = $query->paginate(20)->withQueryString();

        return view('inventory.warehousing.narcotics', compact('vaultLocations', 'dangerousDrugItems', 'vaultBalances', 'entries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'item_batch_id' => ['nullable', 'exists:item_batches,id'],
            'storage_location_id' => ['required', 'exists:storage_locations,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'is_inbound' => ['required', 'boolean'],
            'pdea_spf_number' => ['nullable', 'string', 'max:50'],
            'physician_s2_license' => ['nullable', 'string', 'max:30'],
            'prescriber_name' => ['nullable', 'string', 'max:255'],
            'patient_encounter_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Dual-Custody Witness Credentials
            'witness_email' => ['required', 'email'],
            'witness_password' => ['required', 'string'],
        ]);

        try {
            $witness = $this->vaultService->authenticateWitness(
                $validated['witness_email'],
                $validated['witness_password'],
                $request->user()
            );

            $entry = $this->vaultService->recordEntry($validated, $request->user(), $witness);

            return back()->with('success', "Dangerous Drug transaction {$entry->register_number} successfully recorded in electronic DDRB ledger with dual-custody verification.");
        } catch (DomainException $e) {
            return back()->withErrors(['vault' => $e->getMessage()])->withInput();
        }
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $start = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->subMonths(6)->startOfDay();
        $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        $records = $this->vaultService->getSemiAnnualReportData($start, $end);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="PDEA-Dangerous-Drugs-Semi-Annual-Report-'.now()->format('Ymd').'.csv"',
        ];

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'DDRB Register No',
                'Date & Time (PHT)',
                'Generic Clinical Name',
                'Brand Name / Formulation',
                'Dosage Strength',
                'Transaction Type',
                'Quantity',
                'Running Balance',
                'Special Prescription Form (SPF No)',
                'Prescriber S-2 License',
                'Prescriber Name',
                'Patient Hospital / Encounter ID',
                'Primary Custodian',
                'Witness Pharmacist (Dual-Custody)',
                'Vault Location Code',
            ]);

            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->register_number,
                    $r->recorded_at?->format('Y-m-d H:i:s'),
                    $r->item?->generic_name ?? $r->item?->name,
                    $r->item?->brand_name ?? 'N/A',
                    $r->item?->dosage_form_strength ?? 'N/A',
                    $r->pdea_spf_number ? 'DISPENSE_OUTBOUND' : 'INTAKE_INBOUND',
                    $r->quantity,
                    $r->running_balance,
                    $r->pdea_spf_number ?? 'INBOUND_RECEIPT',
                    $r->physician_s2_license ?? 'N/A',
                    $r->prescriber_name ?? 'N/A',
                    $r->patient_encounter_id ?? 'N/A',
                    $r->custodian?->name,
                    $r->witnessPharmacist?->name,
                    $r->location?->code,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
