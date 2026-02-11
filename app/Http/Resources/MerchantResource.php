<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
{
    /**
     * When true, return full details (for offer detail page: تفاصيل التاجر).
     */
    protected bool $detailed = false;

    public function __construct($resource, bool $detailed = false)
    {
        parent::__construct($resource);
        $this->detailed = $detailed;
    }

    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->id,
            'company_name' => $this->company_name ?? '',
            'company_name_ar' => $this->company_name_ar ?? $this->company_name ?? '',
            'company_name_en' => $this->company_name_en ?? $this->company_name ?? '',
            'logo_url' => $this->logo_url ?? '',
            'city' => $this->city ?? '',
            'country' => $this->country ?? '',
        ];

        if ($this->detailed) {
            $base['description'] = $this->description ?? '';
            $base['description_ar'] = $this->description_ar ?? $this->description ?? '';
            $base['description_en'] = $this->description_en ?? $this->description ?? '';
            $base['address'] = $this->address ?? '';
            $base['address_ar'] = $this->address_ar ?? $this->address ?? '';
            $base['address_en'] = $this->address_en ?? $this->address ?? '';
            $base['phone'] = $this->phone ?? '';
            $base['whatsapp_number'] = $this->whatsapp_number ?? '';
            $base['whatsapp_link'] = $this->whatsapp_link ?? '';
            $base['whatsapp_enabled'] = (bool) ($this->whatsapp_enabled ?? false);
        }

        return $base;
    }
}
