<script setup>
import { ref, computed } from 'vue'
import api from '../api'
import { useRouter } from 'vue-router'
import { saveOfflineRecord } from '../utils/db'

const router = useRouter()
const activeTab = ref('scan') // 'scan' или 'manual'

// Данные формы
const file = ref(null)
const isLoading = ref(false)
const ocrResult = ref(null)

const formData = ref({
  amount: '',
  volume: '',
  station_name: '',
  fuel_type: 'АИ-95',
  date: new Date().toISOString().split('T')[0]
})

const fuelTypes = ['АИ-92', 'АИ-95', 'АИ-98', 'АИ-100', 'ДТ', 'Газ (Пропан)', 'Газ (Метан)']

// Обработка файла
const handleFile = (e) => {
  file.value = e.target.files[0]
}

// Отправка чека на OCR
const uploadReceipt = async () => {
  if (!file.value) return
  isLoading.value = true
  
  if (!navigator.onLine) {
    try {
      await saveOfflineRecord(file.value)
      alert('Нет сети! Чек сохранен локально и будет отправлен позже.')
      router.push('/history')
    } catch (e) {
      alert('Ошибка при сохранении оффлайн')
    } finally {
      isLoading.value = false
      file.value = null
    }
    return
  }

  const fd = new FormData()
  fd.append('receipt_image', file.value)

  try {
    const { data } = await api.post('/records', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    ocrResult.value = data.data
    // Подставляем распознанные данные в форму для подтверждения
    formData.value.amount = ocrResult.value.amount || ''
    formData.value.volume = ocrResult.value.volume || ''
    formData.value.date = ocrResult.value.date || formData.value.date

    // Обязательно добавь эти строки, чтобы мы увидели ответ API!
    console.log("Ответ от сервера:", data);
    ocrResult.value = data.data;
    ocrResult.value.raw_text = data.raw_text; // Если бэкенд прокинет raw_text

  } catch (error) {
    alert('Ошибка загрузки чека')
  } finally {
    isLoading.value = false
  }
}

// Сохранение отредактированных OCR данных
const saveCorrection = async () => {
  isLoading.value = true
  try {
    await api.put(`/records/${ocrResult.value.id}`, {
      amount: formData.value.amount,
      volume: formData.value.volume,
      station_name: formData.value.station_name,
      fuel_type: formData.value.fuel_type,
      date: formData.value.date,
      status: 'success'
    })
    router.push('/history')
  } catch (error) {
    alert('Ошибка при сохранении данных')
  } finally {
    isLoading.value = false
  }
}

// Полностью ручное добавление (без фото)
const submitManual = async () => {
  isLoading.value = true
  try {
    await api.post('/records', {
      amount: formData.value.amount,
      volume: formData.value.volume,
      station_name: formData.value.station_name,
      fuel_type: formData.value.fuel_type,
      date: formData.value.date
    })
    router.push('/history')
  } catch (error) {
    alert('Ошибка при добавлении записи')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="upload-page">
    
    <!-- Переключатель вкладок -->
    <div class="tabs" v-if="!ocrResult">
      <button :class="{ active: activeTab === 'scan' }" @click="activeTab = 'scan'">📸 Скан чека</button>
      <button :class="{ active: activeTab === 'manual' }" @click="activeTab = 'manual'">✍️ Вручную</button>
    </div>

    <!-- Вкладка Скан Чека -->
    <div class="card" v-if="activeTab === 'scan' && !ocrResult">
      <h2>Отсканировать чек</h2>
      <p class="subtitle">Сфотографируйте чек, и нейросеть найдет сумму и литры.</p>
      
      <div class="file-upload-wrapper">
        <input type="file" id="receipt" accept="image/*" capture="environment" @change="handleFile" class="file-input" />
        <label for="receipt" class="file-label">
          <svg viewBox="0 0 24 24" width="32" height="32" stroke="var(--primary)" stroke-width="2" fill="none">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
          </svg>
          <span v-if="!file">Нажмите, чтобы сделать фото</span>
          <span v-else style="color: var(--primary); font-weight: bold;">Фото выбрано ({{ (file.size / 1024 / 1024).toFixed(1) }} MB)</span>
        </label>
      </div>

      <button class="btn-primary" style="margin-top: 20px;" @click="uploadReceipt" :disabled="!file || isLoading">
        {{ isLoading ? 'Распознавание...' : 'Распознать' }}
      </button>
    </div>

    <!-- Вкладка Ручной Ввод (Или подтверждение OCR) -->
    <div class="card" v-if="activeTab === 'manual' || ocrResult">
      <h2>{{ ocrResult ? 'Проверьте данные' : 'Добавить заправку' }}</h2>
      
      <div class="input-group">
        <label>Сумма (₽)</label>
        <input type="number" step="0.01" v-model="formData.amount" placeholder="Например: 1500" required />
      </div>
      
      <div class="input-group">
        <label>Объем (литры)</label>
        <input type="number" step="0.01" v-model="formData.volume" placeholder="Например: 30.5" required />
      </div>

      <div class="input-group">
        <label>Вид топлива</label>
        <select v-model="formData.fuel_type">
          <option v-for="ft in fuelTypes" :key="ft" :value="ft">{{ ft }}</option>
        </select>
      </div>

      <div class="input-group">
        <label>Название АЗС (необязательно)</label>
        <input type="text" v-model="formData.station_name" placeholder="Газпромнефть, Лукойл..." />
      </div>

      <div class="input-group">
        <label>Дата</label>
        <input type="date" v-model="formData.date" required />
      </div>

      <div v-if="ocrResult && ocrResult.raw_text" style="margin-top:20px; font-size:0.8em; color:gray;">
        <p><strong>Сырой текст с чека (для отладки):</strong></p>
        <pre style="white-space: pre-wrap; background:#f0f0f0; padding:10px; border-radius:5px; max-height: 150px; overflow-y: auto;">{{ ocrResult.raw_text }}</pre>
      </div>

      <button class="btn-primary" @click="ocrResult ? saveCorrection() : submitManual()" :disabled="isLoading">
        {{ isLoading ? 'Сохранение...' : (ocrResult ? 'Подтвердить и сохранить' : 'Добавить запись') }}
      </button>
      
      <button v-if="ocrResult" @click="ocrResult = null" style="width: 100%; margin-top: 10px; background: none; color: var(--danger); border: none; font-weight: bold; cursor: pointer;">
        Отменить
      </button>
    </div>

  </div>
</template>

<style scoped>
.subtitle { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px; line-height: 1.4; }

/* Tabs */
.tabs { display: flex; background: #E5E7EB; border-radius: 12px; padding: 4px; margin-bottom: 20px; }
.tabs button {
  flex: 1; padding: 10px; border: none; background: transparent; border-radius: 8px;
  font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.2s;
}
.tabs button.active { background: #fff; color: var(--primary); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

/* File Upload */
.file-upload-wrapper { position: relative; width: 100%; }
.file-input {
  position: absolute; width: 0; height: 0; opacity: 0; overflow: hidden;
}
.file-label {
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
  padding: 40px 20px; border: 2px dashed #D1D5DB; border-radius: var(--radius);
  background: #F9FAFB; cursor: pointer; transition: all 0.2s;
}
.file-label:hover, .file-input:focus + .file-label { border-color: var(--primary); background: #EEF2FF; }
.file-label span { color: var(--text-muted); font-size: 0.9rem; font-weight: 500; }
</style>
