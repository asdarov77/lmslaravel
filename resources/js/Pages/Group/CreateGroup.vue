<template>
  <div class="u-page">
    <FormCard
      :title="$t('groups.create.title')"
      :subtitle="$t('groups.create.subtitle')"
      :busy="saving"
      :submit-text="$t('common.create')"
      @submit="submitForm"
      @cancel="cancelBtnHead"
    >
      <v-text-field
        v-model="groupname"
        :label="$t('groups.create.name')"
        :placeholder="$t('groups.create.namePlaceholder')"
        :error-messages="fieldErrors.groupname"
        autofocus
      ></v-text-field>

      <v-textarea
        v-model="groupdescription"
        :label="$t('groups.create.description')"
        :placeholder="$t('groups.create.descriptionPlaceholder')"
        :error-messages="fieldErrors.groupdescription"
        rows="3"
        auto-grow
      ></v-textarea>

      <!--
        Ошибки сервера показываем явно.

        Раньше здесь стоял <v-container class="notification is-danger">:
        класс из Bulma, которого в приложении нет (Bulma грузится с CDN
        лишь на пяти страницах), поэтому блок ошибок рендерился без
        единого стиля — серверный 422 был виден как пустое место.
      -->
      <v-alert
        v-for="(error, index) in errors"
        :key="index"
        type="error"
        class="mt-3"
        :text="error"
      ></v-alert>
    </FormCard>

  </div>
</template>

<script>
import { mapState, mapGetters } from "vuex";
import { asArray, extractFieldErrors } from "../../api/envelope";
import FormCard from "../../components/ui/FormCard.vue";
import { toast } from "../../composables/useToast";

export default {
  name: "CreateGroup",
  components: { FormCard },

  data() {
    return {
      groupname: "",
      groupdescription: "",
      errors: [],
      fieldErrors: {},
      saving: false,
    };
  },

  computed: {
    ...mapState("User", ["allGroups", "users"]),
    ...mapGetters("User", ["users", "groups"]),
  },

  methods: {
    /**
     * Клиентская валидация.
     *
     * Раньше проверка выглядела как `if (!this.errors.length)`, но
     * массив errors нигде не наполнялся — условие всегда истинно, и
     * пустая группа уходила на сервер. Теперь пустое имя — ошибка
     * до запроса.
     */
    validate() {
      this.errors = [];
      this.fieldErrors = {};

      if (this.groupname.trim() === "") {
        this.fieldErrors.groupname = this.$t("groups.create.errors.nameRequired");
      } else if (this.groupname.trim().length > 255) {
        this.fieldErrors.groupname = this.$t("groups.create.errors.nameTooLong");
      } else {
        const exists = asArray(this.allGroups).some(
          (group) =>
            String(group.groupname ?? "").trim().toLowerCase() ===
            this.groupname.trim().toLowerCase()
        );

        if (exists) {
          this.fieldErrors.groupname = this.$t("groups.create.errors.nameTaken");
        }
      }

      if (this.groupdescription.length > 255) {
        this.fieldErrors.groupdescription = this.$t("groups.create.errors.descriptionTooLong");
      }

      return Object.keys(this.fieldErrors).length === 0;
    },

    async submitForm() {
      if (this.saving) return;

      this.errors = [];

      if (!this.validate()) return;

      this.saving = true;

      try {
        await this.$store.dispatch("User/createGroup", {
          groupname: this.groupname.trim(),
          groupdescription: this.groupdescription.trim(),
        });

        toast.success(this.$t("groups.create.done"));
        this.$router.push("/groups/list");
      } catch (error) {
        // Раньше .finally() уводил назад в любом случае, поэтому
        // при ошибке пользователь оказывался на списке без объяснения.
        const { fields, general } = extractFieldErrors(
          error,
          this.$t("groups.create.errors.generic")
        );
        Object.assign(this.fieldErrors, fields);
        this.errors = general ? [general] : [];
        toast.error(general ?? "");
      } finally {
        this.saving = false;
      }
    },

    cancelBtnHead() {
      this.$router.back();
    },
  },
};
</script>
