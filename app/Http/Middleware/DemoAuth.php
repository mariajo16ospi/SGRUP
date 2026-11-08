<?php

namespace App\Http\Middleware;

use App\Support\DemoData;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Versión de diseño: toma el usuario elegido en el inicio de sesión desde la
 * sesión y lo deja disponible en auth()->user() sin consultar la base de datos.
 */
class DemoAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = DemoData::user($request->session()->get('demo_user'));
        if (! $user) {
            return redirect()->route('login');
        }
        Auth::setUser($user);

        return $next($request);
    }
}
