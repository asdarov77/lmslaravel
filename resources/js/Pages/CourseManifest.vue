<template>
  <v-progress-linear v-if="isLoading" color="primary" indeterminate></v-progress-linear>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css" />

  <v-card color="#f5f5f5">
    <v-row dense no-gutters>
      <v-col cols="3">
        <!-- <v-sheet rounded elevation="4" class="flex-child text-subtitle-1 pa-2 mt-1"> -->
        <v-sheet class="my-sheet pa-2 mt-1" color="#f5f5f5" :style="{ overflow: 'auto', 'overflow-y': 'auto' }">
          <!-- кнопки для поиска в тексте,добавления в избранное -->
          <v-sheet class="mx-auto mt-0 mb-3" elevation=4 rounded=lg>
            <div class="text-center" :style="{ fontSize: '20px' }">{{ titleauk.toUpperCase() }}</div>
          </v-sheet>
          <v-row no-gutters align="center ">
            <v-col cols="1" class="row-with-line"></v-col>
            <v-col cols="4" class="d-flex align-center">
              <v-icon size="x-large" class=" icon-list" :class="{ active: showItems }" @click="toggleList">{{ showItems ?
                'mdi-view-list' : 'mdi-view-list-outline'
              }}</v-icon>
              <v-icon size="x-large" class="icon-favorite" :class="{ active: isFavorite }" @click="toggleFavorite">{{
                isFavorite ? 'mdi-heart' : 'mdi-heart-outline' }}</v-icon>
              <v-icon size="x-large" class="icon-search" :class="{ active: showSearch }" @click="toggleSearch">{{
                showSearch ? 'mdi-magnify-minus-outline' : 'mdi-magnify' }}</v-icon>
            </v-col>
            <v-col cols="6" class="row-with-line "></v-col>
            <v-col cols="1" class="d-flex align-center">
              <v-icon size="x-large" @click="addToFavorites(activeId)" icon="mdi-playlist-star" class="addToFav"></v-icon>
            </v-col>
          </v-row>

          <v-row v-if="isFavorite" class="ml-1 mr-1">
            <ul>
              <li v-for="item in favorites" :key="item.id">
                {{ item.title }}
                <font-awesome-icon icon="times" @click="removeFavorite(item.course_id)" />
              </li>
            </ul>
          </v-row>

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
              <p v-else class="ml-5 mr-5 mt-1 search-files__no-results">Нет результатов</p>
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
              <p v-else class="ml-5 mr-5 mt-1 search-files__no-results">Нет результатов</p>
            </div>
            <!--                                            рабочий эксперимент                           -->



            <!-- ------------------------------------------------ -->
            <!-- результаты поиска -->



          </v-row>
          <!-- <div v-if="showItems" v-for="(item, index) in filterByCategoryAukstructures" :key="item.parent_id"> -->
          <div v-if="showItems" v-for="(item, index) in aukstructures" :key="item.id">
            <div class="mt-1 mx-3" :style="[
              item.type !== 3
                ? {
                  cursor: 'default',
                  opacity: '.7',
                  color: 'green',
                }
                : {
                  cursor: 'pointer',
                  //border: '2px solid firebrick',
                },
              {
                fontSize: `${-5 * item.type + 30}px`,
                //transform: `translate(${item.type * 20}px)`,
                paddingLeft: `${(item.type - 1) * 10}px`,
                display: 'inline-block',
                wordWrap: 'break-word',
              },
            ]">
              <div @mouseover="item.type === 3 ? showthumb(item.id) : ''" @mouseleave="hidethumb(item.id)" :id="item.id"
                @click="item.type === 3 ? getlink(item.id) : ''" v-if="index !== 0">
                {{ item.title }}
                <!-- {{ item.id }}--{{ item.title }} -->
              </div>
            </div>
          </div>
        </v-sheet>
      </v-col>

      <!-- ------------------------------------правый iframe ----------------------------------------------------------->

      <v-col cols="9">
        <v-sheet rounded elevation="5" class="my-sheet pa-2 mt-2 mr-2"
          :style="{ 'border-radius': '8px', overflow: 'auto', 'overflow-y': 'auto' }">
          <div id="iframe-container" :style="{ 'border-radius': '8px' }">
            <p v-if="error" class="has-text-danger px-3 py-2">{{ error }}</p>
            <iframe class="hello px-5" :srcdoc="contentHtml" ref="myIframe" name="iframe_a"
              onload="try{this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';}catch(e){}" :style="contentStyleObj"
              width="100%" scrolling="auto">
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
  <popup :alert="alert" :alertType="alertType" :snackbarText="snackbarText" :overlay="alert" :alertFalse="alertFalse">
  </popup>
