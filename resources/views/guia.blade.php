@php
    /*
     * Guía de uso en palabras de todos los días. Cada sección es un arreglo:
     *   id, titulo, icono, para (quién la usa), intro, bloques[]
     * y cada bloque es uno de:
     *   ['p' => texto]   ['pasos' => [...]]   ['lista' => [...]]   ['consejo' => texto]   ['cuidado' => texto]
     *   ['tabla' => [[izquierda, derecha], ...]]   ['enlaces' => [[etiqueta, ruta, permiso|null], ...]]
     */
    $secciones = [
        [
            'id' => 'empezar', 'titulo' => 'Antes de empezar', 'icono' => 'compass', 'para' => 'Todos',
            'intro' => 'Este sistema es como una caja registradora que vive en tu computadora o tu teléfono. Además de cobrar, se acuerda de todo: qué tienes en la tienda, qué vendiste y cuánto ganaste.',
            'bloques' => [
                ['pasos' => [
                    'Abre la dirección de tu tienda en el navegador (Chrome, Edge o Safari).',
                    'Escribe tu **usuario** (o tu correo) y tu **contraseña**. Toca **Entrar**.',
                    'Para salir, toca la flechita de salida (arriba a la derecha en el teléfono, abajo del menú en la computadora).',
                ]],
                ['p' => '**Cómo moverte.** En la computadora, el menú está a la izquierda. En el teléfono, está abajo: toca **Más** para ver todo lo demás. Solo ves lo que tu trabajo necesita.'],
                ['tabla' => [
                    ['Administrador', 'El dueño. Puede hacer todo.'],
                    ['Encargado', 'Persona de confianza. Supervisa, pero no cambia usuarios ni la configuración.'],
                    ['Cajero', 'Quien cobra. Abre su caja, vende, y cierra su caja.'],
                ]],
                ['consejo' => 'Puedes tener el sistema como una app en tu teléfono. En Chrome toca los tres puntitos y elige **Agregar a pantalla de inicio**. En iPhone usa Safari: botón de compartir y luego **Agregar a pantalla de inicio**.'],
                ['cuidado' => 'El sistema necesita internet para cobrar. Si se va el internet no se puede vender; cuando regrese, lo que tenías en pantalla sigue ahí.'],
            ],
        ],
        [
            'id' => 'dueno', 'titulo' => 'Primeros pasos del dueño: dejar la tienda lista', 'icono' => 'rocket-launch', 'para' => 'Dueño',
            'intro' => 'Haz esto una sola vez, en orden. Cada paso tiene su pantalla en el menú.',
            'bloques' => [
                ['pasos' => [
                    'Cambia tu contraseña por una que solo tú sepas. (**Mi cuenta**)',
                    'Escribe los datos de tu tienda: nombre, dirección, teléfono y el mensaje que sale al final del ticket. Puedes subir tu logotipo. (**Configuración**)',
                    'Revisa los **departamentos** (grupos de productos, como lácteos o bebidas), las **unidades** (pieza, kilo, litro) y los **impuestos**. Las unidades y los impuestos más comunes ya vienen listos. De departamentos solo viene uno, «General»: crea aquí los tuyos (lácteos, bebidas, botanas…). (**Catálogos**)',
                    'Revisa tu caja y elige si tu impresora usa papel angosto (58) o normal (80). Con el botón de la impresora imprimes una hoja de prueba. (**Cajas**)',
                    'Da de alta a tus cajeros: les pones un usuario y una contraseña, y les entregas los datos. (**Usuarios**)',
                    'Anota a quienes te surten. (**Compras → Proveedores**)',
                    'Da de alta tus productos, uno por uno o todos juntos desde un Excel. (**Artículos**)',
                    'Dile al sistema qué tienes hoy en la tienda: al crear cada producto escribe su **Existencia inicial**, o después registra una compra o haz un conteo. (**Artículos**, **Compras** o **Inventario**)',
                ]],
                ['enlaces' => [
                    ['Mi cuenta', 'profile', null], ['Configuración', 'configuracion', 'configuracion.editar'], ['Catálogos', 'catalogos', 'catalogos.administrar'],
                    ['Cajas', 'cajas', 'cajas.administrar'], ['Usuarios', 'usuarios', 'usuarios.administrar'], ['Artículos', 'articulos.index', 'articulos.ver'],
                ]],
                ['consejo' => 'Antes de pedirle a alguien que cobre, haz una venta de prueba tú mismo con un producto barato y mira cómo sale el ticket.'],
            ],
        ],
        [
            'id' => 'articulos', 'titulo' => 'Tus productos (artículos)', 'icono' => 'package', 'para' => 'Dueño · Encargado',
            'intro' => 'Cada producto que vendes es un artículo. Aquí le dices cómo se llama, cuánto cuesta y cuánto vale.',
            'bloques' => [
                ['pasos' => [
                    'Entra a **Artículos** y toca **Nuevo**.',
                    'En **Clave principal**, escanea el código de barras del producto (o escríbelo). En el teléfono, toca el botón de la cámara y apunta al código: se escribe solo. No puede haber dos productos con la misma clave.',
                    'En **Descripción** escribe el nombre del producto. Elige su **departamento** y su **unidad de medida** (pieza, kilogramo…).',
                    'Elige el **IVA** que lleva. Viene marcado IVA 16 %; los alimentos básicos suelen ser IVA 0 %, confírmalo con tu contador. Hazlo **antes** de poner los precios, porque el precio depende de él.',
                    'Escribe cuánto te **cuesta** a ti (sin impuestos). Luego escribe el **Precio 1** (el que paga el cliente, con impuestos incluidos) o, si prefieres, cuánto quieres ganar en **Margen sobre el costo (%)** y el sistema calcula el precio.',
                    'En **Existencia inicial (cuánto hay hoy)** escribe cuántas piezas, kilos o litros tienes ahora en la tienda. Solo aparece al crear el producto y solo si tienes permiso para mover inventario; si lo dejas vacío, empieza en cero.',
                    'Escribe la **existencia mínima** que quieres tener. Cuando baje de ahí, el sistema te avisa.',
                    'Toca **Crear artículo**.',
                ]],
                ['p' => '**Ejemplo del precio.** Si una leche te cuesta $20 y quieres ganarle 35 %: con **IVA 0 %** el sistema pone $27 como precio. Con **IVA 16 %** pone $31.32, porque al precio le suma el impuesto (el precio que ves ya lo incluye). También puedes hacerlo al revés: escribes el precio y él te dice cuánto ganas. Ese «margen» es sobre lo que **te costó**; en Reportes la utilidad se mide sobre lo que **vendiste**, por eso los porcentajes no coinciden.'],
                ['p' => '**Claves alternas.** Si un producto tiene dos códigos de barras (por ejemplo una presentación nueva), agrega el segundo como clave alterna (también puedes escanearlo con la cámara). Cualquiera de los dos lo encuentra.'],
                ['p' => '**Producto a granel.** Si lo vendes por peso (frutas, queso), marca **Se vende a granel** y elige kilo como unidad. Al venderlo, el sistema te pregunta cuánto pesa.'],
                ['p' => '**Subir muchos productos de golpe.** Entra a **Artículos → Importar**, descarga la plantilla de Excel, llénala y súbela. Si algo está mal en una fila, el sistema te dice cuál y no guarda nada, para que lo corrijas y lo vuelvas a subir sin que se repita nada.'],
                ['p' => '**Foto del producto.** En la pantalla del artículo, en la tarjeta **Imagen**, toca **Tomar foto** (en el teléfono abre la cámara) o **Elegir de la galería** para usar una foto que ya tienes. Las fotos grandes se reducen solas antes de subirse, así que no tienes que preocuparte por su tamaño. Para cambiarla, vuelve a elegir otra; para quitarla, marca **Quitar imagen**.'],
                ['p' => '**Etiquetas de precio.** En **Artículos → Etiquetas** eliges productos y cuántas copias, y las imprimes con su código de barras.'],
                ['p' => '**Cambiar un producto o su precio.** En **Artículos** busca el producto, tócalo, cambia lo que necesites (descripción, costo, precio, mínimo…) y toca **Guardar cambios**. Lo vendido antes no cambia: cada venta guarda el precio con el que se cobró.'],
                ['consejo' => 'Cada vez que cambias un costo o un precio, el sistema lo anota (quién y cuándo). Lo ves al final de la pantalla del artículo.'],
            ],
        ],
        [
            'id' => 'compras', 'titulo' => 'Meter mercancía a la tienda (compras)', 'icono' => 'truck', 'para' => 'Dueño · Encargado',
            'intro' => 'Cuando llega mercancía, la registras para que el sistema sepa que tienes más y cuánto te costó. También aquí te ayuda a decidir qué pedir.',
            'bloques' => [
                ['pasos' => [
                    'Entra a **Compras** y toca **Nueva compra**.',
                    'Elige el **proveedor**, escribe el número de factura (si tienes) y revisa la fecha.',
                    'Escanea cada producto que llegó (en el teléfono puedes usar el botón de la cámara). Si lo escaneas otra vez, suma una pieza.',
                    'Revisa la **cantidad** y el **costo** de cada uno (sin impuestos).',
                    'Toca **Registrar compra**.',
                ]],
                ['p' => '**¿Qué cambia solo?** Sube lo que tienes de cada producto y se actualiza cuánto te cuesta. Si te equivocaste, abre la compra y toca **Cancelar compra**: lo que entró vuelve a salir.'],
                ['p' => '**¿Qué conviene comprar?** Entra a **Compras → Sugerencia de compra**. El sistema revisa lo que vendiste en los últimos días y te dice qué productos pedir y cuántas piezas de cada uno, para que te alcancen unos **15 días** sin que ninguno baje de su mínimo.'],
                ['lista' => [
                    'Arriba puedes cambiar cuántos **días de venta** revisar (30 por defecto), cuántos **días quieres cubrir** (15 por defecto) y el **departamento**.',
                    'En cada producto ves cuánto tienes, cuánto vendiste, **cuántos días te alcanza** y cuánto **comprar**. Los que ya se acabaron salen primero, luego los que están bajo el mínimo.',
                    'Toca **Crear compra** para abrir una compra nueva con esos productos y cantidades ya puestos; puedes corregirlos antes de registrarla. O toca **Exportar a Excel** para llevarle la lista a tu proveedor.',
                ]],
                ['consejo' => 'Un producto que apenas empezaste a vender puede salir con menos cantidad de la que necesitas, porque el sistema reparte lo vendido entre todos los días revisados. Corrige la cantidad si lo conoces mejor que él.'],
                ['p' => '**Costo promedio o último costo.** Imagina que compraste 10 piezas a $10 y luego 10 a $20. El **último costo** dice $20. El **costo promedio** dice $15 (la mezcla). Tú eliges cuál usar en **Configuración**; el sistema guarda los dos siempre.'],
            ],
        ],
        [
            'id' => 'inventario', 'titulo' => 'Saber cuánto tienes (inventario)', 'icono' => 'stack', 'para' => 'Dueño · Encargado',
            'intro' => 'Aquí ves cuánto hay de cada producto y qué se está acabando.',
            'bloques' => [
                ['p' => 'Entra a **Inventario**. Arriba ves cuántos productos están **bajo el mínimo** (se están acabando) y cuáles están **agotados**. Toca cualquiera de esas tarjetas para ver solo esos. Para encontrar un producto, escribe su nombre o clave en el buscador, o toca el botón de la cámara en el teléfono y escanea su código. Para saber cuánto pedir de cada uno, usa **Compras → Sugerencia de compra**.'],
                ['p' => '**El cuaderno de cada producto.** Al tocar un producto ves su historial: cuándo entró, cuándo se vendió, quién lo movió. A esto el sistema le llama *kardex*.'],
                ['p' => '**Ajustes.** Si algo se rompe, caduca o se pierde, entra a **Inventario → Ajustes**, elige el producto (escaneándolo o buscándolo por nombre), el motivo y la cantidad. Siempre queda anotado.'],
                ['p' => '**Contar la tienda (conteo).** Para revisar que el sistema y los estantes digan lo mismo:'],
                ['pasos' => [
                    'Entra a **Inventario → Conteo físico** y toca **Empezar a contar**.',
                    'Escanea un producto (o toca el botón de la cámara en el teléfono) y escribe **cuántos hay en el estante**. Guarda y sigue con el siguiente.',
                    'Al terminar, mira la tabla: te muestra la diferencia de cada uno.',
                    'Si todo está bien, toca **Aplicar al inventario**. No se puede deshacer.',
                ]],
                ['p' => '**Si te equivocaste en un conteo**, antes de aplicarlo toca **Cancelar conteo**: no cambia nada del inventario y puedes empezar otro.'],
                ['p' => '**Existencia en negativo.** Si en **Configuración** está activado *Permitir vender aunque no haya existencia*, el sistema deja vender un producto que según él ya se acabó, y la existencia queda en negativo. Casi siempre significa que falta registrar una compra: regístrala y se corrige sola. Si prefieres que no se pueda vender sin existencia, desactiva esa opción.'],
                ['consejo' => 'No tienes que contar toda la tienda de una vez. Puedes contar un pasillo hoy y otro mañana.'],
            ],
        ],
        [
            'id' => 'abrir', 'titulo' => 'Abrir tu caja para empezar a vender (turno)', 'icono' => 'wallet', 'para' => 'Cajero',
            'intro' => 'Tu **turno** es tu jornada en la caja: desde que la abres hasta que la cierras. Todo lo que cobres queda a tu nombre.',
            'bloques' => [
                ['pasos' => [
                    'Entra a **Mi caja**.',
                    'Elige tu caja.',
                    'Cuenta el dinero que hay en el cajón para dar cambio y escríbelo en **Fondo inicial**.',
                    'Toca **Abrir turno**.',
                ]],
                ['cuidado' => 'Sin turno abierto el sistema no deja cobrar. Y no puede haber dos turnos abiertos en la misma caja.'],
            ],
        ],
        [
            'id' => 'vender', 'titulo' => 'Cobrar a un cliente (venta)', 'icono' => 'cash-register', 'para' => 'Cajero',
            'intro' => 'La pantalla de **Venta** está hecha para ir rápido, con el escáner de código de barras o con la cámara del teléfono. El cursor siempre está listo para escanear.',
            'bloques' => [
                ['pasos' => [
                    'Toca **Venta** en el menú.',
                    '**Escanea** cada producto del cliente (o escribe su clave y presiona Enter). Aparece en la lista con su precio.',
                    'Cuando termines, toca **Cobrar** (o la tecla **F8**).',
                    'Elige cómo paga el cliente (abajo lo explicamos) y toca **Confirmar cobro**.',
                    'Si te dio efectivo, la pantalla te dice grande **cuánto cambio entregar**. Toca **Imprimir ticket** si lo quiere.',
                    'Toca **Nueva venta** (o Enter) y sigue con el siguiente cliente.',
                ]],
                ['p' => '**¿Sin escáner? Usa la cámara del teléfono.** En el celular o la tableta, junto al cuadro de captura, aparece un botón con una cámara. Tócalo, permite el uso de la cámara y apunta al código de barras: el producto se agrega solo y la ventana sigue abierta para que escanees los siguientes. Si necesitas dos piezas del mismo producto, aparta el código de la cámara un momento y vuelve a mostrarlo. Toca **Listo** para regresar a la venta.'],
                ['cuidado' => 'La cámara solo funciona cuando la dirección del sistema empieza con **https://**. Si te avisa que no puede abrirla, pídele a quien instaló el sistema que lo configure así.'],
                ['p' => '**Atajos para ir más rápido:**'],
                ['tabla' => [
                    ['5* y luego el código', 'Cinco piezas del mismo producto (también puedes escribir 5*código)'],
                    ['F2', 'Buscar un producto por su nombre, cuando no tiene código'],
                    ['F3', 'Dejar esta venta en espera'],
                    ['F4', 'Recuperar una venta que dejaste en espera'],
                    ['F6', 'Dar un descuento a toda la venta (solo con permiso)'],
                    ['F7', 'Elegir a un cliente'],
                    ['F8', 'Cobrar'],
                    ['Flechas ↑ ↓', 'Moverte por la lista de productos'],
                    ['+  y  −', 'Subir o bajar la cantidad del producto marcado'],
                    ['Supr', 'Quitar el producto marcado'],
                    ['Esc', 'Cerrar una ventana'],
                ]],
                ['p' => '**Productos que se pesan.** Si escaneas fruta o queso, el sistema te pregunta **cuánto pesa**. Escribe el peso (por ejemplo 0.375) y Enter.'],
                ['p' => '**Un producto sin código.** Toca **Buscar** (F2) y escribe parte del nombre. Toca el que sea. Si prefieres escanear, en el teléfono la ventana de búsqueda también tiene el botón de la cámara: lee el código y agrega el producto.'],
                ['p' => '**Un producto que no existe.** El sistema te avisa. Si tienes permiso para crear artículos y poner precios, te deja darlo de alta ahí mismo; si no, avisa al encargado.'],
                ['p' => '**Se acerca otro cliente con prisa.** Toca **Espera** (F3) para guardar esta venta, atiende al otro y luego **Recuperar** (F4). Para recuperar una venta la pantalla debe estar vacía (termina o guarda la que tengas). Las ventas en espera son de la caja, no de una persona, y caben hasta 30. Al guardarlas se guardan los productos y el descuento, pero **no** el cliente: vuelve a elegirlo con F7.'],
                ['p' => '**Descuentos.** Con permiso, toca el símbolo de porcentaje junto al producto, o **Descuento** (F6) para toda la venta. Puedes dar un porcentaje o una cantidad en pesos.'],
                ['p' => '**Cómo paga el cliente:**'],
                ['lista' => [
                    '**Efectivo.** Escribe cuánto te dio (o toca el billete). Verás el cambio.',
                    '**Tarjeta o transferencia.** Elige la forma y listo. Puedes anotar el número de autorización.',
                    '**Mezclado.** Por ejemplo, $100 con tarjeta y el resto en efectivo. Toca **+ Tarjeta** (o **+ Transferencia**), escribe cuánto paga con ella y el efectivo se acomoda solo con lo que falta. Si el cliente da un billete, toca el billete o escribe el efectivo y verás el cambio.',
                    '**A crédito (fiado).** Primero elige al cliente con F7. Mira más abajo, en *Clientes que te deben*.',
                ]],
                ['cuidado' => 'La pantalla te enseña un total, pero **el sistema lo vuelve a calcular con los precios de verdad** antes de cobrar. Si algo cambió, te lo avisa.'],
            ],
        ],
        [
            'id' => 'problemas-venta', 'titulo' => 'Cuando una venta salió mal (cancelar y devolver)', 'icono' => 'arrow-u-up-left', 'para' => 'Encargado · Dueño',
            'intro' => 'Nada se borra: todo queda anotado con tu nombre y el motivo.',
            'bloques' => [
                ['p' => '**Cancelar una venta completa.** Úsalo si te equivocaste al cobrar. Solo se puede **mientras el turno en que se hizo la venta siga abierto**, y solo si esa venta **no tiene devoluciones**. Si ya se cerró ese turno, usa una devolución.'],
                ['pasos' => [
                    'Entra a **Ventas** y abre la venta.',
                    'Toca **Cancelar venta** y escribe el motivo.',
                    'Los productos regresan al inventario.',
                    'Si el cliente había pagado en efectivo, el cajero debe regresarle ese dinero: el sistema ya no cuenta esa venta en el corte.',
                ]],
                ['p' => '**Devolución.** Úsala cuando el cliente regresa algo después (o ya se cerró el turno).'],
                ['pasos' => [
                    'Entra a **Ventas** y abre la venta.',
                    'Toca **Devolución**.',
                    'Escribe cuántas piezas regresan y el motivo.',
                    'Entrégale al cliente el dinero que muestra el sistema. Sale del cajón de tu turno, así que necesitas tener tu turno abierto.',
                ]],
                ['p' => '**Encontrar una venta de otro día.** En **Ventas** escribe el **folio** (por ejemplo A-000123) en el buscador, o elige las fechas **Desde** y **Hasta**.'],
                ['p' => '**Imprimir otra vez un ticket.** Abre la venta y toca **Ver / reimprimir ticket**. Un cajero solo ve sus propias ventas.'],
            ],
        ],
        [
            'id' => 'dinero-caja', 'titulo' => 'Dinero que entra o sale de la caja', 'icono' => 'hand-coins', 'para' => 'Encargado · Dueño',
            'intro' => 'Si agregas o sacas efectivo del cajón por algo que no es una venta, anótalo para que la cuenta salga bien.',
            'bloques' => [
                ['pasos' => [
                    'Entra a **Mi caja**.',
                    'Toca **Entrada de efectivo** (si metes dinero) o **Retiro de efectivo** (si sacas dinero).',
                    'Escribe cuánto y **por qué** (por ejemplo: «pago al de los refrescos» o «depósito al banco»).',
                ]],
                ['cuidado' => 'No puedes retirar más dinero del que hay en el cajón.'],
            ],
        ],
        [
            'id' => 'cerrar', 'titulo' => 'Cerrar tu caja al final (corte)', 'icono' => 'lock-key', 'para' => 'Cajero',
            'intro' => 'Al terminar, cuentas el dinero y el sistema te dice si **cuadra**, si **sobra** o si **falta**.',
            'bloques' => [
                ['p' => '**Corte parcial (X).** Es una foto de cómo va el día. No cierra nada. Úsalo cuando quieras ver cuánto llevas. El cajero ve sus ventas y movimientos, pero **no** cuánto efectivo debería haber: eso lo ve el encargado, para que el conteo al cerrar sea honesto.'],
                ['p' => '**Imprimir un corte otra vez.** En **Mi caja**, en la tarjeta **Cortes de este turno**, abre el corte que quieras y vuelve a imprimirlo. Los turnos ya cerrados los ve el encargado en **Turnos y cortes** (menú → Administración).'],
                ['p' => '**Corte final (Z).** Cierra tu turno.'],
                ['pasos' => [
                    'Entra a **Mi caja** y toca **Cerrar turno**.',
                    'Saca **todo el efectivo** del cajón y cuéntalo (billetes y monedas).',
                    'Escribe el total que contaste.',
                    'Confirma. El sistema te muestra si cuadra y te deja imprimir el corte.',
                    'Firma el papel y entrégaselo al encargado junto con el dinero.',
                ]],
                ['consejo' => 'Es normal que de vez en cuando falten o sobren unos pesos. Si siempre falta, avísale al dueño. Por tu seguridad, el sistema no te dice cuánto debería haber hasta que tú cuentas.'],
            ],
        ],
        [
            'id' => 'clientes', 'titulo' => 'Clientes que te deben (crédito)', 'icono' => 'users', 'para' => 'Cajero · Encargado · Dueño',
            'intro' => 'Algunos clientes te compran «fiado». El sistema apunta cuánto te debe cada uno y no deja que se pase de su límite.',
            'bloques' => [
                ['pasos' => [
                    '**Dar de alta al cliente:** el encargado o el dueño, en **Clientes**, toca **Nuevo cliente** (nombre y teléfono). El cajero puede elegir clientes y cobrarles, pero no crearlos.',
                    '**Darle crédito:** el dueño le pone un **límite de crédito** (lo máximo que puede deber). Con 0 no se le fía.',
                    '**Venderle:** en la pantalla de venta toca **Cliente** (F7) y búscalo. Al cobrar elige **+ A crédito**.',
                    '**Cuando te paga:** abre al cliente en **Clientes** y toca **Registrar abono**. Escribe cuánto te dio y cómo.',
                ]],
                ['p' => '**Precios de mayoreo.** Si el dueño le asigna «Precio 2» o «Precio 3» a un cliente, al elegirlo en la venta, todo se cobra con esos precios.'],
                ['p' => '**Estado de cuenta.** Dentro de cada cliente ves todo lo que ha comprado a crédito y lo que ha abonado. Puedes imprimirlo.'],
                ['cuidado' => 'Si recibes un abono en **efectivo**, el dinero entra a tu cajón: necesitas tener tu turno abierto, y aparecerá en tu corte.'],
            ],
        ],
        [
            'id' => 'reportes', 'titulo' => 'Saber cuánto vendiste y cuánto ganaste (reportes)', 'icono' => 'chart-bar', 'para' => 'Dueño · Encargado',
            'intro' => 'La pantalla de **Inicio** te muestra lo más importante de hoy. En **Reportes** puedes ver más detalle y bajarlo a Excel.',
            'bloques' => [
                ['p' => '**En Inicio verás:** lo que vendiste hoy y cómo va contra ayer, cuántas ventas, el ticket promedio (lo que gasta cada cliente en promedio), cuánto ganaste, las ventas de los últimos 7 días, lo más vendido, lo que hay que reabastecer, qué cajas están abiertas y cuánto te deben.'],
                ['p' => '**En Reportes** eliges fechas (hoy, ayer, este mes…) y ves las ventas por día, por departamento, por producto, por cajero y por forma de pago. Arriba a la derecha tocas **Exportar a Excel**. El archivo lleva en su nombre el reporte y las fechas (por ejemplo *reporte-por-articulo-2026-10-01-a-2026-10-25*), y los códigos de barras largos se ven completos, sin convertirse en números como 7.5E+12.'],
                ['p' => '**¿Qué es la utilidad?** Es lo que ganas en los productos, **antes de pagar** renta, luz y sueldos. Ejemplo: un refresco que te costó $10 y vendiste en $14.50 (sin impuestos) te deja $4.50 de utilidad.'],
                ['consejo' => 'La utilidad usa lo que **te costaba** el producto el día que lo vendiste. Si después sube el costo, las ventas viejas no cambian.'],
            ],
        ],
        [
            'id' => 'administrar', 'titulo' => 'Para el dueño: usuarios, cajas y ajustes', 'icono' => 'gear', 'para' => 'Dueño',
            'intro' => 'Aquí decides quién puede hacer qué y cómo funciona tu tienda.',
            'bloques' => [
                ['p' => '**Usuarios.** Da de alta a cada persona con su usuario, contraseña y rol. En la misma pantalla ves qué puede hacer cada rol. Si alguien deja de trabajar contigo, **desactívalo** (no se borra su historial). Siempre debe haber al menos un administrador.'],
                ['p' => '**Cajas.** Cada caja tiene una letra de serie que abre los folios de sus tickets (por ejemplo A-000123), el ancho del papel y si el ticket se imprime solo.'],
                ['p' => '**Turnos y cortes.** Ahí ves los turnos de todos los cajeros, si cuadraron, y puedes cerrar un turno que alguien olvidó cerrar.'],
                ['p' => '**Imprimir sin que pregunte.** El navegador siempre pregunta antes de imprimir. Pídele a la persona que instala el sistema que configure la computadora de la caja para que imprima directo (hay instrucciones en el manual técnico).'],
            ],
        ],
        [
            'id' => 'problemas', 'titulo' => 'Si algo no funciona', 'icono' => 'lifebuoy', 'para' => 'Todos',
            'intro' => 'Lo que más pasa y cómo resolverlo.',
            'bloques' => [
                ['tabla' => [
                    ['Dice «Abre tu turno de caja antes de vender»', 'Entra a **Mi caja** y abre tu turno.'],
                    ['Dice «Esa clave ya está en uso»', 'Otro producto ya tiene ese código. Búscalo en **Artículos**.'],
                    ['No puedo cancelar una venta', 'Ya se cerró el turno de esa venta o ya tiene una devolución. Haz una devolución.'],
                    ['No veo una opción del menú', 'Tu rol no tiene ese permiso. Pídeselo al dueño.'],
                    ['Olvidé mi contraseña', 'El dueño te la cambia en **Usuarios**.'],
                    ['Se fue el internet', 'No se puede cobrar. Cuando regrese, tu lista sigue en pantalla.'],
                    ['La caja no cuadra', 'Pide al encargado que revise tu corte en **Turnos y cortes**: ahí están las entradas, los retiros y las devoluciones.'],
                    ['El ticket sale cortado', 'En **Cajas** toca la impresora para imprimir una prueba y revisa el ancho del papel. En la ventana de impresión pon los márgenes en «Ninguno».'],
                    ['Escaneo y no pasa nada', 'Toca el cuadro de «Escanea o escribe el código» para que el cursor esté ahí.'],
                    ['La cámara no abre', 'La dirección del sistema debe empezar con **https://**. Cuando el navegador pregunte, permite el uso de la cámara; si la negaste antes, actívala en los permisos del sitio.'],
                ]],
            ],
        ],
        [
            'id' => 'glosario', 'titulo' => 'Palabras que verás', 'icono' => 'book-open-text', 'para' => 'Todos',
            'intro' => 'Cada palabra, explicada fácil.',
            'bloques' => [
                ['tabla' => [
                    ['Turno', 'Tu jornada en la caja, desde que la abres hasta que la cierras.'],
                    ['Fondo inicial', 'El dinero con el que empiezas el día, para dar cambio.'],
                    ['Arqueo', 'Contar el dinero del cajón.'],
                    ['Corte X', 'Una foto de cómo va el día. No cierra nada.'],
                    ['Corte Z', 'La cuenta final. Cierra el turno.'],
                    ['Existencia', 'Cuántas piezas hay de un producto.'],
                    ['Mínimo', 'La cantidad que no quieres que baje de ahí. Si llega a ese número, el sistema te avisa que hay que reabastecer.'],
                    ['Cobertura', 'Cuántos días te dura lo que tienes al ritmo al que lo vendes.'],
                    ['Kardex', 'El cuaderno de un producto: todo lo que le ha pasado.'],
                    ['Merma', 'Producto que se echó a perder, se rompió o se perdió.'],
                    ['Costo', 'Lo que te cuesta a ti el producto.'],
                    ['Margen', 'Cuánto ganas sobre el costo, en porcentaje.'],
                    ['Utilidad', 'Lo que te queda en los productos antes de pagar renta, luz y sueldos.'],
                    ['Granel', 'Producto que se vende por peso o medida.'],
                    ['Ticket', 'El papelito que recibe el cliente.'],
                    ['Abono', 'Dinero que un cliente da para pagar lo que debe.'],
                    ['Folio', 'El número de cada ticket (por ejemplo A-000123).'],
                ]],
            ],
        ],
    ];

    // Texto plano de cada sección: sirve para el buscador de la guía.
    $texto = function (array $s): string {
        $partes = [$s['titulo'], $s['intro'], $s['para']];
        foreach ($s['bloques'] as $b) {
            foreach ($b as $tipo => $valor) {
                if (in_array($tipo, ['p', 'consejo', 'cuidado'], true)) { $partes[] = $valor; }
                elseif (in_array($tipo, ['pasos', 'lista'], true)) { $partes = array_merge($partes, $valor); }
                elseif ($tipo === 'tabla') { foreach ($valor as $fila) { $partes[] = implode(' ', $fila); } }
            }
        }
        // Sin acentos y en minúsculas: el buscador compara contra este texto igual de simplificado.
        return mb_strtolower(\Illuminate\Support\Str::ascii(strip_tags(str_replace('**', '', implode(' ', $partes)))));
    };

    // **negritas** y *cursivas* sencillas, con el texto escapado.
    $fmt = function (string $t): \Illuminate\Support\HtmlString {
        $t = e($t);
        $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong class="font-semibold text-anil-900">$1</strong>', $t);
        $t = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '<em>$1</em>', $t);

        return new \Illuminate\Support\HtmlString($t);
    };
