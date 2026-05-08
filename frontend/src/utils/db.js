import { openDB } from 'idb'

const DB_NAME = 'FuelTrackerDB'
const STORE_NAME = 'offline_uploads'

export const initDB = async () => {
  return openDB(DB_NAME, 1, {
    upgrade(db) {
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true })
      }
    },
  })
}

export const saveOfflineRecord = async (file, payload = {}) => {
  const db = await initDB()
  await db.add(STORE_NAME, {
    file,
    payload,
    timestamp: Date.now()
  })
}

export const getOfflineRecords = async () => {
  const db = await initDB()
  return db.getAll(STORE_NAME)
}

export const deleteOfflineRecord = async (id) => {
  const db = await initDB()
  return db.delete(STORE_NAME, id)
}
