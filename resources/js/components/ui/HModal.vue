<script setup>
const props = defineProps({
  modelValue: Boolean,
  title: String,
  error: String,
  size: { type: String, default: 'md' },
  persistent: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const isOpen = computed(() => props.modelValue)
const close = () => {
  if (!props.persistent)
    emit('update:modelValue', false)
}

useOverlay(isOpen, close)
</script>

<template>
  <Teleport to="body">
    <Transition name="h-overlay">
      <div
        v-if="modelValue"
        class="h-overlay"
        role="dialog"
        aria-modal="true"
        @click.self="close"
      >
      <div
        class="h-modal"
        :class="`is-${size}`"
        @click.stop
      >
        <div class="h-overlay-head">
          <div>
            <h3>{{ title }}</h3>
            <p
              v-if="$slots.subtitle"
              class="h-overlay-sub"
            >
              <slot name="subtitle" />
            </p>
          </div>
          <HButton
            variant="ghost"
            size="icon"
            :disabled="persistent"
            @click="close"
          >
            <HIcon name="x" />
          </HButton>
        </div>
        <div class="h-overlay-body">
          <HTransition name="h-fade">
            <div
              v-if="error"
              class="h-alert"
            >
              {{ error }}
            </div>
          </HTransition>
          <slot />
        </div>
        <div
          v-if="$slots.actions"
          class="h-overlay-foot"
        >
          <slot name="actions" />
        </div>
      </div>
    </div>
    </Transition>
  </Teleport>
</template>
