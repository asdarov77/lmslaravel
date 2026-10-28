<!--
  CoursePlayerLayout — каркас экрана просмотра курса.

  Выделен из CourseManifest.vue (1219 строк): раскладка «содержание +
  материал» была встроена прямо в страницу вместе с логикой загрузки,
  поиска и избранного, поэтому её нельзя было переиспользовать и не на
  чём было проверять вёрстку. Здесь только структура и оформление, все
  данные приходят через пропсы, действия — через события и слоты.

  Что добавляет относительно прежней вёрстки:
   — прогресс курса в верхней панели (был виден только в дереве,
     как отдельная отметка у каждого узла, и не давал ответа «сколько
     курса пройдено»);
   — отметки выполнения в содержании: ✓ прочитан, ◐ начат, — не начат
     (раньше «посещено» красилось только бледно-серым цветом, который
     в тёмной теме почти не читался);
   — переходы «предыдущий/следующий урок» внизу материала;
   — полноэкранный режим чтения.
-->
<template>
  <div class="cpl" :class="{ 'cpl--fullscreen': isFullscreen }">
    <!-- Верхняя панель: курс, прогресс, текущий материал, полный экран -->
    <header class="cpl__bar">
      <div class="cpl__bar-main">
        <h2 class="cpl__title" :title="courseTitle">{{ courseTitle }}</h2>
        <span v-if="activeTitle" class="cpl__current" :title="activeTitle">{{ activeTitle }}</span>
      </div>

      <div class="cpl__bar-tools">
        <!--
          Прогресс курса. Показываем процент и «прочитано разделов»:
          одно число «45%» не отвечает на вопрос «сколько ещё», а вторая
          подпись отвечает.
        -->
        <div v-if="clampedProgress > 0 || isFullscreen" class="cpl__progress">
          <div
            class="cpl__progress-track"
            role="progressbar"
            :aria-valuenow="Math.round(progress)"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="$t('common.progress')"
          >
            <div class="cpl__progress-fill" :style="{ width: `${clampedProgress}%` }" />
          </div>
          <span class="cpl__progress-text">
            {{ Math.round(progress) }}% · {{ $t('courseManifest.contentsDone', { done: doneCount, total: materialCount }) }}
          </span>
        </div>

        <slot name="bar-tools" />

        <button
          type="button"
          class="cpl__icon-btn"
          :aria-pressed="isFullscreen"
          :title="$t('courseManifest.fullscreen')"
          @click="$emit('toggle-fullscreen')"
        >
          <v-icon size="20">{{ isFullscreen ? 'mdi-fullscreen-exit' : 'mdi-fullscreen' }}</v-icon>
          <span class="cpl__icon-label">{{ $t('courseManifest.fullscreen') }}</span>
        </button>
      </div>
    </header>

    <div class="cpl__body">
      <!-- Содержание курса -->
      <aside class="cpl__sidebar" :class="{ 'cpl__sidebar--hidden': !sidebarOpen }">
        <div class="cpl__sidebar-head">
          <span class="cpl__sidebar-title">{{ $t('courseManifest.contents') }}</span>

          <!--
            Управление деревом. Без «развернуть/свернуть всё» в курсе из
            77 разделов человек кликал по темам вручную, чтобы добраться
            до материалов; активный материал после перехода «следующий
            урок» оказывался свёрнутым и терялся в списке.
          -->
          <div class="cpl__sidebar-actions">
            <button
              type="button"
              class="cpl__icon-btn cpl__icon-btn--sm"
              :title="$t('courseManifest.expandAll')"
              @click="expandAll"
            >
              <v-icon size="18">mdi-unfold-more-horizontal</v-icon>
            </button>
            <button
              type="button"
              class="cpl__icon-btn cpl__icon-btn--sm"
              :title="$t('courseManifest.collapseAll')"
              @click="collapseAll"
            >
              <v-icon size="18">mdi-unfold-less-horizontal</v-icon>
            </button>
            <button
              type="button"
              class="cpl__icon-btn cpl__icon-btn--sm"
              :title="sidebarOpen ? $t('common.hide') : $t('common.show')"
              @click="$emit('toggle-sidebar')"
            >
              <v-icon size="18">{{ sidebarOpen ? 'mdi-chevron-left' : 'mdi-chevron-right' }}</v-icon>
            </button>
          </div>
        </div>

        <!--
          Инструменты страницы курса: поиск, избранное, форум.

          Слот стоит ДО дерева намеренно. Раньше всё это лежало в
          `sidebar-extra`, то есть под деревом из 77 разделов: при
          ограниченной высоте панели редактор заметок (413px) вытеснял
          список, и дерево схлопывалось до 16px, а панель инструментов
          уезжала под дерево.
        -->
        <slot name="sidebar-top" />

        <TreeView
          :nodes="treeNodes"
          :active-id="activeId"
          :expanded-nodes="expandedNodes"
          @select="handleSelectSection"
          @toggle="handleToggleSection"
        />

        <!--
          Заметок здесь нет намеренно. Раньше в этой же панели стоял
          свой блок на localStorage, но страница курса уже показывает
          MaterialNotes — он ходит в /api/materials/{id}/note, поэтому
          заметка переживает смену браузера и видна с другого устройства.
          Два редактора в колонке 300px читались как дубль, и человек не
          понимал, какой из них сохраняет.
        -->

        <slot name="sidebar-extra" />
      </aside>

      <!-- Материал -->
      <main class="cpl__content">
        <slot name="default" />

        <div v-if="!sections.length" class="cpl__empty">
          {{ $t('courseManifest.emptyContents') }}
        </div>

        <!-- Переходы между уроками в конце материала.

             События уходят с целевым материалом: раньше «следующий/
             предыдущий» эмитились без аргумента, и страница сама искала
             соседа по своему списку — две копии одной логики (здесь и в
             CourseManifest) могли разъехаться по порядку узлов. Здесь
             порядок один: плоский список sections, материалы — level 3.
        -->
        <nav v-if="hasNeighbours" class="cpl__pager" :aria-label="$t('common.navigation')">
          <button type="button" class="cpl__pager-btn" :disabled="!prevTarget" @click="goPrev">
            <v-icon size="18">mdi-chevron-left</v-icon>
            <span class="cpl__pager-label">{{ $t('courseManifest.prevLesson') }}</span>
          </button>
          <button type="button" class="cpl__pager-btn cpl__pager-btn--next" :disabled="!nextTarget" @click="goNext">
            <span class="cpl__pager-label">{{ $t('courseManifest.nextLesson') }}</span>
            <v-icon size="18">mdi-chevron-right</v-icon>
          </button>
        </nav>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import TreeView from './TreeView.vue'

