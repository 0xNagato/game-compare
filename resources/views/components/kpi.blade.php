{{-- resources/views/components/kpi.blade.php --}}
@props(['label','value'])
<div class="rounded-2xl bg-slate-800/80 text-white p-4">
  <div class="text-3xl font-bold">{{ number_format($value) }}</div>
  <div class="text-white/60 text-sm">{{ $label }}</div>
</div>
