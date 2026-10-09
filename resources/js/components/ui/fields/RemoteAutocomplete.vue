<template>
  <v-autocomplete
    :model-value="modelValue"
    :items="options"
    :item-title="itemTitle"
    :item-value="itemValue"
    :loading="loading"
    :disabled="disabled"
    :clearable="clearable"
    :no-data-text="noDataText"
    :menu-props="{ maxHeight: 320 }"
    @update:model-value="$emit('update:modelValue', $event)"
    @update:search="onSearch"
    @blur="$emit('blur', $event)"
  >
    <!--
      Слоты состояний: пока грузим — «Поиск…», пустая выдача — наш
      текст. Раньше remote-select молчал при отсутствии результатов,
      и это выглядело как сломанный список.
    -->
    <template #prepend-item>
      <v-list-item v-if="loading" :title="loadingText" disabled />
    </template>
    <template #no-data>
      <v-list-item :title="loading ? loadingText : noDataText" disabled />
    </template>
  </v-autocomplete>
</template>

<script setup>
import { watch } from 'vue'
import useRemoteOptions from '../../../composables/useRemoteOptions'

/**
 * Autocomplete с серверным поиском.
 *
 * Зачем: remote-select (пользователи, курсы) дёргал API на каждый
 * keystroke без debounce и без защиты от гонки: ответ на старую букву
 * мог перезаписать новый список. Состояния loading/no-results не
 * показывались вообще.
 *
 * `fetcher(query)` — async-функция, возвращающая массив опций.
 * `preload` — опции, показанные до начала поиска (например, первые
 * N записей).
 */
const props = defineProps({
  modelValue: { type: [String, Number, Array, null], default: null },
  fetcher: { type: Function, required: true },
  preload: { type: Array, default: () => [] },
  minChars: { type: Number, default: 2 },
  debounce: { type: Number, default: 300 },
  itemTitle: { type: [String, Function], default: 'title' },
  itemValue: { type: [String, Function], default: 'id' },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  loadingText: { type: String, default: 'Поиск…' },
  noDataText: { type: String, default: 'Ничего не найдено' },
})

defineEmits(['update:modelValue', 'blur'])

const { options, loading, search } = useRemoteOptions({
  fetcher: (query) => props.fetcher(query),
  minChars: props.minChars,
  debounce: props.debounce,
  initial: props.preload,
})

// Предзагруженный список должен обновляться, если родитель поменял его
// (например, подгрузил справочник), при этом не затирая активный поиск.
watch(
  () => props.preload,
  (next) => {
    if (!loading.value) options.value = Array.isArray(next) ? next : []
  }
)

const onSearch = (query) => search(query)
</script>
