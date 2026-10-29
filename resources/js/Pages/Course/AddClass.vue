<template>
  <!--
    Обёртка u-page + PageHeader добавлены потому, что страница шла
    «голым фрагментом» без единого заголовка: на /classes не было ни
    одного h1-h3, то есть у страницы не было имени — ни для человека,
    ни для скринридера, ни для якоря «к содержанию» (WCAG 2.2 SC 1.3.1,
    2.4.1). Заодно появился корректный единственный h1.

    Редизайн: раньше страница была combobox'ом «в пустоту» — список
    папок ничем не отличался друг от друга, было непонятно, какие
    классы уже импортированы и что вообще лежит на диске. Теперь слева
    форма импорта, справа галерея карточек по данным /api/classesfsmeta
    (число АУК, наличие манифеста, размер, статус импорта) с поиском,
    фильтрами и скелетонами загрузки. Контракт /api/classesfs при этом
    не тронут.
  -->
  <div class="u-page classes-page">
    <PageHeader :title="$t('classes.title')" :subtitle="$t('classes.subtitle')" />

    <v-alert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      :text="errorMessage"
      @click:close="errorMessage = ''"
    ></v-alert>
    <v-alert
      v-if="successMessage"
      type="success"
      variant="tonal"
      class="mb-4"
      closable
      closable-label="Закрыть"
      :text="successMessage"
      @click:close="successMessage = ''"
    ></v-alert>

    <div class="classes-layout">
      <!-- ------------------------------- форма ------------------------------ -->
      <section class="classes-form" aria-labelledby="classes-form-title">
        <v-card class="u-card u-card--elevated" flat>
          <v-card-title class="classes-card__title" id="classes-form-title">
            <v-icon icon="mdi-import" start></v-icon>
            {{ $t("classes.importTitle") }}
          </v-card-title>

          <v-card-text>
            <p class="classes-form__hint">{{ $t("classes.importHint") }}</p>

            <v-form ref="form" @submit.prevent="submitForm">
              <v-combobox
                v-model="path"
                :items="tags"
                item-title="name"
                item-value="name"
                return-object
                clearable
                :menu-icon="'mdi-chevron-down'"
                :prepend-inner-icon="'mdi-airplane'"
                :label="$t('course.labelVyberite-klass-dlya-dobavleniya')"
                :disabled="loading"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item
                    v-bind="itemProps"
                    :title="item.raw.name"
                    :subtitle="$t('classes.aukCount', { count: item.raw.auk_count })"
                  >
                    <template #append>
                      <v-chip
                        v-if="item.raw.imported"
                        size="x-small"
                        variant="flat"
                        color="success"
                        prepend-icon="mdi-check"
                      >
                        {{ $t("classes.badgeImported") }}
                      </v-chip>
                      <v-chip
                        v-else-if="item.raw.manifests === 0"
                        size="x-small"
                        variant="flat"
                        color="warning"
                        prepend-icon="mdi-alert-outline"
                      >
                        {{ $t("classes.badgeNoManifest") }}
                      </v-chip>
                    </template>
                  </v-list-item>
                </template>
              </v-combobox>

              <v-text-field
                :disabled="loading"
                :label="$t('course.labelOpisanie')"
                type="text"
                prepend-inner-icon="mdi-text-box-edit-outline"
                v-model="title"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                class="mt-3"
                hide-details="auto"
              ></v-text-field>

              <!-- Выбранный класс: сразу видно, что именно уйдёт в импорт. -->
              <div v-if="selectedMeta" class="classes-selected">
                <span class="u-badge" :class="selectedMeta.imported ? 'u-badge--warning' : 'u-badge--primary'">
                  {{ selectedMeta.imported ? $t('classes.badgeImported') : $t('classes.badgeNew') }}
                </span>
                <span class="classes-selected__stat">
                  <v-icon icon="mdi-folder-multiple-image" size="14" end></v-icon>
                  {{ $t('classes.aukCount', { count: selectedMeta.auk_count }) }}
                </span>
                <span class="classes-selected__stat">
                  <v-icon icon="mdi-file-document-check-outline" size="14" end></v-icon>
                  {{ $t('classes.manifestCount', { count: selectedMeta.manifests }) }}
                </span>
                <span class="classes-selected__stat">
                  <v-icon icon="mdi-content-save" size="14" end></v-icon>
                  {{ formatSize(selectedMeta.size_bytes) }}
                </span>
              </div>

              <div>
                <v-progress-linear
                  v-if="loading"
                  :value="progress"
                  height="5"
                  :indeterminate="true"
                  :color="progressColor"
                  rounded
                  class="mt-3"
                ></v-progress-linear>
              </div>
            </v-form>
          </v-card-text>

          <v-divider></v-divider>

          <v-card-actions class="classes-form__actions">
            <!--
              Кнопка только открывает диалог. Раньше она стирала 13 таблиц
              контента одним кликом: промахнуться можно было легко, а
              отменить было нечем.
            -->
            <v-btn
              @click="confirmClear = true"
              :disabled="loading"
              color="error"
              variant="tonal"
              prepend-icon="mdi-delete-sweep"
              class="mr-auto"
            >
              {{ $t("classes.clearData") }}
            </v-btn>
            <ButtonGroup
              class="mr-3"
              v-if="!loading"
              :busy="saving"
              :submit-text="$t('common.save')"
              :cancel-text="$t('common.cancel')"
              @submit="uploadData"
              @cancel="cancelBtnHead"
            ></ButtonGroup>
          </v-card-actions>
        </v-card>
      </section>

      <!-- ----------------------------- галерея ------------------------------ -->
      <section class="classes-gallery" aria-labelledby="classes-gallery-title">
        <div class="classes-gallery__toolbar">
          <h2 class="classes-gallery__title" id="classes-gallery-title">
            {{ $t("classes.galleryTitle") }}
            <span class="classes-gallery__count">{{ visibleClasses.length }} / {{ allTags.length }}</span>
          </h2>

          <v-text-field
            v-model="search"
            :label="$t('classes.searchLabel')"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            single-line
            hide-details
            clearable
            class="classes-gallery__search"
          ></v-text-field>
        </div>

        <div class="classes-gallery__filters" role="group" :aria-label="$t('classes.filterLabel')">
          <v-btn-toggle v-model="statusFilter" mandatory density="compact" divided>
            <v-btn size="small" value="all">{{ $t("classes.filterAll") }}</v-btn>
            <v-btn size="small" value="new">
              {{ $t("classes.filterNew") }}
              <span class="classes-filter__n">{{ counts.new }}</span>
            </v-btn>
            <v-btn size="small" value="imported">
              {{ $t("classes.filterImported") }}
              <span class="classes-filter__n">{{ counts.imported }}</span>
            </v-btn>
          </v-btn-toggle>
        </div>

        <!-- Скелетоны: раньше список просто был пустым во время загрузки. -->
        <div v-if="metaLoading" class="classes-grid">
          <v-card v-for="n in 6" :key="n" class="u-card classes-skeleton" flat>
            <v-skeleton-loader type="article" boilerplate></v-skeleton-loader>
          </v-card>
        </div>

        <div v-else-if="visibleClasses.length" class="classes-grid">
          <v-card
            v-for="cls in visibleClasses"
            :key="cls.name"
            class="u-card classes-card"
            :class="{ 'classes-card--imported': cls.imported, 'classes-card--empty': !cls.imported && cls.manifests === 0 }"
            hover
            flat
          >
            <div class="classes-card__accent" aria-hidden="true"></div>

            <v-card-text class="classes-card__body">
              <div class="classes-card__head">
                <span class="classes-card__avatar" aria-hidden="true">
                  <v-icon :icon="cls.imported ? 'mdi-airplane-landing' : 'mdi-airplane'" size="22"></v-icon>
                </span>
                <h3 class="classes-card__name" :title="cls.name">{{ cls.name }}</h3>
                <span
                  class="u-badge"
                  :class="cls.imported ? 'u-badge--success' : cls.manifests === 0 ? 'u-badge--danger' : 'u-badge--warning'"
                >
                  {{ cls.imported ? $t('classes.badgeImported') : cls.manifests === 0 ? $t('classes.badgeNoManifest') : $t('classes.badgeReady') }}
                </span>
              </div>

              <ul class="classes-card__stats">
                <li>
                  <v-icon icon="mdi-folder-multiple-image" size="16"></v-icon>
                  <span>{{ $t('classes.aukCount', { count: cls.auk_count }) }}</span>
                </li>
                <li>
                  <v-icon icon="mdi-file-document-check-outline" size="16"></v-icon>
                  <span>{{ $t('classes.manifestCount', { count: cls.manifests }) }}</span>
                </li>
                <li>
                  <v-icon icon="mdi-content-save" size="16"></v-icon>
                  <span>{{ formatSize(cls.size_bytes) }}</span>
                </li>
              </ul>
            </v-card-text>

            <v-card-actions class="classes-card__actions">
              <v-btn
                size="small"
                variant="flat"
                :disabled="loading || cls.imported"
                prepend-icon="mdi-plus"
                :aria-label="$t('classes.chooseAria', { name: cls.name })"
                @click="pickClass(cls)"
              >
                {{ $t("classes.pickBtn") }}
              </v-btn>
            </v-card-actions>
          </v-card>
        </div>

        <!-- Пустое состояние: различаем «нет данных», «фильтр ничего не дал» и ошибку. -->
        <div v-else class="u-empty">
          <v-icon
            class="u-empty__icon"
            :icon="errorMessageOnLoad ? 'mdi-alert-circle-outline' : 'mdi-folder-open-outline'"
            size="44"
          ></v-icon>
          <p class="u-empty__title">
            {{ errorMessageOnLoad ? $t('classes.emptyErrorTitle') : allTags.length ? $t('classes.emptyFilterTitle') : $t('classes.emptyDirTitle') }}
          </p>
          <p class="u-empty__text">
            {{
              errorMessageOnLoad
                ? errorMessageOnLoad
                : allTags.length
                  ? $t('classes.emptyFilterText')
                  : $t('classes.emptyDirText')
            }}
          </p>
          <v-btn v-if="allTags.length && visibleClasses.length === 0 && !errorMessageOnLoad" size="small" variant="tonal" @click="resetFilters">
            {{ $t("classes.resetFilters") }}
          </v-btn>
        </div>
      </section>
    </div>

    <ConfirmDialog
      v-model="confirmClear"
      :title="$t('classes.clearTitle')"
      :confirm-text="$t('classes.clearConfirm')"
      :cancel-text="$t('common.cancel')"
      :busy="clearing"
      @cancel="confirmClear = false"
      @confirm="clearDatabase"
    >
      {{ $t("classes.clearText") }}
    </ConfirmDialog>
  </div>
