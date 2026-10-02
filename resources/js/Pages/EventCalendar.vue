<template>
  <div class="u-page">
    <PageHeader :title="$t('calendar.title')" :subtitle="$t('calendar.subtitle')">
      <template #actions>
        <v-btn
          color="primary"
          variant="tonal"
          :loading="loading"
          :disabled="loading"
          @click="load()"
        >
          <v-icon start icon="mdi-refresh" size="18" aria-hidden="true"></v-icon>
          {{ $t("common.refresh") }}
        </v-btn>
      </template>
    </PageHeader>

    <!-- Фильтры. Область данных ограничена на сервере, поэтому фильтр
         «группа» у обучаемого содержит ровно одну его группу. -->
    <section class="u-card calendar__filters">
      <div class="calendar__filter">
        <label class="calendar__filter-label" :for="`cal-group-${uid}`">{{ $t("calendar.filterGroup") }}</label>
        <v-select
          :id="`cal-group-${uid}`"
          :items="filterItems(filters.groups)"
          item-title="title"
          item-value="id"
          v-model="query.group_id"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          :placeholder="$t('calendar.allGroups')"
        ></v-select>
      </div>

      <div class="calendar__filter">
        <label class="calendar__filter-label" :for="`cal-course-${uid}`">{{ $t("calendar.filterCourse") }}</label>
        <v-select
          :id="`cal-course-${uid}`"
          :items="filterItems(filters.courses)"
          item-title="title"
          item-value="id"
          v-model="query.course_id"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          :placeholder="$t('calendar.allCourses')"
        ></v-select>
      </div>

      <div class="calendar__filter">
        <label class="calendar__filter-label" :for="`cal-category-${uid}`">{{ $t("calendar.filterCategory") }}</label>
        <v-select
          :id="`cal-category-${uid}`"
          :items="filterItems(filters.categories)"
          item-title="title"
          item-value="id"
          v-model="query.category_id"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          :placeholder="$t('calendar.allCategories')"
        ></v-select>
      </div>

      <div class="calendar__filter">
        <span class="calendar__filter-label">{{ $t("calendar.filterStatus") }}</span>
        <div class="calendar__chips">
          <button
            v-for="tab in statusTabs"
            :key="tab.key"
            type="button"
            class="cats-chip"
            :class="{ 'cats-chip--on': query.status === tab.key }"
            :aria-pressed="query.status === tab.key"
            @click="setStatus(tab.key)"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!--
        Тип события: полосы периодов и метки сроков сдачи — это разные
        вещи. Период отвечает на вопрос «когда группа занимается»,
        дедлайн — «когда сдавать». В одном списке они сливались, и найти
        срок сдачи взглядом было невозможно.
      -->
      <div class="calendar__filter">
        <span class="calendar__filter-label">{{ $t("calendar.filterKind") }}</span>
        <div class="calendar__chips">
          <button
            v-for="tab in kindTabs"
            :key="tab.key"
            type="button"
            class="cats-chip"
            :class="{ 'cats-chip--on': query.kind === tab.key }"
            :aria-pressed="query.kind === tab.key"
            @click="setKind(tab.key)"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>
    </section>

    <!-- Активные фильтры видны текстом: по одному выбранному курсу из
         19 нельзя понять, почему в календаре всего два периода. -->
    <div v-if="activeFilterLabels.length" class="calendar__active">
      <span class="calendar__active-label">{{ $t("calendar.filteredBy") }}</span>
      <span v-for="label in activeFilterLabels" :key="label" class="u-badge u-badge--primary">
        {{ label }}
      </span>
      <v-btn variant="text" size="x-small" @click="resetFilters">
        {{ $t("calendar.resetFilters") }}
      </v-btn>
    </div>

    <section ref="board" class="u-card calendar__board">
      <div class="calendar__board-head">
        <span class="u-page__subtitle">
          {{ $t("calendar.periodsShown", { count: events.length }) }}
        </span>
      </div>

      <div v-if="loading" class="d-flex justify-center calendar__loader">
        <v-progress-circular indeterminate :aria-label="$t('common.loading')"></v-progress-circular>
      </div>

      <EmptyState
        v-else-if="!events.length"
        :icon="'mdi-calendar-remove-outline'"
        :title="hasFilters ? $t('calendar.emptyFiltered') : $t('calendar.emptyTitle')"
        :text="hasFilters ? $t('calendar.emptyFilteredText') : $t('calendar.emptyText')"
      ></EmptyState>

      <!--
        key по набору фильтров: FullCalendar не перечитывает events,
        если ссылка на массив осталась той же. Смена фильтра должна
        приводить к новому массиву и перерисовке.

        Слот #eventContent здесь сознательно НЕ используется. Кастомная
        вёрстка внутри события задавала колонкам минимальную ширину по
        самому длинному названию: таблица дней разъезжалась за правый
        край карточки (1230px в блок 948px) и появлялась горизонтальная
        прокрутка. Однострочную подпись библиотека сокращает сама
        (многоточие), а полные данные — в подсказке и в карточке
        по клику.
      -->
      <FullCalendar
        v-else
        :key="boardKey"
        ref="calendar"
        :options="calendarOptions"
      ></FullCalendar>
    </section>

    <!-- Карточка периода по клику. Раньше клик по событию предлагал
         confirm() «удалить событие» — то есть демо-удаление из памяти. -->
    <v-dialog v-model="detail" max-width="560">
      <v-card v-if="selected">
        <v-card-title class="calendar__detail-title">{{ selected.course_title }}</v-card-title>
        <v-card-text>
          <dl class="calendar__facts">
            <div class="calendar__fact">
              <dt>{{ $t("calendar.detailGroup") }}</dt>
              <dd>{{ selected.group_name || "—" }}</dd>
            </div>
            <div class="calendar__fact">
              <dt>{{ $t("plan.period") }}</dt>
              <dd>{{ formatDate(selected.study_from) }} — {{ formatDate(selected.study_to) }}</dd>
            </div>
            <div v-if="selected.module_title" class="calendar__fact">
              <dt>{{ $t("plan.module") }}</dt>
              <dd>{{ selected.module_title }}</dd>
            </div>
            <div v-if="selected.lesson_type" class="calendar__fact">
              <dt>{{ $t("plan.lessonType") }}</dt>
              <dd>{{ selected.lesson_type }}</dd>
            </div>
            <div v-if="selected.teacher" class="calendar__fact">
              <dt>{{ $t("calendar.detailTeacher") }}</dt>
              <dd>{{ selected.teacher }}</dd>
            </div>
            <div class="calendar__fact">
              <dt>{{ $t("calendar.detailStatus") }}</dt>
              <dd>
                <span class="u-badge" :class="statusBadge(selected.status)">
                  {{ $t(`plan.status.${selected.status}`) }}
                </span>
              </dd>
            </div>
            <div class="calendar__fact calendar__fact--wide">
              <dt>{{ $t("plan.specialties") }}</dt>
              <dd>
                <span
                  v-for="cat in selected.categories"
                  :key="cat.id"
                  class="u-badge u-badge--muted calendar__cat"
                >
                  {{ cat.title }}
                </span>
                <span v-if="!selected.categories?.length">—</span>
              </dd>
            </div>
          </dl>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <!--
            Материалы открываются в НОВОМ окне: это полноэкранный
            просмотрщик с деревом курса, возвращаться из него неудобно.
            Без target="_blank" он замещал текущую страницу, и кнопка
            «Назад» возвращала в список, а не к календарю.
          -->
          <v-btn
            v-if="selected.course_id"
            color="primary"
            variant="flat"
            :to="{ name: 'courses.itemmani', query: { idEdit: selected.course_id } }"
            target="_blank"
            rel="noopener"
            :title="$t('courses.list.openManifestHint')"
          >
            <v-icon start icon="mdi-open-in-new" size="18" aria-hidden="true"></v-icon>
            {{ $t("plan.openMaterials") }}
          </v-btn>
          <v-btn variant="text" @click="detail = false">{{ $t("calendar.close") }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <AppToast v-model="alert" :type="alertType" :text="alertText"></AppToast>
  </div>
</template>

<script>
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import listPlugin from "@fullcalendar/list";
import ruLocale from "@fullcalendar/core/locales/ru";

import PageHeader from "../components/ui/PageHeader.vue";
import EmptyState from "../components/ui/EmptyState.vue";
import AppToast from "../components/ui/AppToast.vue";
import calendarApi from "../api/calendar.api";

/**
 * Календарь учебного процесса.
 *
 * Переписан поверх демо-шаблона FullCalendar, который стоял здесь раньше:
 * он показывал два захардкоженных события, создавал новые через prompt(),
 * удалял через confirm() и ничего не сохранял — после перезагрузки всё
 * исчезало. Таблица events при этом была пуста, и никто в неё не писал.
 *
 * Теперь календарь показывает то, что действительно записано в базе:
 * периоды обучения групп (group2learnings.study_from/study_to).
 * События read-only намеренно — их источник не календарь, а запись
 * группы на курс, и редактирование здесь создавало бы вторую правду.
 */
let uidCounter = 0;

export default {
  name: "TrainingCalendar",

  components: { PageHeader, EmptyState, AppToast, FullCalendar },

  data() {
    const uid = `cal-${++uidCounter}`;

    return {
      uid,
      resizeObserver: null,
      lastBoardWidth: 0,
      events: [],
      filters: { groups: [], courses: [], categories: [] },
      query: { group_id: null, course_id: null, category_id: null, status: null, kind: null },
      loading: false,
      detail: false,
      selected: null,
      alert: false,
      alertType: "success",
      alertText: "",
    };
  },

  computed: {
    hasFilters() {
      return Boolean(this.query.group_id || this.query.course_id || this.query.category_id || this.query.status || this.query.kind);
    },

    /**
     * Ключ перерисовки доски. FullCalendar обновляется через events,
     * но мутируемый массив он не отслеживает — поэтому при смене фильтра
     * даём ему новый ключ и свежий массив.
     */
    boardKey() {
      return `${this.uid}-${this.query.group_id}-${this.query.course_id}-${this.query.category_id}-${this.query.status}-${this.query.kind}`;
    },

    /**
     * Календарь в разметке приложения: светлые поверхности, тот же шрифт,
     * что и на остальных страницах. Раньше здесь были стили самого
     * демо-шаблона (Arial, #eaf9ff), не совпадавшие с дизайн-системой.
     */
    calendarOptions() {
      return {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,dayGridMonth,listWeek",
        },
        buttonText: { today: this.$t("calendar.today"), month: this.$t("calendar.month"), week: this.$t("calendar.week"), list: this.$t("calendar.list") },
        initialView: "dayGridMonth",
        events: this.events,
        // События приходят с сервера и правятся через group2learning,
        // поэтому перетаскивание и выделение отключены: без сохранения
        // перетаскивание вводило бы в заблуждение.
        editable: false,
        selectable: false,
        dayMaxEvents: 3,
        weekends: true,
        locale: ruLocale,
        firstDay: 1,
        height: "auto",
        eventClick: this.openDetail,
        eventTimeFormat: { hour: "2-digit", minute: "2-digit", hour12: false },
        displayEventTime: false,
        // Однострочная подпись: курс и модуль через точку. Та же строка
        // уходит в нативную подсказку браузера (title), поэтому полное
        // название курса не теряется при сокращении в ячейке.
        eventDisplay: 'block',
        dayHeaderFormat: { weekday: "short", day: "numeric" },
        noEventsText: this.$t("calendar.emptyTitle"),
        noEventsContent: this.$t("calendar.emptyText"),
      };
    },

    statusTabs() {
      return [
        { key: null, label: this.$t("calendar.filterAll") },
        { key: "active", label: this.$t("plan.status.active") },
        { key: "planned", label: this.$t("plan.status.planned") },
        { key: "completed", label: this.$t("plan.status.completed") },
      ];
    },

    kindTabs() {
      return [
        { key: null, label: this.$t("calendar.kindAll") },
        { key: "period", label: this.$t("calendar.kindPeriod") },
        { key: "deadline", label: this.$t("calendar.kindDeadline") },
      ];
    },

    activeFilterLabels() {
      const labels = [];
      const { groups, courses, categories } = this.filters;

      const title = (list, id) => list.find((i) => i.id === id)?.title ?? `#${id}`;

      if (this.query.group_id) labels.push(this.$t("calendar.filterGroup") + ": " + title(groups, this.query.group_id));
      if (this.query.course_id) labels.push(this.$t("calendar.filterCourse") + ": " + title(courses, this.query.course_id));
      if (this.query.category_id) labels.push(this.$t("calendar.filterCategory") + ": " + title(categories, this.query.category_id));
      if (this.query.status) labels.push(this.$t("calendar.filterStatus") + ": " + this.$t(`plan.status.${this.query.status}`));
      if (this.query.kind) {
        // Ключи плоские (calendar.kindDeadline), а не вложенные
        // (calendar.kind.deadline): динамический путь показывал пользователю
        // сырое «calendar.kind.deadline» вместо перевода.
        labels.push(
          this.$t("calendar.filterKind") + ": "
          + this.$t(`calendar.kind${this.capitalize(this.query.kind)}`)
        );
      }

      return labels;
    },
  },

  watch: {
    // Фильтры применяются автоматически: кнопки «применить» в календаре
    // не нужны, список вариантов короткий.
    "query.group_id": "load",
    "query.course_id": "load",
    "query.category_id": "load",
    "query.kind": "load",
  },

  created() {
    this.load();
  },

  mounted() {
    // FullCalendar измеряет контейнер ОДИН раз при инициализации. На этой
    // странице к тому моменту ширина ещё не устоялась (подгружаются
    // шрифты, считается футер, меняется наличие полосы прокрутки), и
    // календарь запоминал ширику больше фактической: таблица дней не
    // помещалась в .fc-scroller-harness, колонки «сб»/«вс» обрезались,
    // а страница получала горизонтальную прокрутку.
    //
    // Отложенный вызов updateSize (nextTick, rAF, document.fonts.ready)
    // помогал не полностью — 1230 -> 997 при блоке 934, то есть расхождение
    // оставалось. Поэтому следим за контейнером штатным ResizeObserver:
    // как только реальная ширина отличается от той, по которой календарь
    // построил сетку, библиотеке сообщается пересчитать размер.
    this.$nextTick(() => {
      this.syncSize();
      this.observeResize();
    });
  },

  beforeUnmount() {
    this.resizeObserver?.disconnect();
    this.resizeObserver = null;
  },

  methods: {
    /**
     * Пересчитывает размер доски под фактическую ширину контейнера.
     *
     * Замер отложенный: FullCalendar измеряет контейнер один раз при
     * инициализации, а на этой странице к тому моменту раскладка ещё не
     * устоялась (подгружаются шрифты, считается футер). Из-за этого
     * запоминалась ширина 1230px при фактических 948px: таблица дней
     * не помещалась в .fc-scroller-harness, и колонки «сб»/«вс»
     * обрезались.
     *
     * Почему именно так: проверено, что любое событие resize приводит
     * ширину к правильной (1230 -> 930), то есть дело именно в тайминге
     * замера, а не в раскладке. Ширина через CSS (width: 100%) не
     * помогает — библиотека всё равно меряет сама.
     *
     * Отложенные вызовы (nextTick, rAF, document.fonts.ready) помогали
     * не полностью — оставалось 1230 -> 997 при блоке 934. Поэтому
     * слежение ведётся через ResizeObserver, а этот метод только
     * сообщает библиотеке пересчитать размер.
     */
    syncSize() {
      this.$refs.calendar?.getApi?.()?.updateSize?.();
    },

    capitalize(value) {
      const str = String(value ?? '');
      return str.charAt(0).toUpperCase() + str.slice(1);
    },

    /**
     * Следит за шириной доски и пересчитывает календарь при её изменении.
     *
     * Важно: последнюю ширину запоминаем и сравниваем с той, по которой
     * библиотека уже пересчитала. Иначе updateSize() вызывается на
     * каждом срабатывании наблюдателя, а он реагирует и на собственные
     * изменения — получается бесконечный цикл перерисовок.
     */
    observeResize() {
      const board = this.$refs.board;
      if (!board || typeof ResizeObserver === 'undefined') return;

      this.resizeObserver = new ResizeObserver((entries) => {
        const width = Math.round(entries[0]?.contentRect?.width ?? 0);

        if (!width || width === this.lastBoardWidth) return;

        this.lastBoardWidth = width;
        this.syncSize();
      });

      this.resizeObserver.observe(board);
    },

    filterItems(list) {
      return (list ?? []).map((i) => ({ title: i.title, value: i.id }));
    },

    /**
     * Подпись периода: «Курс · Модуль».
     *
     * Однострочная — иначе кастомный контент раздувает колонки
     * календаря (см. комментарий у <FullCalendar>).
     */
    eventLabel(event) {
      const p = event.extendedProps ?? {};
      const label = [p.course_title || event.title, p.module_title].filter(Boolean).join(' · ');

      /*
       * У метки срока сдачи бэкенд уже присылает готовую подпись с
       * префиксом («Срок сдачи: …»), поэтому берём её как есть.
       * Раньше здесь префикс просто не добавлялся, но собранная из
       * course/module подпись всё равно возвращалась — и метка
       * выглядела как ещё одна полоса периода.
       */
      if (p.kind === 'deadline' && event.title) {
        return event.title;
      }

      return label;
    },

    async load() {
      this.loading = true;

      try {
        const response = await calendarApi.fetchEvents(this.query);
        // Собираем подпись здесь, а не в шаблоне: так она попадает и в
        // ячейку, и в подсказку, и остаётся одной строкой.
        this.events = calendarApi.events(response).map((e) => ({
          ...e,
          title: this.eventLabel(e),
          classNames: [`cal-event--${(e.extendedProps ?? {}).kind ?? 'period'}`],
        }));
        this.filters = calendarApi.filters(response);

        // Данные пришли — пересчитываем размер под фактическую ширину.
        await this.$nextTick();
        this.syncSize();
      } catch (e) {
        // Пустой календарь — не ошибка (никого не записали). Сообщаем
        // только об отказе сервера или запрете доступа.
        this.events = [];

        if (e?.response?.status === 403) {
          this.notify(this.$t("calendar.forbidden"), "error");
        } else if (e?.response?.status >= 400) {
          this.notify(this.$t("calendar.loadError"), "error");
        }
      } finally {
        this.loading = false;
      }
    },

    setStatus(key) {
      this.query.status = this.query.status === key ? null : key;
      this.load();
    },

    setKind(key) {
      this.query.kind = this.query.kind === key ? null : key;
      this.load();
    },

    resetFilters() {
      this.query.group_id = null;
      this.query.course_id = null;
      this.query.category_id = null;
      this.query.status = null;
      this.query.kind = null;
      this.load();
    },

    openDetail(info) {
      this.selected = info.event.extendedProps;
      this.detail = true;
    },

    /**
     * Даты приходят ISO-строкой. Разбираем вручную, а не через Date,
     * чтобы не сдвинуть дату на сутки из-за локальной зоны браузера.
     */
    formatDate(value) {
      const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || "");
      return m ? `${m[3]}.${m[2]}.${m[1]}` : value ?? "—";
    },

    statusBadge(status) {
      if (status === "completed") return "u-badge--success";
      if (status === "planned") return "u-badge--muted";
      return "u-badge--warning";
    },

    notify(text, type = "success") {
      this.alertText = text;
      this.alertType = type;
      this.alert = true;
    },
  },
};
</script>

