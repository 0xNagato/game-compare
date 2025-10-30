<?php
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
require $autoload;

$app = require __DIR__ . '/../bootstrap/app.php';

/** @var Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

$passes = [
    ['limit' => 900, 'window' => 365, 'seed_per_run' => 300],
    ['limit' => 1100, 'window' => 730, 'seed_per_run' => 400],
    ['limit' => 1300, 'window' => 1095, 'seed_per_run' => 500],
];

foreach ($passes as $i => $pass) {
    Artisan::call('providers:verify-and-seed', [
        '--limit' => (string) $pass['limit'],
        '--window' => (string) $pass['window'],
        '--families' => 'xbox,playstation,nintendo,pc',
        '--regions' => 'US,GB,EU,CA,JP',
        '--chunk' => '100',
        '--seed-per-run' => (string) $pass['seed_per_run'],
        '--skip-verify' => true,
        '--seed-known' => true,
    ]);
}

// echo a simple OK marker
echo "filled\n";
