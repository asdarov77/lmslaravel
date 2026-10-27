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

      <!-- Разбор по вопросам -->
      <div v-if="result.question_details?.length" class="runner__review">
        <h3 class="runner__review-title">{{ $t("exams.reviewTitle") }}</h3>
        <div
          v-for="(detail, idx) in result.question_details"
          :key="detail.question_id"
          class="runner__review-item"
          :class="detail.is_correct ? 'runner__review-item--correct' : 'runner__review-item--wrong'"
        >
          <div class="runner__review-header">
            <span class="runner__review-number">{{ idx + 1 }}.</span>
            <span class="runner__review-question">{{ detail.question_text }}</span>
            <v-icon
              :icon="detail.is_correct ? 'mdi-check-circle' : 'mdi-close-circle'"
              size="18"
              :aria-label="detail.is_correct ? $t('exams.correctAnswer') : $t('exams.wrongAnswer')"
            />
          </div>
          <div class="runner__review-body">
            <p class="runner__review-line">
              <span class="runner__review-label">{{ $t("exams.yourAnswer") }}:</span>
              <span>{{ detail.given_answer_text || $t("exams.noAnswer") }}</span>
            </p>
            <p v-if="!detail.is_correct" class="runner__review-line">
              <span class="runner__review-label">{{ $t("exams.correctAnswer") }}:</span>
              <span class="runner__review-correct">{{ detail.correct_answer_text }}</span>
            </p>
          </div>
        </div>
      </div>

      <div class="runner__result-actions">
        <v-btn color="primary" variant="flat" :to="{ name: 'exams.mine' }">
          {{ $t("exams.backToList") }}
        </v-btn>
      </div>
    </section>

    <section v-else class="runner">
      <!-- Таймер-кольцо -->
      <div v-if="timeLimit > 0" class="runner__timer">
        <ProgressRing
          :progress="timeProgress"
          :color="timeColor"
          :size="48"
          :stroke-width="4"
        >
          <span class="runner__timer-text">{{ formattedTimeLeft }}</span>
        </ProgressRing>
      </div>

      <div class="runner__progress">
        <span class="u-page__subtitle">
          {{ $t("exams.progress", { current: currentIndex + 1, total: questions.length }) }}
        </span>
        <span class="u-page__subtitle">
          {{ $t("exams.answeredOf", { answered: answeredCount, total: questions.length }) }}
        </span>
      </div>

      <!-- Навигатор вопросов -->
      <div v-if="questions.length > 1" class="runner__navigator">
        <button
          v-for="(q, idx) in questions"
          :key="q.id"
          class="runner__nav-dot"
          :class="{
            'runner__nav-dot--current': idx === currentIndex,
            'runner__nav-dot--answered': isAnswered(q.id),
            'runner__nav-dot--marked': markedQuestions.includes(q.id)
          }"
          :aria-label="$t('exams.goToQuestion', { number: idx + 1 })"
          :aria-current="idx === currentIndex ? 'true' : undefined"
          @click="currentIndex = idx"
        >
          {{ idx + 1 }}
        </button>
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
            variant="text"
            @click="toggleMark(currentQuestion?.id)"
          >
            <v-icon
              :icon="isMarked(currentQuestion?.id) ? 'mdi-bookmark' : 'mdi-bookmark-outline'"
              size="18"
              aria-hidden="true"
            ></v-icon>
            {{ isMarked(currentQuestion?.id) ? $t("exams.unmark") : $t("exams.mark") }}
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
            @click="confirmSubmit"
          >
            <v-icon start icon="mdi-check" size="18" aria-hidden="true"></v-icon>
            {{ $t("exams.finish") }}
          </v-btn>
        </div>

        <p v-if="!allAnswered" class="runner__hint">{{ $t("exams.answerAll") }}</p>
      </div>

      <!-- Диалог подтверждения завершения -->
      <ConfirmDialog
        v-model="showConfirmDialog"
        tone="primary"
        icon="mdi-flag-checkered"
        :title="$t('exams.confirmTitle')"
        :confirm-text="$t('exams.finish')"
        :cancel-text="$t('common.cancel')"
        @cancel="showConfirmDialog = false"
        @confirm="submit"
      >
        <p>{{ $t("exams.confirmText", { answered: answeredCount, total: questions.length }) }}</p>
        <p v-if="!allAnswered" class="runner__confirm-warning">
          {{ $t("exams.confirmWarning") }}
        </p>
      </ConfirmDialog>
    </section>

  </div>
</template>

<script>
import PageHeader from "../../components/ui/PageHeader.vue";
import EmptyState from "../../components/ui/EmptyState.vue";
import ProgressRing from "../../components/ui/ProgressBarRing.vue";
import ConfirmDialog from "../../components/ui/ConfirmDialog.vue";
import * as examApi from "../../api/exam.api";
import { toast } from "../../composables/useToast";

