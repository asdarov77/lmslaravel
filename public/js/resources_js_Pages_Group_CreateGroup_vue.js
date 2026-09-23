"use strict";
(self["webpackChunk"] = self["webpackChunk"] || []).push([["resources_js_Pages_Group_CreateGroup_vue"],{

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/Group/CreateGroup.vue?vue&type=script&lang=js":
/*!******************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/Group/CreateGroup.vue?vue&type=script&lang=js ***!
  \******************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var vuex__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vuex */ "./node_modules/vuex/dist/vuex.esm-bundler.js");
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  //props: ["idEdit"],
  // props: 
  // {
  //   idEdit: {
  //     type: Number,
  //     required: true
  //   },
  // }, 
  data: function data() {
    return {
      groupname: '',
      groupdescription: '',
      //group: [], // группа с указанным ID из базы данных
      errors: []
    };
  },
  computed: _objectSpread(_objectSpread({}, (0,vuex__WEBPACK_IMPORTED_MODULE_0__.mapState)('User', ['allGroups', 'users'])), (0,vuex__WEBPACK_IMPORTED_MODULE_0__.mapGetters)('User', ['users', 'groups'])),
  created: function created() {

    //if(this.idEdit){
    //console.log(this.idEdit, 'id')
    //this.$store.dispatch('User/fetchGroup', this.idEdit)      

    //}   
  },
  methods: {
    submitForm: function submitForm() {
      var _this = this;
      if (!this.errors.length) {
        var formData = {
          groupname: this.groupname,
          groupdescription: this.groupdescription
        };
        this.$store.dispatch('User/createGroup', _objectSpread({}, formData)).then(function () {})["catch"](function (error) {
          console.error(error);
        })["finally"](function () {
          return _this.$router.back();
        });
      }

      // if(this.idEdit){        
      // //axios
      // $api        
      //   .put("/api/groups/" + this.idEdit, formData)
      //   .then((response) => {
      //     console.log(response);
      //      this.$router.push("/groups/list");
      //   })
      // }
      // else
      // $api
      // //axios 
      // .post("/api/groups/", formData)
      //   .then((response) => {
      //     console.log(response);
      //      this.$router.push("/groups/list");
      //   })
      //   .catch((error) => {
      //     if (error.response) {
      //       for (const property in error.response.data) {
      //         this.errors.push(
      //           `${property}: ${error.response.data[property]}`
      //         );
      //       }

      //       console.log(JSON.stringify(error.response.data));
      //     } else if (error.message) {
      //       this.errors.push("Something went wrong. Please try again");
      //       console.log(JSON.stringify(error));
      //     }
      //   });
    }
  }
  //},
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/Group/CreateGroup.vue?vue&type=template&id=05f8a75a":
/*!**********************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/Group/CreateGroup.vue?vue&type=template&id=05f8a75a ***!
  \**********************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* binding */ render)
/* harmony export */ });
/* harmony import */ var vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue */ "./node_modules/vue/dist/vue.esm-bundler.js");

