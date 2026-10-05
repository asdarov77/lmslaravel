<template>
  <v-card class="elevation-12 mx-auto" style="max-width: 900px; overflow: visible">
    <v-toolbar color="primary">
      <v-toolbar-title>{{ $t("groupLearning.title") }}</v-toolbar-title>
    </v-toolbar>

    <v-card-text>
      <v-form @submit.prevent="submitForm">
        <!-- ============================ выбор группы ============================ -->
        <!-- Регресс: поля выбора группы не было вовсе. Группа бралась только
             из маршрута (/group/learning/:idEdit), а пункт меню вёл на
             жёстко зашитый /group/learning/1 — то есть записать можно было
             только группу №1, и непонятно было, какую именно. -->
        <v-select
          label="Группа"
          type="text"
          :items="allGroups"
          v-model="form.group_id"
          item-value="id"
          item-title="groupname"
          :disabled="loading"
          :error-messages="fieldErrors.group_id"
          @update:modelValue="onGroupChange"
        ></v-select>

        <v-select
          label="Класс"
          type="text"
          :items="aircrafts"
          v-model="form.aircraft_id"
          item-value="id"
          item-title="path"
          :disabled="loading"
          :error-messages="fieldErrors.aircraft_id"
          @update:modelValue="changeAir"
        ></v-select>

        <v-select
          label="Категории"
          type="text"
          :items="catFilter"
          v-model="form.category_id"
          item-value="id"
          item-title="title"
          :disabled="loading || !form.aircraft_id"
          :error-messages="fieldErrors.category_id"
          @update:modelValue="changeCat"
        ></v-select>

        <v-divider class="my-3"></v-divider>

        <!-- Дерево курсов: раздел -> подраздел -> модуль. В выбор идут
             только модули (type 3), они и есть единица записи. -->
        <v-alert
          v-if="!hasAircraft"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-3"
          text="Выберите класс и категорию — после этого появится список курсов"
        ></v-alert>

        <treeselect
          v-if="form.aircraft_id && form.category_id"
          :key="treeselectKey"
          placeholder="Курсы и модули"
          :default-expand-level="1"
          v-model="form.course_ids"
          :options="options"
          :multiple="true"
          :clearable="false"
          :disabled="loading || saving"
        />

        <v-alert
          v-if="form.course_ids.length"
          type="info"
          variant="tonal"
          density="compact"
          class="mt-3"
          :text="`Выбрано модулей: ${form.course_ids.length}`"
        ></v-alert>

        <v-divider class="my-3"></v-divider>

        <v-select
          label="Инструктор"
          type="text"
          :items="instructors"
          v-model="form.teacher"
          item-value="fio"
          item-title="fio"
          clearable
          :disabled="loading"
        ></v-select>

        <!-- Регресс: у select со строковыми items стояло item-value="id".
             У строки нет поля id, поэтому модель всегда становилась
             undefined, и вид занятия молча не сохранялся. -->
        <v-select
          label="Вид занятия"
          type="text"
          :items="lessonTypes"
          v-model="form.typeOfLesson"
          :disabled="loading"
        ></v-select>

        <v-divider class="my-3"></v-divider>

        <v-row>
          <v-col>
            <v-text-field
              type="date"
              v-model="form.study_from"
              label="начало"
              variant="outlined"
              :disabled="loading"
              :error-messages="fieldErrors.study_from"
            ></v-text-field>
          </v-col>
          <v-col>
            <v-text-field
              type="date"
              v-model="form.study_to"
              label="конец"
              variant="outlined"
              :disabled="loading"
              :error-messages="fieldErrors.study_to"
            ></v-text-field>
          </v-col>
          <v-col>
            <!--
              Дедлайн — дата, к которой обучающийся обязан закончить
              модуль. Не путать с «конецом» периода: конец означает, что
              группа больше не занимается, дедлайн — когда сдавать.
              Необязателен, поэтому оставляем пустым по умолчанию.
            -->
            <v-text-field
              type="date"
              v-model="form.deadline"
              :label="$t('plan.deadline')"
              variant="outlined"
              :disabled="loading"
              clearable
              :min="form.study_from"
              :error-messages="fieldErrors.deadline"
            ></v-text-field>
          </v-col>
        </v-row>

        <!-- Регресс: интерполяция была внутри HTML-комментария
             (<!-- {{ error }} -->), поэтому пользователь видел красные
             пустые блоки без единого слова. -->
        <v-alert
          v-for="(error, index) in errors"
          :key="index"
          type="error"
          variant="tonal"
          class="mb-2"
          :text="error"
        ></v-alert>
      </v-form>
    </v-card-text>

    <v-card-actions>
      <v-spacer></v-spacer>
      <ButtonGroup
        @submitForm="submitForm"
        @cancelBtn="cancelBtnHead"
      ></ButtonGroup>
    </v-card-actions>

    <AppToast v-model="alert" :type="alertType" :text="snackbarText"></AppToast>
  </v-card>
</template>

