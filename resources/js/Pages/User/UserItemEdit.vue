<template>
  <div class="u-page">
    <FormCard
      :title="`${t('user.editTitle')} ${user.fio || ''}`"
      :busy="form.submitting.value"
      @submit="submitForm"
      @cancel="cancel"
    >
      <FormField :label="t('user.labelFio')" name="fio" :error="form.errors.fio" :required="form.isRequired('fio')">
        <v-text-field v-bind="form.bind('fio')" autocomplete="name" />
      </FormField>

      <!--
        Роль только для чтения. Поле было v-combobox, то есть свободный
        текст, и PATCH /api/user/{id} писал его прямо в users.role — а это
        один из двух источников роли наряду с role_user. В итоге любой, у
        кого есть users.update, мог вписать «Администратор» и стать
        суперадмином, минуя chroll. Теперь роль назначается только через
        «Назначение ролей» (users.permissions), а здесь она показана
        для чтения.
      -->
      <FormField :label="t('users.role')" :hint="t('users.roleHintReadonly')">
        <v-text-field :model-value="roleLabel" readonly />
      </FormField>

      <FormField :label="t('user.labelTelefon')" name="phonenumber" :error="form.errors.phonenumber">
        <MaskedField
          v-bind="form.bind('phonenumber')"
          preset="phone"
          :label="t('user.labelTelefon')"
          :clearable="true"
        />
      </FormField>

      <FormField :label="t('user.city')" name="city">
        <MultiSelectField
          v-model="form.values.city"
          :options="cityOptions"
          :multiple="false"
          :placeholder="t('user.city')"
          :no-results-text="t('user.cityNotFound')"
        />
      </FormField>

      <FormField :label="t('user.labelStrana')" name="country">
        <v-text-field v-bind="form.bind('country')" autocomplete="country-name" />
      </FormField>

      <FormField :label="t('user.labelOrganizaciya')" name="organization">
        <v-text-field v-bind="form.bind('organization')" autocomplete="organization" />
      </FormField>

      <FormField :label="t('user.labelDolzhnost')" name="position">
        <v-text-field v-bind="form.bind('position')" />
      </FormField>

      <FormField :label="t('user.labelVoinskoe-zvanie')" name="rank">
        <v-select v-bind="form.bind('rank')" :items="rankOptions" />
      </FormField>

      <FormField :label="t('user.labelSfera-deyatelnosti')" name="spfere">
        <v-combobox v-bind="form.bind('spfere')" :items="sfereOptions" />
      </FormField>

      <FormField :label="t('user.labelSpecializaciya')" name="specialization">
        <v-text-field v-bind="form.bind('specialization')" />
      </FormField>

      <!--
        v-autocomplete, а не v-select: список групп виртуализируется, а
        сам список разрастается (десятки групп). У v-select без filter
        поле ввода не фильтрует, и нужную группу приходилось искать долгой
        прокруткой. v-autocomplete ищет по item-title из коробки.
      -->
      <FormField :label="t('user.labelGruppa')" name="group_id" :error="form.errors.group_id">
        <v-autocomplete
          v-bind="form.bind('group_id')"
          :items="allGroups"
          item-value="id"
          item-title="groupname"
          :no-data-text="t('common.noGroupsFound')"
          clearable
        />
      </FormField>

      <div class="d-flex justify-space-between align-center mt-2">
        <!-- Права вынесены в отдельный раздел /permissions: там свои
             правила доступа для администратора и инструктора. -->
        <v-btn
          variant="tonal"
          color="primary"
          prepend-icon="mdi-shield-key-outline"
          :disabled="!canSeePermissions || !userId"
          :to="{ name: 'permissions.manage', query: { user: userId } }"
        >
          {{ t('users.list.permissions') }}
        </v-btn>
      </div>
    </FormCard>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import FormCard from '../../components/ui/FormCard.vue'
import FormField from '../../components/ui/FormField.vue'
import MaskedField from '../../components/ui/fields/MaskedField.vue'
import MultiSelectField from '../../components/ui/fields/MultiSelectField.vue'
import useForm from '../../composables/useForm'
import useLeaveGuard from '../../composables/useLeaveGuard'
import { required, minLength, phone } from '../../composables/validation/rules'
import jsonData from '../User/russia.min.json'
import sferejsonData from '../User/sfere.json'
import { toast } from '../../composables/useToast'

/**
 * Редактирование профиля пользователя.
 *
 * Переведено на useForm (Фаза 2). Что изменилось по сути:
 *  - поля лежат в схеме формы, а не пишутся прямо в store.state.User
 *    (раньше правка поля сразу меняла «сохранённого» пользователя, и
 *    отмена не возвращала исходные значения);
 *  - телефон маскируется MaskedField вместо свободного текста;
 *  - город — единый MultiSelectField под токенами, а не отдельный
 *    @vueform/multiselect с 40 строками переопределений цветов;
 *  - уход с несохранённой формой спрашивает подтверждение.
 */
