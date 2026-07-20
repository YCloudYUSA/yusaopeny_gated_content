<template>
  <div class="gated-containerV2 my-40-20 px--20-10 custom-dashboard" v-if="content">
    <div class="listing-header">
      <h2 class="title text-gray cachet-book-24-20" v-if="title !== 'none'">{{ title }}</h2>
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
};
</script>
