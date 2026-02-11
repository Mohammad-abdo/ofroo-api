<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource === null || $this->resource instanceof \Illuminate\Http\Resources\MissingValue) {
            return [];
        }
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'image_url' => $this->image_url ?? null,
        ];
    }
}
