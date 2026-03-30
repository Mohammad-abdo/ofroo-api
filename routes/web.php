<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

Route::get('/', function () {
    return view('welcome');
});

// API Documentation - Postman Collection
Route::get('/docs/postman_collection.json', function () {
    $filePath = public_path('docs/postman_collection.json');
    
    if (!file_exists($filePath)) {
        abort(404, 'File not found');
    }
    
    $content = file_get_contents($filePath);
    
    return response($content, 200)
        ->header('Content-Type', 'application/json')
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Content-Disposition', 'inline; filename="ofroo_api.postman_collection.json"');
});

// API Documentation Info
Route::get('/docs', function () {
    return response()->json([
        'name' => 'OFROO API Documentation',
        'version' => '1.0.0',
        'postman_collection' => url('/docs/postman_collection.json'),
        'api_base_url' => url('/api'),
        'endpoints' => [
            'auth' => '/api/auth',
            'offers' => '/api/offers',
            'coupons' => '/api/coupons',
            'cart' => '/api/cart',
            'orders' => '/api/orders',
            'merchant' => '/api/merchant',
            'admin' => '/api/admin',
        ],
    ]);
});

// Serve storage files directly (for php artisan serve and production)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        abort(404, 'File not found');
    }
    
    $file = file_get_contents($filePath);
    $type = mime_content_type($filePath);
    
    return Response::make($file, 200, [
        'Content-Type' => $type,
        'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
    ]);
})->where('path', '.*');
