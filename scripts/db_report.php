<?php
declare(strict_types=1);

// Tiny script to report DB counts into storage/app/db_report.json for inspection without relying on terminal output

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload not found.\n");
    exit(1);
}

require $autoload;

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var Illuminate\Database\DatabaseManager $db */
$db = $app->make('db');

function safeCount(\Illuminate\Database\DatabaseManager $db, string $table): ?int {
    try {
        return (int) $db->table($table)->count();
    } catch (Throwable $e) {
        return null;
    }
}

$tables = [
    'products',
    'product_media',
    'sku_regions',
    'region_prices',
    'price_series_aggregates',
    'game_aliases',
    'platforms',
    'genres',
];

$report = [
    'timestamp' => date(DATE_ISO8601),
    'connection' => env('DB_CONNECTION'),
    'database' => env('DB_DATABASE'),
    'counts' => [],
];

foreach ($tables as $t) {
    $report['counts'][$t] = safeCount($db, $t);
}

// Extra media stats
try {
    $report['counts']['product_media_videos'] = (int) \App\Models\ProductMedia::query()->where('media_type','video')->count();
    $report['counts']['products_with_video'] = (int) \App\Models\Product::query()
        ->whereHas('media', fn($q) => $q->where('media_type','video'))
        ->count();
} catch (Throwable $e) {
    // ignore
}

// Sample trailer url if present
try {
    /** @var App\Models\Product|null $zelda */
    $zelda = App\Models\Product::query()->where('slug','the-legend-of-zelda-tears-of-the-kingdom')->first();
    if ($zelda) {
        $video = $zelda->media()->where('media_type','video')->first();
        if ($video) {
            $report['zelda_trailer_url'] = $video->url;
        }
    }
} catch (Throwable $e) {
    $report['zelda_trailer_url'] = null;
}

$path = __DIR__ . '/../storage/app/db_report.json';
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));

echo $path, PHP_EOL;