<script>
import AppToast from "../../components/ui/AppToast.vue";
import { mapState } from "vuex";
import $api from "../../api/httpClient";
import { asArray, unwrapArray, unwrapResponse, numericQuery } from "../../api/envelope";
import { canonicalRoleSlug } from "../../utils/roles";
import ButtonGroup from "../../components/ButtonGroup.vue";
import Treeselect from "vue3-treeselect";
import "vue3-treeselect/dist/vue3-treeselect.css";

export default {
  name: "GroupLearning",
  components: { AppToast, ButtonGroup, Treeselect },

  props: {
    /** Группа из маршрута. Не обязательна: группу можно выбрать в форме. */
    idEdit: { type: Number, default: null },
  },

  data() {
    return {
      errors: [],
      fieldErrors: {},
      loading: true,
      saving: false,
      alert: false,
      alertType: "",
      snackbarText: "",

      // Форма хранится локально, а не в сторе: state.group — это
      // редактируемая группа, смешивать её с формой записи нельзя.
      form: {
        group_id: null,
        aircraft_id: null,
        category_id: null,
        course_ids: [],
        teacher: null,
        typeOfLesson: "Лекция",
        study_from: new Date().toISOString().slice(0, 10),
        study_to: null,
        deadline: null,
      },

      catFilter: [],
      options: [],
      // id узла дерева -> id курса-владельца. Treeselect возвращает только
      // выбранные id, а сохранять нужно и курс, и модуль.
      nodeCourse: {},
      // Пересоздаёт treeselect при смене класса/категории: у vue3-treeselect
      // нет реактивного сброса выбранных значений, и без этого в дереве
      // оставались id модулей от предыдущей категории.
      treeselectKey: 0,
    };
  },

  computed: {
    ...mapState("User", ["allGroups", "users"]),
    ...mapState("Course", ["aircrafts", "categories"]),

    lessonTypes() {
      return ["Лекция", "Практическое занятие", "Самостоятельная подготовка"];
    },

    instructors() {
      // Раньше отбор шёл по строке user.role === "Инструктор". У части
      // записей роль записана как 'instructor', и такие инструкторы
      // просто исчезали из списка. Сверяем канонический slug.
      return this.users.filter((user) => canonicalRoleSlug(user.role) === "instructor");
    },

    hasAircraft() {
      return Boolean(this.form.aircraft_id && this.form.category_id);
    },
  },

  async created() {
    await this.load();
  },

  methods: {
    async load() {
      this.loading = true;

      // Группы нужны обязательно: без них не выбрать, кому записывать курс.
      await Promise.all([
        this.$store.dispatch("User/fetchGroups"),
        this.$store.dispatch("User/fetchUsers"),
        this.$store.dispatch("Course/fetchCategories"),
        this.$store.dispatch("Course/fetchAircrafts"),
      ]).catch((error) => console.error(error));

      this.loading = false;

      // Если пришли с маршрута /group/learning/:id — подставляем группу,
      // но остаёмся на форме: выбор всё равно за пользователем.
      const fromRoute = Number(this.idEdit);

      if (Number.isInteger(fromRoute) && fromRoute > 0) {
        this.form.group_id = fromRoute;
        this.onGroupChange();
      }
    },

    onGroupChange() {
      this.fieldErrors.group_id = "";
    },

    changeAir(id_air) {
      this.fieldErrors.aircraft_id = "";
      this.resetCourses();

      // Категории принадлежат конкретному классу.
      // Сравнение строгое по числу: id из v-select приходит числом,
      // а из маршрута мог прийти строкой.
      this.form.category_id = null;
      this.catFilter = id_air
        ? this.categories.filter((category) => Number(category.aircraft_id) === Number(id_air))
        : [];
    },

    async changeCat(id_cat) {
      this.fieldErrors.category_id = "";
      this.resetCourses();

      if (!id_cat || !this.form.aircraft_id) return;

      const params = numericQuery({
        aircraft_id: this.form.aircraft_id,
        category_id: id_cat,
      });

      try {
        const response = await $api.get("/api/course", { params });
        const courses = unwrapArray(response);

        // В дерево идут только разделы/подразделы, а их потомки — модули.
        // Дерево строится из плоского списка aukstructures по parent_id.
        const nodeCourse = {};

        this.options = courses.map((course) => ({
          id: `course-${course.id}`,
          // vue3-treeselect читает именно `label` и `children`.
          // Без переименования title -> label узлы рендерятся пустыми,
          // а при выборе падает «Cannot read properties of null (reading 'id')».
          label: course.title,
          children: this.flatToHierarchy(asArray(course.aukstructures), course.id, nodeCourse),
        }));

        this.nodeCourse = nodeCourse;

        this.treeselectKey += 1;
      } catch (error) {
        this.options = [];
        this.errors.push("Не удалось загрузить список курсов");
        console.error(error);
      }
    },

    resetCourses() {
      this.form.course_ids = [];
      this.options = [];
      this.nodeCourse = {};
      this.treeselectKey += 1;
    },

    /**
     * Плоский список aukstructures -> иерархия по parent_id
     * с ключами treeselect (id / label / children).
     *
     * Узлы без родителя становятся корнями. Модули (type 3) — это
     * листья: именно их id уходят в запись, поэтому у них нет id,
     * который можно было бы спутать с другим деревом.
     */
    flatToHierarchy(flat, courseId, nodeCourse) {
      const byId = new Map();

      flat.forEach((item) => {
        if (!item) return;
        // label обязателен для treeselect, title из БД не подходит.
        nodeCourse[item.id] = courseId;
        byId.set(item.id, { id: item.id, label: item.title, children: [] });
      });

      const roots = [];

      byId.forEach((node) => {
        const parent = byId.get(node.parent_id);

        if (parent && parent !== node) {
          parent.children.push(node);
        } else {
          roots.push(node);
        }
      });

      return roots;
    },

    /** Приводит ответ API к списку строк с читаемым сообщением. */
    collectErrors(error) {
      const details = error?.response?.data?.error?.details;

      if (details && typeof details === "object") {
        Object.entries(details).forEach(([field, messages]) => {
          const text = Array.isArray(messages) ? messages.join(" ") : String(messages);
          this.fieldErrors[field.replace(/\.\d+$/, "")] = text;
        });
        return;
      }

      this.errors.push(
        error?.response?.data?.error?.message || error?.message || "Не удалось сохранить запись"
      );
    },

    validate() {
      this.errors = [];
      this.fieldErrors = {};

      if (!this.form.group_id) {
        this.fieldErrors.group_id = "Выберите группу";
      }
      if (!this.form.aircraft_id) {
        this.fieldErrors.aircraft_id = "Выберите класс";
      }
      if (!this.form.category_id) {
        this.fieldErrors.category_id = "Выберите категорию";
      }
      if (this.form.course_ids.length === 0) {
        this.errors.push("Выберите хотя бы один курс или модуль");
      }
      if (!this.form.study_from) {
        this.fieldErrors.study_from = "Укажите дату начала";
      }
      if (!this.form.study_to) {
        this.fieldErrors.study_to = "Укажите дату окончания";
      } else if (this.form.study_from && this.form.study_to < this.form.study_from) {
        this.fieldErrors.study_to = "Дата окончания не может быть раньше даты начала";
      }

      return Object.keys(this.fieldErrors).length === 0 && this.errors.length === 0;
    },

    async submitForm() {
      // Защита от повторного клика: запрос уходит один раз.
      if (this.saving) return;

      if (!this.validate()) {
        this.alert = true;
        this.alertType = "error";
        this.snackbarText = "Проверьте заполнение формы";
        return;
      }

      this.saving = true;
      this.alert = false;

      try {
        // Раскрываем выбранные узлы в «курс + модуль»: сам курс в course_id,
        // конкретный модуль (aukstructure) — в parent_id. Раньше туда уходил
        // просто id узла, и учебный план открывал несуществующий курс.
        const entries = Array.from(new Set(this.form.course_ids)).map((nodeId) => {
          const asCourse = /^course-(\d+)$/.exec(String(nodeId));

          return asCourse
            ? { course_id: Number(asCourse[1]), parent_id: null }
            : { course_id: this.nodeCourse[nodeId], parent_id: nodeId };
        }).filter((entry) => entry.course_id);

        await this.$store.dispatch("Course/fetchGroup2learnings", {
          group_id: this.form.group_id,
          entries,
          category_id: this.form.category_id,
          teacher: this.form.teacher,
          typeOfLesson: this.form.typeOfLesson,
          study_from: this.form.study_from,
          study_to: this.form.study_to,
          // null, а не пустая строка: бэкенд валидирует deadline как
          // date, и пустая строка дала бы 422.
          deadline: this.form.deadline || null,
        });

        this.alert = true;
        this.alertType = "success";
        this.snackbarText = "Группа записана на курсы";
        // Уходим только после успеха. Раньше redirect стоял в finally,
        // поэтому и неудачное сохранение выбрасывало на список групп,
        // и сообщение об ошибке пользователь уже не видел.
        this.$router.push("/groups/list");
      } catch (error) {
        this.collectErrors(error);
        this.alert = true;
        this.alertType = "error";
        this.snackbarText = this.errors[0] || "Не удалось сохранить запись";
      } finally {
        this.saving = false;
      }
    },

    cancelBtnHead() {
      this.$router.go(-1);
    },
  },
};
</script>

<style src="vue3-treeselect/dist/vue3-treeselect.css"></style>

<style>
.v-card-text {
  font-size: 16px;
}

.vue-treeselect__control {
  height: 56px;
  border-bottom: 1px solid;
  background: #f4f4f4;
  margin-bottom: 17px;
  border-radius: 5px;
}

.vue-treeselect__placeholder,
.vue-treeselect__single-value {
  padding-left: 12px;
  line-height: 56px;
  color: #848484;
}

.vue-treeselect__control-arrow-container {
  width: 38px;
}

.vue-treeselect__multi-value-item {
  cursor: pointer;
  color: #7a7a7a;
  background-color: #f2efef;
  border-bottom: 3px solid;
}

.vue-treeselect--has-value .vue-treeselect__multi-value {
  margin-bottom: 15px;
}
</style>