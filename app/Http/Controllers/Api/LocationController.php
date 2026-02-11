<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * List all governorates (المحافظات)
     * GET /api/mobile/governorates?language=ar
     */
    public function governorates(Request $request): JsonResponse
    {
        $language = $request->get('language', 'ar');

        $governorates = Governorate::orderBy('order_index')->orderBy('id')->get();

        $data = $governorates->map(function ($gov) use ($language) {
            return [
                'id' => $gov->id,
                'name' => $language === 'ar' ? $gov->name_ar : ($gov->name_en ?? $gov->name_ar),
                'name_ar' => $gov->name_ar,
                'name_en' => $gov->name_en,
                'order_index' => $gov->order_index,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * List cities by governorate (المدن حسب المحافظة)
     * GET /api/mobile/cities?governorate_id=1&language=ar
     */
    public function cities(Request $request): JsonResponse
    {
        $request->validate([
            'governorate_id' => 'required|integer|exists:governorates,id',
        ]);

        $language = $request->get('language', 'ar');

        $cities = City::where('governorate_id', $request->governorate_id)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        $data = $cities->map(function ($city) use ($language) {
            return [
                'id' => $city->id,
                'governorate_id' => $city->governorate_id,
                'name' => $language === 'ar' ? $city->name_ar : ($city->name_en ?? $city->name_ar),
                'name_ar' => $city->name_ar,
                'name_en' => $city->name_en,
                'order_index' => $city->order_index,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
