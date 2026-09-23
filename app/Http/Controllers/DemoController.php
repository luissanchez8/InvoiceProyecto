<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Demo\AjustesDemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrada a la demo pública sin credenciales: el botón de la portada inicia
 * sesión con el usuario de la demo. Fuera del modo demo no existe.
 */
class DemoController extends Controller
{
    public function entrar(Request $request)
    {
        abort_unless(config('app.env') === 'demo', 404);

        $usuario = User::where('email', AjustesDemo::EMAIL_USUARIO)->first();
        abort_unless($usuario, 503, 'La demo se está preparando. Inténtalo en unos minutos.');

        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        return redirect('/admin/dashboard');
    }
}
