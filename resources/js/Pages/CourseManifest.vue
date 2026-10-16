<template>
  <v-progress-linear v-if="isLoading" color="primary" indeterminate></v-progress-linear>

  <CoursePlayerLayout
    :course-title="titleauk"
    :active-title="activeTitle"
    :sections="sections"
    :active-id="activeId"
    :progress="courseProgress"
    :sidebar-open="showItems"
    :is-fullscreen="isFullscreen"
    @select-section="onSelectSection"
    @prev="goPrev"
    @next="goNext"
    @toggle-sidebar="toggleList"
    @toggle-fullscreen="toggleFullscreen"
  >
    <template #sidebar-extra>
      <div class="cm-tree">
          <!-- Заголовок курса убран из сайдбара: он теперь один раз
               в верхней панели CoursePlayerLayout. Раньше надпись
               дублировалась и занимала три строки над инструментами. -->

          <!-- Вопросы по курсу. Форум живёт внутри курса: отдельный
               раздел в меню привёл бы в пустоту, потому что вопрос
               без курса не имеет смысла. -->
          <v-btn
            block
            variant="text"
            prepend-icon="mdi-forum-outline"
            :to="{ name: 'forum.list', params: { idEdit: idEdit } }"
            data-test="course-forum-link"
          >
            {{ $t('forum.title') }}
          </v-btn>
          <!-- Панель инструментов.
                     Регресс: здесь стояли фиксированные width:130px на трёх
                     иконках внутри колонки 4/12 (390px в ~30% ширины) плюс
                     .row-with-line с margin-bottom:-31px. Из-за отрицательного
                     отступа блоки наезжали друг на друга, а иконки вылезали
                     за пределы колонки — шапка «ломалась» на разных ширинах.
                     Теперь это обычная flex-панель без фиксированных ширин
                     и отрицательных отступов. -->
          <div class="cm-toolbar" role="toolbar" aria-:label="$t('common.labelPanel-kursa')">
            <div class="cm-toolbar__group">
              <button type="button"
                class="cm-tool"
                :class="{ 'cm-tool--on': showItems }"
                :title="$t('courseManifest.showTree')"
                :aria-pressed="showItems"
                @click="toggleList"
              >
                <v-icon size="22">{{ showItems ? 'mdi-view-list' : 'mdi-view-list-outline' }}</v-icon>
                <span class="cm-tool__label">{{ $t('courseManifest.tree') }}</span>
              </button>

              <button type="button"
                class="cm-tool"
                :class="{ 'cm-tool--on': isFavorite }"
                :title="$t('courseManifest.favorites')"
                :aria-pressed="isFavorite"
                @click="toggleFavorite"
              >
                <v-icon size="22">{{ isFavorite ? 'mdi-heart' : 'mdi-heart-outline' }}</v-icon>
                <span class="cm-tool__label">{{ $t('courseManifest.favorite') }}</span>
              </button>

              <button type="button"
                class="cm-tool"
                :class="{ 'cm-tool--on': showSearch }"
                :title="$t('courseManifest.search')"
                :aria-pressed="showSearch"
                @click="toggleSearch"
              >
                <v-icon size="22">{{ showSearch ? 'mdi-magnify-minus-outline' : 'mdi-magnify' }}</v-icon>
                <span class="cm-tool__label">{{ $t('courseManifest.search') }}</span>
              </button>

              <button type="button"
                class="cm-tool"
                :disabled="!activeId"
                :title="$t('courseManifest.addFavorite')"
                @click="addToFavorites(activeId)"
              >
                <v-icon size="22">mdi-bookmark-plus-outline</v-icon>
                <span class="cm-tool__label">{{ $t("courseManifest.addFavoriteShort") }}</span>
              </button>
            </div>

            <!-- Заметки студента к текущему материалу -->
            <MaterialNotes
              v-if="activeId"
              :material-id="activeId"
              :course-id="idEdit"
            />

            <div class="cm-toolbar__meta">
              <span v-if="activeTitle" class="cm-toolbar__current" :title="activeTitle">
                {{ activeTitle }}
              </span>
              <v-progress-circular v-if="isLoading" indeterminate size="18" width="2" color="primary" />
            </div>
          </div>

          <!-- Избранное. Раньше список был пустым без объяснения, а переключение
               режима ещё и скрывало дерево, из-за чего казалось, что кнопка
               «сломалась». Теперь режимы не исключают друг друга. -->
          <div v-if="isFavorite" class="cm-favorites">
            <div class="cm-favorites__head">
              {{ $t('courseManifest.favorites') }}
              <v-btn size="x-small" variant="text" @click="isFavorite = false">{{ $t('courseManifest.close') }}</v-btn>
            </div>
            <ul v-if="favorites.length" class="cm-favorites__list">
              <li v-for="item in favorites" :key="item.id" class="cm-favorites__item">
                <span class="cm-favorites__title">{{ item.title }}</span>
                <v-btn icon="mdi-close" size="x-small" variant="text"
                  :title="$t('courseManifest.removeFavorite')"
                  @click="removeFavorite(item.course_id)"></v-btn>
              </li>
            </ul>
            <p v-else class="cm-favorites__empty">{{ $t('courseManifest.favoritesEmpty') }}</p>
          </div>

          <v-row v-if="showSearch">
            <v-text-field class="ml-5 mr-5" :loading="loading" density="compact" v-model="searchTerm" variant="outlined"
              rounded append-inner-icon="mdi-magnify" :label="$t('common.labelPoisk')" @click:append-inner="search" @keyup.enter="search"
              :hint="$t('common.hintSearch')" clearable single-line>
            </v-text-field>

            <!-- ------------------------------------------------ -->
            <!-- результаты поиска -->

            <!--                                            рабочий вариант                           -->
            <!-- <div>
              <ul v-if="matchingFiles.length > 0" class=" ml-2 mr-2 search-files__total-results">{{ $t('courseManifest.foundTotal') }} {{
                matchingFiles.length }}
                <v-btn-group>
                  <v-btn @click="scrollToPrev"><span>&#9650;</span></v-btn>
                  <v-btn @click="scrollToNext"><span>&#9660;</span></v-btn>
                </v-btn-group>
                <v-divider></v-divider>
                <li v-for="result in matchingFiles" :key="result.file" style="white-space: nowrap;">
                  <v-btn @click="loadContent(result.itemId, result.highlightedNodes )" class="text-truncate"
                    :style="{ 'max-width': '100%', 'overflow': 'hidden', 'text-overflow': 'ellipsis' }">{{ result.title
                    }}</v-btn>   
                </li>
              </ul>
              <p v-else class="ml-5 mr-5 mt-1 search-files__no-results">{{ $t("courseManifest.noResults") }}</p>
            </div> -->
            <!--                                            рабочий вариант                           -->

            <!--                                            рабочий эксперимент                           -->
            <div>
              <ul v-if="matchingFiles.length > 0" class=" ml-2 mr-2 search-files__total-results">{{ $t('courseManifest.foundTotal') }} {{
                matchingFiles.length }}
                <v-btn-group>
                  <v-btn @click="scrollToPrev"><span>&#9650;</span></v-btn>
                  <v-btn @click="scrollToNext"><span>&#9660;</span></v-btn>
                </v-btn-group>
                <v-divider></v-divider>
                <li v-for="result in matchingFiles" :key="result.file" style="white-space: nowrap;">
                  <v-btn @click="loadContent(result.itemId, result.highlightedNodes)" class="text-truncate"
                    :style="{ 'max-width': '100%', 'overflow': 'hidden', 'text-overflow': 'ellipsis' }">{{ result.title
                    }}</v-btn>
                </li>
              </ul>
              <p v-else class="ml-5 mr-5 mt-1 search-files__no-results">{{ $t("courseManifest.noResults") }}</p>
            </div>
            <!--                                            рабочий эксперимент                           -->


            <!-- ------------------------------------------------ -->
            <!-- результаты поиска -->


          </v-row>
          <!-- Список разделов/подразделов/модулей.
                     Подсветка при наведении — на CSS, а не через
                     @mouseover + document.getElementById: обработчик
                     на каждом узле заставлял браузер трогать DOM на
                     каждом движении мыши, из-за чего страница «дёргалась»
                     и рендерилась медленно (182 узла). -->
      </div>
    </template>

    <!-- ------------------------------------правый iframe ----------------------------------------------------------->
    <template #default>
        <v-sheet rounded class="my-sheet cm-content pa-2 mt-2 mr-2"
          ref="contentEl">
          <!-- Материал открывается длинным документом: без кнопки «вверх»
               приходилось прокручивать страницу мышью. Показывается только
               когда прокрутка действительно есть. -->
          <v-btn
            v-show="canScrollUp"
            class="cm-scroll-top"
            color="primary"
            size="small"
            elevation="6"
            icon="mdi-arrow-up"
            :title="$t('courseManifest.toTop')"
            @click="scrollToTop"
          ></v-btn>
          <div id="iframe-container" :style="{ 'border-radius': '8px' }">
            <p v-if="error" class="has-text-danger px-3 py-2">{{ error }}</p>
            <!-- Высота материала держится в состоянии Vue, а не в
                 инлайновом onload. Регресс: атрибут onload писал высоту
                 прямо в DOM, а :style="{ height: '' }" при каждом
                 ре-рендере её затирал — материал схлопывался до 172px
                 после первого же изменения состояния и оставался таким. -->
            <iframe class="hello px-5" :srcdoc="contentHtml" ref="myIframe" name="iframe_a"
              @load="onFrameLoad" :style="{ height: frameHeight }"
              width="100%" scrolling="auto" :title="$t('courseManifest.material')">
            </iframe>

            <!-- <iframe class="hello px-5" :src="link" ref="myIframe" name="iframe_a" @load="updateDocHeight"  @load="onIframeLoaded" 
              onload="this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';" 
              :style="contentStyleObj" width="100%">
            </iframe> -->

          </div>
        </v-sheet>
    </template>
  </CoursePlayerLayout>
