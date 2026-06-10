<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test reject
$reqReject = new \Illuminate\Http\Request();
$reqReject->merge(['LyDoTuChoi' => 'Thông tin chưa hợp lệ']);
$resReject = app()->make(App\Http\Controllers\ShopController::class)->reject($reqReject, 1);
echo "REJECT API RESPONSE:\n";
echo json_encode($resReject->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Test approve
$resApprove = app()->make(App\Http\Controllers\ShopController::class)->approve(1);
echo "APPROVE API RESPONSE:\n";
echo json_encode($resApprove->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n";