const { t } = useI18n()

const props = defineProps({
  courseTitle: { type: String, default: '' },
  activeTitle: { type: String, default: '' },
  /** [{ id, title, state: 'done'|'partial'|'todo', level }] */
  sections: { type: Array, default: () => [] },
  activeId: { type: [String, Number], default: null },
  progress: { type: Number, default: 0 },
  sidebarOpen: { type: Boolean, default: true },
  isFullscreen: { type: Boolean, default: false },
})

const emit = defineEmits(['select-section', 'prev', 'next', 'toggle-sidebar', 'toggle-fullscreen'])

/** Символы отметок; состояние приходит снаружи. */
const MARK = { done: '✓', partial: '◐', todo: '—' }

const MARK_LABEL = {
  done: 'courseManifest.markDone',
  partial: 'courseManifest.markPartial',
  todo: 'courseManifest.markTodo',
}

const clampedProgress = computed(() => Math.min(100, Math.max(0, props.progress)))

/**
 * Преобразует плоский список sections в узлы для TreeView.
 * sections уже имеют свойство level (1=тема, 2=раздел, 3=материал).
 */
const treeNodes = computed(() => {
  return props.sections.map(section => ({
    id: section.id,
    title: section.title,
    level: section.level || 1,
    isMaterial: section.isMaterial === true || section.level === 3,
    state: section.state,
    originalSection: section, // для доступа к state и другим полям
  }))
})

/** Множество раскрытых узлов. */
const expandedNodes = ref(new Set())

