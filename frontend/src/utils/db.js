import { openDB } from 'idb'

const DB_NAME = 'FuelTrackerDB'
const UPLOAD_STORE = 'offline_uploads'
const ACTION_STORE = 'offline_actions'
const CACHE_STORE = 'api_cache'

const notifyOfflineQueueChanged = () => {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('offline-queue-changed'))
  }
}

export const initDB = async () => {
  return openDB(DB_NAME, 2, {
    upgrade(db) {
      if (!db.objectStoreNames.contains(UPLOAD_STORE)) {
        db.createObjectStore(UPLOAD_STORE, { keyPath: 'id', autoIncrement: true })
      }
      if (!db.objectStoreNames.contains(ACTION_STORE)) {
        db.createObjectStore(ACTION_STORE, { keyPath: 'id', autoIncrement: true })
      }
      if (!db.objectStoreNames.contains(CACHE_STORE)) {
        db.createObjectStore(CACHE_STORE, { keyPath: 'key' })
      }
    },
  })
}

export const cacheKey = (config) => {
  const params = config.params ? new URLSearchParams(config.params).toString() : ''
  return `${(config.method || 'get').toLowerCase()}:${config.url}${params ? `?${params}` : ''}`
}

export const cacheApiResponse = async (config, data) => {
  if ((config.method || 'get').toLowerCase() !== 'get') return
  const db = await initDB()
  await db.put(CACHE_STORE, {
    key: cacheKey(config),
    data,
    timestamp: Date.now()
  })
}

export const getCachedApiResponse = async (config) => {
  const db = await initDB()
  return db.get(CACHE_STORE, cacheKey(config))
}

export const enqueueOfflineAction = async (action) => {
  const db = await initDB()
  const id = await db.add(ACTION_STORE, {
    ...action,
    createdAt: Date.now(),
    attempts: 0,
    lastError: null
  })
  notifyOfflineQueueChanged()
  return id
}

export const getOfflineActions = async () => {
  const db = await initDB()
  return (await db.getAll(ACTION_STORE)).sort((a, b) => a.createdAt - b.createdAt)
}

export const getOfflineAction = async (id) => {
  const db = await initDB()
  return db.get(ACTION_STORE, id)
}

export const updateOfflineAction = async (id, patch) => {
  const db = await initDB()
  const action = await db.get(ACTION_STORE, id)
  if (!action) return null
  const next = { ...action, ...patch, updatedAt: Date.now() }
  await db.put(ACTION_STORE, next)
  notifyOfflineQueueChanged()
  return next
}

export const deleteOfflineAction = async (id) => {
  const db = await initDB()
  const result = await db.delete(ACTION_STORE, id)
  notifyOfflineQueueChanged()
  return result
}

export const countOfflineActions = async () => {
  const db = await initDB()
  return db.count(ACTION_STORE)
}

export const saveOfflineRecord = async (file, payload = {}) => {
  return enqueueOfflineAction({
    type: 'record:create',
    method: 'post',
    url: '/records',
    file,
    payload,
    label: 'Новая заправка с чеком'
  })
}

export const getOfflineRecords = async () => {
  return (await getOfflineActions()).filter(action => action.type === 'record:create' && action.file)
}

export const deleteOfflineRecord = async (id) => {
  return deleteOfflineAction(id)
}
