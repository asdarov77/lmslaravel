<template>
  <div class="u-page">
    <PageHeader
      :title="$t('categories.list.title')"
      :subtitle="$t('categories.list.subtitle')"
    >
      <template #actions>
        <v-btn color="primary" variant="flat" :to="{ name: 'categories.store' }">
          <v-icon start icon="mdi-plus" size="18" aria-hidden="true"></v-icon>
          {{ $t("categories.list.create") }}
        </v-btn>
      </template>
    </PageHeader>

    <DataTable
      :columns="columns"
      :rows="filteredCategories"
      :loading="isLoading"
      :title="$t('categories.list.title')"
      :count="filteredCategories.length"
      :caption="$t('categories.list.title')"
      :empty-title="aircrafts.length ? $t('categories.list.emptyFiltered') : $t('categories.list.emptyTitle')"
      :empty-text="aircrafts.length ? $t('categories.list.emptyFilteredText') : $t('categories.list.emptyText')"
      :empty-icon="'mdi-shape-outline'"
    >
      <!--
        Фильтр по самолёту.
        Раньше это были чекбоксы с active-class="xxx" — состояние
        выглядело непонятно, «КЛЕН» и «БПЛА» стояли рядом без
        подсказки, что это переключатель. Теперь это группа кнопок:
        нажатая подсвечена, «Все» сбрасывает фильтр.
      -->
      <template #filters>
        <div class="cats-filter">
          <span class="cats-filter__label">{{ $t("categories.list.filterAircraft") }}</span>
          <div class="cats-filter__group" role="group" :aria-label="$t('categories.list.filterAircraft')">
            <button
              type="button"
              class="cats-chip"
              :class="{ 'cats-chip--on': aircraftFilter === null }"
              :aria-pressed="aircraftFilter === null"
              @click="aircraftFilter = null"
            >
              {{ $t("categories.list.all") }}
            </button>
            <button
              v-for="air in aircrafts"
              :key="air.id"
              type="button"
              class="cats-chip"
              :class="{ 'cats-chip--on': aircraftFilter === air.id }"
              :aria-pressed="aircraftFilter === air.id"
              @click="aircraftFilter = air.id"
            >
              {{ air.path }}
            </button>
          </div>
        </div>
      </template>

      <template #cell-id="{ value }">
        <span class="u-table__num">{{ value }}</span>
      </template>

      <template #cell-title="{ row }">
        <span class="cats-title">{{ row.title }}</span>
      </template>

      <template #cell-code="{ value }">
        <code v-if="value" class="cats-code">{{ value }}</code>
        <span v-else class="u-muted">—</span>
      </template>

      <template #cell-description="{ value }">
        <span v-if="!value" class="u-muted">—</span>
        <span v-else class="u-truncate" style="display: block; max-width: 34ch" :title="value">
          {{ value }}
        </span>
      </template>

      <template #cell-aircraft="{ row }">
        <span class="u-badge">{{ aircraftTitle(row.aircraft_id) }}</span>
      </template>

      <template #cell-actions="{ row }">
        <div class="cats-actions">
          <v-btn
            size="small"
            variant="text"
            color="primary"
            :to="{ name: 'categories.update', params: { idEdit: row.id } }"
            :title="$t('categories.list.edit')"
            :aria-label="`${$t('categories.list.edit')}: ${row.title}`"
          >
            <v-icon icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            color="error"
            :aria-label="`${$t('categories.list.delete')}: ${row.title}`"
            :title="$t('categories.list.delete')"
            @click="askDelete(row)"
          >
            <v-icon icon="mdi-trash-can-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>
        </div>
      </template>
    </DataTable>

    <ConfirmDialog
      v-model="deleteDialog"
      :title="$t('categories.delete.title')"
      :confirm-text="$t('categories.delete.confirm')"
      :cancel-text="$t('common.cancel')"
      :busy="deleting"
      @cancel="deleteDialog = false"
      @confirm="confirmDelete"
    >
      {{ $t('categories.delete.text', { name: pendingCategory?.title ?? '' }) }}
    </ConfirmDialog>

  </div>
</template>

<script>
import { mapState, mapGetters } from "vuex";
import PageHeader from "../../components/ui/PageHeader.vue";
import DataTable from "../../components/ui/DataTable.vue";
import ConfirmDialog from "../../components/ui/ConfirmDialog.vue";

export default {
  name: "CategoryList",
  components: { PageHeader, DataTable, ConfirmDialog },

  data() {
    return {
      /** null — фильтр не выбран (показываем все самолёты). */
      aircraftFilter: null,
      isLoading: true,
      deleting: false,
      deleteDialog: false,
      pendingId: null,
    };
  },

  async created() {
    await Promise.all([
      this.$store.dispatch("Course/fetchAircrafts").catch(() => {}),
      this.$store.dispatch("Course/fetchCategories").catch(() => {}),
    ]);
    this.isLoading = false;
  },

  computed: {
    ...mapState("Course", ["categories", "aircrafts"]),

    filteredCategories() {
      const list = Array.isArray(this.categories) ? this.categories : [];

      if (this.aircraftFilter === null) return list;

      return list.filter(
        (category) => Number(category.aircraft_id) === Number(this.aircraftFilter)
      );
    },

    columns() {
      return [
        { key: "id", title: "ID", width: "64px" },
        { key: "title", title: this.$t("categories.list.name") },
        { key: "code", title: this.$t("categories.list.code"), width: "120px" },
        { key: "description", title: this.$t("categories.list.description") },
        { key: "aircraft", title: this.$t("categories.list.aircraft"), width: "120px" },
        { key: "actions", title: "", align: "right", width: "120px" },
      ];
    },

    pendingCategory() {
      return (this.categories ?? []).find((c) => c.id === this.pendingId) ?? null;
    },
  },

  methods: {
    aircraftTitle(id) {
      return this.aircrafts.find((a) => a.id === id)?.path ?? "—";
    },

    askDelete(category) {
      this.pendingId = category.id;
      this.deleteDialog = true;
    },

    async confirmDelete() {
      if (!this.pendingId) return;

      this.deleting = true;

      try {
        await this.$store.dispatch("Course/deleteCategory", this.pendingId);
        await this.$store.dispatch("Course/fetchCategories");
        this.notify(this.$t("categories.delete.done"), "success");
      } catch (error) {
        this.notify(this.$t("categories.delete.error"), "error");
      } finally {
        this.deleting = false;
        this.deleteDialog = false;
        this.pendingId = null;
      }
    },

    notify(text, type) {
      toast.byType(type, text);
    },
  },
};
</script>

<style scoped>
.cats-filter {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  flex-wrap: wrap;
}

.cats-filter__label {
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wide);
  color: var(--c-text-muted);
}

.cats-filter__group {
  display: flex;
  gap: var(--sp-2);
  flex-wrap: wrap;
}


.cats-title {
  font-weight: var(--fw-medium);
}

.cats-code {
  font-family: var(--font-mono);
  font-size: var(--fs-xs);
  padding: 2px var(--sp-2);
  border-radius: var(--radius-sm);
  background: var(--c-surface-3);
  color: var(--c-text-secondary);
}

.cats-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--sp-1);
}
</style>
