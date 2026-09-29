<template>
  <v-card class="elevation-12 mx-auto" style="min-width: 600px">
    <v-card-title
    >Назначить разрешения пользователя {{ user.fio }}
    </v-card-title
    >
    <v-card-text>
      <v-select
          variant="solo"
          dense
          label="Разрешения"
          type="text"
          :items="allPermissions"
          v-model="user.permissions"
          multiple
          item-value="id"
          item-title="name"
      ></v-select>

      <v-container class="notification is-danger" v-if="errors.length">
        <!--class="has-text-centered"> -->
        <p v-for="error in errors" v-bind:key="error">
          {{ error }}
        </p>
      </v-container>{{allPermissions}}
    </v-card-text>
    <ButtonGroup @submitForm="submitForm" @cancelBtn="cancelBtn"></ButtonGroup>
  </v-card>
</template>

<script>
import ButtonGroup from "../../components/ButtonGroup.vue";

import {mapState, mapGetters} from 'vuex'

export default {

  components: {ButtonGroup},
  props: ["idEdit",],
  data() {
    return {
      errors: [],
      value: [],
    };
  },

  created() {
    //console.log('mounted chperm', this.idEdit);
    this.$store
        .dispatch('User/fetchPermissions')
        .catch(error => console.error(error))
  },
  computed: {
    ...mapState('User', ['allPermissions', 'user']),
    // ...mapGetters('User', ['users', 'groups'])
  },
  methods: {
    async submitForm() {
      // Реальный вызов API раньше был закомментирован в UserItemEdit —
      // страница показывала форму, но сохранение не происходило.
      try {
        await this.$store.dispatch('User/updateUserPermissions', {
          id: this.idEdit,
          permissionIds: this.user.permissions || [],
        })
        this.$router.push('/user/list')
      } catch (error) {
        const data = error?.response?.data
        if (data && typeof data === 'object') {
          for (const key in data.errors ?? data) {
            this.errors.push(`${key}: ${data.errors?.[key] ?? data[key]}`)
          }
        } else {
          this.errors.push(error?.message || 'Не удалось сохранить разрешения')
        }
      }
    },
    cancelBtn() {
      this.$router.back()
    }
  },
};

</script>
