<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\HimsNotificationService;
use App\Services\Import\DataImportExecutor;
use App\Services\Import\DataImportReader;
use App\Services\Import\DataImportValidator;
use App\Services\Import\ImportStagingService;
use App\Services\Import\ImportTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DataImportReader $reader,
        private readonly DataImportValidator $validator,
        private readonly DataImportExecutor $executor,
        private readonly ImportTemplateGenerator $templates,
        private readonly ImportStagingService $staging,
        private readonly HimsNotificationService $notifications,
    ) {}

    /**
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
        ];
    }

    /**
     * Show the Import Data main management screen.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $canItems = $user->can(Permission::ManageItems->value);
        $canLocations = $user->can(Permission::ManageLocations->value);
        $canSuppliers = $user->can(Permission::ManageSuppliers->value);

        if (! $canItems && ! $canLocations && ! $canSuppliers) {
            abort(403, 'You do not have permission to import inventory or organizational records.');
        }

        return view('inventory.import.index', [
            'canItems' => $canItems,
            'canLocations' => $canLocations,
            'canSuppliers' => $canSuppliers,
            'totalItems' => InventoryItem::count(),
            'totalLocations' => StorageLocation::count(),
            'totalSuppliers' => Supplier::count(),
        ]);
    }

    /**
     * Download template files in CSV, JSON, or Excel (.xlsx) formats.
     */
    public function downloadTemplate(Request $request): StreamedResponse
    {
        $target = $request->input('target', 'items');
        if (! in_array($target, ['items', 'locations', 'suppliers'], true)) {
            $target = 'items';
        }

        $format = strtolower($request->input('format', 'csv'));

        return match ($format) {
            'json' => $this->templates->downloadJson($target),
            'xlsx', 'excel' => $this->templates->downloadXlsx($target),
            default => $this->templates->downloadCsv($target),
        };
    }

    /**
     * Upload, parse, and validate an import file without saving to database.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'target' => ['required', 'string', 'in:items,locations,suppliers'],
            'mode' => ['required', 'string', 'in:create_only,update_or_create'],
        ], [
            'file.required' => 'Please select a file to import.',
            'file.max' => 'The file size cannot exceed 10 MB.',
            'target.in' => 'Invalid target module selected.',
            'mode.in' => 'Invalid import mode selected.',
        ]);

        $user = $request->user();
        $target = $request->input('target');
        $mode = $request->input('mode');
        $file = $request->file('file');

        // Check target permission
        $this->authorizeTarget($user, $target);

        // Verify supported file extension
        $clientExt = strtolower($file->getClientOriginalExtension());
        if (! in_array($clientExt, ['csv', 'txt', 'json', 'xlsx', 'xls'], true)) {
            $extLabel = $clientExt !== '' ? "[{$clientExt}]" : '[no extension]';
            return response()->json([
                'is_valid' => false,
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 1,
                'create_count' => 0,
                'update_count' => 0,
                'message' => "Unsupported file format {$extLabel}. Please upload a CSV (.csv), Excel (.xlsx / .xls), or JSON (.json) file.",
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'file',
                        'value' => $file->getClientOriginalName(),
                        'type' => 'invalid_structure',
                        'message' => "Unsupported file format {$extLabel}. Allowed formats: CSV (.csv), Excel (.xlsx / .xls), JSON (.json).",
                    ],
                ],
                'preview_rows' => [],
            ], 422);
        }

        try {
            $tableData = $this->reader->read($file, $clientExt, $target);
        } catch (Throwable $e) {
            return response()->json([
                'is_valid' => false,
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 1,
                'create_count' => 0,
                'update_count' => 0,
                'message' => 'Failed to parse file: '.$e->getMessage(),
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'file',
                        'value' => $file->getClientOriginalName(),
                        'type' => 'invalid_structure',
                        'message' => $e->getMessage(),
                    ],
                ],
                'preview_rows' => [],
            ], 422);
        }

        $validationResult = $this->validator->validate($target, $tableData, $mode);

        $importToken = null;
        if ($validationResult['is_valid'] && ! empty($validationResult['validated_payload'])) {
            $importToken = $this->staging->stage(
                $target,
                $mode,
                $validationResult['validated_payload'],
                $user->id
            );
        }

        return response()->json([
            ...$validationResult,
            'import_token' => $importToken,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'target' => $target,
            'mode' => $mode,
        ]);
    }

    /**
     * Execute transactional commit of previously validated staged records.
     */
    public function commit(Request $request): JsonResponse
    {
        $request->validate([
            'import_token' => ['required', 'string'],
            'target' => ['required', 'string', 'in:items,locations,suppliers'],
        ], [
            'import_token.required' => 'Missing import validation token.',
            'target.in' => 'Invalid target module.',
        ]);

        $user = $request->user();
        $target = $request->input('target');
        $token = $request->input('import_token');

        $this->authorizeTarget($user, $target);

        $staged = $this->staging->retrieve($token, $user->id);
        if (! $staged || $staged['target'] !== $target) {
            return response()->json([
                'success' => false,
                'message' => 'Import session has expired or is invalid. Please re-upload and validate your file.',
            ], 422);
        }

        try {
            $result = $this->executor->execute($target, $staged['records'], $user);
            $this->staging->forget($token);

            $targetName = match ($target) {
                'items' => 'inventory items',
                'locations' => 'storage locations',
                'suppliers' => 'suppliers',
                default => 'records',
            };

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$result['total']} {$targetName} ({$result['created']} created, {$result['updated']} updated).",
                'created' => $result['created'],
                'updated' => $result['updated'],
                'total' => $result['total'],
                'target' => $target,
            ]);
        } catch (Throwable $e) {
            report($e);

            $targetName = match ($target) {
                'items' => 'inventory items',
                'locations' => 'storage locations',
                'suppliers' => 'suppliers',
                default => 'records',
            };

            try {
                $this->notifications->sendToUser(
                    $user,
                    'import-failed:'.hash('sha256', $token),
                    'Data import failed',
                    "The {$targetName} import failed and no records were committed. Review the file and try again.",
                    NotificationPriority::Warning,
                    NotificationDestination::Import,
                );
            } catch (Throwable $notificationException) {
                report($notificationException);
            }

            return response()->json([
                'success' => false,
                'message' => 'The import could not be completed. No records were committed. Review the file and try again.',
            ], 500);
        }
    }

    /**
     * Ensure the user holds permission for the target module.
     */
    protected function authorizeTarget($user, string $target): void
    {
        $permission = match ($target) {
            'items' => Permission::ManageItems->value,
            'locations' => Permission::ManageLocations->value,
            'suppliers' => Permission::ManageSuppliers->value,
            default => null,
        };

        if ($permission && ! $user->can($permission)) {
            abort(403, "You do not have permission to manage and import {$target}.");
        }
    }
}
