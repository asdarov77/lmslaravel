<template>
  <div class="u-page">
    <PageHeader
      :title="$t('profile.title')"
      :subtitle="$t('profile.subtitle')"
    >
      <template #actions>
        <v-btn
          variant="text"
          size="small"
          :to="{ name: 'learning.plan' }"
        >
          {{ $t("profile.openPlan") }}
        </v-btn>
      </template>
    </PageHeader>

    <div class="u-grid u-grid--2">
      <!-- Кто вошёл -->
      <section class="u-card" data-test="profile-card">
        <h2 class="u-card__title">{{ $t("profile.about") }}</h2>

        <div class="profile__identity">
          <v-avatar color="primary" size="64" class="profile__avatar">
            <v-img v-if="avatarUrl" :src="avatarUrl" :alt="user?.fio || ''" cover />
            <span v-else>{{ initials }}</span>
          </v-avatar>
          <div>
            <span class="profile__name">{{ user?.fio || $t("profile.noName") }}</span>
            <span class="profile__role">{{ roleLabel }}</span>

            <!--
              Загрузка аватара скрыта на узких экранах не из-за
              декора, а потому что выбор файла на телефоне перекрывает
              всю шапку; ссылка остаётся доступной с клавиатуры.
            -->
            <div class="profile__avatar-actions">
              <v-btn
                size="x-small"
                variant="text"
                prepend-icon="mdi-camera"
                class="mt-1"
                data-test="avatar-pick"
                @click="pickAvatar"
              >
                {{ $t("profile.avatarChange") }}
              </v-btn>
              <v-btn
                v-if="avatarUrl"
                size="x-small"
                variant="text"
                color="error"
                class="mt-1 ml-2"
                data-test="avatar-remove"
                :loading="removingAvatar"
                :disabled="removingAvatar"
                @click="removeAvatar"
              >
                {{ $t("profile.avatarRemove") }}
              </v-btn>

              <!-- input скрыт, но не disabled: без него не работает
                   и клавиатурная активация кнопки. -->
              <input
                ref="avatarInput"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="d-none"
                data-test="avatar-input"
                @change="onAvatarSelected"
              />
            </div>

            <v-alert v-if="avatarError" type="error" density="compact" class="mt-2" data-test="avatar-error">
              {{ avatarError }}
            </v-alert>
          </div>
        </div>

        <!--
          Поля выводятся только заполненные. Пустая строка «телефон: —»
          выглядит как ошибка заполнения и тревожит сильнее, чем
          отсутствие строки.
        -->
        <dl class="profile__fields">
          <template v-for="row in rows" :key="row.key">
            <dt v-if="row.value">{{ $t(row.label) }}</dt>
            <dd v-if="row.value">{{ row.value }}</dd>
          </template>
        </dl>

        <p v-if="!rows.length" class="u-muted">{{ $t("profile.noFields") }}</p>
      </section>

      <!--
        Права.

        Показываются списком, а не числом: «у вас 12 прав» ничего не
        сообщает. Список заодно объясняет, почему в меню есть именно эти
        пункты, — то есть отвечает на вопрос «почему мне недоступно то
        самое». Группировка по разделам повторяет config/permissions.php.
      -->
      <section class="u-card" data-test="profile-permissions">
        <h2 class="u-card__title">{{ $t("profile.permissions") }}</h2>
        <p class="u-page__subtitle">{{ $t("profile.permissionsHint") }}</p>

