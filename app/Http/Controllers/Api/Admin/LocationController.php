<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Get all locations (Admin)
     */
    public function getLocations(Request $request): JsonResponse
    {
        $query = Branch::with(['merchant']);

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        $locations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $locations->getCollection(),
            'meta' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
            ],
        ]);
    }

    /**
     * Get single location (Admin)
     */
    public function getLocation(string $id): JsonResponse
    {
        $location = Branch::with(['merchant'])
            ->findOrFail($id);

        return response()->json([
            'data' => $location,
        ]);
    }

    /**
     * Create location (Admin)
     */
    public function createLocation(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'address' => 'required|string',
            'address_ar' => 'nullable|string',
            'address_en' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'google_place_id' => 'nullable|string',
            'opening_hours' => 'nullable|array',
        ]);

        $location = Branch::create($request->all());

        return response()->json([
            'message' => 'Location created successfully',
            'data' => $location,
        ], 201);
    }

    /**
     * Update location (Admin)
     */
    public function updateLocation(Request $request, string $id): JsonResponse
    {
        $location = Branch::findOrFail($id);

        $request->validate([
            'address' => 'sometimes|string',
            'address_ar' => 'sometimes|string',
            'address_en' => 'sometimes|string',
            'lat' => 'sometimes|numeric',
            'lng' => 'sometimes|numeric',
            'google_place_id' => 'sometimes|string',
            'opening_hours' => 'sometimes|array',
        ]);

        $location->update($request->all());

        return response()->json([
            'message' => 'Location updated successfully',
            'data' => $location->fresh(),
        ]);
    }

    /**
     * Delete location (Admin)
     */
    public function deleteLocation(string $id): JsonResponse
    {
        $location = Branch::findOrFail($id);

        if ($location->offers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete location with offers',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully',
        ]);
    }
}
