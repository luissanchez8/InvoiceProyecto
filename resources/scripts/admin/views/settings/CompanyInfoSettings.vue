<template>
  <form @submit.prevent="updateCompanyData">
    <BaseSettingCard
      :title="$t('settings.company_info.company_info')"
      :description="$t('settings.company_info.section_description')"
    >
      <BaseInputGrid class="mt-5">
        <BaseInputGroup :label="$t('settings.company_info.company_logo')">
          <BaseFileUploader
            v-model="previewLogo"
            base64
            @change="onFileInputChange"
            @remove="onFileInputRemove"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseInputGrid class="mt-5">
        <!-- Nombre de la empresa -->
        <BaseInputGroup
          :label="$t('settings.company_info.company_name')"
          :error="v$.name.$error && v$.name.$errors[0].$message"
          required
        >
          <BaseInput
            v-model="companyForm.name"
            :invalid="v$.name.$error"
            @blur="v$.name.$touch()"
          />
        </BaseInputGroup>

        <!-- Teléfono -->
        <BaseInputGroup :label="$t('settings.company_info.phone')">
          <BaseInput v-model="companyForm.address.phone" />
        </BaseInputGroup>

        <!-- País (solo asistencia) -->
        <BaseInputGroup
          v-if="isAsistencia"
          :label="$t('settings.company_info.country')"
          :error="v$.address.country_id?.$error && v$.address.country_id.$errors[0].$message"
          required
        >
          <BaseMultiselect
            v-model="companyForm.address.country_id"
            label="name"
            :invalid="v$.address.country_id?.$error"
            :options="globalStore.countries"
            value-prop="id"
            :can-deselect="true"
            :can-clear="false"
            searchable
            track-by="name"
          />
        </BaseInputGroup>

        <!-- Provincia (antes Estado) -->
        <BaseInputGroup :label="'Provincia'">
          <BaseInput v-model="companyForm.address.state" name="state" type="text" />
        </BaseInputGroup>

        <!-- Ciudad -->
        <BaseInputGroup :label="$t('settings.company_info.city')">
          <BaseInput v-model="companyForm.address.city" type="text" />
        </BaseInputGroup>

        <!-- Código Postal -->
        <BaseInputGroup :label="$t('settings.company_info.zip')">
          <BaseInput v-model="companyForm.address.zip" />
        </BaseInputGroup>

        <!-- Dirección -->
        <div>
          <BaseInputGroup :label="$t('settings.company_info.address')">
            <BaseTextarea v-model="companyForm.address.address_street_1" rows="2" />
          </BaseInputGroup>

          <BaseTextarea
            v-model="companyForm.address.address_street_2"
            rows="2"
            :row="2"
            class="mt-2"
          />
        </div>

        <div class="space-y-6">
          <!-- CIF (antes Número de identificación fiscal) -->
          <BaseInputGroup :label="'CIF'">
            <BaseInput v-model="companyForm.tax_id" type="text" />
          </BaseInputGroup>

          <!-- Número de IVA (solo asistencia) -->
          <BaseInputGroup v-if="isAsistencia" :label="'Número de IVA'">
            <BaseInput v-model="companyForm.vat_id" type="text" />
          </BaseInputGroup>
        </div>

        <!-- Email de contacto (obligatorio, RAÍZ) -->
        <BaseInputGroup
          :label="'Email de contacto'"
          :error="v$.contact_email.$error && v$.contact_email.$errors[0].$message"
          required
        >
          <BaseInput
            v-model="companyForm.contact_email"
            type="email"
            :invalid="v$.contact_email.$error"
            @blur="v$.contact_email.$touch()"
            placeholder="contacto@empresa.com"
          />
        </BaseInputGroup>

        <!-- Página web (opcional, RAÍZ) -->
        <BaseInputGroup :label="'Página web'">
          <BaseInput v-model="companyForm.website" placeholder="https://example.com" />
        </BaseInputGroup>

        <!-- Persona de contacto (opcional, RAÍZ) -->
        <BaseInputGroup :label="'Persona de contacto'">
          <BaseInput v-model="companyForm.contact_name" />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseButton :loading="isSaving" :disabled="isSaving" type="submit" class="mt-6">
        <template #left="slotProps">
          <BaseIcon v-if="!isSaving" :class="slotProps.class" name="ArrowDownOnSquareIcon" />
        </template>
        {{ $t('settings.company_info.save') }}
      </BaseButton>

      <div v-if="companyStore.companies.length !== 1" class="py-5">
        <BaseDivider class="my-4" />
        <h3 class="text-lg leading-6 font-medium text-gray-900">
          {{ $t('settings.company_info.delete_company') }}
        </h3>
        <div class="mt-2 max-w-xl text-sm text-gray-500">
          <p>{{ $t('settings.company_info.delete_company_description') }}</p>
        </div>
        <div class="mt-5">
          <button
            type="button"
            class="inline-flex items-center justify-center px-4 py-2 border border-transparent font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:text-sm"
            @click="removeCompany"
          >
            {{ $t('general.delete') }}
          </button>
        </div>
      </div>
    </BaseSettingCard>
  </form>
  <DeleteCompanyModal />
