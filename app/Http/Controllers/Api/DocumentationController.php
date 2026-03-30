<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DocumentationController extends Controller
{
    public function postmanCollection(): JsonResponse|Response
    {
        $filePath = public_path('docs/postman_collection.json');
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Postman collection not found',
            ], 404);
        }
        
        $content = file_get_contents($filePath);
        
        return response($content, 200)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'inline; filename="ofroo_api.postman_collection.json"');
    }

    public function apiDocs(): JsonResponse
    {
        $endpoints = [
            'authentication' => [
                'POST /api/auth/register' => 'Register new user',
                'POST /api/auth/login' => 'Login user',
                'POST /api/auth/logout' => 'Logout user (requires auth)',
                'POST /api/auth/request-otp' => 'Request OTP code',
                'POST /api/auth/verify-otp' => 'Verify OTP code',
                'POST /api/auth/register-merchant' => 'Register as merchant',
                'POST /api/auth/login-with-pin' => 'Merchant login with PIN',
            ],
            'offers' => [
                'GET /api/offers' => 'List all offers',
                'GET /api/offers/{id}' => 'Get single offer',
                'POST /api/merchant/offers' => 'Create offer (merchant)',
                'PUT /api/merchant/offers/{id}' => 'Update offer (merchant)',
                'DELETE /api/merchant/offers/{id}' => 'Delete offer (merchant)',
                'POST /api/merchant/offers/{id}/toggle-status' => 'Toggle offer status',
            ],
            'coupons' => [
                'GET /api/coupons' => 'List coupons',
                'GET /api/coupons/{id}' => 'Get coupon details',
                'POST /api/merchant/coupons' => 'Create coupon (merchant)',
                'PUT /api/merchant/coupons/{id}' => 'Update coupon',
                'DELETE /api/merchant/coupons/{id}' => 'Delete coupon',
                'POST /api/coupons/{id}/activate' => 'Activate coupon',
                'POST /api/coupons/validate' => 'Validate coupon by barcode',
            ],
            'cart' => [
                'GET /api/cart' => 'Get user cart',
                'POST /api/cart/add' => 'Add item to cart',
                'PUT /api/cart/{id}' => 'Update cart item',
                'DELETE /api/cart/{id}' => 'Remove from cart',
                'DELETE /api/cart/clear' => 'Clear cart',
            ],
            'orders' => [
                'GET /api/orders' => 'List user orders',
                'GET /api/orders/{id}' => 'Get order details',
                'POST /api/orders/checkout' => 'Checkout cart',
            ],
            'merchant' => [
                'GET /api/merchant/profile' => 'Get merchant profile',
                'PUT /api/merchant/profile' => 'Update profile',
                'POST /api/merchant/profile/logo' => 'Upload logo',
                'GET /api/merchant/statistics' => 'Get statistics',
                'GET /api/merchant/wallet' => 'Get wallet balance',
                'POST /api/merchant/wallet/withdraw' => 'Request withdrawal',
                'GET /api/merchant/locations' => 'List branch locations',
                'POST /api/merchant/locations' => 'Create location',
                'PUT /api/merchant/locations/{id}' => 'Update location',
                'DELETE /api/merchant/locations/{id}' => 'Delete location',
            ],
            'admin' => [
                'GET /api/admin/dashboard/stats' => 'Dashboard statistics',
                'GET /api/admin/users' => 'List all users',
                'GET /api/admin/merchants' => 'List all merchants',
                'POST /api/admin/merchants/{id}/approve' => 'Approve merchant',
                'POST /api/admin/merchants/{id}/reject' => 'Reject merchant',
                'POST /api/admin/merchants/{id}/suspend' => 'Suspend merchant',
                'GET /api/admin/withdrawals' => 'List withdrawals',
                'POST /api/admin/withdrawals/{id}/approve' => 'Approve withdrawal',
                'POST /api/admin/withdrawals/{id}/reject' => 'Reject withdrawal',
                'GET /api/admin/categories' => 'List categories',
                'POST /api/admin/categories' => 'Create category',
                'GET /api/admin/activity-logs' => 'Activity logs',
            ],
            'public' => [
                'GET /api/categories' => 'List categories',
                'GET /api/search' => 'Search offers',
                'GET /api/merchants/{id}' => 'Get merchant public info',
                'GET /api/merchant/{id}/offers' => 'Get merchant offers',
                'GET /api/merchant/{id}/reviews' => 'Get merchant reviews',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'name' => 'OFROO API',
                'version' => '1.0.0',
                'base_url' => config('app.url') . '/api',
                'documentation' => [
                    'postman_collection' => config('app.url') . '/api/docs/postman',
                    'swagger' => config('app.url') . '/api/docs',
                ],
                'endpoints' => $endpoints,
                'authentication' => [
                    'type' => 'Bearer Token',
                    'header' => 'Authorization: Bearer {token}',
                    'get_token' => 'POST /api/auth/login',
                ],
                'common_headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer {token} (for protected routes)',
                    'Accept-Language' => 'ar|en (optional)',
                ],
            ],
        ]);
    }
}
