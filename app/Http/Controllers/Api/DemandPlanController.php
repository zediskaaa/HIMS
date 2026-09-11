<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemandPlanRequest;
use App\Http\Requests\UpdateDemandPlanRequest;
use App\Http\Resources\DemandPlanResource;
use App\Models\DemandPlan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DemandPlanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::ViewReports->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::GenerateForecasts->value, only: ['store', 'update']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = DemandPlan::with('item')->paginate($perPage);

        return DemandPlanResource::collection($items);
    }

    public function show(DemandPlan $demand_plan)
    {
        return new DemandPlanResource($demand_plan->load('item'));
    }

    public function store(StoreDemandPlanRequest $request)
    {
        $data = $request->validated();
        $dp = DemandPlan::create($data);

        return (new DemandPlanResource($dp))->response()->setStatusCode(201);
    }

    public function update(UpdateDemandPlanRequest $request, DemandPlan $demand_plan)
    {
        $demand_plan->update($request->validated());

        return new DemandPlanResource($demand_plan);
    }
}
