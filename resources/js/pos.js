/*
 * Pantalla de caja (Alpine). El carrito vive en el navegador para que escanear sea instantáneo;
 * al cobrar, el servidor vuelve a leer precios, impuestos y existencias de la base de datos
 * y recalcula todo. Aquí el dinero se maneja SIEMPRE en enteros (centavos / milésimas) para
 * que los redondeos coincidan con los del servidor (mitad hacia arriba).
 */

import { camaraDisponible, iniciarLector } from './camara';

const moneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

export const formatear = (centavos) => moneda.format(centavos / 100);
const aCentavos = (v) => Math.round(parseFloat(v || 0) * 100);
const aMilesimas = (v) => Math.round(parseFloat(v || 0) * 1000);
const mitadArriba = (numerador, divisor) => Math.floor((numerador + divisor / 2) / divisor);

const limpiarNumero = (v) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? String(parseFloat(n.toFixed(3))) : '';
};

const guardado = {
    leer(clave) {
        try { return JSON.parse(localStorage.getItem(clave) || 'null'); } catch { return null; }
    },
    escribir(clave, valor) {
        try { valor === null ? localStorage.removeItem(clave) : localStorage.setItem(clave, JSON.stringify(valor)); } catch { /* sin almacenamiento: no pasa nada */ }
    },
};

let uid = 0;

