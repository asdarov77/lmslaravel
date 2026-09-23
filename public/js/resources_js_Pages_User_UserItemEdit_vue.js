"use strict";
(self["webpackChunk"] = self["webpackChunk"] || []).push([["resources_js_Pages_User_UserItemEdit_vue"],{

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserChperm.vue?vue&type=script&lang=js":
/*!****************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserChperm.vue?vue&type=script&lang=js ***!
  \****************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _components_ButtonGroup_vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../components/ButtonGroup.vue */ "./resources/js/components/ButtonGroup.vue");
/* harmony import */ var vuex__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! vuex */ "./node_modules/vuex/dist/vuex.esm-bundler.js");
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }


/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  components: {
    ButtonGroup: _components_ButtonGroup_vue__WEBPACK_IMPORTED_MODULE_0__["default"]
  },
  props: ["idEdit"],
  data: function data() {
    return {
      errors: [],
      value: []
    };
  },
  created: function created() {
    //console.log('mounted chperm', this.idEdit);
    this.$store.dispatch('User/fetchPermissions')["catch"](function (error) {
      return console.error(error);
    });
  },
  computed: _objectSpread({}, (0,vuex__WEBPACK_IMPORTED_MODULE_1__.mapState)('User', ['allPermissions', 'user'])),
  methods: {
    submitForm: function submitForm() {
      this.$emit('submitForm');
    },
    cancelBtn: function cancelBtn() {
      this.$emit('cancelBtn');
    }
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=script&lang=js":
/*!******************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=script&lang=js ***!
  \******************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _api_httpClient__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../api/httpClient */ "./resources/js/api/httpClient.js");
/* harmony import */ var vuex__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! vuex */ "./node_modules/vuex/dist/vuex.esm-bundler.js");
/* harmony import */ var _User_russia_min_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../User/russia.min.json */ "./resources/js/Pages/User/russia.min.json");
/* harmony import */ var _User_sfere_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../User/sfere.json */ "./resources/js/Pages/User/sfere.json");
/* harmony import */ var _vueform_multiselect__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @vueform/multiselect */ "./node_modules/@vueform/multiselect/dist/multiselect.mjs");
/* harmony import */ var _UserChperm_vue__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./UserChperm.vue */ "./resources/js/Pages/User/UserChperm.vue");
/* harmony import */ var _components_ButtonGroup_vue__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../components/ButtonGroup.vue */ "./resources/js/components/ButtonGroup.vue");
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }




//import UserLearning from "./UserLearning.vue";
//import jsonData from "../User/city.json";



/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  components: {
    Multiselect: _vueform_multiselect__WEBPACK_IMPORTED_MODULE_5__["default"],
    UserChperm: _UserChperm_vue__WEBPACK_IMPORTED_MODULE_3__["default"],
    ButtonGroup: _components_ButtonGroup_vue__WEBPACK_IMPORTED_MODULE_4__["default"]
  },
  props: {
    idEdit: {
      type: Number,
      required: true
    }
  },
  data: function data() {
    return {
      dialog: false,
      dialogReg: false,
      errors: [],
      options: _User_russia_min_json__WEBPACK_IMPORTED_MODULE_1__,
      sfereOptions: _User_sfere_json__WEBPACK_IMPORTED_MODULE_2__,
      value: []
    };
  },
  computed: _objectSpread({}, (0,vuex__WEBPACK_IMPORTED_MODULE_6__.mapState)('User', ['allGroups', 'user'])),
  created: function created() {
    this.$store.dispatch('User/fetchUser', this.idEdit);
  },
  methods: {
    cancelBtnRegistration: function cancelBtnRegistration() {
      console.log('cancelBtnRegistration');
      // this.$store
      //     .dispatch('Course/categories')
      //     .catch(error => console.error(error))
      this.$store.dispatch('User/fetchUser', this.idEdit);
      this.dialogReg = false;
    },
    cancelBtnHead: function cancelBtnHead() {
      this.$router.back();
    },
    dialogFalse: function dialogFalse() {
      this.dialog = false;
    },
    cancelBtn: function cancelBtn() {
      console.log('cancelBtn work');
      this.$store.dispatch('User/fetchUser', this.idEdit);
      this.dialog = false;
    },
    submitForm: function submitForm() {
      var _this = this;
      if (!this.errors.length) {
        var formData = {
          permission_id: this.user.permissions
        };

        //  let urlToUp = "/api/user/chperm/" + this.idEdit;

        //  $api
        //    .put(urlToUp, formData)
        //    .then((response) => {
        //     console.log(formData);            
        //      //this.$router.push("/user/list");
        //      // this.$router.back();
        //    })
        //    .catch((error) => {
        //      if (error.response) {
        //        for (const property in error.response.data) {
        //          this.errors.push(
        //            `${property}: ${error.response.data[property]}`
        //          );
        //        }

        //        console.log(JSON.stringify(error.response.data));
        //      } else if (error.message) {
        //        this.errors.push("Something went wrong. Please try again");

        //        console.log(JSON.stringify(error));
        //      }
        //    });
      }

      // if (!this.errors.length) {
      //   const formData = {
      //     fio: this.user.fio,
      //     role: this.user.role,
      //     phonenumber: this.user.phonenumber,
      //     city: this.user.city,
      //     country: this.user.country,
      //     organization: this.user.organization,
      //     position: this.user.position,
      //     rank: this.user.rank,
      //     spfere: this.user.spfere,
      //     specialization: this.user.specialization,
      //     group_id: this.user.group_id,
      //     permission_id: this.user.permissions,
      //   };

      this.$store
      //        .dispatch('User/updateUser', {id:this.idEdit,data:this.users})
      .dispatch('User/updateUser', {
        id: this.idEdit,
        data: this.user
      }).then(function () {})["catch"](function (error) {
        console.error(error);
      })["finally"](function () {
        return _this.$router.back();
      });
    } // },
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/components/ButtonGroup.vue?vue&type=script&lang=js":
/*!*****************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/components/ButtonGroup.vue?vue&type=script&lang=js ***!
  \*****************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  name: "ButtonGroup",
  methods: {
    submitForm: function submitForm() {
      this.$emit('submitForm');
    },
    cancelBtn: function cancelBtn() {
      this.$emit('cancelBtn');
    }
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserChperm.vue?vue&type=template&id=06fdb66a":
/*!********************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserChperm.vue?vue&type=template&id=06fdb66a ***!
  \********************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* binding */ render)
/* harmony export */ });
/* harmony import */ var vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue */ "./node_modules/vue/dist/vue.esm-bundler.js");

function render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_v_card_title = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card-title");
  var _component_v_select = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-select");
  var _component_v_container = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-container");
  var _component_v_card_text = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card-text");
  var _component_ButtonGroup = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("ButtonGroup");
  var _component_v_card = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card");
  return (0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createBlock)(_component_v_card, {
    "class": "elevation-12 mx-auto",
    style: {
      "min-width": "600px"
    }
  }, {
    "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
      return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_card_title, null, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)("Назначить разрешения пользователя " + (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.user.fio), 1 /* TEXT */)];
        }),

        _: 1 /* STABLE */
      }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_card_text, null, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_select, {
            variant: "solo",
            dense: "",
            label: "Разрешения",
            type: "text",
            items: _ctx.allPermissions,
            modelValue: _ctx.user.permissions,
            "onUpdate:modelValue": _cache[0] || (_cache[0] = function ($event) {
              return _ctx.user.permissions = $event;
            }),
            multiple: "",
            "item-value": "id",
            "item-title": "name"
          }, null, 8 /* PROPS */, ["items", "modelValue"]), $data.errors.length ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createBlock)(_component_v_container, {
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
          })) : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)((0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.allPermissions), 1 /* TEXT */)];
        }),

        _: 1 /* STABLE */
      }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_ButtonGroup, {
        onSubmitForm: $options.submitForm,
        onCancelBtn: $options.cancelBtn
      }, null, 8 /* PROPS */, ["onSubmitForm", "onCancelBtn"])];
    }),
    _: 1 /* STABLE */
  });
}

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=template&id=726dc833":
/*!**********************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=template&id=726dc833 ***!
  \**********************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* binding */ render)
/* harmony export */ });
/* harmony import */ var vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue */ "./node_modules/vue/dist/vue.esm-bundler.js");

var _hoisted_1 = {
  "class": "d-flex justify-space-between"
};
function render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_v_card_title = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card-title");
  var _component_v_text_field = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-text-field");
  var _component_v_col = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-col");
  var _component_v_select = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-select");
  var _component_v_row = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-row");
  var _component_Multiselect = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("Multiselect");
  var _component_v_btn = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-btn");
  var _component_ButtonGroup = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("ButtonGroup");
  var _component_v_container = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-container");
  var _component_UserChperm = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("UserChperm");
  var _component_v_dialog = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-dialog");
  var _component_v_card = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card");
  return (0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_card, null, {
    "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
      return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_card_title, {
        "class": "first-row"
      }, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)("Редактирование пользователя " + (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.user.fio), 1 /* TEXT */)];
        }),

        _: 1 /* STABLE */
      }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_row, {
        "class": "first-row"
      }, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_text_field, {
                variant: "solo",
                label: "ФИО",
                type: "text",
                modelValue: _ctx.user.fio,
                "onUpdate:modelValue": _cache[0] || (_cache[0] = function ($event) {
                  return _ctx.user.fio = $event;
                })
              }, null, 8 /* PROPS */, ["modelValue"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_select, {
                variant: "solo",
                modelValue: _ctx.user.role,
                "onUpdate:modelValue": _cache[1] || (_cache[1] = function ($event) {
                  return _ctx.user.role = $event;
                }),
                dense: "",
                label: "Роль",
                items: ['Администратор', 'Инспектор', 'Обучаемый']
              }, null, 8 /* PROPS */, ["modelValue"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_text_field, {
                variant: "solo",
                id: "phonenumber",
                name: "phonenumber",
                label: "Телефон",
                modelValue: _ctx.user.phonenumber,
                "onUpdate:modelValue": _cache[2] || (_cache[2] = function ($event) {
                  return _ctx.user.phonenumber = $event;
                }),
                type: "text"
              }, null, 8 /* PROPS */, ["modelValue"])];
            }),
            _: 1 /* STABLE */
          })];
        }),

        _: 1 /* STABLE */
      }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_row, null, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_Multiselect, {
                id: "city",
                modelValue: _ctx.user.city,
                "onUpdate:modelValue": _cache[3] || (_cache[3] = function ($event) {
                  return _ctx.user.city = $event;
                }),
                selected: $data.value,
                options: $data.options,
                placeholder: "Город",
                searchable: true,
                trackBy: "city",
                label: "city",
                noResultsText: "нет такого города"
              }, null, 8 /* PROPS */, ["modelValue", "selected", "options"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_text_field, {
                variant: "solo",
                id: "country",
                name: "country",
                label: "Страна",
                modelValue: _ctx.user.country,
                "onUpdate:modelValue": _cache[4] || (_cache[4] = function ($event) {
                  return _ctx.user.country = $event;
                }),
                type: "text"
              }, null, 8 /* PROPS */, ["modelValue"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_text_field, {
                variant: "solo",
                id: "organization",
                name: "organization",
                label: "Организация",
                modelValue: _ctx.user.organization,
                "onUpdate:modelValue": _cache[5] || (_cache[5] = function ($event) {
                  return _ctx.user.organization = $event;
                }),
                type: "text"
              }, null, 8 /* PROPS */, ["modelValue"])];
            }),
            _: 1 /* STABLE */
          })];
        }),

        _: 1 /* STABLE */
      }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_row, null, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_text_field, {
                variant: "solo",
                id: "position",
                name: "position",
                label: "Должность",
                modelValue: _ctx.user.position,
                "onUpdate:modelValue": _cache[6] || (_cache[6] = function ($event) {
                  return _ctx.user.position = $event;
                }),
                type: "text"
              }, null, 8 /* PROPS */, ["modelValue"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_select, {
                variant: "solo",
                modelValue: _ctx.user.rank,
                "onUpdate:modelValue": _cache[7] || (_cache[7] = function ($event) {
                  return _ctx.user.rank = $event;
                }),
                dense: "",
                label: "Воинское звание",
                items: ['капитан', 'майор', 'старший лейтенант', 'лейтенант']
              }, null, 8 /* PROPS */, ["modelValue"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_select, {
                variant: "solo",
                modelValue: _ctx.user.spfere,
                "onUpdate:modelValue": _cache[8] || (_cache[8] = function ($event) {
                  return _ctx.user.spfere = $event;
                }),
                dense: "",
                label: "Сфера деятельности",
                items: $data.sfereOptions
              }, null, 8 /* PROPS */, ["modelValue", "items"])];
            }),
            _: 1 /* STABLE */
          })];
        }),

        _: 1 /* STABLE */
      }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_row, null, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_text_field, {
                variant: "solo",
                id: "specialization",
                name: "specialization",
                label: "Специализация",
                modelValue: _ctx.user.specialization,
                "onUpdate:modelValue": _cache[9] || (_cache[9] = function ($event) {
                  return _ctx.user.specialization = $event;
                }),
                type: "text"
              }, null, 8 /* PROPS */, ["modelValue"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col, null, {
            "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
              return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_select, {
                variant: "solo",
                label: "Группа",
                type: "text",
                items: _ctx.allGroups,
                modelValue: _ctx.user.group_id,
                "onUpdate:modelValue": _cache[10] || (_cache[10] = function ($event) {
                  return _ctx.user.group_id = $event;
                }),
                "item-value": "id",
                "item-title": "groupname"
              }, null, 8 /* PROPS */, ["items", "modelValue"])];
            }),
            _: 1 /* STABLE */
          }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_col)];
        }),
        _: 1 /* STABLE */
      }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", _hoisted_1, [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" <div class=\"d-flex justify-space-around\"> "), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" <v-btn\n        tile\n        color=\"green\"\n        class=\"my-3\"\n        :to=\"{\n          name: 'user.chroll',\n          params: {\n            idEdit: user.id,\n          },\n        }\"\n      >\n        Роль</v-btn\n      > "), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", null, [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_btn, {
        style: {
          "margin-left": "10px"
        },
        tile: "",
        color: "green",
        "class": "my-3",
        onClick: _cache[11] || (_cache[11] = function ($event) {
          return $data.dialog = true;
        })
      }, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)(" Разрешение ")];
        }),
        _: 1 /* STABLE */
      })]), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_ButtonGroup, {
        onSubmitForm: $options.submitForm,
        onCancelBtn: $options.cancelBtnHead
      }, null, 8 /* PROPS */, ["onSubmitForm", "onCancelBtn"])]), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" <v-select\n      label=\"Разрешения\"\n      type=\"text\"\n      :items=\"permissions\"\n      v-model=\"user.permissions\"\n      multiple\n      item-value=\"id\"\n      item-title=\"name\"\n      empty-option\n    ></v-select> "), $data.errors.length ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createBlock)(_component_v_container, {
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
      })) : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_dialog, {
        modelValue: $data.dialog,
        "onUpdate:modelValue": _cache[12] || (_cache[12] = function ($event) {
          return $data.dialog = $event;
        })
      }, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_UserChperm, {
            idEdit: _ctx.user.id,
            onSubmitForm: $options.dialogFalse,
            onCancelBtn: $options.cancelBtn
          }, null, 8 /* PROPS */, ["idEdit", "onSubmitForm", "onCancelBtn"])];
        }),
        _: 1 /* STABLE */
      }, 8 /* PROPS */, ["modelValue"]), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" <v-dialog v-model=\"dialogReg\">\n      <UserLearning :idEdit=\"user.id\" @submitForm=\"this.dialogReg = false\" @cancelBtn=\"cancelBtnRegistration\"></UserLearning>\n    </v-dialog> ")];
    }),
    _: 1 /* STABLE */
  }), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)((0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.user), 1 /* TEXT */)], 64 /* STABLE_FRAGMENT */);
}

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/components/ButtonGroup.vue?vue&type=template&id=7ecaea5c":
/*!*********************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/components/ButtonGroup.vue?vue&type=template&id=7ecaea5c ***!
  \*********************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* binding */ render)
/* harmony export */ });
/* harmony import */ var vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue */ "./node_modules/vue/dist/vue.esm-bundler.js");

function render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_v_spacer = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-spacer");
  var _component_v_btn = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-btn");
  var _component_v_card_actions = (0,vue__WEBPACK_IMPORTED_MODULE_0__.resolveComponent)("v-card-actions");
  return (0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createBlock)(_component_v_card_actions, null, {
    "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
      return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_spacer), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_btn, {
        onClick: $options.cancelBtn,
        color: "error"
      }, {
        "default": (0,vue__WEBPACK_IMPORTED_MODULE_0__.withCtx)(function () {
          return [(0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)("Отмена")];
        }),
        _: 1 /* STABLE */
      }, 8 /* PROPS */, ["onClick"]), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createVNode)(_component_v_btn, {
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
  });
}

/***/ }),

/***/ "./node_modules/css-loader/dist/cjs.js??clonedRuleSet-9.use[1]!./node_modules/vue-loader/dist/stylePostLoader.js!./node_modules/postcss-loader/dist/cjs.js??clonedRuleSet-9.use[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css":
/*!*******************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js??clonedRuleSet-9.use[1]!./node_modules/vue-loader/dist/stylePostLoader.js!./node_modules/postcss-loader/dist/cjs.js??clonedRuleSet-9.use[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css ***!
  \*******************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../../node_modules/css-loader/dist/runtime/api.js */ "./node_modules/css-loader/dist/runtime/api.js");
/* harmony import */ var _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_0__);
// Imports

