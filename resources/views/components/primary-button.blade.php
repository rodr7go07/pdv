{{-- Tecla de caja: se hunde al presionarla --}}
<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-rosa-500 px-5 py-2.5 '
        .'font-display text-sm font-extrabold uppercase tracking-wide text-white [font-stretch:112%] '
        .'shadow-[0_3px_0_0_var(--color-rosa-700)] transition-[transform,box-shadow,background-color] duration-100 '
        .'hover:bg-rosa-600 active:translate-y-[3px] active:shadow-none '
        .'disabled:pointer-events-none disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>
