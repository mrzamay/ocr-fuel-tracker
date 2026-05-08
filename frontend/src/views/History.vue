<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'

const records = ref([])
const vehicles = ref([])
const stations = ref([])
const fuelTypes = ref([])
const isLoading = ref(true)
const errorMessage = ref('')
const editing = ref(null)

const filters = ref({
  month: new Date().toISOString().slice(0, 7),
  vehicle_id: '',
  fuel_type: '',
  station_name: '',
  status: ''
})

const editForm = ref({})

const fetchMeta = async () => {
  const [{ data: vehicleData }, { data: meta }] = await Promise.all([
    api.get('/vehicles'),
    api.get('/records/meta', { params: filters.value })
  ])
  vehicles.value = vehicleData
  stations.value = meta.stations || []
  fuelTypes.value = meta.fuel_types || []
}

const fetchRecords = async () => {
  isLoading.value = true
  try {
    const { data } = await api.get('/records', { params: filters.value })
    records.value = data
  } finally {
    isLoading.value = false
  }
}

const refresh = async () => {
  await Promise.all([fetchMeta(), fetchRecords()])
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

const formatDate = (value) => {
  if (!value) return 'Без даты'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value).split('T')[0]

  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(date)
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

const startEdit = (record) => {
  editing.value = record
  editForm.value = {
    vehicle_id: record.vehicle_id || '',
    amount: record.amount || '',
    volume: record.volume || '',
    unit_price: record.unit_price || '',
    is_full_tank: Boolean(record.is_full_tank),
    odometer_km: record.odometer_km || '',
    date: String(record.date || '').split('T')[0],
    station_name: record.station_name || '',
    fuel_type: record.fuel_type || '',
    status: record.status || 'manual',
    latitude: record.latitude || '',
    longitude: record.longitude || '',
    location_accuracy: record.location_accuracy || '',
  }
}

const cancelEdit = () => {
  editing.value = null
  editForm.value = {}
}

const saveEdit = async () => {
  errorMessage.value = ''
  try {
    await api.put(`/records/${editing.value.id}`, editForm.value)
    cancelEdit()
    await refresh()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Не получилось сохранить запись.'
  }
}

const deleteRecord = async (record) => {
  if (!confirm('Удалить заправку?')) return

  try {
    await api.delete(`/records/${record.id}`)
    await refresh()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Не получилось удалить запись.'
  }
}

onMounted(refresh)
</script>

<template>
  <section class="history-page">
    <div class="page-head">
      <h2 class="section-title">История</h2>
      <p class="section-note">
        {{ averageConsumption ? `Средний расход ${formatNumber(averageConsumption, 1)} л / 100 км` : 'Расход появится после двух полных баков с пробегом.' }}
      </p>
    </div>

    <div class="card filters-card">
      <div class="filter-grid">
        <div class="field">
          <label>Месяц</label>
          <input v-model="filters.month" type="month" @change="refresh" />
        </div>
        <div class="field">
          <label>Авто</label>
          <select v-model="filters.vehicle_id" @change="refresh">
            <option value="">Все</option>
            <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.name }}</option>
          </select>
        </div>
        <div class="field">
          <label>Топливо</label>
          <select v-model="filters.fuel_type" @change="refresh">
            <option value="">Все</option>
            <option v-for="type in fuelTypes" :key="type" :value="type">{{ type }}</option>
          </select>
        </div>
        <div class="field">
          <label>Статус</label>
          <select v-model="filters.status" @change="refresh">
            <option value="">Все</option>
            <option value="ocr_pending">Только OCR ошибки</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>АЗС</label>
        <select v-model="filters.station_name" @change="refresh">
          <option value="">Все АЗС</option>
          <option v-for="station in stations" :key="station" :value="station">{{ station }}</option>
        </select>
      </div>
    </div>

    <p v-if="errorMessage" class="form-error">{{ errorMessage }}</p>
    <div v-if="isLoading" class="card empty-card">Загружаем записи...</div>

    <div v-else-if="records.length" class="record-list">
      <article v-for="record in records" :key="record.id" class="record-card">
        <div v-if="editing?.id !== record.id">
          <div class="record-top">
            <div>
              <h3>{{ record.station_name || 'Заправка' }}</h3>
              <p>{{ formatDate(record.date) }} · {{ record.vehicle?.name || 'авто' }} · {{ record.fuel_type || 'топливо' }}</p>
            </div>
            <span class="status-pill" :class="record.status">{{ statusLabel(record.status) }}</span>
          </div>

          <div class="record-metrics">
            <div><span>Сумма</span><strong>{{ formatMoney(record.amount) }} ₽</strong></div>
            <div><span>Литры</span><strong>{{ formatNumber(record.volume, 1) }}</strong></div>
            <div><span>Цена</span><strong>{{ record.unit_price ? `${formatNumber(record.unit_price, 2)} ₽` : '—' }}</strong></div>
            <div><span>Пробег</span><strong>{{ record.odometer_km ? `${formatMoney(record.odometer_km)} км` : '—' }}</strong></div>
          </div>

          <div class="consumption-row">
            <div>
              <span>{{ record.is_full_tank ? 'Полный бак' : 'Частичная' }}</span>
              <strong>{{ record.consumption_l_per_100km ? `${formatNumber(record.consumption_l_per_100km, 1)} л / 100 км` : 'расход позже' }}</strong>
            </div>
            <div v-if="record.cost_per_km">
              <span>Стоимость</span>
              <strong>{{ formatNumber(record.cost_per_km, 2) }} ₽ / км</strong>
            </div>
          </div>

          <p v-if="record.warnings?.length" class="warning-text">{{ record.warnings[0] }}</p>

          <div class="record-actions">
            <button @click="startEdit(record)">Редактировать</button>
            <button @click="deleteRecord(record)">Удалить</button>
          </div>
        </div>

        <form v-else class="edit-form" @submit.prevent="saveEdit">
          <div class="filter-grid">
            <div class="field">
              <label>Авто</label>
              <select v-model="editForm.vehicle_id">
                <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.name }}</option>
              </select>
            </div>
            <div class="field"><label>Дата</label><input v-model="editForm.date" type="date" /></div>
            <div class="field"><label>Сумма</label><input v-model="editForm.amount" type="number" step="0.01" /></div>
            <div class="field"><label>Литры</label><input v-model="editForm.volume" type="number" step="0.01" /></div>
            <div class="field"><label>Цена литра</label><input v-model="editForm.unit_price" type="number" step="0.01" /></div>
            <div class="field"><label>Пробег</label><input v-model="editForm.odometer_km" type="number" /></div>
          </div>
          <div class="field"><label>АЗС</label><input v-model="editForm.station_name" type="text" /></div>
          <div class="field"><label>Топливо</label><input v-model="editForm.fuel_type" type="text" /></div>
          <label class="check-row"><input v-model="editForm.is_full_tank" type="checkbox" /> Полный бак</label>
          <div class="record-actions">
            <button type="submit">Сохранить</button>
            <button type="button" @click="cancelEdit">Отмена</button>
          </div>
        </form>
      </article>
    </div>

    <div v-else class="card empty-card">
      По выбранным фильтрам записей нет.
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

