{{--
    Modo oscuro: decide antes de pintar (sin parpadeo blanco) según lo guardado en este equipo o el tema del sistema.
    Va en el <head> (primera carga) y al inicio del <body> (cada cambio de pantalla con wire:navigate: Livewire reemplaza los
    atributos de <html> y vuelve a ejecutar los scripts del <body>, no los del <head>). El interruptor está en resources/js/tema.js.
--}}
<script>
    try {
        var tema = localStorage.getItem('tema');
        if (tema ? tema === 'oscuro' : window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
            var barra = document.querySelector('meta[name="theme-color"]');
            if (barra) barra.setAttribute('content', '#0a0c1f');
        }
    } catch (e) {}
</script>
