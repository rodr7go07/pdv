{{-- Franja de aviso cuando el equipo se queda sin internet (y un «ya volvió» de unos segundos). Ver resources/js/conexion.js. --}}
<div x-data x-cloak aria-live="polite"
     x-show="!$store.conexion.enLinea || $store.conexion.recuperada"
     :class="[$store.conexion.enLinea ? 'bg-emerald-600' : 'bg-amber-500 text-anil-950', $store.conexion.llamar ? 'animate-pulse' : '']"
     class="fixed inset-x-0 top-0 z-[70] px-4 pb-1.5 pt-[max(0.375rem,env(safe-area-inset-top))] text-center text-sm font-semibold text-white">
    <span x-show="!$store.conexion.enLinea">Sin internet. Espera a que vuelva la señal: por ahora no se puede guardar ni abrir otra pantalla.</span>
    <span x-show="$store.conexion.enLinea">La conexión volvió.</span>
</div>
