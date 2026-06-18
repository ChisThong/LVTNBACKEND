<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$res = Illuminate\Support\Facades\DB::select('SHOW CREATE TABLE user');
print_r($res);
Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS wallets');