const props = defineProps({
  idEdit: { type: [Number, String], required: true },
})

const { t } = useI18n()
const store = useStore()
const router = useRouter()

const rankOptions = ['капитан', 'майор', 'старший лейтенант', 'лейтенант']

const cityOptions = computed(() =>
  (Array.isArray(jsonData) ? jsonData : []).map((city) => ({ value: city, label: city }))
)

const sfereOptions = Array.isArray(sferejsonData) ? sferejsonData : []

const allGroups = computed(() => store.state.User.allGroups || [])
const user = computed(() => store.state.User.user || {})
const userId = computed(() => user.value.id ?? null)

/** Раздел прав открыт администратору и инструктору. */
const canSeePermissions = computed(() => store.getters['Auth/can']?.('users.permissions', 'users.view') ?? false)

/**
 * Роль для показа. Берём связь role_user (её назначает chroll), а если
 * её нет — строку users.role, в которой значения исторически записаны
 * по-разному: «Обучаемый» или «trainee».
 */
const roleLabel = computed(() => {
  const roles = user.value?.roles
  if (Array.isArray(roles) && roles.length > 0) {
    return roles
      .map((role) => (typeof role === 'object' && role !== null ? role.rolename || role.slug : role))
      .filter(Boolean)
      .join(', ')
  }

  return user.value?.role || '—'
})

/** Приводит group_id к number|null — иначе в bigint уходит объект и прилетает 500. */
const normalizeGroupId = (value) => {
  if (value === null || value === undefined || value === '') return null
  const raw = typeof value === 'object' ? value.id : value
  if (raw === null || raw === undefined || raw === '') return null
  const id = Number(raw)

  return Number.isInteger(id) && id > 0 ? id : null
}

const form = useForm({
  schema: {
    fio: { initial: '', rules: [required(t('register.fioRequired'))] },
    phonenumber: { initial: '', rules: [minLength(6), phone(t('user.phoneInvalid'))] },
    city: { initial: '', rules: [] },
    country: { initial: '', rules: [] },
    organization: { initial: '', rules: [] },
    position: { initial: '', rules: [] },
    rank: { initial: '', rules: [] },
    spfere: { initial: '', rules: [] },
    specialization: { initial: '', rules: [] },
    group_id: { initial: null, rules: [] },
  },

  draftKey: `user.edit.${props.idEdit}`,

  onSubmit: async ({ values }) => {
    const data = {
      ...user.value,
      fio: values.fio,
      phonenumber: values.phonenumber,
      city: values.city,
      country: values.country,
      organization: values.organization,
      position: values.position,
      rank: values.rank,
      spfere: values.spfere,
      specialization: values.specialization,
      group_id: normalizeGroupId(values.group_id),
    }

    await store.dispatch('User/updateUser', { id: props.idEdit, data })

    toast.success(t('user.updated'))
    await router.back()
  },
})

const { confirmLeave } = useLeaveGuard(() => form.dirty.value, { message: t('common.leaveConfirm') })

onMounted(async () => {
  store.dispatch('User/fetchUser', props.idEdit)

  // Группы в выпадающем «Группа» не появлялись при прямом заходе на
  // страницу: fetchGroups звал только ряд страниц, а здесь его не было.
  store.dispatch('User/fetchGroups').catch(() => {
    toast.warning(t('user.groupsUnavailable'))
  })

  // Заполняем форму загруженным пользователем. Значения в store больше не
  // правятся «на лету», поэтому после загрузки снимаем dirty — иначе
  // подтверждение ухода спрашивало бы сразу, без единого изменения.
  await Promise.all([store.dispatch('User/fetchUser', props.idEdit).catch(() => {})])

  const loaded = store.state.User.user || {}
  form.reset({
    fio: loaded.fio ?? '',
    phonenumber: loaded.phonenumber ?? '',
    city: loaded.city ?? '',
    country: loaded.country ?? '',
    organization: loaded.organization ?? '',
    position: loaded.position ?? '',
    rank: loaded.rank ?? '',
    spfere: loaded.spfere ?? '',
    specialization: loaded.specialization ?? '',
    group_id: loaded.group_id ?? null,
  })
})

const submitForm = () => {
  form.submit()
}

const cancel = async () => {
  if (await confirmLeave()) router.back()
}

// Тесты дёргают эти методы — в <script setup> наружу ничего не торчит.
defineExpose({ submitForm, cancel, form, normalizeGroupId })
</script>