</template>

<script>
import { mapState, mapGetters } from "vuex";
import ButtonGroup from "../../components/ButtonGroup.vue";
import ConfirmDialog from "../../components/ui/ConfirmDialog.vue";
import PageHeader from "../../components/ui/PageHeader.vue";
import $api from "../../api/httpClient";
import { unwrapArray, unwrapResponse } from "../../api/envelope";

/**
 * Человекочитаемый размер папки в бэкенд-сводке (size_bytes).
 *
 * Экспортируется для тестов: раньше страница показывала голые имена
 * папок, и отличить «БПЛА, 4 ГБ» от «КЛЕН, 20 МБ» было нечем.
 */
export const formatClassSize = (bytes) => {
  const n = Number(bytes);

  if (!Number.isFinite(n) || n < 0) {
    return "—";
  }

  if (n < 1024) {
    return `${n} Б`;
  }

  const units = ["КБ", "МБ", "ГБ", "ТБ"];
  let value = n / 1024;
  let unit = 0;

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }

  const rounded = value >= 10 ? Math.round(value) : Math.round(value * 10) / 10;

  return `${String(rounded).replace(".", ",")} ${units[unit]}`;
};

/**
 * Приводит значение v-combobox к строке.
 *
 * Vuetify в зависимости от версии отдаёт выбранный элемент строкой,
 * объектом { text, value } либо массивом (мультивыбор). Бэкенд тоже
 * принимает только непустую строку, поэтому пустые значения и не
 * строковые «обёртки» приводим к null и не отправляем запрос вовсе —
 * раньше форма уходила с path: "" и получала 422 без объяснений.
 */
