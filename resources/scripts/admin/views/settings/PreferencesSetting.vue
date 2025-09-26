<template>
  <form class="relative" @submit.prevent="updatePreferencesData">
    <BaseSettingCard
      :title="$t('settings.menu_title.preferences')"
      :description="$t('settings.preferences.general_settings')"
    >
      <BaseInputGrid class="mt-5">

        <!-- Moneda (solo asistencia) -->
        <BaseInputGroup
          v-if="isAsistencia"
          :content-loading="isFetchingInitialData"
          :label="$t('settings.preferences.currency')"
          :help-text="$t('settings.preferences.company_currency_unchangeable')"
          :error="v$.currency.$error && v$.currency.$errors[0].$message"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.currency"
            :content-loading="isFetchingInitialData"
            :options="globalStore.currencies"
            label="name"
            value-prop="id"
            :searchable="true"
            track-by="name"
            :invalid="v$.currency.$error"
            disabled
            class="w-full"
          />
        </BaseInputGroup>

        <!-- Idioma por defecto (solo asistencia) -->
        <BaseInputGroup
          v-if="isAsistencia"
          :label="$t('settings.preferences.default_language')"
          :content-loading="isFetchingInitialData"
          :error="v$.language.$error && v$.language.$errors[0].$message"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.language"
            :content-loading="isFetchingInitialData"
            :options="globalStore.config.languages"
            label="name"
            value-prop="code"
            class="w-full"
            track-by="name"
            :searchable="true"
            :invalid="v$.language.$error"
          />
        </BaseInputGroup>

        <!-- Zona horaria (solo asistencia) -->
        <BaseInputGroup
          v-if="isAsistencia"
          :label="$t('settings.preferences.time_zone')"
          :content-loading="isFetchingInitialData"
          :error="v$.time_zone.$error && v$.time_zone.$errors[0].$message"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.time_zone"
            :content-loading="isFetchingInitialData"
            :options="globalStore.timeZones"
            label="key"
            value-prop="value"
            track-by="key"
            :searchable="true"
            :invalid="v$.time_zone.$error"
          />
        </BaseInputGroup>

        <!-- Formato de fecha (SIEMPRE visible) -->
        <BaseInputGroup
          :label="$t('settings.preferences.date_format')"
          :content-loading="isFetchingInitialData"
          :error="v$.carbon_date_format.$error && v$.carbon_date_format.$errors[0].$message"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.carbon_date_format"
            :content-loading="isFetchingInitialData"
            :options="globalStore.dateFormats"
            label="display_date"
            value-prop="carbon_format_value"
            track-by="display_date"
            :searchable="true"
            :invalid="v$.carbon_date_format.$error"
            class="w-full"
          />
        </BaseInputGroup>

        <!-- Año fiscal (solo asistencia) -->
        <BaseInputGroup
          v-if="isAsistencia"
          :content-loading="isFetchingInitialData"
          :error="v$.fiscal_year.$error && v$.fiscal_year.$errors[0].$message"
          :label="$t('settings.preferences.fiscal_year')"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.fiscal_year"
            :content-loading="isFetchingInitialData"
            :options="fiscalYearsList"
            label="key"
            value-prop="value"
            :invalid="v$.fiscal_year.$error"
            track-by="key"
            :searchable="true"
            class="w-full"
          />
        </BaseInputGroup>

        <!-- Formato de hora (SIEMPRE visible) -->
        <BaseInputGroup
          :label="$t('settings.preferences.time_format')"
          :content-loading="isFetchingInitialData"
          :error="v$.carbon_time_format.$error && v$.carbon_time_format.$errors[0].$message"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.carbon_time_format"
            :content-loading="isFetchingInitialData"
            :options="globalStore.timeFormats"
            label="display_time"
            value-prop="carbon_format_value"
            track-by="display_time"
            :searchable="true"
            :invalid="v$.carbon_time_format.$error"
            class="w-full"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <!-- Usar hora en facturas (SIEMPRE visible) -->
      <BaseSwitchSection
        v-model="invoiceUseTimeField"
        :title="$t('settings.preferences.invoice_use_time')"
        :description="$t('settings.preferences.invoice_use_time_description')"
      />

      <BaseButton
        :content-loading="isFetchingInitialData"
        :disabled="isSaving"
        :loading="isSaving"
        type="submit"
        class="mt-6"
      >
        <template #left="slotProps">
          <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('settings.company_info.save') }}
      </BaseButton>

      <BaseDivider class="mt-6 mb-2" />

      <!-- Expirar enlaces públicos (solo asistencia) -->
      <form @submit.prevent="submitData">
        <BaseSwitchSection
          v-if="isAsistencia"
          v-model="expirePdfField"
          :title="$t('settings.preferences.expire_public_links')"
          :description="$t('settings.preferences.expire_setting_description')"
        />

        <BaseInputGroup
          v-if="isAsistencia && expirePdfField"
          :content-loading="isFetchingInitialData"
          :label="$t('settings.preferences.expire_public_links')"
          class="mt-2 mb-4"
        >
          <BaseInput
            v-model="settingsForm.link_expiry_days"
            :disabled="settingsForm.automatically_expire_public_links === 'NO'"
            :content-loading="isFetchingInitialData"
            type="number"
          />
        </BaseInputGroup>

        <BaseButton
          v-if="isAsistencia"
          :content-loading="isFetchingInitialData"
          :disabled="isDataSaving"
          :loading="isDataSaving"
          type="submit"
          class="mt-6"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </form>

      <BaseDivider class="mt-6 mb-2" />

      <!-- Descuento por artículo (solo asistencia) -->
      <BaseSwitchSection
        v-if="isAsistencia"
        v-model="discountPerItemField"
        :title="$t('settings.preferences.discount_per_item')"
        :description="$t('settings.preferences.discount_setting_description')"
      />
    </BaseSettingCard>
  </form>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const userStore = useUserStore()