var ___CSS_LOADER_EXPORT___ = _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_0___default()(function(i){return i[1]});
// Module
___CSS_LOADER_EXPORT___.push([module.id, "\n.v-card {\n  padding: 10px;\n}\n.first-row {\n  margin-top: 20px;\n}\n.multiselect {\n  background-color: #f4f4f4;\n  height: 56px;\n  border: none;\n  box-shadow: 0 3px 1px -2px var(--v-shadow-key-umbra-opacity, rgba(0, 0, 0, 0.2)), 0 2px 2px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.14)), 0 1px 5px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.12));\n}\n.multiselect.is-active {\n  box-shadow: 0 3px 1px -2px var(--v-shadow-key-umbra-opacity, rgba(0, 0, 0, 0.2)), 0 2px 2px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.14)), 0 1px 5px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.12));\n}\n.multiselect-search {\n  background-color: #f4f4f4;\n}\n.multiselect-placeholder {\n  font-weight: normal;\n  color: black;\n}\n.v-field {\n  background-color: #f4f4f4;\n}\n.multiselect-option.is-selected, .multiselect-option.is-selected.is-pointed {\n  background: rgba(0, 0, 0, 0.16);\n}\n.multiselect-dropdown ul li span {\n  color: black;\n}\n", ""]);
// Exports
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (___CSS_LOADER_EXPORT___);


/***/ }),

/***/ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js??clonedRuleSet-9.use[1]!./node_modules/vue-loader/dist/stylePostLoader.js!./node_modules/postcss-loader/dist/cjs.js??clonedRuleSet-9.use[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css":
/*!***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js??clonedRuleSet-9.use[1]!./node_modules/vue-loader/dist/stylePostLoader.js!./node_modules/postcss-loader/dist/cjs.js??clonedRuleSet-9.use[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css ***!
  \***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! !../../../../node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js */ "./node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _node_modules_css_loader_dist_cjs_js_clonedRuleSet_9_use_1_node_modules_vue_loader_dist_stylePostLoader_js_node_modules_postcss_loader_dist_cjs_js_clonedRuleSet_9_use_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserItemEdit_vue_vue_type_style_index_1_id_726dc833_lang_css__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! !!../../../../node_modules/css-loader/dist/cjs.js??clonedRuleSet-9.use[1]!../../../../node_modules/vue-loader/dist/stylePostLoader.js!../../../../node_modules/postcss-loader/dist/cjs.js??clonedRuleSet-9.use[2]!../../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css */ "./node_modules/css-loader/dist/cjs.js??clonedRuleSet-9.use[1]!./node_modules/vue-loader/dist/stylePostLoader.js!./node_modules/postcss-loader/dist/cjs.js??clonedRuleSet-9.use[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css");

            

var options = {};

options.insert = "head";
options.singleton = false;

var update = _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default()(_node_modules_css_loader_dist_cjs_js_clonedRuleSet_9_use_1_node_modules_vue_loader_dist_stylePostLoader_js_node_modules_postcss_loader_dist_cjs_js_clonedRuleSet_9_use_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserItemEdit_vue_vue_type_style_index_1_id_726dc833_lang_css__WEBPACK_IMPORTED_MODULE_1__["default"], options);



/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_css_loader_dist_cjs_js_clonedRuleSet_9_use_1_node_modules_vue_loader_dist_stylePostLoader_js_node_modules_postcss_loader_dist_cjs_js_clonedRuleSet_9_use_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserItemEdit_vue_vue_type_style_index_1_id_726dc833_lang_css__WEBPACK_IMPORTED_MODULE_1__["default"].locals || {});

/***/ }),

/***/ "./resources/js/Pages/User/UserChperm.vue":
/*!************************************************!*\
  !*** ./resources/js/Pages/User/UserChperm.vue ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _UserChperm_vue_vue_type_template_id_06fdb66a__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./UserChperm.vue?vue&type=template&id=06fdb66a */ "./resources/js/Pages/User/UserChperm.vue?vue&type=template&id=06fdb66a");
/* harmony import */ var _UserChperm_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./UserChperm.vue?vue&type=script&lang=js */ "./resources/js/Pages/User/UserChperm.vue?vue&type=script&lang=js");
/* harmony import */ var _home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./node_modules/vue-loader/dist/exportHelper.js */ "./node_modules/vue-loader/dist/exportHelper.js");




;
const __exports__ = /*#__PURE__*/(0,_home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_2__["default"])(_UserChperm_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"], [['render',_UserChperm_vue_vue_type_template_id_06fdb66a__WEBPACK_IMPORTED_MODULE_0__.render],['__file',"resources/js/Pages/User/UserChperm.vue"]])
/* hot reload */
if (false) {}


/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (__exports__);

/***/ }),

/***/ "./resources/js/Pages/User/UserItemEdit.vue":
/*!**************************************************!*\
  !*** ./resources/js/Pages/User/UserItemEdit.vue ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _UserItemEdit_vue_vue_type_template_id_726dc833__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./UserItemEdit.vue?vue&type=template&id=726dc833 */ "./resources/js/Pages/User/UserItemEdit.vue?vue&type=template&id=726dc833");
/* harmony import */ var _UserItemEdit_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./UserItemEdit.vue?vue&type=script&lang=js */ "./resources/js/Pages/User/UserItemEdit.vue?vue&type=script&lang=js");
/* harmony import */ var _UserItemEdit_vue_vue_type_style_index_1_id_726dc833_lang_css__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css */ "./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css");
/* harmony import */ var _home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./node_modules/vue-loader/dist/exportHelper.js */ "./node_modules/vue-loader/dist/exportHelper.js");




;



const __exports__ = /*#__PURE__*/(0,_home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_3__["default"])(_UserItemEdit_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"], [['render',_UserItemEdit_vue_vue_type_template_id_726dc833__WEBPACK_IMPORTED_MODULE_0__.render],['__file',"resources/js/Pages/User/UserItemEdit.vue"]])
/* hot reload */
if (false) {}


/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (__exports__);

/***/ }),

/***/ "./resources/js/components/ButtonGroup.vue":
/*!*************************************************!*\
  !*** ./resources/js/components/ButtonGroup.vue ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _ButtonGroup_vue_vue_type_template_id_7ecaea5c__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./ButtonGroup.vue?vue&type=template&id=7ecaea5c */ "./resources/js/components/ButtonGroup.vue?vue&type=template&id=7ecaea5c");
/* harmony import */ var _ButtonGroup_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./ButtonGroup.vue?vue&type=script&lang=js */ "./resources/js/components/ButtonGroup.vue?vue&type=script&lang=js");
/* harmony import */ var _home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./node_modules/vue-loader/dist/exportHelper.js */ "./node_modules/vue-loader/dist/exportHelper.js");




;
const __exports__ = /*#__PURE__*/(0,_home_prynik917_repo_lmslaravel_node_modules_vue_loader_dist_exportHelper_js__WEBPACK_IMPORTED_MODULE_2__["default"])(_ButtonGroup_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"], [['render',_ButtonGroup_vue_vue_type_template_id_7ecaea5c__WEBPACK_IMPORTED_MODULE_0__.render],['__file',"resources/js/components/ButtonGroup.vue"]])
/* hot reload */
if (false) {}


/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (__exports__);

/***/ }),

/***/ "./resources/js/Pages/User/UserChperm.vue?vue&type=script&lang=js":
/*!************************************************************************!*\
  !*** ./resources/js/Pages/User/UserChperm.vue?vue&type=script&lang=js ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserChperm_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"])
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserChperm_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./UserChperm.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserChperm.vue?vue&type=script&lang=js");
 

/***/ }),

/***/ "./resources/js/Pages/User/UserItemEdit.vue?vue&type=script&lang=js":
/*!**************************************************************************!*\
  !*** ./resources/js/Pages/User/UserItemEdit.vue?vue&type=script&lang=js ***!
  \**************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserItemEdit_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"])
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserItemEdit_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./UserItemEdit.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=script&lang=js");
 

/***/ }),

/***/ "./resources/js/components/ButtonGroup.vue?vue&type=script&lang=js":
/*!*************************************************************************!*\
  !*** ./resources/js/components/ButtonGroup.vue?vue&type=script&lang=js ***!
  \*************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_ButtonGroup_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"])
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_ButtonGroup_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./ButtonGroup.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/components/ButtonGroup.vue?vue&type=script&lang=js");
 

/***/ }),

/***/ "./resources/js/Pages/User/UserChperm.vue?vue&type=template&id=06fdb66a":
/*!******************************************************************************!*\
  !*** ./resources/js/Pages/User/UserChperm.vue?vue&type=template&id=06fdb66a ***!
  \******************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserChperm_vue_vue_type_template_id_06fdb66a__WEBPACK_IMPORTED_MODULE_0__.render)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserChperm_vue_vue_type_template_id_06fdb66a__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../../node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!../../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./UserChperm.vue?vue&type=template&id=06fdb66a */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserChperm.vue?vue&type=template&id=06fdb66a");


/***/ }),

/***/ "./resources/js/Pages/User/UserItemEdit.vue?vue&type=template&id=726dc833":
/*!********************************************************************************!*\
  !*** ./resources/js/Pages/User/UserItemEdit.vue?vue&type=template&id=726dc833 ***!
  \********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserItemEdit_vue_vue_type_template_id_726dc833__WEBPACK_IMPORTED_MODULE_0__.render)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserItemEdit_vue_vue_type_template_id_726dc833__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../../node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!../../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./UserItemEdit.vue?vue&type=template&id=726dc833 */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=template&id=726dc833");


/***/ }),

/***/ "./resources/js/components/ButtonGroup.vue?vue&type=template&id=7ecaea5c":
/*!*******************************************************************************!*\
  !*** ./resources/js/components/ButtonGroup.vue?vue&type=template&id=7ecaea5c ***!
  \*******************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_ButtonGroup_vue_vue_type_template_id_7ecaea5c__WEBPACK_IMPORTED_MODULE_0__.render)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_dist_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_ButtonGroup_vue_vue_type_template_id_7ecaea5c__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./ButtonGroup.vue?vue&type=template&id=7ecaea5c */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/dist/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/components/ButtonGroup.vue?vue&type=template&id=7ecaea5c");


/***/ }),

/***/ "./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css":
/*!**********************************************************************************************!*\
  !*** ./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css ***!
  \**********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_style_loader_dist_cjs_js_node_modules_css_loader_dist_cjs_js_clonedRuleSet_9_use_1_node_modules_vue_loader_dist_stylePostLoader_js_node_modules_postcss_loader_dist_cjs_js_clonedRuleSet_9_use_2_node_modules_vue_loader_dist_index_js_ruleSet_0_use_0_UserItemEdit_vue_vue_type_style_index_1_id_726dc833_lang_css__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../node_modules/style-loader/dist/cjs.js!../../../../node_modules/css-loader/dist/cjs.js??clonedRuleSet-9.use[1]!../../../../node_modules/vue-loader/dist/stylePostLoader.js!../../../../node_modules/postcss-loader/dist/cjs.js??clonedRuleSet-9.use[2]!../../../../node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css */ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js??clonedRuleSet-9.use[1]!./node_modules/vue-loader/dist/stylePostLoader.js!./node_modules/postcss-loader/dist/cjs.js??clonedRuleSet-9.use[2]!./node_modules/vue-loader/dist/index.js??ruleSet[0].use[0]!./resources/js/Pages/User/UserItemEdit.vue?vue&type=style&index=1&id=726dc833&lang=css");


/***/ }),

/***/ "./node_modules/@vueform/multiselect/dist/multiselect.mjs":
/*!****************************************************************!*\
  !*** ./node_modules/@vueform/multiselect/dist/multiselect.mjs ***!
  \****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ script)
/* harmony export */ });
/* harmony import */ var vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue */ "./node_modules/vue/dist/vue.esm-bundler.js");


function isNullish (val) {
  return [null, undefined].indexOf(val) !== -1
}

function useData (props, context, dep)
{
  const { object, valueProp, mode } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  const $this = (0,vue__WEBPACK_IMPORTED_MODULE_0__.getCurrentInstance)().proxy;

  // ============ DEPENDENCIES ============

  const iv = dep.iv;

  // =============== METHODS ==============

  const update = (val, triggerInput = true) => {
    // Setting object(s) as internal value
    iv.value = makeInternal(val);

    // Setting object(s) or plain value as external 
    // value based on `option` setting
    const externalVal = makeExternal(val);

    context.emit('change', externalVal, $this);

    if (triggerInput) {
      context.emit('input', externalVal);
      context.emit('update:modelValue', externalVal);
    }
  }; 

  // no export
  const makeExternal = (val) => {
    // If external value should be object
    // no transformation is required
    if (object.value) {
      return val
    }

    // No need to transform if empty value
    if (isNullish(val)) {
      return val
    }

    // If external should be plain transform
    // value object to plain values
    return !Array.isArray(val) ? val[valueProp.value] : val.map(v => v[valueProp.value])
  };

  // no export
  const makeInternal = (val) => {
    if (isNullish(val)) {
      return mode.value === 'single' ? {} : []
    }

    return val
  };

  return {
    update,
  }
}

function useValue (props, context)
{
  const { value, modelValue, mode, valueProp } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  // ================ DATA ================

  // internalValue
  const iv = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(mode.value !== 'single' ? [] : {});

  // ============== COMPUTED ==============

  /* istanbul ignore next */
  // externalValue
  const ev = modelValue && modelValue.value !== undefined ? modelValue : value;

  const plainValue = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return mode.value === 'single' ? iv.value[valueProp.value] : iv.value.map(v=>v[valueProp.value])
  });

  const textValue = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return mode.value !== 'single' ? iv.value.map(v=>v[valueProp.value]).join(',') : iv.value[valueProp.value]
  });

  return {
    iv,
    internalValue: iv,
    ev,
    externalValue: ev,
    textValue,
    plainValue,
  }
}

function useSearch (props, context, dep)
{
  const { regex } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  const $this = (0,vue__WEBPACK_IMPORTED_MODULE_0__.getCurrentInstance)().proxy;

  // ============ DEPENDENCIES ============

  const isOpen = dep.isOpen;
  const open = dep.open;

  // ================ DATA ================

  const search = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  const input = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  // =============== METHODS ==============

  const clearSearch = () => {
    search.value = '';
  };

  const handleSearchInput = (e) => {
    search.value = e.target.value;
  };

  const handleKeypress = (e) => {
    if (regex && regex.value) {
      let regexp = regex.value;

      if (typeof regexp === 'string') {
        regexp = new RegExp(regexp);
      }

      if (!e.key.match(regexp)) {
        e.preventDefault();
      }
    }
  };

  const handlePaste = (e) => {
    if (regex && regex.value) {
      let clipboardData = e.clipboardData || /* istanbul ignore next */ window.clipboardData;
      let pastedData = clipboardData.getData('Text');

      let regexp = regex.value;

      if (typeof regexp === 'string') {
        regexp = new RegExp(regexp);
      }
      
      if (!pastedData.split('').every(c => !!c.match(regexp))) {
        e.preventDefault();
      }
    }

    context.emit('paste', e, $this);
  };

  // ============== WATCHERS ==============

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(search, (val) => {
    if (!isOpen.value && val) {
      open();
    }

    context.emit('search-change', val, $this);
  });

  return {
    search,
    input,
    clearSearch,
    handleSearchInput,
    handleKeypress,
    handlePaste,
  }
}

function usePointer$1 (props, context, dep)
{
  const { groupSelect, mode, groups, disabledProp } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  // ================ DATA ================

  const pointer = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  // =============== METHODS ==============

  const setPointer = (option) => {
    if (option === undefined || (option !== null && option[disabledProp.value])) {
      return
    }

    if (groups.value && option && option.group && (mode.value === 'single' || !groupSelect.value)) {
      return
    }

    pointer.value = option;
  };

  const clearPointer = () => {
    setPointer(null);
  };

  return {
    pointer,
    setPointer,
    clearPointer,
  }
}

function normalize (str, strict = true) {
  return strict
    ? String(str).toLowerCase().trim()
    : String(str).toLowerCase()
                 .normalize('NFD')
                 .trim()
                 .replace(new RegExp(/æ/g), 'ae')
                 .replace(new RegExp(/œ/g), 'oe')
                 .replace(new RegExp(/ø/g), 'o')
                 .replace(/\p{Diacritic}/gu, '')
}

function isObject (variable) {
  return Object.prototype.toString.call(variable) === '[object Object]'
}

function arraysEqual (array1, array2) {
  const array2Sorted = array2.slice().sort();

  return array1.length === array2.length && array1.slice().sort().every(function(value, index) {
      return value === array2Sorted[index];
  })
}

