import { openDB } from 'idb'

const DB_NAME = 'FuelTrackerDB'
const STORE_NAME = 'offline_uploads'

// Инициализация базы данных
export const initDB = async () => {
  return openDB(DB_NAME, 1, {
    upgrade(db) {
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        // Создаем хранилище с автоинкрементным ключом 'id'
        db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true })
      }
    },
  })
}

// Сохранение чека
export const saveOfflineRecord = async (file) => {
  const db = await initDB()
  await db.add(STORE_NAME, {
    file, // Файл (Blob/File) отлично сохраняется в IndexedDB
    timestamp: Date.now()
  })
}

// Получение всех сохраненных чеков
export const getOfflineRecords = async () => {
  const db = await initDB()
  return db.getAll(STORE_NAME)
}

// Удаление чека после успешной синхронизации
export const deleteOfflineRecord = async (id) => {
  const db = await initDB()
  return db.delete(STORE_NAME, id)
}
