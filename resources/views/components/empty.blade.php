@props(['icono' => 'tray', 'titulo', 'texto' => null])

<div {{ $attributes->class(['rounded-xl border-2 border-dashed border-anil-200 px-6 py-10 text-center']) }}>
    {{ svg('phosphor-'.$icono.'-bold', 'mx-auto size-8 text-anil-300') }}
    <h3 class="mt-3 text-base font-extrabold text-anil-900">{{ $titulo }}</h3>
    @if ($texto)
        <p class="mx-auto mt-1 max-w-sm text-sm text-anil-700">{{ $texto }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-4 flex justify-center">{{ $slot }}</div>
    @endif
</div>
