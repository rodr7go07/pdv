@props(['label', 'ayuda' => null])

<label {{ $attributes->only('class')->class(['flex min-h-11 cursor-pointer items-start gap-3 py-1.5']) }}>
    <input type="checkbox" {{ $attributes->except('class') }}
           class="mt-0.5 size-5 shrink-0 rounded border-anil-300 text-rosa-500 focus:ring-rosa-500/30 disabled:opacity-50">
    <span class="min-w-0">
        <span class="block text-[0.9375rem] font-medium leading-snug text-tinta">{{ $label }}</span>
        @if ($ayuda)
            <span class="block text-sm text-anil-600">{{ $ayuda }}</span>
        @endif
    </span>
</label>
