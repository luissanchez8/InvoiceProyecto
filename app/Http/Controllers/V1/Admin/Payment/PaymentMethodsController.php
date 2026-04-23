<?php

namespace App\Http\Controllers\V1\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodsController extends Controller
{
    /**
     * Onfactu: solo el rol "asistencia" puede crear, editar o borrar modos de
     * pago. Centraliza la comprobación en un método privado usado desde los
     * endpoints de escritura. El listado (index) y el detalle (show) siguen
     * abiertos a los demás roles — sólo necesitan leerlos.
     */
    private function ensureAsistencia(Request $request)
    {
        $user = $request->user();
        $role = $user ? strtolower(trim((string) $user->role)) : '';
        if ($role !== 'asistencia') {
            abort(403, 'Solo Asistencia puede gestionar los modos de pago.');
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $paymentMethods = PaymentMethod::applyFilters($request->all())
            ->where('type', PaymentMethod::TYPE_GENERAL)
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return PaymentMethodResource::collection($paymentMethods);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PaymentMethodRequest $request)
    {
        $this->ensureAsistencia($request);
        $this->authorize('create', PaymentMethod::class);

        $paymentMethod = PaymentMethod::createPaymentMethod($request);

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(PaymentMethod $paymentMethod)
    {
        $this->authorize('view', $paymentMethod);

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        $this->ensureAsistencia($request);
        $this->authorize('update', $paymentMethod);

        $paymentMethod->update($request->getPaymentMethodPayload());

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, PaymentMethod $paymentMethod)
    {
        $this->ensureAsistencia($request);
        $this->authorize('delete', $paymentMethod);

        if ($paymentMethod->payments()->exists()) {
            return respondJson('payments_attached', 'Payments Attached.');
        }

        if ($paymentMethod->expenses()->exists()) {
            return respondJson('expenses_attached', 'Expenses Attached.');
        }

        $paymentMethod->delete();

        return response()->json([
            'success' => 'Payment method deleted successfully',
        ]);
    }
}
