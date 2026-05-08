import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name: 'Учет расхода топлива',
        short_name: 'Топливо',
        description: 'PWA приложение для учета расхода топлива по чекам',
        theme_color: '#ffffff',
        icons: [
          {
            src: 'pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png'
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png'
          }
        ]
      },
      workbox: {
        // Кэшируем запросы к API для оффлайн-работы
        runtimeCaching: [
          {
            urlPattern: /^http:\/\/.*\/api\/records/,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-records-cache',
              expiration: {
                maxEntries: 50,
                maxAgeSeconds: 60 * 60 * 24 * 7 // 1 неделя
              },
              cacheableResponse: { statuses: [200] }
            }
          }
        ]
      }
    })
  ],
  server: {
    port: 5173,
    host: '0.0.0.0',
    proxy: {
      // Проксируем API-запросы к локальному Nginx (Laravel).
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true
      },
      '/storage': {
         target: 'http://localhost:8080',
         changeOrigin: true
      }
    }
  }
})
