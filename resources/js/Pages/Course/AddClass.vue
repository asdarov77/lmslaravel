<template>
  <v-col xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto">
      <v-toolbar color="primary">
        <v-toolbar-title>{{ $t("classes.title") }}</v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-alert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          class="mb-4"
          :text="errorMessage"
        ></v-alert>
        <v-alert
          v-if="successMessage"
          type="success"
          variant="tonal"
          class="mb-4"
          :text="successMessage"
        ></v-alert>

        <v-form ref="form" @submit.prevent="submitForm">
          <v-combobox
            v-model="path"
            :items="tags"
            label="Выберите класс для добавления"
            :disabled="loading"
            :rules="[rules.required]"
          ></v-combobox>
          <v-text-field
            :disabled="loading"
            label="Описание"
            type="text"
            v-model="title"
            :rules="[rules.required]"
          ></v-text-field>

          <div>
            <v-progress-linear
              v-if="loading"
              :value="progress"
              height="5"
              :indeterminate="true"
              :color="progressColor"
            ></v-progress-linear>
          </div>
        </v-form>
      </v-card-text>
      <v-card-actions class="d-flex justify-space-between">
        <!--
          Кнопка только открывает диалог. Раньше она стирала 13 таблиц
          контента одним кликом: промахнуться можно было легко, а
          отменить было нечем.
        -->
        <v-btn @click="confirmClear = true" :disabled="loading" class="mr-auto" color="error">
          {{ $t("classes.clearData") }}
        </v-btn>
        <ButtonGroup
          class="mr-3"
          v-if="!loading"
          @submitForm="uploadData"
          @cancelBtn="cancelBtnHead"
        ></ButtonGroup>
      </v-card-actions>
    </v-card>
  </v-col>

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
</template>

<script>
import { mapState, mapGetters } from "vuex";
import ButtonGroup from "../../components/ButtonGroup.vue";
import ConfirmDialog from "../../components/ui/ConfirmDialog.vue";
import $api from "../../api/httpClient";
import { unwrapArray, unwrapResponse } from "../../api/envelope";

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

      return this.allTags.filter((tag) => tag.toLowerCase().includes(search.toLowerCase()));
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
    /** Загружает список каталогов-классов с диска. */
    async loadTags() {
      try {
        const response = await $api.get("/api/classesfs");
        this.allTags = unwrapArray(response);
        this.tags = this.allTags;
      } catch (error) {
        this.errorMessage = extractApiError(error, "Не удалось загрузить список классов");
      }
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
        this.tags = this.tags.filter((tag) => tag !== path);
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
        // После очистки на диске снова доступны все каталоги, поэтому
        // список тегов возвращаем к исходному (раньше оставалась
        // урезанная копия без только что очищенных классов).
        this.tags = this.allTags;
        // Диалог закрываем после ответа, а не до: при ошибке он
        // остаётся открытым, чтобы можно было повторить или отменить.
        this.confirmClear = false;
        this.successMessage = "База данных очищена";
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