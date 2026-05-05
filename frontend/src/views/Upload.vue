<script setup>
import { ref } from 'vue'
import api from '../api'
import { useRouter } from 'vue-router'
import { saveOfflineRecord } from '../utils/db'

const router = useRouter()
const file = ref(null)
const isLoading = ref(false)
const ocrResult = ref(null)

const handleFile = (e) => {
  file.value = e.target.files[0]
}

const uploadReceipt = async () => {
  if (!file.value) return
  isLoading.value = true
  
  // ПРОВЕРКА НА ОФФЛАЙН
  if (!navigator.onLine) {
    try {
      await saveOfflineRecord(file.value)
      alert('Нет сети! Чек сохранен локально и будет отправлен при появлении интернета.')
      router.push('/history')
    } catch (e) {
      alert('Ошибка при сохранении оффлайн')
    } finally {
      isLoading.value = false
      file.value = null
    }
    return
  }

  // ОБЫЧНАЯ ЗАГРУЗКА (если есть интернет)
  const formData = new FormData()
  formData.append('receipt_image', file.value)

  try {
    const { data } = await api.post('/records', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    ocrResult.value = data.data
  } catch (error) {
    alert('Ошибка загрузки')
  } finally {
    isLoading.value = false
  }
}

const saveCorrection = async () => {
  await api.put(`/records/${ocrResult.value.id}`, {
    amount: ocrResult.value.amount,
    volume: ocrResult.value.volume,
    status: 'success'
  })
  router.push('/history')
}
</script>

<template>
  <div class="card">
    <h2>Добавить заправку</h2>
    
    <div v-if="!ocrResult">
      <!-- capture="environment" включает основную камеру смартфона -->
      <input type="file" accept="image/*" capture="environment" @change="handleFile" />
      <button @click="uploadReceipt" :disabled="!file || isLoading">
        {{ isLoading ? 'Распознавание...' : 'Загрузить и распознать' }}
      </button>
    </div>

    <div v-else>
      <h3>Проверьте данные:</h3>
      <label>Сумма (₽):</label>
      <input type="number" step="0.01" v-model="ocrResult.amount" />
      
      <label>Объем (л):</label>
      <input type="number" step="0.01" v-model="ocrResult.volume" />
      
      <button @click="saveCorrection">Подтвердить и сохранить</button>
    </div>
  </div>
</template>
