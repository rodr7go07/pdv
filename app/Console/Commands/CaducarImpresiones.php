<?php

namespace App\Console\Commands;

use App\Services\ColaImpresion;
use Illuminate\Console\Command;

class CaducarImpresiones extends Command
{
    protected $signature = 'impresiones:caducar';

    protected $description = 'Caduca los pedidos de impresión que nadie atendió y reintenta los que se quedaron a medias';

    public function handle(ColaImpresion $cola): int
    {
        $this->info('Pedidos actualizados: '.$cola->caducar());

        return self::SUCCESS;
    }
}
