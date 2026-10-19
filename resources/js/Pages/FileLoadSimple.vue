<template>
  <PageHeader :title="$t('files.uploadTitle')" />

  <DropzoneUpload
    :queue="queue"
    :title="$t('files.dropzoneTitle')"
    :hint="$t('files.dropzoneHint')"
    :accept="acceptedTypes"
    :multiple="true"
    @completed="onFileCompleted"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import DropzoneUpload from '@/components/ui/DropzoneUpload.vue'
import { createUploadQueue } from '@/services/chunkUpload'
import { useToast } from '@/composables/useToast'

const { toast } = useToast()

/** Разрешенные MIME-типы для загрузки. */
const acceptedTypes = 'application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar,.7z'

/** Очередь загрузки: движок chunkUpload управляет прогрессом и докачкой. */
const queue = createUploadQueue({})

/**
 * Обработка завершения загрузки файла.
 * Движок chunkUpload уже сохранил файл на сервере и вернул метаданные
 * в task.result. Здесь просто показываем уведомление.
 */
function onFileCompleted(task) {
  if (task.result?.url) {
    toast.success(`Файл "${task.name}" загружен`)
  } else {
    toast.success(`Файл "${task.name}" загружен`)
  }
}
</script>