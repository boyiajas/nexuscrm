import { createRouter, createWebHistory } from 'vue-router';

import Login from './views/auth/Login.vue';
import Register from './views/auth/Register.vue';
import Dashboard from './views/Dashboard.vue';
import Clients from './views/Clients.vue';
import Campaigns from './views/Campaigns.vue';
import CampaignShow from './views/CampaignShow.vue';
import WhatsappTemplatePreview from './views/WhatsappTemplatePreview.vue';
import Chat from './views/Chat.vue';
import AuditLog from './views/AuditLog.vue';
import Settings from './views/Settings.vue';
import Departments from './views/Departments.vue';
import Users from './views/Users.vue';
import WhatsAppFlows from './views/WhatsAppFlows.vue';

const routes = [
  { path: '/login', name: 'login', component: Login },
  { path: '/register', name: 'register', component: Register },
  {
    path: '/',
    component: () => import('./components/layout/MainLayout.vue'),
    children: [
      { path: '', name: 'dashboard', component: Dashboard },
      { path: 'clients', name: 'clients', component: Clients },
      { path: 'campaigns', name: 'campaigns', component: Campaigns },
      { path: 'campaigns/:id', name: 'campaign.show', component: CampaignShow },
      { path: '/campaigns/:id/whatsapp-template/:templateSid?', name: 'WhatsappTemplatePreview', component: WhatsappTemplatePreview },
      { path: 'chat', name: 'chat', component: Chat },
      { path: 'audit-log', name: 'audit-log', component: AuditLog },
      { path: 'settings', name: 'settings', component: Settings },
      { path: 'departments', name: 'departments', component: Departments },
      { path: 'users', name: 'users', component: Users },
      { path: 'automation/whatsapp-flows', name: 'whatsapp-flows', component: WhatsAppFlows },
      
    ],

    meta: { requiresAuth: true },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Simple auth guard
router.beforeEach(async (to, from, next) => {
  const publicPages = ['/login', '/register'];
  const authRequired = !publicPages.includes(to.path);

  const storedUser = localStorage.getItem('nexus_user');
  const isLoggedIn = !!storedUser;

  if (authRequired && !isLoggedIn) {
    return next({ name: 'login' });
  }

  if ((to.name === 'login' || to.name === 'register') && isLoggedIn) {
    return next({ name: 'dashboard' });
  }

  next();
});

export default router;
