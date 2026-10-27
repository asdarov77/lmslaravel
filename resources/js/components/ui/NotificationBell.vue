<template>
  <v-menu
    v-model="open"
    :close-on-content-click="false"
    location="bottom end"
    offset="8"
  >
    <template #activator="{ props }">
      <v-btn
        v-bind="props"
        icon="mdi-bell-outline"
        variant="text"
        size="small"
        :aria-label="$t('notifications.title')"
        data-test="notification-bell"
      >
        <v-badge
          v-if="unread > 0"
          :content="unread > 99 ? '99+' : unread"
          color="error"
          inline
        />
      </v-btn>
    </template>

    <v-card min-width="320" max-width="400" class="notif">
      <v-card-title class="notif__head">
        <span>{{ $t('notifications.title') }}</span>
        <v-btn
          v-if="unread > 0"
          variant="text"
          size="small"
          @click="markAll"
        >
          {{ $t('notifications.markAll') }}
        </v-btn>
      </v-card-title>

      <v-list v-if="items.length" class="notif__list" density="compact">
        <v-list-item
          v-for="item in items"
          :key="item.id"
          :to="item.link || undefined"
          :class="{ 'notif__item--unread': !item.read }"
          @click="open = false"
        >
          <template #prepend>
            <v-icon :icon="iconFor(item.type)" size="18" />
          </template>
          <v-list-item-title>{{ item.title }}</v-list-item-title>
          <v-list-item-subtitle v-if="item.body">{{ item.body }}</v-list-item-subtitle>
        </v-list-item>
      </v-list>

      <v-card-text v-else class="notif__empty">
        {{ $t('notifications.empty') }}
      </v-card-text>
    </v-card>
  </v-menu>
</template>

<script>
import $api from '../../api/httpClient';

/**
 * Колокольчик уведомлений.
 *
 * Отдельно от тостов: тост — мгновенная реакция на действие, уведомление —
 * событие, которое пользователь может прочитать позже.
 */
export default {
  name: 'NotificationBell',

  data: () => ({
    open: false,
    items: [],
    unread: 0,
  }),

  methods: {
    iconFor(type) {
      return {
        success: 'mdi-check-circle-outline',
        error: 'mdi-alert-circle-outline',
        warning: 'mdi-alert-outline',
        info: 'mdi-information-outline',
      }[type] || 'mdi-bell-outline';
    },

    async load() {
      try {
        const res = await $api.get('/api/notifications');
        const data = res?.data?.data ?? res?.data ?? {};

        this.items = data.items || [];
        this.unread = data.unread || 0;
      } catch (e) {
        // Уведомления — не критично: ошибка не должна ломать шапку.
      }
    },

    async markAll() {
      try {
        await $api.post('/api/notifications/read-all');
        this.items = this.items.map((i) => ({ ...i, read: true }));
        this.unread = 0;
      } catch (e) {
        // Молча: уведомления не критичны.
      }
    },
  },

  watch: {
    open(isOpen) {
      if (isOpen) this.load();
    },
  },

  mounted() {
    this.load();
  },
};
</script>

<style scoped>
.notif__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.notif__list {
  max-height: 320px;
  overflow-y: auto;
}

.notif__item--unread {
  background: var(--c-primary-soft);
}

.notif__empty {
  text-align: center;
  color: var(--c-text-muted);
}
</style>
