<?php

namespace App\Http\Controllers\V1\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = AppServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /** Onfactu v.1.18.0: cuánto dura "Recordarme" (en minutos): 30 días. */
    public const RECORDAR_MINUTOS = 60 * 24 * 30;

    /**
     * Onfactu v.1.18.0 — Con "Recordarme" marcado, la sesión se recupera sola
     * durante 30 días aunque caduque la normal (cookie de Laravel "remember",
     * de la misma instancia). Sin marcarlo, todo sigue como antes.
     */
    protected function attemptLogin(\Illuminate\Http\Request $request)
    {
        $guard = $this->guard();
        if (method_exists($guard, 'setRememberDuration')) {
            $guard->setRememberDuration(self::RECORDAR_MINUTOS);
        }

        return $guard->attempt($this->credentials($request), $request->boolean('remember'));
    }
}
