<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../api'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const records = ref([])
const chartData = ref(null)

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false
}

const fetchData = async () => {
  const { data } = await api.get('/records')
  records.value = data

  // Группировка по датам для графика
  const grouped = data.reduce((acc, curr) => {
    acc[curr.date] = (acc[curr.date] || 0) + parseFloat(curr.amount || 0)
    return acc
  }, {})

  const labels = Object.keys(grouped).sort()
  const dataset = labels.map(date => grouped[date])

  chartData.value = {
    labels,
    datasets: [{
      label: 'Расходы (₽)',
      backgroundColor: '#4CAF50',
      data: dataset
    }]
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="card">
    <h2>Дашборд</h2>
    <div v-if="chartData" style="height: 300px; margin-top: 20px;">
      <Bar :data="chartData" :options="chartOptions" />
    </div>
    <div v-else>Нет данных для отображения</div>
  </div>
</template>
