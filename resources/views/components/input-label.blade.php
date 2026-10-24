@props(['value'])

<label {{ $attributes->merge(['class' => 'rotulo mb-1.5 block']) }}>
    {{ $value ?? $slot }}
</label>
