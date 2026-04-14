#!/bin/bash
set -euo pipefail
cd /tmp/InvoiceProyecto
echo "=== Aplicando panel de asistencia ==="

# 1. CONTROLADOR
cat > app/Http/Controllers/V1/Admin/Settings/AppConfigController.php << 'PHPEOF'
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
PHPEOF
echo "1/6 OK"

# 2. RUTAS API
if ! grep -q "app-config" routes/api.php; then
cat >> routes/api.php << 'ROUTEEOF'

// --- Panel Asistencia: app_config ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v1/app-config', [\App\Http\Controllers\V1\Admin\Settings\AppConfigController::class, 'index']);
    Route::put('/v1/app-config', [\App\Http\Controllers\V1\Admin\Settings\AppConfigController::class, 'update']);
});
ROUTEEOF
echo "2/6 OK"
else
echo "2/6 SKIP"
fi

# 3. VISTA VUE
cat > resources/scripts/admin/views/settings/AppConfigSetting.vue << 'VUEEOF'
<template>
  <div class="relative">
    <BaseSettingCard
      :title="'Configuración de la instancia'"
      :description="'Activa o desactiva las opciones del menú para este usuario.'"
    >
      <div v-if="isLoading" class="flex justify-center py-10">
        <BaseContentPlaceholders>
          <BaseContentPlaceholdersText :lines="6" />
        </BaseContentPlaceholders>
      </div>
      <div v-else>
        <h3 class="text-lg font-semibold text-gray-700 mb-4 mt-2">Opciones del menú</h3>
        <div class="space-y-4">
          <div v-for="config in toggleConfigs" :key="config.key"
               class="flex items-center justify-between py-3 px-4 bg-gray-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ labelFor(config.key) }}</p>
              <p class="text-xs text-gray-500">{{ config.key }}</p>
            </div>
            <BaseSwitch v-model="config.value" :true-value="'1'" :false-value="'0'" />
          </div>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-4 mt-8">Otros ajustes</h3>
        <div class="space-y-4">
          <div v-for="config in otherConfigs" :key="config.key"
               class="py-3 px-4 bg-gray-50 rounded-lg">
            <label class="block text-sm font-medium text-gray-800 mb-1">{{ labelFor(config.key) }}</label>
            <p class="text-xs text-gray-500 mb-2">{{ config.key }}</p>
            <BaseInput v-model="config.value" type="text" />
          </div>
        </div>
        <div class="mt-8 flex justify-end">
          <BaseButton :loading="isSaving" variant="primary" @click="saveConfig">
            <template #left="slotProps">
              <BaseIcon name="CheckIcon" :class="slotProps.class" />
            </template>
            Guardar cambios
          </BaseButton>
        </div>
      </div>
    </BaseSettingCard>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'
const notificationStore = useNotificationStore()
const configs = ref([])
const isLoading = ref(true)
const isSaving = ref(false)
const labels = {
  'OPCION_MENU_PRESUPUESTOS': 'Presupuestos',
  'OPCION_MENU_FACTURAS': 'Facturas',
  'OPCION_MENU_PROFORMAS': 'Facturas Proforma',
  'OPCION_MENU_ALBARANES': 'Albaranes',
  'OPCION_MENU_FRA_RECURRENTE': 'Facturas recurrentes',
  'OPCION_MENU_PAGOS': 'Pagos',
  'OPCION_MENU_GASTOS': 'Gastos',
  'NOMBRE_EMPRESA': 'Nombre de la empresa',
  'URL_LOGOTIPO': 'URL del logotipo',
}
function labelFor(key) { return labels[key] || key }
const toggleConfigs = computed(() => configs.value.filter(c => c.key.startsWith('OPCION_MENU_')))
const otherConfigs = computed(() => configs.value.filter(c => !c.key.startsWith('OPCION_MENU_')))
async function fetchConfig() {
  isLoading.value = true
  try {
    const response = await axios.get('/api/v1/app-config')
    configs.value = response.data.data
  } catch (err) {
    notificationStore.showNotification({ type: 'error', message: 'Error al cargar la configuración' })
  } finally { isLoading.value = false }
}
async function saveConfig() {
  isSaving.value = true
  try {
    await axios.put('/api/v1/app-config', {
      configs: configs.value.map(c => ({ key: c.key, value: String(c.value) })),
    })
    notificationStore.showNotification({ type: 'success', message: 'Configuración guardada correctamente' })
  } catch (err) {
    notificationStore.showNotification({ type: 'error', message: 'Error al guardar la configuración' })
  } finally { isSaving.value = false }
}
onMounted(() => { fetchConfig() })
</script>
VUEEOF
echo "3/6 OK"

# 4. RUTA VUE
if ! grep -q "AppConfigSetting" resources/scripts/admin/admin-router.js; then
    sed -i "/import('@\/scripts\/admin\/views\/settings\/PDFGenerationSetting.vue')/a\\
\\
const AppConfigSetting = () =>\\
  import('@/scripts/admin/views/settings/AppConfigSetting.vue')" resources/scripts/admin/admin-router.js

    sed -i "/name: 'pdf.generation'/,/component: PDFGenerationSettings/a\\
          },\\
          {\\
            path: 'app-config',\\
            name: 'app.config',\\
            component: AppConfigSetting," resources/scripts/admin/admin-router.js
    echo "4/6 OK"
else
    echo "4/6 SKIP"
fi

# 5. MENÚ + FILTRO ASISTENCIA

# 5a. Propagar asistencia_only en generateMenu (AppServiceProvider)
if ! grep -q "asistencia_only" app/Providers/AppServiceProvider.php; then
    sed -i "s/->data('group', \$data\['group'\]);/->data('group', \$data['group'])\\
            ->data('asistencia_only', \$data['asistencia_only'] ?? false);/" app/Providers/AppServiceProvider.php
    echo "5a/6 OK"
fi

# 5b. Filtrar asistencia_only en checkAccess (User.php)
if ! grep -q "asistencia_only" app/Models/User.php; then
    sed -i "/public function checkAccess(\$data)/a\\
    {\\
        if (!empty(\$data->data['asistencia_only']) \&\& \$this->role !== 'asistencia') {\\
            return false;\\
        }" app/Models/User.php
    # Quitar el { duplicado que queda después del function
    # La función original tiene { en la siguiente línea, hay que quitarlo
    echo "5b/6 OK - REVISAR User.php manualmente por posible llave duplicada"
fi

# 5c. Añadir item al setting_menu
if ! grep -q "app-config" config/invoiceshelf.php; then
    sed -i "/'setting_menu' => \[/a\\
        [\\
            'title' => 'Configuración App',\\
            'group' => '',\\
            'name' => 'App Config',\\
            'link' => '/admin/settings/app-config',\\
            'icon' => 'AdjustmentsHorizontalIcon',\\
            'owner_only' => false,\\
            'ability' => '',\\
            'model' => '',\\
            'asistencia_only' => true,\\
        ]," config/invoiceshelf.php
    echo "5c/6 OK"
fi

echo ""
echo "=== COMPLETADO ==="
echo "IMPORTANTE: Revisar app/Models/User.php - la función checkAccess"
echo "puede tener llaves duplicadas. Verificar con:"
echo "  grep -A15 'function checkAccess' app/Models/User.php"
echo ""
echo "Después:"
echo "  git add . && git commit -m 'Panel de configuración para usuario Asistencia' && git push origin main"
echo "  cd ~/onfactu-produccion-20251125/onfactu && docker build --no-cache --build-arg CACHEBUST=\$(date +%s) -t invoiceshelf-app . 2>&1 | tail -5"
