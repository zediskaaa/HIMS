<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierManagementService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SupplierController extends Controller implements HasMiddleware
{
    public function __construct(private readonly SupplierManagementService $suppliers) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::ViewSuppliers->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::ManageSuppliers->value, only: ['store', 'update']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = Supplier::query()->orderBy('name')->paginate(min(max($perPage, 1), 100));

        return SupplierResource::collection($items);
    }

    public function show(Supplier $supplier)
    {
        return new SupplierResource($supplier);
    }

    public function store(StoreSupplierRequest $request)
    {
        $validated = $request->validated();
        $logoFile = $request->file('logo');
        unset($validated['logo']);

        $supplier = $this->suppliers->create($validated, $request->user());

        if ($logoFile) {
            $path = $logoFile->store('supplier-logos/'.$supplier->id, 'local');
            if ($path !== false) {
                try {
                    $this->suppliers->updateLogo($supplier, $path, $request->user());
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($path);

                    throw $exception;
                }
            }
        }

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $supplier = $this->suppliers->update($supplier, $request->validated(), $request->user());

        return new SupplierResource($supplier);
    }
}
