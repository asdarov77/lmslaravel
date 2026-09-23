<template>
  <div>
    
    <v-menu>
      
      <template v-slot:activator="{ props }">
        <p v-if="this.$store.state.Auth.accessToken">          
          <v-avatar
            color="grey-darken-3"
            image="https://avataaars.io/?avatarStyle=Transparent&topType=ShortHairShortCurly&accessoriesType=Prescription02&hairColor=Black&facialHairType=Blank&clotheType=Hoodie&clotheColor=White&eyeType=Default&eyebrowType=DefaultNatural&mouthType=Default&skinColor=Light"
          ></v-avatar>        
          <v-btn  @click="show = !show" v-bind="props" min-width="200">
          {{this.$store.state.Auth.user.fio }}          
          <v-icon>{{ show ? "mdi-chevron-down" : "mdi-chevron-up" }}</v-icon>
        </v-btn>
      </p>
      </template>
    
      <v-list dense nav variant="text">
        <!-- style="margin-right: auto" -->
        <v-list-item
          v-for="([title,icon,link], i) in items"
          :key="i"          
          :title="title"          
          :to="link"
          :prepend-icon="icon"
        >          
        </v-list-item>
      </v-list>
    </v-menu>
  </div>
</template>

<script>



//class="d-flex justify-space-around"

export default {
  data: () => ({
    show: true,
    items: [
    ["Профиль пользователя",'mdi-account-multiple-outline',"/my" ],    
    ["О пользователе",'mdi-account-multiple-outline',"/my"],
    ["Оценки",'mdi-account-multiple-outline',"/my"],
    ["Настройки",'mdi-account-multiple-outline',"/my"],
    ["Выход",'mdi-account-multiple-outline',"/logout"],
    ],
  }),
};
</script>