export default {
  name: "ExamRunner",

  components: { PageHeader, EmptyState, ProgressRing, ConfirmDialog },

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
      timeLimit: 0,
      timeLeft: 0,
      timerInterval: null,
      markedQuestions: [],
      showConfirmDialog: false,
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

    timeProgress() {
      if (this.timeLimit <= 0) return 0;
      return Math.max(0, Math.min(1, this.timeLeft / this.timeLimit));
    },

    timeColor() {
      if (this.timeLeft < 300) return 'var(--c-danger)';
      if (this.timeLeft < 600) return 'var(--c-warning)';
      return 'var(--c-primary)';
    },

    formattedTimeLeft() {
      const mins = Math.floor(this.timeLeft / 60);
      const secs = this.timeLeft % 60;
      return `${mins}:${secs.toString().padStart(2, '0')}`;
    },
  },

  created() {
    this.load();
  },

  beforeUnmount() {
    this.stopTimer();
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
        } else {
          if (this.exam.time_limit > 0) {
            this.timeLimit = this.exam.time_limit * 60;
            this.timeLeft = this.timeLimit;
            this.startTimer();
          }
        }
      } catch (e) {
        this.loadError = e?.response?.data?.error?.message || this.$t("exams.loadError");
      } finally {
        this.loading = false;
      }
    },

    startTimer() {
      this.stopTimer();
      this.timerInterval = setInterval(() => {
        this.timeLeft--;
        if (this.timeLeft <= 300 && this.timeLeft > 0 && this.timeLeft % 60 === 0) {
          this.notify(this.$t("exams.timeWarning", { minutes: Math.ceil(this.timeLeft / 60) }), "warning");
        }
        if (this.timeLeft <= 0) {
          this.stopTimer();
          this.notify(this.$t("exams.timeUp"), "error");
          this.submit();
        }
      }, 1000);
    },

    stopTimer() {
      if (this.timerInterval) {
        clearInterval(this.timerInterval);
        this.timerInterval = null;
      }
    },

    isSelected(answerId) {
      return String(this.selected[this.currentQuestion?.id]) === String(answerId);
    },

    select(answerId) {
      this.selected[this.currentQuestion.id] = answerId;
    },

    isAnswered(questionId) {
      return this.selected[questionId] !== undefined;
    },

    isMarked(questionId) {
      return this.markedQuestions.includes(questionId);
    },

    toggleMark(questionId) {
      if (!questionId) return;
      const idx = this.markedQuestions.indexOf(questionId);
      if (idx >= 0) {
        this.markedQuestions.splice(idx, 1);
      } else {
        this.markedQuestions.push(questionId);
      }
    },

    confirmSubmit() {
      this.showConfirmDialog = true;
    },

    async submit() {
      if (this.submitting) return;
      this.showConfirmDialog = false;
      this.stopTimer();
      this.submitting = true;

      const answers = Object.entries(this.selected).map(([questionId, answerId]) => ({
        question_id: Number(questionId),
        answer_id: Number(answerId),
      }));

      try {
        const raw = examApi.attemptResult(await examApi.submitExam(this.idEdit, answers));

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
      toast.byType(type, text);
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

.runner__timer {
  display: flex;
  justify-content: center;
  margin-bottom: var(--sp-3);
}

.runner__timer-text {
  font-size: 0.875rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.runner__navigator {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
  margin-bottom: var(--sp-3);
  padding: var(--sp-3);
  background: var(--c-surface-2);
  border-radius: var(--radius-md);
}

.runner__nav-dot {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid var(--c-border);
  border-radius: var(--radius-sm);
  background: var(--c-surface);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--dur-fast) var(--ease-out);
}

.runner__nav-dot:hover {
  border-color: var(--c-primary);
}

.runner__nav-dot--current {
  background: var(--c-primary);
  color: var(--c-on-primary);
  border-color: var(--c-primary);
}

.runner__nav-dot--answered {
  background: var(--c-primary-soft);
  border-color: var(--c-primary);
}

.runner__nav-dot--marked {
  position: relative;
}

.runner__nav-dot--marked::after {
  content: '';
  position: absolute;
  top: 2px;
  right: 2px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--c-warning);
}

.runner__confirm-warning {
  margin-top: var(--sp-2);
  color: var(--c-danger);
  font-weight: 500;
}

.runner__review {
  margin-top: var(--sp-5);
  padding-top: var(--sp-4);
  border-top: 1px solid var(--c-border);
}

.runner__review-title {
  margin: 0 0 var(--sp-3);
  font-size: 1rem;
  font-weight: 600;
}

.runner__review-item {
  padding: var(--sp-3);
  margin-bottom: var(--sp-2);
  border-radius: var(--radius-md);
  border: 1px solid var(--c-border);
}

.runner__review-item--correct {
  border-color: var(--c-success);
  background: color-mix(in srgb, var(--c-success) 5%, transparent);
}

.runner__review-item--wrong {
  border-color: var(--c-danger);
  background: color-mix(in srgb, var(--c-danger) 5%, transparent);
}

.runner__review-header {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  margin-bottom: var(--sp-2);
}

.runner__review-number {
  font-weight: 600;
  color: var(--c-text-muted);
}

.runner__review-question {
  flex: 1;
  font-weight: 500;
}

.runner__review-body {
  padding-left: var(--sp-5);
}

.runner__review-line {
  margin: 0 0 var(--sp-1);
  font-size: 0.875rem;
}

.runner__review-label {
  color: var(--c-text-muted);
  margin-right: var(--sp-1);
}

.runner__review-correct {
  color: var(--c-success);
  font-weight: 500;
}

@media (max-width: 768px) {
  .runner__navigator {
    gap: var(--sp-1);
    padding: var(--sp-2);
  }

  .runner__nav-dot {
    width: 32px;
    height: 32px;
    font-size: 0.75rem;
  }

  .runner__review-body {
    padding-left: 0;
  }
}
</style>
