@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge([
    'class' => 'block w-full rounded-lg border border-anil-200 bg-white px-3.5 py-2.5 text-base text-tinta '
        .'placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none '
        .'disabled:bg-papel disabled:text-anil-400',
]) }}>
