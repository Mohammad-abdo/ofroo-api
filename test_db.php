<?php
// Test script to check database connection and merchants table

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Database configuration (from .env)
$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'ofroo_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    // Test basic connection
    $pdo = $capsule->getConnection()->getPdo();
    echo "✅ Database connection successful\n";
    
    // Test merchants table exists
    $result = $capsule->select("SHOW TABLES LIKE 'merchants'");
    if (count($result) > 0) {
        echo "✅ Merchants table exists\n";
        
        // Count merchants
        $count = $capsule->select("SELECT COUNT(*) as count FROM merchants")[0];
        echo "✅ Merchants count: " . $count->count . "\n";
        
        // Get first few merchants
        $merchants = $capsule->select("SELECT id, company_name, company_name_ar, approved FROM merchants LIMIT 5");
        echo "✅ Sample merchants:\n";
        foreach ($merchants as $merchant) {
            echo "  - ID: {$merchant->id}, Name: {$merchant->company_name_ar}, Approved: " . ($merchant->approved ? 'Yes' : 'No') . "\n";
        }
        
    } else {
        echo "❌ Merchants table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}