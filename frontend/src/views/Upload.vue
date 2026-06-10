<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import api from '../api'
import { useRouter } from 'vue-router'
import { enqueueOfflineAction, saveOfflineRecord } from '../utils/db'

const router = useRouter()
const activeTab = ref('scan')
const file = ref(null)
const receiptPreviewUrl = ref('')
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
const receiptImageUrl = computed(() => receiptPreviewUrl.value || ocrResult.value?.receipt_image_url || '')

const extractedFields = [
  { key: 'amount', label: 'Сумма', suffix: ' ₽' },
  { key: 'volume', label: 'Литры', suffix: ' л' },
  { key: 'unit_price', label: 'Цена литра', suffix: ' ₽/л' },
  { key: 'station_name', label: 'АЗС', suffix: '' },
  { key: 'fuel_type', label: 'Топливо', suffix: '' }
]

const extractedItems = computed(() => {
  const extracted = ocrInfo.value?.extracted || {}
  return extractedFields
    .filter(field => extracted[field.key] !== null && extracted[field.key] !== undefined && extracted[field.key] !== '')
    .map(field => ({
      ...field,
      value: extracted[field.key],
      source: extracted.sources?.[field.key] || ''
    }))
})

const wasExtracted = (field) => {
  const extracted = ocrInfo.value?.extracted || {}
  return extracted[field] !== null && extracted[field] !== undefined && extracted[field] !== ''
}

const revokeReceiptPreview = () => {
  if (receiptPreviewUrl.value) {
    URL.revokeObjectURL(receiptPreviewUrl.value)
    receiptPreviewUrl.value = ''
  }
}

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

const isNetworkError = (error) => !error.response || !navigator.onLine
const isTimeoutError = (error) => error.code === 'ECONNABORTED' || error.response?.status === 504

const compressReceiptImage = async (selectedFile) => {
  if (!selectedFile?.type?.startsWith('image/')) {
    return selectedFile
  }

  const imageUrl = URL.createObjectURL(selectedFile)

  try {
    const image = await new Promise((resolve, reject) => {
      const img = new Image()
      img.onload = () => resolve(img)
      img.onerror = reject
      img.src = imageUrl
    })

    const maxSide = 1800
    const scale = Math.min(maxSide / Math.max(image.width, image.height), 1)

    if (scale === 1 && selectedFile.size <= 2.5 * 1024 * 1024) {
      return selectedFile
    }

    const canvas = document.createElement('canvas')
    canvas.width = Math.round(image.width * scale)
    canvas.height = Math.round(image.height * scale)

    const ctx = canvas.getContext('2d')
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height)

    const blob = await new Promise((resolve) => {
      canvas.toBlob(resolve, 'image/jpeg', 0.82)
    })

    if (!blob) {
      return selectedFile
    }

    const normalizedName = selectedFile.name.replace(/\.[^.]+$/, '') || 'receipt'
    return new File([blob], `${normalizedName}.jpg`, { type: 'image/jpeg' })
  } catch (error) {
    return selectedFile
  } finally {
    URL.revokeObjectURL(imageUrl)
  }
}

const handleFile = async (e) => {
  errorMessage.value = ''
  revokeReceiptPreview()
  const selectedFile = e.target.files?.[0] || null
  file.value = selectedFile ? await compressReceiptImage(selectedFile) : null
  if (file.value) {
    receiptPreviewUrl.value = URL.createObjectURL(file.value)
  }
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
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 40000
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
    if (isTimeoutError(error)) {
      errorMessage.value = '\u0420\u0430\u0441\u043f\u043e\u0437\u043d\u0430\u0432\u0430\u043d\u0438\u0435 \u0437\u0430\u043d\u044f\u043b\u043e \u0441\u043b\u0438\u0448\u043a\u043e\u043c \u043c\u043d\u043e\u0433\u043e \u0432\u0440\u0435\u043c\u0435\u043d\u0438. \u041f\u043e\u043f\u0440\u043e\u0431\u0443\u0439\u0442\u0435 \u0444\u043e\u0442\u043e \u043a\u0440\u0443\u043f\u043d\u0435\u0435/\u0441\u0432\u0435\u0442\u043b\u0435\u0435 \u0438\u043b\u0438 \u0434\u043e\u0431\u0430\u0432\u044c\u0442\u0435 \u0437\u0430\u043f\u0440\u0430\u0432\u043a\u0443 \u0432\u0440\u0443\u0447\u043d\u0443\u044e.'
    } else if (isNetworkError(error)) {
      try {
        await saveOfflineRecord(file.value, payload())
        router.push('/history')
      } catch (e) {
        errorMessage.value = 'Не получилось сохранить чек офлайн.'
      }
    } else if (error.response?.status === 413) {
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
    if (isNetworkError(error)) {
      await enqueueOfflineAction({
        type: 'record:update',
        method: 'put',
        url: `/records/${ocrResult.value.id}`,
        payload: {
          ...payload(),
          status: 'success'
        },
        label: 'Исправление заправки'
      })
      router.push('/history')
    } else {
      errorMessage.value = 'Не получилось сохранить данные.'
    }
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
    if (isNetworkError(error)) {
      await enqueueOfflineAction({
        type: 'record:create',
        method: 'post',
        url: '/records',
        payload: payload(),
        label: 'Новая заправка вручную'
      })
      router.push('/history')
    } else {
      errorMessage.value = 'Не получилось добавить заправку.'
    }
  } finally {
    isLoading.value = false
  }
}

