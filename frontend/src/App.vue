<script setup>
import { useAuthStore } from './store/auth'
import { useRouter, useRoute } from 'vue-router'
import { onMounted, onUnmounted, ref } from 'vue'
import {
  countOfflineActions,
  deleteOfflineAction,
  deleteOfflineRecord,
  getOfflineActions,
  getOfflineRecords,
  updateOfflineAction
} from './utils/db'
import api from './api'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const pendingSyncCount = ref(0)
const isSyncing = ref(false)

const refreshPendingSyncCount = async () => {
  pendingSyncCount.value = await countOfflineActions()
}

const shouldPauseSync = (error) => {
  return !navigator.onLine || error.code === 'ECONNABORTED' || [502, 503, 504].includes(error.response?.status)
}

const syncOfflineData = async () => {
  if (!auth.isAuthenticated) return

  const records = await getOfflineRecords()
  if (records.length === 0) return

  let syncedCount = 0
  for (const record of records) {
    const formData = new FormData()
    formData.append('receipt_image', record.file)

    if (record.payload) {
      Object.entries(record.payload).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          formData.append(key, value)
        }
      })
    }

    try {
      await api.post('/records', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      await deleteOfflineRecord(record.id)
      syncedCount++
    } catch (e) {
      console.error('Ошибка офлайн-синхронизации', e)
    }
  }

  if (syncedCount > 0 && (route.path === '/history' || route.path === '/')) {
    router.go(0)
  }
}

const syncOfflineQueue = async () => {
  if (!auth.isAuthenticated || isSyncing.value) return

  const actions = await getOfflineActions()
  if (actions.length === 0) {
    pendingSyncCount.value = 0
    return
  }

  isSyncing.value = true
  let syncedCount = 0
  const idMap = {}

  for (const action of actions) {
    try {
      const payload = { ...(action.payload || {}) }
      if (payload.vehicle_id && idMap[payload.vehicle_id]) {
        payload.vehicle_id = idMap[payload.vehicle_id]
      }

      let data = payload || null
      let headers = {}

      if (action.file) {
        data = new FormData()
        data.append('receipt_image', action.file)
        data.append('skip_ocr', '1')
        Object.entries(payload).forEach(([key, value]) => {
          if (value !== null && value !== undefined && value !== '') {
            data.append(key, value)
          }
        })
        headers = { 'Content-Type': 'multipart/form-data' }
      }

      const response = await api.request({
        method: action.method,
        url: action.url,
        data,
        headers
      })
      if (action.type === 'vehicle:create' && response.data?.id) {
        idMap[`offline-${action.id}`] = response.data.id
      }
      await deleteOfflineAction(action.id)
      syncedCount++
    } catch (e) {
      await updateOfflineAction(action.id, {
        attempts: (action.attempts || 0) + 1,
        lastError: e.response?.data?.message || e.message || 'Ошибка синхронизации'
      })
      console.error('Ошибка оффлайн-синхронизации', e)
      if (shouldPauseSync(e)) {
        break
      }
    }
  }

  await refreshPendingSyncCount()
  isSyncing.value = false

  if (syncedCount > 0 && ['/', '/history', '/vehicles'].includes(route.path)) {
    router.go(0)
  }
}

onMounted(() => {
  const theme = localStorage.getItem('theme') || 'system'
  document.documentElement.dataset.theme = theme
  refreshPendingSyncCount()
  window.addEventListener('online', syncOfflineQueue)
  window.addEventListener('offline-queue-changed', refreshPendingSyncCount)
  if (navigator.onLine) syncOfflineQueue()
})

onUnmounted(() => {
  window.removeEventListener('online', syncOfflineQueue)
  window.removeEventListener('offline-queue-changed', refreshPendingSyncCount)
})

