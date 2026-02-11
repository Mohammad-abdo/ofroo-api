<?php

namespace App\Http\Resources;

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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'language' => $this->language,
            'city' => $this->city,
            'country' => $this->country,
            'is_blocked' => $this->is_blocked ?? false,
            'role' => $this->whenLoaded('role', function () {
                return [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                    'name_ar' => $this->role->name_ar,
                    'name_en' => $this->role->name_en,
                ];
            }),
            'email_verified_at' => $this->email_verified_at ? $this->email_verified_at->toIso8601String() : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
