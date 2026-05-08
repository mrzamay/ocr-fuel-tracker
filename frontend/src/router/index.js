import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../store/auth'

const routes = [
  { 
    path: '/', 
    component: () => import('../views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  { 
    path: '/login', 
    component: () => import('../views/Login.vue'),
    meta: { requiresAuth: false }
  },
  { 
    path: '/history', 
    component: () => import('../views/History.vue'),
    meta: { requiresAuth: true }
  },
  { 
    path: '/upload', 
    component: () => import('../views/Upload.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/vehicles',
    component: () => import('../views/Vehicles.vue'),
    meta: { requiresAuth: true }
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation Guard
router.beforeEach((to, from, next) => {
  const auth = useAuthStore()
  
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // Если страница требует авторизации, а ее нет - идем на логин
    next('/login')
  } else if (to.path === '/login' && auth.isAuthenticated) {
    // Если уже авторизован и идет на логин - кидаем на дашборд
    next('/')
  } else {
    next()
  }
})

export default router
