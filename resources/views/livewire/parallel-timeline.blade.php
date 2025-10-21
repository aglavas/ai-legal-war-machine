<div data-pt="parallel" class="p-4 border border-slate-200 rounded-md bg-white">
    <h2 class="text-lg font-semibold">ParallelTimeline</h2>
    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <div class="text-sm text-slate-500">Top Lane ({{ count($dataTop) }}) – {{ $day }}</div>
        </div>
        <div>
            <div class="text-sm text-slate-500">Bottom Lane ({{ count($dataBottom) }}) – {{ $day }}</div>
        </div>
    </div>
</div>