function useOptions (props, context, dep)
{
  const { 
    options, mode, trackBy: trackBy_, limit, hideSelected, createTag, createOption: createOption_, label,
    appendNewTag, appendNewOption: appendNewOption_, multipleLabel, object, loading, delay, resolveOnLoad,
    minChars, filterResults, clearOnSearch, clearOnSelect, valueProp, allowAbsent, groupLabel,
    canDeselect, max, strict, closeOnSelect, closeOnDeselect, groups: groupped, reverse, infinite,
    groupOptions, groupHideEmpty, groupSelect, onCreate, disabledProp, searchStart, searchFilter,
  } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  const $this = (0,vue__WEBPACK_IMPORTED_MODULE_0__.getCurrentInstance)().proxy;

  // ============ DEPENDENCIES ============

  const iv = dep.iv;
  const ev = dep.ev;
  const search = dep.search;
  const clearSearch = dep.clearSearch;
  const update = dep.update;
  const pointer = dep.pointer;
  const clearPointer = dep.clearPointer;
  const focus = dep.focus;
  const deactivate = dep.deactivate;
  const close = dep.close;
  const localize = dep.localize;

  // ================ DATA ================

  // no export
  // appendedOptions
  const ap = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)([]);

  // no export
  // resolvedOptions
  const ro = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)([]);

  const resolving = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(false);

  // no export
  const searchWatcher = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  const offset = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(infinite.value && limit.value === -1 ? 10 : limit.value);

  // ============== COMPUTED ==============

  // no export
  const createOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return createTag.value || createOption_.value || false
  });

  // no export
  const appendNewOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    if (appendNewTag.value !== undefined) {
      return appendNewTag.value
    } else if (appendNewOption_.value !== undefined) {
      return appendNewOption_.value
    }

    return true
  });

  // no export
  // extendedOptions
  const eo = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    if (groupped.value) {
      let groups = eg.value || /* istanbul ignore next */ [];

      let eo = [];

      groups.forEach((group) => {
        optionsToArray(group[groupOptions.value]).forEach((option) => {
          eo.push(Object.assign({}, option, group[disabledProp.value] ? { [disabledProp.value]: true } : {}));
        });
      });

      return eo
    } else {
      let eo = optionsToArray(ro.value || /* istanbul ignore next */ []);

      if (ap.value.length) {
        eo = eo.concat(ap.value);
      }

      return eo
    }
  });

  // preFilteredOptions
  const pfo = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let options = eo.value;

    if (reverse.value) {
      options = options.reverse();
    }

    if (createdOption.value.length) {
      options = createdOption.value.concat(options);
    }

    return filterOptions(options)
  });

  // filteredOptions
  const fo = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let options = pfo.value;

    if (offset.value > 0) {
      options = options.slice(0, offset.value);
    }

    return options
  });

  // no export
  // extendedGroups
  const eg = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    if (!groupped.value) {
      return []
    }

    let eg = [];
    let groups = ro.value || /* istanbul ignore next */ [];

    if (ap.value.length) {
      eg.push({
        [groupLabel.value]: ' ',
        [groupOptions.value]: [...ap.value],
        __CREATE__: true
      });
    }

    return eg.concat(groups)
  });

  // preFilteredGroups
  const pfg = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let groups = [...eg.value].map(g => ({...g}));

    if (createdOption.value.length) {
      if (groups[0] && groups[0].__CREATE__) {
        groups[0][groupOptions.value] = [...createdOption.value, ...groups[0][groupOptions.value]];
      } else {
        groups = [{
          [groupLabel.value]: ' ',
          [groupOptions.value]: [...createdOption.value],
          __CREATE__: true
        }].concat(groups);
      }
    }

    return groups
  });

  // filteredGroups
  const fg = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    if (!groupped.value) {
      return []
    }

    let options = pfg.value;

    return filterGroups((options || /* istanbul ignore next */ []).map((group, index) => {
      const arrayOptions = optionsToArray(group[groupOptions.value]);

      return {
        ...group,
        index,
        group: true,
        [groupOptions.value]: filterOptions(arrayOptions, false).map(o => Object.assign({}, o, group[disabledProp.value] ? { [disabledProp.value]: true } : {})),
        __VISIBLE__: filterOptions(arrayOptions).map(o => Object.assign({}, o, group[disabledProp.value] ? { [disabledProp.value]: true } : {})),
      }
      // Difference between __VISIBLE__ and {groupOptions}: visible does not contain selected options when hideSelected=true
    }))
  });

  const hasSelected = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    switch (mode.value) {
      case 'single':
        return !isNullish(iv.value[valueProp.value])

      case 'multiple':
      case 'tags':
        return !isNullish(iv.value) && iv.value.length > 0
    }
  });

  const multipleLabelText = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return multipleLabel !== undefined && multipleLabel.value !== undefined
      ? multipleLabel.value(iv.value, $this)
      : (iv.value && iv.value.length > 1 ? `${iv.value.length} options selected` : `1 option selected`)
  });

  const noOptions = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return !eo.value.length && !resolving.value && !createdOption.value.length
  });


  const noResults = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return eo.value.length > 0 && fo.value.length == 0 && ((search.value && groupped.value) || !groupped.value)
  });

  // no export
  const createdOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    if (createOption.value === false || !search.value) {
      return []
    }

    if (getOptionByTrackBy(search.value) !== -1) {
      return []
    }

    return [{
      [valueProp.value]: search.value,
      [trackBy.value]: search.value,
      [label.value]: search.value,
      __CREATE__: true,
    }]
  });

  const trackBy = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return trackBy_.value || label.value
  });

  // no export
  const nullValue = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    switch (mode.value) {
      case 'single':
        return null

      case 'multiple':
      case 'tags':
        return []
    }
  });

  const busy = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return loading.value || resolving.value
  });

  // =============== METHODS ==============

  /**
   * @param {array|object|string|number} option 
   */
  const select = (option) => {
    if (typeof option !== 'object') {
      option = getOption(option);
    }

    switch (mode.value) {
      case 'single':
        update(option);
        break

      case 'multiple':
      case 'tags':
        update((iv.value).concat(option));
        break
    }

    context.emit('select', finalValue(option), option, $this);
  };

  const deselect = (option) => {
    if (typeof option !== 'object') {
      option = getOption(option);
    }

    switch (mode.value) {
      case 'single':
        clear();
        break

      case 'tags':
      case 'multiple':
        update(Array.isArray(option)
          ? iv.value.filter(v => option.map(o => o[valueProp.value]).indexOf(v[valueProp.value]) === -1)
          : iv.value.filter(v => v[valueProp.value] != option[valueProp.value]));
        break
    }

    context.emit('deselect', finalValue(option), option, $this);
  };

  // no export
  const finalValue = (option) => {
    return object.value ? option : option[valueProp.value]
  };

  const remove = (option) => {
    deselect(option);
  };

  const handleTagRemove = (option, e) => {
    if (e.button !== 0) {
      e.preventDefault();
      return
    }

    remove(option);
  };

  const clear = () => {
    context.emit('clear', $this);
    update(nullValue.value);
  };

  const isSelected = (option) => {
    if (option.group !== undefined) {
      return mode.value === 'single' ? false : areAllSelected(option[groupOptions.value]) && option[groupOptions.value].length
    }

    switch (mode.value) {
      case 'single':
        return !isNullish(iv.value) && iv.value[valueProp.value] == option[valueProp.value]

      case 'tags':
      case 'multiple':
        return !isNullish(iv.value) && iv.value.map(o => o[valueProp.value]).indexOf(option[valueProp.value]) !== -1
    }
  };

  const isDisabled = (option) => {
    return option[disabledProp.value] === true
  };

  const isMax = () => {
    if (max === undefined || max.value === -1 || (!hasSelected.value && max.value > 0)) {
      return false
    }
    
    return iv.value.length >= max.value
  };

  const handleOptionClick = (option) => {
    if (isDisabled(option)) {
      return
    }

    if (onCreate && onCreate.value && !isSelected(option) && option.__CREATE__) {
      option = { ...option };
      delete option.__CREATE__;

      option = onCreate.value(option, $this);
      
      if (option instanceof Promise) {
        resolving.value = true;
        option.then((result) => {
          resolving.value = false;
          handleOptionSelect(result);
        });

        return
      } 
    }

    handleOptionSelect(option);
  };

  const handleOptionSelect = (option) => {
    if (option.__CREATE__) {
      option = { ...option };
      delete option.__CREATE__;
    }
    
    switch (mode.value) {
      case 'single':
        if (option && isSelected(option)) {
          if (canDeselect.value) {
            deselect(option);
          }

          if (closeOnDeselect.value) {
            clearPointer();
            close();
          }
          return
        }

        if (option) {
          handleOptionAppend(option);
        }

        /* istanbul ignore else */
        if (clearOnSelect.value) {
          clearSearch();
        }

        if (closeOnSelect.value) {
          clearPointer();
          close();
        }

        if (option) {
          select(option);
        }
        break

      case 'multiple':
        if (option && isSelected(option)) {
          deselect(option);

          if (closeOnDeselect.value) {
            clearPointer();
            close();
          }
          return
        }

        if (isMax()) {
          context.emit('max', $this);
          return
        }

        if (option) {
          handleOptionAppend(option);
          select(option);
        }

        if (clearOnSelect.value) {
          clearSearch();
        }

        if (hideSelected.value) {
          clearPointer();
        }

        if (closeOnSelect.value) {
          close();
        }
        break

      case 'tags':
        if (option && isSelected(option)) {
          deselect(option);

          if (closeOnDeselect.value) {
            clearPointer();
            close();
          }
          return
        }

        if (isMax()) {
          context.emit('max', $this);
          return
        }

        if (option) {
          handleOptionAppend(option);
        }

        if (clearOnSelect.value) {
          clearSearch();
        }

        if (option) {
          select(option);
        }

        if (hideSelected.value) {
          clearPointer();
        }

        if (closeOnSelect.value) {
          close();
        }
        break
    }

    if (!closeOnSelect.value) {
      focus();
    }
  };

  const handleGroupClick = (group) => {
    if (isDisabled(group) || mode.value === 'single' || !groupSelect.value) {
      return
    }

    switch (mode.value) {
      case 'multiple':
      case 'tags':
        if (areAllEnabledSelected(group[groupOptions.value])) {
          deselect(group[groupOptions.value]);
        } else {
          select(group[groupOptions.value]
            .filter(o => iv.value.map(v => v[valueProp.value]).indexOf(o[valueProp.value]) === -1)
            .filter(o => !o[disabledProp.value])
            .filter((o, k) => iv.value.length + 1 + k <= max.value || max.value === -1)
          );
        }
        break
    }

    if (closeOnSelect.value) {
      deactivate();
    }
  };

  const handleOptionAppend = (option) => {
    if (getOption(option[valueProp.value]) === undefined && createOption.value) {
      context.emit('tag', option[valueProp.value], $this);
      context.emit('option', option[valueProp.value], $this);
      context.emit('create', option[valueProp.value], $this);

      if (appendNewOption.value) {
        appendOption(option);
      }

      clearSearch();
    }
  };

  const selectAll = () => {
    if (mode.value === 'single') {
      return
    }

    select(fo.value.filter(o => !o.disabled && !isSelected(o)));
  };

  // no export
  const areAllEnabledSelected = (options) => {
    return options.find(o => !isSelected(o) && !o[disabledProp.value]) === undefined
  };

  // no export
  const areAllSelected = (options) => {
    return options.find(o => !isSelected(o)) === undefined
  };

  const getOption = (val) => {
    return eo.value[eo.value.map(o => String(o[valueProp.value])).indexOf(String(val))]
  };

  // no export
  const getOptionByTrackBy = (val, norm = true) => {
    return eo.value.map(o => parseInt(o[trackBy.value]) == o[trackBy.value] ? parseInt(o[trackBy.value]) : o[trackBy.value]).indexOf(
      parseInt(val) == val ? parseInt(val) : val
    )
  };

  // no export
  const shouldHideOption = (option) => {
    return ['tags', 'multiple'].indexOf(mode.value) !== -1 && hideSelected.value && isSelected(option)
  };

  // no export
  const appendOption = (option) => {
    ap.value.push(option);
  };

  // no export
  const filterGroups = (groups) => {
    // If the search has value we need to filter among 
    // the ones that are visible to the user to avoid
    // displaying groups which technically have options
    // based on search but that option is already selected.
    return groupHideEmpty.value
      ? groups.filter(g => search.value
          ? g.__VISIBLE__.length
          : g[groupOptions.value].length
        )
      : groups.filter(g => search.value ? g.__VISIBLE__.length : true)
  };

  // no export
  const filterOptions = (options, excludeHideSelected = true) => {
    let fo = options;
    
    if (search.value && filterResults.value) {
      let filter = searchFilter.value;

      if (!filter) {
        filter = (option, $this) => {
          let target = normalize(localize(option[trackBy.value]), strict.value);

          return searchStart.value
            ? target.startsWith(normalize(search.value, strict.value))
            : target.indexOf(normalize(search.value, strict.value)) !== -1
        };
      }

      fo = fo.filter(filter);
    }

    if (hideSelected.value && excludeHideSelected) {
      fo = fo.filter((option) => !shouldHideOption(option));
    }

    return fo
  };

  // no export
  const optionsToArray = (options) => {
    let uo = options;
    
    // Transforming an object to an array of objects
    if (isObject(uo)) {
      uo = Object.keys(uo).map((key) => {
        let val = uo[key];

        return { [valueProp.value]: key, [trackBy.value]: val, [label.value]: val}
      });
    }

    // Transforming an plain arrays to an array of objects
    uo = uo.map((val) => {
      return typeof val === 'object' ? val : { [valueProp.value]: val, [trackBy.value]: val, [label.value]: val}
    });

    return uo
  };

  // no export
  const initInternalValue = () => {
    if (!isNullish(ev.value)) {
      iv.value = makeInternal(ev.value);
    }
  };

  const resolveOptions = (callback) => {
    resolving.value = true;

    return new Promise((resolve, reject) => {
      options.value(search.value, $this).then((response) => {
        ro.value = response || [];

        if (typeof callback == 'function') {
          callback(response);
        }

        resolving.value = false;
      }).catch((e) => {
        console.error(e);

        ro.value = [];

        resolving.value = false;
      }).finally(() => {
        resolve();
      });
    })
  };

  // no export
  const refreshLabels = () => {
    if (!hasSelected.value) {
      return
    }

    if (mode.value === 'single') {
      let option = getOption(iv.value[valueProp.value]);

      /* istanbul ignore else */
      if (option !== undefined) {
        let newLabel = option[label.value];

        iv.value[label.value] = newLabel;

        if (object.value) {
          ev.value[label.value] = newLabel;
        }
      }
    } else {
      iv.value.forEach((val, i) => {
        let option = getOption(iv.value[i][valueProp.value]);

        /* istanbul ignore else */
        if (option !== undefined) {
          let newLabel = option[label.value];

          iv.value[i][label.value] = newLabel;

          if (object.value) {
            ev.value[i][label.value] = newLabel;
          }
        }
      });
    }
  };

  const refreshOptions = (callback) => {
    resolveOptions(callback);
  };

  // no export
  const makeInternal = (val) => {
    if (isNullish(val)) {
      return mode.value === 'single' ? {} : []
    }

    if (object.value) {
      return val
    }

    // If external should be plain transform value object to plain values
    return mode.value === 'single' ? getOption(val) || (allowAbsent.value ? {
      [label.value]: val,
      [valueProp.value]: val,
      [trackBy.value]: val,
    } : {}) : val.filter(v => !!getOption(v) || allowAbsent.value).map(v => getOption(v) || {
      [label.value]: v,
      [valueProp.value]: v,
      [trackBy.value]: v,
    })
  };

  // no export
  const initSearchWatcher = () => {
    searchWatcher.value = (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(search, (query) => {
      if (query.length < minChars.value || (!query && minChars.value !== 0)) {
        return
      }

      resolving.value = true;

      if (clearOnSearch.value) {
        ro.value = [];
      }
      setTimeout(() => {
        if (query != search.value) {
          return
        }

        options.value(search.value, $this).then((response) => {
          if (query == search.value || !search.value) {
            ro.value = response;
            pointer.value = fo.value.filter(o => o[disabledProp.value] !== true)[0] || null;
            resolving.value = false;
          }
        }).catch( /* istanbul ignore next */ (e) => {
          console.error(e);
        });
      }, delay.value);

    }, { flush: 'sync' });
  };

  // ================ HOOKS ===============

  if (mode.value !== 'single' && !isNullish(ev.value) && !Array.isArray(ev.value)) {
    throw new Error(`v-model must be an array when using "${mode.value}" mode`)
  }

  if (options && typeof options.value == 'function') {
    if (resolveOnLoad.value) {
      resolveOptions(initInternalValue);
    } else if (object.value == true) {
      initInternalValue();
    }
  }
  else {
    ro.value = options.value;

    initInternalValue();
  }
  
  // ============== WATCHERS ==============

  if (delay.value > -1) {
    initSearchWatcher();
  }

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(delay, (value, old) => {
    /* istanbul ignore else */
    if (searchWatcher.value) {
      searchWatcher.value();
    }

    if (value >= 0) {
      initSearchWatcher();
    }
  });

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(ev, (newValue) => {
    if (isNullish(newValue)) {
      update(makeInternal(newValue), false);
      return
    }

    switch (mode.value) {
      case 'single':
        if (object.value ? newValue[valueProp.value] != iv.value[valueProp.value] : newValue != iv.value[valueProp.value]) {
          update(makeInternal(newValue), false);
        }
        break

      case 'multiple':
      case 'tags':
        if (!arraysEqual(object.value ? newValue.map(o => o[valueProp.value]) : newValue, iv.value.map(o => o[valueProp.value]))) {
          update(makeInternal(newValue), false);
        }
        break
    }
  }, { deep: true });

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(options, (n, o) => {
    if (typeof props.options === 'function') {
      if (resolveOnLoad.value && (!o || (n && n.toString() !== o.toString()))) {
        resolveOptions();
      }
    } else {
      ro.value = props.options;

      if (!Object.keys(iv.value).length) {
        initInternalValue();
      }

      refreshLabels();
    }
  });

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(label, refreshLabels);

  return {
    pfo,
    fo,
    filteredOptions: fo,
    hasSelected,
    multipleLabelText,
    eo,
    extendedOptions: eo,
    eg,
    extendedGroups: eg,
    fg,
    filteredGroups: fg,
    noOptions,
    noResults,
    resolving,
    busy,
    offset,
    select,
    deselect,
    remove,
    selectAll,
    clear,
    isSelected,
    isDisabled,
    isMax,
    getOption,
    handleOptionClick,
    handleGroupClick,
    handleTagRemove,
    refreshOptions,
    resolveOptions,
    refreshLabels,
  }
}