.filters-card,
.edit-form {
  display: grid;
  gap: 12px;
}

.filter-grid,
.record-metrics {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.record-list {
  display: grid;
  gap: 12px;
}

.record-card {
  padding: 16px;
  border-radius: var(--radius);
  background: var(--surface);
  border: 1px solid var(--card-border);
  box-shadow: var(--shadow);
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
  color: var(--success-ink);
  background: var(--success-bg);
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
}

.status-pill.ocr_pending {
  color: var(--warning-ink);
  background: var(--warning-bg);
}

.status-pill.manual {
  color: var(--info-ink);
  background: var(--info-bg);
}

.record-metrics {
  margin-top: 14px;
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
  margin-top: 10px;
}

.record-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 12px;
}

.record-actions button {
  min-height: 42px;
  border: 1px solid var(--secondary-border);
  border-radius: var(--radius);
  color: var(--primary);
  background: var(--secondary-bg);
  font-weight: 800;
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

.warning-text,
.form-error {
  margin: 12px 0 0;
  padding: 12px;
  border-radius: var(--radius);
  color: var(--warning-ink);
  background: var(--warning-bg);
  font-weight: 700;
  font-size: 13px;
}

.form-error {
  color: var(--danger-ink);
  background: var(--danger-bg);
}

.empty-card {
  color: var(--muted);
  font-weight: 700;
}
</style>
