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

        $bays = Bay::getSortedBaysForAirport($request->airport_id)
            ->map(function ($bay) {
                return ['id' => $bay->id, 'name' => $bay->name];
            });

        return response()->json($bays);
    }
}
