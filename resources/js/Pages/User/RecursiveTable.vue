  <template>
    <v-table class="table">
      <thead v-if="isRoot">
        <tr>
          <!-- <th>id</th> -->
          <th>title</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="item in items" :key="item.id">
          <tr >
            <!-- <td>{{ item.id }}</td> -->
            <td :style="{ textAlign: level === 3 ? 'left' : 'center', 'width': 'calc(100% - ' + item.level * 40 + 'px)' }"  >
                
                
                {{ item.title }}
            </td>
          </tr>
          <tr v-if="item.children">
            <td>
              <!-- <table> -->
                <!-- <tbody> -->
                  <recursive-table :items="item.children" :is-root="false" :level="level + 1" />
                <!-- </tbody> -->
              <!-- </table> -->
            </td>
          </tr>
        </template>
      </tbody>
    </v-table>
  </template>



<script>
export default {
  name: 'RecursiveTable',
  props: {
    items: {
      type: Array,
      required: true
    },
    isRoot: {
      type: Boolean,
      default: true
    },
    level: {
      type: Number,
      default: 0
    }

  }
}
</script>


<!-- <style>
.table {
  border-collapse: collapse;
  width: 100%;
}

.table th,
.table td {
  border: 1px solid #ddd;
  padding: 8px;
  text-align: left;
}

.table th {
  background-color: #f2f2f2;
}
</style> -->

<style>
table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  border: 1px solid #ccc;
  padding: 10px;
}

th {
  background-color: #f2f2f2;
}

.recursive-table td {
    width: 100%;
}
td:first-child {
  font-weight: bold;
}

</style>