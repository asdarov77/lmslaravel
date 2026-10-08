<template>
  <div class="u-page">
    <FormCard
      :title="$t('categories.edit.title')"
      :subtitle="category ? $t('categories.edit.subtitle', { name: category.title }) : ''"
      :busy="saving"
      :show-cancel="true"
      :submit-text="$t('common.save')"
      @submit="submitForm"
      @cancel="cancelBtnHead"
    >
      <!--
        Пока категория не приехала из стора, category === null, и
        обращаться к category.title нельзя: v-model падал с
        "Cannot read properties of null" ещё до загрузки данных.
        Форма рендерится только когда сущность готова к правке.
      -->
      <template v-if="category">
        <v-text-field
          v-model="category.title"
          :label="$t('categories.create.name')"
          :error-messages="fieldErrors.title"
          autofocus
        ></v-text-field>

        <v-textarea
          v-model="category.description"
          :label="$t('categories.create.description')"
          :error-messages="fieldErrors.description"
          rows="3"
          auto-grow
        ></v-textarea>
      </template>

      <div v-else class="d-flex justify-center py-6">
        <v-progress-circular indeterminate aria-label="Загрузка"></v-progress-circular>
      </div>

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
  name: "UpdateCategory",
  components: { FormCard },

  props: {
    idEdit: { type: Number, required: true },
  },

  data() {
    return {
      errors: [],
      fieldErrors: {},
      saving: false,
    };
  },

  async mounted() {
    await this.$store.dispatch("Course/fetchCategory", this.idEdit).catch(() => {});
  },

  computed: {
    ...mapState("Course", ["courses", "category", "totalCategories"]),
    ...mapGetters("Course", ["categories", "courses"]),
  },

  methods: {
    validate() {
      this.errors = [];
      this.fieldErrors = {};

      // Без этой проверки кнопка «Сохранить» отправляла бы null вместо
      // данных категории.
      if (!this.category) return false;

      const title = String(this.category.title ?? "").trim();

      if (title === "") {
        this.fieldErrors.title = this.$t("categories.create.errors.nameRequired");
      } else {
        const exists = asArray(this.categories).some(
          (item) =>
            item.id !== this.idEdit &&
            String(item.title ?? "").trim().toLowerCase() === title.toLowerCase()
        );

        if (exists) {
          this.fieldErrors.title = this.$t("categories.create.errors.nameTaken");
        }
      }

      return Object.keys(this.fieldErrors).length === 0;
    },

    async submitForm() {
      if (this.saving) return;

      this.errors = [];

      if (!this.validate()) return;

      this.saving = true;

      // Отправляем только редактируемые поля. Раньше уходил весь объект
      // state.category вместе с устаревшим алиасом name и служебными
      // полями (id/created_at/updated_at), из-за чего правка названия
      // терялась — PUT отвечал 200, не меняя ничего.
      try {
        await this.$store.dispatch("Course/updateCategory", {
          id: this.idEdit,
          data: {
            title: String(this.category.title).trim(),
            description: String(this.category.description ?? "").trim(),
          },
        });

        await this.$store.dispatch("Course/fetchCategories");
        toast.success(this.$t("categories.edit.done"));
        this.$router.push("/categories");
      } catch (error) {
        const { fields, general } = extractFieldErrors(
          error,
          this.$t("categories.edit.errors.generic")
        );
        Object.assign(this.fieldErrors, fields);
        this.errors = general && !Object.keys(fields).length ? [general] : [];
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