function usePointer (props, context, dep)
{
  const {
    valueProp, showOptions, searchable, groupLabel,
    groups: groupped, mode, groupSelect, disabledProp,
    groupOptions,
  } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  // ============ DEPENDENCIES ============

  const fo = dep.fo;
  const fg = dep.fg;
  const handleOptionClick = dep.handleOptionClick;
  const handleGroupClick = dep.handleGroupClick;
  const search = dep.search;
  const pointer = dep.pointer;
  const setPointer = dep.setPointer;
  const clearPointer = dep.clearPointer;
  const multiselect = dep.multiselect;
  const isOpen = dep.isOpen;

  // ============== COMPUTED ==============

  // no export
  const options = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return fo.value.filter(o => !o[disabledProp.value])
  });

  const groups = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return fg.value.filter(g => !g[disabledProp.value])
  });

  const canPointGroups = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return mode.value !== 'single' && groupSelect.value
  });

  const isPointerGroup = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return pointer.value && pointer.value.group
  });

  const currentGroup = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return getParentGroup(pointer.value)
  });

  const prevGroup = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    const group = isPointerGroup.value ? pointer.value : /* istanbul ignore next */ getParentGroup(pointer.value);
    const groupIndex = groups.value.map(g => g[groupLabel.value]).indexOf(group[groupLabel.value]);
    let prevGroup = groups.value[groupIndex - 1];

    if (prevGroup === undefined) {
      prevGroup = lastGroup.value;
    }

    return prevGroup
  });
  
  const nextGroup = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let nextIndex = groups.value.map(g => g.label).indexOf(isPointerGroup.value
      ? pointer.value[groupLabel.value]
      : getParentGroup(pointer.value)[groupLabel.value]) + 1;

    if (groups.value.length <= nextIndex) {
      nextIndex = 0;
    }

    return groups.value[nextIndex]
  });

  const lastGroup = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return [...groups.value].slice(-1)[0]
  });
  
  const currentGroupFirstEnabledOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return pointer.value.__VISIBLE__.filter(o => !o[disabledProp.value])[0]
  });

  const currentGroupPrevEnabledOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    const options = currentGroup.value.__VISIBLE__.filter(o => !o[disabledProp.value]);
    return options[options.map(o => o[valueProp.value]).indexOf(pointer.value[valueProp.value]) - 1]
  });
  
  const currentGroupNextEnabledOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    const options = getParentGroup(pointer.value).__VISIBLE__.filter(o => !o[disabledProp.value]);
    return options[options.map(o => o[valueProp.value]).indexOf(pointer.value[valueProp.value]) + 1]
  });

  const prevGroupLastEnabledOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return [...prevGroup.value.__VISIBLE__.filter(o => !o[disabledProp.value])].slice(-1)[0]
  });

  const lastGroupLastEnabledOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return [...lastGroup.value.__VISIBLE__.filter(o => !o[disabledProp.value])].slice(-1)[0]
  });

  // =============== METHODS ==============

  const isPointed = (option) => {
    return (!!pointer.value && (
      (!option.group && pointer.value[valueProp.value] === option[valueProp.value]) ||
      (option.group !== undefined && pointer.value[groupLabel.value] === option[groupLabel.value])
    )) ? true : undefined
  };

  const setPointerFirst = () => {
    setPointer(options.value[0] || null);
  };

  const selectPointer = () => {
    if (!pointer.value || pointer.value[disabledProp.value] === true) {
      return
    }

    if (isPointerGroup.value) {
      handleGroupClick(pointer.value);
    } else {
      handleOptionClick(pointer.value);
    }
  };

  const forwardPointer = () => {
    if (pointer.value === null) {
      setPointer((groupped.value && canPointGroups.value ? (!groups.value[0].__CREATE__ ? groups.value[0] : options.value[0]) : options.value[0]) || null);
    }
    else if (groupped.value && canPointGroups.value) {
      let nextPointer = isPointerGroup.value ? currentGroupFirstEnabledOption.value : currentGroupNextEnabledOption.value;

      if (nextPointer === undefined) {
        nextPointer = nextGroup.value;

        if (nextPointer.__CREATE__) {
          nextPointer = nextPointer[groupOptions.value][0];
        }
      }

      setPointer(nextPointer || /* istanbul ignore next */ null);
    } else {
      let next = options.value.map(o => o[valueProp.value]).indexOf(pointer.value[valueProp.value]) + 1;

      if (options.value.length <= next) {
        next = 0;
      }

      setPointer(options.value[next] || null);
    }

    (0,vue__WEBPACK_IMPORTED_MODULE_0__.nextTick)(() => {
      adjustWrapperScrollToPointer();
    });
  };

  const backwardPointer = () => {
    if (pointer.value === null) {
      let prevPointer = options.value[options.value.length - 1];

      if (groupped.value && canPointGroups.value) {
        prevPointer = lastGroupLastEnabledOption.value;

        if (prevPointer === undefined) {
          prevPointer = lastGroup.value;
        }
      }

      setPointer(prevPointer  || null);
    }
    else if (groupped.value && canPointGroups.value) {
      let prevPointer = isPointerGroup.value ? prevGroupLastEnabledOption.value : currentGroupPrevEnabledOption.value;

      if (prevPointer === undefined) {
        prevPointer = isPointerGroup.value ? prevGroup.value : currentGroup.value;

        if (prevPointer.__CREATE__) {
          prevPointer = prevGroupLastEnabledOption.value;

          if (prevPointer === undefined) {
            prevPointer = prevGroup.value;
          }
        }
      }

      setPointer(prevPointer || /* istanbul ignore next */ null);
    } else {
      let prevIndex = options.value.map(o => o[valueProp.value]).indexOf(pointer.value[valueProp.value]) - 1;

      if (prevIndex < 0) {
        prevIndex = options.value.length - 1;
      }

      setPointer(options.value[prevIndex] || null);
    }

    (0,vue__WEBPACK_IMPORTED_MODULE_0__.nextTick)(() => {
      adjustWrapperScrollToPointer();
    });
  };

  const getParentGroup = (option) => {
    return groups.value.find((group) => {
      return group.__VISIBLE__.map(o => o[valueProp.value]).indexOf(option[valueProp.value]) !== -1
    })
  };

  // no export
  /* istanbul ignore next */
  const adjustWrapperScrollToPointer = () => {
    let pointedOption = multiselect.value.querySelector(`[data-pointed]`);

    if (!pointedOption) {
      return
    }

    let wrapper = pointedOption.parentElement.parentElement;

    if (groupped.value) {
      wrapper = isPointerGroup.value
        ? pointedOption.parentElement.parentElement.parentElement
        : pointedOption.parentElement.parentElement.parentElement.parentElement;
    }

    if (pointedOption.offsetTop + pointedOption.offsetHeight > wrapper.clientHeight + wrapper.scrollTop) {
      wrapper.scrollTop = pointedOption.offsetTop + pointedOption.offsetHeight - wrapper.clientHeight;
    }
    
    if (pointedOption.offsetTop < wrapper.scrollTop) {
      wrapper.scrollTop = pointedOption.offsetTop;
    }
  };

  // ============== WATCHERS ==============

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(search, (val) => {
    if (searchable.value) {
      if (val.length && showOptions.value) {
        setPointerFirst();
      } else {
        clearPointer();
      }
    }
  });

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(isOpen, (val) => {
    if (val) {
      let firstSelected = multiselect.value.querySelectorAll(`[data-selected]`)[0];

      if (!firstSelected) {
        return
      }

      let wrapper = firstSelected.parentElement.parentElement;
      
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.nextTick)(() => {
        /* istanbul ignore next */
        if (wrapper.scrollTop > 0) {
          return
        }

        wrapper.scrollTop = firstSelected.offsetTop;
      });
    }
  });

  return {
    pointer,
    canPointGroups,
    isPointed,
    setPointerFirst,
    selectPointer,
    forwardPointer,
    backwardPointer,
  }
}

function useDropdown (props, context, dep)
{
  const { disabled } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  const $this = (0,vue__WEBPACK_IMPORTED_MODULE_0__.getCurrentInstance)().proxy;

  // ================ DATA ================

  const isOpen = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(false);

  // =============== METHODS ==============

  const open = () => {
    if (isOpen.value || disabled.value) {
      return
    }

    isOpen.value = true;
    context.emit('open', $this);
  };

  const close = () => {
    if (!isOpen.value) {
      return
    }

    isOpen.value = false;
    context.emit('close', $this);
  };

  return {
    isOpen,
    open,
    close,
  }
}

function useMultiselect (props, context, dep)
{
  const { searchable, disabled, clearOnBlur } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  // ============ DEPENDENCIES ============

  const input = dep.input;
  const open = dep.open;
  const close = dep.close;
  const clearSearch = dep.clearSearch;
  const isOpen = dep.isOpen;

  // ================ DATA ================

  const multiselect = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);
  
  const wrapper = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  const tags = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  const isActive = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(false);

  const mouseClicked = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(false);

  // ============== COMPUTED ==============

  const tabindex = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return searchable.value || disabled.value ? -1 : 0
  });

  // =============== METHODS ==============

  const blur = () => {
    if (searchable.value) {
      input.value.blur();
    }

    wrapper.value.blur();
  };

  const focus = () => {
    if (searchable.value && !disabled.value) {
      input.value.focus();
    }
  };

  const activate = (shouldOpen = true) => {
    if (disabled.value) {
      return
    }

    isActive.value = true;

    if (shouldOpen) {
      open();
    }
  };

  const deactivate = () => {
    isActive.value = false;

    setTimeout(() => {
      if (!isActive.value) {
        close();

        if (clearOnBlur.value) {
          clearSearch();
        }
      }
    }, 1);
  };

  const handleFocusIn = (e) => {
    if ((e.target.closest('[data-tags]') && e.target.nodeName !== 'INPUT') || e.target.closest('[data-clear]')) {
      return
    }

    activate(mouseClicked.value);
  };

  const handleFocusOut = () => {
    deactivate();
  };

  const handleCaretClick = () => {
    deactivate();
    blur();
  };

  /* istanbul ignore next */
  const handleMousedown = (e) => {
    mouseClicked.value = true;

    if (isOpen.value && (e.target.isEqualNode(wrapper.value) || e.target.isEqualNode(tags.value))) {
      setTimeout(() => {
        deactivate();
      }, 0);
    } else if (document.activeElement.isEqualNode(wrapper.value) && !isOpen.value) {
      activate();    
    }

    setTimeout(() => {
      mouseClicked.value = false;
    }, 0);
  };

  return {
    multiselect,
    wrapper,
    tags,
    tabindex,
    isActive,
    mouseClicked,
    blur,
    focus,
    activate,
    deactivate,
    handleFocusIn,
    handleFocusOut,
    handleCaretClick,
    handleMousedown,
  }
}

function useKeyboard (props, context, dep)
{
  const {
    mode, addTagOn, openDirection, searchable,
    showOptions, valueProp, groups: groupped,
    addOptionOn: addOptionOn_, createTag, createOption: createOption_,
    reverse,
  } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  const $this = (0,vue__WEBPACK_IMPORTED_MODULE_0__.getCurrentInstance)().proxy;

  // ============ DEPENDENCIES ============

  const iv = dep.iv;
  const update = dep.update;
  const search = dep.search;
  const setPointer = dep.setPointer;
  const selectPointer = dep.selectPointer;
  const backwardPointer = dep.backwardPointer;
  const forwardPointer = dep.forwardPointer;
  const multiselect = dep.multiselect;
  const wrapper = dep.wrapper;
  const tags = dep.tags;
  const isOpen = dep.isOpen;
  const open = dep.open;
  const blur = dep.blur;
  const fo = dep.fo;

  // ============== COMPUTED ==============

  // no export
  const createOption = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return createTag.value || createOption_.value || false
  });

  // no export
  const addOptionOn = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    if (addTagOn.value !== undefined) {
      return addTagOn.value
    }
    else if (addOptionOn_.value !== undefined) {
      return addOptionOn_.value
    }

    return ['enter']
  });

  // =============== METHODS ==============

  // no export
  const preparePointer = () => {
    // When options are hidden and creating tags is allowed
    // no pointer will be set (because options are hidden).
    // In such case we need to set the pointer manually to the 
    // first option, which equals to the option created from
    // the search value.
    if (mode.value === 'tags' && !showOptions.value && createOption.value && searchable.value && !groupped.value) {
      setPointer(fo.value[fo.value.map(o => o[valueProp.value]).indexOf(search.value)]);
    }
  };

  const removeLastRemovable = (arr) => {
    // Find the index of the last object in the array that doesn't have a "remove" property set to false
    let indexToRemove = arr.length - 1;
    while (indexToRemove >= 0 && (arr[indexToRemove].remove === false || arr[indexToRemove].disabled)) {
      indexToRemove--;
    }

    // If all objects have a "remove" property set to false, don't remove anything and return the original array
    if (indexToRemove < 0) {
      return arr
    }

    // Remove the object at the found index and return the updated array
    arr.splice(indexToRemove, 1);
    return arr
  };

  const handleKeydown = (e) => {
    context.emit('keydown', e, $this);

    let tagList;
    let activeIndex;

    if (['ArrowLeft', 'ArrowRight', 'Enter'].indexOf(e.key) !== -1 && mode.value === 'tags') {
      tagList = [...(multiselect.value.querySelectorAll(`[data-tags] > *`))].filter(e => e !== tags.value);
      activeIndex = tagList.findIndex(e => e === document.activeElement);
    }

    switch (e.key) {
      case 'Backspace':
        if (mode.value === 'single') {
          return
        }

        if (searchable.value && [null, ''].indexOf(search.value) === -1) {
          return
        }

        if (iv.value.length === 0) {
          return
        }

        update(removeLastRemovable([...iv.value]));
        break

      case 'Enter':
        e.preventDefault();

        if (e.keyCode === 229) {
          // ignore IME confirmation
          return
        }

        if (activeIndex !== -1 && activeIndex !== undefined) {
          update([...iv.value].filter((v, k) => k !== activeIndex));

          if (activeIndex === tagList.length - 1) {
            if (tagList.length - 1) {
              tagList[tagList.length - 2].focus();
            } else if (searchable.value) {
              tags.value.querySelector('input').focus();
            } else {
              wrapper.value.focus();
            }
          }
          return
        }

        if (addOptionOn.value.indexOf('enter') === -1 && createOption.value) {
          return
        }
        
        preparePointer();
        selectPointer();
        break

      case ' ':
        if (!createOption.value && !searchable.value) {
          e.preventDefault();
          
          preparePointer();
          selectPointer();
          return
        }

        if (!createOption.value) {
          return false
        } 

        if (addOptionOn.value.indexOf('space') === -1 && createOption.value) {
          return
        }

        e.preventDefault();
        
        preparePointer();
        selectPointer();
        break
      
      case 'Tab':
      case ';':
      case ',':
        if (addOptionOn.value.indexOf(e.key.toLowerCase()) === -1 || !createOption.value) {
          return
        }

        preparePointer();
        selectPointer();
        e.preventDefault();
        break

      case 'Escape':
        blur();
        break

      case 'ArrowUp':
        e.preventDefault();

        if (!showOptions.value) {
          return
        }

        /* istanbul ignore else */
        if (!isOpen.value) {
          open();
        }
        
        backwardPointer();
        break

      case 'ArrowDown':
        e.preventDefault();

        if (!showOptions.value) {
          return
        }

        /* istanbul ignore else */
        if (!isOpen.value) {
          open();
        }

        forwardPointer();
        break

      case 'ArrowLeft':
        if (
          (searchable.value && tags.value && tags.value.querySelector('input').selectionStart)
          || e.shiftKey || mode.value !== 'tags' || !iv.value || !iv.value.length
        ) {
          return
        }

        e.preventDefault();

        if (activeIndex === -1) {
          tagList[tagList.length-1].focus();
        }
        else if (activeIndex > 0) {
          tagList[activeIndex-1].focus();
        }
        break

      case 'ArrowRight':
        if (activeIndex === -1 || e.shiftKey || mode.value !== 'tags' || !iv.value || !iv.value.length) {
          return
        }

        e.preventDefault();
        
        /* istanbul ignore else */
        if (tagList.length > activeIndex + 1) {
          tagList[activeIndex+1].focus();
        }
        else if (searchable.value) {
          tags.value.querySelector('input').focus();
        }
        else if (!searchable.value) {
          wrapper.value.focus();
        }
        
        break
    }
  };

  const handleKeyup = (e) => {
    context.emit('keyup', e, $this);
  };

  return {
    handleKeydown,
    handleKeyup,
    preparePointer,
  }
}

function useClasses (props, context, dependencies)
{const { 
    classes: classes_, disabled, openDirection, showOptions
  } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  // ============ DEPENDENCIES ============

  const isOpen = dependencies.isOpen;
  const isPointed = dependencies.isPointed;
  const isSelected = dependencies.isSelected;
  const isDisabled = dependencies.isDisabled;
  const isActive = dependencies.isActive;
  const canPointGroups = dependencies.canPointGroups;
  const resolving = dependencies.resolving;
  const fo = dependencies.fo;

  const classes = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => ({
    container: 'multiselect',
    containerDisabled: 'is-disabled',
    containerOpen: 'is-open',
    containerOpenTop: 'is-open-top',
    containerActive: 'is-active',
    wrapper: 'multiselect-wrapper',
    singleLabel: 'multiselect-single-label',
    singleLabelText: 'multiselect-single-label-text',
    multipleLabel: 'multiselect-multiple-label',
    search: 'multiselect-search',
    tags: 'multiselect-tags',
    tag: 'multiselect-tag',
    tagDisabled: 'is-disabled',
    tagRemove: 'multiselect-tag-remove',
    tagRemoveIcon: 'multiselect-tag-remove-icon',
    tagsSearchWrapper: 'multiselect-tags-search-wrapper',
    tagsSearch: 'multiselect-tags-search',
    tagsSearchCopy: 'multiselect-tags-search-copy',
    placeholder: 'multiselect-placeholder',
    caret: 'multiselect-caret',
    caretOpen: 'is-open',
    clear: 'multiselect-clear',
    clearIcon: 'multiselect-clear-icon',
    spinner: 'multiselect-spinner',
    inifinite: 'multiselect-inifite',
    inifiniteSpinner: 'multiselect-inifite-spinner',
    dropdown: 'multiselect-dropdown',
    dropdownTop: 'is-top',
    dropdownHidden: 'is-hidden',
    options: 'multiselect-options',
    optionsTop: 'is-top',
    group: 'multiselect-group',
    groupLabel: 'multiselect-group-label',
    groupLabelPointable: 'is-pointable',
    groupLabelPointed: 'is-pointed',
    groupLabelSelected: 'is-selected',
    groupLabelDisabled: 'is-disabled',
    groupLabelSelectedPointed: 'is-selected is-pointed',
    groupLabelSelectedDisabled: 'is-selected is-disabled',
    groupOptions: 'multiselect-group-options',
    option: 'multiselect-option',
    optionPointed: 'is-pointed',
    optionSelected: 'is-selected',
    optionDisabled: 'is-disabled',
    optionSelectedPointed: 'is-selected is-pointed',
    optionSelectedDisabled: 'is-selected is-disabled',
    noOptions: 'multiselect-no-options',
    noResults: 'multiselect-no-results',
    fakeInput: 'multiselect-fake-input',
    assist: 'multiselect-assistive-text',
    spacer: 'multiselect-spacer',
    ...classes_.value,
  }));

  // ============== COMPUTED ==============

  const showDropdown = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return !!(isOpen.value && showOptions.value && (!resolving.value || (resolving.value && fo.value.length)))
  });

  const classList = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    const c = classes.value;

    return {
      container: [c.container]
        .concat(disabled.value ? c.containerDisabled : [])
        .concat(showDropdown.value && openDirection.value === 'top'  ? c.containerOpenTop : [])
        .concat(showDropdown.value && openDirection.value !== 'top' ? c.containerOpen : [])
        .concat(isActive.value ? c.containerActive : []),
      wrapper: c.wrapper,
      spacer: c.spacer,
      singleLabel: c.singleLabel,
      singleLabelText: c.singleLabelText,
      multipleLabel: c.multipleLabel,
      search: c.search,
      tags: c.tags,
      tag: [c.tag]
        .concat(disabled.value ? c.tagDisabled : []),
      tagDisabled: c.tagDisabled,
      tagRemove: c.tagRemove,
      tagRemoveIcon: c.tagRemoveIcon,
      tagsSearchWrapper: c.tagsSearchWrapper,
      tagsSearch: c.tagsSearch,
      tagsSearchCopy: c.tagsSearchCopy,
      placeholder: c.placeholder,
      caret: [c.caret]
        .concat(isOpen.value ? c.caretOpen : []),
      clear: c.clear,
      clearIcon: c.clearIcon,
      spinner: c.spinner,
      inifinite: c.inifinite,
      inifiniteSpinner: c.inifiniteSpinner,
      dropdown: [c.dropdown]
        .concat(openDirection.value === 'top' ? c.dropdownTop : [])
        .concat(!isOpen.value || !showOptions.value || !showDropdown.value ? c.dropdownHidden : []),
      options: [c.options]
        .concat(openDirection.value === 'top' ? c.optionsTop : []),
      group: c.group,
      groupLabel: (g) => {
        let groupLabel = [c.groupLabel];

        if (isPointed(g)) {
          groupLabel.push(isSelected(g) ? c.groupLabelSelectedPointed : c.groupLabelPointed);
        } else if (isSelected(g) && canPointGroups.value) {
          groupLabel.push(isDisabled(g) ? c.groupLabelSelectedDisabled : c.groupLabelSelected);
        } else if (isDisabled(g)) {
          groupLabel.push(c.groupLabelDisabled);
        }

        if (canPointGroups.value) {
          groupLabel.push(c.groupLabelPointable);
        }

        return groupLabel
      },
      groupOptions: c.groupOptions,
      option: (o, g) => {
        let option = [c.option];

        if (isPointed(o)) {
          option.push(isSelected(o) ? c.optionSelectedPointed : c.optionPointed);
        } else if (isSelected(o)) {
          option.push(isDisabled(o) ? c.optionSelectedDisabled : c.optionSelected);
        } else if (isDisabled(o) || (g && isDisabled(g))) {
          option.push(c.optionDisabled);
        }

        return option
      },
      noOptions: c.noOptions,
      noResults: c.noResults,
      assist: c.assist,
      fakeInput: c.fakeInput,
    }
  });

  return {
    classList,
    showDropdown,
  }
}

