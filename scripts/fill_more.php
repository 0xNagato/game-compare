<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;

$passes = [
    ['limit' => 1200, 'window' => 1825, 'seed_per_run' => 600],
    ['limit' => 1400, 'window' => 2190, 'seed_per_run' => 700],
];

foreach ($passes as $pass) {
    Artisan::call('providers:verify-and-seed', [
        '--limit' => (string) $pass['limit'],
        '--window' => (string) $pass['window'],
        '--families' => 'xbox,playstation,nintendo,pc',
        '--regions' => 'US,GB,EU,CA,JP',
        '--chunk' => '150',
        '--seed-per-run' => (string) $pass['seed_per_run'],
        '--skip-verify' => true,
        '--seed-known' => true,
    ]);
}

echo "ok\n";
