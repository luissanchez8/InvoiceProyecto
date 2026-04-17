<template>
  <div class="flex flex-col items-center justify-center h-screen">
    <img
      src="/images/logo-onfactu-2.png"
      alt="Onfactu"
      class="onfactu-loader-logo"
    />
  </div>
</template>

<script setup>
const props = defineProps({
  showBgOverlay: {
    default: false,
    type: Boolean,
  },
})
</script>

<style>
.onfactu-loader-logo {
  max-width: 240px;
  height: auto;
  animation: onfactu-loader-pulse 1.5s ease-in-out infinite;
}

@keyframes onfactu-loader-pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.7;
    transform: scale(0.97);
  }
}
</style>