export const toClassPath = (value) => {
  if (typeof value === "string") {
    return value.trim() === "" ? null : value.trim();
  }

  if (Array.isArray(value)) {
    return value.length ? toClassPath(value[0]) : null;
  }

  if (value && typeof value === "object") {
    return toClassPath(value.value ?? value.text ?? value.title ?? value.name ?? null);
  }

  return null;
};

/**
 * Приводит элемент списка /api/classesfsmeta к карточке класса.
 *
 * Поле name обязательно — без него объект не является классом. Остальные
 * поля необязательны: при недоступности meta-эндпоинта список собирается
 * из «голых» строк /api/classesfs, и карточка тогда показывается без
 * статистики, а не падает.
 */
export const normalizeClassItem = (item) => {
  if (typeof item === "string") {
    return { name: item, auk_count: 0, manifests: 0, size_bytes: 0, imported: false };
  }

  const name = item && typeof item === "object" ? toClassPath(item.name ?? item.title ?? null) : null;

  if (name === null) {
    return null;
  }

  return {
    name,
    auk_count: Number(item.auk_count) || 0,
    manifests: Number(item.manifests) || 0,
    size_bytes: Number(item.size_bytes) || 0,
    imported: Boolean(item.imported),
  };
};

/**
 * Достаёт человекочитаемый текст ошибки из ответа axios.
 *
 * Раньше ошибка 422/409/500 уходила только в console.error, и пользователь
 * видел «ничего не произошло». Теперь текст показывается в v-alert.
 */
