<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        return response()->json($search->lookup($query));
    }
}
