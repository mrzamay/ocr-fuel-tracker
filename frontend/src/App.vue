<script setup>
import { useAuthStore } from './store/auth'
import { useRouter, useRoute } from 'vue-router'
import { onMounted, onUnmounted } from 'vue'
import { getOfflineRecords, deleteOfflineRecord } from './utils/db'
import api from './api'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const syncOfflineData = async () => {
  if (!auth.isAuthenticated) return
  const records = await getOfflineRecords()
  if (records.length === 0) return

  let syncedCount = 0
  for (const record of records) {
    const formData = new FormData()
    formData.append('receipt_image', record.file)
    try {
      await api.post('/records', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      await deleteOfflineRecord(record.id)
      syncedCount++
    } catch (e) {
      console.error('Ошибка оффлайн синхронизации', e)
    }
  }
  if (syncedCount > 0) {
    alert(`Синхронизировано ${syncedCount} чеков!`)
    if (route.path === '/history' || route.path === '/') router.go(0)
  }
}

onMounted(() => {
  window.addEventListener('online', syncOfflineData)
  if (navigator.onLine) syncOfflineData()
})

onUnmounted(() => {
  window.removeEventListener('online', syncOfflineData)
})

const logout = async () => {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="app-layout">
    <!-- Header -->
    <header class="top-header" v-if="auth.isAuthenticated">
      <h1>⛽ FuelTracker</h1>
      <button @click="logout" class="btn-icon">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
        </svg>
      </button>
    </header>

    <!-- Main Content -->
    <main class="main-content" :class="{ 'no-padding': !auth.isAuthenticated }">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <!-- Bottom Navigation (Mobile Style) -->
    <nav class="bottom-nav" v-if="auth.isAuthenticated">
      <router-link to="/" class="nav-item">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          <line x1="3" y1="9" x2="21" y2="9"/>
          <line x1="9" y1="21" x2="9" y2="9"/>
        </svg>
        <span>Дашборд</span>
      </router-link>
      
      <router-link to="/upload" class="nav-item nav-main">
        <div class="nav-fab">
          <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2" fill="none">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
        </div>
      </router-link>

      <router-link to="/history" class="nav-item">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
          <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
          <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
        <span>История</span>
      </router-link>
    </nav>
  </div>
</template>

<style>
/* CSS Variables */
:root {
  --primary: #4F46E5;
  --primary-hover: #4338CA;
  --bg-color: #F3F4F6;
  --card-bg: #FFFFFF;
  --text-main: #1F2937;
  --text-muted: #6B7280;
  --danger: #EF4444;
  --radius: 16px;
  --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

* { box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
body { margin: 0; background-color: var(--bg-color); color: var(--text-main); -webkit-tap-highlight-color: transparent; }

.app-layout {
  display: flex;
  flex-direction: column;
  height: 100vh;
  max-width: 600px;
  margin: 0 auto;
  background: var(--bg-color);
  position: relative;
}

/* Header */
.top-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  background: var(--card-bg);
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  z-index: 10;
}
.top-header h1 { margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--primary); }
.btn-icon { background: none; border: none; padding: 5px; color: var(--text-muted); cursor: pointer; }

/* Main Content */
.main-content {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  padding-bottom: 90px; /* Space for bottom nav */
}
.main-content.no-padding { padding-bottom: 20px; display: flex; align-items: center; }

/* Bottom Nav */
.bottom-nav {
  position: fixed;
  bottom: 0;
  width: 100%;
  max-width: 600px;
  background: var(--card-bg);
  display: flex;
  justify-content: space-around;
  align-items: center;
  padding: 10px 0;
  padding-bottom: env(safe-area-inset-bottom, 10px);
  box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
  border-top-left-radius: 20px;
  border-top-right-radius: 20px;
  z-index: 20;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-decoration: none;
  color: var(--text-muted);
  font-size: 0.75rem;
  gap: 4px;
  width: 33%;
}

.nav-item.router-link-active { color: var(--primary); font-weight: 600; }

.nav-main { position: relative; }
.nav-fab {
  background: var(--primary);
  color: white;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  position: absolute;
  bottom: -5px;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
  transition: transform 0.2s;
}
.nav-fab:active { transform: scale(0.95); }

/* Global UI Classes */
.card {
  background: var(--card-bg);
  padding: 20px;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  margin-bottom: 20px;
}
.card h2 { margin-top: 0; margin-bottom: 20px; font-size: 1.25rem; }

.input-group { margin-bottom: 15px; }
.input-group label { display: block; margin-bottom: 5px; font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
input, select {
  width: 100%;
  padding: 12px;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  font-size: 1rem;
  background: #F9FAFB;
  outline: none;
  transition: border 0.2s;
}
input:focus, select:focus { border-color: var(--primary); background: #fff; }

.btn-primary {
  width: 100%;
  background: var(--primary);
  color: white;
  border: none;
  padding: 14px;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
  transition: background 0.2s;
}
.btn-primary:hover { background: var(--primary-hover); }
.btn-primary:disabled { background: #9CA3AF; cursor: not-allowed; box-shadow: none; }

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
