<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? $this->name_ar ?? $this->name_en ?? '',
            'phone' => $this->phone ?? '',
            'lat' => $this->lat !== null ? (float) $this->lat : 0,
            'lng' => $this->lng !== null ? (float) $this->lng : 0,
            'address' => $this->address ?? $this->address_ar ?? $this->address_en ?? '',
            'is_active' => (bool) ($this->is_active ?? true),
        ];
    }
}
