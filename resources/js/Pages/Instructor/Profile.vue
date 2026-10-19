<template>
  <div class="instructor-profile">
    <!--
      Заголовок страницы добавлен: раньше его не было вовсе —
      страница начиналась с карточек. Без имени у страницы нет
      ни оглавления, ни ориентира для человека
      (WCAG 2.2 SC 1.3.1, 2.4.6).
    -->
    <PageHeader :title="$t('instructor.profile')" />

    <v-skeleton-loader v-if="loading" type="card, card, card, card" class="mb-4" />
    <v-skeleton-loader v-if="loading" type="card" class="mb-4" />
    <v-skeleton-loader v-if="loading" type="card" class="mb-4" />

    <template v-else-if="instructor">
      <v-alert v-if="error" type="error" dense text class="mb-4" data-test="instructor-error">
        {{ error }}
      </v-alert>

      <v-row>
        <!-- Левая колонка: профиль -->
        <v-col cols="12" md="4">
          <v-card class="instructor-profile__card">
            <v-img
              v-if="instructor.avatar_url"
              :src="instructor.avatar_url"
              class="instructor-profile__avatar"
              contain
            />
            <v-avatar v-else size="120" class="instructor-profile__avatar-placeholder">
              <v-icon size="48">mdi-account-circle</v-icon>
            </v-avatar>

            <div class="instructor-profile__name">{{ instructor.fio }}</div>

            <div v-if="instructor.bio" class="instructor-profile__bio">
              {{ instructor.bio }}
            </div>

            <div class="instructor-profile__stats">
              <div class="instructor-profile__stat">
                <span class="instructor-profile__stat-value">{{ instructor.courses_count }}</span>
                <span class="instructor-profile__stat-label">{{ $t('instructor.courses') }}</span>
              </div>
              <div class="instructor-profile__stat">
                <span class="instructor-profile__stat-value">{{ instructor.rating.average }}</span>
                <span class="instructor-profile__stat-label">{{ $t('instructor.rating') }}</span>
              </div>
              <div class="instructor-profile__stat">
                <span class="instructor-profile__stat-value">{{ instructor.rating.reviews_count }}</span>
                <span class="instructor-profile__stat-label">{{ $t('instructor.reviews') }}</span>
              </div>
            </div>
          </v-card>
        </v-col>

        <!-- Правая колонка: курсы -->
        <v-col cols="12" md="8">
          <v-card class="instructor-profile__courses">
            <v-card-title class="instructor-profile__section-title">
              {{ $t('instructor.myCourses') }}
            </v-card-title>

            <v-data-table
              :headers="courseHeaders"
              :items="instructor.courses"
              :loading="loadingCourses"
              class="instructor-profile__table"
              hide-default-footer
              :items-per-page="5"
              :footer-props="{ 'items-per-page-options': [5, 10, 20] }"
            >
              <template #[`item.title`]="{ item }">
                <router-link :to="`/courses/desc/${item.path || item.id}`" class="instructor-profile__course-link">
                  {{ item.title }}
                </router-link>
              </template>

              <template #[`item.short_description`]="{ item }">
                <span class="instructor-profile__desc">{{ item.short_description }}</span>
              </template>
            </v-data-table>

            <template v-if="instructor.courses.length === 0">
              <div class="instructor-profile__empty">
                <v-icon size="48">mdi-book-outline</v-icon>
                <p>{{ $t('instructor.noCourses') }}</p>
              </div>
            </template>
          </v-card>
        </v-col>
      </v-row>
    </template>

    <!--
      Данных нет: профиль не найден (404) или id некорректен.
      Раньше шаблон рисовался по <template v-else="loading">, и при
      instructor === null обращения instructor.avatar_url и
      instructor.courses роняли рендер с
      «Cannot read properties of null» — вместо сообщения человек
      получал пустую страницу.
    -->
    <div v-else class="instructor-profile__missing" data-test="instructor-empty">
      <v-icon size="48" class="mb-2">mdi-account-off-outline</v-icon>
      <p class="mb-0">{{ error || $t('instructor.notFound') }}</p>
    </div>
  </div>
</template>

<script>
import $api from '../../api/httpClient';
import { useRoute } from 'vue-router';
import PageHeader from '../../components/ui/PageHeader.vue';

export default {
  name: 'InstructorProfile',

  components: { PageHeader },

  data() {
    return {
      loading: true,
      loadingCourses: false,
      error: null,
      instructor: null,
    };
  },

  computed: {
    courseHeaders() {
      return [
        { title: this.$t('instructor.courseTitle'), key: 'title', sortable: true },
        { title: this.$t('instructor.description'), key: 'short_description', sortable: false },
      ];
    },
  },

  async mounted() {
    await this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      this.error = null;

      try {
        const id = this.$route.params.id;
        const response = await $api.get(`/api/instructors/${id}`);
        this.instructor = response?.data?.data;
      } catch (e) {
        this.error = e?.response?.data?.message || this.$t('instructor.loadFailed');
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.instructor-profile__missing {
  text-align: center;
  padding: 48px 24px;
  color: rgba(0, 0, 0, 0.6);
}

.instructor-profile__card {
  text-align: center;
  padding: 24px;
}

.instructor-profile__avatar {
  width: 140px;
  height: 140px;
  border-radius: 50%;
  object-fit: cover;
  margin: 0 auto 16px;
}

.instructor-profile__avatar-placeholder {
  background: var(--v-theme-surface-variant);
  color: var(--v-theme-on-surface-variant);
}

.instructor-profile__name {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 8px;
}

.instructor-profile__bio {
  color: var(--v-theme-on-surface-variant);
  font-size: 0.9375rem;
  margin-bottom: 24px;
  line-height: 1.5;
}

.instructor-profile__stats {
  display: flex;
  justify-content: space-around;
  padding-top: 16px;
  border-top: 1px solid var(--v-theme-surface-variant);
}

.instructor-profile__stat {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.instructor-profile__stat-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--v-theme-primary);
}

.instructor-profile__stat-label {
  font-size: 0.75rem;
  color: var(--v-theme-on-surface-variant);
}

.instructor-profile__courses {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.instructor-profile__section-title {
  font-size: 1.125rem;
  font-weight: 600;
}

.instructor-profile__table {
  flex: 1;
}

.instructor-profile__course-link {
  color: var(--v-theme-primary);
  text-decoration: none;
  font-weight: 500;
}

.instructor-profile__course-link:hover {
  text-decoration: underline;
}

.instructor-profile__desc {
  color: var(--v-theme-on-surface-variant);
  font-size: 0.875rem;
  max-width: 300px;
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.instructor-profile__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  color: var(--v-theme-on-surface-variant);
  text-align: center;
}

.instructor-profile__empty p {
  margin-top: 12px;
  font-size: 0.9375rem;
}
</style>