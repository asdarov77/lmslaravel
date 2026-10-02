<template>
  <div>
    <v-alert
      v-if="loadError"
      type="error"
      variant="tonal"
      class="mb-4"
      :text="loadError"
    ></v-alert>

    <v-row>
      <!-- ------------------------------------------------------ список пользователей -->
      <v-col cols="12" md="4">
        <v-card elevation="2">
          <v-card-title class="d-flex align-center">
            <span>{{ $t("permissions.usersTitle") }}</span>
            <v-spacer></v-spacer>
            <v-btn
              icon="mdi-refresh"
              size="small"
              variant="text"
              :loading="loading"
              :title="$t('permissions.reload')"
              @click="reload"
            ></v-btn>
          </v-card-title>

          <v-card-text>
            <v-text-field
              v-model="search"
              density="compact"
              variant="outlined"
              clearable
              single-line
              :label="$t('permissions.searchUser')"
              :prepend-inner-icon="'mdi-magnify'"
            ></v-text-field>

            <v-list v-if="filteredUsers.length" density="compact" class="py-0">
              <v-list-item
                v-for="candidate in filteredUsers"
                :key="candidate.id"
                :active="candidate.id === selectedId"
                data-test="perm-user"
                @click="selectUser(candidate.id)"
              >
                <v-list-item-title>{{ candidate.fio }}</v-list-item-title>
                <v-list-item-subtitle>
                  <v-chip size="x-small" class="mr-1">{{ candidate.role }}</v-chip>
                  <span class="text-caption">{{ candidate.permissions.length }} {{ $t("permissions.permsShort") }}</span>
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>

            <p v-else class="text-medium-emphasis mb-0">
              {{ $t("permissions.noUsers") }}
            </p>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- ------------------------------------------------------ права выбранного -->
      <v-col cols="12" md="8">
        <v-card elevation="2">
          <v-card-title>
            <span v-if="selectedUser">
              {{ $t("permissions.title") }} — {{ selectedUser.fio }}
            </span>
            <span v-else>{{ $t("permissions.title") }}</span>
          </v-card-title>

          <v-card-subtitle v-if="selectedUser">
            <v-chip size="small" class="mr-2">{{ selectedUser.role }}</v-chip>
            <span v-if="!isAdmin">{{ $t("permissions.instructorHint") }}</span>
          </v-card-subtitle>

          <v-card-text>
            <p v-if="!selectedUser" class="text-medium-emphasis">
              {{ $t("permissions.selectUser") }}
            </p>

            <template v-else>
              <v-alert
                v-if="denied.length"
                type="warning"
                variant="tonal"
                density="compact"
                class="mb-4"
                :text="$t('permissions.deniedHint', { list: denied.join(', ') })"
              ></v-alert>

              <v-alert
                v-if="!isAdmin"
                type="info"
                variant="tonal"
                density="compact"
                class="mb-4"
                :text="$t('permissions.restrictedHint')"
              ></v-alert>

              <!-- Права, которые актор выдать не может: показываем
                   заблокированными, чтобы было видно границу полномочий. -->
              <v-expansion-panels v-model="openGroups" multiple>
                <v-expansion-panel
                  v-for="group in visibleGroups"
                  :key="group.name"
                  :value="group.name"
                  data-test="perm-group"
                >
                  <v-expansion-panel-title>
                    <div class="d-flex align-center">
                      <span>{{ group.name }}</span>
                      <v-spacer></v-spacer>
                      <v-chip size="x-small" class="mr-2">{{ group.permissions.length }}</v-chip>
                      <v-btn
                        size="x-small"
                        variant="text"
                        :disabled="!assignableIn(group).length"
                        @click.stop="toggleGroup(group)"
                      >
                        {{ allIn(group) ? $t("permissions.clearGroup") : $t("permissions.selectGroup") }}
                      </v-btn>
                    </div>
                  </v-expansion-panel-title>

                  <v-expansion-panel-text>
                    <v-row dense>
                      <v-col
                        v-for="permission in group.permissions"
                        :key="permission.slug"
                        cols="12"
                        sm="6"
                      >
                        <v-checkbox
                          :model-value="selected.includes(permission.id)"
                          :disabled="!permission.assignable"
                          :label="permission.name"
                          :hint="permission.slug"
                          persistent-hint
                          density="compact"
                          color="primary"
                          data-test="perm-checkbox"
                          @update:model-value="togglePermission(permission, $event)"
                        ></v-checkbox>
                      </v-col>
                    </v-row>
                  </v-expansion-panel-text>
                </v-expansion-panel>
              </v-expansion-panels>
            </template>
          </v-card-text>

          <v-card-actions v-if="selectedUser">
            <v-spacer></v-spacer>
            <v-btn variant="text" @click="resetDraft">{{ $t("permissions.reset") }}</v-btn>
            <v-btn color="primary" variant="flat" :loading="saving" @click="save">
              {{ $t("permissions.save") }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <AppToast v-model="alert" :type="alertType" :text="snackbarText"></AppToast>
  </div>
</template>

<script>
import AppToast from "../../components/ui/AppToast.vue";
import { mapState, mapGetters } from "vuex";

/**
 * Отдельная страница управления правами.
 *
 * Раньше права показывались колонкой в списке пользователей, а редактор
 * открывался диалогом в карточке пользователя. Управление правами —
 * самостоятельная задача со своими правилами, поэтому вынесено сюда.
 *
 * Интерфейс разный для администратора и инструктора — но решение о том,
 * что именно можно, принимает бэкенд: у каждого права приходит признак
 * assignable, а список пользователей уже отфильтрован по области
 * полномочий актора. Здесь это только отображение.
 */
export default {
  name: "PermissionsManager",
  components: { AppToast },

  data() {
    return {
      search: "",
      // Какие разделы прав раскрыты. Без v-model панели оставались
      // закрытыми (содержимое ленивое и не появлялось вовсе).
      openGroups: [],
      selectedId: null,
      draft: [],
      saving: false,
      loading: false,
      loadError: "",
      alert: false,
      alertType: "",
      snackbarText: "",
    };
  },

  computed: {
    ...mapState("User", ["permissionGroups", "manageableUsers"]),
    ...mapGetters("Auth", ["isSuperAdmin"]),

    isAdmin() {
      return this.isSuperAdmin === true;
    },

    /** Права, которые актор в принципе не может выдать. */
    denied() {
      return this.permissionGroups
        .flatMap((group) => group.permissions ?? [])
        .filter((permission) => permission.assignable === false)
        .map((permission) => permission.slug);
    },

    filteredUsers() {
      const query = (this.search ?? "").trim().toLowerCase();

      if (!query) return this.manageableUsers;

      return this.manageableUsers.filter((user) =>
        String(user.fio ?? "").toLowerCase().includes(query)
      );
    },

    selectedUser() {
      return this.manageableUsers.find((user) => user.id === this.selectedId) ?? null;
    },

    /**
     * id прав, отмеченных в форме (не сохранённых ещё).
     *
     * Из сохранения исключаются права вне полномочий актора. Иначе
     * инструктор, у которого у подопечного уже есть users.create,
     * получил бы 403 при попытке изменить любое другое право: сервер
     * отвергает запрос, если в нём есть хоть одно недоступное право.
     * Такие права показываются заблокированными — но остаются как есть.
     */
    selected() {
      const assignable = this.assignableIds;
      return this.draft.filter((id) => assignable.has(id));
    },

    /** id прав, которые актор вправе выдать (у администратора — все). */
    assignableIds() {
      return new Set(
        this.permissionGroups
          .flatMap((group) => group.permissions ?? [])
          .filter((permission) => permission.assignable !== false)
          .map((permission) => permission.id)
      );
    },

    /**
     * Администратору видны все разделы, инструктору — только те, где
     * у него есть хоть одно выдаваемое право. Иначе инструктор увидел бы
     * пустые разделы вроде «Система», в которых всё заблокировано.
     */
    visibleGroups() {
      if (this.isAdmin) return this.permissionGroups;

      return this.permissionGroups.filter((group) =>
        (group.permissions ?? []).some((permission) => permission.assignable)
      );
    },
  },

  async mounted() {
    await this.reload();

    // Переход из списка пользователей: /permissions?user=42 сразу
    // открывает нужного человека. Идентификатор проверяем — иначе
    // мусорный query просто игнорируется, а не ломает страницу.
    const requested = Number(this.$route?.query?.user);

    if (Number.isInteger(requested) && requested > 0) {
      this.selectUser(requested);
    }
  },

  methods: {
    async reload() {
      this.loading = true;
      this.loadError = "";

      try {
        await Promise.all([
          this.$store.dispatch("User/fetchPermissionCatalog"),
          this.$store.dispatch("User/fetchManageableUsers"),
        ]);
        // Раскрываем все разделы: их девять, а список короткий —
        // так виден весь набор прав без лишних кликов.
        this.openGroups = this.permissionGroups.map((group) => group.name);
      } catch (error) {
        // Страница открыта только администратору/инструктору, поэтому 403
        // здесь — не «список пуст», а «доступа нет». Сообщаем прямо.
        const status = error?.response?.status;

        this.loadError =
          status === 403
            ? this.$t("permissions.accessDenied")
            : this.$t("permissions.loadError");
      } finally {
        this.loading = false;
      }
    },

    selectUser(id) {
      this.selectedId = id;
      this.resetDraft();
    },

    resetDraft() {
      this.draft = (this.selectedUser?.permissions ?? []).map((permission) => permission.id);
    },

    togglePermission(permission, value) {
      // Заблокированное право нельзя включить даже кликом по строке.
      if (permission.assignable === false) return;

      const id = permission.id;

      if (value) {
        if (!this.draft.includes(id)) this.draft.push(id);
      } else {
        this.draft = this.draft.filter((item) => item !== id);
      }
    },

    assignableIn(group) {
      return (group.permissions ?? []).filter(
        (permission) => permission.assignable !== false && permission.id != null
      );
    },

    allIn(group) {
      const ids = this.assignableIn(group).map((permission) => permission.id);
      return ids.length > 0 && ids.every((id) => this.draft.includes(id));
    },

    toggleGroup(group) {
      const ids = this.assignableIn(group).map((permission) => permission.id);

      this.draft = this.allIn(group)
        ? this.draft.filter((id) => !ids.includes(id))
        : [...new Set([...this.draft, ...ids])];
    },

    async save() {
      if (!this.selectedUser) return;

      this.saving = true;

      try {
        await this.$store.dispatch("User/updateUserPermissions", {
          id: this.selectedUser.id,
          // Именно selected, а не draft: draft включает права вне
          // полномочий актора (показываются заблокированными), а сервер
          // отвергает запрос, если в нём есть хоть одно такое право.
          permissionIds: this.selected,
        });
        // Перечитываем список: сервер вернёт фактический набор, а
        // локальный драфт мог разойтись с ним (например, если сервер
        // отклонил часть прав).
        await this.$store.dispatch("User/fetchManageableUsers");
        this.resetDraft();
        this.snackbarText = this.$t("permissions.saved");
        this.alertType = "success";
      } catch (error) {
        const status = error?.response?.status;
        this.snackbarText =
          status === 403
            ? this.$t("permissions.forbidden")
            : this.$t("permissions.saveError");
        this.alertType = "error";
      } finally {
        this.saving = false;
        this.alert = true;
      }
    },
  },
};
</script>