const resetScan = () => {
  ocrResult.value = null
  ocrInfo.value = null
  file.value = null
  activeTab.value = 'scan'
  revokeReceiptPreview()
}

onMounted(fetchStationOptions)
onUnmounted(revokeReceiptPreview)
</script>

<template>
  <section class="upload-page">
    <div class="page-head">
      <h2 class="section-title">Новая заправка</h2>
      <p class="section-note">Добавьте чек или внесите данные вручную. Указывайте пробег именно на момент этой заправки.</p>
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
          <label>Пробег на заправке, км</label>
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
        <p>{{ ocrResult ? 'OCR мог уверенно ошибиться. Сверьте подсвеченные поля с фото чека перед сохранением.' : 'Можно заполнить без фото, если чека нет под рукой.' }}</p>
      </div>

      <div v-if="ocrResult" class="ocr-review">
        <div class="receipt-preview">
          <img v-if="receiptImageUrl" :src="receiptImageUrl" alt="Фото чека для сверки" />
          <div v-else class="receipt-placeholder">Фото чека недоступно</div>
        </div>

        <div class="ocr-summary">
          <div class="ocr-hint">
            <strong>OCR: {{ ocrInfo?.extracted?.confidence || 0 }}%</strong>
            <span>Процент показывает, что поля были найдены, а не что они точно верные.</span>
          </div>
          <div v-if="extractedItems.length" class="extracted-grid">
            <article v-for="item in extractedItems" :key="item.key">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}{{ item.suffix }}</strong>
              <em v-if="item.source">{{ item.source }}</em>
            </article>
          </div>
        </div>
      </div>

      <div class="form-grid">
        <div class="field" :class="{ 'ocr-field': wasExtracted('vehicle_id') }">
          <label>Авто <span v-if="wasExtracted('vehicle_id')">OCR</span></label>
          <select v-model="formData.vehicle_id" @change="fillFromVehicle">
            <option value="">Моё авто</option>
            <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
              {{ vehicle.name }}
            </option>
          </select>
        </div>
        <div class="field" :class="{ 'ocr-field': wasExtracted('amount') }">
          <label>Сумма, ₽ <span v-if="wasExtracted('amount')">OCR</span></label>
          <input v-model="formData.amount" inputmode="decimal" type="number" step="0.01" min="0" placeholder="3200" />
        </div>
        <div class="field" :class="{ 'ocr-field': wasExtracted('volume') }">
          <label>Литры <span v-if="wasExtracted('volume')">OCR</span></label>
          <input v-model="formData.volume" inputmode="decimal" type="number" step="0.01" min="0" placeholder="45.2" />
        </div>
        <div class="field" :class="{ 'ocr-field': wasExtracted('unit_price') }">
          <label>Цена литра <span v-if="wasExtracted('unit_price')">OCR</span></label>
          <input v-model="formData.unit_price" inputmode="decimal" type="number" step="0.01" min="0" placeholder="62.50" />
        </div>
        <div class="field" :class="{ 'ocr-field': wasExtracted('odometer_km') }">
          <label>Пробег на заправке, км <span v-if="wasExtracted('odometer_km')">OCR</span></label>
          <input v-model="formData.odometer_km" inputmode="numeric" type="number" min="0" placeholder="84500" />
        </div>
        <div class="field" :class="{ 'ocr-field': wasExtracted('date') }">
          <label>Дата <span v-if="wasExtracted('date')">OCR</span></label>
          <input v-model="formData.date" type="date" />
        </div>
      </div>

      <div class="field" :class="{ 'ocr-field': wasExtracted('fuel_type') }">
        <label>Топливо <span v-if="wasExtracted('fuel_type')">OCR</span></label>
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

      <div class="field" :class="{ 'ocr-field': wasExtracted('station_name') }">
        <label>АЗС <span v-if="wasExtracted('station_name')">OCR</span></label>
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
      <button v-if="ocrResult" class="btn-secondary" type="button" @click="resetScan">
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

.ocr-review {
  display: grid;
  gap: 12px;
}

.receipt-preview {
  max-height: 360px;
  border: 1px solid var(--card-border);
  border-radius: var(--radius);
  background: var(--soft);
  overflow: auto;
}

.receipt-preview img {
  display: block;
  width: 100%;
  height: auto;
}

.receipt-placeholder {
  min-height: 180px;
  display: grid;
  place-items: center;
  color: var(--muted);
  font-weight: 800;
}

.ocr-summary {
  display: grid;
  gap: 10px;
}

.extracted-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.extracted-grid article {
  display: grid;
  gap: 3px;
  padding: 10px;
  border: 1px solid rgba(245, 158, 11, 0.35);
  border-radius: var(--radius);
  background: var(--warning-bg);
  color: var(--warning-ink);
}

.extracted-grid span {
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}

.extracted-grid strong {
  color: var(--ink);
  overflow-wrap: anywhere;
}

.extracted-grid em {
  color: var(--muted);
  font-size: 11px;
  font-style: normal;
  line-height: 1.25;
  overflow-wrap: anywhere;
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

.field label span {
  margin-left: 6px;
  padding: 2px 6px;
  border-radius: 999px;
  color: var(--warning-ink);
  background: var(--warning-bg);
  font-size: 10px;
  font-weight: 900;
}

.field.ocr-field input,
.field.ocr-field select {
  border-color: rgba(245, 158, 11, 0.75);
  background: var(--warning-bg);
  box-shadow: inset 3px 0 0 var(--amber);
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