function useScroll (props, context, dep)
{
  const {
    limit, infinite,
  } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  // ============ DEPENDENCIES ============

  const isOpen = dep.isOpen;
  const offset = dep.offset;
  const search = dep.search;
  const pfo = dep.pfo;
  const eo = dep.eo;

  // ================ DATA ================

  // no export
  const observer = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  const infiniteLoader = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  // ============== COMPUTED ==============

  const hasMore = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return offset.value < pfo.value.length
  });

  // =============== METHODS ==============

  // no export
  /* istanbul ignore next */
  const handleIntersectionObserver = (entries) => {
    const { isIntersecting, target } = entries[0];

    if (isIntersecting) {
      const parent = target.offsetParent;
      const scrollTop = parent.scrollTop;

      offset.value += limit.value == -1 ? 10 : limit.value;

      (0,vue__WEBPACK_IMPORTED_MODULE_0__.nextTick)(() => {
        parent.scrollTop = scrollTop;
      });
    }
  };

  const observe = () => {
    /* istanbul ignore else */
    if (isOpen.value && offset.value < pfo.value.length) {
      observer.value.observe(infiniteLoader.value);
    } else if (!isOpen.value && observer.value) {
      observer.value.disconnect();
    }
  };

  // ============== WATCHERS ==============

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(isOpen, () => {
    if (!infinite.value) {
      return
    }

    observe();
  });

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(search, () => {
    if (!infinite.value) {
      return
    }

    offset.value = limit.value;

    observe();
  }, { flush: 'post' });

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.watch)(eo, () => {
    if (!infinite.value) {
      return
    }

    observe();
  }, { immediate: false, flush: 'post' });

  // ================ HOOKS ===============

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.onMounted)(() => {
    /* istanbul ignore else */
    if (window && window.IntersectionObserver) {
      observer.value = new IntersectionObserver(handleIntersectionObserver);
    }
  });

  return {
    hasMore,
    infiniteLoader,
  }
}

function useA11y (props, context, dep)
{
  const {
    placeholder, id, valueProp, label: labelProp, mode, groupLabel, aria, searchable ,
  } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  // ============ DEPENDENCIES ============

  const pointer = dep.pointer;
  const iv = dep.iv;
  const hasSelected = dep.hasSelected;
  const multipleLabelText = dep.multipleLabelText;

  // ================ DATA ================

  const label = (0,vue__WEBPACK_IMPORTED_MODULE_0__.ref)(null);

  // ============== COMPUTED ==============

  const ariaAssist = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let texts = [];

    if (id && id.value) {
      texts.push(id.value);
    }

    texts.push('assist');

    return texts.join('-')
  });

  const ariaControls = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let texts = [];

    if (id && id.value) {
      texts.push(id.value);
    }

    texts.push('multiselect-options');

    return texts.join('-')
  });

  const ariaActiveDescendant = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let texts = [];

    if (id && id.value) {
      texts.push(id.value);
    }

    if (pointer.value) {
      texts.push(pointer.value.group ? 'multiselect-group' : 'multiselect-option');

      texts.push(pointer.value.group ? pointer.value.index : pointer.value[valueProp.value]);

      return texts.join('-')
    }
  });



  const ariaPlaceholder = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return placeholder.value
  });

  const ariaMultiselectable = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    return mode.value !== 'single'
  });

  const ariaLabel = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let ariaLabel = '';

    if (mode.value === 'single' && hasSelected.value) {
      ariaLabel += iv.value[labelProp.value];
    }

    if (mode.value === 'multiple' && hasSelected.value) {
      ariaLabel += multipleLabelText.value;
    }

    if (mode.value === 'tags' && hasSelected.value) {
      ariaLabel += iv.value.map(v => v[labelProp.value]).join(', ');
    }

    return ariaLabel
  });

  const arias = (0,vue__WEBPACK_IMPORTED_MODULE_0__.computed)(() => {
    let arias = { ...aria.value };
    
    // Need to add manually because focusing
    // the input won't read the selected value
    if (searchable.value) {
      arias['aria-labelledby'] = arias['aria-labelledby']
        ? `${ariaAssist.value} ${arias['aria-labelledby']}`
        : ariaAssist.value;
      
      if (ariaLabel.value && arias['aria-label']) {
        arias['aria-label'] = `${ariaLabel.value}, ${arias['aria-label']}`;
      }
    }

    return arias
  });

  // =============== METHODS ==============

  const ariaOptionId = (option) => {
    let texts = [];

    if (id && id.value) {
      texts.push(id.value);
    }

    texts.push('multiselect-option');

    texts.push(option[valueProp.value]);

    return texts.join('-')
  };

  const ariaGroupId = (option) => {
    let texts = [];

    if (id && id.value) {
      texts.push(id.value);
    }

    texts.push('multiselect-group');

    texts.push(option.index);

    return texts.join('-')
  };

  const ariaOptionLabel = (label) => {
    let texts = [];

    texts.push(label);

    return texts.join(' ')
  };

  const ariaGroupLabel = (label) => {
    let texts = [];

    texts.push(label);

    return texts.join(' ')
  };

  const ariaTagLabel = (label) => {
    return `${label} ❎`
  };

  // =============== HOOKS ================

  (0,vue__WEBPACK_IMPORTED_MODULE_0__.onMounted)(() => {
    /* istanbul ignore next */
    if (id && id.value && document && document.querySelector) {
      let forTag = document.querySelector(`[for="${id.value}"]`);
      label.value = forTag ? forTag.innerText : null;
    }
  });

  return {
    arias,
    ariaLabel,
    ariaAssist,
    ariaControls,
    ariaPlaceholder,
    ariaMultiselectable,
    ariaActiveDescendant,
    ariaOptionId,
    ariaOptionLabel,
    ariaGroupId,
    ariaGroupLabel,
    ariaTagLabel,
  }
}

function useI18n (props, context, dep)
{
  const {
    locale, fallbackLocale,
  } = (0,vue__WEBPACK_IMPORTED_MODULE_0__.toRefs)(props);

  // =============== METHODS ==============

  const localize = (target) => {
    if (!target || typeof target !== 'object') {
      return target
    }

    if (target && target[locale.value]) {
      return target[locale.value]
    } else if (target && locale.value && target[locale.value.toUpperCase()]) {
      return target[locale.value.toUpperCase()]
    } else if (target && target[fallbackLocale.value]) {
      return target[fallbackLocale.value]
    } else if (target && fallbackLocale.value && target[fallbackLocale.value.toUpperCase()]) {
      return target[fallbackLocale.value.toUpperCase()]
    } else if (target && Object.keys(target)[0]) {
      return target[Object.keys(target)[0]]
    } else {
      return ''
    }
  };

  return {
    localize,
  }
}

function resolveDeps (props, context, features, deps = {}) {
  features.forEach((composable) => {
    /* istanbul ignore else */
    if (composable) {
      deps = {
        ...deps,
        ...composable(props, context, deps)
      };
    }

  });
  
  return deps
}

var script = {
    name: 'Multiselect',
    emits: [
      'paste', 'open', 'close', 'select', 'deselect', 
      'input', 'search-change', 'tag', 'option', 'update:modelValue',
      'change', 'clear', 'keydown', 'keyup', 'max', 'create',
    ],
    props: {
      value: {
        required: false,
      },
      modelValue: {
        required: false,
      },
      options: {
        type: [Array, Object, Function],
        required: false,
        default: () => ([])
      },
      id: {
        type: [String, Number],
        required: false,
      },
      name: {
        type: [String, Number],
        required: false,
        default: 'multiselect',
      },
      disabled: {
        type: Boolean,
        required: false,
        default: false,
      },
      label: {
        type: String,
        required: false,
        default: 'label',
      },
      trackBy: {
        type: String,
        required: false,
        default: undefined,
      },
      valueProp: {
        type: String,
        required: false,
        default: 'value',
      },
      placeholder: {
        type: String,
        required: false,
        default: null,
      },
      mode: {
        type: String,
        required: false,
        default: 'single', // single|multiple|tags
      },
      searchable: {
        type: Boolean,
        required: false,
        default: false,
      },
      limit: {
        type: Number,
        required: false,
        default: -1,
      },
      hideSelected: {
        type: Boolean,
        required: false,
        default: true,
      },
      createTag: {
        type: Boolean,
        required: false,
        default: undefined,
      },
      createOption: {
        type: Boolean,
        required: false,
        default: undefined,
      },
      appendNewTag: {
        type: Boolean,
        required: false,
        default: undefined,
      },
      appendNewOption: {
        type: Boolean,
        required: false,
        default: undefined,
      },
      addTagOn: {
        type: Array,
        required: false,
        default: undefined,
      },
      addOptionOn: {
        type: Array,
        required: false,
        default: undefined,
      },
      caret: {
        type: Boolean,
        required: false,
        default: true,
      },
      loading: {
        type: Boolean,
        required: false,
        default: false,
      },
      noOptionsText: {
        type: [String, Object],
        required: false,
        default: 'The list is empty',
      },
      noResultsText: {
        type: [String, Object],
        required: false,
        default: 'No results found',
      },
      multipleLabel: {
        type: Function,
        required: false,
      },
      object: {
        type: Boolean,
        required: false,
        default: false,
      },
      delay: {
        type: Number,
        required: false,
        default: -1,
      },
      minChars: {
        type: Number,
        required: false,
        default: 0,
      },
      resolveOnLoad: {
        type: Boolean,
        required: false,
        default: true,
      },
      filterResults: {
        type: Boolean,
        required: false,
        default: true,
      },
      clearOnSearch: {
        type: Boolean,
        required: false,
        default: false,
      },
      clearOnSelect: {
        type: Boolean,
        required: false,
        default: true,
      },
      canDeselect: {
        type: Boolean,
        required: false,
        default: true,
      },
      canClear: {
        type: Boolean,
        required: false,
        default: true,
      },
      max: {
        type: Number,
        required: false,
        default: -1,
      },
      showOptions: {
        type: Boolean,
        required: false,
        default: true,
      },
      required: {
        type: Boolean,
        required: false,
        default: false,
      },
      openDirection: {
        type: String,
        required: false,
        default: 'bottom',
      },
      nativeSupport: {
        type: Boolean,
        required: false,
        default: false,
      },
      classes: {
        type: Object,
        required: false,
        default: () => ({})
      },
      strict: {
        type: Boolean,
        required: false,
        default: true,
      },
      closeOnSelect: {
        type: Boolean,
        required: false,
        default: true,
      },
      closeOnDeselect: {
        type: Boolean,
        required: false,
        default: false,
      },
      autocomplete: {
        type: String,
        required: false,
      },
      groups: {
        type: Boolean,
        required: false,
        default: false,
      },
      groupLabel: {
        type: String,
        required: false,
        default: 'label',
      },
      groupOptions: {
        type: String,
        required: false,
        default: 'options',
      },
      groupHideEmpty: {
        type: Boolean,
        required: false,
        default: false,
      },
      groupSelect: {
        type: Boolean,
        required: false,
        default: true,
      },
      inputType: {
        type: String,
        required: false,
        default: 'text',
      },
      attrs: {
        required: false,
        type: Object,
        default: () => ({}),
      },
      onCreate: {
        required: false,
        type: Function,
      },
      disabledProp: {
        type: String,
        required: false,
        default: 'disabled',
      },
      searchStart: {
        type: Boolean,
        required: false,
        default: false,
      },
      reverse: {
        type: Boolean,
        required: false,
        default: false,
      },
      regex: {
        type: [Object, String, RegExp],
        required: false,
        default: undefined,
      },
      rtl: {
        type: Boolean,
        required: false,
        default: false,
      },
      infinite: {
        type: Boolean,
        required: false,
        default: false,
      },
      aria: {
        required: false,
        type: Object,
        default: () => ({}),
      },
      clearOnBlur: {
        required: false,
        type: Boolean,
        default: true,
      },
      locale: {
        required: false,
        type: String,
        default: null,
      },
      fallbackLocale: {
        required: false,
        type: String,
        default: 'en',
      },
      searchFilter: {
        required: false,
        type: Function,
        default: null,
      },
      allowAbsent: {
        required: false,
        type: Boolean,
        default: false,
      },
    },
    setup(props, context)
    { 
      return resolveDeps(props, context, [
        useI18n,
        useValue,
        usePointer$1,
        useDropdown,
        useSearch,
        useData,
        useMultiselect,
        useOptions,
        useScroll,
        usePointer,
        useKeyboard,
        useClasses,
        useA11y,
      ])
    }
  };

const _hoisted_1 = ["id", "dir"];
const _hoisted_2 = ["tabindex", "aria-controls", "aria-placeholder", "aria-expanded", "aria-activedescendant", "aria-multiselectable", "role"];
const _hoisted_3 = ["type", "modelValue", "value", "autocomplete", "id", "aria-controls", "aria-placeholder", "aria-expanded", "aria-activedescendant", "aria-multiselectable"];
const _hoisted_4 = ["onKeyup", "aria-label"];
const _hoisted_5 = ["onClick"];
const _hoisted_6 = ["type", "modelValue", "value", "id", "autocomplete", "aria-controls", "aria-placeholder", "aria-expanded", "aria-activedescendant", "aria-multiselectable"];
const _hoisted_7 = ["innerHTML"];
const _hoisted_8 = ["id"];
const _hoisted_9 = ["id", "aria-label", "aria-selected"];
const _hoisted_10 = ["data-pointed", "onMouseenter", "onClick"];
const _hoisted_11 = ["innerHTML"];
const _hoisted_12 = ["aria-label"];
const _hoisted_13 = ["data-pointed", "data-selected", "onMouseenter", "onClick", "id", "aria-selected", "aria-label"];
const _hoisted_14 = ["data-pointed", "data-selected", "onMouseenter", "onClick", "id", "aria-selected", "aria-label"];
const _hoisted_15 = ["innerHTML"];
const _hoisted_16 = ["innerHTML"];
const _hoisted_17 = ["value"];
const _hoisted_18 = ["name", "value"];
const _hoisted_19 = ["name", "value"];
const _hoisted_20 = ["id"];

