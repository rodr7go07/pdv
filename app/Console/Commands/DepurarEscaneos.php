<?php

namespace App\Console\Commands;

use App\Models\Escaneo;
use Illuminate\Console\Command;

class DepurarEscaneos extends Command
{
    protected $signature = 'escaneos:depurar {--dias=180 : Se borran los escaneos más viejos que estos días}';

    protected $description = 'Borra de la bitácora los códigos escaneados hace más de N días';

    public function handle(): int
    {
        $opcion = (string) $this->option('dias');

        if (! ctype_digit($opcion) || (int) $opcion < 1) {
            $this->error('--dias debe ser un número entero de 1 o más.');

            return self::FAILURE;
        }

        $dias = (int) $opcion;
        $limite = now()->subDays($dias);
        $borrados = 0;

        // Por lotes, para no bloquear la tabla si creció mucho.
        do {
            $lote = Escaneo::query()->where('created_at', '<', $limite)->limit(5000)->delete();
            $borrados += $lote;
        } while ($lote > 0);

        $this->info("Escaneos borrados (más de {$dias} días): {$borrados}.");

        return self::SUCCESS;
    }
}
