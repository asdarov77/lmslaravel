<template>
  <v-container fluid>
    <v-card>
      <v-list>
        <v-list-item>
          <v-list-item-content>
            <v-list-item-title class="headline"
              >Сервер приложений недоступен. Обратитесь к системному администратору</v-list-item-title
            >
          </v-list-item-content>
        </v-list-item>
      </v-list>
    </v-card>
  </v-container>
</template>

<script>
export default {
  name: 'InternalServerError'
}
</script>
