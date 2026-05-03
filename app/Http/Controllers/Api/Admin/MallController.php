<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mall;
use App\Support\ImageUploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class MallController extends Controller
{
    /**
     * Get all malls (Admin)
     */
    public function getMalls(Request $request): JsonResponse
    {
        $query = Mall::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $malls = $query->orderBy('order_index')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $malls->getCollection(),
            'meta' => [
                'current_page' => $malls->currentPage(),
                'last_page' => $malls->lastPage(),
                'per_page' => $malls->perPage(),
                'total' => $malls->total(),
            ],
        ]);
    }

    /**
     * Get single mall (Admin)
     */
    public function getMall(string $id): JsonResponse
    {
        $mall = Mall::findOrFail($id);

        return response()->json([
            'data' => $mall,
        ]);
    }

    /**
     * Create mall (Admin)
     */
    public function createMall(Request $request): JsonResponse
    {
        // Handle FormData boolean conversion
        $requestData = $request->all();
        if (isset($requestData['is_active'])) {
            $requestData['is_active'] = filter_var($requestData['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $imageUrl = null;
        if (! $request->hasFile('image') && ! empty($requestData['image_url'])) {
            $imageUrl = $requestData['image_url'];
        }

        // Build validation rules conditionally
        $rules = [
            'name' => 'nullable|string|max:255', // Can be derived from name_ar or name_en
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'address' => 'nullable|string|max:500', // Can be derived from address_ar or address_en
            'address_ar' => 'nullable|string|max:500',
            'address_en' => 'nullable|string|max:500',
            'location_ar' => 'nullable|string|max:500', // Frontend sends this
            'location_en' => 'nullable|string|max:500', // Frontend sends this
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:4096',
            'images' => 'nullable|array',
            'opening_hours' => 'nullable|array',
            'working_hours_ar' => 'nullable|string', // Frontend sends this
            'working_hours_en' => 'nullable|string', // Frontend sends this
            'is_active' => 'nullable|boolean',
            'order_index' => 'nullable|integer',
        ];

        $maxKb = (int) config('app.max_admin_image_upload_kb', 262144);
        $validateInput = $requestData;
        if ($request->hasFile('image')) {
            $validateInput['image'] = $request->file('image');
            $rules['image'] = ImageUploadRules::permissiveImageMax($maxKb);
        } else {
            $rules['image'] = 'nullable';
        }

        $validator = Validator::make($validateInput, $rules);

        // Custom validation: at least one name is required
        if (empty($requestData['name']) && empty($requestData['name_ar']) && empty($requestData['name_en'])) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'name' => ['At least one name field (name, name_ar, or name_en) is required.'],
                ],
            ], 422);
        }

        // At least one of: text address OR map coordinates (lat+lng from OSM picker)
        $hasTextAddress = ! empty($requestData['address']) || ! empty($requestData['address_ar']) || ! empty($requestData['address_en'])
            || ! empty($requestData['location_ar']) || ! empty($requestData['location_en']);
        $latOk = isset($requestData['latitude']) && $requestData['latitude'] !== '' && $requestData['latitude'] !== null && is_numeric($requestData['latitude']);
        $lngOk = isset($requestData['longitude']) && $requestData['longitude'] !== '' && $requestData['longitude'] !== null && is_numeric($requestData['longitude']);
        $hasMapPin = $latOk && $lngOk;
        if (! $hasTextAddress && ! $hasMapPin) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'address' => ['Provide an address or pick a location on the map (latitude & longitude).'],
                ],
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('image')) {
            $imageUrl = $this->storeMallUploadedImage($request->file('image'));
        }

        // Map frontend fields to backend fields
        $name = $requestData['name'] ?? $requestData['name_ar'] ?? $requestData['name_en'] ?? '';
        $addressAr = $requestData['address_ar'] ?? $requestData['location_ar'] ?? null;
        $addressEn = $requestData['address_en'] ?? $requestData['location_en'] ?? null;
        if ($hasMapPin && trim((string) ($addressAr ?? '')) === '' && trim((string) ($addressEn ?? '')) === '') {
            $coordLabel = round((float) $requestData['latitude'], 6).', '.round((float) $requestData['longitude'], 6);
            $addressAr = 'موقع على الخريطة: '.$coordLabel;
            $addressEn = 'Map location: '.$coordLabel;
        }
        $address = $requestData['address'] ?? $requestData['address_ar'] ?? $requestData['address_en']
            ?? $requestData['location_ar'] ?? $requestData['location_en'] ?? $addressAr ?? $addressEn ?? '';

        // Handle opening hours
        $openingHours = null;
        if (! empty($requestData['working_hours_ar']) || ! empty($requestData['working_hours_en'])) {
            $openingHours = [
                'ar' => $requestData['working_hours_ar'] ?? '',
                'en' => $requestData['working_hours_en'] ?? '',
            ];
        } elseif (! empty($requestData['opening_hours'])) {
            $openingHours = $requestData['opening_hours'];
        }

        $mall = Mall::create([
            'name' => $name,
            'name_ar' => $requestData['name_ar'] ?? null,
            'name_en' => $requestData['name_en'] ?? null,
            'description' => $requestData['description'] ?? null,
            'description_ar' => $requestData['description_ar'] ?? null,
            'description_en' => $requestData['description_en'] ?? null,
            'address' => $address,
            'address_ar' => $addressAr,
            'address_en' => $addressEn,
            'city' => $requestData['city'] ?? 'القاهرة',
            'country' => $requestData['country'] ?? 'مصر',
            'latitude' => $requestData['latitude'] ?? null,
            'longitude' => $requestData['longitude'] ?? null,
            'phone' => $requestData['phone'] ?? null,
            'email' => $requestData['email'] ?? null,
            'website' => $requestData['website'] ?? null,
            'image_url' => $imageUrl,
            'images' => $requestData['images'] ?? null,
            'opening_hours' => $openingHours,
            'is_active' => $requestData['is_active'] ?? true,
            'order_index' => $requestData['order_index'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Mall created successfully',
            'data' => $mall,
        ], 201);
    }

    /**
     * Update mall (Admin)
     */
    public function updateMall(Request $request, string $id): JsonResponse
    {
        $mall = Mall::findOrFail($id);

        // Handle FormData boolean conversion
        $requestData = $request->all();
        if (isset($requestData['is_active'])) {
            $requestData['is_active'] = filter_var($requestData['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        // Build validation rules conditionally
        $rules = [
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'description_ar' => 'sometimes|string',
            'description_en' => 'sometimes|string',
            'address' => 'sometimes|string|max:500',
            'address_ar' => 'sometimes|string|max:500',
            'address_en' => 'sometimes|string|max:500',
            'location_ar' => 'sometimes|string|max:500', // Frontend sends this
            'location_en' => 'sometimes|string|max:500', // Frontend sends this
            'city' => 'sometimes|string|max:100',
            'country' => 'sometimes|string|max:100',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'website' => 'sometimes|string|max:500',
            'image_url' => 'sometimes|string|max:4096',
            'images' => 'sometimes|array',
            'opening_hours' => 'sometimes|array',
            'working_hours_ar' => 'sometimes|string', // Frontend sends this
            'working_hours_en' => 'sometimes|string', // Frontend sends this
            'is_active' => 'sometimes|boolean',
            'order_index' => 'sometimes|integer',
        ];

        $maxKb = (int) config('app.max_admin_image_upload_kb', 262144);
        $validateInput = $requestData;
        if ($request->hasFile('image')) {
            $validateInput['image'] = $request->file('image');
            $rules['image'] = ImageUploadRules::permissiveImageMax($maxKb);
        } else {
            $rules['image'] = 'nullable';
        }

        $validator = Validator::make($validateInput, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('image')) {
            $requestData['image_url'] = $this->storeMallUploadedImage($request->file('image'));
        }

        // Map frontend fields to backend fields
        if (isset($requestData['name_ar']) || isset($requestData['name_en'])) {
            $requestData['name'] = $requestData['name'] ?? $requestData['name_ar'] ?? $requestData['name_en'] ?? $mall->name;
        }
        if (isset($requestData['location_ar']) || isset($requestData['location_en'])) {
            $requestData['address_ar'] = $requestData['address_ar'] ?? $requestData['location_ar'] ?? null;
            $requestData['address_en'] = $requestData['address_en'] ?? $requestData['location_en'] ?? null;
            if (empty($requestData['address'])) {
                $requestData['address'] = $requestData['address_ar'] ?? $requestData['address_en'] ?? $mall->address;
            }
        }

        $latOkUpdate = isset($requestData['latitude']) && $requestData['latitude'] !== '' && $requestData['latitude'] !== null && is_numeric($requestData['latitude']);
        $lngOkUpdate = isset($requestData['longitude']) && $requestData['longitude'] !== '' && $requestData['longitude'] !== null && is_numeric($requestData['longitude']);
        $hasMapPinUpdate = $latOkUpdate && $lngOkUpdate;
        if ($hasMapPinUpdate) {
            $arUp = trim((string) ($requestData['address_ar'] ?? ''));
            $enUp = trim((string) ($requestData['address_en'] ?? ''));
            if ($arUp === '' && $enUp === '') {
                $coordLabel = round((float) $requestData['latitude'], 6).', '.round((float) $requestData['longitude'], 6);
                $requestData['address_ar'] = 'موقع على الخريطة: '.$coordLabel;
                $requestData['address_en'] = 'Map location: '.$coordLabel;
            }
            if (trim((string) ($requestData['address'] ?? '')) === '') {
                $requestData['address'] = $requestData['address_ar'] ?? $requestData['address_en'] ?? $mall->address;
            }
        }

        // Handle opening hours
        if (isset($requestData['working_hours_ar']) || isset($requestData['working_hours_en'])) {
            $requestData['opening_hours'] = [
                'ar' => $requestData['working_hours_ar'] ?? ($mall->opening_hours['ar'] ?? ''),
                'en' => $requestData['working_hours_en'] ?? ($mall->opening_hours['en'] ?? ''),
            ];
        }

        // Remove frontend-specific fields before updating
        unset(
            $requestData['location_ar'],
            $requestData['location_en'],
            $requestData['working_hours_ar'],
            $requestData['working_hours_en'],
            $requestData['image'],
        );

        $mall->update($requestData);

        return response()->json([
            'message' => 'Mall updated successfully',
            'data' => $mall->fresh(),
        ]);
    }

    /**
     * Delete mall (Admin)
     */
    public function deleteMall(string $id): JsonResponse
    {
        $mall = Mall::findOrFail($id);
        $mall->delete();

        return response()->json([
            'message' => 'Mall deleted successfully',
        ]);
    }

    /**
     * Persist mall image with a safe extension (any common camera / editor format).
     */
    private function storeMallUploadedImage(UploadedFile $image): string
    {
        $ext = strtolower((string) $image->getClientOriginalExtension());
        if ($ext === '') {
            $ext = strtolower((string) ($image->guessExtension() ?: 'jpg'));
        }
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';
        if (strlen($ext) > 12) {
            $ext = 'jpg';
        }

        $imageName = time().'_'.uniqid('', true).'.'.$ext;
        $imagePath = $image->storeAs('malls', $imageName, 'public');

        return asset('storage/'.$imagePath);
    }
    private  function  getmobileMAllDetails(Mall $mall): array
    {
        return [
            'id' => $mall->id,
            'name' => $mall->name,
            'image_url' => $mall->image_url,
        ];
        $name = $language === 'ar'
                ? ($mall->name_ar ?? $mall->name_en ?? $mall->name ?? '')
                : ($mall->name_en ?? $mall->name_ar ?? $mall->name ?? '');

            return [
                'id' => $mall->id,
                'name' => $name,
                'image_url' => ApiMediaUrl::publicAbsolute(is_string($mall->image_url) ? $mall->image_url : ''),
                'latitude' => $mall->latitude !== null ? (float) $mall->latitude : null,
                'longitude' => $mall->longitude !== null ? (float) $mall->longitude : null,
            ];
    }
}
