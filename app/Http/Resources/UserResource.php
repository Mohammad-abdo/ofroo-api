<?php

namespace App\Http\Resources;

use App\Models\MerchantStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $egyptCountry = [
            'id' => 1,
            'name_ar' => 'مصر',
            'name_en' => 'Egypt',
        ];

        $cityPayload = null;
        if ($this->relationLoaded('cityRelation') && $this->cityRelation) {
            $cityPayload = [
                'id' => $this->cityRelation->id,
                'name_ar' => $this->cityRelation->name_ar ?? '',
                'name_en' => $this->cityRelation->name_en ?? '',
                'governorate_id' => $this->cityRelation->governorate_id,
            ];
        }

        $governoratePayload = null;
        if ($this->relationLoaded('governorateRelation') && $this->governorateRelation) {
            $governoratePayload = [
                'id' => $this->governorateRelation->id,
                'name_ar' => $this->governorateRelation->name_ar ?? '',
                'name_en' => $this->governorateRelation->name_en ?? '',
                'order_index' => $this->governorateRelation->order_index ?? null,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'language' => $this->language,
            'city_id' => $this->city_id,
            'governorate_id' => $this->governorate_id,
            /** عند تحميل العلاقات يكون city كائناً يطابق شاشة الملف الشخصي؛ وإلا يبقى اسم المدينة كنص قديم */
            'city' => $cityPayload ?? $this->city,
            'governorate' => $governoratePayload,
            'country' => ($this->country === 'مصر' || $this->country === null || $this->country === '')
                ? $egyptCountry
                : $this->country,
            'is_blocked' => $this->is_blocked ?? false,
            /** Always expose FK so clients can resolve labels if `role` is omitted. */
            'role_id' => $this->role_id,
            'role' => $this->whenLoaded('role', function () {
                return [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                    'name_ar' => $this->role->name_ar,
                    'name_en' => $this->role->name_en,
                ];
            }),
            'merchant_staff' => MerchantStaff::toApiArray(
                $this->resource->relationLoaded('activeMerchantStaff')
                    ? $this->resource->activeMerchantStaff
                    : $this->resource->activeMerchantStaff()->first()
            ),
            'email_verified_at' => $this->email_verified_at ? $this->email_verified_at->toIso8601String() : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
