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
    submitForm() {
      this.$emit('submitForm')
    },
    cancelBtn() {
      this.$emit('cancelBtn')
    }
  },
};

</script>
