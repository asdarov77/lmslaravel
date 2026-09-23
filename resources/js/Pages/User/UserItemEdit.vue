<template>
  <v-card>
    <v-card-title class="first-row"
    >Редактирование пользователя {{ user.fio }}
    </v-card-title
    >
    <v-row class="first-row">
      <v-col>
        <v-text-field
            variant="solo"
            label="ФИО"
            type="text"
            v-model="user.fio"
        ></v-text-field>
      </v-col>
      <v-col>
        <v-combobox
            variant="solo"
            v-model="user.role"
            dense
            label="Роль"
            :items="['Администратор', 'Инструктор', 'Обучаемый']"
        ></v-combobox>
      </v-col>
      <v-col>
        <v-text-field
            variant="solo"
            id="phonenumber"
            name="phonenumber"
            label="Телефон"
            v-model="user.phonenumber"
            type="text"
        ></v-text-field>
      </v-col>
    </v-row>
    <v-row>
      <v-col>
        <Multiselect
            id="city"
            v-model="user.city"
            :selected="value"
            :options="options"
            placeholder="Город"
            :searchable="true"
            trackBy="city"
            label="city"
            noResultsText="нет такого города"
        >
        </Multiselect>
      </v-col>
      <v-col>
        <v-text-field
            variant="solo"
            id="country"
            name="country"
            label="Страна"
            v-model="user.country"
            type="text"
        ></v-text-field>
      </v-col>
      <v-col>
        <v-text-field
            variant="solo"
            id="organization"
            name="organization"
            label="Организация"
            v-model="user.organization"
            type="text"
        ></v-text-field>
      </v-col>
    </v-row>
    <v-row>
      <v-col>
        <v-text-field
            variant="solo"
            id="position"
            name="position"
            label="Должность"
            v-model="user.position"
            type="text"
        ></v-text-field>
      </v-col>
      <v-col>
        <v-select
            attach="false"
            
            variant="solo"
            v-model="user.rank"
            dense
            label="Воинское звание"
            :items="['капитан', 'майор', 'старший лейтенант', 'лейтенант']"            
        ></v-select>
      </v-col>
      <v-col>
        <v-combobox
            variant="solo"
            v-model="user.spfere"
            dense
            label="Сфера деятельности"
            :items="sfereOptions"
        ></v-combobox>
      </v-col>
    </v-row>
    <v-row>
      <v-col>
        <v-text-field
            variant="solo"
            id="specialization"
            name="specialization"
            label="Специализация"
            v-model="user.specialization"
            type="text"
        ></v-text-field>
      </v-col>
      <v-col>
        <v-combobox
            variant="solo"
            label="Группа"
            type="text"
            :items="allGroups"
            v-model="user.group_id"
            item-value="id"
            item-title="groupname"
        ></v-combobox>
      </v-col>
      <v-col></v-col>
    </v-row>
    <div class="d-flex justify-space-between">
      <!-- <div class="d-flex justify-space-around"> -->
      <!-- <v-btn
        tile
        color="green"
        class="my-3"
        :to="{
          name: 'user.chroll',
          params: {
            idEdit: user.id,
          },
        }"
      >
        Роль</v-btn
      > -->
      <div>
        <v-btn
            style="margin-left: 10px"
            tile
            color="green"
            class="my-3"
            @click="dialog=true"
        >
          Разрешение
        </v-btn
        >
      </div>
      <ButtonGroup @submitForm="submitForm" @cancelBtn="cancelBtnHead"></ButtonGroup>
    </div>
    <!-- <v-select
      label="Разрешения"
      type="text"
      :items="permissions"
      v-model="user.permissions"
      multiple
      item-value="id"
      item-title="name"
      empty-option
    ></v-select> -->

    <v-container class="notification is-danger" v-if="errors.length">
      <!--class="has-text-centered"> -->
      <p v-for="error in errors" v-bind:key="error">
        {{ error }}
      </p>
    </v-container>
    <v-dialog v-model="dialog">
      <UserChperm :idEdit="user.id" @submitForm="dialogFalse" @cancelBtn="cancelBtn"></UserChperm>
    </v-dialog>
    <!-- <v-dialog v-model="dialogReg">
      <UserLearning :idEdit="user.id" @submitForm="this.dialogReg = false" @cancelBtn="cancelBtnRegistration"></UserLearning>
    </v-dialog> -->
  </v-card>
