<script setup>
const { isDark, toggleTheme } = useTheme()
</script>

<template>
  <button
    class="hms-icon-btn"
    type="button"
    :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    @click="toggleTheme"
  >
    <HIcon
      :name="isDark ? 'sun' : 'moon'"
      :size="18"
    />
  </button>
</template>
