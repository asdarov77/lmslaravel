  <template>
    <div>
      <svg :width="size" :height="size" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="45" :stroke-width="strokeWidth" :stroke-dasharray="circumference" :stroke-dashoffset="progressOffset" :stroke="progressColor" fill="none"/>
        
        <text x="50" y="40" text-anchor="middle" dominant-baseline="middle" style="font-size: 16px;">{{ successPercentage }}%</text>
        <text x="50" y="60" text-anchor="middle" dominant-baseline="middle" style="font-size: 16px;">{{ progressIcon }}</text>
      </svg>
      <!-- <span>{{ progressIcon }}</span> -->
    </div>
  </template>

  <script>
  import { defineComponent, computed } from 'vue'
  
  export default defineComponent({
    props: {
      size: {
        type: Number,
        default: 120
      },
      strokeWidth: {
        type: Number,
        default: 8
      },
      progressColor: {
        type: String,
        default: 'green'
      },
      successPercentage: {
        type: Number,
        default: 80
      },
      progressIcon: {
        type: String,
        default: '\u263A'
      }
    },
    setup(props) {
      const circumference = computed(() => 2 * Math.PI * 45)
      const progressOffset = computed(() => circumference.value * (1 - props.successPercentage / 100))
  
      return {
        circumference,
        progressOffset
      }
    }
  })
  </script>