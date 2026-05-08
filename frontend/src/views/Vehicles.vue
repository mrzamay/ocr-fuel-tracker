<script setup>
import { onMounted, ref } from 'vue'
import api from '../api'

const vehicles = ref([])
const isLoading = ref(false)
const errorMessage = ref('')
const theme = ref(localStorage.getItem('theme') || 'system')

const form = ref({
  id: null,
  name: '',
  plate_number: '',
  fuel_type: 'АИ-95',
  current_odometer_km: '',
  is_default: false
})

const fuelTypes = ['АИ-92', 'АИ-95', 'АИ-98', 'АИ-100', 'ДТ', 'Газ']

const resetForm = () => {
  form.value = {
    id: null,
    name: '',
    plate_number: '',
    fuel_type: 'АИ-95',
    current_odometer_km: '',
    is_default: vehicles.value.length === 0
  }
}

const fetchVehicles = async () => {
  const { data } = await api.get('/vehicles')
  vehicles.value = data
  resetForm()
}

const editVehicle = (vehicle) => {
  form.value = {
    id: vehicle.id,
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
    if (form.value.id) {
      await api.put(`/vehicles/${form.value.id}`, payload)
    } else {
      await api.post('/vehicles', payload)
    }
    await fetchVehicles()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Не получилось сохранить авто.'
  } finally {
    isLoading.value = false
  }
}

const deleteVehicle = async (vehicle) => {
  if (!confirm(`Удалить ${vehicle.name}?`)) return

  try {
    await api.delete(`/vehicles/${vehicle.id}`)
    await fetchVehicles()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Не получилось удалить авто.'
  }
}

const saveTheme = () => {
  localStorage.setItem('theme', theme.value)
  document.documentElement.dataset.theme = theme.value
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
  </section>
</template>

<style scoped>
.vehicles-page {
  display: grid;
  gap: 16px;
}

.vehicle-form,
.settings-card {
  display: grid;
  gap: 14px;
}

.vehicle-form h3,
.settings-card h3,
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
  color: #065f46;
  background: #d1fae5;
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
  border: 1px solid #c7d2fe;
  border-radius: var(--radius);
  color: var(--primary);
  background: #eef2ff;
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
  color: #9f1239;
  background: #ffe4e6;
  font-weight: 700;
}
</style>