function render(_ctx, _cache, $props, $setup, $data, $options) {
  return ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("div", {
    ref: "multiselect",
    class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.container),
    id: $props.searchable ? undefined : $props.id,
    dir: $props.rtl ? 'rtl' : undefined,
    onFocusin: _cache[10] || (_cache[10] = (...args) => (_ctx.handleFocusIn && _ctx.handleFocusIn(...args))),
    onFocusout: _cache[11] || (_cache[11] = (...args) => (_ctx.handleFocusOut && _ctx.handleFocusOut(...args))),
    onKeyup: _cache[12] || (_cache[12] = (...args) => (_ctx.handleKeyup && _ctx.handleKeyup(...args))),
    onKeydown: _cache[13] || (_cache[13] = (...args) => (_ctx.handleKeydown && _ctx.handleKeydown(...args)))
  }, [
    (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", (0,vue__WEBPACK_IMPORTED_MODULE_0__.mergeProps)({
      class: _ctx.classList.wrapper,
      onMousedown: _cache[9] || (_cache[9] = (...args) => (_ctx.handleMousedown && _ctx.handleMousedown(...args))),
      ref: "wrapper",
      tabindex: _ctx.tabindex,
      "aria-controls": !$props.searchable ? _ctx.ariaControls : undefined,
      "aria-placeholder": !$props.searchable ? _ctx.ariaPlaceholder : undefined,
      "aria-expanded": !$props.searchable ? _ctx.isOpen : undefined,
      "aria-activedescendant": !$props.searchable ? _ctx.ariaActiveDescendant : undefined,
      "aria-multiselectable": !$props.searchable ? _ctx.ariaMultiselectable : undefined,
      role: !$props.searchable ? 'combobox' : undefined
    }, !$props.searchable ? _ctx.arias : {}), [
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Search "),
      ($props.mode !== 'tags' && $props.searchable && !$props.disabled)
        ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("input", (0,vue__WEBPACK_IMPORTED_MODULE_0__.mergeProps)({
            key: 0,
            type: $props.inputType,
            modelValue: _ctx.search,
            value: _ctx.search,
            class: _ctx.classList.search,
            autocomplete: $props.autocomplete,
            id: $props.searchable ? $props.id : undefined,
            onInput: _cache[0] || (_cache[0] = (...args) => (_ctx.handleSearchInput && _ctx.handleSearchInput(...args))),
            onKeypress: _cache[1] || (_cache[1] = (...args) => (_ctx.handleKeypress && _ctx.handleKeypress(...args))),
            onPaste: _cache[2] || (_cache[2] = (0,vue__WEBPACK_IMPORTED_MODULE_0__.withModifiers)((...args) => (_ctx.handlePaste && _ctx.handlePaste(...args)), ["stop"])),
            ref: "input",
            "aria-controls": _ctx.ariaControls,
            "aria-placeholder": _ctx.ariaPlaceholder,
            "aria-expanded": _ctx.isOpen,
            "aria-activedescendant": _ctx.ariaActiveDescendant,
            "aria-multiselectable": _ctx.ariaMultiselectable,
            role: "combobox"
          }, {
            ...$props.attrs,
            ..._ctx.arias,
          }), null, 16 /* FULL_PROPS */, _hoisted_3))
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Tags (with search) "),
      ($props.mode == 'tags')
        ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("div", {
            key: 1,
            class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.tags),
            "data-tags": ""
          }, [
            ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(true), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderList)(_ctx.iv, (option, i, key) => {
              return (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "tag", {
                option: option,
                handleTagRemove: _ctx.handleTagRemove,
                disabled: $props.disabled
              }, () => [
                ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("span", {
                  class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)([
                _ctx.classList.tag,
                option.disabled ? _ctx.classList.tagDisabled : null,
              ]),
                  tabindex: "-1",
                  onKeyup: (0,vue__WEBPACK_IMPORTED_MODULE_0__.withKeys)($event => (_ctx.handleTagRemove(option, $event)), ["enter"]),
                  key: key,
                  "aria-label": _ctx.ariaTagLabel(_ctx.localize(option[$props.label]))
                }, [
                  (0,vue__WEBPACK_IMPORTED_MODULE_0__.createTextVNode)((0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.localize(option[$props.label])) + " ", 1 /* TEXT */),
                  (!$props.disabled && !option.disabled)
                    ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("span", {
                        key: 0,
                        class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.tagRemove),
                        onClick: (0,vue__WEBPACK_IMPORTED_MODULE_0__.withModifiers)($event => (_ctx.handleTagRemove(option, $event)), ["stop"])
                      }, [
                        (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
                          class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.tagRemoveIcon)
                        }, null, 2 /* CLASS */)
                      ], 10 /* CLASS, PROPS */, _hoisted_5))
                    : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true)
                ], 42 /* CLASS, PROPS, HYDRATE_EVENTS */, _hoisted_4))
              ])
            }), 256 /* UNKEYED_FRAGMENT */)),
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", {
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.tagsSearchWrapper),
              ref: "tags"
            }, [
              (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Used for measuring search width "),
              (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
                class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.tagsSearchCopy)
              }, (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.search), 3 /* TEXT, CLASS */),
              (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Actual search input "),
              ($props.searchable && !$props.disabled)
                ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("input", (0,vue__WEBPACK_IMPORTED_MODULE_0__.mergeProps)({
                    key: 0,
                    type: $props.inputType,
                    modelValue: _ctx.search,
                    value: _ctx.search,
                    class: _ctx.classList.tagsSearch,
                    id: $props.searchable ? $props.id : undefined,
                    autocomplete: $props.autocomplete,
                    onInput: _cache[3] || (_cache[3] = (...args) => (_ctx.handleSearchInput && _ctx.handleSearchInput(...args))),
                    onKeypress: _cache[4] || (_cache[4] = (...args) => (_ctx.handleKeypress && _ctx.handleKeypress(...args))),
                    onPaste: _cache[5] || (_cache[5] = (0,vue__WEBPACK_IMPORTED_MODULE_0__.withModifiers)((...args) => (_ctx.handlePaste && _ctx.handlePaste(...args)), ["stop"])),
                    ref: "input",
                    "aria-controls": _ctx.ariaControls,
                    "aria-placeholder": _ctx.ariaPlaceholder,
                    "aria-expanded": _ctx.isOpen,
                    "aria-activedescendant": _ctx.ariaActiveDescendant,
                    "aria-multiselectable": _ctx.ariaMultiselectable,
                    role: "combobox"
                  }, {
                ...$props.attrs,
                ..._ctx.arias,
              }), null, 16 /* FULL_PROPS */, _hoisted_6))
                : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true)
            ], 2 /* CLASS */)
          ], 2 /* CLASS */))
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Single label "),
      ($props.mode == 'single' && _ctx.hasSelected && !_ctx.search && _ctx.iv)
        ? (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "singlelabel", {
            key: 2,
            value: _ctx.iv
          }, () => [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", {
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.singleLabel)
            }, [
              (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
                class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.singleLabelText)
              }, (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.localize(_ctx.iv[$props.label])), 3 /* TEXT, CLASS */)
            ], 2 /* CLASS */)
          ])
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Multiple label "),
      ($props.mode == 'multiple' && _ctx.hasSelected && !_ctx.search)
        ? (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "multiplelabel", {
            key: 3,
            values: _ctx.iv
          }, () => [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", {
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.multipleLabel),
              innerHTML: _ctx.multipleLabelText
            }, null, 10 /* CLASS, PROPS */, _hoisted_7)
          ])
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Placeholder "),
      ($props.placeholder && !_ctx.hasSelected && !_ctx.search)
        ? (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "placeholder", { key: 4 }, () => [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", {
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.placeholder),
              "aria-hidden": "true"
            }, (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)($props.placeholder), 3 /* TEXT, CLASS */)
          ])
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Spinner "),
      ($props.loading || _ctx.resolving)
        ? (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "spinner", { key: 5 }, () => [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.spinner),
              "aria-hidden": "true"
            }, null, 2 /* CLASS */)
          ])
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Clear "),
      (_ctx.hasSelected && !$props.disabled && $props.canClear && !_ctx.busy)
        ? (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "clear", {
            key: 6,
            clear: _ctx.clear
          }, () => [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
              "aria-hidden": "true",
              tabindex: "0",
              role: "button",
              "data-clear": "",
              "aria-roledescription": "❎",
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.clear),
              onClick: _cache[6] || (_cache[6] = (...args) => (_ctx.clear && _ctx.clear(...args))),
              onKeyup: _cache[7] || (_cache[7] = (0,vue__WEBPACK_IMPORTED_MODULE_0__.withKeys)((...args) => (_ctx.clear && _ctx.clear(...args)), ["enter"]))
            }, [
              (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
                class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.clearIcon)
              }, null, 2 /* CLASS */)
            ], 34 /* CLASS, HYDRATE_EVENTS */)
          ])
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Caret "),
      ($props.caret && $props.showOptions)
        ? (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "caret", { key: 7 }, () => [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.caret),
              onClick: _cache[8] || (_cache[8] = (...args) => (_ctx.handleCaretClick && _ctx.handleCaretClick(...args))),
              "aria-hidden": "true"
            }, null, 2 /* CLASS */)
          ])
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true)
    ], 16 /* FULL_PROPS */, _hoisted_2),
    (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Options "),
    (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", {
      class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.dropdown),
      tabindex: "-1"
    }, [
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "beforelist", { options: _ctx.fo }),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("ul", {
        class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.options),
        id: _ctx.ariaControls,
        role: "listbox"
      }, [
        ($props.groups)
          ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(true), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, { key: 0 }, (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderList)(_ctx.fg, (group, i, key) => {
              return ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("li", {
                class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.group),
                key: key,
                id: _ctx.ariaGroupId(group),
                "aria-label": _ctx.ariaGroupLabel(_ctx.localize(group[$props.groupLabel])),
                "aria-selected": _ctx.isSelected(group),
                role: "option"
              }, [
                (!group.__CREATE__)
                  ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("div", {
                      key: 0,
                      class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.groupLabel(group)),
                      "data-pointed": _ctx.isPointed(group),
                      onMouseenter: $event => (_ctx.setPointer(group, i)),
                      onClick: $event => (_ctx.handleGroupClick(group))
                    }, [
                      (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "grouplabel", {
                        group: group,
                        isSelected: _ctx.isSelected,
                        isPointed: _ctx.isPointed
                      }, () => [
                        (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
                          innerHTML: _ctx.localize(group[$props.groupLabel])
                        }, null, 8 /* PROPS */, _hoisted_11)
                      ])
                    ], 42 /* CLASS, PROPS, HYDRATE_EVENTS */, _hoisted_10))
                  : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
                (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("ul", {
                  class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.groupOptions),
                  "aria-label": _ctx.ariaGroupLabel(_ctx.localize(group[$props.groupLabel])),
                  role: "group"
                }, [
                  ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(true), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderList)(group.__VISIBLE__, (option, i, key) => {
                    return ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("li", {
                      class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.option(option, group)),
                      "data-pointed": _ctx.isPointed(option),
                      "data-selected": _ctx.isSelected(option) || undefined,
                      key: key,
                      onMouseenter: $event => (_ctx.setPointer(option)),
                      onClick: $event => (_ctx.handleOptionClick(option)),
                      id: _ctx.ariaOptionId(option),
                      "aria-selected": _ctx.isSelected(option),
                      "aria-label": _ctx.ariaOptionLabel(_ctx.localize(option[$props.label])),
                      role: "option"
                    }, [
                      (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "option", {
                        option: option,
                        isSelected: _ctx.isSelected,
                        isPointed: _ctx.isPointed,
                        search: _ctx.search
                      }, () => [
                        (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", null, (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.localize(option[$props.label])), 1 /* TEXT */)
                      ])
                    ], 42 /* CLASS, PROPS, HYDRATE_EVENTS */, _hoisted_13))
                  }), 128 /* KEYED_FRAGMENT */))
                ], 10 /* CLASS, PROPS */, _hoisted_12)
              ], 10 /* CLASS, PROPS */, _hoisted_9))
            }), 128 /* KEYED_FRAGMENT */))
          : ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(true), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, { key: 1 }, (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderList)(_ctx.fo, (option, i, key) => {
              return ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("li", {
                class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.option(option)),
                "data-pointed": _ctx.isPointed(option),
                "data-selected": _ctx.isSelected(option) || undefined,
                key: key,
                onMouseenter: $event => (_ctx.setPointer(option)),
                onClick: $event => (_ctx.handleOptionClick(option)),
                id: _ctx.ariaOptionId(option),
                "aria-selected": _ctx.isSelected(option),
                "aria-label": _ctx.ariaOptionLabel(_ctx.localize(option[$props.label])),
                role: "option"
              }, [
                (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "option", {
                  option: option,
                  isSelected: _ctx.isSelected,
                  isPointed: _ctx.isPointed,
                  search: _ctx.search
                }, () => [
                  (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", null, (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.localize(option[$props.label])), 1 /* TEXT */)
                ])
              ], 42 /* CLASS, PROPS, HYDRATE_EVENTS */, _hoisted_14))
            }), 128 /* KEYED_FRAGMENT */))
      ], 10 /* CLASS, PROPS */, _hoisted_8),
      (_ctx.noOptions)
        ? (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "nooptions", { key: 0 }, () => [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", {
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.noOptions),
              innerHTML: _ctx.localize($props.noOptionsText)
            }, null, 10 /* CLASS, PROPS */, _hoisted_15)
          ])
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (_ctx.noResults)
        ? (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "noresults", { key: 1 }, () => [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", {
              class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.noResults),
              innerHTML: _ctx.localize($props.noResultsText)
            }, null, 10 /* CLASS, PROPS */, _hoisted_16)
          ])
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      ($props.infinite && _ctx.hasMore)
        ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("div", {
            key: 2,
            class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.inifinite),
            ref: "infiniteLoader"
          }, [
            (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "infinite", {}, () => [
              (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("span", {
                class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.inifiniteSpinner)
              }, null, 2 /* CLASS */)
            ])
          ], 2 /* CLASS */))
        : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
      (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderSlot)(_ctx.$slots, "afterlist", { options: _ctx.fo })
    ], 2 /* CLASS */),
    (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Hacky input element to show HTML5 required warning "),
    ($props.required)
      ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("input", {
          key: 0,
          class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.fakeInput),
          tabindex: "-1",
          value: _ctx.textValue,
          required: ""
        }, null, 10 /* CLASS, PROPS */, _hoisted_17))
      : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
    (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Native input support "),
    ($props.nativeSupport)
      ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, { key: 1 }, [
          ($props.mode == 'single')
            ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("input", {
                key: 0,
                type: "hidden",
                name: $props.name,
                value: _ctx.plainValue !== undefined ? _ctx.plainValue : ''
              }, null, 8 /* PROPS */, _hoisted_18))
            : ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(true), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)(vue__WEBPACK_IMPORTED_MODULE_0__.Fragment, { key: 1 }, (0,vue__WEBPACK_IMPORTED_MODULE_0__.renderList)(_ctx.plainValue, (v, i) => {
                return ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("input", {
                  type: "hidden",
                  name: `${$props.name}[]`,
                  value: v,
                  key: i
                }, null, 8 /* PROPS */, _hoisted_19))
              }), 128 /* KEYED_FRAGMENT */))
        ], 64 /* STABLE_FRAGMENT */))
      : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
    (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Screen reader assistive text "),
    ($props.searchable && _ctx.hasSelected)
      ? ((0,vue__WEBPACK_IMPORTED_MODULE_0__.openBlock)(), (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementBlock)("div", {
          key: 2,
          class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.assist),
          id: _ctx.ariaAssist,
          "aria-hidden": "true"
        }, (0,vue__WEBPACK_IMPORTED_MODULE_0__.toDisplayString)(_ctx.ariaLabel), 11 /* TEXT, CLASS, PROPS */, _hoisted_20))
      : (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)("v-if", true),
    (0,vue__WEBPACK_IMPORTED_MODULE_0__.createCommentVNode)(" Create height for empty input "),
    (0,vue__WEBPACK_IMPORTED_MODULE_0__.createElementVNode)("div", {
      class: (0,vue__WEBPACK_IMPORTED_MODULE_0__.normalizeClass)(_ctx.classList.spacer)
    }, null, 2 /* CLASS */)
  ], 42 /* CLASS, PROPS, HYDRATE_EVENTS */, _hoisted_1))
}

script.render = render;
script.__file = "src/Multiselect.vue";




/***/ }),

/***/ "./resources/js/Pages/User/russia.min.json":
/*!*************************************************!*\
  !*** ./resources/js/Pages/User/russia.min.json ***!
  \*************************************************/
