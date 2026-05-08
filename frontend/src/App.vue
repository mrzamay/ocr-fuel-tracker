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

onMounted(() => {
  window.addEventListener('online', syncOfflineData)
  if (navigator.onLine) syncOfflineData()
})

onUnmounted(() => {
  window.removeEventListener('online', syncOfflineData)
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

      <router-link to="/upload" class="nav-main" aria-label="Добавить">
        <span class="nav-fab">
          <svg viewBox="0 0 24 24" width="30" height="30" stroke="currentColor" stroke-width="2.4" fill="none">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>
        </span>
      </router-link>

      <router-link to="/history" class="nav-item" aria-label="История">
        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
          <path d="M4 7h16" />
          <path d="M4 12h16" />
          <path d="M4 17h10" />
        </svg>
        <span>История</span>
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
  --radius: 8px;
  --shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
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
  background: rgba(255, 255, 255, 0.92);
  border-bottom: 1px solid rgba(229, 231, 235, 0.8);
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
  grid-template-columns: 1fr 88px 1fr;
  align-items: center;
  padding: 10px 16px calc(10px + env(safe-area-inset-bottom, 0px));
  background: rgba(255, 255, 255, 0.95);
  border-top: 1px solid rgba(229, 231, 235, 0.9);
  box-shadow: 0 -12px 32px rgba(15, 23, 42, 0.08);
  backdrop-filter: blur(16px);
}

.nav-item {
  height: 54px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: var(--muted);
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
}

.nav-item.router-link-active {
  color: var(--primary);
}

.nav-main {
  display: grid;
  place-items: center;
  text-decoration: none;
}

.nav-fab {
  width: 62px;
  height: 62px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  color: white;
  background: linear-gradient(135deg, var(--primary), var(--mint));
  box-shadow: 0 15px 30px rgba(37, 99, 235, 0.28);
}

.card,
.panel {
  background: var(--surface);
  border: 1px solid rgba(229, 231, 235, 0.85);
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
  background: #fff;
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
  background: #eef2ff;
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
