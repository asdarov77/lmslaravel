<template>
  <div class="u-page">
    <PageHeader :title="$t('users.chrole.title')" :subtitle="fio" />

    <FormCard
      :title="$t('users.chrole.card')"
      :busy="saving"
      :loading="!loaded"
      :show-cancel="true"
      :submit-text="$t('common.save')"
      @submit="submitForm"
      @cancel="$router.push('/user/list')"
    >
      <v-select
        v-model="selected"
        :items="roles"
        item-value="id"
        item-title="rolename"
        :label="$t('users.chrole.role')"
        :hint="$t('users.chrole.hint')"
        :error-messages="fieldError"
        persistent-hint
        multiple
        chips
        closable-chips
        data-test="chrole-select"
      />

      <!--
          Собственные роли бэкенд менять не даёт (chroll отвечает 403
          на самого себя — иначе инструктор с users.permissions повысил
          бы себя до администратора). Здесь это показано заранее, вместо
          того чтобы ждать ошибку от сервера.
      -->
      <v-alert v-if="isSelf" type="info" density="compact" class="mt-4" data-test="chrole-self">
        {{ $t("users.chrole.selfForbidden") }}
      </v-alert>

      <AppToast v-model="alert" :type="alertType" :text="snackbarText" />
    </FormCard>
  </div>
</template>

<script>
import PageHeader from "../../components/ui/PageHeader.vue";
import FormCard from "../../components/ui/FormCard.vue";
import AppToast from "../../components/ui/AppToast.vue";
import $api from "../../api/httpClient";
import { asArray, unwrapArray, unwrapResponse } from "../../api/envelope";

/**
 * Назначение ролей пользователя.
 *
 * Страница была нерабочей по трём причинам:
 *  1) маршрут и API (PUT /api/user/chroll/{id}) были закомментированы —
 *     запрос уходил в 404;
 *  2) в заголовке выводилось `usernameEdit`, которого нет в data: имя
 *     всегда оставалось пустым;
 *  3) ошибки выводились классами Bulma (`notification is-danger`), а в
 *     проекте Bulma нет — при ошибке пользователь видел пустую полосу
 *     вместо текста.
 *
 * Доступ — users.permissions, как у управления правами.
 */
export default {
  components: { PageHeader, FormCard, AppToast },
  props: {
    idEdit: { type: Number, required: true },
  },
  data() {
    return {
      user: null,
      currentUserId: null,
      roles: [],
      selected: [],
      loaded: false,
      saving: false,
      fieldError: "",
      alert: false,
      alertType: "error",
      snackbarText: "",
    };
  },
  computed: {
    fio() {
      return this.user?.fio || this.$t("users.chrole.user");
    },
    isSelf() {
      return this.user != null && Number(this.user.id) === Number(this.currentUserId);
    },
  },
  async created() {
    try {
      const [userRes, rolesRes, meRes] = await Promise.all([
        $api.get(`api/user/list/${this.idEdit}`),
        $api.get("api/role"),
        $api.get("api/v1/me"),
      ]);

      this.user = unwrapResponse(userRes);
      this.currentUserId = unwrapResponse(meRes)?.id ?? null;
      this.roles = asArray(unwrapArray(rolesRes));

      // v-select multiple отдаёт массив id; пустое значение приходит
      // как null, поэтому приводим к массиву явно.
      const current = Array.isArray(this.user?.roles) ? this.user.roles : [];
      this.selected = current
        .map((role) => (typeof role === "object" && role !== null ? role.id : role))
        .filter((id) => id !== null && id !== undefined);
    } catch (error) {
      this.showError(error);
    } finally {
      this.loaded = true;
    }
  },
  methods: {
    async submitForm() {
      this.saving = true;
      this.fieldError = "";
      try {
        await $api.put(`api/user/chroll/${this.idEdit}`, {
          role_id: (this.selected || []).map(Number).filter(Boolean),
        });

        this.showMessage(this.$t("users.chrole.saved"), "success");
        this.$router.push("/user/list");
      } catch (error) {
        const data = error?.response?.data;
        this.fieldError = data?.errors?.role_id?.[0] || "";
        this.showError(error);
      } finally {
        this.saving = false;
      }
    },
    showMessage(text, type = "success") {
      this.snackbarText = text;
      this.alertType = type;
      this.alert = true;
    },
    showError(error) {
      const data = error?.response?.data;
      const message =
        data?.errors?.role_id?.[0] || data?.message || data?.error?.message;
      this.showMessage(message || this.$t("users.chrole.failed"), "error");
    },
  },
};
</script>