</template>

<!-- <script> -->
<script>

const apiUrl = import.meta.env.VITE_APP_URL;
import CoursePlayerLayout from "../components/ui/CoursePlayerLayout.vue";
import MaterialNotes from "./Course/MaterialNotes.vue";
import $api from "../api/httpClient";
import { unwrapResponse, unwrapArray, unwrapField, numericQuery } from "../api/envelope";
import { mapState, mapGetters } from "vuex";
import { toast } from "../composables/useToast";

export default {
  components: {
    MaterialNotes,
    CoursePlayerLayout,
  },

  props: {
    idEdit: {
      type: Number,
      required: true,
    },
    idCategory: {
      type: Number,
      required: true,
    },
    treeData: Object,
  },

  data() {

    return {

      titleauk: "",
      aukstructures: [],
      // id уже открытых модулей — для подсветки «посещённого» светло-серым.
      visitedIds: [],
      // Заголовок активного модуля для панели инструментов.
      activeTitle: "",
      // Имя последнего открытого файла: по нему «продолжить»
      // возвращает к нужному разделу, а не к началу курса.
      activeFile: null,
      // Документ пролистан до конца — урок засчитан как пройденный.
      isScrolledToEnd: false,
      canScrollUp: false,
      filterByCategoryAukstructures: [],
      categories: {},
      link: "",
      // Документ материала для iframe. Раньше iframe грузил файл напрямую
      // по ссылке, и вложенные ресурсы падали в 403 без подписи.
      contentHtml: "",
      firstId: '',
      // Высота кадра в состоянии: см. onFrameLoad.
      frameHeight: '320px',
      activeId: this.firstId,
      curAuk: this.item,

      showItems: true,
      showSearch: false,
      // Полноэкранный режим чтения материала.
      isFullscreen: false,
      isFavorite: false, // добавить в избранное
      //---------------- блок имитации загрузки--------
      loaded: false,
      loading: false,
      //----------- конец блока имитации загрузки--------
      //------------блок поиска---------------
      searchTerm: '',
      matchingFiles: [],
      aircraftTitle: '', //передавать в контроллер 
      //------------конец блока поиска---------------
      path: '',
      aircraft: '',
      //
      error: '',
      //---- for popup
      isLoading: false,
      overlay: false,
      //
      // избранное
      favorites: [],
      // загрузка iframe
      highlighted: [],
      currentHighlight: 0,
      iframe: null,  //удалить для подсветки
      IframeisLoaded: false,

    };
  },

  mounted() {
    document.addEventListener("keydown", this.onKeydown);

    //   const iframe = this.$refs.myIframe;
    //   const iframeDoc = iframe.contentDocument;
    // iframe.addEventListener('load', () => {
    //   this.highlightNodes(iframe);
    // });
    //const iframe = this.$refs.myIframe;

    //iframe.addEventListener('load', this.onIframeLoad);
    // window.addEventListener('message', (e) => {
    //   if (e.data && e.data.height) {
    //     const iframe = this.$refs.myIframe;
    //     iframe.style.height = `${e.data.height}px`;
    //     window.scrollTo(0, iframe.offsetTop);
    //   }
    // });
    // this.$refs.myIframe.addEventListener('load', this.onIframeLoad);

    // this.$refs.myIframe.addEventListener('load', () => {
    //   this.loadContent(item, hlHtml);
    // });
    // this.$refs.myIframe.addEventListener('load', () => {
    //   this.loadContent(itemId, highlightedNodes);
    // });

    this.getFavorites(); // загружаем избранное
    this.restoreVisited();
    this.$nextTick(this.bindScroll);

    //this.$store.dispatch("Course/fetchCourse",  { courseId: this.idEdit, categoryId: this.category_id });    
    if (!this.aircrafts) {
      this.$store.dispatch("Course/fetchAircrafts");
    }
    this.$store.dispatch("Course/fetchCourse", { course_id: this.idEdit, category_id: this.idCategory });
    this.$store.dispatch("Course/fetchCategory", this.idCategory);
    this.$store.dispatch("Course/fetchCategories");
    this.$store.dispatch("Course/fetchAircrafts");
    this.$store.dispatch("Course/fetchAircraft", this.aircraft);

    // --------------------------------загрузка левого меню-------------------------------

    //this.$store.dispatch("Course/fetchCourse", { course_id: this.idEdit, category_id: this.idCategory })
    $api
      .get(apiUrl + "/api/course", { params: numericQuery({ course_id: this.idEdit, category_id: this.idCategory }) })
      .then((response) => {
        const course = unwrapArray(response)[0] || {};
        // Раньше здесь был course.title без fallback: при пустом ответе
        // titleauk становился undefined, и шаблон падал на
        // titleauk.toUpperCase() с «Cannot read properties of undefined».
        this.titleauk = course.title || "";
        this.aukstructures = course.aukstructures || []; // получаем с backEnd все aukstruct для построения меню левого       

        // фильтруем по категориям
        // Регресс: categoryCode равен null, пока категория не загружена
        // (в списке курсов idCategory не передаётся вовсе), а вызов
        // .toString() на нём бросал TypeError. Из-за этого .then()
        // прерывался до присваивания path/aircraft, и в консоль падала
        // ошибка при каждом открытии курса.
        const code = this.categoryCode ? String(this.categoryCode).trim() : '';

        this.filterByCategoryAukstructures = code
          ? this.aukstructures.filter((aukstructure) => {
              return aukstructure.categories ? aukstructure.categories.includes(code) : true;
            }).sort((a, b) => a.id - b.id)
          : this.aukstructures;

        //this.getfirstauk(this.idEdit);
        //удалить возможно
        this.path = course.path; // папка с АУК        
        this.aircraft = course.aircraft_id;
      })
      .catch((err) => {
        console.error(err);
      });
  },

  beforeUnmount() {
    // Слушатель прокрутки жил на странице вечно и держал компонент,
    // из-за чего навигация копила их по одной на каждый переход.
    if (this.scrollHandler) {
      const el = this.$refs.contentEl?.$el ?? this.$refs.contentEl;
      el?.removeEventListener('scroll', this.scrollHandler);
      this.scrollHandler = null;
    }
  },

  beforeDestroy() {
    document.removeEventListener("keydown", this.onKeydown);
  },

  watch: {
    link(newLink, oldLink) {
      // Вызов метода loadContent для загрузки нового контента
    },
    activeId(newVal, oldVal) {
    },
    getFirstAukId: function (newVal, oldVal) {
      // вызываем метод getlink с новым значением
      if (newVal) {
        this.getlink(newVal);
      }
    }
  },
  computed: {
    ...mapState("Course", ["course", "category", "totalCourses", "aircrafts", "aircraft"]),
    ...mapGetters("Course", ["categories", "courses"]),

    idEditComputed() {
      return this.idEdit;
    },
    idCategoryComputed() {
      return this.idCategory;
    },
    // возвращаем из vuex category categoryCode по id
    categoryCode() {
      return this.category ? this.category.code : null;
    },
    /**
     * Разделы для CoursePlayerLayout: плоский массив aukstructures
     * превращается в список с уровнем вложенности и отметкой
     * прочтения (done / partial / todo).
     *
     * Раньше «посещённый» раздел красился бледно-серым, а у активного
     * менялся только цвет текста — в тёмной теме это было почти не
     * различимо, и прогресс курса нельзя было оценить целиком.
     */
    sections() {
      return this.aukstructures.map((item) => ({
        id: item.id,
        title: item.title,
        level: Math.min(3, Math.max(1, item.type)),
        /*
         * Материал ли это (type 3). Тема и раздел — заголовки структуры,
         * за ними файла нет: открывать их нечего. Признак передаётся
         * явно, потому что level зажат в 1..3 и сам по себе не отвечает
         * на вопрос «есть ли за узлом материал».
         */
        isMaterial: item.type === 3,
        state: this.sectionState(item.id),
      }));
    },

    /**
     * Прогресс курса в процентах — по разделам, а не по страницам:
     * длина материала не пропорциональна трудоёмкости.
     */
    courseProgress() {
      if (!this.sections.length) return 0;
      const weight = { done: 1, partial: 0.5, todo: 0 };
      const sum = this.sections.reduce((acc, s) => acc + weight[s.state], 0);
      return (sum / this.sections.length) * 100;
    },

    getFirstAukId() {
      // Используем метод find() для поиска первого элемента, у которого type равен 3
      // Побочных эффектов здесь нет намеренно: раньше computed дёргал getlink(),
      // а на него же подписан watch — материал грузился дважды.
      const firstAuk = this.aukstructures.find((item) => item.type === 3);
      return firstAuk ? firstAuk.id : null;
    },
  },
  // ----------------------------------------- методы --------------------------------------------------

  methods: {
    /**
     * Стиль узла дерева. Отступ и кегль зависят от уровня (type),
     * остальное — на CSS. Раньше кегль, курсор, цвет и opacity были
     * инлайновыми для каждого из 182 узлов, из-за чего любое изменение
     * состояния перерисовывало дерево целиком.
     */
    nodeStyle(item) {
      return {
        paddingLeft: `${Math.max(0, (item.type - 1) * 12 + 8)}px`,
        fontSize: `${30 - item.type * 4}px`,
      };
    },

    isVisited(id) {
      return this.visitedIds.includes(id);
    },

    /**
     * Состояние раздела: прочитан / начат / не начат.
     * «Начат» — раздел открыт прямо сейчас.
     */
    sectionState(id) {
      if (id === this.activeId) return "partial";
      return this.isVisited(id) ? "done" : "todo";
    },

    /**
     * Клик по узлу содержания.
     *
     * Открывается только материал (type 3): за темой и разделом файла
     * нет, и клик по ним раньше молча менял подсветку, создавая впечатление
     * перехода. Тема и раздел кликабельны не будут (см. CoursePlayerLayout).
     */
    onSelectSection(section) {
      if (!section?.isMaterial) return;

      const item = this.aukstructures.find((entry) => entry.id === section.id);
      if (!item) return;

      this.openModule(item);
    },

    /**
     * Соседний МАТЕРИАЛ относительно активного.
     *
     * Раньше переход шёл по всем узлам подряд, и «следующий» после темы
     * приводил на заголовок без файла. Ориентируемся на список материалов.
     */
    neighbour(offset) {
      const materials = this.sections.filter((s) => s.isMaterial);
      if (!materials.length) return null;

      const index = materials.findIndex((s) => s.id === this.activeId);
      if (index === -1) {
        // Активен заголовок (например, после смены курса): отправляем
        // к первому материалу в нужную сторону.
        return offset > 0 ? materials[0] : materials[materials.length - 1];
      }

      return materials[index + offset] ?? null;
    },

    goPrev() {
      const target = this.neighbour(-1);
      if (target) this.onSelectSection(target);
    },

    goNext() {
      const target = this.neighbour(1);
      if (target) this.onSelectSection(target);
    },

    /**
     * Полный экран. Выход по Escape — ожидаемое поведение, поэтому
     * вешаем слушатель и снимаем его при уходе со страницы: иначе
     * после ухода с курса Escape переставал закрывать что-либо и
     * оставался висеть на document.
     */
    toggleFullscreen() {
      this.isFullscreen = !this.isFullscreen;
    },

    onKeydown(event) {
      if (event.key !== "Escape") return;
      if (this.isFullscreen) this.isFullscreen = false;
    },

    /** Открытие модуля: контент + отметка «посещён». */
    openModule(item) {
      this.getlink(item.id);
    },

    markVisited(id) {
      if (!this.visitedIds.includes(id)) {
        this.visitedIds = [...this.visitedIds, id];
      }
      this.persistVisited();
    },

    /**
     * Процент урока: 100, если материал пролистали до конца.
     *
     * Отдельного «пройти урок» в материалах нет — документ один
     * большой HTML. Поэтому признак прохождения здесь такой же
     * практический, как у видеоуроков: доскроллил до конца.
     * Промежуточные значения не пишем: иначе один скролл создавал бы
     * запись в базе на каждом кадре.
     */
    lessonPercent(itemId) {
      if (this.isScrolledToEnd) {
        return 100;
      }

      return this.visitedIds.includes(itemId) ? 0 : 0;
    },

    /**
     * Отправляет серверный прогресс по уроку.
     *
     * Ошибка намеренно проглатывается: материал уже открыт, и падение
     * фоновой записи не должно превращаться в сообщение об ошибке на
     * странице. В localStorage копия остаётся как запасной вариант.
     */
    async saveProgress(itemId, percent) {
      try {
        await $api.post(`${apiUrl}/api/my/progress`, {
          course_id: Number(this.idEdit),
          lesson_id: Number(itemId),
          percent,
          last_file: this.activeFile || null,
        }, { optional: true });
      } catch (error) {
        // Прогресс не критичен для показа материала.
      }
    },

    /** Ключ хранилища — по курсу: прогресс разных курсов не смешивается. */
    visitedKey() {
      return `course-manifest-visited:${this.idEdit}`;
    },

    persistVisited() {
      try {
        window.localStorage.setItem(this.visitedKey(), JSON.stringify(this.visitedIds));
      } catch (error) {
        // localStorage бывает недоступен (приватный режим): прогресс
        // просто не сохранится, ломать страницу из-за этого не нужно.
      }
    },

    restoreVisited() {
      try {
        const raw = window.localStorage.getItem(this.visitedKey());
        const parsed = raw ? JSON.parse(raw) : [];
        this.visitedIds = Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        this.visitedIds = [];
      }
    },

    /**
     * Слушатель прокрутки.
     *
     * Через requestAnimationFrame: событие прокрутки приходит десятками
     * раз в секунду, а изменение реактивного состояния на каждом из них
     * перерисовывало дерево из 180+ узлов и пересоздавало iframe — отсюда
     * «низкая скорость рендеринга» и подвисание материала.
     *
     * Кнопка сделана через v-show, а не v-if: v-if уничтожал и создавал
     * iframe заново на каждом появлении кнопки, и высота материала
     * схлопывалась до пустой.
     */
    bindScroll() {
      const el = this.$refs.contentEl?.$el ?? this.$refs.contentEl;

      if (!el) return;

      let frame = null;

      this.scrollHandler = () => {
        if (frame !== null) return;

        frame = window.requestAnimationFrame(() => {
          frame = null;
          this.canScrollUp = el.scrollTop > 240;
          this.checkScrollEnd(el);
        });
      };

      el.addEventListener('scroll', this.scrollHandler, { passive: true });
      this.canScrollUp = el.scrollTop > 240;
    },

    /**
     * Подгоняет высоту кастра под содержимое материала.
     *
     * Раньше это делал инлайновый onload, писавший высоту прямо в DOM.
     * Vue на каждом ре-рендере перезаписывал :style пустым значением, и
     * материал «схлопывался» до размера пустого iframe.
     */
    onFrameLoad(event) {
      try {
        const doc = event?.target?.contentDocument;
        const height = doc?.body?.scrollHeight ?? 0;

        if (height > 0) {
          this.frameHeight = `${height + 20}px`;
        }
      } catch (error) {
        // srcdoc-документ бывает недоступен (sandbox) — оставляем прошлую высоту.
      }
    },

    /**
     * Дошёл ли пользователь до конца материала.
     *
     * Кадр подгоняется под высоту документа, поэтому «конец
     * документа» — это нижняя граница прокрутки страницы. Запас в
     * 40px нужен из-за дробной высоты и субпиксельной раскладки.
     * Отметка о выходе на конец отправляется один раз: при каждом
     * движении колеса запрос не уходит.
     */
    checkScrollEnd(el) {
      if (this.isScrolledToEnd) return;

      const distance = el.scrollHeight - el.scrollTop - el.clientHeight;

      if (distance <= 40) {
        this.isScrolledToEnd = true;
        this.saveProgress(this.activeId, 100);
      }
    },

    scrollToTop() {
      const el = this.$refs.contentEl?.$el ?? this.$refs.contentEl;
      el?.scrollTo({ top: 0, behavior: 'smooth' });
    },
    // onIframeLoad(event) {
    //   const iframe = event.currentTarget;
    //   iframe.style.height = (iframe.contentWindow.document.body.scrollHeight + 20) + 'px';
    // // Добавьте здесь свой код, который нужно выполнить после загрузки iframe
    //   if(this.showSearch) {
    //   const iframeDoc = iframe.contentDocument;
    //   setTimeout(() => {
    //   this.highlightNodes(iframeDoc);    
    // }, 100)

    //   setTimeout(() => {
    //   this.highlightNodes(iframeDoc);    
    // }, 500)
    //}
    //,
    replaceNodeContent(node, replacement) {
      const temp = document.createElement('div');
      temp.innerHTML = replacement;
      const newContent = temp.firstChild;
      const attrs = node.attributes;

      // Copy attributes from old node to new node
      for (let i = attrs.length - 1; i >= 0; i--) {
        const name = attrs.item(i).nodeName;
        const value = attrs.item(i).nodeValue;

        // Handle double-escaped values
        const parsedValue = JSON.parse('"' + value + '"');

        newContent.setAttribute(name, parsedValue);
      }

      // Replace old node with new node
      node.parentNode.replaceChild(newContent, node);
    },

    // onIframeLoaded() {
    //   //console.log('Iframe loaded')
    //   //this.isLoading = false;
    //   this.IframeisLoaded = true;
    //   const iframe = this.$refs.myIframe;
    //   iframe.style.height = (iframe.contentWindow.document.body.scrollHeight + 20) + 'px';
    // },

    // onIframeLoad() {
    // this.$refs.myIframe.height = this.$refs.myIframe.contentWindow.document.body.scrollHeight + 20;
    // },

    async getlink(item_id) {
      this.isLoading = true;
      this.activeId = item_id;
      this.activeTitle = this.aukstructures.find((item) => item.id === item_id)?.title ?? '';
      // Сбрасываем прошлую высоту: иначе до загрузки нового документа
      // в кадре пустота, а блок занимает размер предыдущего материала.
      this.frameHeight = '320px';
      // Новый материал открыт сверху, значит «вверх» идти некуда.
      // Без сброса кнопка оставалась видимой: событие scroll не
      // срабатывает, когда позиция и так уже 0.
      this.canScrollUp = false;
      this.isScrolledToEnd = false;
      // Отмечаем модуль посещённым сразу, а не после загрузки контента:
      // долгая загрузка не должна оставлять пункт «непосещённым».
      this.markVisited(item_id);
      this.saveProgress(item_id, 0);
      try {
        // Контент курсов отдаётся по подписи, а не по auth:sanctum:
        // вложенные ресурсы (CSS/JS/картинки) браузер запрашивает напрямую,
        // без заголовка Authorization. Раньше здесь подставлялся «голый»
        // путь api/private/КЛЕН/01/file.html — middleware отвечал 403,
        // и правая панель оставалась пустой.
        // Теперь getlink отдаёт составляющие пути, подпись получаем
        // отдельным запросом, а документ показываем через srcdoc с
        // <base href>: все относительные ресурсы наследуют токен
        // из query-строки, поэтому стили и картинки тоже загружаются.
        const response = await $api.get(apiUrl + "/api/getlink/" + item_id, {
          /*
           * stayOnPage: 403 здесь — это «группа не записана на ЭТОТ
           * материал», а не «нет прав на страницу». Без флага глобальный
           * перехватчик httpClient уводил со страницы целиком, и человек
           * терял список материалов из-за одного недоступного — тем
           * более что при записи на конкретные материалы (parent_id)
           * закрытых модулей в курсе много, а открыт ровно один.
           */
          stayOnPage: true,
        });
        const target = unwrapResponse(response) || {};
        const aircraft = (target.aircraft || "").trim();
        const auk = (target.auk || "").trim();
        const file = (target.file || "").trim();
        this.activeFile = file;

        if (!aircraft || !auk || !file) {
          this.contentHtml = "";
          this.error = "Для этого раздела не найден файл материала";
          return;
        }

        this.error = "";

        const sigResponse = await $api.get(apiUrl + "/api/private/signed-url", {
          params: { aircraft, auk },
        });
        const signed = unwrapResponse(sigResponse) || {};
        const base = signed.base || "";

        if (!base) {
          this.contentHtml = "";
          this.error = "Не удалось получить доступ к материалу курса";
          return;
        }

        // optional: файла может не оказаться на диске — это не ошибка API.
        const contentResponse = await $api.get(
          base + encodeURIComponent(file).replace(/%2F/g, "/"),
          { optional: true }
        );
        const html = typeof contentResponse.data === "string" ? contentResponse.data : "";
        this.contentHtml = html ? '<base href="' + base + '" />' + html : "";
        this.link = this.contentHtml;
        // Урок открыт и прочитан хотя бы частично: серверный прогресс
        // нужен, чтобы «продолжить обучение» работало на другом
        // устройстве, а не только в этом браузере.
        this.saveProgress(item_id, this.lessonPercent(item_id));
      } catch (error) {
        console.log(error);
        this.contentHtml = "";
        /*
         * Показываем причину от backend, а не общую фразу: при записи на
         * отдельные материалы отказ означает «этот материал не входит в
         * запись группы», и человеку нужно именно это знать, а не
         * «не удалось загрузить».
         */
        const status = error?.response?.status;
        const reason =
          error?.response?.data?.data?.message ||
          error?.response?.data?.error?.message ||
          "";

        if (status === 403 && reason) {
          this.error = reason;
        } else if (status === 403) {
          this.error = "Материал недоступен для вашей группы";
        } else {
          this.error = "Не удалось загрузить материал курса";
        }
      } finally {
        this.isLoading = false; // Установить isLoading в false после завершения загрузки
      }
    },

    // method(hlHtml) {
    //   //console.log(hltml, 'hltml')
    //   setTimeout(() => {
    //     const iframe = this.$refs.myIframe;
    //     const iframeDoc = iframe.contentDocument;
    //     const dom = new DOMParser().parseFromString(iframeDoc.body.innerHTML, 'text/html');
    //     //console.log(dom, "dom");        
    //     try {
    //       hlHtml.forEach((highlighted) => {
    //         const nodeToReplace = dom.evaluate(highlighted.originalXpath, dom, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
    //         //console.log(nodeToReplace, 'node')
    //         //     console.log(highlighted.originalXpath, 'оригинальный xpath');
    //         if (nodeToReplace) {
    //           const parentNode = nodeToReplace.parentNode;
    //           const highlightedTextCon = document.createElement('span');
    //           highlightedTextCon.style.backgroundColor = 'yellow';
    //           //console.log(highlighted.highlightedText, 'highlighted.highlightedText')
    //           parentNode.innerHTML = highlighted.highlightedText;
    //         }
    //       });
    //     } catch (e) {
    //     }
    //     // console.log(highlight,'highlight')
    //     iframeDoc.body.innerHTML = dom.documentElement.innerHTML;
    //     this.highlightNodes(iframeDoc);

    //   }, 1000) // делаем задержку чтобы на тяжелых страницах iframe прогрузился и успели раскраситься слова
    //   this.isLoading = false;
    // },

    loadContent(item, hlHtml) {
      this.isLoading = true;
      this.getlink(item); // загружаем в iframe содержимое файла статического html

      //await this.onIframeLoaded();

      //this.method(hlHtml)

      setTimeout(() => {
        const iframe = this.$refs.myIframe;
        const iframeDoc = iframe.contentDocument;
        const dom = new DOMParser().parseFromString(iframeDoc.body.innerHTML, 'text/html');
        try {
          hlHtml.forEach((highlighted) => {
            const nodeToReplace = dom.evaluate(highlighted.originalXpath, dom, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
            if (nodeToReplace) {
              const parentNode = nodeToReplace.parentNode;
              //const highlightedTextCon = document.createElement('span');
              //highlightedTextCon.style.backgroundColor = 'yellow';
              parentNode.innerHTML = highlighted.highlightedText;
            }
          });
        } catch (e) {
          console.log(e);
        }
        iframeDoc.body.innerHTML = dom.documentElement.innerHTML;
        this.highlightNodes(iframeDoc);

      }, 1000) // делаем задержку чтобы на тяжелых страницах iframe прогрузился и успели раскраситься слова
      this.isLoading = false;
    },

    ///-------------------------------------------скроллинг --------------------------------------------------------------

    highlightNodes(iframe) {
      this.highlighted = Array.from(iframe.querySelectorAll('.highlighted'));
      this.currentHighlight = 0
      this.scrollToHighlight(this.highlighted[this.currentHighlight]);
    },

    // scrollToHighlight(node) {
    //   //console.log(node,'node')      
    //   const iframe = this.$refs.myIframe
    //   const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;      
    //   //console.log(node.getBoundingClientRect().top, "node.getBoundingClientRect().top")
    //   //console.log(iframe.contentWindow.pageYOffset, "iframe.contentWindow.pageYOffset")    
    //   //console.log(iframe.contentDocument.documentElement.clientTop, "iframe.contentDocument.documentElement.clientTop")
    //   const topOffset = node.getBoundingClientRect().top + iframe.contentWindow.pageYOffset+300 - iframe.contentDocument.documentElement.clientTop
    //   //console.log(topOffset, 'topOffset')
    //    iframe.contentWindow.scrollTo({
    //      top: topOffset,
    //      behavior: 'smooth'
    //    })       
    // },
    scrollToHighlight(node) {
      const iframe = this.$refs.myIframe;
      const iframeDocument = iframe.contentWindow.document;
      const iframeRect = iframe.getBoundingClientRect();
      const nodeRect = node.getBoundingClientRect();
      const offsetTop = nodeRect.top - iframeRect.top + iframeDocument.documentElement.scrollTop;
      iframeDocument.documentElement.scrollTop = offsetTop;
    },

    scrollToNext() {
      if (this.highlighted.length > 0) {
        this.currentHighlight = (this.currentHighlight + 1) % this.highlighted.length;
        this.scrollToHighlight(this.highlighted[this.currentHighlight]);
      } else {
        this.currentHighlight = 0;
      }
    },
    scrollToPrev() {
      if (this.highlighted.length > 0) {
        this.currentHighlight = (this.currentHighlight - 1 + this.highlighted.length) % this.highlighted.length;
        this.scrollToHighlight(this.highlighted[this.currentHighlight]);
      } else {
        this.currentHighlight = 0;
      }
    },
    ///-----------------------------------конец-скроллинг--------------------------------------------------------------



    getfirstauk: function (course_id) {
      $api
        .get(apiUrl + "/api/getfirstauk/" + course_id)
        .then((response) => {
          this.firstId = unwrapResponse(response);
          this.getlink(this.firstId);
        });
    },
    // добавить в избранное
    /**
     * Панели не исключают друг друга.
     *
     * Регресс: переключатели гасили другие панели и само дерево
     * (toggleSearch ставил showItems = false). Из-за этого нажатие на
     * «лупу» или «сердечко» убирало список материалов, и казалось, что
     * кнопки сломаны. Теперь каждый управляет только своей панелью,
     * а дерево скрывает отдельная кнопка «Дерево».
     */
    toggleFavorite() {
      this.isFavorite = !this.isFavorite;
      if (this.isFavorite) this.getFavorites();
    },

    toggleList() {
      this.showItems = !this.showItems;
    },

    toggleSearch() {
      this.showSearch = !this.showSearch;
    },
    // onClickSearch() {
    //   this.loading = true

    //   setTimeout(() => {
    //     this.loading = false
    //     this.loaded = true
    //   }, 100)
    // },

    addToFavorites(course_id) {
      const activeTitle = this.aukstructures.find(item => item.id === course_id)?.title;
      $api
        .post(apiUrl + '/api/favorites/add', { course_id: course_id, title: activeTitle })
        .then(response => {
          // Обработка успешного добавления в избранное
          this.getFavorites(); // загружаем избранное
        })
        .catch(error => {
          // 400 — материал уже в избранном. Раньше ошибка уходила в
          // консоль и пользователь видел «ничего не произошло».
          const message =
            error?.response?.data?.error?.message || error?.response?.data?.error || "";

          // 400 здесь — «уже в избранном»: это не поломка, а
          // повторное действие, поэтому сообщение информационное.
          if (error?.response?.status === 400) {
            toast.info(
              typeof message === "string" && message
                ? message
                : this.$t("courseManifest.alreadyFavorite"),
            );
            return;
          }

          toast.error(this.$t("courseManifest.favoriteError"));
        });
    },

    getFavorites() {
      $api.get(apiUrl + '/api/favorites/').then((response) => {
        this.favorites = unwrapField(response, 'favorites') || [];
      });
    },
    /*
     * Удаление из избранного — обратимое, поэтому диалог подтверждения
     * здесь был бы лишним трением: достаточно одного нажатия, чтобы
     * вернуть. Вместо него сообщение с действием «Вернуть»: отмена
     * доступна прямо в тосте.
     *
     * Диалог остаётся для необратимых действий (см. AddClass.vue и
     * списки групп, категорий, курсов, пользователей).
     */
    removeFavorite(id) {
      const removed = this.favorites.find((f) => f.id === id)

      $api
        .delete(apiUrl + `/api/favorites/${id}`)
        .then(() => {
          this.getFavorites();

          toast.success(this.$t("courseManifest.favoriteRemoved"), {
            action: {
              label: this.$t("courseManifest.favoriteRestore"),
              handler: () => this.restoreFavorite(removed),
            },
          });
        });
    },

    /** Возврат удалённого избранного — обработчик кнопки в тосте. */
    restoreFavorite(removed) {
      if (!removed) return;

      $api
        .post(apiUrl + "/api/favorites/add", {
          course_id: removed.course_id,
          title: removed.title,
        })
        .then(() => this.getFavorites());
    },
    async search() {
      const formData = {
        //  query: this.searchTerm, path: this.path, aircraft: this.aircraft
        query: this.searchTerm, path: this.path, aircraft: this.aircraft

      }
      if (this.searchTerm.length < 3) {
        toast.warning(this.$t("courseManifest.searchMinChars"));
        return;
      }

      $api.post(apiUrl + `/api/search-files/`, formData)

        .then(response => {
          this.matchingFiles = unwrapArray(response);
        })
        .catch(error => {
          console.log(error);
        }).finally(() => {
          // this.alert = true;
        });
    },

  },
};
</script>

<style>
.highlighted {
  background-color: yellow;
  display: inline-block;
}

.v-card {
  border: 1px solid lightgrey;
}

/* --------------------------- панель инструментов --------------------------- */
/* Регресс: у иконок стоял width:130px (три штуки — 390px) внутри колонки
   4/12, а у разделителя .row-with-line был margin-bottom:-31px. Иконки
   вылезали за колонку, а отрицательный отступ наезжал блоками друг на
   друга — шапка ломалась. Здесь обычный flex без фиксированных ширин. */
.cm-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  padding: var(--sp-2) var(--sp-2);
  border-bottom: 1px solid var(--c-border);
  background: var(--c-surface-2);
  position: sticky;
  top: 0;
  z-index: 3;

  /*
    Блок заметок внутри панели не помещался и вылезал за её край:
    scrollWidth 320 при clientWidth 299.

    Причина — стандартная для flex: у детей по умолчанию
    min-width: auto, то есть они не сжимаются уже своего содержимого.
    Блок заметок — это v-textarea, у которой минимальная ширина
    считается по содержимому, поэтому на узкой боковой колонке панель
    разъезжалась. min-width: 0 разрешает сжатие до ширины колонки.
  */
  min-width: 0;
}

