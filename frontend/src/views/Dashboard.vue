<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const records = ref([])
const monthly = ref(null)
const isLoading = ref(true)
const currentMonth = new Date().toISOString().slice(0, 7)

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

const parseDate = (value) => {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const formatDate = (value) => {
  const date = parseDate(value)
  if (!date) return 'Без даты'
  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(date)
}

const formatChartDate = (value) => {
  const date = parseDate(value)
  if (!date) return 'Без даты'
  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'short'
  }).format(date)
}

const fetchData = async () => {
  isLoading.value = true
  try {
    const [{ data }, { data: meta }] = await Promise.all([
      api.get('/records'),
      api.get('/records/meta', { params: { month: currentMonth } })
    ])
    records.value = data
    monthly.value = meta.monthly
  } finally {
    isLoading.value = false
  }
}

const totalAmount = computed(() => records.value.reduce((sum, item) => sum + Number(item.amount || 0), 0))
const totalVolume = computed(() => records.value.reduce((sum, item) => sum + Number(item.volume || 0), 0))
const avgUnitPrice = computed(() => {
  const prices = records.value.filter(item => item.unit_price).map(item => Number(item.unit_price))
  if (!prices.length) return null
  return prices.reduce((sum, item) => sum + item, 0) / prices.length
})
const latestOdometer = computed(() => {
  return [...records.value]
    .filter(item => item.odometer_km)
    .sort((a, b) => Number(b.odometer_km) - Number(a.odometer_km))[0]?.odometer_km || null
})

const consumptionRecords = computed(() => records.value.filter(item => item.consumption_l_per_100km))
const averageConsumption = computed(() => {
  if (consumptionRecords.value.length === 0) return null
  const distance = consumptionRecords.value.reduce((sum, item) => sum + Number(item.distance_km || 0), 0)
  const volume = consumptionRecords.value.reduce((sum, item) => sum + Number(item.interval_volume || item.volume || 0), 0)
  if (!distance || !volume) return null
  return (volume / distance) * 100
})

const latestRecords = computed(() => records.value.slice(0, 3))
const trendRecords = computed(() => records.value
  .filter(record => record.consumption_l_per_100km || record.unit_price)
  .slice(0, 5)
)