export const extractApiError = (error, fallback = "Не удалось выполнить операцию") => {
  const body = error?.response?.data;

  // Конверт ApiResponseEnvelope: { success, data, error: { message } }
  const envelopeMessage = unwrapResponse(body) ?? body?.error?.message;

  if (typeof envelopeMessage === "string" && envelopeMessage.trim() !== "") {
    return envelopeMessage;
  }

  // Ответ валидации Laravel: { message, errors: { field: [msg, ...] } }
  if (body && typeof body === "object" && body.errors && typeof body.errors === "object") {
    const messages = Object.values(body.errors)
      .flat()
      .filter((msg) => typeof msg === "string" && msg.trim() !== "");
    if (messages.length) {
      return messages.join(" ");
    }
  }

  if (typeof body?.message === "string" && body.message.trim() !== "") {
    return body.message;
  }

  if (error?.message && !/Network Error|timeout/i.test(error.message)) {
    return error.message;
  }

  return fallback;
};

export default {
  name: "AddClass",
  components: {
    ButtonGroup,
    ConfirmDialog,
    PageHeader,
  },
  data() {
    return {
      allTags: [],
      tags: [],
      auks: [],
      title: "",
      path: "",
      loading: false,
      clearing: false,
      // Диалог подтверждения очистки: без него кнопка стирала базу
      // одним кликом.
      confirmClear: false,
      progress: 0,
      progressColor: "blue",
      errorMessage: "",
      successMessage: "",
      // --- галерея классов (/api/classesfsmeta) ---
      // Отдельная ошибка загрузки сводки: она не должна перекрывать
      // ошибки импорта (errorMessage), иначе «что упало» не разобрать.
      errorMessageOnLoad: "",
      metaLoading: true,
      search: "",
      statusFilter: "all",
      rules: {
        required: (value) => {
          const normalized = toClassPath(value);
          return (normalized !== null && normalized !== "") || "Поле обязательно для заполнения";
        },
      },
    };
  },

  computed: {
    ...mapState("Course", ["courses", "category", "totalCourses", "course"]),
    ...mapGetters("Course", ["categories"]),

    /** Каталог классов, отфильтрованный по уже введённому тексту. */
    filteredTags() {
      const search = toClassPath(this.path);

      if (search === null) {
        return this.allTags;
      }

      return this.allTags.filter((tag) => tag.name.toLowerCase().includes(search.toLowerCase()));
    },

    /** Карточки галереи: поиск подстроке имени + фильтр статуса импорта. */
    visibleClasses() {
      const query = this.search.trim().toLowerCase();

      return this.allTags.filter((cls) => {
        if (query !== "" && !cls.name.toLowerCase().includes(query)) {
          return false;
        }

        if (this.statusFilter === "imported") {
          return cls.imported;
        }

        if (this.statusFilter === "new") {
          return !cls.imported;
        }

        return true;
      });
    },

    /** Счётчики для переключателя фильтров (по всему каталогу, не по выборке). */
    counts() {
      return {
        all: this.allTags.length,
        imported: this.allTags.filter((cls) => cls.imported).length,
        new: this.allTags.filter((cls) => !cls.imported).length,
      };
    },

    /** Мета выбранного в комбобоксе класса — для панели под формой. */
    selectedMeta() {
      const name = toClassPath(this.path);

      if (name === null) {
        return null;
      }

      return this.allTags.find((cls) => cls.name === name) ?? null;
    },

    /** Кнопка сохранения активна только с заполненными полями. */
    canSubmit() {
      return toClassPath(this.path) !== null && this.title.trim() !== "";
    },
  },

  async mounted() {
    await this.loadTags();
  },

  methods: {
    /**
     * Загружает сводку по каталогам-классам с диска.
     *
     * Источник — /api/classesfsmeta (карточки: число АУК, манифесты,
     * размер, статус импорта). Если он недоступен или отдаёт мусор,
     * список достраивается из «голых» имён /api/classesfs — контракт
     * второго эндпоинта не менялся, страница деградирует до карточек
     * без статистики, а не остаётся пустой.
     */
    async loadTags() {
      this.metaLoading = true;
      this.errorMessageOnLoad = "";

      let items = [];

      try {
        const response = await $api.get("/api/classesfsmeta");
        items = unwrapArray(response)
          .map(normalizeClassItem)
          .filter((cls) => cls !== null);
      } catch (error) {
        this.errorMessageOnLoad = extractApiError(error, "Не удалось загрузить сводку по классам");
      }

      if (items.length === 0) {
        try {
          const response = await $api.get("/api/classesfs");
          items = unwrapArray(response)
            .map(normalizeClassItem)
            .filter((cls) => cls !== null);
          // Fallback сработал штатно — ошибка meta больше не актуальна.
          if (items.length > 0) {
            this.errorMessageOnLoad = "";
          }
        } catch (error) {
          if (this.errorMessageOnLoad === "") {
            this.errorMessageOnLoad = extractApiError(error, "Не удалось загрузить список классов");
          }
        }
      }

      // Ошибку загрузки показываем в том же понятном виде, что и раньше:
      // отдельным сообщением, а не молча пустым списком.
      this.allTags = items;
      this.tags = items;
      this.metaLoading = false;
    },

    /** Форматирует размер папки для карточек и панели выбранного класса. */
    formatSize(bytes) {
      return formatClassSize(bytes);
    },

    /** Клик по карточке: подставляет класс в форму и перемещает фокус на неё. */
    pickClass(cls) {
      this.path = cls.name;
      this.errorMessage = "";

      const form = document.getElementById("classes-form-title");

      if (form) {
        form.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    },

    /** Сброс поиска и фильтра (кнопка в пустом состоянии галереи). */
    resetFilters() {
      this.search = "";
      this.statusFilter = "all";
    },

    /**
     * Основной обработчик кнопки «Сохранить» (ButtonGroup @submitForm).
     *
     * Раньше запрос уходил даже с пустыми полями и возвращал 422,
     * а submit формы (v-form @submit) делал вторую, ни о чём не
     * сообщавшую попытку и всегда уводил на предыдущую страницу.
     * Теперь обе точки входа ведут в один метод с валидацией.
     */
    async uploadData() {
      this.errorMessage = "";
      this.successMessage = "";

      const path = toClassPath(this.path);

      if (path === null) {
        this.errorMessage = "Выберите класс для добавления";
        return false;
      }

      if (this.title.trim() === "") {
        this.errorMessage = "Укажите описание класса";
        return false;
      }

      this.loading = true;

      try {
        const response = await $api.post(
          "/api/classes",
          { title: this.title.trim(), path },
          {
            onUploadProgress: (progressEvent) => {
              this.progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            },
          }
        );

        const payload = unwrapResponse(response);
        // Сводка импорта лежит в meta (см. AircraftController::storeclasses):
        // там aircraft, courses, auk (массив импортированных АУК), gift_files_parsed.
        // Раньше здесь читался data.auks, которого в data нет вообще, поэтому
        // пользователю всегда показывалось «Загружено АУК: 0».
        const summary = response?.data?.meta ?? {};
        const imported = Array.isArray(summary.auk)
          ? summary.auk
          : Array.isArray(summary.auks)
            ? summary.auks
            : Array.isArray(payload?.auks)
              ? payload.auks
              : [];
        this.auks = imported;
        const parsed = Number.isFinite(Number(summary.gift_files_parsed))
          ? Number(summary.gift_files_parsed)
          : null;
        // Импортированный класс остаётся в каталоге и в списке, но
        // помечается импортированным: карточка сама обновится по allTags.
        this.markClassImported(path);
        this.successMessage = parsed === null
          ? `Класс «${path}» импортирован. Загружено АУК: ${imported.length}.`
          : `Класс «${path}» импортирован. Загружено АУК: ${imported.length} (распознано документов: ${parsed}).`;
        this.title = "";
        this.path = "";
        return true;
      } catch (error) {
        this.errorMessage = extractApiError(error);
        return false;
      } finally {
        this.loading = false;
        this.progress = 0;
      }
    },

    /**
     * Помечает класс импортированным в локальном каталоге.
     *
     * Раньше только что импортированная папка исчезала из списка до
     * перезагрузки страницы — пользователь терял её из виду. Теперь
     * карточка остаётся и меняет бейдж; полный статус при следующем
     * loadTags() придёт с сервера.
     */
    markClassImported(name) {
      const apply = (list) =>
        list.map((cls) => (cls.name === name ? { ...cls, imported: true } : cls));

      this.allTags = apply(this.allTags);
      this.tags = apply(this.tags);
    },

    /** Submit формы (Enter в поле). Ведёт в тот же сценарий, что и кнопка. */
    async submitForm() {
      await this.uploadData();
    },

    async clearDatabase() {
      // Повторный клик во время запроса игнорируем: truncate необратим,
      // две параллельные очистки бессмысленны.
      if (this.clearing) {
        return false;
      }

      this.errorMessage = "";
      this.successMessage = "";
      this.clearing = true;

      try {
        await $api.post("/api/clear-database");
        // Диалог закрываем после ответа, а не до: при ошибке он
        // остаётся открытым, чтобы можно было повторить или отменить.
        this.confirmClear = false;
        this.successMessage = "База данных очищена";
        // Перезагружаем каталог целиком: после truncate все классы снова
        // «не импортированы», локальные пометки markClassImported устарели.
        await this.loadTags();
        return true;
      } catch (error) {
        this.errorMessage = extractApiError(error, "Не удалось очистить базу данных");
        return false;
      } finally {
        this.clearing = false;
      }
    },

    cancelBtnHead() {
      this.$router.go(-1);
    },
  },
};
</script>

<style scoped>
/*
 * Дизайн-система приложения (tokens.css / app.css) даёт базовые u-card,
 * u-badge, u-empty; здесь — только раскладка страницы классов:
 * двухколоночная сетка «форма + галерея», карточки с акцентной полосой
 * состояния и адаптив. Всё через CSS-переменные токенов, чтобы тёмная
 * тема работала без правок.
 */

.classes-layout {
  display: grid;
  grid-template-columns: minmax(320px, 400px) minmax(0, 1fr);
  gap: var(--sp-6);
  align-items: start;
}

@media (max-width: 1100px) {
  .classes-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}

/* --------------------------------- форма --------------------------------- */
.classes-form {
  position: sticky;
  top: var(--sp-4);
}

@media (max-width: 1100px) {
  .classes-form {
    position: static;
  }
}

.classes-card__title {
  display: flex;
  align-items: center;
  font-size: var(--fs-lg);
  font-weight: var(--fw-semibold);
  color: var(--c-text);
}

.classes-form__hint {
  margin: 0 0 var(--sp-4);
  font-size: var(--fs-sm);
  line-height: var(--lh-normal);
  color: var(--c-text-secondary);
}

.classes-form__actions {
  padding: var(--sp-3) var(--sp-4);
}

/* Панель выбранного класса: сводка прямо под полями формы. */
.classes-selected {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--sp-2) var(--sp-4);
  margin-top: var(--sp-4);
  padding: var(--sp-3);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  background: var(--c-surface-2);
  font-size: var(--fs-sm);
  color: var(--c-text-secondary);
}

