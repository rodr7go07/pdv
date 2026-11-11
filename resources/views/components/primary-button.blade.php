{{-- Botón principal: plano, rosa mate; al pasar el mouse se oscurece un tono --}}
<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-rosa-500 px-5 py-2.5 '
        .'font-display text-sm font-extrabold uppercase tracking-wide text-white [font-stretch:112%] '
        .'transition-colors duration-100 hover:bg-rosa-600 active:bg-rosa-700 '
        .'disabled:pointer-events-none disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>