@endphp

<x-app-layout>
    <x-slot name="titulo">Guía de uso</x-slot>

    <div x-data="{ q: '', get qn() { return this.q.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); }, vistas: 1, filtrar() { let n = 0; this.$refs.secciones.querySelectorAll('[data-seccion]').forEach((e) => { const ok = this.qn === '' || e.dataset.texto.includes(this.qn); e.style.display = ok ? '' : 'none'; if (ok) n++; }); this.vistas = n; } }" x-effect="filtrar()" class="grid items-start gap-8 lg:grid-cols-[15rem_minmax(0,1fr)]">

        {{-- Índice --}}
        <aside class="lg:sticky lg:top-8">
            <label for="buscar-guia" class="rotulo mb-1.5 block">Buscar en la guía</label>
            <div class="relative">
                <x-phosphor-magnifying-glass-bold class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-anil-400" />
                <input id="buscar-guia" x-model="q" type="search" placeholder="Por ejemplo: corte, devolución…" autocomplete="off"
                       class="block w-full rounded-lg border border-anil-200 bg-white py-2.5 pl-10 pr-3 text-base placeholder:text-anil-300 focus:border-anil-500 focus:ring-4 focus:ring-anil-500/15 focus:outline-none">
            </div>

            <nav class="mt-5 hidden lg:block" aria-label="Índice de la guía">
                <p class="rotulo mb-2">Índice</p>
                <ol class="space-y-0.5 text-sm">
                    @foreach ($secciones as $s)
                        <li><a href="#{{ $s['id'] }}" class="flex min-h-9 items-center gap-2 rounded-md px-2 py-1.5 text-anil-800 hover:bg-white hover:text-anil-900">
                            {{ svg('phosphor-'.$s['icono'].'-bold', 'size-4 shrink-0 text-anil-500') }} <span class="truncate">{{ $s['titulo'] }}</span></a></li>
                    @endforeach
                </ol>
            </nav>
        </aside>

        <div>
            <p class="mb-6 max-w-2xl text-lg text-anil-800">
                Aquí te explicamos, paso a paso, cómo usar el sistema de tu tienda. No necesitas saber de computación.
                Lee solo la parte que te toca, o escribe en el buscador lo que quieres hacer.
            </p>

            <div x-ref="secciones" class="space-y-6">
                @foreach ($secciones as $s)
                    <section id="{{ $s['id'] }}" data-seccion data-texto="{{ $texto($s) }}"                             class="scroll-mt-6 rounded-2xl border border-anil-100 bg-white p-5 shadow-[0_2px_0_0_var(--color-anil-100)] sm:p-7">
                        <header class="mb-4 flex items-start gap-4">
                            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-anil-900 text-rosa-400">{{ svg('phosphor-'.$s['icono'].'-fill', 'size-6') }}</span>
                            <div class="min-w-0">
                                <h2 class="text-xl font-extrabold leading-tight text-anil-900 sm:text-2xl">{{ $s['titulo'] }}</h2>
                                <p class="mt-1"><x-badge tono="rosa">Para: {{ $s['para'] }}</x-badge></p>
                            </div>
                        </header>

                        <p class="mb-5 text-[1.0625rem] leading-relaxed text-anil-800">{{ $fmt($s['intro']) }}</p>

                        <div class="space-y-5">
                            @foreach ($s['bloques'] as $b)
                                @if (isset($b['p']))
                                    <p class="leading-relaxed text-anil-900">{{ $fmt($b['p']) }}</p>

                                @elseif (isset($b['pasos']))
                                    <ol class="space-y-3">
                                        @foreach ($b['pasos'] as $paso)
                                            <li class="flex items-start gap-3">
                                                <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-rosa-500 font-display text-sm font-extrabold text-white">{{ $loop->iteration }}</span>
                                                <span class="leading-relaxed text-anil-900">{{ $fmt($paso) }}</span>
                                            </li>
                                        @endforeach
                                    </ol>

                                @elseif (isset($b['lista']))
                                    <ul class="space-y-2">
                                        @foreach ($b['lista'] as $item)
                                            <li class="flex items-start gap-2.5 leading-relaxed text-anil-900"><x-phosphor-caret-right-bold class="mt-1.5 size-3.5 shrink-0 text-rosa-500" /> <span>{{ $fmt($item) }}</span></li>
                                        @endforeach
                                    </ul>

                                @elseif (isset($b['consejo']))
                                    <div class="flex items-start gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-emerald-950 ring-1 ring-emerald-200">
                                        <x-phosphor-lightbulb-bold class="mt-0.5 size-5 shrink-0 text-emerald-700" />
                                        <p class="leading-relaxed"><strong class="font-extrabold">Consejo. </strong>{{ $fmt($b['consejo']) }}</p>
                                    </div>

                                @elseif (isset($b['cuidado']))
                                    <div class="flex items-start gap-3 rounded-xl bg-etiqueta/30 px-4 py-3 text-amber-950 ring-1 ring-etiqueta/70">
                                        <x-phosphor-warning-bold class="mt-0.5 size-5 shrink-0 text-amber-800" />
                                        <p class="leading-relaxed"><strong class="font-extrabold">Ojo. </strong>{{ $fmt($b['cuidado']) }}</p>
                                    </div>

                                @elseif (isset($b['tabla']))
                                    <dl class="divide-y divide-anil-50 overflow-hidden rounded-xl border border-anil-100">
                                        @foreach ($b['tabla'] as [$izq, $der])
                                            <div class="grid gap-x-4 gap-y-1 px-4 py-3 sm:grid-cols-[13rem_minmax(0,1fr)]">
                                                <dt class="font-semibold text-anil-900">{{ $fmt($izq) }}</dt>
                                                <dd class="leading-relaxed text-anil-800">{{ $fmt($der) }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>

                                @elseif (isset($b['enlaces']))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($b['enlaces'] as [$etiqueta, $ruta, $permiso])
                                            @if ($permiso === null || auth()->user()->can($permiso))
                                                <a href="{{ route($ruta) }}" wire:navigate class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-anil-200 bg-white px-3.5 text-sm font-semibold text-anil-800 hover:bg-anil-50">
                                                    {{ $etiqueta }} <x-phosphor-arrow-right-bold class="size-3.5" />
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div x-show="qn !== '' && vistas === 0" x-cloak class="mt-4">
                <x-empty icono="magnifying-glass" titulo="No encontramos nada con esa palabra" texto="Prueba con otra palabra más sencilla, como «cobrar», «cerrar», «cliente» o «precio»." />
            </div>

            <p class="mt-8 text-sm text-anil-600">¿Sigues con dudas? Pregúntale al dueño o al encargado de la tienda.</p>
        </div>
    </div>
</x-app-layout>
