<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$product = App\Models\Product::with(['hinhAnh', 'phanLoai'])->first();
echo json_encode($product->toArray(), JSON_PRETTY_PRINT);
