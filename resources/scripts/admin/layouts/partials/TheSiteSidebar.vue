<template>
  <!-- MOBILE MENU -->
  <TransitionRoot as="template" :show="globalStore.isSidebarOpen">
    <Dialog
      as="div"
      class="fixed inset-0 z-40 flex md:hidden"
      @close="globalStore.setSidebarVisibility(false)"
    >
      <TransitionChild
        as="template"
        enter="transition-opacity ease-linear duration-300"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="transition-opacity ease-linear duration-300"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <DialogOverlay class="fixed inset-0 bg-gray-600 bg-opacity-75" />
      </TransitionChild>

      <TransitionChild
        as="template"
        enter="transition ease-in-out duration-300"
        enter-from="-translate-x-full"
        enter-to="translate-x-0"
        leave="transition ease-in-out duration-300"
        leave-from="translate-x-0"
        leave-to="-translate-x-full"
      >
        <div class="relative flex flex-col flex-1 w-full max-w-xs bg-[#070322]">
          <TransitionChild
            as="template"
            enter="ease-in-out duration-300"
            enter-from="opacity-0"
            enter-to="opacity-100"
            leave="ease-in-out duration-300"
            leave-from="opacity-100"
            leave-to="opacity-0"
          >
            <div class="absolute top-0 right-0 pt-2 -mr-12">
              <button
                class="flex items-center justify-center w-10 h-10 ml-1 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                @click="globalStore.setSidebarVisibility(false)"
              >
                <span class="sr-only">Close sidebar</span>
                <BaseIcon name="XMarkIcon" class="w-6 h-6 text-white" aria-hidden="true" />
              </button>
            </div>
          </TransitionChild>
          <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
            <div class="flex items-center shrink-0 px-4 mb-10">
              <MainLogo class="block h-auto max-w-full w-36 text-primary-400" alt="Onfactu" />
            </div>

            <nav v-for="menu in globalStore.menuGroups" :key="menu" class="mt-5 space-y-1">
              <component
                :is="item.external ? 'a' : 'router-link'"
                v-for="item in menu"
                :key="item.name"
                v-bind="item.external ? { href: item.link, target: '_blank' } : { to: item.link }"
                :class="[
                  !item.external && hasActiveUrl(item.link)
                    ? 'bg-[#38d587] text-[#070322] border-[#38d587]'
                    : 'text-white border-transparent hover:bg-white/10 hover:text-white',
                  'cursor-pointer px-0 pl-6 py-3 group flex items-center border-l-4 border-solid text-sm not-italic font-medium transition-colors',
                ]"
                @click="globalStore.setSidebarVisibility(false)"
              >
                <img
                  v-if="item.custom_icon"
                  :src="!item.external && hasActiveUrl(item.link) && item.custom_icon_active ? item.custom_icon_active : item.custom_icon"
                  :alt="$t(item.title)"
                  class="mr-4 shrink-0 h-5 w-5"
                />
                <BaseIcon
                  v-else
                  :name="item.icon"
                  :class="[hasActiveUrl(item.link) ? 'text-primary-500' : 'text-gray-400', 'mr-4 shrink-0 h-5 w-5']"
                />
                {{ $t(item.title) }}
              </component>
            </nav>
          </div>

          <!-- MOBILE: Sección inferior -->
          <div class="onf-sidebar-bottom">
            <div v-if="planName" class="onf-sidebar-plan">
              <span class="onf-sidebar-plan-label">{{ planName }}</span>
              <a v-if="portalUrl" :href="portalUrl" target="_blank" class="onf-sidebar-plan-link">Gestionar suscripción</a>
            </div>
            <button class="onf-sidebar-logout" @click="logout">
              <BaseIcon name="ArrowLeftOnRectangleIcon" class="w-5 h-5 mr-3" />
              Cerrar sesión
            </button>
          </div>
        </div>
      </TransitionChild>
      <div class="shrink-0 w-14"></div>
    </Dialog>
  </TransitionRoot>

  <!-- DESKTOP MENU -->
  <div
    class="hidden w-56 h-screen overflow-y-auto bg-[#070322] xl:w-64 md:fixed md:flex md:flex-col md:inset-y-0 pt-16"
  >
    <!-- Menú principal (con scroll) -->
    <div class="flex-1 overflow-y-auto pb-4">
      <div v-for="menu in globalStore.menuGroups" :key="menu" class="p-0 m-0 mt-6 list-none">
        <component
          :is="item.external ? 'a' : 'router-link'"
          v-for="item in menu"
          :key="item"
          v-bind="item.external ? { href: item.link, target: '_blank' } : { to: item.link }"
          :class="[
            !item.external && hasActiveUrl(item.link)
              ? 'bg-[#38d587] text-[#070322] border-[#38d587]'
              : 'text-white border-transparent hover:bg-white/10 hover:text-white',
            'cursor-pointer px-0 pl-6 py-3 group flex items-center border-l-4 border-solid text-sm not-italic font-medium transition-colors',
          ]"
        >
          <img
            v-if="item.custom_icon"
            :src="!item.external && hasActiveUrl(item.link) && item.custom_icon_active ? item.custom_icon_active : item.custom_icon"
            :alt="$t(item.title)"
            class="mr-4 shrink-0 h-5 w-5"
          />
          <BaseIcon
            v-else
            :name="item.icon"
            :class="[
              hasActiveUrl(item.link) ? 'text-[#070322]' : 'text-white/70 group-hover:text-white',
              'mr-4 shrink-0 h-5 w-5',
            ]"
          />
          {{ $t(item.title) }}
        </component>
      </div>
    </div>

    <!-- DESKTOP: Sección inferior fija -->
    <div class="onf-sidebar-bottom">
      <div v-if="planName" class="onf-sidebar-plan">
        <span class="onf-sidebar-plan-label">{{ planName }}</span>
        <a v-if="portalUrl" :href="portalUrl" target="_blank" class="onf-sidebar-plan-link">Gestionar suscripción</a>
      </div>
      <button class="onf-sidebar-logout" @click="logout">
        <BaseIcon name="ArrowLeftOnRectangleIcon" class="w-5 h-5 mr-3" />
        Cerrar sesión
      </button>
    </div>
  </div>
