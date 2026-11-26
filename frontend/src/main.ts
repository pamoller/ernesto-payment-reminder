import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './style.css'
import LoginView from './components/LoginView.vue'
import SubscriptionListView from './components/SubscriptionListView.vue'
import SubscriptionDetailView from './components/SubscriptionDetailView.vue'
import { auth } from './auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: LoginView, meta: { public: true } },
    { path: '/subscriptions', component: SubscriptionListView },
    { path: '/subscriptions/new', component: SubscriptionDetailView },
    { path: '/subscriptions/:id', component: SubscriptionDetailView },
    { path: '/', redirect: '/subscriptions' },
  ],
})

router.beforeEach(async (to) => {
  if (!auth.initialized) await auth.loadSession()
  if (!to.meta.public && !auth.authenticated) return '/login'
  if (to.meta.public && auth.authenticated) return '/subscriptions'
  return true
})

createApp(App).use(router).mount('#app')
