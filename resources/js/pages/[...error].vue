<script setup>
definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const userData = useCookie('userData')
</script>

<template>
  <HErrorPage code="404">
    <HButton
      v-if="userData"
      to="/"
    >
      Open workspace
    </HButton>
    <HButton
      v-else
      :to="{ name: 'login' }"
    >
      Sign in
    </HButton>
  </HErrorPage>
</template>
