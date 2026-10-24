@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge([
    'class' => 'block w-full rounded-lg border border-anil-200 bg-white py-2.5 pl-3.5 pr-9 text-base text-tinta '
        .'focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none disabled:bg-papel disabled:text-anil-400',
]) }}>
    {{ $slot }}
</select>
