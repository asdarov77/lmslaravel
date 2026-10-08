

<template>
<h1>Date</h1>
<Datepicker 
v-model="picked" 
inputFormat='yyyy-MM-dd'
>
</Datepicker>

</template>


<script>
import Datepicker from "vue3-datepicker"
import { ref } from 'vue'
export default {
    components :{
        Datepicker,
    },
  data() {
    return {
      picked: new Date(),
      
    };
  },
  methods: {
    selectDate(curDate)
    {        
    }
  },
  watch:{
    picked(value,oldValue) {        
    }

  }
};
</script>