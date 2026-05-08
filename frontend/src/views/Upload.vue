<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'
import { useRouter } from 'vue-router'
import { saveOfflineRecord } from '../utils/db'

const router = useRouter()
const activeTab = ref('scan')
const file = ref(null)
const isLoading = ref(false)
const ocrResult = ref(null)
const ocrInfo = ref(null)
const errorMessage = ref('')
const stationOptions = ref([])
const vehicles = ref([])
const locationState = ref({
  latitude: '',
  longitude: '',
  accuracy: '',
  label: ''
})

const formData = ref({
  vehicle_id: '',
  amount: '',
  volume: '',
  unit_price: '',
  is_full_tank: true,
  odometer_km: '',
  station_name: '',
  fuel_type: 'АИ-95',
  date: new Date().toISOString().split('T')[0]
})

const fuelTypes = ['АИ-92', 'АИ-95', 'АИ-98', 'АИ-100', 'ДТ', 'Газ']

const fileSizeMb = computed(() => file.value ? (file.value.size / 1024 / 1024).toFixed(1) : null)

const fetchStationOptions = async () => {
  try {
    const [{ data: meta }, { data: vehicleData }] = await Promise.all([
      api.get('/records/meta'),
      api.get('/vehicles')
    ])
    stationOptions.value = meta.stations || []
    vehicles.value = vehicleData
    const defaultVehicle = vehicleData.find(vehicle => vehicle.is_default) || vehicleData[0]
    if (defaultVehicle && !formData.value.vehicle_id) {
      formData.value.vehicle_id = defaultVehicle.id
      formData.value.fuel_type = defaultVehicle.fuel_type || formData.value.fuel_type
      formData.value.odometer_km = defaultVehicle.current_odometer_km || formData.value.odometer_km
    }
  } catch (error) {
    stationOptions.value = []
    vehicles.value = []
  }
}

const payload = () => ({
  vehicle_id: formData.value.vehicle_id,
  amount: formData.value.amount,
  volume: formData.value.volume,
  unit_price: formData.value.unit_price,
  is_full_tank: formData.value.is_full_tank ? 1 : 0,
  odometer_km: formData.value.odometer_km,
  station_name: formData.value.station_name,
  fuel_type: formData.value.fuel_type,
  date: formData.value.date,
  latitude: locationState.value.latitude,
  longitude: locationState.value.longitude,
  location_accuracy: locationState.value.accuracy
})

const appendPayload = (fd) => {
  Object.entries(payload()).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      fd.append(key, value)
    }
  })
}

const handleFile = (e) => {
  errorMessage.value = ''
  file.value = e.target.files?.[0] || null
}

const fillFromVehicle = () => {
  const vehicle = vehicles.value.find(item => item.id === Number(formData.value.vehicle_id))
  if (!vehicle) return
  formData.value.fuel_type = vehicle.fuel_type || formData.value.fuel_type
  formData.value.odometer_km = vehicle.current_odometer_km || formData.value.odometer_km
}

const captureLocation = () => {
  if (!navigator.geolocation) {
    locationState.value.label = 'Геолокация недоступна'
    return
  }

  locationState.value.label = 'Определяем место...'
  navigator.geolocation.getCurrentPosition(
    (position) => {
      locationState.value = {
        latitude: position.coords.latitude.toFixed(7),
        longitude: position.coords.longitude.toFixed(7),
        accuracy: `${Math.round(position.coords.accuracy)} м`,
        label: `Место сохранено, точность ${Math.round(position.coords.accuracy)} м`
      }
    },
    () => {
      locationState.value.label = 'Не удалось получить геопозицию'
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
  )
}

const uploadReceipt = async () => {
  if (!file.value) return

  isLoading.value = true
  errorMessage.value = ''

  if (!navigator.onLine) {
    try {
      await saveOfflineRecord(file.value, payload())
      router.push('/history')
    } catch (e) {
      errorMessage.value = 'Не получилось сохранить чек офлайн.'
    } finally {
      isLoading.value = false
      file.value = null
    }
    return
  }

  const fd = new FormData()
  fd.append('receipt_image', file.value)
  appendPayload(fd)

  try {
    const { data } = await api.post('/records', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    ocrResult.value = data.data
    ocrInfo.value = {
      ...(data.ocr || {}),
      extracted: data.ocr_extracted || {}
    }
    formData.value.amount = ocrResult.value.amount || formData.value.amount
    formData.value.volume = ocrResult.value.volume || formData.value.volume
    formData.value.unit_price = ocrResult.value.unit_price || data.ocr_extracted?.unit_price || formData.value.unit_price
    formData.value.odometer_km = ocrResult.value.odometer_km || formData.value.odometer_km
    formData.value.station_name = ocrResult.value.station_name || data.ocr_extracted?.station_name || formData.value.station_name
    formData.value.fuel_type = ocrResult.value.fuel_type || data.ocr_extracted?.fuel_type || formData.value.fuel_type
    formData.value.date = ocrResult.value.date || formData.value.date
    activeTab.value = 'manual'
  } catch (error) {
    if (error.response?.status === 413) {
      errorMessage.value = 'Фото слишком большое для сервера. После обновления лимит будет 30 МБ.'
    } else if (error.response?.status === 422) {
      errorMessage.value = 'Фото не принято сервером. На iPhone попробуйте формат JPEG/PNG или фото до 30 МБ.'
    } else {
      errorMessage.value = 'Не получилось загрузить чек. Попробуйте ещё раз.'
    }
  } finally {
    isLoading.value = false
  }
}

const saveCorrection = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    await api.put(`/records/${ocrResult.value.id}`, {
      ...payload(),
      status: 'success'
    })
    router.push('/history')
  } catch (error) {
    errorMessage.value = 'Не получилось сохранить данные.'
  } finally {
    isLoading.value = false
  }
}