const { t } = useI18n()

const isAsistencia = computed(() => userStore.currentUser?.role === 'asistencia')

let isSaving = ref(false)
let isDataSaving = ref(false)
let isFetchingInitialData = ref(false)

const settingsForm = reactive({ ...companyStore.selectedCompanySettings })

const fiscalYearsList = computed(() =>
  globalStore.config.fiscal_years.map((i) => ({ ...i, key: t(i.key) }))
)

watch(() => settingsForm.carbon_date_format, (val) => {
  if (!val) return
  const o = globalStore.dateFormats.find(d => d.carbon_format_value === val)
  if (o) settingsForm.moment_date_format = o.moment_format_value
})

watch(() => settingsForm.carbon_time_format, (val) => {
  if (!val) return
  const o = globalStore.timeFormats.find(d => d.carbon_format_value === val)
  if (o) settingsForm.moment_time_format = o.moment_format_value
})

const invoiceUseTimeField = computed({
  get: () => settingsForm.invoice_use_time === 'YES',
  set: (on) => { settingsForm.invoice_use_time = on ? 'YES' : 'NO' }
})

const discountPerItemField = computed({
  get: () => settingsForm.discount_per_item === 'YES',
  set: async (on) => {
    const value = on ? 'YES' : 'NO'
    settingsForm.discount_per_item = value
    if (isAsistencia.value) {
      await companyStore.updateCompanySettings({
        data: { settings: { discount_per_item: value } },
        message: 'general.setting_updated'
      })
    }
  }
})

const expirePdfField = computed({
  get: () => settingsForm.automatically_expire_public_links === 'YES',
  set: (on) => {
    const value = on ? 'YES' : 'NO'
    settingsForm.automatically_expire_public_links = value
  }
})

const rules = computed(() => ({
  // Sólo requerimos estos si el campo está visible (asistencia)
  currency: isAsistencia.value ? {
    required: helpers.withMessage(t('validation.required'), required),
  } : {},
  language: isAsistencia.value ? {
    required: helpers.withMessage(t('validation.required'), required),
  } : {},
  time_zone: isAsistencia.value ? {
    required: helpers.withMessage(t('validation.required'), required),
  } : {},
  fiscal_year: isAsistencia.value ? {
    required: helpers.withMessage(t('validation.required'), required),
  } : {},
  // Siempre visibles
  carbon_date_format: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  moment_date_format: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  carbon_time_format: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  moment_time_format: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  invoice_use_time: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, computed(() => settingsForm))

setInitialData()
async function setInitialData () {
  isFetchingInitialData.value = true
  await Promise.all([
    globalStore.fetchCurrencies(),
    globalStore.fetchDateFormats(),
    globalStore.fetchTimeFormats(),
    globalStore.fetchTimeZones(),
  ])
  isFetchingInitialData.value = false
}

async function updatePreferencesData () {
  v$.value.$touch()
  if (v$.value.$invalid) return

  // Payload base
  const payload = { settings: { ...settingsForm } }

  // Si NO es asistencia, limpiamos campos restringidos
  if (!isAsistencia.value) {
    delete payload.settings.currency
    delete payload.settings.language
    delete payload.settings.time_zone
    delete payload.settings.fiscal_year
    delete payload.settings.automatically_expire_public_links
    delete payload.settings.link_expiry_days
    delete payload.settings.discount_per_item
  }

  // No enviamos link_expiry_days en este submit (igual que antes)
  delete payload.settings.link_expiry_days

  isSaving.value = true
  await companyStore.updateCompanySettings({
    data: payload,
    message: 'settings.preferences.updated_message',
  })
  isSaving.value = false
}

async function submitData () {
  if (!isAsistencia.value) return
  isDataSaving.value = true
  await companyStore.updateCompanySettings({
    data: {
      settings: {
        link_expiry_days: settingsForm.link_expiry_days,
        automatically_expire_public_links: settingsForm.automatically_expire_public_links,
      },
    },
    message: 'settings.preferences.updated_message',
  })
  isDataSaving.value = false
}
</script>
