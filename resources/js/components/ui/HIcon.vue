<script setup>
const props = defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 18 },
})

const icons = {
  desk: 'M4 10h16M4 10v8M20 10v8M2 18h20M8 10V6h8v4',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
  stethoscope: 'M4.8 2.3A.7.7 0 1 0 6 2.3v8.4a4 4 0 0 0 8 0V6M6 6h4M18 14a3 3 0 1 0 0-6 3 3 0 0 0 0 6M18 14v2a6 6 0 0 1-6 6h-1',
  emergency: 'M3 7v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7M3 7l3-4h12l3 4M8 14h.01M12 14h.01M16 14h.01',
  hospital: 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6',
  bed: 'M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8M4 10V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4M2 18h20',
  cut: 'M6 6l12 12M6 18L18 6',
  flask: 'M10 2v7.5L4.2 20.2A2 2 0 0 0 6 23h12a2 2 0 0 0 1.8-2.8L14 9.5V2M8 2h8',
  scan: 'M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2',
  pill: 'M10.5 20.5l-7-7a5 5 0 0 1 7-7l7 7a5 5 0 0 1-7 7M8.5 8.5l7 7',
  transfer: 'M8 3L4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4',
  heartbeat: 'M2 12h4l3 8 4-16 3 8h6',
  ambulance: 'M10 17h.01M18 17h.01M2 9h11v8H2zM13 6h5l3 3v8h-8V6',
  receipt: 'M4 2v20l4-2 4 2 4-2 4 2V2l-4 2-4-2-4 2zM8 10h8M8 14h5',
  chart: 'M3 3v18h18M7 16v-5M12 16V8M17 16v-8',
  dashboard: 'M3 9h8V3H3zM13 21h8v-6h-8zM13 3v6h8V3zM3 21h8v-8H3z',
  building: 'M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18M6 12h12',
  community: 'M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6',
  shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10',
  plus: 'M5 12h14M12 5v14',
  edit: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
  eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
  refresh: 'M21 12a9 9 0 1 1-2.6-6.4M21 3v6h-6',
  menu: 'M4 6h16M4 12h16M4 18h16',
  logout: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
  search: 'M21 21l-4.3-4.3M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14',
  x: 'M18 6L6 18M6 6l12 12',
  user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
  cross: 'M12 6v12M8 10h8',
  back: 'M19 12H5M12 19l-7-7 7-7',
}

const d = computed(() => icons[props.name] || icons.cross)
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path :d="d" />
  </svg>
</template>
