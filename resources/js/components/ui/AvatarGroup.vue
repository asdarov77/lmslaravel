<template>
  <div class="u-avatars" :class="`u-avatars--${size}`">
    <v-tooltip
      v-for="(person, i) in visible"
      :key="person.id ?? i"
      :text="person.name || person.email || ''"
      location="top"
    >
      <template #activator="{ props: tip }">
        <span class="u-avatars__item" v-bind="tip">
          <v-avatar :size="px" :color="colorFor(person, i)" class="u-avatars__avatar">
            <img v-if="person.avatar" :src="person.avatar" :alt="person.name || ''" />
            <span v-else class="u-avatars__initials">{{ initials(person) }}</span>
          </v-avatar>
        </span>
      </template>
    </v-tooltip>

    <span v-if="rest > 0" class="u-avatars__item">
      <v-avatar :size="px" class="u-avatars__avatar u-avatars__more">+{{ rest }}</v-avatar>
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Группа аватаров с переполнением «+N».
 *
 * В списках групп, на курсе и во вложениях нужно показать несколько
 * человек компактно. Раньше выводили первые 3–4 аватара и теряли
 * остальных без следа, либо растягивали ряд на всю ширину. Здесь
 * лишние свёрнуты в «+N», а наведение на каждый показывает имя.
 *
 * Цвет аватара детерминирован по имени: один и тот же человек всегда
 * одного цвета, даже между перезагрузками и разными списками — это
 * помогает узнавать людей в интерфейсе.
 */
const props = defineProps({
  /** [{ id, name, avatar, email }] */
  people: { type: Array, default: () => [] },
  max: { type: Number, default: 4 },
  /** sm | md | lg */
  size: { type: String, default: 'md' },
})

const visible = computed(() => props.people.slice(0, props.max))
const rest = computed(() => Math.max(0, props.people.length - props.max))

const px = computed(() => ({ sm: 26, md: 34, lg: 42 }[props.size] ?? 34))

const palette = [
  '#1a5fb4', '#0d9488', '#9a6400', '#b3261e',
  '#6d28d9', '#0f766e', '#c2410c', '#4338ca',
]

function initials(person) {
  const source = (person.name || person.email || '?').trim()
  const parts = source.split(/\s+/)
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
  return source.slice(0, 2).toUpperCase()
}

function colorFor(person, index) {
  const key = person.name || person.email || String(index)
  let hash = 0
  for (let i = 0; i < key.length; i += 1) hash = (hash * 31 + key.charCodeAt(i)) >>> 0
  return palette[hash % palette.length]
}
</script>

<style scoped>
.u-avatars {
  display: inline-flex;
  align-items: center;
  padding-left: var(--sp-2);
}

.u-avatars__item {
  margin-left: calc(-1 * var(--sp-2));
  z-index: 1;
  transition: transform var(--dur-fast) var(--ease);
}

.u-avatars__item:hover {
  z-index: 2;
  transform: translateY(-2px);
}

.u-avatars__avatar {
  border: 2px solid var(--c-surface);
  color: var(--c-on-primary);
  font-weight: var(--fw-semibold);
}

.u-avatars__initials {
  font-size: var(--fs-xs);
  letter-spacing: 0.02em;
}

.u-avatars__more {
  background: var(--c-surface-3);
  color: var(--c-text-secondary);
  font-size: var(--fs-xs);
}
</style>
