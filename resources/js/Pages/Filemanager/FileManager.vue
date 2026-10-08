<template>
  <div class="u-page fm">
    <PageHeader :title="$t('files.title')" :subtitle="$t('files.subtitle')">
      <template #actions>
        <v-btn
          color="primary"
          variant="flat"
          data-test="fm-upload"
          :disabled="busy"
          @click="pickFiles"
        >
          <v-icon start icon="mdi-upload" size="18" aria-hidden="true"></v-icon>
          {{ $t("files.actions.upload") }}
        </v-btn>

        <v-btn
          variant="tonal"
          data-test="fm-new-folder"
          :disabled="busy"
          @click="openCreateFolder"
        >
          <v-icon start icon="mdi-folder-plus-outline" size="18" aria-hidden="true"></v-icon>
          {{ $t("files.actions.newFolder") }}
        </v-btn>

        <v-btn
          icon="mdi-refresh"
          variant="text"
          :aria-label="$t('files.actions.refresh')"
          :title="$t('files.actions.refresh')"
          :loading="loading"
          @click="load"
        ></v-btn>
      </template>
    </PageHeader>

    <!--
      Очередь загрузки.

      Показана всегда, пока в ней есть что показывать, и не исчезает
      сама: после последнего файла список убирается по кнопке. Иначе
      полоса прогресса пропадала бы ровно в тот момент, когда
      пользователь хочет убедиться, что всё дошло.
    -->
    <v-card v-if="uploadTasks.length" class="u-card fm-queue" data-test="fm-queue">
      <div class="u-card__head">
        <div class="d-flex align-center" style="gap: var(--sp-2)">
          <h2 class="u-card__title">{{ $t("files.queue.title") }}</h2>
          <span class="u-badge">{{ uploadTasks.length }}</span>
        </div>
        <div class="d-flex align-center" style="gap: var(--sp-2)">
          <v-btn
            size="small"
            variant="text"
            data-test="fm-queue-clear"
            @click="queue.clearFinished()"
          >
            {{ $t("files.queue.clear") }}
          </v-btn>
          <v-btn
            size="small"
            variant="text"
            @click="cancelAllUploads"
          >
            {{ $t("files.queue.cancelAll") }}
          </v-btn>
        </div>
      </div>

      <v-card-text class="fm-queue__body">
        <div
          v-for="task in uploadTasks"
          :key="task.id"
          class="fm-queue__item"
          :data-test="'fm-upload-item'"
        >
          <div class="fm-queue__head">
            <v-icon
              size="18"
              :icon="statusIcon(task)"
              :color="statusColor(task)"
              aria-hidden="true"
            ></v-icon>

            <span class="fm-queue__name" :title="task.name">{{ task.name }}</span>

            <span class="fm-queue__status" :data-test="'fm-upload-status'">
              {{ statusText(task) }}
            </span>

            <v-btn
              v-if="isActive(task)"
              size="x-small"
              variant="text"
              :aria-label="$t('files.queue.cancel')"
              @click="removeTask(task)"
            >
              {{ $t("files.queue.cancel") }}
            </v-btn>
          </div>

          <v-progress-linear
            :model-value="percent(task)"
            :color="statusColor(task)"
            height="6"
            rounded
            :aria-label="$t('files.queue.progress')"
          ></v-progress-linear>

          <div v-if="task.error" class="fm-queue__error">{{ task.error }}</div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Путь и действия над выделенным -->
    <v-card class="u-card fm-toolbar">
      <div class="fm-toolbar__path" data-test="fm-breadcrumbs">
        <template v-for="(crumb, index) in breadcrumb" :key="crumb.id ?? 'root'">
          <button
            type="button"
            class="fm-crumb"
            :class="{ 'fm-crumb--current': index === breadcrumb.length - 1 }"
            :aria-current="index === breadcrumb.length - 1 ? 'page' : null"
            :disabled="index === breadcrumb.length - 1"
            @click="openFolder(crumb.id)"
          >
            {{ crumb.name }}
          </button>
          <span v-if="index < breadcrumb.length - 1" class="fm-crumb__sep" aria-hidden="true">/</span>
        </template>
      </div>

      <div class="fm-toolbar__actions">
        <span v-if="selectedFileIds.length" class="u-badge" data-test="fm-selected">
          {{ $t("files.selection.count", { n: selectedFileIds.length }) }}
        </span>

        <v-btn
          size="small"
          variant="text"
          data-test="fm-move"
          :disabled="!selectedFileIds.length || busy"
          @click="openMove"
        >
          <v-icon start icon="mdi-folder-move-outline" size="18" aria-hidden="true"></v-icon>
          {{ $t("files.actions.move") }}
        </v-btn>

        <v-btn
          size="small"
          variant="text"
          color="error"
          data-test="fm-delete"
          :disabled="!selectedFileIds.length || busy"
          @click="askDeleteFiles"
        >
          <v-icon start icon="mdi-trash-can-outline" size="18" aria-hidden="true"></v-icon>
          {{ $t("files.actions.delete") }}
        </v-btn>
      </div>
    </v-card>

    <!--
      Зонa перетаскивания. Показывается всегда, а не только на
      перетаскивании: пользователь должен видеть, что сюда можно
      положить файл, иначе перетаскивание выглядит как ошибка.
    -->
    <div
      class="fm-drop"
      :class="{ 'fm-drop--active': dragOver }"
      data-test="fm-dropzone"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="dragOver = false"
      @drop.prevent="onDrop"
    >
      <v-icon icon="mdi-tray-arrow-down" size="22" aria-hidden="true"></v-icon>
      <span>{{ $t("files.drop.hint") }}</span>
      <span v-if="limits.max_bytes > 0" class="fm-drop__limit">
        {{ $t("files.drop.limit", { size: formatBytes(limits.max_bytes) }) }}
      </span>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :title="$t('files.table.title')"
      :count="rows.length"
      :caption="$t('files.table.caption')"
      :empty-title="$t('files.empty.title')"
      :empty-text="$t('files.empty.text')"
      empty-icon="mdi-folder-open-outline"
      :is-selected="isRowSelected"
    >
      <template #cell-select="{ row }">
        <v-checkbox-btn
          :model-value="isRowSelected(row)"
          :aria-label="selectLabel(row)"
          :data-test="'fm-select-' + row.kind + '-' + row.id"
          @update:model-value="toggleRow(row, $event)"
        ></v-checkbox-btn>
      </template>

      <template #cell-name="{ row }">
        <div class="fm-name">
          <v-icon
            size="20"
            :icon="row.kind === 'folder' ? 'mdi-folder' : fileIcon(row)"
            :color="row.kind === 'folder' ? 'primary' : undefined"
            aria-hidden="true"
          ></v-icon>

          <!--
            Папка открывается, файл скачивается. Разные действия на
            одном и том же «имени» — привычно пользователю файловых
            менеджеров, и неожиданно, если и то и другое открывает
            диалог.
          -->
          <a
            v-if="row.kind === 'folder'"
            href="#"
            class="fm-name__link"
            :data-test="'fm-folder-' + row.id"
            @click.prevent="openFolder(row.id)"
          >
            {{ row.name }}
          </a>

          <button
            v-else
            type="button"
            class="fm-name__link fm-name__link--file"
            :data-test="'fm-file-' + row.id"
            @click="download(row)"
          >
            {{ row.name }}
          </button>
        </div>
      </template>

      <template #cell-size="{ row }">
        <span v-if="row.kind === 'folder'" class="u-muted">{{ $t("files.table.items", { n: row.item_count || 0 }) }}</span>
        <span v-else class="u-table__num">{{ formatBytes(row.size) }}</span>
      </template>

      <template #cell-modified="{ row }">
        <span class="u-muted">{{ formatDate(row.updated_at) }}</span>
      </template>

      <template #cell-actions="{ row }">
        <div class="fm-actions">
          <v-btn
            v-if="row.kind === 'file'"
            size="small"
            variant="text"
            :aria-label="actionLabel('download', row)"
            :title="$t('files.actions.download')"
            @click="download(row)"
          >
            <v-icon icon="mdi-download-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            color="primary"
            :aria-label="actionLabel('rename', row)"
            :title="$t('files.actions.rename')"
            :data-test="'fm-rename-' + row.kind + '-' + row.id"
            @click="openRename(row)"
          >
            <v-icon icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            color="primary"
            :aria-label="actionLabel('move', row)"
            :title="$t('files.actions.move')"
            :data-test="'fm-move-' + row.kind + '-' + row.id"
            @click="openMoveForRow(row)"
          >
            <v-icon icon="mdi-folder-move-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            color="error"
            :aria-label="actionLabel('delete', row)"
            :title="$t('files.actions.delete')"
            :data-test="'fm-delete-' + row.kind + '-' + row.id"
            @click="askDeleteRow(row)"
          >
            <v-icon icon="mdi-trash-can-outline" size="18" aria-hidden="true"></v-icon>
          </v-btn>
        </div>
      </template>
    </DataTable>

    <!-- Скрытый input файлов: кнопка и перетаскивание ведут в один сценарий -->
    <input
      ref="fileInput"
      type="file"
      multiple
      class="fm-input"
      data-test="fm-file-input"
      @change="onPicked"
    />

    <!-- Создание папки / переименование: один диалог на два случая -->
    <v-dialog v-model="nameDialog" max-width="480" role="dialog">
      <v-card>
        <v-card-title class="fm-dialog__title">
          {{ nameDialogMode === 'create' ? $t("files.folder.create") : $t("files.rename.title") }}
        </v-card-title>

        <v-card-text>
          <v-text-field
            v-model="nameValue"
            :label="$t('files.field.name')"
            :error-messages="nameError"
            data-test="fm-name-input"
            :autofocus="true"
            @keyup.enter="submitName"
          ></v-text-field>
        </v-card-text>

        <v-card-actions class="fm-dialog__actions">
          <v-btn variant="text" :disabled="saving" @click="nameDialog = false">
            {{ $t("common.cancel") }}
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="saving"
            data-test="fm-name-submit"
            @click="submitName"
          >
            {{ $t("common.save") }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Перемещение: файлы выделенные, иначе — те, что нажали в строке -->
    <v-dialog v-model="moveDialog" max-width="480" role="dialog">
      <v-card>
        <v-card-title class="fm-dialog__title">{{ $t("files.move.title") }}</v-card-title>

        <v-card-text>
          <v-select
            v-model="moveTarget"
            :items="moveTargets"
            item-title="label"
            item-value="value"
            :label="$t('files.move.target')"
            data-test="fm-move-select"
          ></v-select>

          <p v-if="moveError" class="fm-dialog__error">{{ moveError }}</p>
        </v-card-text>

        <v-card-actions class="fm-dialog__actions">
          <v-btn variant="text" :disabled="saving" @click="moveDialog = false">
            {{ $t("common.cancel") }}
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="saving"
            data-test="fm-move-submit"
            @click="submitMove"
          >
            {{ $t("files.move.submit") }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ConfirmDialog
      v-model="deleteDialog"
      :title="deleteDialogTitle"
      :confirm-text="$t('common.delete')"
      :cancel-text="$t('common.cancel')"
      :busy="saving"
      @cancel="closeDelete"
      @confirm="confirmDelete"
    >
      {{ deleteDialogText }}
    </ConfirmDialog>
  </div>
</template>

<script>
import PageHeader from "../../components/ui/PageHeader.vue";
import DataTable from "../../components/ui/DataTable.vue";
import ConfirmDialog from "../../components/ui/ConfirmDialog.vue";
import { toast } from "../../composables/useToast";
import { extractFieldErrors } from "../../api/envelope";
import { createUploadQueue, STATUS } from "../../services/chunkUpload";
import {
  fetchFolderContents,
  fetchFolderTree,
  createFolder,
  renameFolder,
  moveFolder,
  deleteFolder,
  renameFile,
  moveFiles,
  deleteFiles,
  downloadFile,
} from "../../api/filemanager.api";

/**
 * Файловый менеджер.
 *
 * Что заменено: страница зонда /filemanager, которая дёргала несуществующий
 * api/tree (404), показывала кнопку «Test» с жёстко зашитым
 * file:///home/.../index.html и не умела ничего. Страница /files/add
 * осталась: её использует FileLoadSimple.vue, который подключают формы
 * курсов.
 *
 * Ключевые решения, которые не видны из разметки:
 *
 *  1. ЗАГРУЗКА ИДЁТ ЧЕРЕЗ ОЧЕРЕДЬ, А НЕ ОДИН ЗАПРОС НА ФАЙЛ. Файл
 *     режется на части на клиенте, поэтому лимит размера запроса
 *     (post_max_size, client_max_body_size) перестаёт быть ограничением
 *     на файл, а докачка после обрыва появляется почти бесплатно.
 *     Подробности — в services/chunkUpload.js.
 *
 *  2. ПРЕДЕЛ ПРИХОДИТ С СЕРВЕРА. Клиент не должен знать про
 *     post_max_size: он отличается на каждой машине. Значение приходит
 *     в limits и показывается до выбора файла.
 *
 *  3. СОСТОЯНИЕ СПИСКА ОБНОВЛЯЕТСЯ ПОСЛЕ КАЖДОЙ ОПЕРАЦИИ, А НЕ
 *     ОПТИМИСТИЧНО. Перенос пятидесяти файлов меняет имена (занятые
 *     получают «(2)»), и оптимистичная вставка показала бы не то, что
 *     лежит на диске.
 *
 *  4. ПАПКА БЕЗ ФАЙЛОВ НЕ УДАЛЯЕТСЯ МОЛЧА. Сервер отвечает 422 с
 *     отдельным текстом, и только после подтверждения интерфейс
 *     повторяет запрос с recursive — «удалить папку» не превращается в
 *     «удалить триста файлов».
 */
export default {
  name: "FileManager",
  components: { PageHeader, DataTable, ConfirmDialog },

  data() {
    return {
      /** Текущий каталог. null — корень пользователя. */
      folderId: null,
      breadcrumb: [{ id: null, name: "Файлы" }],
      folders: [],
      files: [],
      limits: { max_bytes: 0, chunk_bytes: 0, used_bytes: 0 },

      loading: true,
      saving: false,
      dragOver: false,

      /** Ключи выделенных строк: 'f:12' (папка) и 'd:34' (файл). */
      selected: [],

      nameDialog: false,
      nameDialogMode: "create",
      nameValue: "",
      nameError: null,
      nameTarget: null,

      moveDialog: false,
      moveTarget: 0,
      moveTargets: [],
      moveRows: [],
      moveError: null,

      deleteDialog: false,
      deleteRows: [],
      deleteNonEmptyFolder: null,

      /**
       * Список задач загрузки ЗДЕСЬ, а не внутри очереди.
       *
       * Vue 3 уведомляет об изменениях только при записи через
       * прокси. Если бы список жил внутри createUploadQueue, движок
       * писал бы в «сырые» объекты, а шаблон читал reactive-версию —
       * и не увидел бы ничего: файлы загружались бы, а карточек
       * прогресса не появлялось. Поэтому массив принадлежит странице,
       * а очередь работает с ним (см. services/chunkUpload.js).
       */
      uploadTasks: [],

      /** Очередь — обычное свойство компонента, в data() ему не место. */
      queue: null,
    };
  },

  computed: {
    /**
     * Папки и файлы одним списком, но с пометкой kind.
     *
     * Смешанный список, а не два DataTable: у пользователя один взгляд на
     * каталог, а сортировка по имени работает по всей видимости сразу.
     * Различать их по полю в каждой строке — обязанность шаблона, а не
     * бэкенда.
     */
    rows() {
      const folders = this.folders.map((folder) => ({
        ...folder,
        kind: "folder",
        size: null,
      }));

      const files = this.files.map((file) => ({
        ...file,
        kind: "file",
      }));

      return [...folders, ...files].sort((a, b) =>
        a.name.localeCompare(b.name, "ru", { numeric: true })
      );
    },

    columns() {
      return [
        { key: "select", title: "", width: "48px" },
        { key: "name", title: this.$t("files.table.name") },
        { key: "size", title: this.$t("files.table.size"), width: "140px" },
        { key: "modified", title: this.$t("files.table.modified"), width: "180px" },
        { key: "actions", title: "", align: "right", width: "180px" },
      ];
    },

    /** Выделенные файлы: перенос и удаление работают только по файлам. */
    selectedFileIds() {
      return this.selected
        .filter((key) => key.startsWith("d:"))
        .map((key) => Number(key.slice(2)))
        .filter((id) => Number.isFinite(id));
    },

    selectedFolderIds() {
      return this.selected
        .filter((key) => key.startsWith("f:"))
        .map((key) => Number(key.slice(2)))
        .filter((id) => Number.isFinite(id));
    },

    busy() {
      return this.saving;
    },

    deleteDialogTitle() {
      if (this.deleteRows.length === 1) {
        return this.$t(
          this.deleteRows[0].kind === "folder"
            ? "files.delete.folderTitle"
            : "files.delete.fileTitle"
        );
      }

      return this.$t("files.delete.manyTitle", { n: this.deleteRows.length });
    },

    deleteDialogText() {
      if (this.deleteRows.length === 1) {
        return this.$t("files.delete.text", { name: this.deleteRows[0].name });
      }

      return this.$t("files.delete.textMany", { n: this.deleteRows.length });
    },
  },

  created() {
    // Множество уже перечитанных задач. Обычный Set, не реактивный:
    // оно нужно только как память «этот файл уже учтён», в шаблоне не
    // показывается, а помещать его в data() незачем.
    this.reloadedTasks = new Set();

    this.queue = createUploadQueue({
      tasks: this.uploadTasks,
      onChange: (task) => this.onTaskChange(task),
    });

    this.load();
  },

  methods: {
    // -----------------------------------------------------------------
    // Чтение
    // -----------------------------------------------------------------

    async load() {
      this.loading = true;

      try {
        const response = await fetchFolderContents(this.folderId);
        const data = response?.data?.data ?? {};

        this.breadcrumb = Array.isArray(data.breadcrumb) && data.breadcrumb.length
          ? data.breadcrumb
          : [{ id: null, name: this.$t("files.root") }];
        this.folders = Array.isArray(data.folders) ? data.folders : [];
        this.files = Array.isArray(data.files) ? data.files : [];
        this.limits = data.limits ?? this.limits;

        // Выделение сбрасывается: строки, которых больше нет, выделять
        // нечего, а оставить отметку значит показать действие, которое
        // молча ничего не сделает.
        this.selected = [];
      } catch (error) {
        const { general } = extractFieldErrors(error, this.$t("files.errors.load"));

        this.notify(general, "error");
      } finally {
        this.loading = false;
      }
    },

    async openFolder(folderId) {
      if (folderId === this.folderId) return;

      this.folderId = folderId;
      await this.load();
    },

    // -----------------------------------------------------------------
    // Выделение
    // -----------------------------------------------------------------

    rowKey(row) {
      return `${row.kind}:${row.id}`;
    },

    isRowSelected(row) {
      return this.selected.includes(this.rowKey(row));
    },

    toggleRow(row, value) {
      const key = this.rowKey(row);
      const index = this.selected.indexOf(key);

      if (value && index === -1) {
        this.selected.push(key);
      } else if (!value && index !== -1) {
        this.selected.splice(index, 1);
      }
    },

    selectLabel(row) {
      return this.$t("files.selection.item", { name: row.name });
    },

    actionLabel(action, row) {
      return this.$t(`files.actions.${action}`) + ": " + row.name;
    },

    // -----------------------------------------------------------------
    // Загрузка
    // -----------------------------------------------------------------

    pickFiles() {
      this.$refs.fileInput?.click();
    },

    onPicked(event) {
      const files = Array.from(event.target?.files ?? []);

      // Значение сбрасывается СРАЗУ: иначе повторный выбор того же файла
      // не вызовет change, и пользователь решит, что кнопка сломалась.
      event.target.value = "";

      this.enqueue(files);
    },

    onDrop(event) {
      this.dragOver = false;
      this.enqueue(Array.from(event.dataTransfer?.files ?? []));
    },

    enqueue(files) {
      if (!files.length) return;

      const max = Number(this.limits?.max_bytes ?? 0);
      const tooBig = max > 0 ? files.filter((file) => file.size > max) : [];

      // Отказ до начала загрузки: файл всё равно упёрся бы в предел,
      // но потратил бы время на отправку частей.
      if (tooBig.length) {
        this.notify(
          this.$t("files.errors.tooBig", {
            size: this.formatBytes(max),
            names: tooBig.map((file) => file.name).join(", "),
          }),
          "warning"
        );
      }

      const accepted = max > 0 ? files.filter((file) => file.size <= max) : files;

      this.queue.addAll(accepted, { folderId: this.folderId });

      if (accepted.length) {
        this.notify(this.$t("files.queue.queued", { n: accepted.length }), "info");
      }
    },

    isActive(task) {
      return task.status === STATUS.QUEUED || task.status === STATUS.UPLOADING;
    },

    percent(task) {
      if (task.status === STATUS.DONE) return 100;
      if (!task.size) return task.status === STATUS.DONE ? 100 : 0;

      return Math.min(100, Math.round((task.sent / task.size) * 100));
    },

    statusText(task) {
      if (task.status === STATUS.DONE) return this.$t("files.queue.done");
      if (task.status === STATUS.ERROR) return this.$t("files.queue.failed");
      if (task.status === STATUS.CANCELLED) return this.$t("files.queue.cancelled");
      if (task.status === STATUS.QUEUED) return this.$t("files.queue.queuedShort");

      return this.$t("files.queue.uploading", { percent: this.percent(task) });
    },

    statusIcon(task) {
      if (task.status === STATUS.DONE) return "mdi-check-circle-outline";
      if (task.status === STATUS.ERROR) return "mdi-alert-circle-outline";
      if (task.status === STATUS.CANCELLED) return "mdi-cancel";
      return "mdi-cloud-upload-outline";
    },

    statusColor(task) {
      if (task.status === STATUS.DONE) return "success";
      if (task.status === STATUS.ERROR) return "error";
      if (task.status === STATUS.CANCELLED) return "grey";
      return "primary";
    },

    /**
     * Убирает задачу из очереди.
     */
    removeTask(task) {
      this.queue.remove(task);
    },

    /** Отменяет все активные загрузки разом. */
    cancelAllUploads() {
      this.uploadTasks.filter((task) => this.isActive(task)).forEach((task) => this.removeTask(task));
    },

    /**
     * Реакция на изменение задачи: когда файл загружен, каталог
     * перечитывается.
     *
     * Именно по завершению, а не на каждый прогресс: файл появляется
     * в каталоге один раз, а перечитывание на каждый чанк означало бы
     * GET /api/filemanager на каждый мегабайт.
     *
     * Множество reloadedTasks защищает от повторного перечитывания:
     * движок сообщает о прогрессе и после сборки, и файл был бы
     * перезапрошен дважды.
     */
    onTaskChange(task) {
      if (task.status !== STATUS.DONE) return;
      if (this.reloadedTasks.has(task.id)) return;

      this.reloadedTasks.add(task.id);
      this.load();
    },

    // -----------------------------------------------------------------
    // Папки и переименование
    // -----------------------------------------------------------------

    openCreateFolder() {
      this.nameDialogMode = "create";
      this.nameTarget = null;
      this.nameValue = "";
      this.nameError = null;
      this.nameDialog = true;
    },

    openRename(row) {
      this.nameDialogMode = "rename";
      this.nameTarget = row;

      // Расширение у файла — часть имени, но редактировать его в том же
      // поле неудобно: пользователь чаще меняет основную часть. Показываем
      // полное имя — иначе «переименовать в» и «переименовать файл» будут
      // означать разное.
      this.nameValue = row.name;
      this.nameError = null;
      this.nameDialog = true;
    },

    async submitName() {
      const name = this.nameValue.trim();

      if (name === "") {
        this.nameError = this.$t("files.errors.emptyName");
        return;
      }

      this.saving = true;
      this.nameError = null;

      try {
        if (this.nameDialogMode === "create") {
          await createFolder(name, this.folderId);
          this.notify(this.$t("files.folder.created", { name }), "success");
        } else if (this.nameTarget.kind === "folder") {
          await renameFolder(this.nameTarget.id, name);
          this.notify(this.$t("files.renamed", { name }), "success");
        } else {
          await renameFile(this.nameTarget.id, name);
          this.notify(this.$t("files.renamed", { name }), "success");
        }

        this.nameDialog = false;
        await this.load();
      } catch (error) {
        // Текст ошибки с поля «name» показывается у поля, а не в общем
        // сообщении: пользователь смотрит на диалог, а не на тост.
        const { fields, general } = extractFieldErrors(error, this.$t("files.errors.save"));
        this.nameError = fields.name ?? general;
      } finally {
        this.saving = false;
      }
    },

    // -----------------------------------------------------------------
    // Перемещение
    // -----------------------------------------------------------------

    async openMove() {
      await this.openMoveForRows(this.selectedFileIds.map((id) => ({ kind: "file", id })));
    },

    async openMoveForRow(row) {
      await this.openMoveForRows([row]);
    },

    async openMoveForRows(rows) {
      if (!rows.length) return;

      this.moveRows = rows;
      this.moveTarget = 0;
      this.moveError = null;

      try {
        const response = await fetchFolderTree();
        const tree = response?.data?.data ?? [];

        this.moveTargets = [
          { value: 0, label: this.$t("files.move.root") },
          // Текущая папка в списке назначения — лишняя: файл, который
          // уже там, ничего не переместит, а интерфейс сообщил бы «0
          // перемещено».
          ...tree
            .filter((folder) => folder.id !== this.folderId)
            .map((folder) => ({
              value: folder.id,
              label: folder.path,
            })),
        ];

        this.moveDialog = true;
      } catch (error) {
        const { general } = extractFieldErrors(error, this.$t("files.errors.move"));
        this.notify(general, "error");
      }
    },

    async submitMove() {
      if (!this.moveRows.length) return;

      // 0 — корень. Числом, а не null, потому что v-select пустым
      // значением считал бы «ничего не выбрано» и не дал отправить
      // запрос в корень.
      const target = Number(this.moveTarget) === 0 ? null : Number(this.moveTarget);

      this.saving = true;
      this.moveError = null;

      try {
        const folders = this.moveRows.filter((row) => row.kind === "folder");
        const files = this.moveRows.filter((row) => row.kind === "file");

        for (const folder of folders) {
          await moveFolder(folder.id, target);
        }

        if (files.length) {
          await moveFiles(files.map((row) => row.id), target);
        }

        this.moveDialog = false;
        await this.load();
        this.notify(this.$t("files.moved"), "success");
      } catch (error) {
        const { fields, general } = extractFieldErrors(error, this.$t("files.errors.move"));
        this.moveError = fields.folder_id ?? general;
      } finally {
        this.saving = false;
      }
    },

    // -----------------------------------------------------------------
    // Удаление
    // -----------------------------------------------------------------

    askDeleteFiles() {
      this.deleteRows = this.selectedFileIds
        .map((id) => this.files.find((file) => file.id === id))
        .filter(Boolean);

      if (this.deleteRows.length) {
        this.deleteNonEmptyFolder = null;
        this.deleteDialog = true;
      }
    },

    askDeleteRow(row) {
      this.deleteRows = [row];
      this.deleteNonEmptyFolder = null;
      this.deleteDialog = true;
    },

    closeDelete() {
      this.deleteDialog = false;
      this.deleteRows = [];
      this.deleteNonEmptyFolder = null;
    },

    async confirmDelete() {
      if (!this.deleteRows.length) return;

      this.saving = true;

      try {
        const folders = this.deleteRows.filter((row) => row.kind === "folder");
        const files = this.deleteRows.filter((row) => row.kind === "file");

        for (const folder of folders) {
          if (this.deleteNonEmptyFolder === folder.id) {
            await deleteFolder(folder.id, true);
            continue;
          }

          try {
            await deleteFolder(folder.id, false);
          } catch (error) {
            // 422 на непустую папку — это не ошибка, а вопрос: «там есть
            // содержимое, удалить?». Подтверждение задаётся один раз на
            // папку, и повтор идёт с recursive.
            if (isNotEmptyFolderError(error)) {
              this.deleteNonEmptyFolder = folder.id;
              this.deleteDialog = false;
              this.notify(this.$t("files.delete.confirmNonEmpty", { name: folder.name }), "warning");
              return;
            }

            throw error;
          }
        }

        if (files.length) {
          await deleteFiles(files.map((row) => row.id));
        }

        this.closeDelete();
        await this.load();
        this.notify(this.$t("files.deleted"), "success");
      } catch (error) {
        const { general } = extractFieldErrors(error, this.$t("files.errors.delete"));
        this.notify(general, "error");
        this.closeDelete();
      } finally {
        this.saving = false;
      }
    },

    // -----------------------------------------------------------------
    // Скачивание
    // -----------------------------------------------------------------

    async download(row) {
      try {
        await downloadFile(row);
      } catch (error) {
        const { general } = extractFieldErrors(error, this.$t("files.errors.download"));
        this.notify(general, "error");
      }
    },

    // -----------------------------------------------------------------
    // Разное
    // -----------------------------------------------------------------

    /** Иконка по расширению: не украшение, а быстрый способ найти нужное. */
    fileIcon(row) {
      const ext = (row.extension || "").toLowerCase();

      if (["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"].includes(ext)) return "mdi-file-image-outline";
      if (["mp4", "webm", "mov", "avi", "mpeg"].includes(ext)) return "mdi-file-video-outline";
      if (["mp3", "ogg", "wav", "m4a", "flac"].includes(ext)) return "mdi-file-music-outline";
      if (["pdf"].includes(ext)) return "mdi-file-pdf-box";
      if (["zip", "rar", "7z", "tar", "gz"].includes(ext)) return "mdi-folder-zip-outline";
      if (["xls", "xlsx", "csv", "ods"].includes(ext)) return "mdi-file-table-outline";
      if (["doc", "docx", "odt", "rtf"].includes(ext)) return "mdi-file-word-outline";
      if (["txt", "md"].includes(ext)) return "mdi-file-document-outline";

      return "mdi-file-outline";
    },

    formatBytes(value) {
      const bytes = Number(value ?? 0);

      if (!Number.isFinite(bytes) || bytes <= 0) return this.$t("files.table.emptySize");

      const units = ["Б", "КБ", "МБ", "ГБ", "ТБ"];
      let size = bytes;
      let index = 0;

      while (size >= 1024 && index < units.length - 1) {
        size /= 1024;
        index++;
      }

      return `${index === 0 ? size : size.toFixed(1).replace(".", ",")} ${units[index]}`;
    },

    formatDate(value) {
      if (!value) return "—";

      const date = new Date(value);

      if (Number.isNaN(date.getTime())) return "—";

      return new Intl.DateTimeFormat("ru-RU", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }).format(date);
    },

    notify(text, type) {
      toast.byType(type, text);
    },
  },
};

/** Папка не пуста — сервер ответил 422 с полем folder_id. */
function isNotEmptyFolderError(error) {
  const status = error?.response?.status;

  if (status !== 422) return false;

  const details = error?.response?.data?.error?.details;

  return Boolean(details && "folder_id" in details);
}
</script>

<style scoped>
.fm-queue__body {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
  padding-top: var(--sp-4);
}

.fm-queue__item {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.fm-queue__head {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  min-width: 0;
}

.fm-queue__name {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 0.875rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fm-queue__status {
  font-size: var(--fs-xs);
  color: var(--c-text-muted, var(--c-text-secondary));
  white-space: nowrap;
}

.fm-queue__error {
  font-size: var(--fs-xs);
  color: var(--c-danger, #d32f2f);
}

.fm-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  flex-wrap: wrap;
  padding: var(--sp-3) var(--sp-5);
}

.fm-toolbar__path {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  flex-wrap: wrap;
  min-width: 0;
}

.fm-toolbar__actions {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.fm-crumb {
  background: none;
  border: 0;
  padding: 0;
  font: inherit;
  font-size: 0.875rem;
  color: var(--c-primary);
  cursor: pointer;
}

.fm-crumb:disabled {
  color: var(--c-text-secondary);
  cursor: default;
}

.fm-crumb:not(:disabled):hover {
  text-decoration: underline;
}

.fm-crumb--current {
  font-weight: var(--fw-semibold);
}

.fm-crumb__sep {
  color: var(--c-text-muted, var(--c-text-secondary));
  opacity: 0.6;
}

.fm-drop {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  flex-wrap: wrap;
  margin: var(--sp-4) 0;
  padding: var(--sp-4) var(--sp-5);
  border: 1px dashed var(--c-border);
  border-radius: var(--radius-md, 8px);
  color: var(--c-text-secondary);
  font-size: 0.875rem;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.fm-drop--active {
  border-color: var(--c-primary);
  background: var(--c-surface-2);
}

.fm-drop__limit {
  color: var(--c-text-muted, var(--c-text-secondary));
  font-size: var(--fs-xs);
}

/* input скрыт, но доступен: он и есть механика выбора файла. */
.fm-input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.fm-name {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  min-width: 0;
}

.fm-name__link {
  background: none;
  border: 0;
  padding: 0;
  font: inherit;
  color: var(--c-primary);
  cursor: pointer;
  text-align: left;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fm-name__link:hover {
  text-decoration: underline;
}

.fm-name__link--file {
  color: var(--c-text);
  cursor: pointer;
}

.fm-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--sp-1);
}

.fm-dialog__title {
  font-size: 1.125rem;
  font-weight: 600;
  padding: var(--sp-5) var(--sp-5) 0;
}

.fm-dialog__error {
  font-size: var(--fs-xs);
  color: var(--c-danger, #d32f2f);
  margin-top: var(--sp-2);
}

.fm-dialog__actions {
  padding: var(--sp-2) var(--sp-4) var(--sp-4);
}
</style>
