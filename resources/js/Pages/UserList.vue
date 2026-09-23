<template>
  <!-- <permission-wrapper
    content-type="Manage users"
    :operations="['manage-users']"
     <v-btn class="mb-3" color="success" :to="{ name: 'regist' }"
      >Добавить пользователя</v-btn
    >
  >
</permission-wrapper
  > -->
  <v-btn class="mb-3" color="success" :to="{ name: 'regist' }"
      >Добавить пользователя</v-btn
    >
  <v-table dense class="elevation-1 cursor-pointer">
    <thead>
      <tr>
        <th class="text-left">No</th>
        <th class="text-left">Пользователь</th>
        <!-- <th class="text-left">Курсы</th> -->
        <th class="text-left">Разрешения</th>
        <th class="text-left">Группа</th>
        <th class="text-left">Роль</th>
        <th class="text-left">Редактировать</th>
        <!-- <th class="text-left" v-if="user.role==='Администратор'">Сменить пароль</th> -->
        <th class="text-left" v-if="hasEditPermission">Сменить пароль</th>
        <th class="text-left">Удалить</th>
      </tr>
    </thead>

    <tbody>
      <tr v-for="_user in users" :key="_user.id">
        <td class="py-1">{{ _user.id }}</td>
        <td class="py-1">
          <router-link
            :to="{
              name: 'user.edit',
              params: {
                idEdit: _user.id,
              },
            }"
          >
            {{ _user.fio }}
          </router-link>
        </td>
        <!-- <td class="py-1">          
          <ul>
            <li v-for="item in _user.categories" :key="item.id">
              {{ item.title }}
            </li>
          </ul>
        </td> -->
        <td class="py-1">
          <!-- {{ _user.permissions }} -->
          <ul>
            <li v-for="perm in _user.permissions" :key="perm.name">
              {{ perm.name }}
            </li>
          </ul>
        </td>
        <!-- <td class="py-1">{{ _user.group ? _user.group.groupname : "" }}</td> -->
        <td class="py-1">{{ _user.group }}</td>

        <td class="py-1">
          <ul>
            {{
              _user.role
            }}
          </ul>
        </td>
        <td class="my-1">
          <v-btn
            tile
            color="success"
            class="my-1"
            @click="
              this.$router.push({
                name: 'user.edit',
                params: { idEdit: _user.id },
              })
            "
          >
            <v-icon left> mdi-pencil </v-icon> Редактировать
          </v-btn>
        </td>

        <td v-if="hasEditPermission">
          <v-btn
            tile
            color="yellow"
            class="my-1"
            @click="
              this.$router.push({
                name: 'user.chpass',
                params: { idEdit: _user.id },
              })
            "
          >
            Пароль </v-btn
          >
        </td>

        <td>
          <v-btn tile color="error" @click="deleteUser(_user.id)">
            Удалить</v-btn
          >
        </td>
      </tr>
    </tbody>
  </v-table>

  <popup
    :alert="alert"
    :alertType="alertType"
    :snackbarText="snackbarText"
    :overlay="alert"
    :alertFalse="alertFalse"
  ></popup>
</template>


<script>
// :to="{
//   name: 'user.chpass',
//   params: {
//     idEdit: _user.id,
//   },
// }"
import PermissionWrapper from "./PermissionWrapper.vue";
import popup from "./Popup.vue";
import { mapState, mapGetters } from "vuex";

export default {
  components: { PermissionWrapper, popup },
  data() {
    return {
      isLoading: false,
      alert: false,
      alertType: "",
      overlay: false,
      snackbarText: "",
    };
  },
  async mounted() {
    //console.log(localStorage.getItem('token'))
    this.$store.dispatch("User/fetchPermissions");
    this.$store
      .dispatch("User/fetchUsers")
      .catch((error) => console.error(error))
      .finally(() => (this.isLoading = false));
    if (this.allGroups.length === 0) {
      this.$store.dispatch("User/fetchGroups");
    }
  },
  computed: {
    ...mapState("User", ["totalUsers", "allGroups"]),
    //...mapState("Auth", ["accessToken", "user"]),
    ...mapGetters("User", ["users", "groups"]),
    ...mapGetters("Auth", ["hasPermission"]),

    hasEditPermission() {
      return this.hasPermission(["manage-users"], "Manage users");
    },
  },

  methods: {
    alertFalse() {
      this.alert = false;
    },
    async deleteUser(user_id) {
      //console.log(user_id, "user_id");
      if (confirm("Вы хотите удалить пользователя " + user_id + " ?"))
        this.$store
          .dispatch("User/deleteUser", user_id)
          .then(() => {
            this.snackbarText = "пользователь удален";
            this.alertType = "success";
          })
          .catch((error) => {
            //console.error(error)
            //console.log(error.response.status)
            if (error.response.status === 500) {
              this.snackbarText = "нельзя удалить суперпользователя";
              this.alertType = "error";
            }
          })
          .finally(() => {
            this.alert = true;
          });
      this.$store
        .dispatch("User/fetchUsers")
        .catch((error) => console.error(error))
        .finally(() => (this.isLoading = false));
    },
  },
};
</script>