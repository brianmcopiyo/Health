<script setup>
const props = defineProps({
  scene: { type: [String, Number], default: 'login' },
})

const kind = computed(() => {
  const scene = String(props.scene)
  const map = {
    login: 'identity',
    forgot: 'restore',
    reset: 'restore',
    verify: 'restore',
    sent: 'restore',
    401: 'session',
    403: 'access',
    404: 'missing',
    408: 'wait',
    410: 'missing',
    419: 'session',
    422: 'invalid',
    429: 'wait',
    500: 'fault',
    502: 'fault',
    503: 'fault',
    504: 'wait',
  }
  return map[scene] || 'missing'
})
</script>

<template>
  <svg
    class="h-scene-svg"
    viewBox="0 0 520 780"
    preserveAspectRatio="xMidYMid slice"
    fill="none"
    aria-hidden="true"
  >
    <defs>
      <linearGradient
        id="h-wash"
        x1="0.2"
        y1="0"
        x2="0.9"
        y2="1"
      >
        <stop
          offset="0%"
          stop-color="currentColor"
          stop-opacity="0.14"
        />
        <stop
          offset="100%"
          stop-color="currentColor"
          stop-opacity="0.02"
        />
      </linearGradient>
      <pattern
        id="h-ward"
        width="28"
        height="22"
        patternUnits="userSpaceOnUse"
      >
        <rect
          x="3"
          y="4"
          width="20"
          height="14"
          rx="2"
          stroke="currentColor"
          stroke-width="0.8"
          opacity="0.32"
        />
      </pattern>
    </defs>
    <g
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <circle
        cx="260"
        cy="292"
        r="310"
        opacity="0.1"
        stroke-width="1"
      />
      <circle
        cx="260"
        cy="292"
        r="248"
        opacity="0.14"
        stroke-width="1.15"
      />
      <circle
        cx="260"
        cy="292"
        r="186"
        opacity="0.18"
        stroke-width="1.2"
      />
      <circle
        cx="260"
        cy="292"
        r="124"
        opacity="0.28"
        stroke-width="1.3"
      />
      <circle
        cx="260"
        cy="292"
        r="62"
        fill="url(#h-wash)"
        opacity="0.9"
        stroke-width="1.4"
      />
      <path
        d="M-10 292h540"
        opacity="0.1"
        stroke-width="1"
      />
      <path
        d="M260 -20v820"
        opacity="0.08"
        stroke-width="1"
      />
      <g v-if="kind === 'identity' || kind === 'restore'">
        <rect
          x="86"
          y="176"
          width="348"
          height="232"
          rx="8"
          fill="url(#h-wash)"
          stroke-width="1.4"
        />
        <rect
          x="108"
          y="200"
          width="304"
          height="184"
          fill="url(#h-ward)"
          stroke="none"
        />
        <path
          d="M86 240h348"
          opacity="0.22"
          stroke-width="1.1"
        />
        <rect
          x="236"
          y="280"
          width="48"
          height="48"
          rx="6"
          fill="currentColor"
          opacity="0.12"
          stroke-width="1.5"
        />
        <path
          d="M260 292v24M248 304h24"
          opacity="0.8"
          stroke-width="1.6"
        />
        <path
          d="M40 468c48-36 102-36 150 0s110 40 168 4 102-28 142 18"
          opacity="0.55"
          stroke-width="2"
        />
        <path
          d="M40 492c56-22 108-8 154 14 58 28 112 8 166-10 46-16 86-8 120 16"
          opacity="0.2"
          stroke-width="1.3"
        />
        <circle
          cx="96"
          cy="468"
          r="6"
          opacity="0.5"
          stroke-width="1.2"
        />
        <circle
          cx="246"
          cy="468"
          r="6"
          opacity="0.7"
          stroke-width="1.2"
        />
        <circle
          cx="414"
          cy="472"
          r="6"
          opacity="0.5"
          stroke-width="1.2"
        />
        <rect
          x="72"
          y="528"
          width="92"
          height="58"
          rx="6"
          opacity="0.4"
          stroke-width="1.2"
        />
        <rect
          x="214"
          y="518"
          width="92"
          height="68"
          rx="6"
          opacity="0.55"
          stroke-width="1.3"
        />
        <rect
          x="356"
          y="534"
          width="92"
          height="52"
          rx="6"
          opacity="0.35"
          stroke-width="1.2"
        />
        <path
          d="M118 528v-18h-20M260 518v-22h-22M402 534v-16h18"
          opacity="0.28"
          stroke-width="1.1"
        />
        <g
          v-if="kind === 'restore'"
          opacity="0.85"
        >
          <circle
            cx="412"
            cy="168"
            r="34"
            stroke-width="1.6"
          />
          <path
            d="M412 150v22l14 8"
            stroke-width="1.6"
          />
        </g>
      </g>
      <g v-else>
        <rect
          x="86"
          y="176"
          width="348"
          height="232"
          rx="8"
          stroke-width="1.3"
          opacity="0.55"
        />
        <rect
          x="108"
          y="200"
          width="118"
          height="184"
          fill="url(#h-ward)"
          stroke="none"
          opacity="0.7"
        />
        <rect
          x="246"
          y="200"
          width="146"
          height="184"
          stroke-dasharray="7 8"
          opacity="0.4"
          stroke-width="1.3"
        />
        <circle
          cx="319"
          cy="292"
          r="26"
          opacity="0.7"
          stroke-width="1.5"
        />
        <path
          d="M40 468c48-20 96-8 140 18"
          opacity="0.35"
          stroke-width="1.8"
        />
        <path
          d="M250 486c54 8 110-24 230-8"
          opacity="0.18"
          stroke-dasharray="8 10"
          stroke-width="1.6"
        />
        <rect
          x="72"
          y="528"
          width="92"
          height="58"
          rx="6"
          opacity="0.4"
          stroke-width="1.2"
        />
        <rect
          x="214"
          y="518"
          width="92"
          height="68"
          rx="6"
          stroke-dasharray="6 7"
          opacity="0.4"
          stroke-width="1.2"
        />
        <rect
          x="356"
          y="534"
          width="92"
          height="52"
          rx="6"
          opacity="0.3"
          stroke-width="1.2"
        />
        <g v-if="kind === 'session'">
          <circle
            cx="260"
            cy="168"
            r="40"
            opacity="0.45"
            stroke-width="1.4"
          />
          <path
            d="M260 146v26l16 8"
            opacity="0.85"
            stroke-width="1.6"
          />
        </g>
        <g v-if="kind === 'access'">
          <rect
            x="296"
            y="266"
            width="46"
            height="58"
            stroke-width="1.5"
          />
          <path
            d="M306 266V250a13 13 0 0 1 26 0v16"
            stroke-width="1.5"
          />
        </g>
        <g v-if="kind === 'wait'">
          <circle
            cx="319"
            cy="168"
            r="24"
            opacity="0.4"
            stroke-width="1.3"
          />
          <circle
            cx="319"
            cy="168"
            r="42"
            opacity="0.2"
            stroke-width="1.1"
          />
        </g>
        <g v-if="kind === 'fault'">
          <path
            d="M70 168h64l16 24 20-44 18 28h90"
            opacity="0.55"
            stroke-width="1.6"
          />
        </g>
        <g v-if="kind === 'invalid'">
          <path
            d="M300 274l38 38M338 274l-38 38"
            opacity="0.7"
            stroke-width="1.6"
          />
        </g>
      </g>
    </g>
  </svg>
</template>