<style scoped>
.calendar__filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: var(--sp-3);
  padding: var(--sp-4);
  margin-bottom: var(--sp-3);
}

.calendar__filter-label {
  display: block;
  margin-bottom: var(--sp-1);
  color: var(--c-text-muted);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.calendar__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-1);
}

.calendar__active {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--sp-2);
  margin-bottom: var(--sp-3);
}

.calendar__active-label {
  color: var(--c-text-muted);
  font-size: 0.8125rem;
}

.calendar__board {
  padding: var(--sp-4);
}

.calendar__board-head {
  margin-bottom: var(--sp-2);
}

.calendar__loader {
  padding: var(--sp-8) 0;
}

.calendar__detail-title {
  font-size: 1.125rem;
  font-weight: 600;
}

.calendar__facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--sp-3);
  margin: 0;
}

.calendar__fact--wide {
  grid-column: 1 / -1;
}

.calendar__fact dt {
  margin-bottom: var(--sp-1);
  color: var(--c-text-muted);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.calendar__fact dd {
  margin: 0;
  font-size: 0.875rem;
}

.calendar__cat {
  margin: 0 var(--sp-1) var(--sp-1) 0;
}

</style>

<!--
  Стили FullCalendar: подгоняем под поверхности и шрифт приложения.
  Раньше здесь стояли стили демо-шаблона (Arial, #eaf9ff) — календарь
  выглядел как чужеродный блок внутри интерфейса.
-->
<style>
.calendar__board .fc {
  /*
   * Ширина фиксируется явно.
   *
   * FullCalendar измеряет контейнер один раз при инициализации, и на этой
   * странице к тому моменту ширина ещё не устоялась: он запоминал 1230px
   * при фактических 948px, после чего таблица дней не помещалась в
   * .fc-scroller-harness и колонки «сб»/«вс» обрезались. Проверено:
   * любое событие resize приводит ширину к правильной — то есть дело
   * именно в тайминге замера, а не в раскладке.
   */
  width: 100%;
  max-width: 100%;

  --fc-border-color: var(--c-border);
  --fc-page-bg-color: var(--c-surface);
  --fc-today-bg-color: var(--c-primary-soft);
  --fc-neutral-bg-color: var(--c-surface-2);
  --fc-list-event-hover-bg-color: var(--c-surface-2);
  font-family: inherit;
  font-size: 0.875rem;
}

.calendar__board .fc .fc-toolbar-title {
  font-size: 1.0625rem;
  font-weight: 600;
}

.calendar__board .fc .fc-button {
  background: var(--c-surface);
  border: 1px solid var(--c-border-strong);
  color: var(--c-text);
  font-size: 0.8125rem;
  text-transform: none;
  box-shadow: none;
}

.calendar__board .fc .fc-button:hover,
.calendar__board .fc .fc-button:focus {
  background: var(--c-surface-2);
  border-color: var(--c-primary);
  color: var(--c-primary);
}

.calendar__board .fc .fc-button-primary:not(:disabled).fc-button-active,
.calendar__board .fc .fc-button-primary:not(:disabled):active {
  background: var(--c-primary);
  border-color: var(--c-primary);
  color: #fff;
}

.calendar__board .fc .fc-col-header-cell-cushion,
.calendar__board .fc .fc-daygrid-day-number {
  color: var(--c-text);
  text-decoration: none;
}

.calendar__board .fc .fc-daygrid-day-number:hover {
  color: var(--c-primary);
}

/*
 * Периоды обучения — сплошная полоса во всю ширину дня, а не «точка».
 *
 * Заливка сплошная, а не приглушённая, и текст белый: FullCalendar задаёт
 * цвет текста события через --fc-event-text-color (#fff) на элементах
 * собственной вёрстки. При светлой заливке получался белый текст на
 * светло-голубом — нечитаемо (проверено: computed color = rgb(255,255,255)
 * при фоне #e8f0fb).
 *
 * Поэтому цвет задан явно для самой полосы И для вложенных элементов
 * слот-разметки, с селекторами выше по специфичности, чем у FullCalendar,
 * иначе тема библиотеки снова перебивает.
 */
.calendar__board .fc .fc-daygrid-event {
  border: none;
  border-radius: var(--radius-sm);
  background: var(--c-primary);
  color: #fff;
  padding: 2px var(--sp-2);
  cursor: pointer;
}

.calendar__board .fc .fc-daygrid-event:hover,
.calendar__board .fc .fc-daygrid-event:focus-visible {
  background: var(--c-primary);
  filter: brightness(0.9);
  color: #fff;
}

/*
 * Метка срока сдачи.
 *
 * Цвет намеренно другой — предупреждающий, а не основной: полосы
 * периодов это фон учебного процесса, а метки «когда сдавать» то, что
 * человек ищет в календаре в первую очередь. Плюс пунктирная рамка,
 * чтобы тип читался даже при печати в grayscale.
 */
/* Внутренние элементы события наследуют цвет полосы, иначе тема
   библиотеки снова красит текст в белый по своим селекторам. */
.calendar__board .fc .fc-daygrid-event .fc-event,
.calendar__board .fc .fc-daygrid-event .fc-event-main,
.calendar__board .fc .fc-daygrid-event .fc-event-title,
.calendar__board .fc .fc-daygrid-event .fc-event-time {
  color: #fff;
}

/*
 * Заливка — --c-warning-soft, текст — --c-warning.
 *
 * Заливка основным --c-warning давала тёмный янтарь (#9a6400) с обычным
 * тёмным текстом: тёмное по тёмному, метка читалась хуже полосы
 * периода. Soft-вариант — тот же приём, что у бейджей
 * .u-badge--warning, и в тёмной теме работает автоматически.
 */
.calendar__board .fc .fc-daygrid-event.cal-event--deadline {
  background: var(--c-warning-soft);
  border: 1px dashed var(--c-warning);
  font-weight: 600;
}

.calendar__board .fc .fc-daygrid-event.cal-event--deadline:hover {
  background: var(--c-warning-soft);
  filter: brightness(0.97);
}

.calendar__board .fc .fc-daygrid-event.cal-event--deadline .fc-event,
.calendar__board .fc .fc-daygrid-event.cal-event--deadline .fc-event-main,
.calendar__board .fc .fc-daygrid-event.cal-event--deadline .fc-event-title {
  color: var(--c-warning);
}

/* Список (list view) — на светлой подложке, текст обычный */
.calendar__board .fc .fc-list-event .fc-event-title {
  color: var(--c-text);
}

.calendar__board .fc .fc-list-event:hover td {
  background: var(--c-surface-2);
}
</style>