function render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_v_toolbar_title = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-toolbar-title");
  var _component_v_toolbar = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-toolbar");
  var _component_v_text_field = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-text-field");
  var _component_v_container = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-container");
  var _component_v_form = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-form");
  var _component_v_card_text = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card-text");
  var _component_v_spacer = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-spacer");
  var _component_v_btn = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-btn");
  var _component_v_card_actions = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card-actions");
  var _component_v_card = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card");
  var _component_v_flex = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-flex");
  return (0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createBlock)(_component_v_flex, {
    xs12: "",
    sm8: "",
    md4: ""
  }, {
    "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
      return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_card, {
        "class": "elevation-12 mx-auto"
      }, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_toolbar, {
            color: "primary"
          }, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_toolbar_title, null, {
                "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
                  return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)("Добавить группу ")];
                }),
                _: 1 /* STABLE */
              })];
            }),

            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_card_text, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_form, {
                "on@submit": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withModifiers)($options.submitForm, ["prevent"])
              }, {
                "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
                  return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_text_field, {
                    label: "наименование группы",
                    type: "text",
                    modelValue: $data.groupname,
                    "onUpdate:modelValue": _cache[0] || (_cache[0] = function ($event) {
                      return $data.groupname = $event;
                    })
                  }, null, 8 /* PROPS */, ["modelValue"]), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_text_field, {
                    label: "описание группы",
                    type: "text",
                    modelValue: $data.groupdescription,
                    "onUpdate:modelValue": _cache[1] || (_cache[1] = function ($event) {
                      return $data.groupdescription = $event;
                    })
                  }, null, 8 /* PROPS */, ["modelValue"]), $data.errors.length ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createBlock)(_component_v_container, {
                    key: 0,
                    "class": "notification is-danger"
                  }, {
                    "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
                      return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("class=\"has-text-centered\"> "), ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(true), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderList)($data.errors, function (error) {
                        return (0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("p", {
                          key: error
                        }, (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(error), 1 /* TEXT */);
                      }), 128 /* KEYED_FRAGMENT */))];
                    }),

                    _: 1 /* STABLE */
                  })) : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true)];
                }),
                _: 1 /* STABLE */
              }, 8 /* PROPS */, ["on@submit"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_card_actions, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_spacer), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_btn, {
                onClick: $options.submitForm,
                color: "primary"
              }, {
                "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
                  return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)("Сохранить")];
                }),
                _: 1 /* STABLE */
              }, 8 /* PROPS */, ["onClick"])];
            }),
            _: 1 /* STABLE */
          })];
        }),

        _: 1 /* STABLE */
      })];
    }),

    _: 1 /* STABLE */
  });
}

/***/ }),

/***/ "./resources/js/Pages/Group/CreateGroup.vue":
/*!**************************************************!*\
  !*** ./resources/js/Pages/Group/CreateGroup.vue ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _CreateGroup_vue_vue_type_template_id_05f8a75a__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./CreateGroup.vue?vue&type=template&id=05f8a75a */ "./resources/js/Pages/Group/CreateGroup.vue?vue&type=template&id=05f8a75a");
/* harmony import */ var _CreateGroup_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./CreateGroup.vue?vue&type=script&lang=js */ "./resources/js/Pages/Group/CreateGroup.vue?vue&type=script&lang=js");
/* harmony import */ var _home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./node_modules/vue-loader/dist/exportHelper.js */ "./node_modules/vue-loader/dist/exportHelper.js");




;
const __exports__ = /*#__PURE__*/(0,_home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_2__["default"])(_CreateGroup_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"], [['render',_CreateGroup_vue_vue_type_template_id_05f8a75a__WEBPACK_IMPORTED_MODULE_0__.render],['__file',"resources/js/Pages/Group/CreateGroup.vue"]])
/* hot reload */
if (false) {}


/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (__exports__);

/***/ }),

/***/ "./resources/js/Pages/Group/CreateGroup.vue?vue&type=script&lang=js":
/*!**************************************************************************!*\
  !*** ./resources/js/Pages/Group/CreateGroup.vue?vue&type=script&lang=js ***!
  \**************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_CreateGroup_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"])
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_CreateGroup_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./CreateGroup.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/Group/CreateGroup.vue?vue&type=script&lang=js");
 

/***/ }),

/***/ "./resources/js/Pages/Group/CreateGroup.vue?vue&type=template&id=05f8a75a":
/*!********************************************************************************!*\
  !*** ./resources/js/Pages/Group/CreateGroup.vue?vue&type=template&id=05f8a75a ***!
  \********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_CreateGroup_vue_vue_type_template_id_05f8a75a__WEBPACK_IMPORTED_MODULE_0__.render)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_CreateGroup_vue_vue_type_template_id_05f8a75a__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../../node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!../../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./CreateGroup.vue?vue&type=template&id=05f8a75a */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/Group/CreateGroup.vue?vue&type=template&id=05f8a75a");


/***/ })

}]);