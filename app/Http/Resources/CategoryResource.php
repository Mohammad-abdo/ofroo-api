<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $language = $request->get('language', 'ar');
        $name = $language === 'ar' ? ($this->name_ar ?? $this->name_en) : ($this->name_en ?? $this->name_ar);

        return [
            'id' => $this->id,
            'name' => $name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'image' => $this->image_url ?? null,
            'icon_url' => $this->image_url ?? null,
        ];
    }
}
