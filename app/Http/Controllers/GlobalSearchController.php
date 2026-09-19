<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class GlobalSearchController extends Controller implements HasMiddleware
{
    /**
     * Accessible by any authenticated staff, administrator, or super administrator.
     * Specific entity visibility is enforced inside the search service based on permissions.
     *
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        return ['auth:web,admin,super_admin'];
    }

    public function __construct(
        private readonly GlobalSearchService $searchService
    ) {}

    /**
     * Execute global multi-entity search and return grouped results.
     */
    public function search(Request $request): JsonResponse
    {
        $rawQuery = (string) $request->query('query', $request->query('search', ''));
        $query = mb_substr(trim($rawQuery), 0, 100);

        $results = $this->searchService->search($request->user(), $query);

        return response()->json($results);
    }
}
