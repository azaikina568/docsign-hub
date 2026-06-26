import { createApp } from 'vue'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { i18n } from './shared/i18n'

createApp(App).use(createPinia()).use(i18n).use(VueQueryPlugin).use(router).mount('#app')
