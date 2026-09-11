<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierQuoteRequest;
use App\Http\Requests\UpdateSupplierQuoteRequest;
use App\Http\Resources\SupplierQuoteResource;
use App\Models\SupplierQuote;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SupplierQuoteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::ViewProcurementSensitiveData->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::ManageSourcing->value, only: ['store', 'update']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = SupplierQuote::with(['supplier', 'procurementRequest'])->paginate($perPage);

        return SupplierQuoteResource::collection($items);
    }

    public function show(SupplierQuote $supplier_quote)
    {
        return new SupplierQuoteResource($supplier_quote->load(['supplier', 'procurementRequest']));
    }

    public function store(StoreSupplierQuoteRequest $request)
    {
        $data = $request->validated();
        $sq = SupplierQuote::create($data);

        return (new SupplierQuoteResource($sq))->response()->setStatusCode(201);
    }

    public function update(UpdateSupplierQuoteRequest $request, SupplierQuote $supplier_quote)
    {
        $supplier_quote->update($request->validated());

        return new SupplierQuoteResource($supplier_quote);
    }
}
