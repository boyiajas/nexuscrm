import { createRouter, createWebHistory } from 'vue-router';
import { syncAuthenticatedUser } from './axios';

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
const QueueMonitor = () => import('./views/QueueMonitor.vue');
const MainLayout = () => import('./components/layout/MainLayout.vue');

const routes = [
  { path: '/login', name: 'login', component: Login },
  { path: '/register', name: 'register', component: Register },
  {
    path: '/',
    component: MainLayout,
    children: [
      { path: 'dashboard', name: 'dashboard', component: Dashboard, meta: { sensitiveView: true, pageIcon: 'bi-grid-fill' } },
      { path: 'clients', name: 'clients', component: Clients, meta: { requiredPermission: 'view_clients', sensitiveView: true, pageIcon: 'bi-people-fill' } },
      { path: 'campaigns', name: 'campaigns', component: Campaigns, meta: { requiredPermission: 'view_campaigns', sensitiveView: true, pageIcon: 'bi-megaphone-fill' } },
      { path: 'campaigns/:id', name: 'campaign.show', component: CampaignShow, meta: { requiredPermission: 'view_campaigns', sensitiveView: true, pageIcon: 'bi-megaphone-fill' } },
      { path: '/campaigns/:id/whatsapp-template/:templateSid?', name: 'WhatsappTemplatePreview', component: WhatsappTemplatePreview, meta: { requiredPermission: 'view_campaigns', sensitiveView: true, pageIcon: 'bi-whatsapp' } },
      { path: 'chat', name: 'chat', component: Chat, meta: { requiredAnyPermission: ['view_live_chat', 'send_whatsapp'], sensitiveView: true, pageIcon: 'bi-chat-dots-fill' } },
      { path: 'import-uploads', name: 'import-uploads', component: ImportUploads, meta: { requiredPermission: 'import_clients', sensitiveView: true, pageIcon: 'bi-cloud-upload-fill' } },
      {
        path: 'audit-log',
        name: 'audit-log',
        component: AuditLog,
        meta: { requiredAnyPermission: ['view_audit_logs', 'view_audit_logs_role_only', 'view_audit_logs_all_users'], sensitiveView: true, pageIcon: 'bi-activity' },
      },
      {
        path: 'security-incidents',
        name: 'security-incidents',
        component: SecurityIncidents,
        meta: { requiredAnyPermission: ['view_security_incidents', 'manage_security_incidents'], sensitiveView: true, pageIcon: 'bi-shield-exclamation' },
      },
      {
        path: 'compliance-console',
        name: 'compliance-console',
        component: ComplianceConsole,
        meta: { requiredAnyPermission: ['view_compliance_console', 'manage_compliance_console'], sensitiveView: true, pageIcon: 'bi-shield-check' },
      },
      {
        path: 'export-requests',
        name: 'export-requests',
        component: ExportRequests,
        meta: { requiredAnyPermission: ['request_exports', 'approve_exports', 'bypass_export_approval'], sensitiveView: true, pageIcon: 'bi-shield-lock' },
      },
      {
        path: 'settings',
        name: 'settings',
        component: Settings,
        meta: {
          requiredAnyPermission: [
            'settings_user_account',
            'settings_system',
            'manage_system_settings',
            'settings_meta_whatsapp',
            'settings_waba_profile',
            'settings_waba_numbers',
            'settings_waba_templates',
          ],
          sensitiveView: true,
          pageIcon: 'bi-gear-fill',
        },
      },
      {
        path: 'banks',
        name: 'banks',
        component: Banks,
        meta: { requiredPermission: 'manage_banks', pageIcon: 'bi-bank' },
      },
      {
        path: 'departments',
        name: 'departments',
        component: Departments,
        meta: { requiredPermission: 'manage_departments', pageIcon: 'bi-building' },
      },
      {
        path: 'users',
        name: 'users',
        component: Users,
        meta: { requiredPermission: 'manage_users', pageIcon: 'bi-person-badge-fill' },
      },
      {
        path: 'roles',
        name: 'roles',
        component: Roles,
        meta: { requiredPermission: 'manage_roles', pageIcon: 'bi-shield-shaded' },
      },
      { path: 'automation/whatsapp-flows', name: 'whatsapp-flows', component: WhatsAppFlows, meta: { requiredPermission: 'manage_whatsapp_flows', sensitiveView: true, pageIcon: 'bi-diagram-3-fill' } },
      { path: 'whatsapp-replies', name: 'whatsapp-replies', component: WhatsappReplies, meta: { requiredAnyPermission: ['view_live_chat', 'send_whatsapp'], sensitiveView: true, pageIcon: 'bi-whatsapp' } },
      { path: 'queue-monitor', name: 'queue-jobs', component: QueueMonitor, meta: { sensitiveView: true, pageIcon: 'bi-cpu-fill' } },
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
router.beforeEach((to, from, next) => {
  const publicPages = ['/login', '/register'];
  const authRequired = !publicPages.includes(to.path);

  const storedToken = localStorage.getItem('nexus_token');
  const storedUser = localStorage.getItem('nexus_user');
  const isLoggedIn = !!(storedToken && storedUser);

  if (authRequired && !isLoggedIn) {
    return next({ name: 'login' });
  }

  if ((to.name === 'login' || to.name === 'register') && isLoggedIn) {
    return next({ name: 'dashboard' });
  }

  if (authRequired && isLoggedIn) {
    // Fire user sync in background without blocking initial route rendering
    syncAuthenticatedUser().catch(() => {});

    let user = {};
    try {
      user = JSON.parse(storedUser || '{}');
    } catch (e) {
      user = {};
    }

    const roleCodes = resolveStoredUserRoles(user);

    // Super Admin and Admin bypass all route checks
    if (!roleCodes.includes('SUPER_ADMIN') && !roleCodes.includes('ADMIN')) {
      const requiredAnyPermission = Array.isArray(to.meta?.requiredAnyPermission)
        ? to.meta.requiredAnyPermission
        : [];
      const requiredPermissions = requiredAnyPermission.length
        ? requiredAnyPermission
        : (to.meta?.requiredPermission ? [to.meta.requiredPermission] : []);

      if (requiredPermissions.length) {
        const userPerms = Array.isArray(user.permission_codes) ? user.permission_codes : [];
        const hasAccess = requiredPermissions.some((permission) => userPerms.includes(permission));

        if (!hasAccess) {
          return next({ name: 'dashboard' });
        }
      }
    }
  }

  next();
});

export default router;
