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
        <!--
            Роль только для чтения. Поле было v-combobox, то есть
            свободный текст, и PATCH /api/user/{id} писал его прямо в
            users.role — а это один из двух источников роли наряду с
            role_user. В итоге любой, у кого есть users.update, мог
            вписать «Администратор» и стать суперадмином, минуя chroll
            (который запрещает менять собственные роли).
            Теперь роль назначается только через «Назначение ролей»
            (users.permissions), а здесь она показана для чтения.
        -->
        <v-text-field
            variant="solo"
            :model-value="roleLabel"
            dense
            readonly
            :label="$t('users.role')"
            :hint="$t('users.roleHintReadonly')"
            persistent-hint
        ></v-text-field>
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
        <!--
          v-autocomplete, а не v-select: список групп виртуализируется,
          а сам список разрастается (десятки групп). У v-select без
          filter поле ввода не фильтрует, и нужную группу приходилось
          искать долгой прокруткой. v-autocomplete ищет по item-title
          из коробки.
        -->
        <v-autocomplete
            variant="outlined"
            density="comfortable"
            label="Группа"
            :items="allGroups"
            v-model="user.group_id"
            item-value="id"
            item-title="groupname"
            :no-data-text="$t('common.noGroupsFound')"
            clearable
        ></v-autocomplete>
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
      <!-- Права вынесены в отдельный раздел /permissions: там свои
           правила доступа для администратора и инструктора. -->
      <div>
        <v-btn
          class="my-3 ml-3"
          color="success"
          variant="tonal"
          :disabled="!canSeePermissions"
          :to="{ name: 'permissions.manage', query: { user: user.id } }"
        >
          {{ $t("users.list.permissions") }}
        </v-btn>
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
import ButtonGroup from "../../components/ButtonGroup.vue";

export default {
  components: {Multiselect, ButtonGroup},
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
    ...mapGetters('Auth', ['hasPermission']),

    /** Раздел прав открыт администратору и инструктору — остальным кнопка не нужна. */
    canSeePermissions() {
      return this.hasPermission(['users.permissions', 'users.view'])
    },

    /**
     * Роль для показа. Берём связь role_user (её назначает chroll), а
     * если её нет — строку users.role, в которой значения исторически
     * записаны по-разному: «Обучаемый» или «trainee». Показываем как
     * есть: поле только для чтения, и подменять текст здесь нечем.
     */
    roleLabel() {
      const roles = this.user?.roles
      if (Array.isArray(roles) && roles.length > 0) {
        return roles
          .map((role) => (typeof role === 'object' && role !== null ? role.rolename || role.slug : role))
          .filter(Boolean)
          .join(', ')
      }
      return this.user?.role || '—'
    },
  },

  created() {
    this.$store.dispatch('User/fetchUser', this.idEdit)
    // Группы в выпадающем «Группа» не появлялись при прямом заходе на
    // страницу редактирования: fetchGroups звал только ряд страниц
    // (UserList/GroupList/MyAccount/Register), а здесь его не было вовсе,
    // поэтому allGroups оставался пустым.
    this.$store.dispatch('User/fetchGroups').catch(error => {
      console.error(error)
      this.errors.push('Не удалось загрузить список групп')
    })
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

    // Приводит group_id к number|null. Защищает от отправки в API
    // объекта/массива/пустой строки, что приводит к 500 на стороне БД.
    normalizeGroupId(value) {
      if (value === null || value === undefined || value === '') return null
      const raw = typeof value === 'object' ? value.id : value
      if (raw === null || raw === undefined || raw === '') return null
      const id = Number(raw)
      return Number.isInteger(id) && id > 0 ? id : null
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


      // v-select отдаёт id числом, но allGroups может содержать и строки,
      // а при очистке приходит null/"". Приводим к number|null,
      // иначе в bigint уходит объект/строка и прилетает 500.
      const data = {
        ...this.user,
        group_id: this.normalizeGroupId(this.user.group_id),
      }

      this.$store
          //        .dispatch('User/updateUser', {id:this.idEdit,data:this.users})
          .dispatch('User/updateUser', {id: this.idEdit, data: data})
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

