<?php
namespace App\Http\Controllers\V1\Admin\Settings;
use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;
class AppConfigController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'asistencia') {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        $configs = AppConfig::all()->map(function ($item) {
            return ['id' => $item->id, 'key' => $item->key, 'value' => $item->value];
        });
        return response()->json(['data' => $configs]);
    }
    public function update(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'asistencia') {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*.key' => 'required|string',
            'configs.*.value' => 'required|string',
        ]);
        foreach ($validated['configs'] as $config) {
            AppConfig::updateOrCreate(['key' => $config['key']], ['value' => $config['value']]);
        }
        return response()->json(['success' => true, 'message' => 'Configuracion actualizada']);
    }
}
