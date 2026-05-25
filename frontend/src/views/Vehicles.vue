<script setup>
import { onMounted, ref } from 'vue'
import api from '../api'
import { deleteOfflineAction, enqueueOfflineAction, getOfflineAction, getOfflineActions, updateOfflineAction } from '../utils/db'

const vehicles = ref([])
const isLoading = ref(false)
const isPasswordLoading = ref(false)
const errorMessage = ref('')
const passwordMessage = ref('')
const passwordError = ref('')
const theme = ref(localStorage.getItem('theme') || 'system')

const form = ref({
  id: null,
  offline_action_id: null,
  name: '',
  plate_number: '',
  fuel_type: 'АИ-95',
  current_odometer_km: '',
  is_default: false
})

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: ''
})

const fuelTypes = ['АИ-92', 'АИ-95', 'АИ-98', 'АИ-100', 'ДТ', 'Газ']
const isNetworkError = (error) => !error.response || !navigator.onLine

const offlineVehicleFromAction = (action) => ({
  id: `offline-${action.id}`,
  offline_action_id: action.id,
  is_offline: true,
  ...(action.payload || {})
})

const pendingVehicles = async () => {
  return (await getOfflineActions()).filter(action => action.type === 'vehicle:create')
}

const resetForm = () => {
  form.value = {
    id: null,
    offline_action_id: null,
    name: '',
    plate_number: '',
    fuel_type: 'АИ-95',
    current_odometer_km: '',
    is_default: vehicles.value.length === 0
  }
}

const fetchVehicles = async () => {
  const pending = await pendingVehicles()
  try {
    const { data } = await api.get('/vehicles')
    vehicles.value = [
      ...pending.map(offlineVehicleFromAction),
      ...data
    ]
  } catch (error) {
    vehicles.value = pending.map(offlineVehicleFromAction)
  }
  resetForm()
}

const editVehicle = (vehicle) => {
  form.value = {
    id: vehicle.id,
    offline_action_id: vehicle.offline_action_id,
    name: vehicle.name || '',
    plate_number: vehicle.plate_number || '',
    fuel_type: vehicle.fuel_type || 'АИ-95',
    current_odometer_km: vehicle.current_odometer_km || '',
    is_default: Boolean(vehicle.is_default)
  }
}

const saveVehicle = async () => {
  isLoading.value = true
  errorMessage.value = ''

  const payload = {
    ...form.value,
    current_odometer_km: form.value.current_odometer_km || null
  }

  try {
    if (form.value.id && String(form.value.id).startsWith('offline-')) {
      const action = await getOfflineAction(form.value.offline_action_id)
      await updateOfflineAction(form.value.offline_action_id, {
        payload: { ...(action?.payload || {}), ...payload }
      })
    } else if (form.value.id) {
      await api.put(`/vehicles/${form.value.id}`, payload)
    } else {
      await api.post('/vehicles', payload)
    }
    await fetchVehicles()
  } catch (error) {
    if (isNetworkError(error)) {
      await enqueueOfflineAction({
        type: form.value.id ? 'vehicle:update' : 'vehicle:create',
        method: form.value.id ? 'put' : 'post',
        url: form.value.id ? `/vehicles/${form.value.id}` : '/vehicles',
        payload,
        label: form.value.id ? 'Редактирование авто' : 'Новое авто'
      })
      await fetchVehicles()
    } else {
      errorMessage.value = error.response?.data?.message || 'Не получилось сохранить авто.'
    }
  } finally {
    isLoading.value = false
  }
}

const deleteVehicle = async (vehicle) => {
  if (!confirm(`Удалить ${vehicle.name}?`)) return

  try {
    if (vehicle.is_offline) {
      await deleteOfflineAction(vehicle.offline_action_id)
    } else {
      await api.delete(`/vehicles/${vehicle.id}`)
    }
    await fetchVehicles()
  } catch (error) {
    if (isNetworkError(error)) {
      await enqueueOfflineAction({
        type: 'vehicle:delete',
        method: 'delete',
        url: `/vehicles/${vehicle.id}`,
        label: 'Удаление авто'
      })
      vehicles.value = vehicles.value.filter(item => item.id !== vehicle.id)
    } else {
      errorMessage.value = error.response?.data?.message || 'Не получилось удалить авто.'
    }
  }
}

const saveTheme = () => {
  localStorage.setItem('theme', theme.value)
  document.documentElement.dataset.theme = theme.value
}

const resetPasswordForm = () => {
  passwordForm.value = {
    current_password: '',
    password: '',
    password_confirmation: ''
  }
}

const changePassword = async () => {
  passwordError.value = ''
  passwordMessage.value = ''

  if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
    passwordError.value = 'Новый пароль и повтор пароля не совпадают.'
    return
  }

  isPasswordLoading.value = true

  try {
    const { data } = await api.put('/password', passwordForm.value)
    passwordMessage.value = data.message || 'Пароль успешно изменён.'
    resetPasswordForm()
  } catch (error) {
    const errors = error.response?.data?.errors
    passwordError.value = errors
      ? Object.values(errors).flat()[0]
      : (error.response?.data?.message || 'Не получилось изменить пароль.')
  } finally {
    isPasswordLoading.value = false
  }
}

onMounted(async () => {
  await fetchVehicles()
  saveTheme()
})
</script>