.cm-toolbar > * {
  min-width: 0;
  max-width: 100%;
}

.cm-toolbar__group {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
  min-width: 0;
}

.cm-toolbar__meta {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.cm-toolbar__current {
  font-size: 0.8125rem;
  color: var(--c-text-muted);
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Кнопка инструмента: обычная кнопка, а не v-icon с рамками.
   Состояние «включено» — фоном и цветом, а не обводкой. */
.cm-tool {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
  color: var(--c-text);
  cursor: pointer;
  font: inherit;
  font-size: 0.8125rem;
  line-height: 1;
  transition: background-color .15s ease-in-out, color .15s ease-in-out;
}

.cm-tool:hover:not(:disabled) {
  background: var(--c-surface-3);
  color: var(--c-primary);
}

/* Клавиатурная доступность: фокус должен быть виден, иначе
   пользователь не понимает, где он находится. */
.cm-tool:focus-visible {
  outline: 2px solid var(--c-primary);
  outline-offset: 1px;
}

.cm-tool--on {
  background: var(--c-primary);
  color: var(--c-on-primary);
}

.cm-tool:disabled {
  opacity: .45;
  cursor: default;
}

/* -------------------------------- избранное ------------------------------- */
.cm-favorites {
  margin: 8px 4px;
  padding: 8px 10px;
  border: 1px solid var(--c-border);
  border-radius: 6px;
  background: var(--c-surface);
}

.cm-favorites__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 600;
  font-size: 0.875rem;
  margin-bottom: 6px;
}

.cm-favorites__list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 180px;
  overflow-y: auto;
}

