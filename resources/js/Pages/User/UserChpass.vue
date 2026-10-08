<template>
  <v-col cols="12" sm="8" md="4">
    <v-card class="elevation-12 mx-auto" style="width: 600px">
      <v-toolbar color="primary">
        <v-toolbar-title>Сменить пароль {{ usernameEdit }}</v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="submitForm">
          <v-text-field
            id="password"
            prepend-icon="lock"
            name="password"
            label="Пароль"
            v-model="password"
            type="password"
          ></v-text-field>
          <v-text-field
            id="password_confirmation"
            prepend-icon="lock"
            name="password_confirmation"
            label="Повторите пароль"
            v-model="password_confirmation"
            type="password"
          ></v-text-field>
          <v-alert v-if="errors.length" type="error" density="compact" class="mb-4">
            <div v-for="error in errors" :key="error">{{ error }}</div>
          </v-alert>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn v-on:click="submitForm" color="primary">{{ $t("common.save") }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-col>
</template>

<script>
import { mapState, mapGetters } from "vuex";
import { toast } from "../../composables/useToast";
export default {
  props: ["idEdit"],
  data() {
    return {
      password: "", // пользователь с указанным ID из базы данных
      password_confirmation: "",
      errors: [],

    };
  },
  mounted() {
  },
  computed: {
    ...mapState("User", ["user"]),
    //...mapGetters('User', ['users','groups']),
  },
  methods: {
    submitForm: function () {
      if (this.password !== this.password_confirmation) {
        this.errors.push("пароли не совпадают");
        toast.error(this.$t("user.chpass.mismatch"));
        return;
      }

      if (!this.errors.length) {
        const formData = {
          password: this.password,
        };

        this.$store
          .dispatch("User/chpassUser", { id: this.idEdit, data: formData })
          .then(() => {
            toast.success(this.$t("user.chpass.success"));
          })
          .catch((error) => {
            console.error(error);
            toast.error(toast.fromError(error, this.$t("user.chpass.error")));
          })
          /*
           * Возврат на страницу сделан с задержкой: раньше она нужна
           * была, чтобы тост успел отрисоваться на этой странице. Тост
           * живёт в общем стеке и переживает навигацию, поэтому ждать
           * ничего не нужно — иначе пользователь секунду смотрит на
           * пустую страницу.
           */
          .finally(() => {
            this.$router.back();
          });
      }
    },
  },
};
</script>
