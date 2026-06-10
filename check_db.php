<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$tables = Schema::getTables();
$result = [];

foreach ($tables as $tableInfo) {
    $tableName = is_object($tableInfo) ? $tableInfo->name : $tableInfo['name'];
    $columns = Schema::getColumnListing($tableName);
    $count = 0;
    try {
        $count = DB::table($tableName)->count();
    } catch (\Exception $e) {
        $count = -1; // Indicate error
    }
    $result[] = [
        'table' => $tableName,
        'columns_count' => count($columns),
        'rows' => $count
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
