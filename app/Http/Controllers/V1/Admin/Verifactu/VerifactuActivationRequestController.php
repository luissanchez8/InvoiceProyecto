<?php

namespace App\Http\Controllers\V1\Admin\Verifactu;

use App\Http\Controllers\Controller;
use App\Mail\VerifactuActivationRequestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Gestiona las solicitudes de activación de VeriFactu iniciadas por usuarios.
 *
 * El usuario normal NO puede activar/desactivar VeriFactu. En su lugar tiene
 * un botón "Solicitar activación" que llama a este endpoint. Aquí:
 *   1. Se registra la solicitud en verifactu_activation_requests.
 *   2. Se envía un email a soporte@onfactu.com con los datos.
 *
 * El panel de Asistencia (AppConfigController) es el único sitio desde el
 * que se puede activar realmente vía la clave OPCION_VERIFACTU.
 */
class VerifactuActivationRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        // Compañía actual del usuario
        $company = $user->companies()->first();
        $companyName = $company ? ($company->name ?? null) : null;

        try {
            // Guardar la solicitud en BD para trazabilidad.
            $requestId = DB::table('verifactu_activation_requests')->insertGetId([
                'user_id'      => $user->id,
                'company_id'   => $company ? $company->id : 0,
                'user_name'    => $user->name,
                'user_email'   => $user->email,
                'company_name' => $companyName,
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Enviar email a soporte. Si falla el envío, la solicitud ya
            // quedó grabada en BD, así que no perdemos la intención del usuario.
            try {
                Mail::to('soporte@onfactu.com')
                    ->send(new VerifactuActivationRequestMail(
                        user: $user,
                        company: $company,
                        requestId: $requestId,
                    ));
            } catch (\Throwable $mailEx) {
                Log::warning('VerifactuActivationRequest: fallo enviando email', [
                    'request_id' => $requestId,
                    'error'      => $mailEx->getMessage(),
                ]);
                // No devolvemos error al usuario: la solicitud está registrada.
            }

            return response()->json([
                'success'    => true,
                'request_id' => $requestId,
                'message'    => 'Solicitud registrada. Te contactaremos pronto.',
            ]);
        } catch (\Throwable $e) {
            Log::error('VerifactuActivationRequest: error en store', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'No se pudo registrar la solicitud. Inténtalo de nuevo o contacta por email.',
            ], 500);
        }
    }
}