export function registrarPos(Alpine) {
    Alpine.data('pos', (cfg) => ({
        cfg,
        lineas: [],
        entrada: '',
        multiplicador: null,
        sel: -1,
        aviso: '',
        avisoTipo: 'info',
        cargando: false,
        cola: [],
        modal: null,

        cliente: null,
        clientes: { texto: '', resultados: [], cargando: false },
        busqueda: { texto: '', resultados: [], cargando: false, enfocado: 0 },
        descGlobal: { tipo: '', valor: '' },
        descLinea: { indice: -1, tipo: 'pct', valor: '' },
        peso: { articulo: null, valor: '' },
        pesoError: '',
        pesoLeyendo: false,
        alta: { clave: '', descripcion: '', precio: '', departamento_id: '', unidad_medida_id: '', iva_id: '', error: '', guardando: false },
        esperas: { lista: [], cargando: false, nombre: '' },
        cobro: { pagos: [], procesando: false, error: '', totalServidor: null },
        exito: null,
        camara: { iniciando: false, error: '', hayLinterna: false, linterna: false, leidos: 0 },
        _lector: null,
        codigoSinAlta: '', // código leído con la cámara que no existe y se ofrece dar de alta
        _idCamara: 0, // identifica cada apertura de la cámara (ver escaner.js)

        // ─── Ciclo de vida ──────────────────────────────────────────────────────────
        init() {
            const previo = guardado.leer(this.claveGuardado());
            if (previo && Array.isArray(previo.lineas) && previo.lineas.length) {
                this.lineas = previo.lineas.map((l) => ({ ...l, uid: ++uid }));
                this.descGlobal = previo.descGlobal || { tipo: '', valor: '' };
                this.cliente = this.cfg.puedeCliente ? (previo.cliente || null) : null;
                this.refrescarPrecios();
            }

            this.$watch('lineas', () => this.persistir());
            this.$watch('descGlobal', () => this.persistir());
            this.$watch('cliente', () => this.persistir());
            // Al abrir una ventana, el cursor pasa a su primer campo (con un respiro para que termine de mostrarse).
            this.$watch('modal', (nombre) => {
                if (nombre !== 'camara') this.cerrarCamara();
                if (!nombre || nombre === 'exito' || nombre === 'esperas') return;
                setTimeout(() => {
                    if (this.modal !== nombre) return;
                    const ventana = [...document.querySelectorAll('[x-show]')].find((el) => el.getAttribute('x-show') === `modal === '${nombre}'`);
                    const campo = ventana && [...ventana.querySelectorAll('input:not([type=hidden]), textarea')].find((c) => c.offsetParent !== null);
                    if (campo) { campo.focus(); campo.select?.(); }
                }, 80);
            });
            this.enfocar();
        },

        /** Alpine lo llama al salir de la pantalla (wire:navigate): la cámara no debe quedar encendida. */
        destroy() {
            this.cerrarCamara();
        },

        claveGuardado() {
            return `pos-carrito-${this.cfg.cajaId}`;
        },

        persistir() {
            guardado.escribir(this.claveGuardado(), this.lineas.length ? { lineas: this.lineas, descGlobal: this.descGlobal, cliente: this.cliente } : null);
        },

        enfocar() {
            // Si hay una ventana abierta, el foco es de ella (el peso, el cobro, etc.).
            this.$nextTick(() => this.modal === null && this.$refs.entrada && this.$refs.entrada.focus());
        },

        // ─── Dinero ─────────────────────────────────────────────────────────────────
        /** Lista de precios vigente: la del cliente elegido o la 1. */
        get listaActual() {
            return this.cliente?.lista ?? this.cfg.lista ?? 1;
        },
        precioCents(l) {
            const lista = this.listaActual;
            return aCentavos(l.precios[lista - 1] ?? l.precios[0]);
        },
        brutoLinea(l) {
            return mitadArriba(this.precioCents(l) * aMilesimas(l.cantidad), 1000);
        },
        descuentoLinea(l) {
            const bruto = this.brutoLinea(l);
            const valor = parseFloat(l.descValor || 0);
            if (!l.descTipo || !(valor > 0)) return 0;
            const d = l.descTipo === 'pct'
                ? mitadArriba(bruto * Math.round(valor * 100), 10000)
                : aCentavos(valor);
            return Math.min(d, bruto);
        },
        totalLinea(l) {
            return this.brutoLinea(l) - this.descuentoLinea(l);
        },
        get sumaLineas() {
            return this.lineas.reduce((s, l) => s + this.totalLinea(l), 0);
        },
        get descuentoGeneral() {
            const valor = parseFloat(this.descGlobal.valor || 0);
            if (!this.descGlobal.tipo || !(valor > 0)) return 0;
            const suma = this.sumaLineas;
            const d = this.descGlobal.tipo === 'pct'
                ? mitadArriba(suma * Math.round(valor * 100), 10000)
                : aCentavos(valor);
            return Math.min(d, suma);
        },
        get total() {
            return this.sumaLineas - this.descuentoGeneral;
        },
        get descuentoTotal() {
            return this.lineas.reduce((s, l) => s + this.descuentoLinea(l), 0) + this.descuentoGeneral;
        },
        get piezas() {
            return this.lineas.reduce((s, l) => s + parseFloat(l.cantidad || 0), 0);
        },
        dinero(centavos) {
            return formatear(centavos);
        },
        cantidadTexto(l) {
            return limpiarNumero(l.cantidad) || '0';
        },

        // ─── Avisos ─────────────────────────────────────────────────────────────────
        avisar(texto, tipo = 'info', ms = 4000) {
            this.aviso = texto;
            this.avisoTipo = tipo;
            clearTimeout(this._avisoT);
            if (ms) this._avisoT = setTimeout(() => (this.aviso = ''), ms);
        },

        // ─── Cámara (teléfono o tableta) ────────────────────────────────────────────
        abrirCamara() {
            if (!camaraDisponible()) {
                this.avisar('La cámara solo funciona con conexión segura (HTTPS). Abre el sistema con https://.', 'error', 8000);
                return;
            }
            this.camara = { iniciando: true, error: '', hayLinterna: false, linterna: false, leidos: 0 };
            this.modal = 'camara';
            const id = ++this._idCamara;
            this.$nextTick(() => this.arrancarCamara(id));
        },

        async arrancarCamara(id) {
            try {
                const lector = await iniciarLector(this.$refs.video, (codigo) => this.codigoDeCamara(codigo));
                // Si cerraron la ventana (o abrieron otra vez) mientras se pedía el permiso, este arranque se apaga de inmediato.
                if (id !== this._idCamara || this.modal !== 'camara') { lector.detener(); return; }
                this._lector = lector;
                this.camara.hayLinterna = lector.hayLinterna;
            } catch (e) {
                if (id === this._idCamara) this.camara.error = e.message;
            } finally {
                if (id === this._idCamara) this.camara.iniciando = false;
            }
        },

        cerrarCamara() {
            this._idCamara++;
            this._lector?.detener();
            this._lector = null;
        },

        async alternarLinterna() {
            try {
                await this._lector?.linterna(!this.camara.linterna);
                this.camara.linterna = !this.camara.linterna;
            } catch { this.avisar('No se pudo cambiar la linterna.', 'error'); }
        },

        /** La cámara deja la ventana abierta para escanear varios artículos seguidos. */
        codigoDeCamara(codigo) {
            if (this.modal !== 'camara') return; // llegó tarde: la ventana ya se cerró
            navigator.vibrate?.(60);
            this.camara.leidos++;
            this.entrada = codigo;
            this.enviarEntrada('camara');
        },

        // ─── Captura (escáner o teclado) ────────────────────────────────────────────
        async enviarEntrada(medio = 'lector') {
            const texto = this.entrada.trim();
            this.entrada = '';
            if (!texto) return;

            // "5*" deja el multiplicador listo para el siguiente artículo.
            const soloMult = texto.match(/^(\d+(?:\.\d+)?)\s*[*xX]$/);
            if (soloMult) {
                this.multiplicador = parseFloat(soloMult[1]);
                this.avisar(`Cantidad ×${limpiarNumero(this.multiplicador)}: escanea el artículo`, 'info', 0);
                return;
            }

            // "5*7501055300075" = 5 piezas de ese código.
            const conMult = texto.match(/^(\d+(?:\.\d+)?)\s*[*xX]\s*(.+)$/);
            let cantidad = this.multiplicador;
            let codigo = texto;
            if (conMult) { cantidad = parseFloat(conMult[1]); codigo = conMult[2].trim(); }
            this.multiplicador = null;

            this.cola.push({ codigo, cantidad, medio });
            if (!this.cargando) await this.procesarCola();
        },

        async procesarCola() {
            this.cargando = true;
            while (this.cola.length) {
                const { codigo, cantidad, medio } = this.cola.shift();
                await this.agregarPorCodigo(codigo, cantidad, medio);
            }
            this.cargando = false;
            this.enfocar();
        },

        /** `escaneo`: 'lector' o 'camara' cuando el código viene del campo de captura (queda en la bitácora de escaneos). */
        async consultar(texto, escaneo = null) {
            const marca = escaneo ? `&escaneo=1&medio=${escaneo}` : '';
            const r = await fetch(`${this.cfg.urlBuscar}?q=${encodeURIComponent(texto)}${marca}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!r.ok) throw new Error(r.status === 401 || r.status === 419 ? 'Tu sesión expiró. Vuelve a iniciar sesión.' : 'No se pudo consultar el artículo.');
            return r.json();
        },

        async agregarPorCodigo(codigo, cantidad, medio = 'lector') {
            try {
                const { exacto, articulos } = await this.consultar(codigo, medio);

                if (exacto && articulos.length === 1) {
                    this.agregar(articulos[0], cantidad);
                } else if (articulos.length && medio !== 'camara') {
                    this.abrirBusqueda(codigo, articulos);
                } else {
                    // Con la cámara una lectura debe coincidir exacta: una coincidencia parcial (el inicio de otra clave) es una lectura a medias.
                    this.avisar(medio === 'camara' ? `No se reconoció «${codigo}». Si se leyó mal, vuelve a escanearlo.` : `No existe el artículo «${codigo}».`, 'error', 6000);
                    // Con el lector o el teclado se ofrece el alta de inmediato. Con la cámara, un código que no existe puede
                    // ser una lectura a medias: solo se ofrece, y la persona decide si quiere darlo de alta.
                    if (this.cfg.puedeAlta && medio !== 'camara') this.abrirAlta(codigo);
                    else if (this.cfg.puedeAlta) this.ofrecerAlta(codigo);
                }
            } catch (e) {
                this.avisar(e.message, 'error', 6000);
            }
        },

        /** Muestra el botón «Dar de alta» para un código leído con la cámara que no existe (desaparece solo). */
        ofrecerAlta(codigo) {
            this.codigoSinAlta = codigo;
            clearTimeout(this._codigoSinAltaT);
            this._codigoSinAltaT = setTimeout(() => (this.codigoSinAlta = ''), 12000);
        },

        // ─── Carrito ────────────────────────────────────────────────────────────────
        agregar(articulo, cantidad = null) {
            this.codigoSinAlta = '';
            if (articulo.granel && cantidad === null) {
                this.peso = { articulo, valor: '' };
                this.modal = 'peso';
                return;
            }

            cantidad = cantidad ?? 1;
            if (!articulo.decimales && !Number.isInteger(cantidad)) {
                this.avisar(`«${articulo.descripcion}» solo se vende por piezas completas.`, 'error');
                return;
            }

            const existente = !articulo.granel
                ? this.lineas.findIndex((l) => l.id === articulo.id && !l.descTipo)
                : -1;

            if (existente >= 0) {
                this.lineas[existente].cantidad = limpiarNumero(parseFloat(this.lineas[existente].cantidad) + cantidad);
                this.sel = existente;
            } else {
                this.lineas.push({
                    uid: ++uid,
                    id: articulo.id,
                    clave: articulo.clave,
                    descripcion: articulo.descripcion,
                    unidad: articulo.unidad,
                    decimales: articulo.decimales,
                    granel: articulo.granel,
                    inventariable: articulo.inventariable,
                    existencia: articulo.existencia,
                    precios: articulo.precios,
                    cantidad: limpiarNumero(cantidad),
                    descTipo: '',
                    descValor: '',
                });
                this.sel = this.lineas.length - 1;
            }

            this.avisar('');
            this.$nextTick(() => {
                const fila = this.$refs.lista?.querySelector('[data-sel="true"]');
                fila?.scrollIntoView({ block: 'nearest' });
            });
        },

        /*
         * Lectura de báscula por puerto serie (Web Serial, solo Chrome o Edge). EXPERIMENTAL: no se ha
         * probado con una báscula real. Toma el primer número que se repite dos veces seguidas
         * (lectura estable). La velocidad (baudios) se puede cambiar guardando 'bascula-baud' en el navegador.
         */
        async leerBascula() {
            this.pesoError = '';
            if (!('serial' in navigator)) { this.pesoError = 'Este navegador no puede leer básculas. Usa Chrome o Edge.'; return; }

            this.pesoLeyendo = true;
            let puerto;
            try {
                puerto = await navigator.serial.requestPort();
                await puerto.open({ baudRate: Number(localStorage.getItem('bascula-baud')) || 9600 });

                const decodificador = new TextDecoderStream();
                const cierre = puerto.readable.pipeTo(decodificador.writable).catch(() => {});
                const lector = decodificador.readable.getReader();
                const limite = Date.now() + 5000;
                let texto = '';
                let leido = '';

                while (!leido && Date.now() < limite) {
                    const { value, done } = await lector.read();
                    if (done) break;
                    texto += value;
                    const numeros = [...texto.matchAll(/(\d+(?:[.,]\d+)?)/g)].map((m) => m[1].replace(',', '.'));
                    if (numeros.length >= 2 && numeros.at(-1) === numeros.at(-2)) leido = numeros.at(-1);
                }

                await lector.cancel().catch(() => {});
                await cierre;

                if (leido && parseFloat(leido) > 0) this.peso.valor = String(parseFloat(leido));
                else this.pesoError = 'No se obtuvo una lectura estable. Revisa que haya peso en la báscula.';
            } catch (e) {
                if (e.name !== 'NotFoundError') this.pesoError = 'No se pudo leer la báscula. Revisa el cable y la velocidad (baudios).';
            } finally {
                try { await puerto?.close(); } catch { /* ya estaba cerrado */ }
                this.pesoLeyendo = false;
            }
        },

        confirmarPeso() {
            const v = parseFloat(this.peso.valor);
            if (!(v > 0)) return;
            this.agregar(this.peso.articulo, v);
            this.modal = null;
            this.enfocar();
        },

        cambiarCantidad(i, delta) {
            const l = this.lineas[i];
            if (!l) return;
            const paso = l.decimales && l.granel ? 0.1 : 1;
            const nueva = Math.max(parseFloat(l.cantidad || 0) + delta * paso, 0);
            if (nueva <= 0) { this.quitar(i); return; }
            l.cantidad = limpiarNumero(nueva);
        },

        fijarCantidad(i, valor) {
            const l = this.lineas[i];
            let v = parseFloat(valor);
            if (!(v > 0)) { l.cantidad = limpiarNumero(l.cantidad) || '1'; return; }
            if (!l.decimales) v = Math.round(v) || 1;
            l.cantidad = limpiarNumero(v);
        },

        quitar(i) {
            this.lineas.splice(i, 1);
            this.sel = Math.min(this.sel, this.lineas.length - 1);
            this.enfocar();
        },

        vaciar() {
            if (!this.lineas.length) return;
            if (!confirm('¿Quitar todos los artículos de esta venta?')) return;
            this.limpiarVenta();
        },

        limpiarVenta() {
            this.lineas = [];
            this.cliente = null;
            this.descGlobal = { tipo: '', valor: '' };
            this.multiplicador = null;
            this.sel = -1;
            this.aviso = '';
            this.enfocar();
        },

        async refrescarPrecios() {
            const ids = [...new Set(this.lineas.map((l) => l.id))];
            if (!ids.length) return;
            try {
                const r = await fetch(`${this.cfg.urlPorIds}?ids=${ids.join(',')}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) return;
                const { articulos } = await r.json();
                const porId = Object.fromEntries(articulos.map((a) => [a.id, a]));
                const antes = this.lineas.length;
                this.lineas = this.lineas.filter((l) => porId[l.id]);
                let cambios = antes !== this.lineas.length;
                this.lineas.forEach((l) => {
                    const a = porId[l.id];
                    if (JSON.stringify(l.precios) !== JSON.stringify(a.precios)) cambios = true;
                    l.precios = a.precios;
                    l.descripcion = a.descripcion;
                    l.existencia = a.existencia;
                });
                if (cambios) this.avisar('Se actualizaron precios o artículos de esta venta.', 'info', 6000);
            } catch { /* si falla, el servidor revalida al cobrar */ }
        },

        // ─── Búsqueda por descripción (F2) ──────────────────────────────────────────
        abrirBusqueda(texto = '', resultados = []) {
            this.busqueda = { texto, resultados, cargando: false, enfocado: 0 };
            this.modal = 'buscar';
            this.$nextTick(() => this.$refs.buscarTexto?.focus());
        },

        async buscarTexto() {
            const texto = this.busqueda.texto.trim();
            if (texto.length < 2) { this.busqueda.resultados = []; return; }
            this.busqueda.cargando = true;
            try {
                const { articulos } = await this.consultar(texto);
                if (texto === this.busqueda.texto.trim()) { this.busqueda.resultados = articulos; this.busqueda.enfocado = 0; }
            } catch (e) {
                this.avisar(e.message, 'error');
            }
            this.busqueda.cargando = false;
        },

        /** Código leído con la cámara dentro de «Buscar»: se agrega como si lo hubiera escaneado un lector. */
        codigoDeBuscar(codigo) {
            this.modal = null;
            this.entrada = codigo;
            this.enviarEntrada('camara');
        },

        elegirResultado(a) {
            this.modal = null;
            this.agregar(a, this.multiplicador);
            this.multiplicador = null;
            this.enfocar();
        },

        // ─── Cliente (F7) ───────────────────────────────────────────────────────────
        abrirClientes() {
            if (!this.cfg.puedeCliente) { this.avisar('No tienes permiso para elegir clientes.', 'error'); return; }
            this.clientes = { texto: '', resultados: [], cargando: false };
            this.modal = 'cliente';
            this.$nextTick(() => this.$refs.clienteTexto?.focus());
        },

        async buscarClientes() {
            const texto = this.clientes.texto.trim();
            if (texto.length < 2) { this.clientes.resultados = []; return; }
            this.clientes.cargando = true;
            try {
                const r = await this.$wire.buscarClientes(texto);
                if (texto === this.clientes.texto.trim()) this.clientes.resultados = r;
            } catch (e) {
                this.avisar('No se pudo buscar clientes.', 'error');
            }
            this.clientes.cargando = false;
        },

        elegirCliente(c) {
            this.cliente = { id: c.id, nombre: c.nombre, lista: c.lista, credito: c.credito, disponible: c.disponible };
            this.modal = null;
            this.avisar(`Cliente: ${c.nombre}` + (c.lista > 1 ? ` · precio ${c.lista}` : ''), 'ok');
            this.enfocar();
        },

        quitarCliente() {
            this.cliente = null;
            this.modal = null;
            this.enfocar();
        },

        // ─── Descuentos ─────────────────────────────────────────────────────────────
        abrirDescuentoGeneral() {
            if (!this.cfg.puedeDescuento) { this.avisar('No tienes permiso para dar descuentos.', 'error'); return; }
            if (!this.lineas.length) return;
            this.modal = 'descuento';
            this.$nextTick(() => this.$refs.descValor?.focus());
        },

        aplicarDescuentoGeneral() {
            const v = parseFloat(this.descGlobal.valor);
            if (!(v > 0)) this.descGlobal = { tipo: '', valor: '' };
            else if (this.descGlobal.tipo === 'pct' && v > 100) { this.avisar('El descuento no puede pasar de 100%.', 'error'); return; }
            this.modal = null;
            this.enfocar();
        },

        quitarDescuentoGeneral() {
            this.descGlobal = { tipo: '', valor: '' };
            this.modal = null;
            this.enfocar();
        },

        abrirDescuentoLinea(i) {
            if (!this.cfg.puedeDescuento) { this.avisar('No tienes permiso para dar descuentos.', 'error'); return; }
            const l = this.lineas[i];
            this.descLinea = { indice: i, tipo: l.descTipo || 'pct', valor: l.descValor || '' };
            this.modal = 'descLinea';
            this.$nextTick(() => this.$refs.descLineaValor?.focus());
        },

        aplicarDescuentoLinea() {
            const l = this.lineas[this.descLinea.indice];
            const v = parseFloat(this.descLinea.valor);
            if (!l) return;
            if (!(v > 0)) { l.descTipo = ''; l.descValor = ''; }
            else if (this.descLinea.tipo === 'pct' && v > 100) { this.avisar('El descuento no puede pasar de 100%.', 'error'); return; }
            else { l.descTipo = this.descLinea.tipo; l.descValor = String(v); }
            this.modal = null;
            this.enfocar();
        },

        // ─── Cobro ──────────────────────────────────────────────────────────────────
        payload() {
            return this.lineas.map((l) => ({
                articulo_id: l.id,
                cantidad: String(l.cantidad),
                descuento_tipo: l.descTipo || null,
                descuento_valor: l.descTipo ? String(l.descValor) : null,
            }));
        },

        descuentoPayload() {
            return this.descGlobal.tipo && parseFloat(this.descGlobal.valor) > 0
                ? { tipo: this.descGlobal.tipo, valor: String(this.descGlobal.valor) }
                : {};
        },

        async abrirCobro() {
            if (!this.lineas.length) { this.avisar('Agrega al menos un artículo.', 'error'); return; }
            this.cobro = { pagos: [], procesando: true, error: '', totalServidor: null, disponible: 0 };
            this.modal = 'cobro';

            try {
                const r = await this.$wire.previsualizar(this.payload(), this.descuentoPayload(), this.listaActual, this.cliente?.id ?? null);
                if (!r.ok) { this.cobro.error = r.mensaje; this.cobro.procesando = false; return; }
                this.cobro.totalServidor = aCentavos(r.total);
                this.cobro.disponible = aCentavos(r.disponible);
                this.cobro.pagos = [{ forma: 'efectivo', monto: r.total, referencia: '', auto: true }];
                if (this.cobro.totalServidor !== this.total) this.avisar('El total se recalculó con los precios actuales.', 'info', 5000);
            } catch (e) {
                this.cobro.error = 'No se pudo conectar con el servidor. Revisa tu internet.';
            }
            this.cobro.procesando = false;
            this.$nextTick(() => this.$refs.montoCobro?.select?.());
        },

        get totalCobro() {
            return this.cobro.totalServidor ?? this.total;
        },
        get sinEfectivo() {
            return this.cobro.pagos.filter((p) => p.forma !== 'efectivo').reduce((s, p) => s + aCentavos(p.monto), 0);
        },
        get efectivoRecibido() {
            return this.cobro.pagos.filter((p) => p.forma === 'efectivo').reduce((s, p) => s + aCentavos(p.monto), 0);
        },
        get efectivoNecesario() {
            return Math.max(this.totalCobro - this.sinEfectivo, 0);
        },
        get faltante() {
            return Math.max(this.efectivoNecesario - this.efectivoRecibido, 0);
        },
        get cambio() {
            return Math.max(this.efectivoRecibido - this.efectivoNecesario, 0);
        },
        get creditoUsado() {
            return this.cobro.pagos.filter((p) => p.forma === 'credito').reduce((s, p) => s + aCentavos(p.monto), 0);
        },
        get puedeConfirmar() {
            return !this.cobro.procesando
                && this.cobro.totalServidor !== null
                && this.creditoUsado <= (this.cobro.disponible || 0)
                && this.sinEfectivo <= this.totalCobro
                && this.faltante === 0
                && !(this.efectivoNecesario === 0 && this.efectivoRecibido > 0);
        },

        agregarPago(forma) {
            // Lo que falta por cubrir sin contar el efectivo: el efectivo se ajusta solo para completar el resto.
            let restante = forma === 'efectivo'
                ? Math.max(this.totalCobro - this.sinEfectivo - this.efectivoRecibido, 0)
                : Math.max(this.totalCobro - this.sinEfectivo, 0);
            if (forma === 'credito') restante = Math.min(restante, Math.max((this.cobro.disponible || 0) - this.creditoUsado, 0));
            this.cobro.pagos.push({ forma, monto: (restante / 100).toFixed(2), referencia: '', auto: forma === 'efectivo' });
            this.sincronizarEfectivo();
        },
        quitarPago(i) {
            this.cobro.pagos.splice(i, 1);
            this.sincronizarEfectivo();
        },
        // Mientras el efectivo no se haya escrito a mano, siempre es lo que falta para completar el total.
        sincronizarEfectivo() {
            const fila = this.cobro.pagos.find((p) => p.forma === 'efectivo' && p.auto);
            if (fila) fila.monto = (this.efectivoNecesario / 100).toFixed(2);
        },
        fijarEfectivo(centavos) {
            const fila = this.cobro.pagos.find((p) => p.forma === 'efectivo');
            const monto = (Math.max(centavos, this.efectivoNecesario) / 100).toFixed(2);
            const exacto = centavos <= this.efectivoNecesario;
            if (fila) { fila.monto = monto; fila.auto = exacto; }
            else this.cobro.pagos.push({ forma: 'efectivo', monto, referencia: '', auto: exacto });
        },
        get billetes() {
            return [50, 100, 200, 500, 1000].map((b) => b * 100).filter((b) => b >= this.efectivoNecesario && this.efectivoNecesario > 0).slice(0, 4);
        },

        async confirmarCobro() {
            if (!this.puedeConfirmar) return;
            this.cobro.procesando = true;
            this.cobro.error = '';

            try {
                const pagos = this.cobro.pagos
                    .filter((p) => aCentavos(p.monto) > 0)
                    .map((p) => ({ forma: p.forma, monto: String(p.monto), referencia: p.referencia || null }));

                const r = await this.$wire.cobrar(this.payload(), pagos, this.descuentoPayload(), this.listaActual, this.cliente?.id ?? null);

                if (!r.ok) {
                    this.cobro.error = r.mensaje;
                    this.cobro.procesando = false;
                    return;
                }

                this.exito = r;
                this.limpiarVenta();
                this.modal = 'exito';
                if (this.cfg.autoImprimir) this.imprimirTicket(r.id);
            } catch (e) {
                this.cobro.error = 'No se pudo conectar con el servidor. La venta NO se registró: revisa tu internet e inténtalo de nuevo.';
            }
            this.cobro.procesando = false;
        },

        imprimirTicket(id) {
            const marco = document.createElement('iframe');
            marco.style.cssText = 'position:fixed;width:0;height:0;border:0;visibility:hidden';
            marco.src = this.cfg.urlTicket.replace('__ID__', id) + '?imprimir=1';
            document.body.appendChild(marco);
            setTimeout(() => marco.remove(), 60000);
        },

        nuevaVenta() {
            this.exito = null;
            this.modal = null;
            this.enfocar();
        },

        // ─── Ventas en espera ───────────────────────────────────────────────────────
        async guardarEnEspera() {
            if (!this.lineas.length) { this.avisar('No hay nada que guardar.', 'error'); return; }
            const r = await this.$wire.guardarEspera(this.lineas, this.descGlobal, this.esperas.nombre || null);
            if (!r.ok) { this.avisar(r.mensaje, 'error'); return; }
            this.limpiarVenta();
            this.modal = null;
            this.esperas.nombre = '';
            this.avisar('Venta guardada en espera. Ya puedes atender a otro cliente.', 'ok');
        },

        async abrirEsperas() {
            this.modal = 'esperas';
            this.esperas.cargando = true;
            this.esperas.lista = await this.$wire.listarEsperas();
            this.esperas.cargando = false;
        },

        async recuperarEspera(id) {
            if (this.lineas.length) { this.avisar('Termina o guarda la venta actual antes de recuperar otra.', 'error'); return; }
            const r = await this.$wire.recuperarEspera(id);
            if (!r.ok) { this.avisar(r.mensaje, 'error'); return; }
            this.lineas = r.lineas.map((l) => ({ ...l, uid: ++uid }));
            this.descGlobal = r.descGlobal || { tipo: '', valor: '' };
            this.modal = null;
            await this.refrescarPrecios();
            this.avisar('Venta recuperada.', 'ok');
            this.enfocar();
        },

        async descartarEspera(id) {
            if (!confirm('¿Descartar esta venta en espera?')) return;
            await this.$wire.descartarEspera(id);
            this.esperas.lista = this.esperas.lista.filter((e) => e.id !== id);
        },

        // ─── Alta rápida ────────────────────────────────────────────────────────────
        abrirAlta(clave = '') {
            this.codigoSinAlta = '';
            this.alta = { ...this.alta, clave, descripcion: '', precio: '', error: '', guardando: false };
            this.modal = 'alta';
            this.$nextTick(() => this.$refs.altaDescripcion?.focus());
        },

        async guardarAlta() {
            this.alta.guardando = true;
            this.alta.error = '';
            const r = await this.$wire.altaRapida({
                clave: this.alta.clave,
                descripcion: this.alta.descripcion,
                precio: this.alta.precio,
                departamento_id: this.alta.departamento_id,
                unidad_medida_id: this.alta.unidad_medida_id,
                iva_id: this.alta.iva_id,
            });
            this.alta.guardando = false;
            if (!r.ok) { this.alta.error = r.mensaje; return; }
            this.modal = null;
            this.agregar(r.articulo, this.multiplicador);
            this.multiplicador = null;
            this.avisar(`Artículo «${r.articulo.descripcion}» creado y agregado.`, 'ok');
            this.enfocar();
        },

        // ─── Teclado ────────────────────────────────────────────────────────────────
        teclado(e) {
            // Con el escáner de cámara de «Buscar» abierto, Esc solo lo cierra a él (ver escaner-camara.blade.php).
            if (e.key === 'Escape' && document.querySelector('[data-escaner-abierto]')) return;

            const enModal = this.modal !== null;

            if (e.key === 'Escape') {
                if (enModal && this.modal !== 'exito') { this.modal = null; this.enfocar(); e.preventDefault(); }
                else if (this.modal === 'exito') this.nuevaVenta();
                else if (this.multiplicador) { this.multiplicador = null; this.aviso = ''; }
                return;
            }

            if (this.modal === 'exito' && e.key === 'Enter') { e.preventDefault(); this.nuevaVenta(); return; }
            if (this.modal === 'cobro' && e.key === 'Enter' && e.target.tagName !== 'BUTTON') { e.preventDefault(); this.confirmarCobro(); return; }

            const teclas = {
                F2: () => this.abrirBusqueda(),
                F3: () => (this.lineas.length ? (this.modal = 'espera-guardar') : this.avisar('No hay nada que guardar.', 'error')),
                F4: () => this.abrirEsperas(),
                F6: () => this.abrirDescuentoGeneral(),
                F7: () => this.abrirClientes(),
                F8: () => this.abrirCobro(),
            };

            if (teclas[e.key]) { e.preventDefault(); if (!e.repeat && !enModal) teclas[e.key](); return; }
            if (e.key === 'Enter' && e.ctrlKey && !enModal) { e.preventDefault(); this.abrirCobro(); return; }
            if (enModal) return;

            const vacio = this.entrada === '';
            if (e.key === 'ArrowDown' && this.lineas.length) { e.preventDefault(); this.sel = Math.min(this.sel + 1, this.lineas.length - 1); }
            else if (e.key === 'ArrowUp' && this.lineas.length) { e.preventDefault(); this.sel = Math.max(this.sel - 1, 0); }
            else if (e.key === 'Delete' && vacio && this.sel >= 0) { e.preventDefault(); this.quitar(this.sel); }
            else if ((e.key === '+' || e.key === 'Add') && vacio && this.sel >= 0) { e.preventDefault(); this.cambiarCantidad(this.sel, 1); }
            else if ((e.key === '-' || e.key === 'Subtract') && vacio && this.sel >= 0) { e.preventDefault(); this.cambiarCantidad(this.sel, -1); }
            else this.capturarEnCampo(e);
        },

        /**
         * Un lector de códigos escribe como un teclado. Si el foco no está en un campo (se tocó una zona vacía, la lista
         * o un botón), el primer carácter que llegue pasa al campo de captura y se le da el foco: el resto de la lectura
         * y el Enter final caen ahí solos. Con una ventana abierta o escribiendo en otro campo no se interviene.
         */
        capturarEnCampo(e) {
            if (e.key.length !== 1 || e.key === ' ' || e.ctrlKey || e.metaKey || e.altKey || e.isComposing) return false;
            if (e.target.closest?.('input, textarea, select, [contenteditable]')) return false;

            const campo = this.$refs.entrada;
            if (!campo) return false;

            e.preventDefault();
            this.entrada += e.key;
            campo.focus();
            this.$nextTick(() => campo.setSelectionRange(campo.value.length, campo.value.length));

            return true;
        },
    }));
}