/** Индекс «id → узел» и «id → родитель»: O(1) вместо поиска по списку. */
const nodeIndex = computed(() => {
  const byId = new Map()
  const parentById = new Map()

  // Плоский список дерева: родитель — ближайший предыдущий узел
  // с уровнем на единицу меньше (уровни идут подряд, без пропусков).
  const stack = []
  for (const node of treeNodes.value) {
    byId.set(node.id, node)
    while (stack.length && stack[stack.length - 1].level >= node.level) stack.pop()
    parentById.set(node.id, stack.length ? stack[stack.length - 1] : null)
    stack.push(node)
  }

  return { byId, parentById }
})

/** Раскрыть цепочку родителей активного материала и довести его до видуимости. */
const revealActive = () => {
  const id = props.activeId
  if (id == null) return

  const { byId, parentById } = nodeIndex.value
  const active = byId.get(id)
  if (!active) return

  const next = new Set(expandedNodes.value)
  let parent = parentById.get(id)
  while (parent) {
    next.add(parent.id)
    parent = parentById.get(parent.id)
  }
  expandedNodes.value = next

  // Прокрутка внутри панели содержания: после «следующий урок» активный
  // пункт мог оставаться за краем экрана — человек терял его из вида.
  requestAnimationFrame(() => {
    const panel = document.querySelector('.cpl__sidebar .u-tree')?.closest('.cpl__sidebar')
    const el = panel?.querySelector(`[data-node-id="${CSS.escape(String(id))}"]`)
    el?.scrollIntoView({ block: 'nearest' })
  })
}

// Автораскрытие при смене материала (первый кадр, resume, prev/next).
watch(() => props.activeId, revealActive, { immediate: true })

// Когда дерево только что приехало с сервера, activeId мог прийти раньше
// nodes — повторить раскрытие, как появятся узлы.
watch(treeNodes, () => {
  if (props.activeId != null) revealActive()
})

/** Обработка выбора узла (только материалы). */
const handleSelectSection = (node) => {
  if (node.isMaterial) {
    emit('select-section', node.originalSection)
  }
}

/** Переключение раскрытия узла. */
const handleToggleSection = (nodeId) => {
  const newExpanded = new Set(expandedNodes.value)
  if (newExpanded.has(nodeId)) {
    newExpanded.delete(nodeId)
  } else {
    newExpanded.add(nodeId)
  }
  expandedNodes.value = newExpanded
}

/** Развернуть / свернуть всё дерево (узлы-контейнеры). */
const expandAll = () => {
  const all = new Set(expandedNodes.value)
  for (const node of treeNodes.value) {
    if (!node.isMaterial) all.add(node.id)
  }
  expandedNodes.value = all
}

const collapseAll = () => {
  expandedNodes.value = new Set()
}

/** Материалы (только узлы уровня 3). */
const materials = computed(() => props.sections.filter((s) => s.isMaterial === true || s.level === 3))

/** Количество материалов. */
const materialCount = computed(() => materials.value.length)

/** Количество прочитанных материалов (state === 'done'). */
const doneCount = computed(() => materials.value.filter((m) => m.state === 'done').length)

/**
 * Соседний МАТЕРИАЛ относительно активного.
 *
 * Ориентируемся на список материалов (isMaterial=true).
 * Раньше переход шёл по всем узлам подряд, и «следующий» после темы
 * приводил на заголовок без файла.
 */
const neighbour = (offset) => {
  const mats = materials.value
  if (!mats.length) return null

  const index = mats.findIndex((s) => s.id === props.activeId)
  if (index === -1) {
    // Активен заголовок (например, после смены курса): отправляем
    // к первому материалу в нужную сторону.
    return offset > 0 ? mats[0] : mats[mats.length - 1]
  }

  return mats[index + offset] ?? null
}

const hasNeighbours = computed(() => {
  const mats = materials.value
  if (!mats.length) return false
  const index = mats.findIndex((s) => s.id === props.activeId)
  if (index === -1) return false
  return index > 0 || index < mats.length - 1
})

const canPrev = computed(() => {
  const mats = materials.value
  if (!mats.length) return false
  const index = mats.findIndex((s) => s.id === props.activeId)
  if (index === -1) return false
  return index > 0
})

