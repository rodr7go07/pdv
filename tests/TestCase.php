<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase hace «migrate:fresh»: borra todas las tablas de la conexión activa.
     * Antes de que eso ocurra se comprueba que sea la base de pruebas (SQLite en memoria o archivo)
     * y nunca la MySQL real, por ejemplo si alguien dejó la configuración en caché con «config:cache».
     */
    protected function setUpTraits()
    {
        $conexion = config('database.default');
        $driver = config("database.connections.{$conexion}.driver");

        if ($this->app->configurationIsCached() || $driver !== 'sqlite') {
            throw new \RuntimeException('Las pruebas solo corren con SQLite. Ejecuta «php artisan config:clear» y revisa que phpunit.xml use DB_CONNECTION=sqlite.');
        }

        return parent::setUpTraits();
    }
}
