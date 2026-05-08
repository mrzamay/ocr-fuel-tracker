<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../store/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

const isLogin = ref(true)
const name = ref('')
const email = ref('')
const password = ref('')
const error = ref('')
const isLoading = ref(false)

const submit = async () => {
  isLoading.value = true
  error.value = ''

  try {
    if (isLogin.value) {
      await auth.login(email.value, password.value)
    } else {
      await auth.register(name.value, email.value, password.value)
    }
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Ошибка авторизации'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <section class="auth-page">
    <div class="brand-panel">
      <span class="brand-mark">
        <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2" fill="none">
          <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16" />
          <path d="M8 7h4" />
          <path d="M8 11h4" />
          <path d="M16 8h2l2 3v7a2 2 0 0 1-2 2h-2" />
        </svg>
      </span>
      <h1>FuelTracker</h1>
      <p>Заправки, пробег и расход топлива в одном аккуратном PWA.</p>
    </div>

    <form class="auth-card" @submit.prevent="submit">
      <div>
        <h2>{{ isLogin ? 'Вход' : 'Регистрация' }}</h2>
        <p>{{ isLogin ? 'Продолжим вести историю заправок.' : 'Создайте аккаунт для личной истории.' }}</p>
      </div>

      <div v-if="!isLogin" class="field">
        <label>Имя</label>
        <input v-model="name" type="text" autocomplete="name" required />
      </div>

      <div class="field">
        <label>Email</label>
        <input v-model="email" type="email" inputmode="email" autocomplete="email" required />
      </div>

      <div class="field">
        <label>Пароль</label>
        <input v-model="password" type="password" autocomplete="current-password" required />
      </div>

      <p v-if="error" class="auth-error">{{ error }}</p>

      <button class="btn-primary" type="submit" :disabled="isLoading">
        {{ isLoading ? 'Проверяем...' : (isLogin ? 'Войти' : 'Создать аккаунт') }}
      </button>

      <button class="switch-button" type="button" @click="isLogin = !isLogin">
        {{ isLogin ? 'Нет аккаунта? Зарегистрироваться' : 'Уже есть аккаунт? Войти' }}
      </button>
    </form>
  </section>
</template>

<style scoped>
.auth-page {
  width: 100%;
  display: grid;
  gap: 18px;
}

.brand-panel {
  padding: 20px 8px 0;
}

.brand-mark {
  width: 58px;
  height: 58px;
  border-radius: 18px;
  display: grid;
  place-items: center;
  color: #fff;
  background: linear-gradient(135deg, var(--primary), var(--mint));
  box-shadow: 0 16px 32px rgba(37, 99, 235, 0.24);
}

.brand-panel h1 {
  margin: 16px 0 8px;
  font-size: 36px;
  line-height: 1;
  letter-spacing: 0;
}

.brand-panel p {
  margin: 0;
  max-width: 320px;
  color: var(--muted);
  line-height: 1.45;
}

.auth-card {
  padding: 18px;
  border-radius: var(--radius);
  background: var(--surface);
  border: 1px solid var(--line);
  box-shadow: var(--shadow);
  display: grid;
  gap: 14px;
}

.auth-card h2 {
  margin: 0;
  font-size: 22px;
}

.auth-card p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 14px;
}

.auth-error {
  padding: 12px;
  border-radius: var(--radius);
  color: #9f1239;
  background: #ffe4e6;
  font-weight: 700;
}

.switch-button {
  min-height: 42px;
  border: 0;
  color: var(--primary);
  background: transparent;
  font-weight: 800;
}
</style>
