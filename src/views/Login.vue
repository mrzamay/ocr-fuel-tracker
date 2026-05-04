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

const submit = async () => {
  try {
    error.value = ''
    if (isLogin.value) {
      await auth.login(email.value, password.value)
    } else {
      await auth.register(name.value, email.value, password.value)
    }
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Ошибка авторизации'
  }
}
</script>

<template>
  <div class="card">
    <h2>{{ isLogin ? 'Вход' : 'Регистрация' }}</h2>
    <form @submit.prevent="submit">
      <input v-if="!isLogin" v-model="name" type="text" placeholder="Имя" required />
      <input v-model="email" type="email" placeholder="Email" required />
      <input v-model="password" type="password" placeholder="Пароль" required />
      <p v-if="error" style="color: red">{{ error }}</p>
      <button type="submit">{{ isLogin ? 'Войти' : 'Зарегистрироваться' }}</button>
    </form>
    <a href="#" @click.prevent="isLogin = !isLogin">
      {{ isLogin ? 'Нет аккаунта? Зарегистрируйтесь' : 'Уже есть аккаунт? Войти' }}
    </a>
  </div>
</template>