<template>
  <section class="vehicles-page">
    <div class="page-head">
      <h2 class="section-title">Авто и настройки</h2>
      <p class="section-note">Добавьте машины, выберите топливо по умолчанию и настройте PWA под себя.</p>
    </div>

    <div class="card vehicle-form">
      <h3>{{ form.id ? 'Редактировать авто' : 'Новое авто' }}</h3>
      <div class="field">
        <label>Название</label>
        <input v-model="form.name" type="text" placeholder="Solaris, Camry, Рабочая..." />
      </div>
      <div class="form-grid">
        <div class="field">
          <label>Госномер</label>
          <input v-model="form.plate_number" type="text" placeholder="А123ВС" />
        </div>
        <div class="field">
          <label>Топливо</label>
          <select v-model="form.fuel_type">
            <option v-for="type in fuelTypes" :key="type" :value="type">{{ type }}</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>Текущий пробег</label>
        <input v-model="form.current_odometer_km" inputmode="numeric" type="number" min="0" placeholder="84500" />
      </div>
      <label class="check-row">
        <input v-model="form.is_default" type="checkbox" />
        <span>Авто по умолчанию</span>
      </label>
      <p v-if="errorMessage" class="form-error">{{ errorMessage }}</p>
      <button class="btn-primary" :disabled="isLoading || !form.name" @click="saveVehicle">
        {{ isLoading ? 'Сохраняем...' : 'Сохранить авто' }}
      </button>
      <button v-if="form.id" class="btn-secondary" @click="resetForm">Отменить редактирование</button>
    </div>

    <div class="vehicle-list">
      <article v-for="vehicle in vehicles" :key="vehicle.id" class="vehicle-card">
        <div>
          <h3>{{ vehicle.name }}</h3>
          <p>{{ vehicle.plate_number || 'без номера' }} · {{ vehicle.fuel_type || 'топливо не указано' }}</p>
          <span v-if="vehicle.is_default" class="default-pill">по умолчанию</span>
        </div>
        <div class="vehicle-actions">
          <button @click="editVehicle(vehicle)">Изм.</button>
          <button @click="deleteVehicle(vehicle)">Удал.</button>
        </div>
      </article>
    </div>

    <div class="card settings-card">
      <h3>Настройки PWA</h3>
      <div class="field">
        <label>Тема</label>
        <select v-model="theme" @change="saveTheme">
          <option value="system">Как в системе</option>
          <option value="light">Светлая</option>
          <option value="dark">Тёмная</option>
        </select>
      </div>
      <div class="settings-grid">
        <span>Валюта</span><strong>₽</strong>
        <span>Расход</span><strong>л / 100 км</strong>
        <span>Пробег</span><strong>км</strong>
      </div>
    </div>

    <form class="card password-card" @submit.prevent="changePassword">
      <h3>Смена пароля</h3>
      <div class="field">
        <label>Текущий пароль</label>
        <input v-model="passwordForm.current_password" type="password" autocomplete="current-password" required />
      </div>
      <div class="form-grid">
        <div class="field">
          <label>Новый пароль</label>
          <input v-model="passwordForm.password" type="password" autocomplete="new-password" minlength="8" required />
        </div>
        <div class="field">
          <label>Повторите пароль</label>
          <input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" minlength="8" required />
        </div>
      </div>
      <p v-if="passwordError" class="form-error">{{ passwordError }}</p>
      <p v-if="passwordMessage" class="form-success">{{ passwordMessage }}</p>
      <button class="btn-primary" type="submit" :disabled="isPasswordLoading">
        {{ isPasswordLoading ? 'Меняем пароль...' : 'Изменить пароль' }}
      </button>
    </form>
  </section>
</template>

<style scoped>
.vehicles-page {
  display: grid;
  gap: 16px;
}

.vehicle-form,
.settings-card,
.password-card {
  display: grid;
  gap: 14px;
}

.vehicle-form h3,
.settings-card h3,
.password-card h3,
.vehicle-card h3 {
  margin: 0;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.check-row {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 800;
}

.check-row input {
  width: 20px;
  height: 20px;
}

.vehicle-list {
  display: grid;
  gap: 10px;
}

.vehicle-card {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 16px;
  border-radius: var(--radius);
  background: var(--surface);
  border: 1px solid var(--line);
  box-shadow: var(--shadow);
}

.vehicle-card p {
  margin: 4px 0;
  color: var(--muted);
  font-size: 13px;
}

.default-pill {
  display: inline-flex;
  padding: 4px 9px;
  border-radius: 999px;
  color: var(--success-ink);
  background: var(--success-bg);
  font-size: 12px;
  font-weight: 800;
}

.vehicle-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.vehicle-actions button {
  min-width: 58px;
  min-height: 34px;
  border: 1px solid var(--secondary-border);
  border-radius: var(--radius);
  color: var(--primary);
  background: var(--secondary-bg);
  font-weight: 800;
}

.settings-grid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 10px;
  color: var(--muted);
}

.settings-grid strong {
  color: var(--ink);
}

.form-error {
  margin: 0;
  padding: 12px;
  border-radius: var(--radius);
  color: var(--danger-ink);
  background: var(--danger-bg);
  font-weight: 700;
}

.form-success {
  margin: 0;
  padding: 12px;
  border-radius: var(--radius);
  color: var(--success-ink);
  background: var(--success-bg);
  font-weight: 700;
}
</style>
