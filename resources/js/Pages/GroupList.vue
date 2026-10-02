<template>
  <div class="u-page">
    <PageHeader
      :title="$t('groups.list.title')"
      :subtitle="$t('groups.list.subtitle')"
    >
      <template #actions>
        <v-btn color="primary" variant="flat" :to="{ name: 'groups.create' }">
          <v-icon start icon="mdi-plus" size="18" aria-hidden="true"></v-icon>
          {{ $t("groups.list.create") }}
        </v-btn>
      </template>
    </PageHeader>

    <DataTable
      :columns="columns"
      :rows="filteredGroups"
      :loading="isLoading"
      :title="$t('groups.list.title')"
      :count="filteredGroups.length"
      :caption="$t('groups.list.title')"
      :empty-title="allGroups.length ? $t('groups.list.emptyFiltered') : $t('groups.list.emptyTitle')"
      :empty-text="allGroups.length ? $t('groups.list.emptyFilteredText') : $t('groups.list.emptyText')"
      :empty-icon="'mdi-account-group-outline'"
    >
      <!-- Фильтр по названию: список групп небольшой, но искать в нём
           приходится часто, а раньше фильтра не было вовсе. -->
      <template #filters>
        <v-text-field
          v-model="search"
          :placeholder="$t('groups.list.search')"
          :aria-label="$t('groups.list.search')"
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

      <template #cell-groupname="{ row }">
        <router-link
          class="groups-link"
          :to="{ name: 'groups.update', params: { idEdit: row.id } }"
        >
          {{ row.groupname }}
        </router-link>
      </template>

      <template #cell-groupdescription="{ value }">
        <span class="u-truncate" style="display: block; max-width: 28ch" :title="value">
          {{ value || '—' }}
        </span>
      </template>

      <template #cell-courses="{ row }">
        <span v-if="!row.group2learnings?.length" class="u-muted">—</span>
        <span v-else>{{ row.group2learnings.length }}</span>
      </template>

      <template #cell-category="{ row }">
        <span v-if="!row.group2learnings?.length" class="u-muted">—</span>
        <ul v-else class="groups-cats">
          <li v-for="entry in row.group2learnings" :key="entry.id">
            {{ categoryTitle(entry.category_id) }}
          </li>
        </ul>
      </template>

      <template #cell-period="{ row }">
        <span v-if="!row.group2learnings?.length" class="u-muted">—</span>
        <span v-else class="u-nowrap">
          {{ row.group2learnings[0].study_from }} — {{ row.group2learnings[0].study_to }}
        </span>
      </template>

      <template #cell-actions="{ row }">
        <div class="groups-actions">
          <v-btn
            size="small"
            variant="text"
            color="primary"
            :to="{ name: 'group.learning', params: { idEdit: row.id } }"
            :title="$t('groups.list.enroll')"
            :aria-label="`${$t('groups.list.enroll')}: ${row.groupname}`"
          >
            <v-icon icon="mdi-school-outline" size="18" aria-hidden="true"></v-icon>
            {{ $t("groups.list.enrollShort") }}
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            :to="{ name: 'groups.update', params: { idEdit: row.id } }"
            :title="$t('groups.list.edit')"
            :aria-label="`${$t('groups.list.edit')}: ${row.groupname}`"
          >
            <v-icon icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            color="error"
            :aria-label="`${$t('groups.list.delete')}: ${row.groupname}`"
            :title="$t('groups.list.delete')"
            @click="askDelete(row)"
          >
            <v-icon icon="mdi-trash-can-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>
        </div>
      </template>
    </DataTable>

    <!--
      Подтверждение удаления. Раньше диалог был один на страницу
      (общий булев dialog), поэтому открывался сразу у всех строк,
      а «Отменить» был красным, а «Удалить» — зелёным.
    -->
    <ConfirmDialog
      v-model="deleteDialog"
      :title="$t('groups.delete.title')"
      :confirm-text="$t('groups.delete.confirm')"
      :cancel-text="$t('common.cancel')"
      :busy="deleting"
      @cancel="deleteDialog = false"
      @confirm="confirmDelete"
    >
      {{ $t('groups.delete.text', { name: pendingGroup?.groupname ?? '' }) }}
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
  name: "GroupList",
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
    this.$store.dispatch("Course/fetchCourses");
    this.$store.dispatch("Course/fetchCategories");
    await this.$store.dispatch("User/fetchGroups").catch(() => {});
    this.isLoading = false;
  },

  computed: {
    ...mapState("User", ["allGroups"]),
    ...mapState("Course", ["categories"]),

    columns() {
      return [
        { key: "id", title: "ID", width: "64px" },
        { key: "groupname", title: this.$t("groups.list.name") },
        { key: "groupdescription", title: this.$t("groups.list.description") },
        { key: "courses", title: this.$t("groups.list.courses"), width: "96px" },
        { key: "category", title: this.$t("groups.list.category"), width: "200px" },
        { key: "period", title: this.$t("groups.list.period"), width: "180px" },
        { key: "actions", title: "", align: "right", width: "200px" },
      ];
    },

    pendingGroup() {
      return this.allGroups.find((group) => group.id === this.pendingId) ?? null;
    },

    filteredGroups() {
      const query = (this.search ?? "").trim().toLowerCase();
      if (!query) return this.allGroups;
      return this.allGroups.filter(
        (group) => String(group.groupname ?? "").toLowerCase().includes(query)
      );
    },
  },

  methods: {
    categoryTitle(categoryId) {
      if (!categoryId) return "—";
      return this.categories.find((c) => c.id === categoryId)?.title ?? `#${categoryId}`;
    },

    askDelete(group) {
      this.pendingId = group.id;
      this.deleteDialog = true;
    },

    async confirmDelete() {
      if (!this.pendingId) return;

      this.deleting = true;

      try {
        await this.$store.dispatch("User/deleteGroup", this.pendingId);
        await this.$store.dispatch("User/fetchGroups");
        this.notify(this.$t("groups.delete.done"), "success");
      } catch (error) {
        this.notify(this.$t("groups.delete.error"), "error");
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
.groups-link {
  color: var(--c-primary);
  font-weight: 500;
  text-decoration: none;
}

.groups-link:hover {
  text-decoration: underline;
}

.groups-cats {
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: var(--fs-sm);
  color: var(--c-text-secondary);
}

.groups-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--sp-1);
}
</style>