/***/ ((module) => {

module.exports = JSON.parse('["Москва","Абрамцево","Алабино","Апрелевка","Архангельское","Ашитково","Байконур","Бакшеево","Балашиха","Барыбино","Белоомут","Белые Столбы","Бородино","Бронницы","Быково","Валуево","Вербилки","Верея","Видное","Внуково","Вождь Пролетариата","Волоколамск","Вороново","Воскресенск","Восточный","Востряково","Высоковск","Голицино","Деденево","Дедовск","Джержинский","Дмитров","Долгопрудный","Домодедово","Дорохово","Дрезна","Дубки","Дубна","Егорьевск","Железнодорожный","Жилево","Жуковский","Загорск","Загорянский","Запрудная","Зарайск","Звенигород","Зеленоград","Ивантеевка","Икша","Ильинский","Истра","Калининград","Кашира","Керва","Климовск","Клин","Клязьма","Кожино","Кокошкино","Коломна","Колюбакино","Королев","Косино","Котельники","Красково","Красноармейск","Красногорск","Краснозаводск","Краснознаменск","Красный Ткач","Крюково","Кубинка","Купавна","Куровское","Лесной Городок","Ликино-Дулево","Лобня","Лопатинский","Лосино-Петровский","Лотошино","Лукино","Луховицы","Лыткарино","Львовский","Люберцы","Малаховка","Михайловское","Михнево","Можайск","Монино","Муханово","Мытищи","Нарофоминск","Нахабино","Некрасовка","Немчиновка","Новобратцевский","Новоподрезково","Ногинск","Обухово","Одинцово","Ожерелье","Озеры","Октябрьский","Опалиха","Орехово-Зуево","Павловский Посад","Первомайский","Пески","Пироговский","Подольск","Полушкино","Правдинский","Привокзальный","Пролетарский","Протвино","Пушкино","Пущино","Радовицкий","Раменское","Реутов","Решетниково","Родники","Рошаль","Рублево","Руза","Салтыковка","Северный","Сергиев Посад","Серебряные Пруды","Серпухов","Солнечногорск","Солнцево","Софрино","Старая Купавна","Старбеево","Ступино","Сходня","Талдом","Текстильщик","Темпы","Тишково","Томилино","Троицк","Туголесский Бор","Тучково","Уваровка","Удельная","Успенское","Фирсановка","Фосфоритный","Фрязино","Фряново","Химки","Хорлово","Хотьково","Черкизово","Черноголовка","Черусти","Чехов","Шарапово","Шатура","Шатурторф","Шаховская","Шереметьевский","Щелково","Щербинка","Электрогорск","Электросталь","Электроугли","Яхрома","Санкт-Петербург","Александровская","Бокситогорск","Большая Ижора","Будогощь","Вознесенье","Волосово","Волхов","Всеволожск","Выборг","Вырица","Высоцк","Гатчина","Дружная Горка","Дубровка","Ефимовский","Зеленогорск","Ивангород","Каменногорск","Кикерино","Кингисепп","Кириши","Кировск","Кобринское","Колпино","Коммунар","Кронштадт","Лисий Нос","Лодейное Поле","Ломоносов","Луга","Павловск","Парголово","Петродворец","Пикалёво","Подпорожье","Приозерск","Пушкин","Сестрорецк","Сланцы","Сосновый Бор","Тихвин","Тосно","Шлиссельбург","Адыгейск","Майкоп","Акташ","Акутиха","Алейск","Алтайский","Баево","Барнаул","Белово","Белокуриха","Белоярск","Бийск","Благовещенск","Боровлянка","Бурла","Бурсоль","Волчиха","Горно-Алтайск","Горняк","Ельцовка","Залесово","Заринск","Заток","Змеиногорск","Камень-на-Оби","Ключи","Кош-Агач","Красногорское","Краснощеково","Кулунда","Кытманово","Мамонтово","Новичиха","Новоалтайск","Онгудай","Павловск","Петропавловское","Поспелиха","Ребриха","Родино","Рубцовск","Славгород","Смоленское","Солонешное","Солтон","Староаллейское","Табуны","Тальменка","Топчиха","Троицкое","Турочак","Тюменцево","Угловское","Усть-Калманка","Усть-Кан","Усть-Кокса","Усть-Улаган","Усть-Чарышская Пристань","Хабары","Целинное","Чарышское","Шебалино","Шелаболиха","Шипуново","Айгунь","Архара","Белогорск","Благовещенск (Амурская обл.)","Бурея","Возжаевка","Екатеринославка","Ерофей Павлович","Завитинск","Зея","Златоустовск","Ивановка","Коболдо","Магдагачи","Новобурейский","Поярково","Райчихинск","Ромны","Свободный","Серышево","Сковородино","Стойба","Тамбовка","Тында","Шимановск","Экимчан","Ядрино","Амдерма","Архангельск","Березник","Вельск","Верхняя Тойма","Волошка","Вычегодский","Емца","Илеза","Ильинско-Подомское","Каргополь","Карпогоры","Кодино","Коноша","Коряжма","Котлас","Красноборск","Лешуконское","Мезень","Мирный","Нарьян-Мар","Новодвинск","Няндома","Онега","Пинега","Плесецк","Северодвинск","Сольвычегодск","Холмогоры","Шенкурск","Яренск","Астрахань","Ахтубинск","Верхний Баскунчак","Володарский","Енотаевка","Икряное","Камызяк","Капустин Яр","Красный Яр","Лиман","Началово","Харабали","Черный Яр","Аксаково","Амзя","Аскино","Баймак","Бакалы","Белебей","Белорецк","Бижбуляк","Бирск","Благовещенск","Большеустьикинское","Бураево","Верхнеяркеево","Верхние Киги","Верхние Татышлы","Верхний Авзян","Давлеканово","Дуван","Дюртюли","Ермекеево","Ермолаево","Зилаир","Зирган","Иглино","Инзер","Исянгулово","Ишимбай","Кананикольское","Кандры","Караидель","Караидельский","Киргиз-Мияки","Красноусольский","Кумертау","Кушнаренково","Малояз","Мелеуз","Месягутово","Мраково","Нефтекамск","Октябрьский","Раевский","Салават","Сибай","Старобалтачево","Старосубхангулово","Стерлибашево","Стерлитамак","Туймазы","Уфа","Учалы","Федоровка","Чекмагуш","Чишмы","Шаран","Янаул","Алексеевка","Белгород","Борисовка","Валуйки","Вейделевка","Волоконовка","Грайворон","Губкин","Ивня","Короча","Красногвардейское","Новый Оскол","Ракитное","Ровеньки","Старый Оскол","Строитель","Чернянка","Шебекино","Алтухово","Белая Березка","Белые Берега","Большое Полпино","Брянск","Бытошь","Выгоничи","Вышков","Гордеевка","Дубровка","Дятьково","Жирятино","Жуковка","Злынка","Ивот","Карачев","Клетня","Климово","Клинцы","Кокаревка","Комаричи","Красная Гора","Локоть","Мглин","Навля","Новозыбков","Погар","Почеп","Ржаница","Рогнедино","Севск","Стародуб","Суземка","Сураж","Трубчевск","Унеча","Бабушкин","Багдарин","Баргузин","Баянгол","Бичура","Выдрино","Гусиное Озеро","Гусиноозерск","Заиграево","Закаменск","Иволгинск","Илька","Кабанск","Каменск","Кижинга","Курумкан","Кырен","Кяхта","Монды","Мухоршибирь","Нижнеангарск","Орлик","Петропавловка","Романовка","Северобайкальск","Селенгинск","Сосново-Озерское","Таксимо","Турунтаево","Улан-Удэ","Хоринск","Александров","Андреево","Анопино","Бавлены","Балакирево","Боголюбово","Великодворский","Вербовский","Владимир","Вязники","Городищи","Гороховец","Гусевский","Гусь Хрустальный","Золотково","Иванищи","Камешково","Карабаново","Киржач","Ковров","Кольчугино","Красная Горбатка","Меленки","Муром","Петушки","Покров","Собинка","Судогда","Суздаль","Юрьев-Польский","Алексеевская","Алущевск","Быково","Волгоград","Волжский","Городище","Дубовка","Елань","Жирновск","Иловля","Калач-на-Дону","Камышин","Кириллов","Клетский","Котельниково","Котово","Кумылженская","Ленинск","Михайловка","Нехаевский","Николаевск","Новоаннинский","Новониколаевский","Ольховка","Палласовка","Рудня","Светлый Яр","Серафимович","Средняя Ахтуба","Сталинград","Старая Полтавка","Суровикино","Урюпинск","Фролово","Чернышковский","Бабаево","Белозерск","Великий Устюг","Верховажье","Вожега","Вологда","Вохтога","Вытегра","Грязовец","Кадников","Кадуй","Кичменгский Городок","Липин Бор","Никольск","Нюксеница","Сокол","Сямжа","Тарногский Городок","Тотьма","Устюжна","Харовск","Чагода","Череповец","Шексна","Шуйское","Анна","Бобров","Богучар","Борисоглебск","Бутурлиновка","Верхний Мамон","Верхняя Хава","Воробьевка","Воронеж","Грибановский","Давыдовка","Елань-Коленовский","Калач","Кантемировка","Лиски","Нижнедевицк","Новая Усмань","Новохоперск","Ольховатка","Острогожск","Павловск","Панино","Петропавловка","Поворино","Подгоренский","Рамонь","Репьевка","Россошь","Семилуки","Таловая","Терновка","Хохольский","Эртиль","нововоронеж","Агвали","Акуша","Ахты","Ачису","Бабаюрт","Бежта","Ботлих","Буйнакск","Вачи","Гергебиль","Гуниб","Дагестанские Огни","Дербент","Дылым","Ершовка","Избербаш","Карабудахкент","Карата","Каспийск","Касумкент","Кизилюрт","Кизляр","Кочубей","Кумух","Курах","Магарамкент","Маджалис","Махачкала","Мехельта","Новолакское","Рутул","Советское","Тарумовка","Терекли-Мектеб","Тлярата","Тпиг","Уркарах","Хасавюрт","Хив","Хунзах","Цуриб","Южно-Сухокумск","Биробиджан","Архиповка","Верхний Ландех","Вичуга","Гаврилов Посад","Долматовский","Дуляпино","Заволжск","Заречный","Иваново","Иваньковский","Ильинское-Хованское","Каминский","Кинешма","Комсомольск","Кохма","Лух","Палех","Пестяки","Приволжск","Пучеж","Родники","Савино","Сокольское","Тейково","Фурманов","Шуя","Южа","Юрьевец","Алексеевск","Алзамай","Алыгжер","Ангарск","Артемовский","Атагай","Байкал","Байкальск","Балаганск","Баяндай","Бирюсинск","Бодайбо","Большая Речка","Большой Луг","Бохан","Братск","Видим","Витимский","Вихоревка","Еланцы","Ербогачен","Железногорск-Илимский","Жигалово","Забитуй","Залари","Звездный","Зима","Иркутск","Казачинское","Качуг","Квиток","Киренск","Куйтун","Култук","Кутулик","Мама","Нижнеудинск","Оса","Саянск","Слюдянка","Тайшет","Тулун","Усолье-Сибирское","Усть-Илимск","Усть-Кут","Усть-Ордынский","Усть-Уда","Черемхово","Чунский","Шелехов","Баксан","Майский","Нальчик","Нарткала","Прохладный","Советское","Терек","Тырныауз","Чегем-Первый","Багратионовск","Балтийск","Гвардейск","Гурьевск","Гусев","Железнодорожный","Зеленоградск","Знаменск","Кёнигсберг","Калининград","Кенисберг","Краснознаменск","Мамоново","Неман","Нестеров","Озерск","Полесск","Правдинск","Светлогорск","Светлый","Славск","Советск","Черняховск","Аршань","Каспийский","Комсомольский","Малые Дербеты","Приютное","Советское","Троицкое","Утта","Цаган-Аман","Элиста","Юста","Яшалта","Яшкуль","Бабынино","Балабаново","Барятино","Белоусово","Бетлица","Боровск","Дугна","Дудоровский","Думиничи","Еленский","Жиздра","Износки","Калуга","Киров","Козельск","Кондрово","Людиново","Малоярославец","Медынь","Мещовск","Мосальск","Обнинск","Перемышль","Спас-Деменск","Сухиничи","Таруса","Ульяново","Ферзиково","Хвастовичи","Юхнов","Атласово","Аянка","Большерецк","Вилючинск","Елизово","Ильпырский","Каменское","Кировский","Ключи","Крапивная","Мильково","Никольское","Озерновский","Оссора","Палана","Парень","Пахачи","Петропавловск-Камчатский","Тигиль","Тиличики","Усть-Большерецк","Усть-Камчатск","Амбарный","Беломорск","Валаам","Вирандозеро","Гирвас","Деревянка","Идель","Ильинский","Импалахти","Калевала","Кемь","Кестеньга","Кондопога","Костомукша","Лахденпохья","Лоухи","Медвежьегорск","Муезерский","Олонец","Петрозаводск","Питкяранта","Повенец","Пряжа","Пудож","Сегежа","Сортавала","Софпорог","Суоярви","Анжеро-Судженск","Барзас","Белово","Белогорск","Березовский","Грамотеино","Гурьевск","Ижморский","Итатский","Калтан","Кедровка","Кемерово","Киселевск","Крапивинский","Ленинск-Кузнецкий","Мариинск","Междуреченск","Мыски","Новокузнецк","Осинники","Прокопьевск","Промышленная","Тайга","Таштагол","Тисуль","Топки","Тяжинский","Юрга","Яшкино","Яя","Арбаж","Аркуль","Белая Холуница","Богородское","Боровой","Верхошижемье","Вятские Поляны","Зуевка","Каринторф","Кикнур","Кильмезь","Киров","Кирово-Чепецк","Кирс","Кобра","Котельнич","Кумены","Ленинское","Луза","Малмыж","Мураши","Нагорск","Нема","Нововятск","Нолинск","Омутнинск","Опарино","Оричи","Пижанка","Подосиновец","Санчурск","Свеча","Слободской","Советск","Суна","Тужа","Уни","Уржум","Фаленки","Халтурин","Юрья","Яранск","Абезь","Айкино","Верхняя Инта","Визинга","Водный","Вожаель","Воркута","Вуктыл","Гешарт","Елецкий","Емва","Заполярный","Ижма","Инта","Ираель","Каджером","Кажым","Кожым","Койгородок","Корткерос","Кослан","Объячево","Печора","Сосногорск","Сыктывкар","Троицко-Печерск","Усинск","Усогорск","Усть-Кулом","Усть-Цильма","Ухта","Антропово","Боговарово","Буй","Волгореченск","Галич","Горчуха","Зебляки","Кадый","Кологрив","Кострома","Красное-на-Волге","Макарьев","Мантурово","Нерехта","Нея","Островское","Павино","Парфентьево","Поназырево","Солигалич","Судиславль","Сусанино","Чухлома","Шарья","Шемятино","Абинск","Абрау-Дюрсо","Анапа","Апшеронск","Армавир","Архипо-Осиповка","Афипский","Ахтырский","Ачуево","Белореченск","Верхнебаканский","Выселки","Геленджик","Гиагинская","Горячий Ключ","Джубга","Динская","Ейск","Ильский","Кабардинка","Калинино","Калининская","Каменномостский","Каневская","Кореновск","Красноармейская","Краснодар","Кропоткин","Крыловская","Крымск","Курганинск","Кущевская","Лабинск","Лениградская","Майкоп","Мостовской","Новороссийск","Отрадная","Павловская","Приморско-Ахтарск","Северская","Славянск-на-Кубани","Сочи","Староминская","Старощербиновская","Тбилисская","Темрюк","Тимашевск","Тихорецк","Туапсе","Тульский","Усть-Лабинск","Шовгеновский"," Железногорск","Абаза","Абакан","Абан","Агинское","Артемовск","Аскиз","Ачинск","Байкит","Балахта","Балыкса","Белый Яр","Бельтырский","Бея","Бискамжа","Боготол","Боград","Богучаны","Большая Мурта","Большой Улуй","Бородино","Ванавара","Верхнеимбатск","Горячегорск","Дзержинское","Дивногорск","Диксон","Дудинка","Емельяново","Енисейск","Ермаковское","Заозерный","Зеленогорск","Игарка","Идринское","Иланский","Ирбейское","Казачинское","Канск","Каратузское","Караул","Кежма","Кодинск","Козулька","Копьево","Краснотуранск","Красноярск","Курагино","Лесосибирск","Минусинск","Мотыгино","Назарово","Нижний Ингаш","Новоселово","Норильск","Партизанское","Пировское","Саяногорск","Северо-Енисейский","Сосновоборск","Тасеево","Таштып","Тура","Туруханск","Тюхтет","Ужур","Усть-Авам","Уяр","Хатанга","Черемушки","Черногорск","Шалинское","Шарыпово","Шира","Шушенское","Варгаши","Глядянское","Далматово","Каргаполье","Катайск","Кетово","Курган","Куртамыш","Лебяжье","Макушино","Мишкино","Мокроусово","Петухово","Половинное","Сафакулево","Целинное","Шадринск","Шатрово","Шумиха","Щучье","Юргамыш","Альменево","Белая","Большое Солдатское","Глушково","Горшечное","Дмитриев-Льговский","Железногорск","Золотухино","Касторное","Конышевка","Коренево","Курск","Курчатов","Кшенский","Льгов","Мантурово","Медвенка","Обоянь","Поныри","Пристень","Прямицыно","Рыльск","Суджа","Тим","Фатеж","Хомутовка","Черемисиново","Щигры","Грязи","Данхов","Доброе","Долгоруково","Елец","Задонск","Измалково","Казинка","Лебедянь","Лев Толстой","Липецк","Тербуны","Усмань","Хлевное","Чаплыгин","Анадырь","Атка","Балыгычан","Беринговский","Билибино","Большевик","Ванкарем","Иульитин","Кадыкчан","Лаврентия","Магадан","Мыс Шмидта","Ола","Омонск","Омсукчан","Палатка","Певек","Провидения","Сеймчан","Синегорье","Сусуман","Усть-Белая","Усть-Омчуг","Эвенск","Эгвекинот","Ягодное","Волжск","Дубовский","Звенигово","Йошкар-Ола","Килемары","Козьмодемьянск","Куженер","Мари-Турек","Медведево","Морки","Новый Торьял","Оршанка","Параньга","Сернур","Советский","Юрино","Ардатов","Атюрьево","Атяшево","Большие Березники","Большое Игнатово","Выша","Ельники","Зубова Поляна","Инсар","Кадошкино","Кемля","Ковылкино","Комсомольский","Кочкурово","Краснослободск","Лямбирь","Ромоданово","Рузаевка","Саранск","Старое Шайгово","Темников","Теньгушево","Торбеево","Чамзинка","Апатиты","Африканда","Верхнетуломский","Заозерск","Заполярный","Зареченск","Зашеек","Зеленоборский","Кандалакша","Кильдинстрой","Кировск","Ковдор","Кола","Конда","Мончегорск","Мурманск","Мурмаши","Никель","Оленегорск","Полярные Зори","Полярный","Североморск","Снежногорск","Умба","Ардатов","Арзамас","Арья","Балахна","Богородск","Большереченск","Большое Болдино","Большое Козино","Большое Мурашкино","Большое Пикино","Бор","Бутурлино","Вад","Варнавино","Васильсурск","Вахтан","Вача","Велетьма","Ветлуга","Виля","Вознесенское","Володарск","Воротынец","Ворсма","Воскресенское","Выездное","Выкса","Гагино","Гидроторф","Горбатов","Горбатовка","Городец","Горький","Дальнее Константиново","Дзержинск","Дивеево","Досчатое","Заволжье","Катунки","Керженец","Княгинино","Ковернино","Красные Баки","Кстово","Кулебаки","Лукоянов","Лысково","Навашино","Нижний Новгород","Павлово","Первомайск","Перевоз","Пильна","Починки","Саров","Сергач","Сеченово","Сосновское","Спасское","Тонкино","Тоншаево","Уразовка","Урень","Чкаловск","Шаранга","Шатки","Шахунья","Анциферово","Батецкий","Большая Вишера","Боровичи","Валдай","Волот","Деманск","Зарубино","Кресцы","Любытино","Малая Вишера","Марево","Мошенское","Новгород","Окуловка","Парфино","Пестово","Поддорье","Сольцы","Старая Русса","Хвойное","Холм","Чудово","Шимск","Баган","Барабинск","Бердск","Биаза","Болотное","Венгерово","Довольное","Завьялово","Искитим","Карасук","Каргат","Колывань","Краснозерское","Крутиха","Куйбышев","Купино","Кыштовка","Маслянино","Михайловский","Мошково","Новосибирск","Ордынское","Северное","Сузун","Татарск","Тогучин","Убинское","Усть-Тарка","Чаны","Черепаново","Чистоозерное","Чулым","Береговой","Большеречье","Большие Уки","Горьковское","Знаменское","Исилькуль","Калачинск","Колосовка","Кормиловка","Крутинка","Любинский","Марьяновка","Муромцево","Называевск","Нижняя Омка","Нововаршавка","Одесское","Оконешниково","Омск","Павлоградка","Полтавка","Русская Поляна","Саргатское","Седельниково","Таврическое","Тара","Тевриз","Тюкалинск","Усть-Ишим","Черлак","Шербакуль","Абдулино","Адамовка","Айдырлинский","Акбулак","Аккермановка","Асекеево","Беляевка","Бугуруслан","Бузулук","Гай","Грачевка","Домбаровский","Дубенский","Илек","Ириклинский","Кувандык","Курманаевка","Матвеевка","Медногорск","Новоорск","Новосергиевка","Новотроицк","Октябрьское","Оренбург","Орск","Первомайский","Переволоцкий","Пономаревка","Саракташ","Светлый","Северное","Соль-Илецк","Сорочинск","Ташла","Тоцкое","Тюльган","Шарлык","Энергетик","Ясный","Болхов","Верховье","Глазуновка","Дмитровск-Орловский","Долгое","Залегощь","Змиевка","Знаменское","Колпны","Красная Заря","Кромы","Ливны","Малоархангельск","Мценск","Нарышкино","Новосиль","Орел","Покровское","Сосково","Тросна","Хомутово","Хотынец","Шаблыкино","Башмаково","Беднодемьяновск","Беково","Белинский","Бессоновка","Вадинск","Верхозим","Городище","Евлашево","Земетчино","Золотаревка","Исса","Каменка","Колышлей","Кондоль","Кузнецк","Лопатино","Малая Сердоба","Мокшан","Наровчат","Неверкино","Нижний Ломов","Никольск","Пачелма","Пенза","Русский Камешкир","Сердобск","Сосновоборск","Сура","Тамала","Шемышейка","Барда","Березники","Большая Соснова","Верещагино","Гайны","Горнозаводск","Гремячинск","Губаха","Добрянка","Елово","Зюкайка","Ильинский","Карагай","Керчевский","Кизел","Коса","Кочево","Красновишерск","Краснокамск","Кудымкар","Куеда","Кунгур","Лысьва","Ныроб","Нытва","Октябрьский","Орда","Оса","Оханск","Очер","Пермь","Соликамск","Суксун","Уинское","Усолье","Усть-Кишерть","Чайковский","Частые","Чердынь","Чернореченский","Чернушка","Чусовой","Юрла","Юсьва","Анучино","Арсеньев","Артем","Артемовский","Большой Камень","Валентин","Владивосток","Высокогорск","Горные Ключи","Горный","Дальнегорск","Дальнереченск","Зарубино","Кавалерово","Каменка","Камень-Рыболов","Кировский","Лазо","Лесозаводск","Лучегорск","Михайловка","Находка","Новопокровка","Ольга","Партизанск","Пограничный","Покровка","Русский","Самарга","Славянка","Спасск-Дальний","Терней","Уссурийск","Фокино","Хасан","Хороль","Черниговка","Чугуевка","Яковлевка","Бежаницы","Великие Луки","Гдов","Дедовичи","Дно","Заплюсье","Идрица","Красногородское","Кунья","Локня","Невель","Новоржев","Новосокольники","Опочка","Остров","Палкино","Печоры","Плюсса","Порхов","Псков","Пустошка","Пушкинские Горы","Пыталово","Себеж","Струги-Красные","Усвяты","Азов","Аксай","Алмазный","Аютинск","Багаевский","Батайск","Белая Калитва","Боковская","Большая Мартыновка","Вешенская","Волгодонск","Восход","Гигант","Горняцкий","Гуково","Донецк","Донской","Дубовское","Егорлыкская","Жирнов","Заветное","Заводской","Зверево","Зерноград","Зимовники","Кагальницкая","Казанская","Каменоломни","Каменск-Шахтинский","Кашары","Коксовый","Константиновск","Красный Сулин","Куйбышево","Матвеев Курган","Мигулинская","Миллерово","Милютинская","Морозовск","Новочеркасск","Новошахтинск","Обливская","Орловский","Песчанокопское","Покровское","Пролетарск","Ремонтное","Родионово-Несветайская","Ростов-на-Дону","Сальск","Семикаракорск","Таганрог","Тарасовский","Тацинский","Усть-Донецкий","Целина","Цимлянск","Чалтырь","Чертково","Шахты","Шолоховский","Александро-Невский","Горняк","Гусь Железный","Елатьма","Ермишь","Заречный","Захарово","Кадом","Касимов","Кораблино","Милославское","Михайлов","Пителино","Пронск","Путятино","Рыбное","Ряжск","Рязань","Сапожок","Сараи","Сасово","Скопин","Спас-Клепики","Спасск-Рязанский","Старожилово","Ухолово","Чучково","Шацк","Шилово","Алексеевка","Безенчук","Богатое","Богатырь","Большая Глущица","Большая Черниговка","Борское","Волжский","Жигулевск","Зольное","Исаклы","Камышла","Кинель","Кинель-Черкасы","Клявлино","Кошки","Красноармейское","Красный Яр","Куйбышев","Нефтегорск","Новокуйбышевск","Октябрьск","Отрадный","Пестравка","Похвистнево","Приволжье","Самара","Сургут (Самарская обл.)","Сызрань","Тольятти","Хворостянка","Чапаевск","Челно-Вершины","Шентала","Шигоны","Александров Гай","Аркадак","Аткарск","Базарный Карабулак","Балаково","Балашов","Балтай","Возрождение","Вольск","Воскресенское","Горный","Дергачи","Духовницкое","Екатериновка","Ершов","Заречный","Ивантеевка","Калининск","Каменский","Красноармейск","Красный Кут","Лысые Горы","Маркс","Мокроус","Новоузенск","Новые Бурасы","Озинки","Перелюб","Петровск","Питерка","Пугачев","Ровное","Романовка","Ртищево","Самойловка","Саратов","Степное","Татищево","Турки","Хвалынск","Энгельс","Абый","Алдан","Амга","Батагай","Бердигестях","Беркакит","Бестях","Борогонцы","Верхневилюйск","Верхнеколымск","Верхоянск","Вилюйск","Витим","Власово","Жиганск","Зырянка","Кангалассы","Канкунский","Ленск","Майя","Менкеря","Мирный","Нерюнгри","Нычалах","Нюрба","Олекминск","Покровск","Сангар","Саскылах","Среднеколымск","Сунтар","Тикси","Усть-Мая","Усть-Нера","Хандыга","Хонуу","Черский","Чокурдах","Чурапча","Якутск","Александровск-Сахалинский","Анбэцу","Анива","Бошняково","Быков","Вахрушев","Взморье","Гастелло","Горнозаводск","Долинск","Ильинский","Катангли","Корсаков","Курильск","Макаров","Невельск","Ноглики","Оха","Поронайск","Северо-Курильск","Смирных","Томари","Тымовское","Углегорск","Холмск","Шахтерск","Южно-Курильск","Южно-Сахалинск","Алапаевск","Алтынай","Арамиль","Артемовский","Арти","Асбест","Ачит","Байкалово","Басьяновский","Белоярский","Березовский","Богданович","Буланаш","Верхний Тагил","Верхняя Пышма","Верхняя Салда","Верхняя Синячиха","Верхняя Сысерть","Верхняя Тура","Верхотурье","Висим","Волчанск","Воронцовка","Гари","Дегтярск","Екатеринбург","Ертарский","Заводоуспенское","Зыряновский","Зюзельский","Ивдель","Изумруд","Ирбит","Ис","Каменск-Уральский","Камышлов","Карпинск","Карпунинский","Качканар","Кировград","Краснотурьинск","Красноуральск","Красноуфимск","Кушва","Лесной","Михайловск","Невьянск","Нижние Серги","Нижний Тагил","Нижняя Салда","Нижняя Тура","Новая Ляля","Новоуральск","Оус","Первоуральск","Полевской","Пышма","Ревда","Реж","Свердловск","Североуральск","Серов","Сосьва","Среднеуральск","Сухой Лог","Сысерть","Таборы","Тавда","Талица","Тугулым","Туринск","Туринская Слобода","Алагир","Ардон","Беслан","Бурон","Владикавказ","Дигора","Моздок","Орджоникидзе","Чикола","Велиж","Верхнеднепровский","Ворга","Вязьма","Гагарин","Глинка","Голынки","Демидов","Десногорск","Дорогобуж","Духовщина","Екимовичи","Ельня","Ершичи","Издешково","Кардымово","Красный","Монастырщина","Новодугино","Починок","Рославль","Рудня","Сафоново","Смоленск","Сычевка","Угра","Хиславичи","Холм-Жирковский","Шумячи","Ярцево","Александровское","Арзгир","Благодарный","Буденновск","Георгиевск","Дивное","Домбай","Донское","Ессентуки","Железноводск","Зеленокумск","Изобильный","Иноземцево","Ипатово","Карачаевск","Кисловодск","Кочубеевское","Красногвардейское","Курсавка","Левокумское","Минеральные Воды","Невинномысск","Нефтекумск","Новоалександровск","Новоалександровская","Новопавловск","Новоселицкое","Преградная","Пятигорск","Светлоград","Солнечнодольск","Ставрополь","Степное","Теберда","Усть-Джегута","Хабез","Черкесск","Бондари","Гавриловка Вторая","Жердевка","Знаменка","Инжавино","Кирсанов","Котовск","Мичуринск","Мордово","Моршанск","Мучкапский","Первомайский","Петровское","Пичаево","Рассказово","Ржакса","Староюрьево","Тамбов","Токаревка","Уварово","Умет","Агрыз","Азнакаево","Аксубаево","Актюбинский","Алексеевское","Альметьевск","Альметьевск","Апастово","Арск","Бавлы","Базарные Матаки","Балтаси","Богатые Сабы","Брежнев","Бугульма","Буинск","Васильево","Верхний Услон","Высокая Гора","Дербешкинский","Елабуга","Заинск","Зеленодольск","Казань","Камское Устье","Карабаш","Куйбышев","Кукмод","Кукмор","Лаишево","Лениногорск","Мамадыш","Менделеевск","Мензелинск","Муслюмово","Набережные Челны","Нижнекамск","Новошешминск","Нурлат","Пестрецы","Рыбная Слобода","Сарманово","Старое Дрожжаное","Тетюши","Чистополь","Андреаполь","Бежецк","Белый","Белый Городок","Березайка","Бологое","Васильевский Мох","Выползово","Вышний Волочек","Жарковский","Западная Двина","Заречье","Зубцов","Изоплит","Калашниково","Калинин","Калязин","Кашин","Кесова Гора","Кимры","Конаково","Красный Холм","Кувшиново","Лесное","Лихославль","Максатиха","Молоково","Нелидово","Оленино","Осташков","Пено","Рамешки","Ржев","Сандово","Селижарово","Сонково","Спирово","Старица","Тверь","Торжок","Торопец","Удомля","Фирово","Александровское","Асино","Бакчар","Батурино","Белый Яр","Зырянское","Итатка","Каргасок","Катайга","Кожевниково","Колпашево","Кривошеино","Мельниково","Молчаново","Парабель","Первомайское","Подгорное","Северск","Стрежевой","Томск","Тымск","Ак-Довурак","Бай Хаак","Кызыл","Самагалтай","Сарыг-Сеп","Суть-Холь","Тоора-Хем","Туран","Тээли","Хову-Аксы","Чадан","Шагонар","Эрзин","Агеево","Алексин","Арсеньево","Барсуки","Бегичевский","Белев","Богородицк","Болохово","Велегож","Венев","Волово","Горелки","Донской","Дубна","Епифань","Ефремов","Заокский","Казановка","Кимовск","Киреевск","Куркино","Ленинский","Новомосковск","Одоев","Плавск","Суворов","Тула","Узловая","Щекино","Ясногорск","Абатский","Аксарка","Армизонское","Аромашево","Белоярский","Бердюжье","Большое Сорокино","Вагай","Викулово","Винзили","Голышманово","Губкинский","Заводопетровский","Заводоуковск","Излучинск","Исетское","Ишим","Казанское","Казым-Мыс","Когалым","Кондинское","Красноселькуп","Лабытнанги","Ларьяк","Мегион","Мужи","Муравленко","Надым","Находка","Нефтеюганск","Нижневартовск","Нижняя Тавда","Новый Уренгой","Ноябрьск","Нягань","Октябрьское","Омутинский","Радужный","Салехард","Сладково","Советский","Сургут","Тазовский","Тарко-Сале","Тобольск","Тюмень","Уват","Унъюган","Упорово","Урай","Ханты-Мансийск","Юрибей","Ялуторовск","Яр-Сале","Ярково","Алнаши","Балезино","Вавож","Воткинск","Глазов","Грахово","Дебесы","Завьялово","Игра","Ижевск","Кама","Камбарка","Каракулино","Кез","Кизнер","Киясово","Красногорское","Можга","Сарапул","Селты","Сюмси","Ува","Устинов","Шаркан","Юкаменское","Якшур-Бодья","Яр","Базарный Сызган","Барыш","Большое Нагаткино","Вешкайма","Глотовка","Димитровград","Игнатовка","Измайлово","Инза","Ишеевка","Канадей","Карсун","Кузоватово","Майна","Новая Малыкла","Новоспасское","Павловка","Радищево","Сенгилей","Старая Кулатка","Старая Майна","Сурское","Тереньга","Ульяновск","Чердаклы","Аксай","Дарьинское","Деркул","Джамбейты","Джаныбек","Казталовка","Калмыково","Каратобе","Переметное","Сайхин","Уральск","Федоровка","Фурманово","Чапаев","Амурск","Аян","Березовый","Бикин","Бира","Биракан","Богородское","Болонь","Ванино","Волочаевка Вторая","Высокогорный","Вяземский","Горный","Гурское","Дормидонтовка","Заветы Ильича","Известковый","Иннокентьевка","Комсомольск-на-Амуре","Ленинское","Нелькан","Николаевск-на-Амуре","Облучье","Охотск","Переяславка","Смидович","Советская Гавань","Софийск","Троицкое","Тугур","Хабаровск","Чегдомын","Чумикан","Абакан","Саяногорск","Аган","Игрим","Излучинск","Лангепас","Лянтор","Мегион","Нефтеюганск","Нижневартовск","Нягань","Покачи","Приобье","Пыть-Ях","Радужный","Сургут","Урай","Ханты-Мансийск","Югорск","Агаповка","Аргаяш","Аша","Бакал","Бреды","Варна","Верхнеуральск","Верхний Уфалей","Еманжелинск","Златоуст","Карабаш","Карталы","Касли","Катав-Ивановск","Копейск","Коркино","Кунашак","Куса","Кыштым","Магнитогорск","Миасс","Озерск","Октябрьское","Пласт","Сатка","Сим","Снежинск","Трехгорный","Троицк","Увельский","Уйское","Усть-Катав","Фершампенуаз","Чебаркуль","Челябинск","Чесма","Южно-Уральск","Юрюзань","Аргун","Грозный","Гудермез","Малгобек","Назрань","Наурская","Ножай-Юрт","Орджоникидзевская","Советское","Урус-Мартан","Шали","Агинское","Аксеново-Зиловское","Акша","Александровский Завод","Амазар","Арбагар","Атамановка","Балей","Борзя","Букачача","Газимурский Завод","Давенда","Дарасун","Дровяная","Дульдурга","Жиндо","Забайкальск","Итака","Калга","Карымское","Кличка","Ключевский","Кокуй","Краснокаменск","Красный Чикой","Кыра","Моготуй","Могоча","Нерчинск","Нерчинский Завод","Нижний Часучей","Оловянная","Первомайский","Петровск-Забайкальский","Приаргунск","Сретенск","Тупик","Улеты","Хилок","Чара","Чернышевск","Чита","Шелопугино","Шилка","Алатырь","Аликово","Батырева","Буинск","Вурнары","Ибреси","Канаш","Киря","Комсомольское","Красноармейское","Красные Четаи","Кугеси","Мариинский Посад","Моргауши","Новочебоксарск","Порецкое","Урмары","Цивильск","Чебоксары","Шемурша","Шумерля","Ядрин","Яльчики","Янтиково","Анадырь","Билибино","Губкинский","Заполярный","Муравленко","Надым","Новый Уренгой","Ноябрьск","Пуровск","Салехард","Тарко","Андропов","Берендеево","Большое Село","Борисоглебский","Брейтово","Бурмакино","Варегово","Волга","Гаврилов Ям","Данилов","Любим","Мышкино","Некрасовское","Новый Некоуз","Переславль-Залесский","Пошехонье-Володарск","Ростов","Рыбинск","Тутаев","Углич","Ярославль"]');

/***/ }),

