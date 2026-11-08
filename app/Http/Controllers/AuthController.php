<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use Illuminate\Http\Request;

/**
 * Inicio de sesión de la versión de demostración (mockup 00): se elige un usuario
 * y se entra con su rol. Versión de diseño: el usuario se guarda en la sesión,
 * sin base de datos. Para producción debe reemplazarse por correo + contraseña.
 */
class AuthController extends Controller
{
    public function show(Request $request)
    {
        if ($user = DemoData::user($request->session()->get('demo_user'))) {
            return redirect($user->homeRoute());
        }
        $d = DemoData::get();
        $active = $d->projects->where('status', 'activo');

        return view('auth.login', [
            // Un usuario por rol (un solo investigador)
            'users' => $d->users->unique('role')->values(),
            'activeCount' => $active->count(),
            'budget' => $active->sum('value'),
            'liquidated' => $d->projects->where('status', 'liquidado')->count(),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['user_id' => ['required', 'integer']], ['user_id.required' => 'Elige un usuario para ingresar.']);
        $user = DemoData::user((int) $data['user_id']) ?? abort(422, 'Usuario no válido.');

        $request->session()->regenerate();
        $request->session()->put('demo_user', $user->id);

        return redirect($user->homeRoute());
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
