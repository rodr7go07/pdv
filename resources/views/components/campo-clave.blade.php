{{--
    Campo de contraseña con ojito para verla u ocultarla. Recibe los mismos atributos que <x-text-input>
    (wire:model, id, autocomplete…); el tipo lo maneja el ojito, no hace falta pasar `type`.
--}}
<div x-data="{ ver: false }" class="relative">
    <x-text-input {{ $attributes->except('type') }} type="password" x-bind:type="ver ? 'text' : 'password'" class="pr-12" />
    <button type="button" x-on:click="ver = ! ver" x-bind:aria-pressed="ver" x-bind:aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-lg text-anil-500 hover:text-anil-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-anil-500/15">
        <x-phosphor-eye-bold x-show="! ver" class="size-5" />
        <x-phosphor-eye-slash-bold x-show="ver" x-cloak class="size-5" />
    </button>
</div>
