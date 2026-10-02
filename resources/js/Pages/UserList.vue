<template>
  <div class="u-page">
    <PageHeader
      :title="$t('users.list.title')"
      :subtitle="$t('users.list.subtitle')"
    >
      <template #actions>
        <v-btn color="primary" variant="flat" :to="{ name: 'regist' }">
          <v-icon start icon="mdi-account-plus-outline" size="18" aria-hidden="true"></v-icon>
          {{ $t("users.list.create") }}
        </v-btn>
      </template>
    </PageHeader>

    <DataTable
      :columns="columns"
      :rows="filteredUsers"
      :loading="isLoading"
      :title="$t('users.list.title')"
      :count="filteredUsers.length"
      :caption="$t('users.list.title')"
      :empty-title="allUsers.length ? $t('users.list.emptyFiltered') : $t('users.list.emptyTitle')"
      :empty-text="allUsers.length ? $t('users.list.emptyFilteredText') : $t('users.list.emptyText')"
      :empty-icon="'mdi-account-multiple-outline'"
    >
      <template #filters>
        <v-text-field
          v-model="search"
          :placeholder="$t('users.list.search')"
          :aria-label="$t('users.list.search')"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template #cell-id="{ value }">
        <span class="u-table__num">{{ value }}</span>
      </template>

      <template #cell-fio="{ row }">
        <router-link
          class="users-link"
          :to="{ name: 'user.edit', params: { idEdit: row.id } }"
        >
          {{ row.fio }}
        </router-link>
      </template>

      <template #cell-role="{ value }">
        <!-- Роль бейджем: так она читается с одного взгляда, а не
             размазывается по колонке текстом. -->
        <span class="u-badge" :class="roleBadgeClass(value)">{{ value || '—' }}</span>
      </template>

      <template #cell-group="{ value }">
        <span v-if="!value" class="u-muted">—</span>
        <span v-else class="u-truncate" style="display: block; max-width: 24ch" :title="value">
          {{ value }}
        </span>
      </template>

      <template #cell-actions="{ row }">
        <div class="users-actions">
          <v-btn
            size="small"
            variant="text"
            color="primary"
            :to="{ name: 'user.edit', params: { idEdit: row.id } }"
            :title="$t('users.list.edit')"
            :aria-label="`${$t('users.list.edit')}: ${row.fio}`"
          >
            <v-icon icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <!-- Смена пароля доступна не всем: раньше колонка просто
               пропадала, и пользователь не понимал, куда нажать. -->
          <v-btn
            v-if="canEdit"
            size="small"
            variant="text"
            :to="{ name: 'user.chpass', params: { idEdit: row.id } }"
            :title="$t('users.list.password')"
            :aria-label="`${$t('users.list.password')}: ${row.fio}`"
          >
            <v-icon icon="mdi-lock-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            color="primary"
            :disabled="!canSeePermissions"
            :to="{ name: 'permissions.manage', query: { user: row.id } }"
            :title="$t('users.list.permissions')"
            :aria-label="`${$t('users.list.permissions')}: ${row.fio}`"
          >
            <v-icon icon="mdi-shield-key-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            color="error"
            :aria-label="`${$t('users.list.delete')}: ${row.fio}`"
            :title="$t('users.list.delete')"
            @click="askDelete(row)"
          >
            <v-icon icon="mdi-trash-can-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>
        </div>
      </template>
    </DataTable>

    <ConfirmDialog
      v-model="deleteDialog"
      :title="$t('users.delete.title')"
      :confirm-text="$t('users.delete.confirm')"
      :cancel-text="$t('common.cancel')"
      :busy="deleting"
      @cancel="deleteDialog = false"
      @confirm="confirmDelete"
    >
      {{ $t('users.delete.text', { name: pendingUser?.fio ?? '' }) }}
    </ConfirmDialog>

    <AppToast v-model="toast.open" :type="toast.type" :text="toast.text" />
  </div>
</template>

<script>
import { mapState, mapGetters } from "vuex";
import PageHeader from "../components/ui/PageHeader.vue";
import DataTable from "../components/ui/DataTable.vue";
import ConfirmDialog from "../components/ui/ConfirmDialog.vue";
import AppToast from "../components/ui/AppToast.vue";

export default {
  name: "UserList",
  components: { PageHeader, DataTable, ConfirmDialog, AppToast },

  data() {
    return {
      search: "",
      isLoading: true,
      deleting: false,
      deleteDialog: false,
      pendingId: null,
      toast: { open: false, text: "", type: "success" },
    };
  },

  async created() {
    await this.$store.dispatch("User/fetchUsers").catch(() => {});
    if (!this.allGroups.length) {
      await this.$store.dispatch("User/fetchGroups").catch(() => {});
    }
    this.isLoading = false;
  },

  computed: {
    ...mapGetters("User", ["users"]),
    ...mapState("User", ["allGroups"]),
    ...mapGetters("Auth", ["hasPermission"]),

    allUsers() {
      return this.users ?? [];
    },

    filteredUsers() {
      const query = (this.search ?? "").trim().toLowerCase();
      if (!query) return this.allUsers;

      return this.allUsers.filter((user) =>
        String(user.fio ?? "").toLowerCase().includes(query)
      );
    },

    columns() {
      return [
        { key: "id", title: "ID", width: "64px" },
        { key: "fio", title: this.$t("users.list.name") },
        { key: "role", title: this.$t("users.list.role"), width: "160px" },
        { key: "group", title: this.$t("users.list.group"), width: "200px" },
        { key: "actions", title: "", align: "right", width: "180px" },
      ];
    },

    /**
     * Раньше проверка шла по legacy-slug «manage-users». Он покрыт
     * алиасом users.view в каталоге прав, но полагаться на алиас в
     * UI не нужно — там канонический slug.
     */
    canEdit() {
      return this.hasPermission(["users.update", "manage-users"]);
    },

    /** Раздел прав открыт администратору и инструктору. */
    canSeePermissions() {
      return this.hasPermission(["users.permissions", "users.view"]);
    },

    pendingUser() {
      return this.allUsers.find((user) => user.id === this.pendingId) ?? null;
    },
  },

  methods: {
    roleBadgeClass(role) {
      if (role === "Администратор" || role === "admin") return "u-badge--danger";
      if (role === "Инструктор" || role === "instructor") return "u-badge--primary";
      return "";
    },

    askDelete(user) {
      this.pendingId = user.id;
      this.deleteDialog = true;
    },

    async confirmDelete() {
      if (!this.pendingId) return;

      this.deleting = true;

      try {
        await this.$store.dispatch("User/deleteUser", this.pendingId);
        await this.$store.dispatch("User/fetchUsers");
        this.notify(this.$t("users.delete.done"), "success");
      } catch (error) {
        // 500 отдавался, когда удаляли суперпользователя.
        this.notify(
          error?.response?.status === 500
            ? this.$t("users.delete.cannotDeleteSuper")
            : this.$t("users.delete.error"),
          "error"
        );
      } finally {
        this.deleting = false;
        this.deleteDialog = false;
        this.pendingId = null;
      }
    },

    notify(text, type) {
      this.toast = { open: true, text, type };
    },
  },
};
</script>

<style scoped>
.users-link {
  color: var(--c-primary);
  font-weight: 500;
  text-decoration: none;
}

.users-link:hover {
  text-decoration: underline;
}

.users-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--sp-1);
}
</style>
