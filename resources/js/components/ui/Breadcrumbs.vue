<template>
  <!--
      Пустой <header> на каждой странице — лишний узел для скринридера,
      поэтому при отсутствии уровней не рендеримся вовсе.
  -->
  <header v-if="items.length" class="u-crumbs" :aria-label="$t('common.breadcrumbs')">
    <ol class="u-crumbs__list">
      <li v-for="(item, index) in items" :key="item.key" class="u-crumbs__item">
        <!--
            Последний элемент — текущая страница: он не ссылка.
            Так же, как в PageHeader он несёт название раздела, поэтому
            дублируется только в момент перехода между страницами
            одного раздела.
        -->
        <router-link
          v-if="item.to && index < items.length - 1"
          class="u-crumbs__link"
          :to="item.to"
          >{{ item.title }}</router-link
        >
        <span v-else class="u-crumbs__current" aria-current="page">{{ item.title }}</span>

        <span v-if="index < items.length - 1" class="u-crumbs__sep" aria-hidden="true">/</span>
      </li>
    </ol>
  </header>
</template>

<script setup>
/**
 * Хлебные крошки.
 *
 * Зачем: на страницах вроде «Пользователи → Иванов Иван Иванович» или
 * «Курсы → Конструкция самолёта» не было нигде видно, где мы находимся
 * и как вернуться назад. Заголовок страницы повторял только последний
 * уровень, а кнопки «назад» у списков не было.
 *
 * Путь собирается из `meta.breadcrumbs` маршрута: в нём перечислены
 * уровни для конкретной страницы, поэтому страница не угадывает
 * структуру раздела.
 *
 * В meta лежат КЛЮЧИ перевода (`{ key: 'app.menu.users' }`), а не
 * вызовы $t: meta вычисляется при загрузке модуля маршрутов, вне
 * компонента, где $t не существует — страница падала с
 * «$t is not defined».
 */
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const route = useRoute()
const router = useRouter()
const { t } = useI18n({ useScope: 'global' })

const items = computed(() => {
  const declared = route.meta?.breadcrumbs

  if (!Array.isArray(declared) || declared.length === 0) {
    return []
  }

  const result = []

  for (const crumb of declared) {
    const key = typeof crumb === 'string' ? crumb : crumb?.key

    if (!key) continue

    // Уровень без ссылки — текущая запись (например, «Редактировать»
    // на карточке пользователя). Название берём из ключа.
    if (typeof crumb === 'string' || !crumb.to) {
      result.push({ key, title: t(key, key), to: null })
      continue
    }

    // Ссылка проверяется разрешением маршрута: если путь не существует
    // (например, параметр не подставился), уровень пропускается, а не
    // превращается в ссылку, которая даст 404.
    if (!router.resolve(crumb.to).matched.length) continue

    result.push({ key, title: t(key, key), to: crumb.to })
  }

  return result
})
</script>

<style scoped>
.u-crumbs {
  padding: var(--sp-2) 0 var(--sp-1);
}

.u-crumbs__list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--sp-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.u-crumbs__item {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  min-width: 0;
}

.u-crumbs__link {
  color: var(--c-primary);
  text-decoration: none;
  font-size: 0.875rem;
}

.u-crumbs__link:hover,
.u-crumbs__link:focus-visible {
  text-decoration: underline;
}

.u-crumbs__current {
  color: var(--c-text-secondary);
  font-size: 0.875rem;
  /* Название записи бывает длинным — обрезаем, чтобы не ломать
     шапку на мобильном. */
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 48ch;
}

.u-crumbs__sep {
  color: var(--c-text-muted, var(--c-text-secondary));
  opacity: 0.6;
}
</style>