</template>

<!-- <script> -->
<script export default>

const apiUrl = import.meta.env.VITE_APP_URL;
import $api from "../api/httpClient";
import { unwrapResponse, unwrapArray, unwrapField, numericQuery } from "../api/envelope";
import popup from "./Popup.vue";
import { mapState, mapGetters } from "vuex";
import { library } from '@fortawesome/fontawesome-svg-core';
import { faTimes } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
library.add(faTimes);

export default {
  components: {
    popup, FontAwesomeIcon

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
      filterByCategoryAukstructures: [],
      categories: {},
      link: "",
      // Документ материала для iframe. Раньше iframe грузил файл напрямую
      // по ссылке, и вложенные ресурсы падали в 403 без подписи.
      contentHtml: "",
      firstId: '',
      contentStyleObj: {
        height: "",
      },
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
      alert: false,
      alertType: "",
      overlay: false,
      snackbarText: "",
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
    //   console.log('слушатель highlightNodes')
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
        //console.log(response.data[0].aircraft_id, "air");
        const course = unwrapArray(response)[0] || {};
        // Раньше здесь был course.title без fallback: при пустом ответе
        // titleauk становился undefined, и шаблон падал на
        // titleauk.toUpperCase() с «Cannot read properties of undefined».
        this.titleauk = course.title || "";
        //console.log(response[0].title, "response");
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


  // beforeDestroy() {
  // this.$refs.myIframe.removeEventListener('load', this.onIframeLoaded);
  // this.isLoading===false;
  // },

  watch: {
    link(newLink, oldLink) {
      // console.log('Link has changed:', oldLink, '->', newLink);
      // Вызов метода loadContent для загрузки нового контента
    },
    activeId(newVal, oldVal) {
      //console.log("active",this.activeId)
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
    // onIframeLoad(event) {
    //   const iframe = event.currentTarget;
    //   iframe.style.height = (iframe.contentWindow.document.body.scrollHeight + 20) + 'px';
    // // Добавьте здесь свой код, который нужно выполнить после загрузки iframe
    // console.log('Iframe загружен!');
    //   if(this.showSearch) {
    //   const iframeDoc = iframe.contentDocument;
    //   console.log(iframeDoc)
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
      // console.log(node.parentNode.replaceChild(newContent, node), 'replace')
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
      //console.log('getlink')      
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
      } catch (error) {
        console.log(error);
        this.contentHtml = "";
        this.error = "Не удалось загрузить материал курса";
      } finally {
        this.isLoading = false; // Установить isLoading в false после завершения загрузки
      }
    },


    // method(hlHtml) {
    //   console.log('method')
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
    //       console.log(e);
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
      //console.log(hlHtml, "подсвеченный");

      //this.method(hlHtml)

      setTimeout(() => {
        const iframe = this.$refs.myIframe;
        const iframeDoc = iframe.contentDocument;
        const dom = new DOMParser().parseFromString(iframeDoc.body.innerHTML, 'text/html');
        //console.log(dom, "dom");        
        try {
          hlHtml.forEach((highlighted) => {
            const nodeToReplace = dom.evaluate(highlighted.originalXpath, dom, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
            //console.log(nodeToReplace, 'node')
            //     console.log(highlighted.originalXpath, 'оригинальный xpath');
            if (nodeToReplace) {
              const parentNode = nodeToReplace.parentNode;
              //const highlightedTextCon = document.createElement('span');
              //highlightedTextCon.style.backgroundColor = 'yellow';
              //console.log(highlighted.highlightedText, 'highlighted.highlightedText')
              parentNode.innerHTML = highlighted.highlightedText;
            }
          });
        } catch (e) {
          console.log(e);
        }
        // console.log(highlight,'highlight')
        iframeDoc.body.innerHTML = dom.documentElement.innerHTML;
        this.highlightNodes(iframeDoc);

      }, 1000) // делаем задержку чтобы на тяжелых страницах iframe прогрузился и успели раскраситься слова
      this.isLoading = false;
    },

    ///-------------------------------------------скроллинг --------------------------------------------------------------

    highlightNodes(iframe) {
      //console.log(iframe.querySelectorAll('.highlighted'),'iframe')
      this.highlighted = Array.from(iframe.querySelectorAll('.highlighted'));
      //console.log(this.highlighted, 'highlighted');
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
        //console.log("к следующему")
        this.currentHighlight = (this.currentHighlight + 1) % this.highlighted.length;
        this.scrollToHighlight(this.highlighted[this.currentHighlight]);
      } else {
        this.currentHighlight = 0;
      }
    },
    scrollToPrev() {
      if (this.highlighted.length > 0) {
        //console.log("к предыдующему")
        this.currentHighlight = (this.currentHighlight - 1 + this.highlighted.length) % this.highlighted.length;
        this.scrollToHighlight(this.highlighted[this.currentHighlight]);
      } else {
        this.currentHighlight = 0;
      }
    },
    ///-----------------------------------конец-скроллинг--------------------------------------------------------------


    showthumb(item_id) {
      // console.log(item_id)
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
      //console.log(item_id, 'вышел')
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
          //console.log(this.firstId,"this.firstId")
          this.getlink(this.firstId);
        });
    },
    // добавить в избранное
    toggleFavorite() {
      this.isFavorite = !this.isFavorite;
      this.showItems = false;
      this.showSearch = false;
      if (this.isFavorite == false) this.showItems = true;
    },
    toggleList() {
      //this.clearContent();
      this.showItems = !this.showItems;
      this.isFavorite = false;
      this.showSearch = false;
    },
    toggleSearch() {
      this.showSearch = !this.showSearch;
      this.isFavorite = false;
      this.showItems = false;
      if (this.showSearch == false) this.showItems = true;
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
      // console.log(activeTitle, "activeTitle")
      $api
        .post(apiUrl + '/api/favorites/add', { course_id: course_id, title: activeTitle })
        .then(response => {
          // Обработка успешного добавления в избранное
          this.getFavorites(); // загружаем избранное
        })
        .catch(error => {
          // Обработка ошибки
        });
    },

    getFavorites() {
      $api.get(apiUrl + '/api/favorites/').then((response) => {
        this.favorites = unwrapField(response, 'favorites') || [];
      });
    },
    removeFavorite(id) {
      $api
        .delete(apiUrl + `/api/favorites/${id}`).then(() => {
          this.getFavorites();
        });
    },



    alertFalse() {
      this.alert = false;
    },
    async search() {
      const formData = {
        //  query: this.searchTerm, path: this.path, aircraft: this.aircraft
        query: this.searchTerm, path: this.path, aircraft: this.aircraft

      }
      if (this.searchTerm.length < 3) {
        this.snackbarText = "..не меньше трех символов";
        this.alertType = "error";
        this.alert = true;
        return;
      }

      $api.post(apiUrl + `/api/search-files/`, formData)

        .then(response => {
          this.matchingFiles = unwrapArray(response);
          //         console.log(response, "кол-во")
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

.my-sheet {

  opacity: 1;
  /* потом удалить opacity,сделал чтобы стили не убирать для v-sheet */
  /* height: 800px; */
  /* Установите высоту для v-sheet, чтобы прокрутка сработала */
}

.icon-list::before {
  /* border-radius: 10%; */
  opacity: 0.5;
  transition: opacity 0.2s ease-in-out;
}

.icon-list:hover::before {
  opacity: 1;
}

/* .icon-favorite {
  position: relative;  
} */

.icon-favorite::before {
  /* border-radius: 10%; */
  opacity: 0.5;
  transition: opacity 0.2s ease-in-out;
}

.icon-favorite:hover::before {
  opacity: 1;
}

.icon-search::before {
  opacity: 0.5;
  transition: opacity 0.2s ease-in-out;
}

.icon-search:hover::before {
  opacity: 1;
}

.row-with-line {
  border-bottom: 2px solid green;
  margin-bottom: -31px;
}

.row-with-line .active {
  border-bottom: none !important;
}

.icon-list,
.icon-favorite,
.icon-search {

  border-bottom: 2px solid green;
  width: 130px;
  /* position: relative; */

}

.addToFav {
  /* width: 70px; */
  border-bottom: 2px solid green;
  border-top: 1px solid green;
  border-left: 1px solid green;
  border-right: 1px solid green;
  border-top-left-radius: 50%;
  border-top-right-radius: 10%;
}

.active {
  border-bottom: none;
  border-top: 2px solid green;
  border-left: 2px solid green;
  border-right: 2px solid green;
  border-top-left-radius: 15%;
  border-top-right-radius: 15%;
}

.search-files__total-results {
  font-size: 14px;
  color: #666;
}
</style>
