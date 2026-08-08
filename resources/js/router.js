import { createRouter, createWebHistory } from 'vue-router';

const Login = () => import('./views/auth/Login.vue');
const Register = () => import('./views/auth/Register.vue');
const Dashboard = () => import('./views/Dashboard.vue');
const Clients = () => import('./views/Clients.vue');
const Campaigns = () => import('./views/Campaigns.vue');
const CampaignShow = () => import('./views/CampaignShow.vue');
const WhatsappTemplatePreview = () => import('./views/WhatsappTemplatePreview.vue');
const Chat = () => import('./views/Chat.vue');
const ImportUploads = () => import('./views/ImportUploads.vue');
const AuditLog = () => import('./views/AuditLog.vue');
const SecurityIncidents = () => import('./views/SecurityIncidents.vue');
const ComplianceConsole = () => import('./views/ComplianceConsole.vue');
const ExportRequests = () => import('./views/ExportRequests.vue');
const Settings = () => import('./views/Settings.vue');
const Departments = () => import('./views/Departments.vue');
const Banks = () => import('./views/Banks.vue');
const Users = () => import('./views/Users.vue');
const Roles = () => import('./views/Roles.vue');
const WhatsAppFlows = () => import('./views/WhatsAppFlows.vue');
const WhatsappReplies = () => import('./views/WhatsappReplies.vue');
const MainLayout = () => import('./components/layout/MainLayout.vue');

const routes = [
  { path: '/login', name: 'login', component: Login },
  { path: '/register', name: 'register', component: Register },
  {
    path: '/',
    component: MainLayout,
    children: [
      { path: 'dashboard', name: 'dashboard', component: Dashboard, meta: { sensitiveView: true, pageIcon: 'bi-grid-fill' } },
      { path: 'clients', name: 'clients', component: Clients, meta: { sensitiveView: true, pageIcon: 'bi-people-fill' } },
      { path: 'campaigns', name: 'campaigns', component: Campaigns, meta: { sensitiveView: true, pageIcon: 'bi-megaphone-fill' } },
      { path: 'campaigns/:id', name: 'campaign.show', component: CampaignShow, meta: { sensitiveView: true, pageIcon: 'bi-megaphone-fill' } },
      { path: '/campaigns/:id/whatsapp-template/:templateSid?', name: 'WhatsappTemplatePreview', component: WhatsappTemplatePreview, meta: { sensitiveView: true, pageIcon: 'bi-whatsapp' } },
      { path: 'chat', name: 'chat', component: Chat, meta: { sensitiveView: true, pageIcon: 'bi-chat-dots-fill' } },
      { path: 'import-uploads', name: 'import-uploads', component: ImportUploads, meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AGENT', 'STAFF'], sensitiveView: true, pageIcon: 'bi-cloud-upload-fill' } },
      {
        path: 'audit-log',
        name: 'audit-log',
        component: AuditLog,
        meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER'], sensitiveView: true, pageIcon: 'bi-activity' },
      },
      {
        path: 'security-incidents',
        name: 'security-incidents',
        component: SecurityIncidents,
        meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER'], sensitiveView: true, pageIcon: 'bi-shield-exclamation' },
      },
      {
        path: 'compliance-console',
        name: 'compliance-console',
        component: ComplianceConsole,
        meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER'], sensitiveView: true, pageIcon: 'bi-shield-check' },
      },
      {
        path: 'export-requests',
        name: 'export-requests',
        component: ExportRequests,
        meta: { sensitiveView: true, pageIcon: 'bi-shield-lock' },
      },
      {
        path: 'settings',
        name: 'settings',
        component: Settings,
        meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN'], sensitiveView: true, pageIcon: 'bi-gear-fill' },
      },
      {
        path: 'banks',
        name: 'banks',
        component: Banks,
        meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN'], pageIcon: 'bi-bank' },
      },
      {
        path: 'departments',
        name: 'departments',
        component: Departments,
        meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN'], pageIcon: 'bi-building' },
      },
      {
        path: 'users',
        name: 'users',
        component: Users,
        meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN'], pageIcon: 'bi-person-badge-fill' },
      },
      {
        path: 'roles',
        name: 'roles',
        component: Roles,
        meta: { allowedRoles: ['SUPER_ADMIN', 'ADMIN'], pageIcon: 'bi-shield-shaded' },
      },
      { path: 'automation/whatsapp-flows', name: 'whatsapp-flows', component: WhatsAppFlows, meta: { sensitiveView: true, pageIcon: 'bi-diagram-3-fill' } },
      { path: 'whatsapp-replies', name: 'whatsapp-replies', component: WhatsappReplies, meta: { sensitiveView: true, pageIcon: 'bi-whatsapp' } },
      
    ],

    meta: { requiresAuth: true },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

function resolveStoredUserRoles(user) {
  if (!user || typeof user !== 'object') return [];
  if (Array.isArray(user.role_codes) && user.role_codes.length) {
    return user.role_codes;
  }
  if (user.role) {
    return [user.role];
  }
  return [];
}

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

  if (authRequired && isLoggedIn) {
    const user = JSON.parse(storedUser || '{}');
    const allowedRoles = to.meta?.allowedRoles;
    const roleCodes = resolveStoredUserRoles(user);

    if (Array.isArray(allowedRoles) && allowedRoles.length && !roleCodes.some((role) => allowedRoles.includes(role))) {
      return next({ name: 'dashboard' });
    }
  }

  next();
});

export default router;
