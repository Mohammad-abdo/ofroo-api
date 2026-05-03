<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxSettingController extends Controller
{
    /**
     * Get tax settings
     */
    public function taxSettings(): JsonResponse
    {
        $settings = TaxSetting::where('is_active', true)->get();

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Update tax settings
     */
    public function updateTaxSettings(Request $request): JsonResponse
    {
        $request->validate([
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country_code' => 'required|string',
        ]);

        $setting = TaxSetting::updateOrCreate(
            ['country_code' => $request->country_code],
            $request->all()
        );

        return response()->json([
            'message' => 'Tax settings updated successfully',
            'data' => $setting,
        ]);
    }

    /**
     * Create tax setting (Admin)
     */
    public function createTaxSetting(Request $request): JsonResponse
    {
        $request->validate([
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country_code' => 'required|string|unique:tax_settings,country_code',
        ]);

        $setting = TaxSetting::create($request->all());

        return response()->json([
            'message' => 'Tax setting created successfully',
            'data' => $setting,
        ], 201);
    }

    /**
     * Delete tax setting (Admin)
     */
    public function deleteTaxSetting(string $id): JsonResponse
    {
        $setting = TaxSetting::findOrFail($id);
        $setting->delete();

        return response()->json([
            'message' => 'Tax setting deleted successfully',
        ]);
    }
}
