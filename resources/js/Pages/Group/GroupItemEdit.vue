<template>
  <div class="u-page">
    <FormCard
      :title="$t('groups.edit.title')"
      :subtitle="group.groupname ? $t('groups.edit.subtitle', { name: group.groupname }) : ''"
      :busy="saving"
      :loading="loading"
      @submit="submitForm"
      @cancel="cancelBtnHead"
    >
      <v-text-field
        v-model="group.groupname"
        :label="$t('groups.create.name')"
        :error-messages="fieldErrors.groupname"
        autofocus
      ></v-text-field>

      <v-textarea
        v-model="group.groupdescription"
        :label="$t('groups.create.description')"
        :error-messages="fieldErrors.groupdescription"
        rows="3"
        auto-grow
      ></v-textarea>

      <!-- Второе действие формы, а не кнопка цвета «green» внутри
           карточки: раньше зелёная кнопка рядом с синей кнопкой
           сохранения читалась как отдельное главное действие. -->
      <div class="d-flex mt-2">
        <v-btn
          variant="tonal"
          color="primary"
          prepend-icon="mdi-school-outline"
          :disabled="saving || !group.id"
          :to="{ name: 'group.learning', params: { idEdit: group.id } }"
        >
          {{ $t("groups.edit.enroll") }}
        </v-btn>
      </div>

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
  name: "GroupItemEdit",
  components: { FormCard, AppToast },

  props: {
    idEdit: { type: Number, required: true },
  },

  data() {
    return {
      errors: [],
      fieldErrors: {},
      saving: false,
      loading: false,
      toast: { open: false, text: "", type: "success" },
    };
  },

  computed: {
    ...mapState("User", ["allGroups", "users", "group"]),
    ...mapGetters("User", ["users", "groups"]),
  },

  async created() {
    this.loading = true;
    await this.$store.dispatch("User/fetchGroup", this.idEdit).catch(() => {});
    this.loading = false;
  },

  methods: {
    validate() {
      this.errors = [];
      this.fieldErrors = {};

      if (String(this.group.groupname ?? "").trim() === "") {
        this.fieldErrors.groupname = this.$t("groups.create.errors.nameRequired");
      } else {
        const exists = asArray(this.allGroups).some(
          (item) =>
            item.id !== this.idEdit &&
            String(item.groupname ?? "").trim().toLowerCase() ===
              this.group.groupname.trim().toLowerCase()
        );

        if (exists) {
          this.fieldErrors.groupname = this.$t("groups.create.errors.nameTaken");
        }
      }

      return Object.keys(this.fieldErrors).length === 0;
    },

    async submitForm() {
      if (this.saving) return;

      this.errors = [];

      if (!this.validate()) return;

      this.saving = true;

      try {
        await this.$store.dispatch("User/updateGroup", {
          id: this.idEdit,
          data: {
            groupname: this.group.groupname.trim(),
            groupdescription: this.group.groupdescription.trim(),
          },
        });

        this.toast = { open: true, text: this.$t("groups.edit.done"), type: "success" };
        this.$router.push("/groups/list");
      } catch (error) {
        const { fields, general } = extractFieldErrors(error, this.$t("groups.edit.errors.generic"));
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
