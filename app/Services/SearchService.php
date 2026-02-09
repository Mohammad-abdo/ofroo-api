<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SearchService
{
    /**
     * Full-text search across multiple models
     */
    public function globalSearch(string $query, array $filters = []): array
    {
        $results = [
            'offers' => [],
            'merchants' => [],
            'users' => [],
        ];

        // Search offers
        if (!isset($filters['exclude']) || !in_array('offers', $filters['exclude'])) {
            $results['offers'] = Offer::with(['merchant', 'category', 'mall', 'branches', 'coupons'])
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('title_ar', 'like', "%{$query}%")
                    ->orWhere('title_en', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('description_ar', 'like', "%{$query}%")
                    ->orWhere('description_en', 'like', "%{$query}%");
            })
            ->where('status', 'active')
            ->limit(20)
            ->get();
        }

        // Search merchants
        if (!isset($filters['exclude']) || !in_array('merchants', $filters['exclude'])) {
            $results['merchants'] = Merchant::where(function ($q) use ($query) {
                $q->where('company_name_ar', 'like', "%{$query}%")
                    ->orWhere('company_name_en', 'like', "%{$query}%");
            })
            ->where('approved', true)
            ->limit(20)
            ->get();
        }

        return $results;
    }

    /**
     * Auto-suggest search
     */
    public function autoSuggest(string $query, int $limit = 10): array
    {
        $suggestions = [];

        // Offer suggestions
        $offers = Offer::where('title', 'like', "{$query}%")
            ->orWhere('title_ar', 'like', "{$query}%")
            ->orWhere('title_en', 'like', "{$query}%")
            ->where('status', 'active')
            ->limit($limit)
            ->pluck('title', 'id')
            ->toArray();

        foreach ($offers as $id => $title) {
            $suggestions[] = [
                'type' => 'offer',
                'id' => $id,
                'text' => $title,
            ];
        }

        return $suggestions;
    }
}


