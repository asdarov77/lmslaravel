<!-- <template>
  
  <v-btn @click="$router.go(-1)"> back</v-btn>
      <v-table dense class="elevation-1 cursor-pointer">
          <thead>
            <tr>
              <th class="text-left">No</th>
              <th class="text-left">Titles</th>
            </tr>
          </thead>
      
          <tbody>
            <tr v-for="(_titles,index) in titles" :key="index">
              <td>{{ index}}</td>        
              <td><a @click="showTitles(_titles)">{{ _titles}}</a> </td>
            </tr>
          </tbody>
        </v-table>
      
      
      </template> -->
      
      <script>
      // import axios from "axios";
      // export default {
      //   data() {
      //     return {          
      //       titles: [],     
      //     };
          
      //   },
      // props:{
      //     nameLyx : String
      // },
      //   async mounted() {
      //      //console.log(this.nameLyx, 'nameLyx')
          
      //       axios.get("api/tree/"+ this.nameLyx).then((response) => {
      //     //    //console.log(response.data);
      //         this.titles = response.data;
      //       });
      //   },
      //   methods: {    
      //     showTitles(_titles){
      //        console.log(_titles, 'имя _titles');            
            
      //         //this.$router.push({ name: 'auk.lern', params: { course : this.nameLyx,title : _titles, } })
      //         // console.log(response.data);
      //       //this.titles = response.data;         
      //      }
      //    },
      // };
      </script>
  