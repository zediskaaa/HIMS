<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStorageLocationRequest;
use App\Http\Requests\UpdateStorageLocationRequest;
use App\Http\Resources\StorageLocationResource;
use App\Models\StorageLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorageLocationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::ManageLocations->value, only: ['store', 'update']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = StorageLocation::query()->paginate($perPage);

        return StorageLocationResource::collection($items);
    }

    public function show(StorageLocation $storage_location)
    {
        return new StorageLocationResource($storage_location);
    }

    public function store(StoreStorageLocationRequest $request)
    {
        $data = $request->validated();
        $this->validateHierarchy($data);
        $data['code'] = strtoupper($data['code'] ?: 'LOC-'.substr((string) Str::ulid(), -10));
        $data['barcode_value'] = $data['code'];
        $loc = StorageLocation::create($data);

        return (new StorageLocationResource($loc))->response()->setStatusCode(201);
    }

    public function update(UpdateStorageLocationRequest $request, StorageLocation $storage_location)
    {
        $data = $request->validated();
        $this->validateHierarchy([...$storage_location->toArray(), ...$data]);
        if (($data['status'] ?? null) === 'inactive' && ($storage_location->totalQuantity() > 0 || $storage_location->children()->exists())) {
            throw ValidationException::withMessages([
                'status' => 'A location with stock or child locations cannot be deactivated.',
            ]);
        }
        if (array_key_exists('code', $data)) {
            $data['code'] = strtoupper($data['code'] ?: $storage_location->code);
            $data['barcode_value'] = $data['code'];
        }
        $storage_location->update($data);

        return new StorageLocationResource($storage_location);
    }

    /** @param array<string, mixed> $data */
    private function validateHierarchy(array $data): void
    {
        $type = $data['type'] ?? null;
        $parentId = $data['parent_id'] ?? null;
        if ($type === 'warehouse' && $parentId) {
            throw ValidationException::withMessages(['parent_id' => 'A warehouse must be a root location.']);
        }
        if ($type !== 'warehouse' && ! $parentId && ! in_array($type, ['department', 'pharmacy'], true)) {
            throw ValidationException::withMessages(['parent_id' => 'Select a parent warehouse or location.']);
        }
    }
}
