<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const records = ref([])

const fetchRecords = async () => {
  const { data } = await api.get('/records')
  records.value = data
}

onMounted(fetchRecords)
</script>

<template>
  <div class="card">
    <h2>История заправок</h2>
    <div v-for="record in records" :key="record.id" style="border-bottom: 1px solid #ccc; padding: 10px 0;">
      <strong>Дата:</strong> {{ record.date }} <br/>
      <strong>Сумма:</strong> {{ record.amount }} ₽ | <strong>Объем:</strong> {{ record.volume }} л <br/>
      <span style="color: gray; font-size: 0.8em">Статус: {{ record.status }}</span>
    </div>
  </div>
</template>
