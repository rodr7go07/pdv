{{--
    Prueba de impresión por Bluetooth directo desde Chrome (Web Bluetooth), sin RawBT.
    Sirve para saber si la térmica de la tienda acepta Bluetooth de bajo consumo (BLE) antes de construir
    la impresión de un toque con esta técnica. No está en el menú: se abre con /impresora-bluetooth.
--}}
<x-app-layout>
    <x-slot name="titulo">Prueba de impresora Bluetooth</x-slot>

    <script>
        window.pruebaImpresoraBt = () => ({
            // Servicios de escritura que usan las térmicas baratas más comunes (Chrome solo deja usar los que se declaran aquí).
            servicios: [
                '000018f0-0000-1000-8000-00805f9b34fb',
                'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
                '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                '0000ff00-0000-1000-8000-00805f9b34fb',
                '0000fff0-0000-1000-8000-00805f9b34fb',
                '0000ffe0-0000-1000-8000-00805f9b34fb',
                '0000ae30-0000-1000-8000-00805f9b34fb',
            ],
            soportado: !!navigator.bluetooth,
            seguro: window.isSecureContext,
            dispositivo: null,
            caracteristica: null,
            ocupado: false,
            bitacora: [],

            anotar(texto) {
                this.bitacora.push(new Date().toLocaleTimeString() + '  ' + texto);
            },

            async conectar() {
                this.ocupado = true;
                this.caracteristica = null;
                try {
                    this.anotar('Buscando impresoras…');
                    this.dispositivo = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: this.servicios });
                    this.anotar('Elegiste: ' + (this.dispositivo.name || '(sin nombre)'));
                    const servidor = await this.dispositivo.gatt.connect();
                    this.anotar('Conectada.');
                    const servicios = await servidor.getPrimaryServices();
                    this.anotar('Servicios encontrados: ' + (servicios.map((s) => s.uuid).join(', ') || 'ninguno'));

                    for (const s of servicios) {
                        for (const c of await s.getCharacteristics()) {
                            const p = c.properties;
                            this.anotar('  ' + c.uuid + (p.write ? ' [escribe]' : '') + (p.writeWithoutResponse ? ' [escribe sin respuesta]' : '') + (p.notify ? ' [avisa]' : ''));
                            if (!this.caracteristica && (p.write || p.writeWithoutResponse)) this.caracteristica = c;
                        }
                    }

                    this.anotar(this.caracteristica
                        ? '✔ Lista para imprimir por ' + this.caracteristica.uuid
                        : '✘ La impresora no ofrece por dónde escribir. Mándame una captura de esta lista.');
                } catch (e) {
                    this.anotar('✘ ' + e.name + ': ' + e.message);
                }
                this.ocupado = false;
            },

            /** Texto a bytes en PC437 (acentos y ñ como en el ticket ESC/POS del sistema). */
            pc437(texto) {
                const tabla = { 'á': 0xA0, 'é': 0x82, 'í': 0xA1, 'ó': 0xA2, 'ú': 0xA3, 'ñ': 0xA4, 'Ñ': 0xA5, '¿': 0xA8, '¡': 0xAD };
                return [...texto].map((c) => tabla[c] ?? (c.charCodeAt(0) < 128 ? c.charCodeAt(0) : 0x3F));
            },

            async imprimir() {
                if (!this.caracteristica) return;
                this.ocupado = true;
                const ESC = 0x1B, GS = 0x1D;
                const bytes = [
                    ESC, 0x40, ESC, 0x74, 0,
                    ESC, 0x61, 1, ESC, 0x45, 1, GS, 0x21, 0x01, ...this.pc437('PRUEBA BLUETOOTH'), 0x0A, GS, 0x21, 0, ESC, 0x45, 0,
                    ...this.pc437('Impresión directa desde Chrome'), 0x0A,
                    ...this.pc437('Acentos: á é í ó ú ñ Ñ ¿¡'), 0x0A,
                    ESC, 0x61, 0, ...this.pc437('-'.repeat(32)), 0x0A,
                    ...this.pc437(new Date().toLocaleString()), 0x0A,
                    ESC, 0x64, 4, GS, 0x56, 66, 0,
                ];
                try {
                    const datos = new Uint8Array(bytes);
                    const c = this.caracteristica;
                    // De a poquito: muchas térmicas BLE solo aceptan paquetes chicos.
                    for (let i = 0; i < datos.length; i += 100) {
                        const trozo = datos.slice(i, i + 100);
                        if (c.properties.writeWithoutResponse) {
                            await c.writeValueWithoutResponse(trozo);
                            await new Promise((r) => setTimeout(r, 30));
                        } else {
                            await c.writeValueWithResponse(trozo);
                        }
                    }
                    this.anotar('✔ Enviado (' + datos.length + ' bytes). ¿Salió el papel?');
                } catch (e) {
                    this.anotar('✘ ' + e.name + ': ' + e.message);
                }
                this.ocupado = false;
            },
        });
    </script>

    <div x-data="pruebaImpresoraBt()" class="mx-auto grid max-w-xl gap-4">
        <div class="rounded-xl border border-anil-100 bg-white p-4 text-sm text-anil-800">
            <p class="font-semibold text-tinta">Antes de probar</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Cierra RawBT y SicarX (o apaga el Bluetooth de la tablet): la impresora atiende a uno a la vez.</li>
                <li>Abre esta página en Chrome del teléfono, con la dirección https (ngrok).</li>
                <li>Si Chrome pide permiso de Bluetooth o de dispositivos cercanos, acéptalo.</li>
            </ul>
        </div>

        <template x-if="!soportado || !seguro">
            <x-alerta tono="error">
                <span x-text="!seguro ? 'Esta página necesita https (abre la dirección de ngrok).' : 'Este navegador no tiene Bluetooth web. Usa Chrome en Android.'"></span>
            </x-alerta>
        </template>

        <div class="grid gap-2 sm:grid-cols-2">
            <x-primary-button type="button" x-on:click="conectar()" x-bind:disabled="ocupado || !soportado || !seguro" class="w-full">1. Conectar impresora</x-primary-button>
            <x-secondary-button x-on:click="imprimir()" x-bind:disabled="ocupado || !caracteristica" class="w-full">2. Imprimir prueba</x-secondary-button>
        </div>

        <div class="rounded-xl border border-anil-100 bg-white p-4">
            <p class="rotulo">Qué pasó</p>
            <pre class="mt-2 min-h-24 whitespace-pre-wrap break-words font-mono text-xs text-anil-800" x-text="bitacora.join('\n') || 'Toca «Conectar impresora».'"></pre>
        </div>
    </div>
</x-app-layout>
