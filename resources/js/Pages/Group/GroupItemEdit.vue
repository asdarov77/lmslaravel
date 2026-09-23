<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto">
      <v-toolbar color="primary">
        <v-toolbar-title>Редактирование группы </v-toolbar-title>
        <!-- {{ groupameEdit }} -->
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="submitForm">
          <v-text-field
            label="наименование группы"
            type="text"
            v-model="group.groupname"
          ></v-text-field>
          <v-text-field
            label="описание группы"
            type="text"
            v-model="group.groupdescription"
          ></v-text-field>
          <v-btn
            tile
            color="green"
            class="my-3"
            @click="this.$router.push({ name: 'group.learning', params: { idEdit: group.id, } })"
        >
          на курсы
        </v-btn
        >
          <v-container class="notification is-danger" v-if="errors.length">
            <!--class="has-text-centered"> -->
            <p v-for="error in errors" v-bind:key="error">
              {{ error }}
            </p>
          </v-container>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <!-- <v-btn v-on:click="submitForm" color="primary">Сохранить</v-btn> -->
        <ButtonGroup @submitForm="submitForm" @cancelBtn="cancelBtnHead"></ButtonGroup>
      </v-card-actions>
    </v-card>
  </v-flex>
</template>

<script>
import { mapState, mapGetters } from "vuex";
import ButtonGroup from "../../components/ButtonGroup.vue";
export default {
  components: { ButtonGroup },
  //props: ["idEdit"],
  props: {
    idEdit: {
      type: Number,
      required: true,
    },
  },
  data() {
    return {
      //groupname: this.groupnameEdit,
      //groupdescription: this.groupdescriptionEdit,
      //group: [], // группа с указанным ID из базы данных
      errors: [],
    };
  },
  computed: {
    ...mapState("User", ["allGroups", "users", "group"]),
    ...mapGetters("User", ["users", "groups"]),
  },

  created() {
    //if(this.idEdit){
    //console.log(this.idEdit, 'id')
    this.$store.dispatch("User/fetchGroup", this.idEdit);
    //}
  },

  methods: {
    submitForm: function () {
      if (!this.errors.length) {
        const formData = {
          groupname: this.group.groupname,
          groupdescription: this.group.groupdescription,
        };

        //????? this.$store.dispatch("User/fetchGroup", this.idEdit);
        this.$store
          .dispatch("User/updateGroup", { id: this.idEdit, data: formData })
          .then(() => {})
          .catch((error) => {
            console.error(error);
          })
          .finally(() => this.$router.back());
      }

      // if(this.idEdit){
      // //axios
      // $api
      //   .put("/api/groups/" + this.idEdit, formData)
      //   .then((response) => {
      //     console.log(response);
      //      this.$router.push("/groups/list");
      //   })
      // }
      // else
      // $api
      // //axios
      // .post("/api/groups/", formData)
      //   .then((response) => {
      //     console.log(response);
      //      this.$router.push("/groups/list");
      //   })
      //   .catch((error) => {
      //     if (error.response) {
      //       for (const property in error.response.data) {
      //         this.errors.push(
      //           `${property}: ${error.response.data[property]}`
      //         );
      //       }

      //       console.log(JSON.stringify(error.response.data));
      //     } else if (error.message) {
      //       this.errors.push("Something went wrong. Please try again");
      //       console.log(JSON.stringify(error));
      //     }
      //   });
    },

    cancelBtnHead()
    {
      //console.log('cancel');
      this.$router.go(-1);
    },

  },
  //},
};
</script>

<style></style>