</template>

<script>

import $api from "../../api/httpClient";
import {mapState, mapGetters} from 'vuex'
import jsonData from "../User/russia.min.json";
import sferejsonData from "../User/sfere.json";
//import UserLearning from "./UserLearning.vue";
//import jsonData from "../User/city.json";
import Multiselect from "@vueform/multiselect";
import UserChperm from "./UserChperm.vue";
import ButtonGroup from "../../components/ButtonGroup.vue";

export default {
  components: {Multiselect, UserChperm, ButtonGroup},
  props:
      {
        idEdit: {
          type: Number,
          required: true
        },
      },
  data() {
    return {
      dialog: false,
      dialogReg: false,
      errors: [],
      options: jsonData,
      sfereOptions: sferejsonData,
      value: [],
    };
  },
  computed: {
    ...mapState('User', ['allGroups', 'user']),
    //...mapGetters('User', ['users','groups']),
  },

  created() {
    this.$store.dispatch('User/fetchUser', this.idEdit)
  },
  methods: {
    cancelBtnRegistration(){      
      // this.$store
      //     .dispatch('Course/categories')
      //     .catch(error => console.error(error))
      this.$store.dispatch('User/fetchUser', this.idEdit)
      this.dialogReg = false
    },
    cancelBtnHead(){
      this.$router.back()
    },
    dialogFalse(){
      this.dialog = false
    },
    cancelBtn(){      
      this.$store.dispatch('User/fetchUser', this.idEdit)
      this.dialog = false
    },

    submitForm() {
       if (!this.errors.length) {
         const formData = {
           permission_id: this.user.permissions,
         };

        //  let urlToUp = "/api/user/chperm/" + this.idEdit;

        //  $api
        //    .put(urlToUp, formData)
        //    .then((response) => {
        //     console.log(formData);            
        //      //this.$router.push("/user/list");
        //      // this.$router.back();
        //    })
        //    .catch((error) => {
        //      if (error.response) {
        //        for (const property in error.response.data) {
        //          this.errors.push(
        //            `${property}: ${error.response.data[property]}`
        //          );
        //        }

        //        console.log(JSON.stringify(error.response.data));
        //      } else if (error.message) {
        //        this.errors.push("Something went wrong. Please try again");

        //        console.log(JSON.stringify(error));
        //      }
        //    });
       }


      // if (!this.errors.length) {
      //   const formData = {
      //     fio: this.user.fio,
      //     role: this.user.role,
      //     phonenumber: this.user.phonenumber,
      //     city: this.user.city,
      //     country: this.user.country,
      //     organization: this.user.organization,
      //     position: this.user.position,
      //     rank: this.user.rank,
      //     spfere: this.user.spfere,
      //     specialization: this.user.specialization,
      //     group_id: this.user.group_id,
      //     permission_id: this.user.permissions,
      //   };


      this.$store
          //        .dispatch('User/updateUser', {id:this.idEdit,data:this.users})
          .dispatch('User/updateUser', {id: this.idEdit, data: this.user})
          .then(() => {
          })
          .catch(error => {
            console.error(error)
          })
          .finally(() => this.$router.back())
    },

    // },
  },
};
</script>

<style src="@vueform/multiselect/themes/default.css">
</style>
<style>
.v-card {
  padding: 10px;
}

.first-row {
  margin-top: 20px;
}

.multiselect {
  background-color: #f4f4f4;
  height: 56px;
  border: none;
  box-shadow: 0 3px 1px -2px var(--v-shadow-key-umbra-opacity, rgba(0, 0, 0, 0.2)), 0 2px 2px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.14)), 0 1px 5px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.12));
}

.multiselect.is-active {
  box-shadow: 0 3px 1px -2px var(--v-shadow-key-umbra-opacity, rgba(0, 0, 0, 0.2)), 0 2px 2px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.14)), 0 1px 5px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.12));

}

.multiselect-search {
  background-color: #f4f4f4;

}

.multiselect-placeholder {
  font-weight: normal;
  color: black;
}

.v-field {
  background-color: #f4f4f4;
}

.multiselect-option.is-selected, .multiselect-option.is-selected.is-pointed {
  background: rgba(0, 0, 0, 0.16);
}

.multiselect-dropdown ul li span {
  color: black;
}
</style>

