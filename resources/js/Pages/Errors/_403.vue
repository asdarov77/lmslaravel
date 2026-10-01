<template>
  <v-container fluid>
    <v-card>
      <v-list>
        <!-- В Vuetify 3 нет v-list-item-content: текст кладётся прямо в
             v-list-item, а обёртка VListItemContent удалена. -->
        <v-list-item>
          <v-list-item-title class="headline">В доступе отказано</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-card>
  </v-container>
</template>

<script>
export default {
  name: 'Forbidden'
}
</script>
