<template>
  <div v-if="hasPermission(operations, contentType)">
    <slot></slot>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'

export default {
  name: 'PermissionWrapper',
  props: {
    contentType: {
      type: String
    },
    operations: {
      type: Array
    }
  },
  computed: {
    ...mapGetters('Auth', ['hasPermission'])
  }
}
</script>
