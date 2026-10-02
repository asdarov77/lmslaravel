<template>
  <div class="u-page">
    <PageHeader :title="exam.title || $t('exams.take.title')" :subtitle="examSubtitle">
      <template #actions>
        <v-btn
          v-if="!result && !loading && !loadError"
          color="primary"
          variant="tonal"
          :to="{ name: 'exams.mine' }"
        >
          {{ $t("exams.backToList") }}
        </v-btn>
      </template>
    </PageHeader>

    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate :aria-label="$t('common.loading')"></v-progress-circular>
    </div>

    <EmptyState
      v-else-if="loadError"
      :icon="'mdi-lock-alert-outline'"
      :title="$t('exams.unavailable')"
      :text="loadError"
    >
      <v-btn class="mt-4" color="primary" variant="flat" :to="{ name: 'exams.mine' }">
        {{ $t("exams.backToList") }}
      </v-btn>
    </EmptyState>

    <section v-else-if="result" class="u-card runner__result">
      <h2 class="u-card__title">{{ $t("exams.take.finished") }}</h2>

      <div class="runner__result-grid">
        <div class="runner__result-cell">
          <span class="runner__result-value">{{ result.scorePercent }}%</span>
          <span class="runner__result-label">{{ $t("exams.score") }}</span>
        </div>
        <div class="runner__result-cell">
          <span class="runner__result-value">{{ result.correct_count }} / {{ result.total_count }}</span>
          <span class="runner__result-label">{{ $t("exams.correct") }}</span>
        </div>
        <div class="runner__result-cell">
          <span class="runner__result-value">{{ result.passingScorePercent }}%</span>
          <span class="runner__result-label">{{ $t("exams.passingScore") }}</span>
        </div>
      </div>

      <p class="runner__verdict" :class="result.passed ? 'runner__verdict--ok' : 'runner__verdict--fail'">
        <v-icon
          :icon="result.passed ? 'mdi-check-circle-outline' : 'mdi-close-circle-outline'"
          size="20"
          aria-hidden="true"
        ></v-icon>
        {{ result.passed ? $t("exams.passed") : $t("exams.notPassed") }}
      </p>

      <p v-if="result.attempts_left > 0" class="u-page__subtitle">
        {{ $t("exams.attemptsLeft", { count: result.attempts_left }) }}
      </p>
      <p v-else class="u-page__subtitle">{{ $t("exams.attemptsExhausted") }}</p>

      <div class="runner__result-actions">
        <v-btn color="primary" variant="flat" :to="{ name: 'exams.mine' }">
          {{ $t("exams.backToList") }}
        </v-btn>
      </div>
    </section>

    <section v-else class="runner">
      <div class="runner__progress">
        <span class="u-page__subtitle">
          {{ $t("exams.progress", { current: currentIndex + 1, total: questions.length }) }}
        </span>
        <span class="u-page__subtitle">
          {{ $t("exams.answeredOf", { answered: answeredCount, total: questions.length }) }}
        </span>
      </div>

      <div class="u-card runner__question">
        <h2 class="runner__question-text">{{ currentQuestion?.question_text }}</h2>

        <fieldset class="runner__answers">
          <legend class="u-sr-only">{{ currentQuestion?.question_text }}</legend>
          <label
            v-for="answer in currentQuestion?.answers || []"
            :key="answer.id"
            class="runner__answer"
            :class="{ 'runner__answer--on': isSelected(answer.id) }"
          >
            <input
              type="radio"
              :name="'q-' + (currentQuestion?.id)"
              :value="answer.id"
              :checked="isSelected(answer.id)"
              @change="select(answer.id)"
            />
            <span>{{ answer.answer }}</span>
          </label>
        </fieldset>

        <div class="runner__nav">
          <v-btn variant="text" :disabled="currentIndex === 0" @click="currentIndex -= 1">
            <v-icon start icon="mdi-chevron-left" size="18" aria-hidden="true"></v-icon>
            {{ $t("exams.prev") }}
          </v-btn>

          <v-btn
            v-if="currentIndex < questions.length - 1"
            color="primary"
            variant="flat"
            @click="currentIndex += 1"
          >
            {{ $t("exams.next") }}
            <v-icon end icon="mdi-chevron-right" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <v-btn
            v-else
            color="success"
            variant="flat"
            :loading="submitting"
            :disabled="!allAnswered"
            @click="submit"
          >
            <v-icon start icon="mdi-check" size="18" aria-hidden="true"></v-icon>
            {{ $t("exams.finish") }}
          </v-btn>
        </div>

        <p v-if="!allAnswered" class="runner__hint">{{ $t("exams.answerAll") }}</p>
      </div>
    </section>

    <AppToast v-model="alert" :type="alertType" :text="alertText"></AppToast>
  </div>
</template>

<script>
import PageHeader from "../../components/ui/PageHeader.vue";
import EmptyState from "../../components/ui/EmptyState.vue";
import AppToast from "../../components/ui/AppToast.vue";
import examApi from "../../api/exam.api";