const canNext = computed(() => {
  const mats = materials.value
  if (!mats.length) return false
  const index = mats.findIndex((s) => s.id === props.activeId)
  if (index === -1) return false
  return index < mats.length - 1
})

const goPrev = () => {
  const target = neighbour(-1)
  if (target) emit('prev', target.originalSection)
}

const goNext = () => {
  const target = neighbour(1)
  if (target) emit('next', target.originalSection)
}
</script>

<style scoped>
.cpl {
  display: flex;
  flex-direction: column;

  /*
   * Высота фиксированная, а не «не меньше».
   *
   * Регресс, который закрывает этот блок: стояло min-height, поэтому
   * колонка содержания, у которой нет своего ограничения, разрасталась
   * под дерево из 77 разделов — на замере боковая панель была 5148px при
   * окне 856px. Панель инструментов (поиск, избранное, дерево) уезжала
   * на 4765px вниз и оказывалась за краем экрана: кнопок не было видно,
   * а `.cpl` с overflow:hidden отрезал низ. Прокручивался документ
   * целиком вместо колонок по отдельности.
   *
   * Теперь высота равна высоте окна, и обе колонки прокручиваются
   * внутри себя, как и задумано.
   */
  height: calc(100vh - 96px);
  max-height: calc(100vh - 96px);
  background: var(--c-surface-2);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.cpl__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  flex-wrap: wrap;
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--c-border);
  background: var(--c-surface-1);
}

.cpl__bar-main {
  display: flex;
  align-items: baseline;
  gap: var(--sp-3);
  min-width: 0;
}

.cpl__title {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cpl__current {
  font-size: var(--fs-sm);
  color: var(--c-text-2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cpl__bar-tools {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.cpl__progress {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  min-width: 180px;
}

.cpl__progress-track {
  flex: 1;
  height: 6px;
  border-radius: 999px;
  background: var(--c-surface-3);
  overflow: hidden;
}

.cpl__progress-fill {
  height: 100%;
  background: var(--c-primary);
  transition: width var(--page-transition-dur) var(--page-transition-ease);
}

.cpl__progress-text {
  font-size: var(--fs-xs);
  color: var(--c-text-2);
  white-space: nowrap;
}

.cpl__icon-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-2);
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  background: none;
  color: var(--c-text-2);
  cursor: pointer;
  font-size: var(--fs-sm);
}

.cpl__icon-btn:hover {
  background: var(--c-surface-3);
  color: var(--c-text-1);
}

.cpl__icon-btn[aria-pressed='true'] {
  color: var(--c-primary);
}

.cpl__icon-btn--sm {
  padding: var(--sp-1);
}

.cpl__body {
  display: flex;
  flex: 1;
  /*
   * min-height: 0 обязателен во flex-контейнере: иначе колонка не может
   * стать ниже содержимого и распирает родителя, даже если он ограничен.
   * overflow: hidden не даёт содержимому выйти за пределы панели.
   */
  min-height: 0;
  overflow: hidden;
}

.cpl__sidebar {
  width: var(--layout-drawer-width);
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--c-border);
  background: var(--c-surface-1);
  transition: width var(--page-transition-dur) var(--page-transition-ease);
}

/*
 * Свёрнутое состояние — рельс: узкая колонка отметок о прохождении.
 *
 * Раньше здесь стояло width:44px вместе с display:none у списка, но слот
 * sidebar-extra с кнопками («Форум», инструменты, поиск) скрывался НЕ
 * сколько: он продолжал рендериться в колонке 44px, и панель
 * переполнялась — иконки вылезали за край и ломались. Теперь в рельсе
 * остаётся ровно то, что в 44px помещается: кнопка разворачивания и
 * колонка отметок материалов.
 */
.cpl__sidebar--hidden {
  width: var(--layout-drawer-width-collapsed, 48px);
  align-items: center;
}

.cpl__sidebar--hidden .cpl__sidebar-title,
.cpl__sidebar--hidden .cpl__heading,
/* Слот приходит извне, поэтому только через :deep. */
.cpl__sidebar--hidden :deep(.u-tree) {
  display: none;
}

.cpl__sidebar--hidden .cpl__sidebar-head {
  padding: var(--sp-2) 0;
  justify-content: center;
  border-bottom: none;
}

.cpl__sidebar--hidden .cpl__sections {
  padding: var(--sp-1) 0;
  width: 100%;
}

.cpl__sidebar--hidden .cpl__section {
  /* Отметка по центру, без подписи и без отступа по уровню. */
  padding: var(--sp-2) 0;
  min-height: 32px;
  border-radius: 0;
  justify-content: center;
}

.cpl__sidebar--hidden .cpl__section-text {
  display: none;
}

.cpl__sidebar--hidden .cpl__mark {
  width: auto;
  font-size: var(--fs-base);
}

.cpl__sidebar-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-2) var(--sp-3);
  border-bottom: 1px solid var(--c-border);
}