const logout = () => {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="app-shell">
    <header class="top-header" v-if="auth.isAuthenticated">
      <div>
        <p class="eyebrow">Учёт топлива</p>
        <h1>FuelTracker</h1>
        <span v-if="pendingSyncCount" class="sync-pill">
          {{ isSyncing ? 'Синхронизация...' : `${pendingSyncCount} ждёт сети` }}
        </span>
      </div>
      <button @click="logout" class="icon-button" aria-label="Выйти">
        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <path d="M16 17l5-5-5-5" />
          <path d="M21 12H9" />
        </svg>
      </button>
    </header>

    <main class="main-content" :class="{ 'auth-content': !auth.isAuthenticated }">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <nav class="bottom-nav" v-if="auth.isAuthenticated">
      <router-link to="/" class="nav-item" aria-label="Обзор">
        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
          <path d="M4 13h6V4H4v9Z" />
          <path d="M14 20h6V4h-6v16Z" />
          <path d="M4 20h6v-3H4v3Z" />
        </svg>
        <span>Обзор</span>
      </router-link>

      <router-link to="/history" class="nav-item" aria-label="История">
        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
          <path d="M4 7h16" />
          <path d="M4 12h16" />
          <path d="M4 17h10" />
        </svg>
        <span>История</span>
      </router-link>

      <router-link to="/upload" class="nav-item nav-add" aria-label="Добавить">
        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2.4" fill="none">
          <path d="M12 5v14" />
          <path d="M5 12h14" />
        </svg>
        <span>Добавить</span>
      </router-link>

      <router-link to="/vehicles" class="nav-item" aria-label="Авто">
        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
          <path d="M5 17h14" />
          <path d="M7 17l1-5h8l1 5" />
          <circle cx="8" cy="17" r="2" />
          <circle cx="16" cy="17" r="2" />
        </svg>
        <span>Авто</span>
      </router-link>
    </nav>
  </div>
</template>

<style>
:root {
  --primary: #2563eb;
  --primary-strong: #1d4ed8;
  --mint: #14b8a6;
  --amber: #f59e0b;
  --rose: #e11d48;
  --ink: #111827;
  --muted: #667085;
  --line: #e5e7eb;
  --page: #eef2f7;
  --surface: #ffffff;
  --soft: #f8fafc;
  --floating: rgba(255, 255, 255, 0.92);
  --floating-solid: rgba(255, 255, 255, 0.97);
  --card-border: rgba(229, 231, 235, 0.85);
  --control-focus: #ffffff;
  --secondary-bg: #eef2ff;
  --secondary-border: #c7d2fe;
  --success-bg: #d1fae5;
  --success-ink: #065f46;
  --warning-bg: #fef3c7;
  --warning-ink: #92400e;
  --danger-bg: #ffe4e6;
  --danger-ink: #9f1239;
  --info-bg: #e0f2fe;
  --info-ink: #075985;
  --tab-bg: #dde7f5;
  --tab-active: #ffffff;
  --upload-bg: linear-gradient(180deg, #f8fbff, #eef6ff);
  --upload-border: #9db4d6;
  --radius: 8px;
  --shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
  --nav-shadow: 0 -12px 32px rgba(15, 23, 42, 0.08);
}

:root[data-theme="dark"] {
  --ink: #f8fafc;
  --muted: #aab4c2;
  --line: #253044;
  --page: #0b1220;
  --surface: #121c2e;
  --soft: #172338;
  --floating: rgba(18, 28, 46, 0.9);
  --floating-solid: rgba(18, 28, 46, 0.97);
  --card-border: rgba(84, 101, 128, 0.38);
  --control-focus: #101a2b;
  --secondary-bg: #1f2b46;
  --secondary-border: #33446a;
  --success-bg: rgba(20, 184, 166, 0.16);
  --success-ink: #7dd3c7;
  --warning-bg: rgba(245, 158, 11, 0.16);
  --warning-ink: #fbbf24;
  --danger-bg: rgba(225, 29, 72, 0.17);
  --danger-ink: #fda4af;
  --info-bg: rgba(37, 99, 235, 0.18);
  --info-ink: #93c5fd;
  --tab-bg: #18243a;
  --tab-active: #22314d;
  --upload-bg: linear-gradient(180deg, #16233a, #101a2b);
  --upload-border: #3c5278;
  --shadow: 0 14px 35px rgba(0, 0, 0, 0.24);
  --nav-shadow: 0 -16px 34px rgba(0, 0, 0, 0.28);
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --ink: #f8fafc;
    --muted: #aab4c2;
    --line: #253044;
    --page: #0b1220;
    --surface: #121c2e;
    --soft: #172338;
    --floating: rgba(18, 28, 46, 0.9);
    --floating-solid: rgba(18, 28, 46, 0.97);
    --card-border: rgba(84, 101, 128, 0.38);
    --control-focus: #101a2b;
    --secondary-bg: #1f2b46;
    --secondary-border: #33446a;
    --success-bg: rgba(20, 184, 166, 0.16);
    --success-ink: #7dd3c7;
    --warning-bg: rgba(245, 158, 11, 0.16);
    --warning-ink: #fbbf24;
    --danger-bg: rgba(225, 29, 72, 0.17);
    --danger-ink: #fda4af;
    --info-bg: rgba(37, 99, 235, 0.18);
    --info-ink: #93c5fd;
    --tab-bg: #18243a;
    --tab-active: #22314d;
    --upload-bg: linear-gradient(180deg, #16233a, #101a2b);
    --upload-border: #3c5278;
    --shadow: 0 14px 35px rgba(0, 0, 0, 0.24);
    --nav-shadow: 0 -16px 34px rgba(0, 0, 0, 0.28);
  }
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  background: var(--page);
  color: var(--ink);
  font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  -webkit-font-smoothing: antialiased;
  -webkit-tap-highlight-color: transparent;
}

button,
input,
select {
  font: inherit;
}

button {
  cursor: pointer;
}

.app-shell {
  width: min(100%, 640px);
  min-height: 100svh;
  margin: 0 auto;
  background: var(--page);
  position: relative;
}

.top-header {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: calc(14px + env(safe-area-inset-top, 0px)) 18px 14px;
  background: var(--floating);
  border-bottom: 1px solid var(--card-border);
  backdrop-filter: blur(14px);
}

.top-header h1 {
  margin: 0;
  font-size: 22px;
  line-height: 1.05;
  letter-spacing: 0;
}

.eyebrow {
  margin: 0 0 2px;
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.sync-pill {
  display: inline-flex;
  margin-top: 6px;
  padding: 4px 8px;
  border-radius: 999px;
  color: var(--warning-ink);
  background: var(--warning-bg);
  font-size: 11px;
  font-weight: 900;
}

.icon-button {
  width: 42px;
  height: 42px;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--muted);
  display: grid;
  place-items: center;
}

.main-content {
  min-height: calc(100svh - 80px);
  padding: 18px 14px calc(96px + env(safe-area-inset-bottom, 0px));
}

.main-content.auth-content {
  min-height: 100svh;
  display: grid;
  place-items: center;
  padding: 24px 14px;
}

.bottom-nav {
  position: fixed;
  left: 50%;
  bottom: 0;
  z-index: 20;
  width: min(100%, 640px);
  transform: translateX(-50%);
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  align-items: center;
  gap: 6px;
  padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px));
  background: var(--floating-solid);
  border-top: 1px solid var(--card-border);
  box-shadow: var(--nav-shadow);
  backdrop-filter: blur(16px);
}

.nav-item {
  height: 58px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: var(--muted);
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
  border-radius: var(--radius);
  transition: background 0.14s ease, color 0.14s ease, transform 0.14s ease;
}

.nav-item.router-link-active {
  color: var(--primary);
  background: var(--secondary-bg);
}

.nav-add {
  color: var(--primary);
}

.nav-add svg {
  padding: 4px;
  border-radius: 999px;
  color: white;
  background: linear-gradient(135deg, var(--primary), var(--mint));
  box-shadow: 0 10px 22px rgba(37, 99, 235, 0.22);
}

.card,
.panel {
  background: var(--surface);
  border: 1px solid var(--card-border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
}

.card {
  padding: 18px;
}

.section-title {
  margin: 0;
  font-size: 22px;
  line-height: 1.15;
  letter-spacing: 0;
}

.section-note {
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 14px;
  line-height: 1.4;
}

.field {
  display: grid;
  gap: 7px;
}

.field label {
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
}

.field input,
.field select {
  width: 100%;
  min-height: 48px;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  background: var(--soft);
  color: var(--ink);
  padding: 11px 12px;
  outline: none;
}

.field input:focus,
.field select:focus {
  border-color: rgba(37, 99, 235, 0.7);
  background: var(--control-focus);
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.btn-primary,
.btn-secondary {
  min-height: 50px;
  border: 0;
  border-radius: var(--radius);
  padding: 0 16px;
  font-weight: 800;
}

.btn-primary {
  color: white;
  background: linear-gradient(135deg, var(--primary), var(--primary-strong));
}

.btn-secondary {
  color: var(--ink);
  background: var(--secondary-bg);
}

.btn-primary:disabled,
.btn-secondary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.14s ease, transform 0.14s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
