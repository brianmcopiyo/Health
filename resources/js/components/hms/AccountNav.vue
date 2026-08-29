<script setup>
const route = useRoute()
</script>

<template>
  <nav
    class="h-account-nav"
    aria-label="Account"
  >
    <RouterLink
      :to="{ name: 'account-profile' }"
      :class="{ 'is-on': route.name === 'account-profile' }"
    >
      <HIcon
        name="user"
        :size="16"
      />
      Profile
    </RouterLink>
    <RouterLink
      :to="{ name: 'account-security' }"
      :class="{ 'is-on': route.name === 'account-security' }"
    >
      <HIcon
        name="lock"
        :size="16"
      />
      Security
    </RouterLink>
  </nav>
</template>
