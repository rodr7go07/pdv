<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-anil-200 bg-white px-5 py-2.5 '
        .'text-sm font-semibold text-anil-800 transition '
        .'hover:border-anil-300 hover:bg-anil-50 '
        .'disabled:pointer-events-none disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>
