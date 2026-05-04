<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useAuthStore } from './store/auth'
import { useRouter } from 'vue-router'
import { getOfflineRecords, deleteOfflineRecord } from './utils/db'
import api from './api'

const auth = useAuthStore()
const router = useRouter()

const logout = async () => {
  auth.logout()
  router.push('/login')
}

// Функция синхронизации
const syncOfflineData = async () => {
  if (!auth.isAuthenticated) return

  const records = await getOfflineRecords()
  if (records.length === 0) return

  console.log('Начало синхронизации оффлайн записей...')
  let syncedCount = 0

  for (const record of records) {
    const formData = new FormData()
    formData.append('receipt_image', record.file)

    try {
      await api.post('/records', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      // Если успешно загрузилось, удаляем из локальной БД
      await deleteOfflineRecord(record.id)
      syncedCount++
    } catch (e) {
      console.error('Ошибка синхронизации записи', record.id, e)
    }
  }

  if (syncedCount > 0) {
    alert(`Синхронизация завершена: ${syncedCount} чек(ов) отправлено на сервер.`)
    // Перезагружаем текущую страницу, чтобы обновить историю
    router.go(0) 
  }
}

onMounted(() => {
  // Слушаем появление интернета
  window.addEventListener('online', syncOfflineData)
  
  // Пытаемся синхронизировать при загрузке приложения, если сеть уже есть
  if (navigator.onLine) {
    syncOfflineData()
  }
})

onUnmounted(() => {
  window.removeEventListener('online', syncOfflineData)
})
</script>

<template>
  <div class="app-container">
    <nav v-if="auth.isAuthenticated" class="navbar">
      <router-link to="/">Дашборд</router-link>
      <router-link to="/history">История</router-link>
      <router-link to="/upload">Скан чека</router-link>
      <button @click="logout" class="logout-btn">Выход</button>
    </nav>
    <main class="content">
      <router-view></router-view>
    </main>
  </div>
</template>

<style>
/* Базовые стили */
body { margin: 0; font-family: Arial, sans-serif; background: #f4f4f9; }
.app-container { max-width: 800px; margin: 0 auto; padding: 20px; }
.navbar { display: flex; gap: 15px; padding: 15px; background: #fff; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.navbar a { text-decoration: none; color: #333; font-weight: bold; }
.navbar a.router-link-active { color: #4CAF50; }
.logout-btn { margin-left: auto; background: #ff4c4c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
.card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
input, button { padding: 10px; margin-bottom: 10px; width: 100%; box-sizing: border-box; }
button { background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
</style>