</template>

<script setup>
import { reactive, ref, inject, computed } from 'vue'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers, email } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal'
import DeleteCompanyModal from '@/scripts/admin/components/modal-components/DeleteCompanyModal.vue'

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const userStore = useUserStore()
const modalStore = useModalStore()
const { t } = useI18n()
const utils = inject('utils')

const isAsistencia = computed(() => userStore.currentUser?.role === 'asistencia')

let isSaving = ref(false)

const companyForm = reactive({
  name: null,
  logo: null,
  tax_id: null,          // CIF
  vat_id: null,          // Número de IVA (solo asistencia)
  // NUEVOS (en la RAÍZ de companies)
  contact_email: '',
  website: '',
  contact_name: '',
  address: {
    address_street_1: '',
    address_street_2: '',
    country_id: null,
    state: '',
    city: '',
    phone: '',
    zip: '',
  },
})

// precarga desde store
utils.mergeSettings(companyForm, { ...companyStore.selectedCompany })

let previewLogo = ref([])
let logoFileBlob = ref(null)
let logoFileName = ref(null)
const isCompanyLogoRemoved = ref(false)

if (companyForm.logo) {
  previewLogo.value.push({ image: companyForm.logo })
}

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(t('validation.name_min_length'), minLength(3)),
  },
  // email de contacto en la RAÍZ
  contact_email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  address: {
    // País requerido sólo visible para asistencia
    country_id: isAsistencia.value
      ? { required: helpers.withMessage(t('validation.required'), required) }
      : {},
  },
}))

const v$ = useVuelidate(rules, computed(() => companyForm))

globalStore.fetchCountries()

function onFileInputChange(fileName, file, fileCount, fileList) {
  logoFileName.value = fileList.name
  logoFileBlob.value = file
}
function onFileInputRemove() {
  logoFileBlob.value = null
  isCompanyLogoRemoved.value = true
}

async function updateCompanyData() {
  v$.value.$touch()
  if (v$.value.$invalid) return true

  isSaving.value = true

  // Payload que cuadra con el backend (companies.* + address.*)
  const payload = {
    name: companyForm.name,
    tax_id: companyForm.tax_id,
    contact_email: companyForm.contact_email,
    website: companyForm.website || '',
    contact_name: companyForm.contact_name || '',
    address: {
      address_street_1: companyForm.address.address_street_1,
      address_street_2: companyForm.address.address_street_2,
      state: companyForm.address.state,
      city: companyForm.address.city,
      phone: companyForm.address.phone,
      zip: companyForm.address.zip,
      // country_id solo si asistencia
      ...(isAsistencia.value ? { country_id: companyForm.address.country_id } : {}),
    },
    // vat_id solo si asistencia
    ...(isAsistencia.value ? { vat_id: companyForm.vat_id } : {}),
  }

  const res = await companyStore.updateCompany(payload)

  if (res.data.data) {
    if (logoFileBlob.value || isCompanyLogoRemoved.value) {
      const logoData = new FormData()
      if (logoFileBlob.value) {
        logoData.append(
          'company_logo',
          JSON.stringify({ name: logoFileName.value, data: logoFileBlob.value }),
        )
      }
      logoData.append('is_company_logo_removed', isCompanyLogoRemoved.value)

      await companyStore.updateCompanyLogo(logoData)
      logoFileBlob.value = null
      isCompanyLogoRemoved.value = false
    }
    isSaving.value = false
  }
  isSaving.value = false
}

function removeCompany() {
  modalStore.openModal({
    title: t('settings.company_info.are_you_absolutely_sure'),
    componentName: 'DeleteCompanyModal',
    size: 'sm',
  })
}
</script>