/***/ "./resources/js/Pages/User/sfere.json":
/*!********************************************!*\
  !*** ./resources/js/Pages/User/sfere.json ***!
  \********************************************/
/***/ ((module) => {

module.exports = JSON.parse('["Бытовое обслуживание","Медицина","Юриспруденция","Промышленность","Малый и средний бизнес","Наука","Культура","Социально-психологическая деятельность","Искусство и прикладное творчество","Информационные системы","Армия и силовые структуры","Морское дело","Транспорт","Государственная служба (чиновники)","Жилищно-коммунальное хозяйство","Сельское хозяйство","Инспекторские службы","Политика и общественная деятельность","Строительство","Электротехника","Финансовая деятельность","Образование и воспитание","Торговля","Менеджмент (управление)","Информационные технологии и связь","Энергетика","Химия и биология","Средства массовой информации","Добыча и переработка ископаемых","Аналитическая деятельность и прогнозирование","Природопользование (экология и охрана)","Администрирование","Туризм, отдых, путешествия","Спорт","Развлекательный и шоу-бизнес","Металлообработка","Лесное хозяйство","Деревообработка","Производство продуктов питания","Моделирование и производство одежды и обуви","Полиграфия и рекламное дело","Общественное питание и ресторанный бизнес"]');

/***/ })

}]);