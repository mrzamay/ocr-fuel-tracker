<script setup>
import { computed, ref } from 'vue'
import api from '../api'
import { useRouter } from 'vue-router'
import { saveOfflineRecord } from '../utils/db'

const router = useRouter()
const activeTab = ref('scan')
const file = ref(null)
const isLoading = ref(false)
const ocrResult = ref(null)
const errorMessage = ref('')

const formData = ref({
  amount: '',
  volume: '',
  odometer_km: '',
  station_name: '',
  fuel_type: 'АИ-95',
  date: new Date().toISOString().split('T')[0]
})

const fuelTypes = ['АИ-92', 'АИ-95', 'АИ-98', 'АИ-100', 'ДТ', 'Газ']

const fileSizeMb = computed(() => file.value ? (file.value.size / 1024 / 1024).toFixed(1) : null)

const payload = () => ({
  amount: formData.value.amount,
  volume: formData.value.volume,
  odometer_km: formData.value.odometer_km,
  station_name: formData.value.station_name,
  fuel_type: formData.value.fuel_type,
  date: formData.value.date
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
    formData.value.amount = ocrResult.value.amount || formData.value.amount
    formData.value.volume = ocrResult.value.volume || formData.value.volume
    formData.value.odometer_km = ocrResult.value.odometer_km || formData.value.odometer_km
    formData.value.date = ocrResult.value.date || formData.value.date
    activeTab.value = 'manual'
  } catch (error) {
    if (error.response?.status === 422) {
      errorMessage.value = 'Фото не принято сервером. На iPhone чаще всего помогает JPEG/PNG или фото до 15 МБ.'
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
          <label>Пробег, км</label>
          <input v-model="formData.odometer_km" inputmode="numeric" type="number" min="0" placeholder="84500" />
        </div>
        <div class="field">
          <label>Дата</label>
          <input v-model="formData.date" type="date" />
        </div>
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
          <label>Сумма, ₽</label>
          <input v-model="formData.amount" inputmode="decimal" type="number" step="0.01" min="0" placeholder="3200" />
        </div>
        <div class="field">
          <label>Литры</label>
          <input v-model="formData.volume" inputmode="decimal" type="number" step="0.01" min="0" placeholder="45.2" />
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

      <div class="field">
        <label>АЗС</label>
        <input v-model="formData.station_name" type="text" placeholder="Лукойл, Газпромнефть..." />
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
  background: #dde7f5;
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
  background: #fff;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
}

.scan-card,
.form-card {
  display: grid;
  gap: 16px;
}

.file-target {
  min-height: 210px;
  border: 1.5px dashed #9db4d6;
  border-radius: var(--radius);
  background: linear-gradient(180deg, #f8fbff, #eef6ff);
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
  background: #dbeafe;
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
  color: #9f1239;
  background: #ffe4e6;
  font-size: 14px;
  font-weight: 700;
}
</style>
