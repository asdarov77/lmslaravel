<template>
  <v-progress-linear v-if="isLoading" color="primary" indeterminate></v-progress-linear>

  <v-card color="#f5f5f5">
    <v-row dense no-gutters>
      <v-col cols="3">
        <!-- <v-sheet rounded elevation="4" class="flex-child text-subtitle-1 pa-2 mt-1"> -->
        <v-sheet class="my-sheet cm-tree pa-2 mt-1" color="#f5f5f5">
          <!-- кнопки для поиска в тексте,добавления в избранное -->
          <v-sheet class="mx-auto mt-0 mb-3" elevation=4 rounded=lg>
            <div class="text-center" :style="{ fontSize: '20px' }">{{ titleauk.toUpperCase() }}</div>
          </v-sheet>

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
          <div class="cm-toolbar" role="toolbar" aria-label="Панель курса">
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
              rounded append-inner-icon="mdi-magnify" label="Поиск" @click:append-inner="search" @keyup.enter="search"
              hint="Введи искомый текст для поиска" clearable single-line>
            </v-text-field>

            <!-- ------------------------------------------------ -->
            <!-- результаты поиска -->

            <!--                                            рабочий вариант                           -->
            <!-- <div>
              <ul v-if="matchingFiles.length > 0" class=" ml-2 mr-2 search-files__total-results">Всего найдено: {{
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
              <ul v-if="matchingFiles.length > 0" class=" ml-2 mr-2 search-files__total-results">Всего найдено: {{
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
          <div v-if="showItems" v-for="(item, index) in aukstructures" :key="item.id"
            class="auk-node" :style="nodeStyle(item)" :data-type="item.type">
            <div v-if="index !== 0"
              class="auk-node__title"
              :class="{
                'auk-node__title--module': item.type === 3,
                'auk-node__title--active': item.id === activeId,
                'auk-node__title--visited': isVisited(item.id),
              }"
              :title="item.title"
              @click="item.type === 3 ? openModule(item) : ''"
            >
              {{ item.title }}
            </div>
          </div>
        </v-sheet>
      </v-col>

      <!-- ------------------------------------правый iframe ----------------------------------------------------------->

      <v-col cols="9" class="cm-content-col">
        <v-sheet rounded elevation="5" class="my-sheet cm-content pa-2 mt-2 mr-2"
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
              width="100%" scrolling="auto" title="Материал курса">
            </iframe>

            <!-- <iframe class="hello px-5" :src="link" ref="myIframe" name="iframe_a" @load="updateDocHeight"  @load="onIframeLoaded" 
              onload="this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';" 
              :style="contentStyleObj" width="100%">
            </iframe> -->

          </div>
        </v-sheet>
      </v-col>
    </v-row>
  </v-card>
</template>

<!-- <script> -->
<script export default>

const apiUrl = import.meta.env.VITE_APP_URL;
import $api from "../api/httpClient";
import { unwrapResponse, unwrapArray, unwrapField, numericQuery } from "../api/envelope";
import { mapState, mapGetters } from "vuex";
import { toast } from "../composables/useToast";

export default {
  components: {
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
        const response = await $api.get(apiUrl + "/api/getlink/" + item_id);
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
        this.error = "Не удалось загрузить материал курса";
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


    showthumb(item_id) {
      // У узла может не быть DOM-элемента (например, у первого элемента
      // списка рендер скрыт условием index !== 0) — раньше это давало
      // TypeError при наведении мыши.
      const node = document.getElementById(item_id);
      if (!node) return;
      node.style.border = "2px doted grey ";
      node.style.borderRadius = "4px";
      if (item_id !== this.activeId)
        node.style.background = "#D3D3D3";

      node.style.transform = "scale(1.03)";
    },
    hidethumb(item_id) {
      const node = document.getElementById(item_id);
      if (!node) return;
      node.style.border = "none";
      if (item_id !== this.activeId)
        node.style.background = "none";

      node.style.transform = "scale(1.0)";
    },


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
  padding: 6px 8px;
  border-bottom: 1px solid #d0d0d0;
  background: #fafafa;
  position: sticky;
  top: 0;
  z-index: 3;
}

.cm-toolbar__group {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
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
  color: #555;
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
  color: #444;
  cursor: pointer;
  font: inherit;
  font-size: 0.8125rem;
  line-height: 1;
  transition: background-color .15s ease-in-out, color .15s ease-in-out;
}

.cm-tool:hover:not(:disabled) {
  background: #e8eef7;
  color: #1a4d8f;
}

/* Клавиатурная доступность: фокус должен быть виден, иначе
   пользователь не понимает, где он находится. */
.cm-tool:focus-visible {
  outline: 2px solid #1a73e8;
  outline-offset: 1px;
}

.cm-tool--on {
  background: #1a73e8;
  color: #fff;
}

.cm-tool:disabled {
  opacity: .45;
  cursor: default;
}

/* -------------------------------- избранное ------------------------------- */
.cm-favorites {
  margin: 8px 4px;
  padding: 8px 10px;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  background: #fff;
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
  border-bottom: 1px solid #f0f0f0;
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
  color: #777;
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

.auk-node {
  margin: 2px 0;
  word-wrap: break-word;
}

.auk-node__title {
  display: inline-block;
  padding: 3px 6px;
  border-radius: 4px;
  line-height: 1.25;
  /* Разделы и подразделы — не кликабельны, поэтому без курсора руки. */
  cursor: default;
}

/* Раздел/подраздел приглушены, чтобы взгляд шёл к модулям. */
.auk-node[data-type="0"] .auk-node__title,
.auk-node[data-type="1"] .auk-node__title,
.auk-node[data-type="2"] .auk-node__title {
  color: #4b7a3a;
  opacity: .78;
  font-weight: 600;
}

.auk-node__title--module {
  cursor: pointer;
  color: #222;
}

.auk-node__title--module:hover {
  background: #e8eef7;
}

/* Уже открытый модуль — светло-серым: видно, где пользователь был,
   не спорит с подсветкой текущего пункта. */
.auk-node__title--visited {
  background: #ececec;
  color: #666;
}

.auk-node__title--module.auk-node__title--visited:hover {
  background: #dfe7f2;
}

/* Текущий модуль — синим, поверх посещённого. */
.auk-node__title--active {
  background: #1a73e8;
  color: #fff;
}

.auk-node__title--active:hover {
  background: #1667cf;
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
  color: #666;
}
</style>