const submitManual = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    await api.post('/records', payload())
    router.push('/history')
  } catch (error) {
    errorMessage.value = 'Не получилось добавить заправку.'
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchStationOptions)
</script>

<template>
  <section class="upload-page">
    <div class="page-head">
      <h2 class="section-title">Новая заправка</h2>
      <p class="section-note">Добавьте чек или внесите данные вручную. Пробег нужен для расчёта расхода.</p>
    </div>

    <div class="tabs" v-if="!ocrResult">
      <button :class="{ active: activeTab === 'scan' }" @click="activeTab = 'scan'">Чек</button>
      <button :class="{ active: activeTab === 'manual' }" @click="activeTab = 'manual'">Вручную</button>
    </div>

    <div v-if="activeTab === 'scan' && !ocrResult" class="card scan-card">
      <label for="receipt" class="file-target">
        <input
          id="receipt"
          class="file-input"
          type="file"
          accept="image/*,.heic,.heif"
          capture="environment"
          @change="handleFile"
        />
        <span class="file-icon">
          <svg viewBox="0 0 24 24" width="30" height="30" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M4 7h3l2-3h6l2 3h3v13H4V7Z" />
            <circle cx="12" cy="13" r="4" />
          </svg>
        </span>
        <span class="file-title">{{ file ? file.name : 'Сделать фото чека' }}</span>
        <span class="file-note">{{ file ? `${fileSizeMb} МБ` : 'JPEG, PNG, WebP, HEIC до 15 МБ' }}</span>
      </label>

      <div class="quick-grid">
        <div class="field">
          <label>Авто</label>
          <select v-model="formData.vehicle_id" @change="fillFromVehicle">
            <option value="">Моё авто</option>
            <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
              {{ vehicle.name }}
            </option>
          </select>
        </div>
        <div class="field">
          <label>Пробег, км</label>
          <input v-model="formData.odometer_km" inputmode="numeric" type="number" min="0" placeholder="84500" />
        </div>
      </div>

      <button class="btn-secondary" type="button" @click="captureLocation">
        {{ locationState.label || 'Сохранить геопозицию АЗС' }}
      </button>

      <div class="quick-grid">
        <div class="field">
          <label>Дата</label>
          <input v-model="formData.date" type="date" />
        </div>
        <label class="check-card">
          <input v-model="formData.is_full_tank" type="checkbox" />
          <span>Полный бак</span>
        </label>
      </div>

      <button class="btn-primary" @click="uploadReceipt" :disabled="!file || isLoading">
        {{ isLoading ? 'Распознаём...' : 'Распознать чек' }}
      </button>
    </div>

    <form v-if="activeTab === 'manual' || ocrResult" class="card form-card" @submit.prevent="ocrResult ? saveCorrection() : submitManual()">
      <div>
        <h3>{{ ocrResult ? 'Проверьте данные' : 'Данные заправки' }}</h3>
        <p>{{ ocrResult ? 'OCR мог ошибиться, поправьте значения перед сохранением.' : 'Можно заполнить без фото, если чека нет под рукой.' }}</p>
      </div>

      <div class="form-grid">
        <div class="field">
          <label>Авто</label>
          <select v-model="formData.vehicle_id" @change="fillFromVehicle">
            <option value="">Моё авто</option>
            <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
              {{ vehicle.name }}
            </option>
          </select>
        </div>
        <div class="field">
          <label>Сумма, ₽</label>
          <input v-model="formData.amount" inputmode="decimal" type="number" step="0.01" min="0" placeholder="3200" />
        </div>
        <div class="field">
          <label>Литры</label>
          <input v-model="formData.volume" inputmode="decimal" type="number" step="0.01" min="0" placeholder="45.2" />
        </div>
        <div class="field">
          <label>Цена литра</label>
          <input v-model="formData.unit_price" inputmode="decimal" type="number" step="0.01" min="0" placeholder="62.50" />
        </div>
        <div class="field">
          <label>Пробег, км</label>
          <input v-model="formData.odometer_km" inputmode="numeric" type="number" min="0" placeholder="84500" />
        </div>
        <div class="field">
          <label>Дата</label>
          <input v-model="formData.date" type="date" />
        </div>
      </div>

      <div class="field">
        <label>Топливо</label>
        <select v-model="formData.fuel_type">
          <option v-for="ft in fuelTypes" :key="ft" :value="ft">{{ ft }}</option>
        </select>
      </div>

      <label class="check-card">
        <input v-model="formData.is_full_tank" type="checkbox" />
        <span>Заправка до полного бака</span>
      </label>

      <button class="btn-secondary" type="button" @click="captureLocation">
        {{ locationState.label || 'Сохранить геопозицию АЗС' }}
      </button>

      <div v-if="ocrInfo" class="ocr-hint">
        <strong>OCR: {{ ocrInfo.extracted?.confidence || 0 }}%</strong>
        <span>Проверьте подсвеченные поля перед сохранением.</span>
      </div>

      <div class="field">
        <label>АЗС</label>
        <input
          v-model="formData.station_name"
          type="text"
          list="station-options"
          autocomplete="off"
          placeholder="Лукойл, Газпромнефть..."
        />
        <datalist id="station-options">
          <option v-for="station in stationOptions" :key="station" :value="station" />
        </datalist>
        <div v-if="stationOptions.length" class="station-chips">
          <button
            v-for="station in stationOptions.slice(0, 6)"
            :key="station"
            type="button"
            @click="formData.station_name = station"
          >
            {{ station }}
          </button>
        </div>
      </div>

      <p v-if="errorMessage" class="form-error">{{ errorMessage }}</p>

      <button class="btn-primary" type="submit" :disabled="isLoading">
        {{ isLoading ? 'Сохраняем...' : (ocrResult ? 'Сохранить исправления' : 'Добавить заправку') }}
      </button>
      <button v-if="ocrResult" class="btn-secondary" type="button" @click="ocrResult = null">
        Сканировать другой чек
      </button>
    </form>

    <p v-if="errorMessage && activeTab === 'scan' && !ocrResult" class="form-error">{{ errorMessage }}</p>
  </section>
