<template>
  <div class="fluent-mail-app">
    <div class="fluent-mail-main-menu-items">
      <el-menu
          :default-active="active"
          :router="true"
          class="fluent-mail-navigation"
          mode="horizontal"
      >
        <el-menu-item :route="{ name: 'dashboard' }" index="dashboard" v-html="logo"/>

        <el-menu-item
            v-for="item in items"
            :key="item.route"
            :index="item.route"
            :route="{ name: item.route }"
            v-html="item.title"
        />
      </el-menu>
    </div>

    <div class="fluent-mail-body">
      <router-view :key="$route.name"></router-view>
    </div>
  </div>
</template>

<script type="text/babel">
export default {
  name: 'FluentMailApplication',
  data() {
    return {
      logo: '',
      items: [],
      active: null
    }
  },
  watch: {
    '$route'(to, from) {
      if (this.$route.name) {
        this.setActive();
      }
    }
  },
  methods: {
    defaultRoutes() {
      return [
        {
          route: 'connections',
          title: this.$t('Settings')
        },
        {
          route: 'test',
          title: this.$t('Email Test')
        },
        {
          route: 'logs',
          title: this.$t('Email Logs')
        },
        {
          route: 'notification_settings',
          title: this.$t('Alerts')
        },
        {
          route: 'support',
          title: this.$t('About')
        },
        {
          route: 'docs',
          title: this.$t('Documentation')
        },
        {
          route: 'rr_config',
          title: this.$t('Round Robin Configurations')
        }
      ];
    },
    setMenus() {
      this.items = this.applyFilters('fluentmail_top_menus', this.defaultRoutes());
      this.setActive();
    },
    setActive() {
      this.active = this.$route.meta.parent || this.$route.name;
    }
  },
  computed: {
    brandLogo() {
      const src = this.appVars.brand_logo;
      return `<img style="width:140px;" src="${src}" />`;
    }
  },
  created() {
    jQuery('.update-nag,.notice:not(.fluentsmtp_urgent), #wpbody-content > .updated, #wpbody-content > .error').remove();
    this.logo = `<div class='logo'>${this.brandLogo}</div>`;
    this.setMenus();
  }
};
</script>
