<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CachedDataService;
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
            'airport_id' => 'required|exists:airports,id',
            'event_id' => 'nullable|exists:events,id'
        ]);

        $cachedDataService = new CachedDataService();
        $bays = $cachedDataService->getSortedBays($request->airport_id);

        // Get all blocked bay IDs for this event in a single query
        $blockedBayIds = collect();
        if ($request->event_id) {
            $blockedBayIds = \App\Models\BayBlocking::where('event_id', $request->event_id)
                ->pluck('bay_id');
        }

        $bays = $bays->map(function ($bay) use ($blockedBayIds) {
            $bayData = ['id' => $bay->id, 'name' => $bay->name];

            // Check if bay is blocked using the pre-fetched collection
            if ($blockedBayIds->contains($bay->id)) {
                $bayData['name'] = $bay->name . ' (Blocked)';
            }

            return $bayData;
        });

        return response()->json($bays);
    }
}
