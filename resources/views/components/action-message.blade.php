@props(['on'])

<div x-data="{ shown: false, timeout: null }"
     x-init="@this.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2500); })"
     x-show="shown"
     x-transition.opacity.out.duration.1000ms
     style="display: none;"
     role="status"
     {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm font-semibold text-ok']) }}>
    <x-phosphor-check-bold class="size-4" />
    {{ $slot->isEmpty() ? 'Guardado' : $slot }}
</div>
