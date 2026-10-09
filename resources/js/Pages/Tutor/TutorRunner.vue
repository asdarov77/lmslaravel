<template>
  <v-container class="tutor-runner">
    <v-row justify="space-between" align="center" class="mb-4">
      <v-col>
        <PageHeader :title="$t('tutor.runner')" />
        <p v-if="materialTitle" class="text-body-2 text-medium-emphasis">
          {{ materialTitle }}
        </p>
      </v-col>
      <v-col cols="auto">
        <v-btn variant="text" :to="{ name: 'tutor.index' }" data-test="tutor-back">
          {{ $t('tutor.back') }}
        </v-btn>
      </v-col>
    </v-row>

    <v-alert v-if="alert" :type="alert.type" density="compact" class="mb-4">{{ alert.text }}</v-alert>

    <!-- Генерация идёт: показываем ожидание с честным объяснением,
         сколько это займёт при синхронной очереди. -->
    <v-card v-if="generating && !item" class="mb-4" data-test="tutor-generating">
      <v-card-text class="d-flex align-center ga-3">
        <v-progress-circular indeterminate color="primary" size="20" />
        <span>{{ $t('tutor.generating') }}</span>
      </v-card-text>
    </v-card>

    <!--
      Генерация идёт в фоне. Если `php artisan queue:work` не запущен,
      задание не берётся никем и спиннер висит вечно. Раньше об этом
      нельзя было догадаться: пользователь просто ждал. Сообщаем
      прямо, что именно сломано и что делать.
    -->
    <v-alert
      v-if="generating && workerStalled"
      type="warning"
      density="compact"
      class="mb-4"
      data-test="tutor-worker-stalled"
    >
      {{ $t('tutor.workerStalled') }}
    </v-alert>

    <v-card v-if="generating && !asyncQueue && !workerStalled" type="info" density="compact" class="mb-4">
      {{ $t('tutor.syncQueueHint') }}
    </v-card>

    <EmptyState
      v-else-if="!item && !generating && !loading && exhausted"
      data-test="tutor-exhausted"
      :title="$t('tutor.exhaustedTitle')"
      :text="$t('tutor.exhaustedText')"
    />

    <v-card v-else-if="item" class="mb-4" data-test="tutor-question">
      <v-card-text>
        <div class="d-flex justify-space-between align-center mb-3">
          <span class="text-caption text-medium-emphasis">{{ typeLabel }}</span>
          <span class="text-caption text-medium-emphasis">
            {{ $t('tutor.progress', { done: answered, total: answered + 1 }) }}
          </span>
        </div>

        <p class="text-body-1 mb-4">{{ item.question }}</p>

        <!-- Варианты ответа. Кнопки, а не radio: после ответа вариант
             фиксируется и повторный выбор невозможен. -->
        <v-radio-group v-if="isChoice" v-model="choice" :disabled="answeredNow">
          <v-radio
            v-for="(option, index) in item.options"
            :key="index"
            :value="option"
            :class="{ 'tutor-option--correct': answeredNow && isRight(option) }"
            class="mb-1"
          />
        </v-radio-group>

        <v-textarea
          v-else
          v-model="freeAnswer"
          :label="$t('tutor.yourAnswer')"
          rows="3"
          :disabled="answeredNow"
          auto-grow
          data-test="tutor-free-answer"
        />
      </v-card-text>

      <v-card-actions v-if="!answeredNow">
        <v-spacer />
        <v-btn
          color="primary"
          variant="flat"
          :disabled="!canAnswer"
          :loading="sending"
          data-test="tutor-send"
          @click="send"
        >
          {{ $t('tutor.check') }}
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Результат ответа. -->
    <v-card v-if="result" class="mb-4" data-test="tutor-result">
      <v-card-text>
        <v-alert :type="verdictType" density="compact" class="mb-3">
          {{ verdictLabel }}
        </v-alert>

        <p v-if="result.feedback" class="text-body-2 mb-3">{{ result.feedback }}</p>

        <template v-if="revealed">
          <div class="text-caption text-medium-emphasis mb-1">
            {{ $t('tutor.reference') }}
          </div>
          <p class="text-body-2 mb-3">{{ result.reference_answer }}</p>

          <div class="text-caption text-medium-emphasis mb-1">
            {{ $t('tutor.sourceQuote') }}
          </div>
          <p class="text-body-2 text-italic">{{ result.source_quote }}</p>
        </template>
        <div v-else>
          <v-btn variant="text" size="small" data-test="tutor-show-reference" @click="showReference">
            {{ $t('tutor.showReference') }}
          </v-btn>
        </div>
      </v-card-text>

      <v-card-actions>
        <v-btn variant="text" @click="revealed = true" v-if="!revealed" class="d-none" />
        <v-spacer />
        <v-btn color="primary" variant="flat" data-test="tutor-next" @click="next">
          {{ $t('tutor.next') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>

<script>
/**
 * Окно тренировки: вопрос → ответ → разбор → следующий.
 *
 * Сессия живёт на сервере, поэтому окно можно закрыть и открыть заново
 * по адресу /tutor/run/:session — состояние восстановится. Это и было
 * требованием плана, а не украшением.
 *
 * Отличия от экзамена и почему они важны:
 *  - нет таймера и обратного отсчёта: тренажёр для самоподготовки, а
 *    не для оценки, спешка тут вредит;
 *  - нет «баллов зачёт»: результат не попадает в оценку;
 *  - эталон и цитата скрыты до нажатия — иначе правильный ответ едет
 *    вместе с вопросом.
 *
 * Тип ответа: mcq — выбор, остальные — свободный текст. Открытый ответ
 * по умолчанию выключен (tutor.default_types), потому что оценка
 * свободного текста моделью — самая слабая часть и портит первое же
 * впечатление.
 */
import {
  fetchTutorSession,
  fetchNextQuestion,
  answerQuestion,
} from '../../api/tutor.api'
import { unwrapResponse, metaField } from '../../api/envelope'
import EmptyState from '../../components/ui/EmptyState.vue'
import PageHeader from '../../components/ui/PageHeader.vue'

export default {
  name: 'TutorRunner',
  components: { EmptyState, PageHeader },
  data() {
    return {
      item: null,
      materialTitle: '',
      choice: null,
      freeAnswer: '',
      result: null,
      exhausted: false,
      revealed: false,
      answered: 0,
      sending: false,
      loading: false,
      generating: false,
      workerStalled: false,
      asyncQueue: true,
      alert: null,
      // Таймер опроса объявлен в data, а не в переменной модуля:
      // иначе при двух открытых окнах тренажёра очистался бы таймер
      // чужого окна, и одно из них переставало бы обновляться.
      pollTimer: null,
    }
  },
  computed: {
    isChoice() {
      return this.item?.qtype === 'mcq' && Array.isArray(this.item?.options)
    },
    answeredNow() {
      return this.result !== null
    },
    canAnswer() {
      return this.isChoice ? Boolean(this.choice) : this.freeAnswer.trim().length > 0
    },
    typeLabel() {
      return this.$t(`tutor.type.${this.item?.qtype ?? 'mcq'}`)
    },
    verdictType() {
      switch (this.result?.verdict) {
        case 'correct':
          return 'success'
        case 'partial':
          return 'warning'
        case 'ungraded':
          return 'info'
        default:
          return 'error'
      }
    },
    verdictLabel() {
      return this.$t(`tutor.verdict.${this.result?.verdict ?? 'ungraded'}`)
    },
  },
  async mounted() {
    await this.loadSession()
    await this.next()
    this.startPolling()
  },

  beforeUnmount() {
    this.stopPolling()
  },
  methods: {
    /**
     * Опрос состояния, пока вопросы готовятся в очереди.
     *
     * Генерация идёт отдельной задачей и занимает десятки секунд, а
     * HTTP-запрос на её запуск возвращается мгновенно. Без опроса окно
     * висело бы на «готовятся вопросы» до перезагрузки страницы.
     *
     * Опрос останавливается, как только появился вопрос, и не
     * дублируется: если пользователь ушёл на другую вкладку, таймер
     * всё равно снимается при закрытии компонента.
     */
    startPolling() {
      this.stopPolling()

      if (!this.generating || this.item) {
        return
      }

      this.pollTimer = setInterval(async () => {
        if (this.item || document.hidden) {
          return
        }

        await this.next()
      }, 5000)
    },

    stopPolling() {
      if (this.pollTimer !== null) {
        clearInterval(this.pollTimer)
        this.pollTimer = null
      }
    },

    async loadSession() {
      try {
        const session = unwrapResponse(await fetchTutorSession(this.$route.params.session))

        this.materialTitle = session?.material?.title ?? ''
        this.answered = session?.answered ?? 0
        this.generating = Boolean(session?.generating)
      this.workerStalled = Boolean(session?.worker_stalled)
        this.asyncQueue = session?.async !== false
      } catch (error) {
        this.alert = { type: 'error', text: this.errorText(error) }
      }
    },

    async next() {
      this.loading = true
      this.result = null
      this.revealed = false
      this.choice = null
      this.freeAnswer = ''

      try {
        // meta читается из СЫРОГО ответа: unwrapResponse возвращает
        // только payload, и флаг «вопросы кончились» жил бы в meta
        // конверта. Обращаясь к нему через unwrap, получали undefined,
        // и страница вечно показывала «готовятся вопросы» вместо
        // объяснения, что они кончились.
        const response = await fetchNextQuestion(this.$route.params.session)
        const payload = unwrapResponse(response)

        if (!payload?.item) {
          /*
           * Отсутствие вопроса НЕ означает, что вопросы кончились:
           * задание может ещё работать. Признак берём у сессии — он
           * ставится при постановке задания и снимается по его
           * завершению. Иначе первый пустой ответ останавливал опрос, и
           * вопросы, дошедшие позже, уже никто не забирал: пользователь
           * видел «вопросы закончились» при полной ленте.
           */
          this.item = null

          await this.loadSession()
          this.exhausted = !this.generating

          return
        }

        this.exhausted = false

        this.item = payload.item
        this.generating = false
        this.stopPolling()
      } catch (error) {
        this.alert = { type: 'error', text: this.errorText(error) }
      } finally {
        this.loading = false
      }
    },

    async send() {
      if (!this.item || !this.canAnswer) {
        return
      }

      this.sending = true
      this.alert = null

      try {
        const result = unwrapResponse(
          await answerQuestion(this.item.id, {
            answer: this.isChoice ? this.choice : this.freeAnswer,
          })
        )

        this.result = result
        this.answered += 1
      } catch (error) {
        this.alert = { type: 'error', text: this.errorText(error) }
      } finally {
        this.sending = false
      }
    },

    /**
     * Показать эталон.
     *
     * Запрашивается отдельным вызовом с show_reference: в ответе на
     * отправку ответа эталона нет, и он не появляется в HTML — то есть
     * не попадает в инструменты разработчика и в кеш браузера до
     * явного действия пользователя.
     */
    async showReference() {
      if (!this.item) {
        return
      }

      try {
        const result = unwrapResponse(
          await answerQuestion(this.item.id, {
            answer: this.isChoice ? this.choice : this.freeAnswer,
            show_reference: true,
          })
        )

        this.result = { ...this.result, ...result }
        this.revealed = true
      } catch (error) {
        this.alert = { type: 'error', text: this.errorText(error) }
      }
    },

    isRight(option) {
      return this.result?.reference_answer === option
    },

    errorText(error) {
      return (
        error?.response?.data?.error?.message ||
        error?.message ||
        this.$t('tutor.genericError')
      )
    },
  },
}
</script>

<style scoped>
.tutor-runner {
  max-width: 900px;
}
.tutor-option--correct {
  color: rgb(var(--v-theme-success));
}
</style>