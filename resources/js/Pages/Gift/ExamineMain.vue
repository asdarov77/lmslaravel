<template>
  <div class="u-page">
    <PageHeader :title="$t('bank.title')" :subtitle="$t('bank.subtitle')">
      <template #head>
        <p v-if="totals.questions" class="bank__totals">
          {{ $t("bank.totals", totals) }}
        </p>
      </template>
      <template #actions>
        <v-btn
          color="primary"
          variant="tonal"
          :loading="loading"
          :disabled="loading"
          @click="load"
        >
          <v-icon start icon="mdi-refresh" size="18" aria-hidden="true"></v-icon>
          {{ $t("common.refresh") }}
        </v-btn>
      </template>
    </PageHeader>

    <!--
      Предупреждение о целостности банка.

      Вопрос без верного варианта или с двумя верными выглядит в таблице
      как обычный, и методист узнаёт о нём только тогда, когда обучающийся
      «не сдал» экзамен. Показываем проблему сразу, до назначения.
    -->
    <div v-if="hasIntegrityIssues" class="bank__alert" role="alert">
      <v-icon icon="mdi-alert-outline" size="20" aria-hidden="true"></v-icon>
      <div>
        <strong>{{ $t("bank.integrityTitle") }}</strong>
        <ul class="bank__alert-list">
          <li v-if="totals.without_answers">
            {{ $t("bank.withoutAnswers", { count: totals.without_answers }) }}
          </li>
          <li v-if="totals.without_correct">
            {{ $t("bank.withoutCorrect", { count: totals.without_correct }) }}
          </li>
          <li v-if="totals.multiple_correct">
            {{ $t("bank.multipleCorrect", { count: totals.multiple_correct }) }}
          </li>
        </ul>
      </div>
    </div>

    <!-- Фильтры: специальность → тема, со счётчиками вопросов -->
    <section class="u-card bank__filters">
      <div class="bank__filter">
        <label class="bank__filter-label" :for="`bank-cat-${uid}`">
          {{ $t("bank.category") }}
        </label>
        <v-select
          :id="`bank-cat-${uid}`"
          v-model="currentCategory"
          :items="categoryItems"
          item-title="label"
          item-value="id"
          density="compact"
          variant="outlined"
          hide-details
          :placeholder="$t('bank.pickCategory')"
          @update:model-value="onCategoryChange"
        ></v-select>
      </div>

      <div class="bank__filter">
        <label class="bank__filter-label" :for="`bank-module-${uid}`">
          {{ $t("bank.module") }}
        </label>
        <v-select
          :id="`bank-module-${uid}`"
          v-model="currentModule"
          :items="moduleItems"
          item-title="label"
          item-value="id"
          density="compact"
          variant="outlined"
          hide-details
          :disabled="!currentCategory"
          :placeholder="currentCategory ? $t('bank.pickModule') : $t('bank.pickCategoryFirst')"
          @update:model-value="loadQuestions"
        ></v-select>
      </div>
    </section>

    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate :aria-label="$t('common.loading')"></v-progress-circular>
    </div>

    <!-- Пустое состояние различает «банк пуст» и «в этой теме вопросов нет» -->
    <EmptyState
      v-else-if="!currentCategory"
      :icon="'mdi-format-list-checks'"
      :title="$t('bank.emptyPickTitle')"
      :text="$t('bank.emptyPickText')"
    ></EmptyState>

    <EmptyState
      v-else-if="!currentModule"
      :icon="'mdi-folder-question-outline'"
      :title="$t('bank.emptyModuleTitle')"
      :text="$t('bank.emptyModuleText')"
    ></EmptyState>

    <EmptyState
      v-else-if="!questions.length"
      :icon="'mdi-help-circle-outline'"
      :title="$t('bank.emptyQuestionsTitle')"
      :text="$t('bank.emptyQuestionsText')"
    >
      <v-btn class="mt-4" color="primary" variant="flat" @click="createQuestion">
        <v-icon start icon="mdi-plus" size="18" aria-hidden="true"></v-icon>
        {{ $t("bank.create") }}
      </v-btn>
    </EmptyState>

    <section v-else class="u-card bank__table-card">
      <div class="bank__table-head">
        <span class="u-page__subtitle">
          {{ $t("bank.found", { count: questions.length }) }}
        </span>
        <div class="d-flex align-center bank__table-tools">
          <v-select
            :items="[10, 20, 50, 100]"
            v-model="pageSize"
            density="compact"
            variant="outlined"
            hide-details
            class="bank__page-size"
            :aria-label="$t('bank.rowsPerPage')"
          ></v-select>
          <v-btn color="primary" variant="flat" size="small" @click="createQuestion">
            <v-icon start icon="mdi-plus" size="18" aria-hidden="true"></v-icon>
            {{ $t("bank.create") }}
          </v-btn>
        </div>
      </div>

      <div class="u-table-wrap">
        <table class="u-table">
          <thead>
            <tr>
              <th class="u-table__num">#</th>
              <th>{{ $t("bank.question") }}</th>
              <th class="u-table__answers">{{ $t("bank.answers") }}</th>
              <th class="u-table__actions">{{ $t("bank.actions") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in displayedQuestions" :key="item.id">
              <td class="u-table__num">{{ (page - 1) * pageSize + index + 1 }}</td>
              <td>
                <span class="bank__question">{{ item.question_text }}</span>
                <span
                  v-if="item.integrity"
                  class="u-badge u-badge--danger bank__issue"
                >
                  {{ $t(`bank.integrity.${item.integrity}`) }}
                </span>
              </td>
              <td class="u-table__answers">
                <!--
                  Счётчики приходят с сервера (withCount). Раньше их не
                  было, поэтому методист не мог отличить вопрос с тремя
                  вариантами от вопроса с одним — и не видел битых вопросов.
                -->
                <span class="bank__count">{{ item.answers_count ?? 0 }}</span>
                <span
                  v-if="item.correct_answers_count"
                  class="u-badge u-badge--success bank__correct"
                >
                  {{ $t("bank.correctCount", { count: item.correct_answers_count }) }}
                </span>
              </td>
              <td class="u-table__actions">
                <v-btn
                  variant="text"
                  size="small"
                  :aria-label="$t('bank.edit') + ': ' + item.id"
                  :title="$t('bank.edit')"
                  @click="edit(item.id)"
                >
                  <v-icon icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
                </v-btn>
                <v-btn
                  variant="text"
                  size="small"
                  color="error"
                  :aria-label="$t('bank.delete') + ': ' + item.id"
                  :title="$t('bank.delete')"
                  @click="remove(item)"
                >
                  <v-icon icon="mdi-delete-outline" size="18" aria-hidden="true"></v-icon>
                </v-btn>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="totalPages > 1" class="bank__pager">
        <v-pagination
          v-model="page"
          :length="totalPages"
          rounded="circle"
          :total-visible="7"
          density="comfortable"
          @update:model-value="page = 1"
        ></v-pagination>
      </div>
    </section>

    <ConfirmDialog
      v-model="confirmDelete"
      icon="mdi-delete-alert-outline"
      :title="$t('bank.deleteTitle')"
      :text="$t('bank.deleteText')"
      confirm-text="$t('bank.delete')"
      :busy="deleting"
      @confirm="doDelete"
    ></ConfirmDialog>

  </div>
</template>

<script>
import $api from "../../api/httpClient";
import { unwrapResponse, unwrapArray } from "../../api/envelope";
import PageHeader from "../../components/ui/PageHeader.vue";
import EmptyState from "../../components/ui/EmptyState.vue";
import ConfirmDialog from "../../components/ui/ConfirmDialog.vue";
import { toast } from "../../composables/useToast";

let uidCounter = 0;

/**
 * Банк вопросов.
 *
 * Переписан с плоской таблицы на PageHeader + фильтры со счётчиками +
 * пустые состояния + диалог подтверждения.
 *
 * Что изменилось по существу, а не только по виду:
 *  - список категорий и тем приходит из /api/questions/statistics, где у
 *    каждого пункта есть счётчик вопросов. Раньше, чтобы узнать размер
 *    специальности, нужно было открыть каждую тему вручную;
 *  - в таблице видно число вариантов ответа и сколько из них верных, а
 *    также Integrity-метку. Раньше битый вопрос (без верного варианта или
 *    с двумя верными) выглядел как обычный, и методист узнавал о нём
 *    только из результата экзамена обучающегося;
 *  - список тем строится ОДИН раз из статистики, а не из полной
 *    выкачки вопросов категории с последующей дедупликацией по названию
 *    на клиенте. Из-за той дедупликации две темы с одинаковым названием
 *    схлопывались в одну, а вопросы второй просто становились
 *    недостижимыми;
 *  - выбор специальности или темы пишется в адресную строку, поэтому
 *    ссылкой на раздел банка можно поделиться.
 */
export default {
  name: "QuestionBank",

  components: { PageHeader, EmptyState, ConfirmDialog },

  data() {
    return {
      uid: `bank-${++uidCounter}`,
      stats: { totals: {}, categories: [], modules: [] },
      currentCategory: null,
      currentModule: null,
      questions: [],
      page: 1,
      pageSize: 20,
      loading: false,
      deleting: false,
      confirmDelete: false,
      pendingDelete: null,
    };
  },

  computed: {
    totals() {
      return this.stats.totals || {};
    },

    hasIntegrityIssues() {
      const t = this.totals;
      return (t.without_answers || 0) + (t.without_correct || 0) + (t.multiple_correct || 0) > 0;
    },

    /**
     * Категории с нулевым счётчиком помечаем: в списке они есть, но
     * выбрать тему в них нельзя, и без пометки это выглядит как поломка.
     */
    categoryItems() {
      return (this.stats.categories || []).map((c) => ({
        id: c.id,
        label: c.questions > 0
          ? `${c.title} — ${c.questions}`
          : `${c.title} — ${this.$t("bank.empty")}`,
      }));
    },

    /** Темы строятся из статистики и ограничиваются выбранной категорией. */
    moduleItems() {
      return (this.stats.modules || [])
        .filter((m) => !this.currentCategory || m.category_id === this.currentCategory)
        .map((m) => ({ id: m.id, label: `${m.title} — ${m.questions}` }));
    },

    totalPages() {
      return Math.ceil(this.questions.length / this.pageSize);
    },

    displayedQuestions() {
      const start = (this.page - 1) * this.pageSize;
      return this.questions.slice(start, start + this.pageSize);
    },
  },

  watch: {
    // Смена размера страницы на последней может оставить пользователя
    // на пустой странице.
    pageSize() {
      this.page = 1;
    },
  },

  created() {
    this.restoreFromRoute();
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;

      try {
        const res = await $api.get('/api/questions/statistics');
        // unwrapResponse разворачивает конверт и отдаёт payload целиком:
        // { totals, categories, modules }. Обращение через unwrapField
        // искало бы вложенное поле и вернуло бы пустой объект.
        this.stats = this.statsFromEnvelope(res);
      } catch (e) {
        // Пустая статистика — не повод блокировать страницу: список
        // категорий придёт из стора, и банком можно пользоваться.
        if (e?.response?.status >= 400) {
          this.notify(this.$t("bank.loadError"), "error");
        }
      } finally {
        this.loading = false;
      }
    },

    statsFromEnvelope(res) {
      const payload = unwrapResponse(res) || {};
      return { totals: payload.totals || {}, categories: payload.categories || [], modules: payload.modules || [] };
    },

    onCategoryChange(id) {
      // Тема всегда принадлежит категории, поэтому при смене категории
      // прежняя тема становится невалидной.
      this.currentModule = null;
      this.questions = [];
      this.page = 1;
      this.syncRoute();

      if (id) {
        // Если в категории ровно одна тема, выбираем её сразу: лишний
        // клик по пустому списку ни о чём не говорит.
        const themes = (this.stats.modules || []).filter((m) => m.category_id === id);
        if (themes.length === 1) {
          this.currentModule = themes[0].id;
          this.syncRoute();
          this.loadQuestions();
        }
      }
    },

    async loadQuestions() {
      this.page = 1;
      this.syncRoute();

      if (!this.currentModule) {
        this.questions = [];
        return;
      }

      this.loading = true;
      try {
        // Фильтр по категории не отправляем: тема принадлежит ровно
        // одной категории, а лишний параметр только добавлял бы риск
        // расхождения (и 422 при несовпадении).
        const res = await $api.get('/api/questions', { params: { aukstructure_id: this.currentModule } });
        this.questions = unwrapArray(res);
      } catch (e) {
        this.questions = [];
        if (e?.response?.status >= 400) {
          this.notify(this.$t("bank.loadError"), "error");
        }
      } finally {
        this.loading = false;
      }
    },

    syncRoute() {
      const query = {};
      if (this.currentCategory) query.category_id = this.currentCategory;
      if (this.currentModule) query.aukstructure_id = this.currentModule;

      this.$router.replace({ name: "questions.main", query }).catch(() => {});
    },

    restoreFromRoute() {
      const q = this.$route?.query || {};
      const cat = Number(q.category_id);
      const mod = Number(q.aukstructure_id);

      if (Number.isFinite(cat) && cat > 0) this.currentCategory = cat;
      if (Number.isFinite(mod) && mod > 0) {
        this.currentModule = mod;
        this.loadQuestions();
      }
    },

    createQuestion() {
      this.$router.push({
        name: "question.new",
        params: {
          category_id: this.currentCategory,
          aukstructure_id: this.currentModule,
        },
      });
    },

    edit(id) {
      this.$router.push({ name: "question.edit", params: { idEdit: id } });
    },

    remove(item) {
      this.pendingDelete = item;
      this.confirmDelete = true;
    },

    async doDelete() {
      if (!this.pendingDelete) return;

      this.deleting = true;
      try {
        await $api.delete(`/api/questions/${this.pendingDelete.id}`);
        this.questions = this.questions.filter((q) => q.id !== this.pendingDelete.id);
        this.pendingDelete = null;
        this.confirmDelete = false;
        this.notify(this.$t("bank.deleted"));
      } catch (e) {
        this.notify(e?.response?.data?.error?.message || this.$t("bank.deleteError"), "error");
      } finally {
        this.deleting = false;
      }
    },

    notify(text, type = "success") {
      toast.byType(type, text);
    },
  },
};
</script>

<style scoped>
.bank__totals {
  margin: var(--sp-2) 0 0;
  color: var(--c-text-muted);
  font-size: 0.8125rem;
}

.bank__alert {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-4);
  margin-bottom: var(--sp-3);
  border: 1px solid var(--c-warning);
  border-radius: var(--radius-md);
  background: var(--c-warning-soft);
  color: var(--c-warning);
  font-size: 0.875rem;
}

.bank__alert-list {
  margin: var(--sp-1) 0 0;
  padding-left: var(--sp-5);
}

.bank__filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: var(--sp-3);
  padding: var(--sp-4);
  margin-bottom: var(--sp-3);
}

.bank__filter-label {
  display: block;
  margin-bottom: var(--sp-1);
  color: var(--c-text-muted);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.bank__table-card {
  padding: var(--sp-4);
}

.bank__table-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  margin-bottom: var(--sp-3);
  flex-wrap: wrap;
}

.bank__table-tools {
  gap: var(--sp-3);
}

.bank__page-size {
  max-width: 110px;
}

.bank__question {
  display: block;
}

.bank__issue {
  margin-top: var(--sp-1);
}

.bank__answers {
  white-space: nowrap;
}

.bank__count {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  margin-right: var(--sp-2);
}

.bank__correct {
  margin-right: var(--sp-1);
}

.bank__pager {
  display: flex;
  justify-content: center;
  margin-top: var(--sp-3);
}
</style>
