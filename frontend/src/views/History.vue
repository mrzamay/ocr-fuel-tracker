<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'

const records = ref([])
const isLoading = ref(true)

const fetchRecords = async () => {
  isLoading.value = true
  try {
    const { data } = await api.get('/records')
    records.value = data
  } finally {
    isLoading.value = false
  }
}

const formatMoney = (value) => new Intl.NumberFormat('ru-RU', {
  maximumFractionDigits: 0
}).format(Number(value || 0))

const formatNumber = (value, digits = 1) => {
  if (value === null || value === undefined || value === '') return '—'
  return new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits
  }).format(Number(value))
}

const statusLabel = (status) => ({
  manual: 'Вручную',
  success: 'Готово',
  ocr_pending: 'Проверить'
}[status] || status)

const averageConsumption = computed(() => {
  const items = records.value.filter(record => record.consumption_l_per_100km)
  if (!items.length) return null
  return items.reduce((sum, record) => sum + Number(record.consumption_l_per_100km), 0) / items.length
})

onMounted(fetchRecords)
</script>

<template>
  <section class="history-page">
    <div class="page-head">
      <h2 class="section-title">История</h2>
      <p class="section-note">
        {{ averageConsumption ? `Средний расход ${formatNumber(averageConsumption, 1)} л / 100 км` : 'Добавьте минимум две заправки с пробегом, чтобы увидеть расход.' }}
      </p>
    </div>

    <div v-if="isLoading" class="card empty-card">Загружаем записи...</div>

    <div v-else-if="records.length" class="record-list">
      <article v-for="record in records" :key="record.id" class="record-card">
        <div class="record-top">
          <div>
            <h3>{{ record.station_name || 'Заправка' }}</h3>
            <p>{{ record.date }} · {{ record.fuel_type || 'топливо' }}</p>
          </div>
          <span class="status-pill" :class="record.status">{{ statusLabel(record.status) }}</span>
        </div>

        <div class="record-metrics">
          <div>
            <span>Сумма</span>
            <strong>{{ formatMoney(record.amount) }} ₽</strong>
          </div>
          <div>
            <span>Литры</span>
            <strong>{{ formatNumber(record.volume, 1) }}</strong>
          </div>
          <div>
            <span>Пробег</span>
            <strong>{{ record.odometer_km ? `${formatMoney(record.odometer_km)} км` : '—' }}</strong>
          </div>
        </div>

        <div class="consumption-row">
          <div>
            <span>Расход</span>
            <strong>{{ record.consumption_l_per_100km ? `${formatNumber(record.consumption_l_per_100km, 1)} л / 100 км` : 'нужно две заправки с пробегом' }}</strong>
          </div>
          <div v-if="record.distance_km">
            <span>Дистанция</span>
            <strong>{{ formatMoney(record.distance_km) }} км</strong>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="card empty-card">
      История пока пустая. Нажмите плюс и добавьте первую заправку.
    </div>
  </section>
</template>

<style scoped>
.history-page {
  display: grid;
  gap: 16px;
}

.page-head {
  padding: 2px 4px 0;
}

.record-list {
  display: grid;
  gap: 12px;
}

.record-card {
  padding: 16px;
  border-radius: var(--radius);
  background: var(--surface);
  border: 1px solid rgba(229, 231, 235, 0.9);
  box-shadow: var(--shadow);
  display: grid;
  gap: 14px;
}

.record-top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.record-top h3 {
  margin: 0;
  font-size: 18px;
}

.record-top p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
}

.status-pill {
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  color: #065f46;
  background: #d1fae5;
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
}

.status-pill.ocr_pending {
  color: #92400e;
  background: #fef3c7;
}

.status-pill.manual {
  color: #1d4ed8;
  background: #dbeafe;
}

.record-metrics {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.record-metrics div,
.consumption-row {
  padding: 10px;
  border-radius: var(--radius);
  background: var(--soft);
}

.record-metrics span,
.consumption-row span {
  display: block;
  color: var(--muted);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.record-metrics strong,
.consumption-row strong {
  display: block;
  margin-top: 4px;
  font-size: 14px;
  line-height: 1.2;
}

.consumption-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.consumption-row div:last-child {
  text-align: right;
}

.empty-card {
  color: var(--muted);
  font-weight: 700;
}
</style>
