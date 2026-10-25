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
        $dias = max(1, (int) $this->option('dias'));
        $borrados = Escaneo::query()->where('created_at', '<', now()->subDays($dias))->delete();

        $this->info("Escaneos borrados (más de {$dias} días): {$borrados}.");

        return self::SUCCESS;
    }
}
