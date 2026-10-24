<?php

namespace Database\Seeders;

use App\Models\Caja;
use Illuminate\Database\Seeder;

/** Una caja inicial para poder vender desde el primer día. Idempotente. */
class CajasSeeder extends Seeder
{
    public function run(): void
    {
        Caja::query()->firstOrCreate(['serie' => 'A'], ['nombre' => 'Caja 1']);
    }
}
