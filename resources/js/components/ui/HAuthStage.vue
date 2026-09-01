<script setup>
defineProps({
  kind: { type: String, default: 'auth' },
  scene: { type: [String, Number], default: 'login' },
  tone: { type: String, default: '' },
  brand: { type: String, default: 'Caregrid' },
  headline: { type: String, required: true },
  lead: { type: String, default: '' },
  points: { type: Array, default: () => [] },
})
</script>

<template>
  <div
    class="h-stage"
    :class="kind === 'error' ? 'h-error' : 'h-auth'"
    :data-scene="scene"
    :data-tone="tone || undefined"
  >
    <section
      class="h-stage-art"
      :class="kind === 'error' ? 'h-error-art' : 'h-auth-art'"
      :data-scene="scene"
      :data-tone="tone || undefined"
    >
      <div
        class="h-scene-field"
        aria-hidden="true"
      >
        <span class="h-scene-plane is-a" />
        <span class="h-scene-plane is-b" />
        <span class="h-scene-plane is-c" />
        <HSceneArt :scene="scene" />
        <span
          v-if="kind === 'error'"
          class="h-scene-code"
        >{{ scene }}</span>
      </div>
      <div class="h-scene-copy">
        <p class="hms-kicker">
          {{ brand }}
        </p>
        <h2>{{ headline }}</h2>
        <p v-if="lead">
          {{ lead }}
        </p>
        <ul
          v-if="points.length"
          class="h-scene-points"
        >
          <li
            v-for="point in points"
            :key="point.title"
          >
            <strong>{{ point.title }}</strong>
            {{ point.body }}
          </li>
        </ul>
      </div>
    </section>
    <section
      class="h-stage-panel"
      :class="kind === 'error' ? 'h-error-panel' : 'h-auth-panel'"
    >
      <HThemeToggle class="h-theme-float" />
      <div class="h-stage-shell">
        <slot />
      </div>
    </section>
  </div>
</template>
