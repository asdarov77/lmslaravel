<!--
  InlineQuizRunner — тест внутри «Плеера 2.0» (design-manifest §2.2, Часть 3.1).

  Принципы:
   - один вопрос на экран (Duolingo-ритм): нет длинного скролла и «стены форм»;
   - ответ собирается локально, ОЦЕНКА — только сервером: submit отдаёт verdict
     наружу (родитель CoursePlayerPremium шлёт его в exam.api и показывает
     celebrate-оверлей). Клиентская галочка не является источником оценки;
   - прогресс модуля — тонкая полоса над вопросом (не номер «3/10» как декор);
   - клавиатура: 1–4 выбирают вариант, Enter — далее (SC 2.1.1).

  schema (payload урока content_type=quiz):
    { title?, questions: [ { text, options: [string], correct?: number } ] }
  correct используется только для предпросмотра автором (preview=true);
  студенту серверный verdict приходит снаружи.
-->
<template>
  <form class="iqr" @submit.prevent="next">
    <div class="iqr__head d-flex align-center ga-3 mb-2">
      <v-icon size="18" color="accent" aria-hidden="true">mdi-help-circle-outline</v-icon>
      <span class="text-subtitle-2 text-truncate">{{ schema?.title || $t('coursePlayer.quizTitle') }}</span>
      <v-spacer />
      <span class="text-caption u-muted iqr__count">{{ idx + 1 }} / {{ questions.length }}</span>
    </div>

    <!-- прогресс прохождения теста: состояние, а не украшение -->
    <v-progress-linear
      :model-value="progressPercent" height="4" rounded color="accent"
      class="mb-5" aria-hidden="true"
    />

    <transition name="iqr-slide" mode="out-in">
      <div :key="idx" class="iqr__q">
        <p class="iqr__text">{{ current.text }}</p>

        <!-- radio нативные, стилизованы карточками-опциями: фокус-кольцо видно всегда -->
        <div class="iqr__options" role="radiogroup" :aria-label="current.text">
          <label
            v-for="(opt, i) in current.options"
            :key="i"
            class="iqr__opt"
            :class="{ 'iqr--selected': answers[idx] === i, 'iqr--preview-correct': preview && answered && i === current.correct }"
          >
            <input
              type="radio"
              class="iqr__radio"
              :name="'iqr-q-' + idx"
              :value="i"
              :checked="answers[idx] === i"
              @change="select(i)"
            >
            <span class="iqr__kbd" aria-hidden="true">{{ i + 1 }}</span>
            <span class="iqr__opt-text">{{ opt }}</span>
            <v-icon v-if="preview && answered && i === current.correct" size="18" color="success" class="ml-auto">
              mdi-check-circle
            </v-icon>
          </label>
        </div>

        <p v-if="answered && !isCorrect" class="iqr__retry text-caption" role="status">
          {{ $t('coursePlayer.quizTryAgain') }}
        </p>
      </div>
    </transition>

    <div class="iqr__actions d-flex align-center mt-6">
      <v-btn
        variant="text" icon="mdi-chevron-left" :disabled="idx === 0"
        :aria-label="$t('courseManifest.prevLesson')" @click="prev"
      />
      <v-spacer />
      <v-btn
        type="submit" color="primary" rounded="pill" size="large" class="px-8"
        :disabled="answers[idx] == null || (answered && !isCorrect)"
      >
        {{ last ? $t('coursePlayer.quizSubmit') : $t('coursePlayer.quizNext') }}
      </v-btn>
    </div>
  </form>
</template>

