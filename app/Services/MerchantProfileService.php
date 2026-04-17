<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use App\Helpers\StorageHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MerchantProfileService
{
    public function getProfile(Merchant $merchant, User $user): array
    {
        Log::info('Get merchant profile', [
            'user_id' => $user->id,
            'merchant_id' => $merchant->id,
        ]);

        $merchant->loadMissing(['mall', 'category', 'branches.mall']);
        $mall = $merchant->resolveDisplayMall();

        return [
            'id' => $merchant->id,
            'company_name' => $merchant->company_name,
            'company_name_ar' => $merchant->company_name_ar,
            'company_name_en' => $merchant->company_name_en,
            'description' => $merchant->description,
            'description_ar' => $merchant->description_ar,
            'description_en' => $merchant->description_en,
            'address' => $merchant->address,
            'address_ar' => $merchant->address_ar,
            'address_en' => $merchant->address_en,
            'phone' => $merchant->phone,
            'whatsapp_number' => $merchant->whatsapp_number,
            'whatsapp_link' => $merchant->whatsapp_link,
            'whatsapp_enabled' => $merchant->whatsapp_enabled,
            'city' => $merchant->city,
            'country' => $merchant->country ?? 'مصر',
            'logo_url' => $merchant->logo_url,
            'category_id' => $merchant->category_id,
            'category' => $merchant->category ? [
                'id' => $merchant->category->id,
                'name_ar' => $merchant->category->name_ar,
                'name_en' => $merchant->category->name_en,
            ] : null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'mall' => $mall ? [
                'id' => $mall->id,
                'name' => $mall->name,
                'name_ar' => $mall->name_ar,
                'name_en' => $mall->name_en,
            ] : null,
        ];
    }

    public function updateProfile(Merchant $merchant, User $user, array $validatedData, ?object $logoFile = null): array
    {
        Log::info('Update profile request', [
            'merchant_id' => $merchant->id,
            'has_logo_file' => $logoFile !== null,
            'has_logo_url' => isset($validatedData['logo_url']),
        ]);

        $merchantData = $this->prepareMerchantData($validatedData);
        $userData = $this->prepareUserData($validatedData);
        $logoUrl = $this->handleLogoUpdate($merchant, $logoFile, $validatedData['logo_url'] ?? null);

        if ($logoUrl !== null) {
            $this->updateMerchantLogo($merchant->id, $logoUrl);
        }

        if (!empty($merchantData)) {
            $merchant->update($merchantData);
            Log::info('Merchant updated', ['merchant_id' => $merchant->id]);
        }

        if (!empty($userData)) {
            $user->update($userData);
            Log::info('User updated', ['user_id' => $user->id]);
        }

        $merchant->refresh();
        $user->refresh();

        return $this->getProfile($merchant, $user);
    }

    private function prepareMerchantData(array $data): array
    {
        $fields = [
            'company_name', 'company_name_ar', 'company_name_en',
            'description', 'description_ar', 'description_en',
            'address', 'address_ar', 'address_en',
            'phone', 'whatsapp_number', 'whatsapp_link', 'whatsapp_enabled', 'city',
        ];

        return collect($fields)
            ->filter(fn ($field) => isset($data[$field]))
            ->mapWithKeys(fn ($field) => [$field => $data[$field]])
            ->toArray();
    }

    private function prepareUserData(array $data): array
    {
        $userFields = ['name', 'email', 'phone_user'];

        $result = [];
        if (isset($data['name'])) {
            $result['name'] = $data['name'];
        }
        if (isset($data['email'])) {
            $result['email'] = $data['email'];
        }
        if (isset($data['phone_user'])) {
            $result['phone'] = $data['phone_user'];
        }

        return $result;
    }

    private function handleLogoUpdate(Merchant $merchant, ?object $file, ?string $logoUrl): ?string
    {
        if ($file !== null) {
            $validation = StorageHelper::validateImage($file, 2);
            if (!$validation['valid']) {
                throw new \InvalidArgumentException($validation['error']);
            }

            if ($merchant->logo_url) {
                StorageHelper::deleteFile($merchant->logo_url);
            }

            $uploadResult = StorageHelper::uploadMerchantLogo($file, $merchant->id);
            return $uploadResult['url'];
        }

        if ($logoUrl !== null) {
            return $logoUrl;
        }

        return null;
    }

    private function updateMerchantLogo(int $merchantId, string $logoUrl): void
    {
        DB::table('merchants')
            ->where('id', $merchantId)
            ->update([
                'logo_url' => $logoUrl,
                'updated_at' => now(),
            ]);

        Log::info('Logo updated via direct query', [
            'merchant_id' => $merchantId,
            'logo_url' => $logoUrl,
        ]);
    }

    public function uploadLogo(Merchant $merchant, object $file): string
    {
        $validation = StorageHelper::validateImage($file, 2);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['error']);
        }

        if ($merchant->logo_url) {
            StorageHelper::deleteFile($merchant->logo_url);
        }

        $uploadResult = StorageHelper::uploadMerchantLogo($file, $merchant->id);
        $logoUrl = $uploadResult['url'];

        $this->updateMerchantLogo($merchant->id, $logoUrl);

        return $logoUrl;
    }
}
