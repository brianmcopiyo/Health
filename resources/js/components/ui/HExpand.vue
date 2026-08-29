<script setup>
import { prefersReducedMotion } from '@/composables/useMotion'

const reduced = () => prefersReducedMotion()

const enter = el => {
  if (reduced())
    return
  el.style.height = '0'
  el.style.overflow = 'hidden'
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      el.style.height = `${el.scrollHeight}px`
    })
  })
}

const afterEnter = el => {
  el.style.height = ''
  el.style.overflow = ''
}

const leave = el => {
  if (reduced())
    return
  el.style.height = `${el.scrollHeight}px`
  el.style.overflow = 'hidden'
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      el.style.height = '0'
    })
  })
}

const afterLeave = el => {
  el.style.height = ''
  el.style.overflow = ''
}
</script>

<template>
  <Transition
    name="h-expand"
    @enter="enter"
    @after-enter="afterEnter"
    @leave="leave"
    @after-leave="afterLeave"
  >
    <slot />
  </Transition>
</template>