<script>
export default {
  name: 'InlineQuizRunner',
  props: {
    schema: { type: Object, default: null },
    // preview: режим автора — показывать правильные ответы после ответа
    preview: { type: Boolean, default: false },
  },
  emits: ['answer', 'submit'],
  data() {
    return { idx: 0, answers: [], answered: false }
  },
  computed: {
    questions() {
      return Array.isArray(this.schema?.questions) ? this.schema.questions : []
    },
    current() {
      return this.questions[this.idx] || { text: '', options: [] }
    },
    last() {
      return this.idx === this.questions.length - 1
    },
    progressPercent() {
      if (!this.questions.length) return 0
      return Math.round((this.answers.filter((a) => a != null).length / this.questions.length) * 100)
    },
    isCorrect() {
      return this.current.correct != null && this.answers[this.idx] === this.current.correct
    },
  },
  watch: {
    schema() { this.idx = 0; this.answers = []; this.answered = false },
  },
  mounted() {
    window.addEventListener('keydown', this.onKey)
  },
  beforeUnmount() {
    window.removeEventListener('keydown', this.onKey)
  },
  methods: {
    select(i) {
      this.answers.splice(this.idx, 1, i) // splice вместо индексного присваивания: надёжный reactive-триггер массива
      this.answered = this.preview
      this.$emit('answer', { index: this.idx, choice: i })
    },
    prev() {
      if (this.idx > 0) { this.idx -= 1; this.answered = false }
    },
    next() {
      if (this.answers[this.idx] == null) return
      if (this.preview && !this.isCorrect) return // автор: дать переответить
      if (this.last) {
        // verdict наружу — родитель отправляет на сервер и получает passed/xp
        this.$emit('submit', { answers: [...this.answers], count: this.questions.length })
        return
      }
      this.idx += 1
      this.answered = false
    },
    onKey(e) {
      const t = e.target
      if (t && (t.tagName === 'INPUT' && t.type !== 'radio' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return
      const n = Number(e.key)
      if (n >= 1 && n <= (this.current.options?.length || 0)) {
        e.preventDefault()
        this.select(n - 1)
      }
    },
  },
}
</script>

<style scoped>
.iqr { max-width: 720px; margin: 0 auto; }
.iqr__count { font-variant-numeric: tabular-nums; }
.iqr__text { font-size: var(--fs-lg); font-weight: 600; letter-spacing: -0.01em; line-height: 1.4; margin: 0 0 20px; }

/* Опция = карточка с полем ввода внутри: border-layering вместо Material-radio */
.iqr__opt {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; margin-bottom: 10px; cursor: pointer;
  border: 1px solid var(--c-border); border-radius: var(--radius-md);
  background: var(--c-surface);
  transition: border-color var(--dur-fast) ease, background var(--dur-fast) ease, transform var(--dur-fast) ease;
}
.iqr__opt:hover { border-color: var(--c-border-strong); }
.iqr__opt:focus-within { outline: 2px solid var(--c-primary); outline-offset: 2px; }
.iqr--selected { border-color: var(--c-accent); background: var(--c-accent-soft); }
.iqr--preview-correct { border-color: var(--c-success); background: var(--c-success-soft); }
.iqr__radio { position: absolute; opacity: 0; width: 1px; height: 1px; } /* label держит фокус-стиль */
.iqr__kbd {
  flex: none; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center;
  border: 1px solid var(--c-border-strong); border-radius: 6px;
  font-size: var(--fs-2xs); font-weight: 700; color: var(--c-text-secondary);
}
.iqr--selected .iqr__kbd { border-color: var(--c-accent); color: var(--c-accent); }
.iqr__opt-text { font-size: var(--fs-base); }
.iqr__retry { color: var(--c-warning); }

/* вход вопроса: fade+slide 8px; выход: только fade (motion-словарь Манифеста) */
.iqr-slide-enter-active { transition: opacity var(--dur-base) cubic-bezier(0.2, 0, 0, 1), transform var(--dur-base) cubic-bezier(0.2, 0, 0, 1); }
.iqr-slide-leave-active { transition: opacity var(--dur-fast) ease; }
.iqr-slide-enter-from { opacity: 0; transform: translateY(8px); }
.iqr-slide-leave-to { opacity: 0; }

@media (prefers-reduced-motion: reduce) {
  .iqr__opt, .iqr-slide-enter-active, .iqr-slide-leave-active { transition: none; }
}
</style>
