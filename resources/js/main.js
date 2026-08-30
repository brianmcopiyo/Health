import { createApp } from 'vue'
import { abilitiesPlugin } from '@casl/vue'
import App from '@/App.vue'
import { router } from '@/plugins/router'
import { ability, hydrateAbility } from '@/plugins/ability'
import { useTheme } from '@/composables/useTheme'
import '@/../css/hms.css'

hydrateAbility()
useTheme()

const app = createApp(App)

app.use(abilitiesPlugin, ability, { useGlobalProperties: true })
app.use(router)
app.mount('#app')
