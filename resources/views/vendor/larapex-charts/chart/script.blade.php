<?php
/** @var object $chart */

$chartId = null;

if (is_object($chart) && property_exists($chart, 'id') && is_string($chart->id)) {
    $chartId = $chart->id;
} elseif (is_object($chart) && method_exists($chart, 'id')) {
    $chartId = $chart->id();
}

if ($chartId === null) {
    $chartId = 'chart-' . uniqid();
}

$optionsJson = '{}';

if (is_object($chart) && method_exists($chart, 'toJsonEncodedString')) {
    $optionsJson = $chart->toJsonEncodedString();
} elseif (is_object($chart) && method_exists($chart, 'toJson')) {
    $response = $chart->toJson();
    $payload = method_exists($response, 'getData') ? $response->getData(true) : [];
    $options = $payload['options'] ?? [];

    if (! isset($options['series']) && method_exists($chart, 'dataset')) {
        $dataset = json_decode($chart->dataset(), true);
        if (is_array($dataset)) {
            $options['series'] = $dataset;
        }
    }

    $encoded = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $optionsJson = $encoded !== false ? $encoded : '{}';
} elseif (is_object($chart) && method_exists($chart, 'getAdditionalOptions')) {
    $encoded = json_encode($chart->getAdditionalOptions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $optionsJson = $encoded !== false ? $encoded : '{}';
}
?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const target = document.querySelector("#{!! $chartId !!}");

        if (!target) {
            return;
        }

        const chart = new ApexCharts(target, {!! $optionsJson !!});
        chart.render();
    });
</script>