</template>

<script setup>
import MainLogo from '@/scripts/components/icons/MainLogo.vue'
import axios from 'axios'

import {
  Dialog,
  DialogOverlay,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'

import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useAuthStore } from '@/scripts/admin/stores/auth'

const route = useRoute()
const globalStore = useGlobalStore()
const authStore = useAuthStore()

const planName = ref('')
const portalUrl = ref('')

function hasActiveUrl(url) {
  return route.path.indexOf(url) > -1
}

async function logout() {
  await authStore.logout()
}

onMounted(async () => {
  try {
    const res = await axios.get('/api/v1/app-config/my-plan')
    if (res.data?.ok) {
      planName.value = res.data.plan_name || ''
      portalUrl.value = res.data.portal_url || ''
    }
  } catch (e) {
    // Silenciar error
  }
})
</script>

<style scoped>
.onf-sidebar-bottom {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding: 1rem 1.5rem;
  flex-shrink: 0;
}

.onf-sidebar-plan {
  display: flex;
  flex-direction: column;
  margin-bottom: 0.75rem;
}

.onf-sidebar-plan-label {
  color: #38d587;
  font-size: 0.875rem;
  font-weight: 600;
}

.onf-sidebar-plan-link {
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.8125rem;
  text-decoration: none;
  margin-top: 0.25rem;
  transition: color 0.15s;
}

.onf-sidebar-plan-link:hover {
  color: #ffffff;
}

.onf-sidebar-logout {
  display: flex;
  align-items: center;
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.875rem;
  font-weight: 500;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.625rem 0;
  width: 100%;
  transition: color 0.15s;
}

.onf-sidebar-logout:hover {
  color: #ffffff;
}
</style>