<div v-if="permissionGroups.length" class="profile__perms">
            <div v-for="group in permissionGroups" :key="group.key" class="profile__perm-group">
              <span class="profile__perm-title">{{ $t(group.titleKey) }}</span>
              <ul class="profile__perm-list">
                <li v-for="slug in group.slugs" :key="slug">
                  <span class="u-badge">{{ permissionLabel(slug) }}</span>
                </li>
              </ul>
            </div>
          </div>
          <p v-else class="u-muted">{{ $t("profile.noPermissions") }}</p>
    </section>

      <!--
        Моё обучение и ближайшие сроки.

        Личный кабинет по практикам LMS отвечает на вопрос «что делать
        сегодня», а не только рассказывает о правах. Данные — из
        /api/my/dashboard (inProgress, upcoming): прогресс считается по
        урокам (LessonProgress), дедлайн — по deadline, иначе по концу
        периода. Блок грузится отдельно: отказ не должен превращать
        профиль в ошибку.
      -->
      <div class="u-grid u-grid--2 profile__learning" data-test="profile-learning">
        <section class="u-card">
          <h2 class="u-card__title">{{ $t("profile.learning.title") }}</h2>
          <p class="u-page__subtitle">{{ $t("profile.learning.hint") }}</p>

          <div v-if="inProgress.length" class="profile__courses">
            <router-link
              v-for="row in inProgress"
              :key="row.course_id"
              class="profile__course"
              :to="{ name: 'courses.itemmani', query: { idEdit: row.course_id } }"
            >
              <ProgressBarRing :value="row.percent" :size="56" :stroke="6" />
              <span class="profile__course-body">
                <span class="profile__course-title">{{ row.title }}</span>
                <span v-if="row.due_at" class="profile__course-due u-muted">
                  {{ $t("profile.learning.dueLabel") }}: {{ formatDate(row.due_at) }}
                </span>
              </span>
              <v-icon size="18" class="profile__course-arrow">mdi-chevron-right</v-icon>
            </router-link>
          </div>
          <p v-else class="u-muted">{{ $t("profile.learning.empty") }}</p>
        </section>

        <section class="u-card">
          <h2 class="u-card__title">{{ $t("profile.deadlines.title") }}</h2>
          <p class="u-page__subtitle">{{ $t("profile.deadlines.hint") }}</p>

          <ul v-if="upcoming.length" class="profile__deadlines">
            <li v-for="row in upcoming" :key="`dl-${row.course_id}`">
              <router-link
                class="profile__deadline"
                :to="{ name: 'courses.itemmani', query: { idEdit: row.course_id } }"
              >
                <span class="profile__course-body">
                  <span class="profile__course-title">{{ row.title }}</span>
                  <time class="profile__course-due u-muted" :datetime="row.due_at">
                    {{ formatDate(row.due_at) }}
                  </time>
                </span>
                <span class="u-badge" :class="row.days_left <= 1 ? 'u-badge--danger' : 'u-badge--warning'">
                  {{ row.days_left === 0 ? $t("dashboard.deadlineToday") : row.days_left === 1 ? $t("dashboard.deadlineTomorrow") : $t("dashboard.deadlineInDays", { days: row.days_left }) }}
                </span>
              </router-link>
            </li>
          </ul>
          <p v-else class="u-muted">{{ $t("profile.deadlines.empty") }}</p>
        </section>
      </div>

        <!-- 2FA: двухфакторная аутентификация. -->
        <section class="u-card" data-test="profile-2fa">
          <h2 class="u-card__title">{{ $t("profile.twoFactor.title") }}</h2>
          <p class="u-page__subtitle">{{ $t("profile.twoFactor.hint") }}</p>

          <v-alert
            v-if="twoFactorEnabled"
            type="success"
            variant="tonal"
            density="compact"
            class="mb-3"
          >
            {{ $t("profile.twoFactor.enabled") }}
          </v-alert>
          <v-alert
            v-else
            type="info"
            variant="tonal"
            density="compact"
            class="mb-3"
          >
            {{ $t("profile.twoFactor.disabled") }}
          </v-alert>

          <div v-if="!twoFactorSetupLoaded" class="d-flex justify-center py-4">
            <v-progress-circular indeterminate :aria-label="$t('common.loading')"></v-progress-circular>
          </div>

          <div v-else-if="!twoFactorEnabled" class="two-factor__setup">
            <h3 class="two-factor__title">{{ $t("profile.twoFactor.setupTitle") }}</h3>
            <p class="two-factor__text">{{ $t("profile.twoFactor.setupText") }}</p>

            <div v-if="qrCodeUrl" class="two-factor__qr mb-4">
              <img :src="qrCodeUrl" :alt="$t('profile.twoFactor.qrAlt')" />
            </div>

            <div v-if="secret" class="two-factor__secret mb-4">
              <!--
                Подпись связана с полем через for/id. Раньше <label> шёл
                рядом с <input> без id, поэтому поле оставалось БЕЗ
                доступного имени: скринридер читал «поле ввода» вместо
                «Секретный ключ». WCAG 2.2 SC 1.3.1 и 4.1.2.
              -->
              <label class="u-field__label" for="two-factor-secret">
                {{ $t("profile.twoFactor.secretLabel") }}
              </label>
              <div class="d-flex align-center gap-2">
                <input
                  id="two-factor-secret"
                  type="text"
                  readonly
                  :value="secret"
                  class="u-field__input u-mono"
                />
                <v-btn
                  size="x-small"
                  variant="text"
                  prepend-icon="mdi-content-copy"
                  @click="copySecret"
                >
                  {{ $t("profile.twoFactor.copy") }}
                </v-btn>
              </div>
            </div>

            <v-text-field
              v-model="totpCode"
              :label="$t('profile.twoFactor.codeLabel')"
              :placeholder="$t('profile.twoFactor.codePlaceholder')"
              maxlength="6"
              inputmode="numeric"
              autocomplete="one-time-code"
              density="compact"
              variant="outlined"
              class="mb-3"
              hide-details="auto"
              :error-messages="totpError"
              @update:model-value="onTotpInput"
              @keyup.enter="enable2FA"
            />

            <v-btn
              color="primary"
              variant="flat"
              :loading="enabling2FA"
              @click="enable2FA"
            >
              {{ $t("profile.twoFactor.enable") }}
            </v-btn>
          </div>

          <div v-else class="two-factor__manage">
            <div class="d-flex flex-wrap gap-2 mb-3">
              <v-btn
                color="warning"
                variant="tonal"
                prepend-icon="mdi-key-variant"
                @click="regenerateRecoveryCodes"
              >
                {{ $t("profile.twoFactor.regenerateCodes") }}
              </v-btn>
              <v-btn
                color="error"
                variant="tonal"
                prepend-icon="mdi-shield-off-outline"
                @click="disable2FA"
              >
                {{ $t("profile.twoFactor.disable") }}
              </v-btn>
            </div>

            <v-alert
              v-if="recoveryCodes.length"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-3"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="u-typography__subtitle-2">{{ $t("profile.twoFactor.recoveryCodesLabel") }}</span>
                <v-btn size="x-small" variant="text" @click="copyRecoveryCodes">
                  {{ $t("profile.twoFactor.copyAll") }}
                </v-btn>
              </div>
              <div class="two-factor__recovery-codes">
                <span
                  v-for="code in recoveryCodes"
                  :key="code"
                  class="two-factor__recovery-code"
                >{{ code }}</span>
              </div>
            </v-alert>
          </div>
        </section>
      </div>
    </div>