.cm-favorites__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 0;
  border-bottom: 1px solid var(--c-border);
}

.cm-favorites__title {
  font-size: 0.875rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cm-favorites__empty {
  margin: 0;
  font-size: 0.875rem;
  color: var(--c-text-muted);
}

/* ------------------------------ дерево материалов -------------------------- */
.my-sheet {
  opacity: 1;
}

/* Обе колонки прокручиваются независимо, шапка остаётся на месте.
   Регресс: у блока контента стоял только overflow:auto без высоты,
   из-за чего он разрастался на всю длину документа (4530px при
   scrollHeight = clientHeight) и не прокручивался вовсе — скроллилась
   вся страница, и кнопка «вверх» не могла найти свой контейнер. */
.cm-tree,
.cm-content {
  max-height: calc(100vh - 150px);
  overflow-y: auto;
  overflow-x: hidden;
}

/* На невысоких экранах оставляем хоть немного места под контент. */
@media (max-height: 620px) {
  .cm-tree,
  .cm-content {
    max-height: calc(100vh - 110px);
  }
}


/* ------------------------------ кнопка «вверх» ----------------------------- */
.cm-content-col {
  position: relative;
}

.cm-scroll-top {
  position: sticky;
  top: 12px;
  margin-left: auto;
  margin-right: 8px;
  z-index: 4;
  display: block;
}

.search-files__total-results {
  font-size: 0.875rem;
  color: var(--c-text-muted);
}


</style>
