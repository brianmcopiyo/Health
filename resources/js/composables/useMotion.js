export const prefersReducedMotion = () =>
  typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

export const motionNames = {
  page: 'h-page',
  fade: 'h-fade',
  rise: 'h-rise',
  pop: 'h-pop',
  tab: 'h-tab',
  overlay: 'h-overlay',
  drawer: 'h-drawer',
  expand: 'h-expand',
  toast: 'h-toast',
}