</template>

<style scoped>
.upload-page {
  display: grid;
  gap: 16px;
}

.page-head {
  padding: 2px 4px 0;
}

.tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px;
  padding: 4px;
  background: var(--tab-bg);
  border-radius: var(--radius);
}

.tabs button {
  min-height: 42px;
  border: 0;
  border-radius: var(--radius);
  color: var(--muted);
  background: transparent;
  font-weight: 800;
}

.tabs button.active {
  color: var(--primary);
  background: var(--tab-active);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
}

.scan-card,
.form-card {
  display: grid;
  gap: 16px;
}

.file-target {
  min-height: 210px;
  border: 1.5px dashed var(--upload-border);
  border-radius: var(--radius);
  background: var(--upload-bg);
  display: grid;
  place-items: center;
  align-content: center;
  gap: 8px;
  padding: 22px;
  text-align: center;
}

.file-input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}

.file-icon {
  width: 64px;
  height: 64px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  color: var(--primary);
  background: var(--info-bg);
}

.file-title {
  max-width: 100%;
  color: var(--ink);
  font-weight: 900;
  overflow-wrap: anywhere;
}

.file-note {
  color: var(--muted);
  font-size: 13px;
}

.quick-grid,
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-card h3 {
  margin: 0;
  font-size: 18px;
}

.form-card p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.4;
}

.form-error {
  margin: 0;
  padding: 12px;
  border-radius: var(--radius);
  color: var(--danger-ink);
  background: var(--danger-bg);
  font-size: 14px;
  font-weight: 700;
}

.check-card {
  min-height: 48px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 12px;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  background: var(--soft);
  color: var(--ink);
  font-weight: 800;
}

.check-card input {
  width: 20px;
  height: 20px;
}

.ocr-hint {
  display: grid;
  gap: 4px;
  padding: 12px;
  border-radius: var(--radius);
  color: var(--info-ink);
  background: var(--info-bg);
}

.ocr-hint span {
  font-size: 13px;
}

.station-chips {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 2px 0 4px;
}

.station-chips button {
  flex: 0 0 auto;
  min-height: 34px;
  border: 1px solid var(--secondary-border);
  border-radius: 999px;
  padding: 0 12px;
  color: var(--primary);
  background: var(--secondary-bg);
  font-size: 13px;
  font-weight: 800;
}
</style>
