import { defineStore } from 'pinia'
import api from '../api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null
  }),
  getters: {
    isAuthenticated: (state) => !!state.token
  },
  actions: {
    async login(email, password) {
      // Здесь ТОЛЬКО api.post, никакого csrf
      const response = await api.post('/login', { email, password });
      this.token = response.data.access_token;
      this.user = response.data.user;
      localStorage.setItem('token', this.token);
    },
    async register(name, email, password) {
      // Здесь тоже ТОЛЬКО api.post
      const response = await api.post('/register', { name, email, password });
      this.token = response.data.access_token;
      this.user = response.data.user;
      localStorage.setItem('token', this.token);
    },
    logout() {
      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
    }
  }
})