</template>

<script>
import { mapGetters, mapState } from "vuex";
import $api from "../api/httpClient";
import { unwrapField } from "../api/envelope";
import learningApi from "../api/learning.api";
import PageHeader from "../components/ui/PageHeader.vue";
import ProgressBarRing from "../components/ui/ProgressBarRing.vue";

/**
 * Профиль пользователя.
 *
 * Раньше здесь был закомментированный шаблон: пункт «Профиль» в меню
 * вёл на пустую страницу, и это выглядело как «личный кабинет грузится
 * и ничего не показывает».
 *
 * Данные берутся из стора, отдельного запроса не делается:
 * GET /api/v1/me уже отдаёт пользователя и эффективный набор прав, а
 * он нужен и меню, и странице. Дублирующий запрос означал бы вторую
 * точку правды о том, какие права у человека.
 */
export default {
  components: { PageHeader, ProgressBarRing },

  mounted() {
    this.loadTwoFactorSetup();
    this.loadLearningSummary();
  },

  data: () => ({
    // Разделы для группировки прав. Ключи совпадают с разделами
    // config/permissions.php на бэкенде — это их названия, а не
    // классификация, придуманная здесь.
    permSections: [
      { key: "courses", titleKey: "profile.permCourses" },
      { key: "users", titleKey: "profile.permUsers" },
      { key: "groups", titleKey: "profile.permGroups" },
      { key: "exams", titleKey: "profile.permExams" },
      { key: "questions", titleKey: "profile.permQuestions" },
      { key: "tutor", titleKey: "profile.permTutor" },
      { key: "content", titleKey: "profile.permContent" },
      { key: "system", titleKey: "profile.permSystem" },
    ],
    // Текст ошибки загрузки аватара: сервер вернёт 422 с перечнем
    // правил, а человеку нужна одна понятная фраза.
    avatarError: null,
    removingAvatar: false,
    uploadingAvatar: false,
    // 2FA
    twoFactorEnabled: false,
    twoFactorSetupLoaded: false,
    secret: null,
    qrCodeUrl: null,
    totpCode: '',
    totpError: null,
    enabling2FA: false,
    recoveryCodes: [],
    // Блок «Моё обучение» / «Ближайшие сроки» (из /api/my/dashboard).
    inProgress: [],
    upcoming: [],
  }),

  computed: {
    // permissionSlugs — это STATE, а не геттер: mapGetters вернул бы
    // undefined, и раздел прав был бы пустым, а в консоли сыпался бы
    // «[vuex] unknown getter: Auth/permissionSlugs». Роль доступна и как
    // state, и как геттер; берём геттер — он уже нормализует слаги.
    ...mapState("Auth", ["user", "permissionSlugs"]),
    ...mapGetters("Auth", ["roleSlugs", "isSuperAdmin"]),

    /**
     * Адрес аватара.
     *
     * Файл кладётся в storage/public, но раздаётся веб-сервером из
     * /storage, а не из api: путь строим сами, потому что в ответе API
     * лежит относительный путь вида avatars/12/abc.jpg.
     */
    avatarUrl() {
      const path = this.user?.avatar;

      if (!path) return null;

      if (/^https?:/i.test(path)) return path;

      const base = import.meta.env.VITE_STORAGE_URL || "/storage";

      return `${base}/${String(path).replace(/^\/+/, "")}`;
    },

    initials() {
      const fio = String(this.user?.fio || "").trim();

      if (!fio) return "?";

      return fio
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
    },

    roleLabel() {
      if (this.isSuperAdmin) return this.$t("profile.roleAdmin");

      const slug = this.roleSlugs?.[0];

      return slug ? this.$t(`profile.roles.${slug}`) : this.$t("profile.roleUnknown");
    },

    /**
     * Заполненные поля профиля.
     *
     * Пустые отсекаются здесь, а не шаблоном: иначе пришлось бы писать
     * v-if в каждой строке dt/dd.
     */
    rows() {
      const u = this.user || {};

      return [
        { key: "email", label: "profile.fields.email", value: u.email },
        { key: "phone", label: "profile.fields.phone", value: u.phonenumber },
        { key: "city", label: "profile.fields.city", value: u.city },
        { key: "country", label: "profile.fields.country", value: u.country },
        { key: "org", label: "profile.fields.organization", value: u.organization },
        { key: "position", label: "profile.fields.position", value: u.position },
        { key: "rank", label: "profile.fields.rank", value: u.rank },
        { key: "spec", label: "profile.fields.specialization", value: u.specialization },
      ].filter((row) => row.value && String(row.value).trim());
    },

    /** Права, сгруппированные по разделам; без «прочих». */
    permissionGroups() {
      const known = this.permSections.map((section) => ({
        ...section,
        slugs: [],
      }));

      for (const slug of this.permissionSlugs || []) {
        const [section] = String(slug).split(".");
        const bucket = known.find((k) => k.key === section);

        if (bucket) bucket.slugs.push(slug);
      }

      return known.filter((group) => group.slugs.length);
    },
  },

  methods: {
    /**
     * Сводка обучения для блоков «Моё обучение» и «Ближайшие сроки».
     *
     * Отдельный необязательный запрос (как на дашборде): отказ сервера
     * или отсутствие группы не должны ломать профиль — блоки просто
     * остаются пустыми с понятной подписью.
     */
    async loadLearningSummary() {
      try {
        const response = await learningApi.fetchMyDashboard();
        const summary = learningApi.dashboard(response);

        this.inProgress = Array.isArray(summary.inProgress) ? summary.inProgress : [];
        this.upcoming = Array.isArray(summary.upcoming) ? summary.upcoming : [];
      } catch (error) {
        // Пустые данные — валидное состояние (нет группы, нет назначений).
        console.warn('[MyAccount] loadLearningSummary:', error?.message);
      }
    },

    /** Дата в локальном формате; не падает на пустом значении. */
    formatDate(value) {
      if (!value) return '';

      const date = new Date(value);

      return Number.isNaN(date.getTime())
        ? String(value)
        : date.toLocaleDateString(this.$locale || undefined, { day: 'numeric', month: 'short' });
    },

    /** Открывает системный диалог выбора файла. */
    pickAvatar() {
      this.avatarError = null;
      this.$refs.avatarInput?.click();
    },

    /**
     * Загрузка выбранного файла.
     *
     * Файл отправляется сразу, без отдельной кнопки «сохранить»:
     * аватар — единственное, что меняется здесь, и лишний шаг только
     * путает: человек нажимает «Сохранить» на аватаре и не понимает,
     * почему ничего не происходит.
     */
    async onAvatarSelected(event) {
      const file = event.target.files?.[0];

      // Сбрасываем значение: повторный выбор того же файла иначе не
      // вызовет change, и «замена аватара» выглядела бы сломанной.
      event.target.value = "";

      if (!file) return;

      if (file.size > 2 * 1024 * 1024) {
        this.avatarError = this.$t("profile.avatarTooBig");

        return;
      }

      const form = new FormData();
      form.append("avatar", file);

      this.uploadingAvatar = true;
      this.avatarError = null;

      try {
        const response = await $api.post("/api/user/avatar", form, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        const path = unwrapField(response, 'avatar') ?? response?.data?.data?.avatar;
        this.$store.commit("Auth/SET_USER_AVATAR", path);
      } catch (error) {
        this.avatarError = this.$t("profile.avatarFailed");
      } finally {
        this.uploadingAvatar = false;
      }
    },

    /**
     * Убрать аватар.
     *
     * Раньше здесь был только `SET_USER_AVATAR(null)` — то есть
     * менялось состояние вкладки, а не данные: после перезагрузки
     * аватар возвращался, и человек заключал, что кнопка не работает.
     * Теперь запрос уходит на сервер, и запись стирается по-настоящему.
     */
    async removeAvatar() {
      if (this.removingAvatar) return;

      this.removingAvatar = true;
      this.avatarError = null;

      try {
        await $api.delete("/api/user/avatar");
        this.$store.commit("Auth/SET_USER_AVATAR", null);
      } catch (e) {
        this.avatarError = this.$t("profile.avatarRemoveFailed");
      } finally {
        this.removingAvatar = false;
      }
    },

    /**
     * Право показывается словом, а не slug'ом.
     *
     * «courses.view» в интерфейсе — это мусор из базы; человек должен
     * видеть «просмотр курсов». Если перевода нет, показывается slug, а
     * не пустая строка: лучше техническое слово, чем ничего.
     */
    permissionLabel(slug) {
      const key = `perms.${String(slug).replace(/\./g, "_")}`;

      const translated = this.$t(key);

      return translated === key ? slug : translated;
    },

    // ===== 2FA Methods =====

    /** Загружает настройки 2FA для текущего пользователя. */
    async loadTwoFactorSetup() {
      this.twoFactorSetupLoaded = false;
      try {
        const response = await $api.get('/api/user/two-factor');
        const data = response?.data?.data ?? {};
        this.twoFactorEnabled = Boolean(data.enabled);
        this.secret = data.secret;
        this.qrCodeUrl = data.qr_code_url;
        this.recoveryCodes = data.recovery_codes ?? [];
      } catch (error) {
        console.warn('[MyAccount] loadTwoFactorSetup:', error?.message);
      } finally {
        this.twoFactorSetupLoaded = true;
      }
    },

    // Клиентская валиация кода 2FA: принимаем только цифры,
    // сразу отсекая буквы/пробелы и показывая понятную ошибку.
    onTotpInput(value) {
      const digits = String(value ?? '').replace(/\D/g, '').slice(0, 6);

      this.totpCode = digits;
      this.totpError = null;
    },

    /** Включает 2FA после проверки TOTP кода. */
    async enable2FA() {
      if (!/^\d{6}$/.test(this.totpCode)) {
        this.totpError = this.$t('profile.twoFactor.codeInvalid');
        return;
      }

      this.enabling2FA = true;
      try {
        const response = await $api.post('/api/user/two-factor/enable', {
          code: this.totpCode,
        });
        this.twoFactorEnabled = true;
        this.totpCode = '';
        this.recoveryCodes = response?.data?.data?.recovery_codes ?? [];
        this.notify(this.$t('profile.twoFactor.enabledSuccess'));
        this.notify(this.$t('profile.twoFactor.saveCodesHint'), 'info');
      } catch (error) {
        this.notify(
          error?.response?.data?.error?.message || this.$t('profile.twoFactor.enableFailed'),
          'error'
        );
      } finally {
        this.enabling2FA = false;
      }
    },

    /** Отключает 2FA после проверки кода. */
    async disable2FA() {
      const code = window.prompt(this.$t('profile.twoFactor.disablePrompt'));
      if (!code || code.length !== 6) return;

      try {
        await $api.post('/api/user/two-factor/disable', { code: code.trim() });
        this.twoFactorEnabled = false;
        this.secret = null;
        this.qrCodeUrl = null;
        this.recoveryCodes = [];
        this.notify(this.$t('profile.twoFactor.disabledSuccess'));
      } catch (error) {
        this.notify(
          error?.response?.data?.error?.message || this.$t('profile.twoFactor.disableFailed'),
          'error'
        );
      }
    },

    /** Регенерирует коды восстановления. */
    async regenerateRecoveryCodes() {
      const confirmed = window.confirm(this.$t('profile.twoFactor.regenerateConfirm'));
      if (!confirmed) return;

      try {
        const response = await $api.post('/api/user/two-factor/regenerate-codes');
        this.recoveryCodes = response?.data?.data?.recovery_codes ?? [];
        this.notify(this.$t('profile.twoFactor.codesRegenerated'));
        this.notify(this.$t('profile.twoFactor.saveCodesHint'), 'info');
      } catch (error) {
        this.notify(
          error?.response?.data?.error?.message || this.$t('profile.twoFactor.regenerateFailed'),
          'error'
        );
      }
    },

    /** Копирует секрет в буфер обмена. */
    copySecret() {
      navigator.clipboard.writeText(this.secret || '');
      this.notify(this.$t('profile.twoFactor.secretCopied'));
    },

    /** Копирует все коды восстановления в буфер обмена. */
    copyRecoveryCodes() {
      navigator.clipboard.writeText(this.recoveryCodes.join('\n'));
      this.notify(this.$t('profile.twoFactor.codesCopied'));
    },
  },
};
</script>

<style scoped>
.profile__identity {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.profile__avatar {
  font-weight: 600;
}

.profile__name {
  display: block;
  font-weight: 600;
}

.profile__role {
  display: block;
  color: var(--c-text-muted);
  font-size: 0.9rem;
}

.profile__fields {
  display: grid;
  grid-template-columns: minmax(120px, auto) 1fr;
  gap: 8px 16px;
  margin: 0;
}

.profile__fields dt {
  color: var(--c-text-muted);
  font-size: 0.9rem;
}

.profile__fields dd {
  margin: 0;
}

.profile__perms {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 320px;
  overflow-y: auto;
}

.profile__perm-title {
  display: block;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--c-text-muted);
  margin-bottom: 6px;
}

.profile__perm-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  list-style: none;
  padding: 0;
  margin: 0;
}

/* Моё обучение / ближайшие сроки (u-grid--2 растягивает карточки по высоте). */
.profile__learning {
  align-items: stretch;
}

.profile__courses {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.profile__course,
.profile__deadline {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 12px;
  border: 1px solid var(--c-border);
  border-radius: var(--c-radius, 12px);
  background: var(--c-surface);
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.profile__course:hover,
.profile__deadline:hover {
  border-color: var(--c-primary, #1976d2);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.profile__course-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.profile__course-title {
  font-weight: 600;
  font-size: 0.95rem;
  line-height: 1.35;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile__course-due {
  font-size: 0.82rem;
}

.profile__course-arrow {
  color: var(--c-text-muted);
  flex-shrink: 0;
}

.profile__deadlines {
  display: flex;
  flex-direction: column;
  gap: 8px;
  list-style: none;
  padding: 0;
  margin: 0;
}

.profile__deadlines li {
  display: flex;
}

.profile__deadline {
  flex: 1;
  justify-content: space-between;
}
</style>