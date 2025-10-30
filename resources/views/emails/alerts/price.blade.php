@component('mail::message')
# Price Alert Triggered

A price alert for **{{ $product->name }}** in **{{ $skuRegion->region_code }}** has been triggered.

- **Retailer:** {{ $skuRegion->retailer }}
- **Region:** {{ $skuRegion->region_code }}
- **Currency:** {{ $skuRegion->currency }}
- **Current Price:** {{ number_format((float) $price->fiat_amount, 2) }} {{ $skuRegion->currency }}
- **Current BTC:** {{ number_format((float) $price->btc_value, 8) }} BTC
- **Threshold:** {{ number_format((float) $alert->threshold_btc, 8) }} BTC ({{ $alert->comparison_operator }})
- **Recorded At:** {{ $price->recorded_at->toDateTimeString() }}

@if(isset($context['change_percentage']))
- **Change vs Previous:** {{ number_format((float) $context['change_percentage'], 2) }}%
@endif

@if(isset($context['previous_fiat']))
- **Previous Price:** {{ number_format((float) $context['previous_fiat'], 2) }} {{ $skuRegion->currency }}
@endif

@if(isset($context['previous_btc']))
- **Previous BTC:** {{ number_format((float) $context['previous_btc'], 8) }} BTC
@endif

@if(!empty($context['previous_recorded_at']))
- **Previous Snapshot:** {{ $context['previous_recorded_at'] }}
@endif


Thanks,
{{ config('app.name') }}
@endcomponent
