<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BayController extends Controller
{
    /**
     * Get bays for a specific airport
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getByAirport(Request $request): JsonResponse
    {
        $request->validate([
            'airport_id' => 'required|exists:airports,id'
        ]);

        $bays = Bay::where('airport_id', $request->airport_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($bays);
    }
}