.cpl__sidebar-title {
  font-size: var(--fs-xs);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--c-text-2);
}

.cpl__sections {
  flex: 1;
  overflow-y: auto;
  padding: var(--sp-2) var(--sp-1);
}

.cpl__empty {
  padding: var(--sp-4);
  color: var(--c-text-2);
  text-align: center;
  font-size: var(--fs-sm);
}

.cpl__section {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  width: 100%;
  padding: var(--sp-2);
  border: none;
  border-radius: var(--radius-sm);
  background: none;
  color: var(--c-text-1);
  text-align: left;
  font-size: var(--fs-sm);
  cursor: pointer;
}

.cpl__section:hover {
  background: var(--c-surface-3);
}

/* Отступ материала — он всегда вложен в раздел. */
.cpl__section--material { padding-inline-start: var(--sp-5); }

.cpl__section--active {
  background: var(--c-primary-soft);
  color: var(--c-primary);
  font-weight: 600;
}

/*
 * Тема и раздел — заголовки структуры.
 *
 * Это не элемент управления: курсор по умолчанию, без фокуса с клавиатуры
 * и без роли button. Раньше это был <button> с cursor:pointer, и клик по
 * заголовку выглядел как переход, хотя ничего не открывал.
 */
.cpl__heading {
  padding: var(--sp-2) var(--sp-3) var(--sp-1);
  color: var(--c-text-secondary);
}

.cpl__heading--l1 {
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
}

.cpl__heading--l2 {
  font-size: var(--fs-xs);
  padding-inline-start: var(--sp-5);
}

.cpl__heading-text {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cpl__mark {
  flex-shrink: 0;
  width: 1em;
  text-align: center;
  color: var(--c-text-muted);
}

/* Прочитанный раздел. Раньше «посещено» красилось бледно-серым,
   приглушённым текстом, что в тёмной теме сливалось с фоном панели:
   отметка о прочтении была почти не видна. */
.cpl__mark--done {
  color: var(--c-success);
}

.cpl__mark--partial {
  color: var(--c-warning);
}

.cpl__section-text {
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.cpl__content {
  flex: 1;
  min-width: 0;
  min-height: 320px;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.cpl__pager {
  margin-top: auto;
  display: flex;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-4);
  border-top: 1px solid var(--c-border);
  background: var(--c-surface-1);
}

.cpl__pager-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-2) var(--sp-3);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  background: var(--c-surface-1);
  color: var(--c-text-1);
  font-size: var(--fs-sm);
  cursor: pointer;
}

.cpl__pager-btn:hover:not(:disabled) {
  border-color: var(--c-primary);
  color: var(--c-primary);
}

.cpl__pager-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* Полноэкранный режим чтения: убираем «хром» приложения,
   материал занимает весь экран. */
.cpl--fullscreen {
  position: fixed;
  inset: 0;
  z-index: 1200;
  border-radius: 0;
  height: 100vh;
}

@media (max-width: 1024px) {
  .cpl__sidebar {
    width: var(--layout-drawer-width-tablet, 260px);
  }
}

@media (max-width: 768px) {
  .cpl__sidebar {
    width: 100%;
    position: absolute;
    inset: 0;
    z-index: 2;
  }

  .cpl__sidebar--hidden {
    display: none;
  }

  .cpl__bar {
    gap: var(--sp-2);
  }

  .cpl__progress {
    min-width: 120px;
  }

  .cpl__icon-label {
    display: none;
  }
}
</style>
