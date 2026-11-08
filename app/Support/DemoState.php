<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Estado de la demo (aprobaciones, CDP, pagos, usuarios creados…).
 *
 * Se guarda en la caché de la aplicación, no en la sesión, para que lo que hace
 * un rol lo vea el siguiente al cambiar de usuario. «Restablecer datos demo»
 * (Administrador) lo borra todo.
 */
class DemoState
{
    private const KEY = 'sgrup.demo-state';

    private static function all(): array
    {
        return Cache::get(self::KEY, []);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(self::all(), $key, $default);
    }

    public static function put(string $key, mixed $value): void
    {
        $all = self::all();
        Arr::set($all, $key, $value);
        Cache::forever(self::KEY, $all);
    }

    public static function push(string $key, mixed $value): void
    {
        $list = self::get($key, []);
        $list[] = $value;
        self::put($key, $list);
    }

    public static function forget(array|string $keys): void
    {
        $all = self::all();
        Arr::forget($all, (array) $keys);
        Cache::forever(self::KEY, $all);
    }

    public static function reset(): void
    {
        Cache::forget(self::KEY);
    }
}
