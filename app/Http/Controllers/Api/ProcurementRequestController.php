<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcurementRequestRequest;
use App\Http\Requests\UpdateProcurementRequestRequest;
use App\Http\Resources\ProcurementRequestResource;
use App\Models\ProcurementRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProcurementRequestController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::ViewProcurement->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::CreateRequisition->value, only: ['store']),
            new Middleware('can:'.Permission::ManageProcurement->value, only: ['update']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = ProcurementRequest::with(['item', 'supplier'])->paginate($perPage);

        return ProcurementRequestResource::collection($items);
    }

    public function show(ProcurementRequest $procurement_request)
    {
        return new ProcurementRequestResource($procurement_request->load(['item', 'supplier']));
    }

    public function store(StoreProcurementRequestRequest $request)
    {
        $data = $request->validated();
        $pr = ProcurementRequest::create($data);

        return (new ProcurementRequestResource($pr))->response()->setStatusCode(201);
    }

    public function update(UpdateProcurementRequestRequest $request, ProcurementRequest $procurement_request)
    {
        $procurement_request->update($request->validated());

        return new ProcurementRequestResource($procurement_request);
    }
}
