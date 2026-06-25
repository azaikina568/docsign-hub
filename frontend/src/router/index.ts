import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/pages/documents/DocumentsListPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/documents/new',
      name: 'document-create',
      component: () => import('@/pages/documents/DocumentCreatePage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/documents/:id',
      name: 'document-detail',
      component: () => import('@/pages/documents/DocumentDetailPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/signing-requests',
      name: 'signing-requests',
      component: () => import('@/pages/signing/SigningRequestsPage.vue'),
      meta: { requiresAuth: true },
    },
    // Публичные страницы по ссылке из письма — без auth (внешний участник может быть не залогинен).
    {
      path: '/signing/:token',
      name: 'sign',
      component: () => import('@/pages/signing/SignPage.vue'),
    },
    {
      path: '/verify-email/:id/:hash',
      name: 'verify-email',
      component: () => import('@/pages/auth/VerifyEmailPage.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/auth/LoginPage.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/pages/auth/RegisterPage.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/pages/NotFoundPage.vue'),
    },
  ],
})

// Гард доступа: перед первой навигацией поднимаем сессию из сохранённого токена (init идемпотентен).
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
