<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Redis;

class HttpTelemetryWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $sources = [
            'catalogue.rawg' => 'RAWG',
            'catalogue.giantbomb' => 'GiantBomb',
            'catalogue.nexarda' => 'NEXARDA',
            'catalogue.nexarda_feed' => 'NEXARDA Feed',
            'probe' => 'Probes',
        ];

        $stats = [];
        foreach ($sources as $key => $label) {
            $k429 = sprintf('http:telemetry:lh:%s:429', $key);
            $k5xx = sprintf('http:telemetry:lh:%s:5xx', $key);
            $v429 = (int) (Redis::get($k429) ?? 0);
            $v5xx = (int) (Redis::get($k5xx) ?? 0);

            $value = sprintf('%d 429s / %d 5xx', $v429, $v5xx);
            $description = 'Last hour rejections';
            $color = $v5xx > 0 || $v429 > 0 ? 'danger' : 'success';

            $stats[] = Stat::make($label, $value)
                ->description($description)
                ->color($color);
        }

        return $stats;
    }
}