/**
 * Прохождение экзамена.
 *
 * Отдельный компонент, а не доработка Pages/Gift/ExamineItem.vue, потому
 * что там результат считался В БРАУЗЕРЕ:
 *
 *   const correctAnswerId = question.answers.find(a => a.is_correct).id
 *
 * а /api/questions отдавал все ответы вместе с is_correct. Итог: «сдать»
 * экзамен можно было не отвечая — достаточно прочитать правильные ответы
 * из JSON-ответа. Плюс submitTest() был написан, но ни разу не вызван, а
 * маршрута /api/student-answers не существовало, поэтому результаты
 * нигде не сохранялись.
 *
 * Здесь вопросы берутся из /exams/{id}/questions, где is_correct нет, а
 * проверка и вердикт считаются сервером. Клиент показывает то, что
 * вернули, и изменить это не может.
 */
export default {
  name: "ExamRunner",

  components: { PageHeader, EmptyState, AppToast },

  props: {
    idEdit: { type: Number, required: true },
  },

  data() {
    return {
      exam: {},
      questions: [],
      selected: {},
      currentIndex: 0,
      loading: false,
      submitting: false,
      loadError: "",
      result: null,
      alert: false,
      alertType: "success",
      alertText: "",
    };
  },

  computed: {
    currentQuestion() {
      return this.questions[this.currentIndex] ?? null;
    },

    answeredCount() {
      return Object.keys(this.selected).length;
    },

    allAnswered() {
      return this.questions.length > 0 && this.answeredCount === this.questions.length;
    },

    examSubtitle() {
      const parts = [this.exam.module_title, this.exam.category_title].filter(Boolean);
      return parts.length ? parts.join(" · ") : this.$t("exams.take.subtitle");
    },
  },

  created() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      this.loadError = "";

      try {
        const bundle = examApi.examBundle(await examApi.fetchExamQuestions(this.idEdit));
        this.exam = bundle.exam;
        this.questions = bundle.questions;

        if (!bundle.questions.length) {
          this.loadError = this.$t("exams.noQuestions");
        }
      } catch (e) {
        // 403 — экзамен не назначен или окно закрыто; причина уже
        // сформулирована сервером, показываем её, а не «что-то пошло не так».
        this.loadError = e?.response?.data?.error?.message || this.$t("exams.loadError");
      } finally {
        this.loading = false;
      }
    },

    isSelected(answerId) {
      return String(this.selected[this.currentQuestion?.id]) === String(answerId);
    },

    select(answerId) {
      // Прямое присваивание, а НЕ this.$set: $set — API Vue 2, в Vue 3
      // его нет, и вызов падал «this.$set is not a function», из-за чего
      // ответы не отмечались вовсе («Отвечено 0 из 5» при кликах) и
      // кнопка «Закончить» оставалась заблокированной.
      this.selected[this.currentQuestion.id] = answerId;
    },

    async submit() {
      this.submitting = true;

      const answers = Object.entries(this.selected).map(([questionId, answerId]) => ({
        question_id: Number(questionId),
        answer_id: Number(answerId),
      }));

      try {
        const raw = examApi.attemptResult(await examApi.submitExam(this.idEdit, answers));

        // Проценты считаются для показа, но вердикт «сдал/не сдал»
        // приходит с сервера: пересчитывать его на клиенте нельзя.
        this.result = {
          ...raw,
          scorePercent: Math.round((raw.score ?? 0) * 100),
          passingScorePercent: Math.round((raw.passing_score ?? 0) * 100),
        };
      } catch (e) {
        this.notify(e?.response?.data?.error?.message || this.$t("exams.submitError"), "error");
      } finally {
        this.submitting = false;
      }
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
.runner__progress {
  display: flex;
  justify-content: space-between;
  gap: var(--sp-3);
  margin-bottom: var(--sp-2);
}

.runner__question {
  padding: var(--sp-5);
}

.runner__question-text {
  margin: 0 0 var(--sp-4);
  font-size: 1.125rem;
  font-weight: 600;
}

.runner__answers {
  border: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--sp-2);
}

.runner__answer {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-3);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  background: var(--c-surface);
}

.runner__answer:hover {
  border-color: var(--c-primary);
}

.runner__answer--on {
  border-color: var(--c-primary);
  background: var(--c-primary-soft);
}

.runner__nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  margin-top: var(--sp-4);
}

.runner__hint {
  margin: var(--sp-2) 0 0;
  color: var(--c-text-muted);
  font-size: 0.8125rem;
}

.runner__result {
  padding: var(--sp-5);
}

.runner__result-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--sp-4);
  margin: var(--sp-4) 0;
}

.runner__result-cell {
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
}

.runner__result-value {
  font-size: 1.75rem;
  font-weight: 600;
  line-height: 1.1;
}

.runner__result-label {
  color: var(--c-text-muted);
  font-size: 0.8125rem;
}

.runner__verdict {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin: 0 0 var(--sp-2);
  font-weight: 600;
}

.runner__verdict--ok {
  color: var(--c-success);
}

.runner__verdict--fail {
  color: var(--c-danger);
}

.runner__result-actions {
  margin-top: var(--sp-4);
}
</style>
