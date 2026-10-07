<?php

namespace App\Http\Controllers;

use App\Models\Bay;
use App\Models\Airport;
use App\Services\CachedDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class BayController extends Controller
{
    /**
     * Display a listing of bays for a specific airport.
     */
    public function index(Airport $airport): View
    {
        $cachedDataService = new CachedDataService();
        $bays = $cachedDataService->getSortedBays($airport->id);

        return view('bay.index', compact('airport', 'bays'));
    }

    /**
     * Store a newly created bay in storage.
     */
    public function store(Request $request, Airport $airport): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bays')->where(function ($query) use ($airport) {
                    return $query->where('airport_id', $airport->id);
                }),
            ],
        ]);

        $bay = $airport->bays()->create($validated);

        // Clear bay cache for this airport
        $cachedDataService = new CachedDataService();
        $cachedDataService->clearBaysCache($airport->id);

        return response()->json([
            'success' => true,
            'message' => 'Bay created successfully.',
            'bay' => $bay,
        ]);
    }

    /**
     * Update the specified bay in storage.
     */
    public function update(Request $request, Airport $airport, Bay $bay): JsonResponse
    {
        // Ensure bay belongs to this airport
        if ($bay->airport_id !== $airport->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bays')->where(function ($query) use ($airport) {
                    return $query->where('airport_id', $airport->id);
                })->ignore($bay->id),
            ],
        ]);

        $bay->update($validated);

        // Clear bay cache for this airport
        $cachedDataService = new CachedDataService();
        $cachedDataService->clearBaysCache($airport->id);

        return response()->json([
            'success' => true,
            'message' => 'Bay updated successfully.',
            'bay' => $bay,
        ]);
    }

    /**
     * Remove the specified bay from storage.
     */
    public function destroy(Airport $airport, Bay $bay): JsonResponse
    {
        // Ensure bay belongs to this airport
        if ($bay->airport_id !== $airport->id) {
            abort(404);
        }

        $bay->delete();

        // Clear bay cache for this airport
        $cachedDataService = new CachedDataService();
        $cachedDataService->clearBaysCache($airport->id);

        return response()->json([
            'success' => true,
            'message' => 'Bay deleted successfully.',
        ]);
    }
}