const chartData = computed(() => {
  const grouped = records.value.reduce((acc, curr) => {
    const key = curr.date || 'Без даты'
    acc[key] = (acc[key] || 0) + Number(curr.amount || 0)
    return acc
  }, {})

  const labels = Object.keys(grouped).sort().slice(-8)

  return {
    labels: labels.map(formatChartDate),
    datasets: [{
      label: 'Расходы, ₽',
      backgroundColor: '#2563eb',
      borderRadius: 6,
      data: labels.map(date => grouped[date])
    }]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    x: { grid: { display: false } },
    y: { grid: { color: '#edf2f7' } }
  }
}

onMounted(fetchData)
</script>

<template>
  <section class="dashboard">
    <div class="hero-panel">
      <div>
        <p>Средний расход</p>
        <strong>{{ averageConsumption ? formatNumber(averageConsumption, 1) : '—' }}</strong>
        <span>л / 100 км</span>
      </div>
      <div class="hero-badge">
        <span>{{ latestOdometer ? formatMoney(latestOdometer) : '—' }}</span>
        <small>км</small>
      </div>
    </div>

    <div class="stats-grid">
      <article class="stat-card">
        <span>Заправок</span>
        <strong>{{ records.length }}</strong>
      </article>
      <article class="stat-card">
        <span>Литров</span>
        <strong>{{ formatNumber(totalVolume, 1) }}</strong>
      </article>
      <article class="stat-card">
        <span>Потрачено</span>
        <strong>{{ formatMoney(totalAmount) }} ₽</strong>
      </article>
    </div>

    <div class="card month-card" v-if="monthly">
      <div class="card-head">
        <h2>Месяц</h2>
        <span>{{ monthly.month }}</span>
      </div>
      <div class="month-grid">
        <div><span>Траты</span><strong>{{ formatMoney(monthly.amount) }} ₽</strong></div>
        <div><span>Литры</span><strong>{{ formatNumber(monthly.volume, 1) }}</strong></div>
        <div><span>Км</span><strong>{{ monthly.distance_km ? formatMoney(monthly.distance_km) : '—' }}</strong></div>
        <div><span>Цена литра</span><strong>{{ monthly.avg_unit_price ? formatNumber(monthly.avg_unit_price, 2) : (avgUnitPrice ? formatNumber(avgUnitPrice, 2) : '—') }} ₽</strong></div>
        <div><span>₽ / км</span><strong>{{ monthly.cost_per_km ? formatNumber(monthly.cost_per_km, 2) : '—' }}</strong></div>
        <div><span>Расход</span><strong>{{ monthly.avg_consumption ? formatNumber(monthly.avg_consumption, 1) : '—' }}</strong></div>
      </div>
    </div>

    <div class="card chart-card">
      <div class="card-head">
        <h2>Расходы</h2>
        <span>последние дни</span>
      </div>
      <div v-if="!isLoading && records.length" class="chart-box">
        <Bar :data="chartData" :options="chartOptions" />
      </div>
      <p v-else class="empty-text">Добавьте первую заправку, и здесь появится график.</p>
    </div>

    <div class="card trend-card" v-if="trendRecords.length">
      <div class="card-head">
        <h2>Динамика</h2>
        <span>расход и цена</span>
      </div>
      <div class="trend-list">
        <article v-for="record in trendRecords" :key="record.id">
          <span>{{ formatChartDate(record.date) }}</span>
          <strong>{{ record.consumption_l_per_100km ? `${formatNumber(record.consumption_l_per_100km, 1)} л/100` : '—' }}</strong>
          <em>{{ record.unit_price ? `${formatNumber(record.unit_price, 2)} ₽/л` : '—' }}</em>
        </article>
      </div>
    </div>

    <div class="card recent-card">
      <div class="card-head">
        <h2>Последние записи</h2>
      </div>
      <div v-if="latestRecords.length" class="recent-list">
        <article v-for="record in latestRecords" :key="record.id" class="recent-item">
          <div>
            <strong>{{ record.station_name || 'Заправка' }}</strong>
            <span>{{ formatDate(record.date) }} · {{ record.fuel_type || 'топливо' }}</span>
          </div>
          <div>
            <strong>{{ formatNumber(record.volume, 1) }} л</strong>
            <span>{{ record.consumption_l_per_100km ? `${formatNumber(record.consumption_l_per_100km, 1)} л/100` : 'расход позже' }}</span>
          </div>
        </article>
      </div>
      <p v-else class="empty-text">История пока пустая.</p>
    </div>
  </section>
</template>

<style scoped>
.dashboard {
  display: grid;
  gap: 14px;
}

.hero-panel {
  min-height: 156px;
  padding: 20px;
  border-radius: var(--radius);
  color: white;
  background:
    linear-gradient(135deg, rgba(37, 99, 235, 0.96), rgba(20, 184, 166, 0.92)),
    #2563eb;
  display: flex;
  justify-content: space-between;
  align-items: end;
  box-shadow: 0 18px 38px rgba(37, 99, 235, 0.24);
}

.hero-panel p,
.hero-panel span,
.hero-panel small {
  margin: 0;
  color: rgba(255, 255, 255, 0.82);
  font-weight: 700;
}

.hero-panel strong {
  display: block;
  margin-top: 4px;
  font-size: 48px;
  line-height: 0.95;
  letter-spacing: 0;
}

.hero-badge {
  min-width: 86px;
  padding: 12px;
  border-radius: var(--radius);
  background: rgba(255, 255, 255, 0.14);
  text-align: right;
}

.hero-badge span {
  display: block;
  color: #fff;
  font-size: 18px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.stat-card {
  min-height: 88px;
  padding: 14px;
  border-radius: var(--radius);
  background: var(--surface);
  border: 1px solid var(--line);
}

.stat-card span {
  color: var(--muted);
  font-size: 12px;
  font-weight: 800;
}

.stat-card strong {
  display: block;
  margin-top: 8px;
  font-size: 19px;
  line-height: 1.1;
}

.chart-card,
.recent-card,
.trend-card,
.month-card {
  display: grid;
  gap: 14px;
}

.trend-list {
  display: grid;
  gap: 8px;
}

.trend-list article {
  display: grid;
  grid-template-columns: 70px 1fr auto;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: var(--radius);
  background: var(--soft);
}

.trend-list span,
.trend-list em {
  color: var(--muted);
  font-size: 13px;
  font-style: normal;
}

.month-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.month-grid div {
  padding: 10px;
  border-radius: var(--radius);
  background: var(--soft);
}

.month-grid span {
  display: block;
  color: var(--muted);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.month-grid strong {
  display: block;
  margin-top: 4px;
  font-size: 14px;
}

.card-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}

.card-head h2 {
  margin: 0;
  font-size: 18px;
}

.card-head span {
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
}

.chart-box {
  height: 240px;
}

.recent-list {
  display: grid;
  gap: 10px;
}

.recent-item {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border-radius: var(--radius);
  background: var(--soft);
}

.recent-item div:last-child {
  text-align: right;
}

.recent-item strong,
.recent-item span {
  display: block;
}

.recent-item span,
.empty-text {
  color: var(--muted);
  font-size: 13px;
}

.empty-text {
  margin: 0;
}
</style>
