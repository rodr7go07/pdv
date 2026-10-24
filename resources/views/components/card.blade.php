@props(['titulo' => null, 'descripcion' => null, 'padding' => true])

<section {{ $attributes->class(['rounded-xl border border-anil-100 bg-white shadow-[0_2px_0_0_var(--color-anil-100)]']) }}>
    @if ($titulo || isset($acciones))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-anil-50 px-5 pb-4 pt-5 sm:px-6">
            <div class="min-w-0">
                @if ($titulo)
                    <h2 class="text-lg font-extrabold leading-tight text-anil-900">{{ $titulo }}</h2>
                @endif
                @if ($descripcion)
                    <p class="mt-1 text-sm text-anil-700">{{ $descripcion }}</p>
                @endif
            </div>
            @isset($acciones)
                <div class="flex shrink-0 items-center gap-2">{{ $acciones }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5 sm:p-6' => $padding])>
        {{ $slot }}
    </div>
</section>
