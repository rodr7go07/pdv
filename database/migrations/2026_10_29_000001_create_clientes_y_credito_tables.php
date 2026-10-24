<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 120)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->unsignedTinyInteger('lista_precios')->default(1); // 1 | 2 | 3
            $table->decimal('limite_credito', 12, 2)->default(0); // 0 = sin crédito
            $table->decimal('saldo', 12, 2)->default(0); // lo que el cliente debe; solo cambia con movimientos
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('nombre');
            $table->index('telefono');
        });

        // Cuenta corriente: cada cargo o abono con el saldo que quedó.
        Schema::create('cliente_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('tipo', 15); // cargo | abono | cancelacion | devolucion
            $table->decimal('monto', 12, 2); // siempre positivo; el tipo define si suma o resta
            $table->decimal('saldo_resultante', 12, 2);
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->string('forma', 15)->nullable(); // forma de pago del abono
            $table->string('referencia', 60)->nullable();
            $table->string('nota', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cliente_id', 'created_at']);
            $table->index('turno_id');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('user_id')->constrained('clientes')->restrictOnDelete();
            $table->index('cliente_id');
        });

        Schema::table('devoluciones', function (Blueprint $table) {
            // Parte de la devolución que se descuenta de la deuda del cliente (no sale efectivo del cajón).
            $table->decimal('monto_credito', 12, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropColumn('monto_credito');
        });
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
        });

        Schema::dropIfExists('cliente_movimientos');
        Schema::dropIfExists('clientes');
    }
};
