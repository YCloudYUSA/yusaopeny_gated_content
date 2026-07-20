<template>
  <div class="gated-containerV2 my-40-20 px--20-10 custom-dashboard" v-if="content">
    <div class="listing-header">
      <h2 class="title text-gray" v-if="title">{{ title }}</h2>
    </div>
    <div class="custom-dashboard__content" v-html="content"></div>
  </div>
</template>

<script>
export default {
  name: 'CustomDashboard',
  props: {
    title: {
      type: String,
      default: 'Custom Dashboard',
    },
    content: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      executedContent: null,
    };
  },
  mounted() {
    this.executeEmbeddedScripts();
  },
  updated() {
    this.executeEmbeddedScripts();
  },
  methods: {
    // Browsers don't execute <script> tags inserted via v-html/innerHTML,
    // so re-create them as real script elements to run the embed's JS.
    // Guarded to only run once per distinct content value, so unrelated
    // re-renders don't re-trigger embeds with side effects (timers, etc).
    executeEmbeddedScripts() {
      if (this.content === this.executedContent) {
        return;
      }
      const container = this.$el.querySelector && this.$el.querySelector('.custom-dashboard__content');
      if (!container) {
        return;
      }
      this.executedContent = this.content;
      container.querySelectorAll('script').forEach((oldScript) => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach((attr) => {
          newScript.setAttribute(attr.name, attr.value);
        });
        newScript.text = oldScript.textContent;
        oldScript.parentNode.replaceChild(newScript, oldScript);
      });
    },
  },
};
</script>
