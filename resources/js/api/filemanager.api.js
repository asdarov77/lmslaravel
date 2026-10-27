import httpClient from './httpClient'

/**
 * Файловый менеджер: каталоги, папки, файлы, загрузка по частям.
 *
 * Отдельный модуль от course.api/catalog.api по двум причинам:
 *
 *  1. Формат ответов другой — здесь объект со вложенными folders/files,
 *     а не список. Смешивать их в общем модуле значило бы держать в
 *     сторе поля, которые тут не нужны.
 *  2. Часть вызовов уходит СЫРЫМ телом (application/octet-stream),
 *     а не JSON. httpClient по умолчанию ставит Content-Type:
 *     application/json, поэтому у таких запросов заголовок задан явно —
 *     иначе Symfony счёл бы тело формой и не отдал бы его в
 *     Request::getContent().
 *
 * Все ответы приходят в конверте {success,data,error,meta}, поэтому
 * вызывающий берёт payload через unwrapResponse.
 */
export const fetchFolderContents = (folderId = null) =>
  httpClient.get('/api/filemanager', { params: folderId === null ? {} : { folder_id: folderId } })

/** Всё дерево папок: для выбора папки назначения. */
export const fetchFolderTree = () => httpClient.get('/api/filemanager/folders')

export const createFolder = (name, parentId = null) =>
  httpClient.post('/api/filemanager/folders', { name, parent_id: parentId })

export const renameFolder = (folderId, name) =>
  httpClient.patch(`/api/filemanager/folders/${folderId}`, { name })

export const moveFolder = (folderId, parentId = null) =>
  httpClient.post(`/api/filemanager/folders/${folderId}/move`, { parent_id: parentId })

export const deleteFolder = (folderId, recursive = false) =>
  httpClient.delete(`/api/filemanager/folders/${folderId}`, { params: { recursive: recursive ? 1 : 0 } })

export const renameFile = (fileId, name) =>
  httpClient.patch(`/api/filemanager/files/${fileId}`, { name })

export const moveFiles = (ids, folderId = null) =>
  httpClient.post('/api/filemanager/files/move', { ids, folder_id: folderId })

export const deleteFiles = (ids) => httpClient.post('/api/filemanager/files/delete', { ids })

/**
 * Скачивание файла.
 *
 * Именно через axios, а не ссылкой: маршрут требует заголовок
 * Authorization, а обычный <a href> его не пошлёт — сервер ответил бы
 * 401, и файл не открылся бы. Байты ответа превращаются в объектный
 * URL, который и уходит в имя, предложенное сервером.
 *
 * Имя файла берётся из Content-Disposition, а не из record.name:
 * после переноса в папку с занятым именем сервер дописывает «(2)», и
 * имя из базы может отличаться от того, что лежит на диске.
 */
export const downloadFile = async (record) => {
  const response = await httpClient.get(`/api/filemanager/files/${record.id}/download`, {
    responseType: 'blob',
  })

  const blobUrl = URL.createObjectURL(new Blob([response.data]))

  try {
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = filenameFromDisposition(response.headers['content-disposition']) || record.name
    link.rel = 'noopener'
    document.body.appendChild(link)
    link.click()
    link.remove()
  } finally {
    // Освобождаем объект сразу: иначе каждый скачанный файл держит
    // в памяти копию содержимого до перезагрузки страницы.
    URL.revokeObjectURL(blobUrl)
  }

  return record
}

/** Имя файла из Content-Disposition (в т.ч. RFC 5987 filename*). */
export const filenameFromDisposition = (header) => {
  if (!header || typeof header !== 'string') return ''

  // filename*=UTF-8''%D0%9E%D1%82%D1%87%D1%91%D1%82.txt — единственная
  // форма, в которой кириллица доезжает без искажений.
  const extended = /filename\*=(?:[^']*)'[^']*'([^;]+)/i.exec(header)

  if (extended) {
    try {
      return decodeURIComponent(extended[1].trim().replace(/^"|"$/g, ''))
    } catch (error) {
      // Повреждённая кодировка — не повод ронять скачивание: ниже
      // попробуем обычный filename.
    }
  }

  const plain = /filename="?([^";]+)"?/i.exec(header)

  return plain ? plain[1].trim() : ''
}

// ---------------------------------------------------------------------
// Загрузка по частям
// ---------------------------------------------------------------------

/**
 * Начинает или продолжает загрузку. Отдаёт upload_id, размер части,
 * сколько всего частей и какие уже приняты.
 */
export const initUpload = (payload) => httpClient.post('/api/filemanager/uploads/init', payload)

/**
 * Отправляет одну часть СЫРЫМ телом.
 *
 * Content-Type здесь не application/octet-stream «по умолчанию», а
 * задан явно: httpClient ставит application/json, и при нём Symfony
 * не отдаст тело в getContent() — контроллер получит пустую строку и
 * ответит 413 на нормальную часть.
 */
export const sendChunk = (uploadId, index, blob, onUploadProgress) =>
  httpClient.post(`/api/filemanager/uploads/${uploadId}/chunk?index=${index}`, blob, {
    headers: { 'Content-Type': 'application/octet-stream' },
    onUploadProgress,
  })

export const completeUpload = (uploadId) => httpClient.post(`/api/filemanager/uploads/${uploadId}/complete`)

export const abortUpload = (uploadId) => httpClient.delete(`/api/filemanager/uploads/${uploadId}`)

export default {
  fetchFolderContents,
  fetchFolderTree,
  createFolder,
  renameFolder,
  moveFolder,
  deleteFolder,
  renameFile,
  moveFiles,
  deleteFiles,
  downloadFile,
  filenameFromDisposition,
  initUpload,
  sendChunk,
  completeUpload,
  abortUpload,
}
