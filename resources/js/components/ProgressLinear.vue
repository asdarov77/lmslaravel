<template>
    <div>
      <div v-if="loading" class="fade-in">{{ label }}</div>
      <transition name="fade">
        <div class="progress-container" v-if="loading">
          <v-icon class="loading-icon" :class="{ 'rotate': progress > 0 }">{{ icon }}</v-icon>
          <v-progress-linear
            :value="progress"
            :color="color"
            :styles="{
              backgroundImage: 'linear-gradient(to right, green ' + progress + '%, red ' + progress + '%)',
            }"
            height="5"
          ></v-progress-linear>
        </div>
      </transition>
      <v-btn @click="uploadData">{{ buttonText }}</v-btn>
    </div>
  </template>
  
  <script>
  export default {
    props: {
      loading: {
        type: Boolean,
        required: true,
      },
      progress: {
        type: Number,
        required: true,
      },
      color: {
        type: String,
        default: 'primary',
      },
      label: {
        type: String,
        default: 'Loading...',
      },
      icon: {
        type: String,
        default: 'mdi-loading',
      },
      buttonText: {
        type: String,
        default: 'Upload Data',
      },
    },

  mounted() {
    // Listen for progress events from the FileUploader component
    this.$root.$on('progress', (progress) => {
      this.progress = progress;
    });
  }

  };
  </script>
  
  <style>
  .fade-enter-active,
  .fade-leave-active {
    transition: opacity 0.5s;
  }
  
  .fade-enter,
  .fade-leave-to {
    opacity: 0;
  }
  
  .progress-container {
    display: flex;
    align-items: center;
  }
  
  .loading-icon {
    margin-right: 10px;
    transition: transform 0.5s;
  }
  
  .rotate {
    animation: rotate 1s infinite linear;
  }
  
  @keyframes rotate {
    from {
      transform: rotate(0deg);
    }
    to {
      transform: rotate(360deg);
    }
  }
  </style>
  