import laravel from 'laravel-vite-plugin'
import { fileURLToPath } from 'node:url'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports, getPascalCaseRouteName } from 'unplugin-vue-router'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [
    VueRouter({
      getRouteName: routeNode => getPascalCaseRouteName(routeNode)
        .replace(/([a-z\d])([A-Z])/g, '$1-$2')
        .toLowerCase(),
      routesFolder: 'resources/js/pages',
      exclude: [
        'dashboards/**',
        'apps/**',
        'pages/**',
        'forms/**',
        'tables/**',
        'charts/**',
        'components/**',
        'extensions/**',
        'wizard-examples/**',
        'front-pages/**',
        'access-control.vue',
      ],
    }),
    vue(),
    laravel({
      input: ['resources/js/main.js'],
      refresh: true,
    }),
    Components({
      dirs: ['resources/js/components', 'resources/js/layouts'],
      dts: true,
    }),
    AutoImport({
      imports: ['vue', VueRouterAutoImports],
      dirs: [
        './resources/js/composables/',
        './resources/js/utils/',
      ],
      vueTemplate: true,
      dts: true,
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
    cors: true,
    hmr: { host: '127.0.0.1' },
  },
})
