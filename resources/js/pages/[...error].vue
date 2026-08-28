<script setup>
definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})
</script>

<template>
  <div class="blank-state">
    <p class="hms-kicker">
      404
    </p>
    <h1>This page is not in the hospital map.</h1>
    <HButton
      to="/"
      style="margin-top:18px"
    >
      Return to Caregrid
    </HButton>
  </div>
</template>
