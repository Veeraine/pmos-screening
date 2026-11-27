{{-- A horizontal bar chart: one row per item, each bar sized against the largest. --}}
@php($largest = max([1, ...array_column($rows, 'count')]))
<div class="space-y-2" role="img" aria-label="{{ $label }}">
    @foreach ($rows as $row)
        <div class="flex items-center gap-3 text-sm">
            <div class="w-28 shrink-0 text-gray-700">{{ $row['label'] }}</div>
            <div class="h-5 flex-1 rounded bg-gray-100 print-exact">
                <div class="h-5 rounded bg-indigo-500 print-exact" style="width: {{ round($row['count'] / $largest * 100) }}%"></div>
            </div>
            <div class="w-10 shrink-0 text-right font-medium tabular-nums text-gray-900">{{ $row['count'] }}</div>
        </div>
    @endforeach
</div>
