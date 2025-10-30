<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Run the BulkProductSeeder directly
/** @var Database\Seeders\BulkProductSeeder $seeder */
$seeder = $app->make(Database\Seeders\BulkProductSeeder::class);
$seeder->run();

echo "seeded\n";
