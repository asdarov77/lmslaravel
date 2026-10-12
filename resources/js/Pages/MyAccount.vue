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
    </div>
  </div>
</template>

<script>
import { mapGetters, mapState } from "vuex";
import $api from "../api/httpClient";
import { unwrapField } from "../api/envelope";
import PageHeader from "../components/ui/PageHeader.vue";

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
  components: { PageHeader },

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
    uploadingAvatar: false,
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

    /** Убирает аватар: возвращаем инициалы. */
    removeAvatar() {
      this.$store.commit("Auth/SET_USER_AVATAR", null);
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
</style>