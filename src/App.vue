<script setup>
import { useAuthStore } from './store/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

const logout = async () => {
  auth.logout()
  router.push('/login')
}
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
