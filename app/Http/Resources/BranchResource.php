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
            'name' => $this->name,
            'phone' => $this->phone,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'address' => $this->address,
            'is_active' => $this->is_active,
        ];
    }
}