.classes-selected__stat {
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
}

/* -------------------------------- галерея -------------------------------- */
.classes-gallery__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  flex-wrap: wrap;
  margin-bottom: var(--sp-3);
}

.classes-gallery__title {
  margin: 0;
  font-size: var(--fs-xl);
  font-weight: var(--fw-semibold);
  color: var(--c-text);
}

.classes-gallery__count {
  margin-left: var(--sp-2);
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  color: var(--c-text-muted);
}

.classes-gallery__search {
  max-width: 320px;
  flex: 1 1 220px;
}

.classes-gallery__filters {
  margin-bottom: var(--sp-4);
}

.classes-filter__n {
  margin-left: var(--sp-1);
  padding: 0 var(--sp-1);
  border-radius: var(--radius-pill);
  background: var(--c-surface-3);
  color: var(--c-text-secondary);
  font-size: var(--fs-2xs);
  font-weight: var(--fw-semibold);
}

.classes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: var(--sp-4);
}

.classes-skeleton {
  overflow: hidden;
}

/* ------------------------------- карточка -------------------------------- */
.classes-card {
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.classes-card:hover {
  transform: translateY(-2px);
}

/* Акцентная полоса состояния: импортирован / готов / пустой каталог. */
.classes-card__accent {
  height: 4px;
  background: linear-gradient(90deg, var(--c-warning), var(--c-warning-soft));
}

.classes-card--imported .classes-card__accent {
  background: linear-gradient(90deg, var(--c-success), var(--c-success-soft));
}

.classes-card--empty .classes-card__accent {
  background: linear-gradient(90deg, var(--c-danger), var(--c-danger-soft));
}

.classes-card__body {
  flex: 1 1 auto;
}

.classes-card__head {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-bottom: var(--sp-3);
}

.classes-card__avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  flex: none;
  border-radius: var(--radius-md);
  background: var(--c-primary-soft);
  color: var(--c-primary);
}

.classes-card--imported .classes-card__avatar {
  background: var(--c-success-soft);
  color: var(--c-success);
}

.classes-card--empty .classes-card__avatar {
  background: var(--c-surface-3);
  color: var(--c-text-muted);
}

.classes-card__name {
  flex: 1 1 auto;
  margin: 0;
  font-size: var(--fs-lg);
  font-weight: var(--fw-semibold);
  color: var(--c-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.classes-card__stats {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
  font-size: var(--fs-sm);
  color: var(--c-text-secondary);
}

.classes-card__stats li {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.classes-card__stats :deep(.v-icon) {
  color: var(--c-text-muted);
}

.classes-card__actions {
  padding: var(--sp-2) var(--sp-4) var(--sp-4);
}

@media (prefers-reduced-motion: reduce) {
  .classes-card {
    transition: none;
  }

  .classes-card:hover {
    transform: none;
  }
}
</style>