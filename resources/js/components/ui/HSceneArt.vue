<script setup>
const props = defineProps({
  scene: { type: [String, Number], default: 'login' },
})

const kind = computed(() => {
  const scene = String(props.scene)
  const map = {
    login: 'login',
    forgot: 'forgot',
    reset: 'reset',
    verify: 'verify',
    sent: 'sent',
    401: 'session',
    403: 'access',
    404: 'missing',
    408: 'wait',
    410: 'gone',
    419: 'expired',
    422: 'invalid',
    429: 'limit',
    500: 'fault',
    502: 'gateway',
    503: 'offline',
    504: 'wait',
  }
  return map[scene] || 'missing'
})
</script>

<template>
  <svg
    class="h-scene-svg"
    viewBox="0 0 400 240"
    fill="none"
    aria-hidden="true"
  >
    <g
      v-if="kind === 'login'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <circle
        cx="200"
        cy="118"
        r="102"
        opacity="0.1"
        stroke-width="1"
      />
      <circle
        cx="200"
        cy="118"
        r="74"
        opacity="0.16"
        stroke-width="1"
      />
      <circle
        cx="200"
        cy="118"
        r="46"
        opacity="0.28"
        stroke-width="1.4"
      />
      <rect
        x="148"
        y="78"
        width="104"
        height="96"
        rx="8"
        fill="currentColor"
        opacity="0.12"
        stroke="none"
      />
      <path
        d="M164 174V94h72v80"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M190 78V64h20v14"
        opacity="0.8"
        stroke-width="1.6"
      />
      <path
        d="M194 68h12M200 62v12"
        opacity="0.95"
        stroke-width="2.2"
      />
      <rect
        x="176"
        y="108"
        width="16"
        height="14"
        rx="2"
        opacity="0.55"
        stroke-width="1.2"
      />
      <rect
        x="208"
        y="108"
        width="16"
        height="14"
        rx="2"
        opacity="0.55"
        stroke-width="1.2"
      />
      <rect
        x="176"
        y="132"
        width="16"
        height="14"
        rx="2"
        opacity="0.35"
        stroke-width="1.2"
      />
      <rect
        x="208"
        y="132"
        width="16"
        height="14"
        rx="2"
        opacity="0.35"
        stroke-width="1.2"
      />
      <path
        d="M188 174v-22h24v22"
        opacity="0.7"
        stroke-width="1.6"
      />
      <polyline
        points="48,208 96,208 118,176 138,218 164,158 188,208 352,208"
        opacity="0.55"
        stroke-width="2"
      />
    </g>
    <g
      v-else-if="kind === 'forgot'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <circle
        cx="132"
        cy="118"
        r="70"
        opacity="0.12"
        stroke-width="1"
      />
      <rect
        x="86"
        y="78"
        width="92"
        height="64"
        rx="8"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M86 86l46 32 46-32"
        opacity="0.8"
        stroke-width="1.6"
      />
      <rect
        x="214"
        y="96"
        width="72"
        height="58"
        rx="10"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M232 96V82a18 18 0 0 1 36 0v14"
        opacity="0.85"
        stroke-width="1.8"
      />
      <circle
        cx="250"
        cy="124"
        r="6"
        opacity="0.8"
        stroke-width="1.6"
      />
    </g>
    <g
      v-else-if="kind === 'reset'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <circle
        cx="200"
        cy="120"
        r="88"
        opacity="0.12"
        stroke-width="1"
      />
      <circle
        cx="148"
        cy="118"
        r="28"
        opacity="0.8"
        stroke-width="1.8"
      />
      <path
        d="M176 118h90l18 18M248 118l16 16M266 136l14-8"
        opacity="0.85"
        stroke-width="1.8"
      />
      <rect
        x="168"
        y="158"
        width="86"
        height="28"
        rx="8"
        opacity="0.35"
        stroke-width="1.2"
      />
    </g>
    <g
      v-else-if="kind === 'verify' || kind === 'session'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <circle
        cx="168"
        cy="118"
        r="54"
        opacity="0.18"
        stroke-width="1.4"
      />
      <circle
        cx="168"
        cy="118"
        r="36"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M168 96v24l16 10"
        opacity="0.9"
        stroke-width="1.8"
      />
      <rect
        x="236"
        y="96"
        width="70"
        height="56"
        rx="10"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M254 96V84a17 17 0 0 1 34 0v12"
        opacity="0.8"
        stroke-width="1.8"
      />
    </g>
    <g
      v-else-if="kind === 'sent'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <circle
        cx="200"
        cy="118"
        r="86"
        opacity="0.12"
        stroke-width="1"
      />
      <rect
        x="118"
        y="78"
        width="164"
        height="84"
        rx="10"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M118 92l82 48 82-48"
        opacity="0.8"
        stroke-width="1.6"
      />
      <circle
        cx="278"
        cy="78"
        r="18"
        fill="currentColor"
        opacity="0.18"
        stroke="none"
      />
      <path
        d="M270 78l6 6 12-12"
        opacity="0.95"
        stroke-width="2"
      />
    </g>
    <g
      v-else-if="kind === 'expired'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <circle
        cx="200"
        cy="118"
        r="58"
        opacity="0.7"
        stroke-width="1.8"
      />
      <path
        d="M200 86v36l22 12"
        opacity="0.9"
        stroke-width="1.8"
      />
      <path
        d="M154 74l92 88M246 74L154 162"
        opacity="0.45"
        stroke-width="1.6"
      />
    </g>
    <g
      v-else-if="kind === 'access'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <path
        d="M200 44l86 28v52c0 48-38 74-86 90-48-16-86-42-86-90V72z"
        opacity="0.7"
        stroke-width="1.8"
      />
      <path
        d="M168 118h64M176 138h48"
        opacity="0.55"
        stroke-width="1.6"
      />
      <path
        d="M188 102h24v28h-24z"
        opacity="0.8"
        stroke-width="1.6"
      />
    </g>
    <g
      v-else-if="kind === 'missing'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <rect
        x="72"
        y="58"
        width="256"
        height="140"
        rx="12"
        opacity="0.14"
        stroke-width="1"
      />
      <path
        d="M96 174V86h56v88"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M168 174V102h52v72"
        opacity="0.35"
        stroke-dasharray="6 6"
        stroke-width="1.6"
      />
      <path
        d="M236 174V78h68v96"
        opacity="0.7"
        stroke-width="1.6"
      />
      <circle
        cx="194"
        cy="128"
        r="16"
        opacity="0.8"
        stroke-width="1.6"
      />
      <path
        d="M206 140l14 16"
        opacity="0.8"
        stroke-width="1.8"
      />
    </g>
    <g
      v-else-if="kind === 'gone'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <rect
        x="128"
        y="86"
        width="144"
        height="88"
        rx="8"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M128 108h144M160 86V70h80v16"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M168 136h64M168 152h40"
        opacity="0.45"
        stroke-width="1.6"
      />
    </g>
    <g
      v-else-if="kind === 'wait'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <circle
        cx="200"
        cy="120"
        r="70"
        opacity="0.16"
        stroke-width="1"
      />
      <circle
        cx="200"
        cy="120"
        r="46"
        opacity="0.75"
        stroke-width="1.8"
      />
      <path
        d="M200 88v36l20 12"
        opacity="0.9"
        stroke-width="1.8"
      />
      <path
        d="M200 48v16M200 176v16M128 120H112M288 120h-16"
        opacity="0.35"
        stroke-width="1.4"
      />
    </g>
    <g
      v-else-if="kind === 'invalid'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <rect
        x="118"
        y="58"
        width="164"
        height="132"
        rx="10"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M142 86h116M142 110h88M142 134h72"
        opacity="0.45"
        stroke-width="1.6"
      />
      <path
        d="M236 148l28 28M264 148l-28 28"
        opacity="0.85"
        stroke-width="1.8"
      />
    </g>
    <g
      v-else-if="kind === 'limit'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <rect
        x="96"
        y="70"
        width="44"
        height="108"
        rx="8"
        opacity="0.35"
        stroke-width="1.4"
      />
      <rect
        x="156"
        y="94"
        width="44"
        height="84"
        rx="8"
        opacity="0.55"
        stroke-width="1.4"
      />
      <rect
        x="216"
        y="54"
        width="44"
        height="124"
        rx="8"
        opacity="0.28"
        stroke-dasharray="5 6"
        stroke-width="1.4"
      />
      <rect
        x="276"
        y="118"
        width="28"
        height="60"
        rx="6"
        opacity="0.2"
        stroke-width="1.2"
      />
      <path
        d="M228 108v28M248 108v28"
        opacity="0.9"
        stroke-width="2.2"
      />
    </g>
    <g
      v-else-if="kind === 'fault'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <rect
        x="112"
        y="58"
        width="176"
        height="48"
        rx="8"
        opacity="0.7"
        stroke-width="1.6"
      />
      <rect
        x="112"
        y="118"
        width="176"
        height="48"
        rx="8"
        opacity="0.7"
        stroke-width="1.6"
      />
      <circle
        cx="136"
        cy="82"
        r="4"
        opacity="0.8"
        stroke-width="1.4"
      />
      <circle
        cx="136"
        cy="142"
        r="4"
        opacity="0.8"
        stroke-width="1.4"
      />
      <polyline
        points="168,142 188,142 198,158 214,126 228,142 272,142"
        opacity="0.7"
        stroke-width="1.8"
      />
    </g>
    <g
      v-else-if="kind === 'gateway'"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <rect
        x="72"
        y="86"
        width="88"
        height="68"
        rx="10"
        opacity="0.7"
        stroke-width="1.6"
      />
      <rect
        x="240"
        y="86"
        width="88"
        height="68"
        rx="10"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M160 120h28M212 120h28"
        opacity="0.45"
        stroke-dasharray="5 7"
        stroke-width="1.8"
      />
      <path
        d="M188 108l12 12-12 12"
        opacity="0.35"
        stroke-width="1.6"
      />
    </g>
    <g
      v-else
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <path
        d="M132 174V86l68-28 68 28v88"
        opacity="0.7"
        stroke-width="1.6"
      />
      <path
        d="M164 174v-40h72v40"
        opacity="0.55"
        stroke-width="1.6"
      />
      <path
        d="M188 92h24M200 84v24"
        opacity="0.9"
        stroke-width="2"
      />
      <path
        d="M96 188h208"
        opacity="0.25"
        stroke-width="1.4"
      />
    </g>
  </svg>
</template>
