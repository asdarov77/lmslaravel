<template>
  <div class="u-page">
    <FormCard
      :title="$t('categories.create.title')"
      :subtitle="$t('categories.create.subtitle')"
      :busy="saving"
      :submit-text="$t('common.create')"
      @submit="submitForm"
      @cancel="cancelBtnHead"
    >
      <v-text-field
        v-model="title"
        :label="$t('categories.create.name')"
        :placeholder="$t('categories.create.namePlaceholder')"
        :error-messages="fieldErrors.title"
        autofocus
      ></v-text-field>

      <v-textarea
        v-model="description"
        :label="$t('categories.create.description')"
        :placeholder="$t('categories.create.descriptionPlaceholder')"
        :error-messages="fieldErrors.description"
        rows="3"
        auto-grow
      ></v-textarea>

      <!-- Класс notification is-danger раньше не давал никакого вида:
           Bulma в приложении не подключена, и ошибка 422 выглядела
           как пустой блок. -->
      <v-alert
        v-for="(error, index) in errors"
        :key="index"
        type="error"
        class="mt-3"
        :text="error"
      ></v-alert>
    </FormCard>

    <AppToast v-model="toast.open" :type="toast.type" :text="toast.text" />
  </div>
</template>

<script>
import { mapState, mapGetters } from "vuex";
import { asArray, extractFieldErrors } from "../../api/envelope";
import FormCard from "../../components/ui/FormCard.vue";
import AppToast from "../../components/ui/AppToast.vue";

export default {
  name: "RegisterCategory",
  components: { FormCard, AppToast },

  data() {
    return {
      title: "",
      description: "",
      errors: [],
      fieldErrors: {},
      saving: false,
      toast: { open: false, text: "", type: "success" },
    };
  },

  computed: {
    ...mapState("Course", ["totalCategories", "categories"]),
    ...mapGetters("Course", ["categories", "courses"]),
  },

  methods: {
    validate() {
      this.errors = [];
      this.fieldErrors = {};

      const name = this.title.trim();

      if (name === "") {
        this.fieldErrors.title = this.$t("categories.create.errors.nameRequired");
      } else if (name.length > 255) {
        this.fieldErrors.title = this.$t("categories.create.errors.nameTooLong");
      } else {
        const exists = asArray(this.categories).some(
          (category) => String(category.title ?? "").trim().toLowerCase() === name.toLowerCase()
        );

        if (exists) {
          this.fieldErrors.title = this.$t("categories.create.errors.nameTaken");
        }
      }

      if (this.description.length > 255) {
        this.fieldErrors.description = this.$t("categories.create.errors.descriptionTooLong");
      }

      return Object.keys(this.fieldErrors).length === 0;
    },

    async submitForm() {
      if (this.saving) return;

      this.errors = [];

      if (!this.validate()) return;

      this.saving = true;

      try {
        await this.$store.dispatch("Course/createCategory", {
          title: this.title.trim(),
          description: this.description.trim(),
        });

        this.toast = { open: true, text: this.$t("categories.create.done"), type: "success" };
        this.$router.push("/categories");
      } catch (error) {
        const { fields, general } = extractFieldErrors(
          error,
          this.$t("categories.create.errors.generic")
        );
        Object.assign(this.fieldErrors, fields);
        this.errors = general ? [general] : [];
        this.toast = { open: true, text: general ?? "", type: "error" };
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
