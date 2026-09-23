"use strict";
(self["webpackChunk"] = self["webpackChunk"] || []).push([["resources_js_Pages_FileLoadSimple_vue"],{

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/FileLoadSimple.vue?vue&type=script&lang=js":
/*!***************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/FileLoadSimple.vue?vue&type=script&lang=js ***!
  \***************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _api_httpClient__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../api/httpClient */ "./resources/js/api/httpClient.js");

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  components: {},
  data: function data() {
    return {
      image: [],
      selectedFile: null,
      // текущий выбранный файл
      filesOrder: [] // очередь загрузки      
    };
  },

  methods: {
    onFileSelected: function onFileSelected(e) {
      var files = Array.from(e.target.files);
      //console.log(files);
      this.filesOrder = files.slice(); // текущая очередь    

      // this.image = files;
      // for (let item of files) {        
      //   this.sendFiles(item);
      // }
    },
    sendFiles: function sendFiles() {
      var _this = this;
      var formData = new FormData();
      for (var i = 0; i < this.image.length; i++) {
        var file = this.image[i];
        formData.append('image[' + i + ']', file);
      }
      _api_httpClient__WEBPACK_IMPORTED_MODULE_0__["default"].post("/api/files/add", formData,
      // разобраться почему авторизация не работает
      {
        headers: {
          'Content-Type': 'multipart/form-data'
          //          'Authorization':`Bearer ${localStorage.getItem('token')}`  // вынесен в httpClient.js
        }
      }).then(function (response) {
        _this.image = [];
      });
    }
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/FileLoadSimple.vue?vue&type=template&id=69b470bd":
/*!*******************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/FileLoadSimple.vue?vue&type=template&id=69b470bd ***!
  \*******************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* binding */ render)
/* harmony export */ });
/* harmony import */ var vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue */ "./node_modules/vue/dist/vue.esm-bundler.js");

var _hoisted_1 = /*#__PURE__*/(0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("h1", null, "Загрузка файлов", -1 /* HOISTED */);

function render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_v_file_input = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-file-input");
  var _component_v_btn = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-btn");
  return (0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, [_hoisted_1, (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_file_input, {
    label: "выбери файл",
    onChange: $options.onFileSelected,
    "show-size": "",
    counter: "",
    multiple: "",
    type: "file",
    modelValue: $data.image,
    "onUpdate:modelValue": _cache[0] || (_cache[0] = function ($event) {
      return $data.image = $event;
    }),
    enctype: "multipart/form-data"
  }, null, 8 /* PROPS */, ["onChange", "modelValue"]), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_btn, {
    onClick: $options.sendFiles,
    "class": "button is-light",
    type: "submit"
  }, {
    "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
      return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)("Отправить ")];
    }),
    _: 1 /* STABLE */
  }, 8 /* PROPS */, ["onClick"])], 64 /* STABLE_FRAGMENT */);
}

/***/ }),

/***/ "./resources/js/Pages/FileLoadSimple.vue":
/*!***********************************************!*\
  !*** ./resources/js/Pages/FileLoadSimple.vue ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _FileLoadSimple_vue_vue_type_template_id_69b470bd__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./FileLoadSimple.vue?vue&type=template&id=69b470bd */ "./resources/js/Pages/FileLoadSimple.vue?vue&type=template&id=69b470bd");
/* harmony import */ var _FileLoadSimple_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./FileLoadSimple.vue?vue&type=script&lang=js */ "./resources/js/Pages/FileLoadSimple.vue?vue&type=script&lang=js");
/* harmony import */ var _home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./node_modules/vue-loader/dist/exportHelper.js */ "./node_modules/vue-loader/dist/exportHelper.js");




;
const __exports__ = /*#__PURE__*/(0,_home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_2__["default"])(_FileLoadSimple_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"], [['render',_FileLoadSimple_vue_vue_type_template_id_69b470bd__WEBPACK_IMPORTED_MODULE_0__.render],['__file',"resources/js/Pages/FileLoadSimple.vue"]])
/* hot reload */
if (false) {}


/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (__exports__);

/***/ }),

/***/ "./resources/js/Pages/FileLoadSimple.vue?vue&type=script&lang=js":
/*!***********************************************************************!*\
  !*** ./resources/js/Pages/FileLoadSimple.vue?vue&type=script&lang=js ***!
  \***********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_FileLoadSimple_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"])
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_FileLoadSimple_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./FileLoadSimple.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/FileLoadSimple.vue?vue&type=script&lang=js");
 

/***/ }),

/***/ "./resources/js/Pages/FileLoadSimple.vue?vue&type=template&id=69b470bd":
/*!*****************************************************************************!*\
  !*** ./resources/js/Pages/FileLoadSimple.vue?vue&type=template&id=69b470bd ***!
  \*****************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_FileLoadSimple_vue_vue_type_template_id_69b470bd__WEBPACK_IMPORTED_MODULE_0__.render)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_FileLoadSimple_vue_vue_type_template_id_69b470bd__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./FileLoadSimple.vue?vue&type=template&id=69b470bd */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/FileLoadSimple.vue?vue&type=template&id=69b470bd");


/***/ })

}]);