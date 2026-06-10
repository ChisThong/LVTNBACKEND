<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$req = new \Illuminate\Http\Request();
$req->merge(['search' => 'Cần']);
$res = app()->make(App\Http\Controllers\ShopController::class)->adminIndex($req);
echo json_encode($res->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
