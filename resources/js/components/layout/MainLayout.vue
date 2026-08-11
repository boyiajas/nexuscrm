<template>
  <div class="d-flex min-vh-100 bg-light">
    <!-- SIDEBAR -->
    <nav
      class="strauss-sidebar d-flex flex-column justify-content-between border-end shadow-sm"
      :class="{ 'sidebar-collapsed': isSidebarCollapsed }"
    >
      <div>
        <!-- BRAND LOGO HEADER -->
        <div class="p-3 mb-2 d-flex align-items-center gap-3">
          <div class="bg-white text-dark rounded-2 fw-black d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; font-weight: 900; font-size: 1.25rem;">
            S
          </div>
          <div>
            <div class="strauss-sidebar-brand h5 mb-0 fw-bold">STRAUSS</div>
            <div class="small fw-semibold" style="color: #60a5fa; font-size: 0.72rem; letter-spacing: 0.05em;">Nexus Recovery</div>
          </div>
        </div>

        <!-- OVERVIEW -->
        <div class="px-3 pt-1 pb-1 small sidebar-section-title fw-bold text-uppercase mt-1" style="font-size: 0.65rem; letter-spacing: 0.05em;">Overview</div>
        <ul class="nav nav-pills flex-column px-2 gap-1 mb-2">
          <li class="nav-item">
            <router-link :to="{ name: 'dashboard' }" class="nav-link" :class="{ active: isActive('dashboard') }">
              <i class="bi bi-grid-fill me-2"></i><span class="nav-label">Dashboard</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canViewClients">
            <router-link :to="{ name: 'clients' }" class="nav-link" :class="{ active: isActive('clients') }">
              <i class="bi bi-people-fill me-2"></i><span class="nav-label">Clients</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canViewCampaigns">
            <router-link :to="{ name: 'campaigns' }" class="nav-link" :class="{ active: isActive('campaigns') }">
              <i class="bi bi-megaphone-fill me-2"></i><span class="nav-label">Campaigns</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canViewImportUploads">
            <router-link :to="{ name: 'import-uploads' }" class="nav-link" :class="{ active: isActive('import-uploads') }">
              <i class="bi bi-cloud-arrow-up-fill me-2"></i><span class="nav-label">Import Data</span>
            </router-link>
          </li>
        </ul>

        <!-- COMMUNICATIONS & AUTOMATION -->
        <div class="px-3 pt-1 pb-1 small sidebar-section-title fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Communications</div>
        <ul class="nav nav-pills flex-column px-2 gap-1 mb-2">
          <li class="nav-item" v-if="canViewChat">
            <router-link :to="{ name: 'chat' }" class="nav-link" :class="{ active: isActive('chat') }">
              <i class="bi bi-chat-dots-fill me-2"></i><span class="nav-label">Live Chat</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canViewAutomation">
            <router-link :to="{ name: 'whatsapp-replies' }" class="nav-link" :class="{ active: isActive('whatsapp-replies') }">
              <i class="bi bi-reply-fill me-2"></i><span class="nav-label">Auto Replies</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canViewAutomation">
            <router-link :to="{ name: 'whatsapp-flows' }" class="nav-link" :class="{ active: isActive('whatsapp-flows') }">
              <i class="bi bi-diagram-3-fill me-2"></i><span class="nav-label">WhatsApp Flows</span>
            </router-link>
          </li>
        </ul>

        <!-- SECURITY & ANALYTICS -->
        <div class="px-3 pt-1 pb-1 small sidebar-section-title fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Security & Analytics</div>
        <ul class="nav nav-pills flex-column px-2 gap-1 mb-2">
          <li class="nav-item" v-if="canViewAuditLog">
            <router-link :to="{ name: 'audit-log' }" class="nav-link" :class="{ active: isActive('audit-log') }">
              <i class="bi bi-bar-chart-fill me-2"></i><span class="nav-label">Audit Log</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canViewSecurityIncidents">
            <router-link :to="{ name: 'security-incidents' }" class="nav-link" :class="{ active: isActive('security-incidents') }">
              <i class="bi bi-shield-exclamation me-2"></i><span class="nav-label">Incidents</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canViewComplianceConsole">
            <router-link :to="{ name: 'compliance-console' }" class="nav-link" :class="{ active: isActive('compliance-console') }">
              <i class="bi bi-clipboard-check-fill me-2"></i><span class="nav-label">Compliance</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link :to="{ name: 'export-requests' }" class="nav-link" :class="{ active: isActive('export-requests') }">
              <i class="bi bi-download me-2"></i><span class="nav-label">Exports</span>
            </router-link>
          </li>
        </ul>

        <!-- ADMINISTRATION -->
        <div class="px-3 pt-1 pb-1 small sidebar-section-title fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;" v-if="canViewAdminSection">Administration</div>
        <ul class="nav nav-pills flex-column px-2 gap-1 mb-2" v-if="canViewAdminSection">
          <li class="nav-item" v-if="canManageBanks">
            <router-link :to="{ name: 'banks' }" class="nav-link" :class="{ active: isActive('banks') }">
              <i class="bi bi-bank2 me-2"></i><span class="nav-label">Banks</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canManageDepartments">
            <router-link :to="{ name: 'departments' }" class="nav-link" :class="{ active: isActive('departments') }">
              <i class="bi bi-building me-2"></i><span class="nav-label">Departments</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canManageUsers">
            <router-link :to="{ name: 'users' }" class="nav-link" :class="{ active: isActive('users') }">
              <i class="bi bi-person-lines-fill me-2"></i><span class="nav-label">Users</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canManageRoles">
            <router-link :to="{ name: 'roles' }" class="nav-link" :class="{ active: isActive('roles') }">
              <i class="bi bi-shield-lock-fill me-2"></i><span class="nav-label">Roles</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="canManageSettings">
            <router-link :to="{ name: 'settings' }" class="nav-link" :class="{ active: isActive('settings') }">
              <i class="bi bi-gear-fill me-2"></i><span class="nav-label">Settings</span>
            </router-link>
          </li>
        </ul>
      </div>

      <!-- SIDEBAR FOOTER & CTA BUTTON -->
      <div class="px-3 pb-3 pt-2 d-flex flex-column gap-3 border-top border-secondary border-opacity-25">
        <button class="btn btn-sidebar-cta w-100 d-flex align-items-center justify-content-center gap-2 shadow-sm" @click="$router.push({ name: 'campaigns' })">
          <i class="bi bi-plus-lg"></i> New Campaign
        </button>

        <div class="d-flex flex-column gap-1">
          <a href="#" class="nav-link py-1 px-2 text-muted small d-flex align-items-center gap-2 text-decoration-none" style="color: #94a3b8 !important;" @click.prevent>
            <i class="bi bi-question-circle"></i> Help Center
          </a>
          <button class="btn btn-link p-0 text-start nav-link py-1 px-2 text-muted small d-flex align-items-center gap-2 text-decoration-none border-0 bg-transparent" style="color: #94a3b8 !important;" @click="logout">
            <i class="bi bi-box-arrow-right"></i> Logout
          </button>
        </div>
      </div>
    </nav>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-grow-1 d-flex flex-column min-w-0">
      <!-- TOP UTILITY NAVBAR -->
      <header class="top-utility-header px-4 d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <button
            class="btn btn-sm btn-light border-0 d-md-none"
            type="button"
            @click="toggleSidebar"
          >
            <i class="bi bi-list fs-5"></i>
          </button>

          <!-- Centered Title / Brand Header -->
          <div class="d-none d-lg-block fw-bold h4 mb-0 text-dark" style="letter-spacing: -0.02em;">
            STRAUSS
          </div>
        </div>

        <!-- UTILITY ICONS & USER AVATAR -->
        <div class="d-flex align-items-center gap-3">
          <button
            class="btn btn-sm btn-light rounded-circle p-2 text-secondary border-0"
            type="button"
            @click="toggleTheme"
            :title="currentTheme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
          >
            <i :class="currentTheme === 'dark' ? 'bi bi-sun-fill text-warning' : 'bi bi-moon-stars-fill text-primary'" class="fs-6"></i>
          </button>

          <div class="dropdown">
            <button class="btn btn-sm btn-light rounded-circle p-2 text-secondary border-0 position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
              <i class="bi bi-bell fs-6"></i>
              <span v-if="unreadWhatsappRepliesCount > 0" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                {{ unreadWhatsappRepliesCount }}
                <span class="visually-hidden">unread messages</span>
              </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="notificationDropdown" style="width: 320px;">
              <li><h6 class="dropdown-header fw-bold text-dark border-bottom pb-2 mb-1">Notifications</h6></li>
              <template v-if="unreadWhatsappRepliesCount > 0">
                <li v-for="reply in unreadWhatsappReplies.slice(0, 5)" :key="reply.id">
                  <a class="dropdown-item py-3 d-flex align-items-start gap-3 border-bottom" href="#" @click.prevent="openWhatsappReplies">
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1 shadow-sm" style="width: 38px; height: 38px;">
                      <i class="bi bi-whatsapp fs-5"></i>
                    </div>
                    <div>
                      <div class="fw-bold text-dark small mb-1">{{ reply.client_name || 'Unknown Client' }} <span class="badge bg-danger ms-1" v-if="reply.unread_count > 1">{{ reply.unread_count }}</span></div>
                      <div class="text-muted text-wrap text-truncate" style="font-size: 0.8rem; line-height: 1.3; max-width: 220px;">
                        {{ reply.last_response || 'New message received.' }}
                      </div>
                      <div class="text-primary mt-2 fw-semibold" style="font-size: 0.75rem;">
                        View Chat <i class="bi bi-arrow-right"></i>
                      </div>
                    </div>
                  </a>
                </li>
              </template>
              <li v-else>
                <div class="dropdown-item py-4 text-center text-muted small">
                  <i class="bi bi-bell-slash fs-4 d-block mb-2 text-black-50"></i>
                  No new notifications
                </div>
              </li>
              <li>
                <a class="dropdown-item text-center py-2 text-primary fw-semibold small bg-light" href="#" @click.prevent="markAllAsRead">
                  Mark all as read
                </a>
              </li>
            </ul>
          </div>

          <button class="btn btn-sm btn-light rounded-circle p-2 text-secondary border-0 d-none d-sm-inline" title="History" @click="$router.push({ name: 'audit-log' })">
            <i class="bi bi-clock-history fs-6"></i>
          </button>

          <button class="btn btn-sm btn-light rounded-circle p-2 text-secondary border-0 d-none d-sm-inline" title="Apps">
            <i class="bi bi-grid-3x3-gap fs-6"></i>
          </button>

          <div class="d-flex align-items-center gap-2 ms-1">
            <img
              v-if="user && user.avatar_url"
              :src="user.avatar_url"
              alt="User Avatar"
              class="header-user-avatar border"
              style="object-fit: cover;"
            />
            <div v-else-if="user && user.name" class="avatar-initial-badge border border-secondary" style="width: 32px; height: 32px; font-size: 0.85rem;">
              {{ user.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() }}
            </div>
            <img
              v-else
              src="https://ui-avatars.com/api/?name=User&background=0D8ABC&color=fff"
              alt="User Avatar"
              class="header-user-avatar border"
            />
          </div>
        </div>
      </header>

      <!-- PAGE CONTENT -->
      <main
        class="flex-grow-1 p-3 position-relative main-content-bg"
        :class="{ 'sensitive-surface': showSensitiveWatermark }"
        @contextmenu="handleSensitiveContextMenu"
      >
        <div
          v-if="showSensitiveWatermark"
          class="sensitive-watermark-layer"
          aria-hidden="true"
        >
          <div
            v-for="tile in watermarkTiles"
            :key="tile"
            class="sensitive-watermark-tile"
          >
            {{ watermarkText }}
          </div>
        </div>
        <div v-if="showSensitiveWatermark" class="print-security-notice">
          Confidential debtor data. Printing and uncontrolled capture are restricted and attributable to the current user.
        </div>

        <!-- PAGE BACKGROUND ICON WATERMARK -->
        <i
          v-if="$route.meta.pageIcon"
          :class="['bi', $route.meta.pageIcon, 'position-absolute', 'text-secondary']"
          style="top: -20px; right: -20px; font-size: 14rem; opacity: 0.04; z-index: 0; pointer-events: none;"
        ></i>

        <div class="page-content-shell">
          <router-view />
        </div>
      </main>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="toast align-items-center border-0 mb-2"
        :class="toastClass(toast.variant)"
        role="alert"
        aria-live="assertive"
        aria-atomic="true"
        :ref="setToastRef"
        :data-toast-id="toast.id"
      >
        <div class="d-flex">
          <div class="toast-body">
            <div v-if="toast.title" class="fw-semibold mb-1">{{ toast.title }}</div>
            <div>{{ toast.message }}</div>
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios, { syncAuthenticatedUser } from '../../axios';
import { Toast } from 'bootstrap';
// Icon CSS
import 'bootstrap-icons/font/bootstrap-icons.css';  

export default {
  name: 'MainLayout',
  data() {
    return {
      user: null,
      unreadWhatsappReplies: [],
      isSidebarCollapsed: false,
      currentTheme: localStorage.getItem('nexus_theme') || 'light',
      branding: {
        app_name: 'NexusCRM',
        app_short_name: 'NC',
        app_tagline: 'Mini CRM Console',
        app_logo_url: '',
      },
      publicLogoSrc: `${window.location.origin}/images/strauss%20recovery%20solution%20logo-dark.png`,
      watermarkTimestamp: '',
      watermarkTimer: null,
      toasts: [],
      toastRefs: {},
    };
  },
  created() {
    this.applyTheme(this.currentTheme);
    // Load user from localStorage if present
    const stored = localStorage.getItem('nexus_user');
    if (stored) {
      try {
        this.user = JSON.parse(stored);
      } catch (e) {
        this.user = null;
      }
    }
    this.loadStoredBranding();
    this.loadBranding();
    this.refreshWatermarkTimestamp();
    this.watermarkTimer = window.setInterval(this.refreshWatermarkTimestamp, 60 * 1000);
    window.addEventListener('branding-updated', this.handleBrandingUpdated);
    window.addEventListener('app-toast', this.handleToastEvent);
    window.addEventListener('auth-user-updated', this.handleAuthUserUpdated);
  },
  async mounted() {
    this.fetchUnreadReplies();
    try {
      const user = await syncAuthenticatedUser();
      if (user) {
        this.user = user;
      }
    } catch (e) {
      // Let the normal 401 interceptor handle invalid sessions/tokens.
    }
  },
  beforeUnmount() {
    window.removeEventListener('branding-updated', this.handleBrandingUpdated);
    window.removeEventListener('app-toast', this.handleToastEvent);
    window.removeEventListener('auth-user-updated', this.handleAuthUserUpdated);
    if (this.watermarkTimer) {
      window.clearInterval(this.watermarkTimer);
    }
  },
  computed: {
    unreadWhatsappRepliesCount() {
      return this.unreadWhatsappReplies.length;
    },
    currentRole() {
      return this.user?.role || 'AGENT';
    },
    currentRoleCodes() {
      if (Array.isArray(this.user?.role_codes) && this.user.role_codes.length) {
        return this.user.role_codes;
      }

      if (this.user?.role) {
        return [this.user.role];
      }

      return ['AGENT'];
    },
    canViewClients() {
      return !this.hasAnyRole(['AUDITOR', 'READ_ONLY_REVIEWER']);
    },
    canViewCampaigns() {
      return !this.hasAnyRole(['AUDITOR', 'READ_ONLY_REVIEWER']);
    },
    canViewChat() {
      return !this.hasAnyRole(['AUDITOR', 'READ_ONLY_REVIEWER']);
    },
    canViewAuditLog() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER']);
    },
    canViewImportUploads() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AGENT', 'STAFF']);
    },
    canViewSecurityIncidents() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER']);
    },
    canViewComplianceConsole() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER']);
    },
    canViewAutomation() {
      return !this.hasAnyRole(['AUDITOR', 'READ_ONLY_REVIEWER']);
    },
    canManageBanks() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN']);
    },
    canManageDepartments() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN']);
    },
    canManageUsers() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN']);
    },
    canManageRoles() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN']);
    },
    canManageSettings() {
      return this.hasAnyRole(['SUPER_ADMIN', 'ADMIN']);
    },
    canViewAdminSection() {
      return this.canManageBanks || this.canManageDepartments || this.canManageUsers || this.canManageRoles || this.canManageSettings;
    },
    roleWatermarkEnabled() {
      if (Array.isArray(this.user?.roles) && this.user.roles.length) {
        return this.user.roles.some((role) => role?.watermark_enabled !== false);
      }

      return true;
    },
    showSensitiveWatermark() {
      return !!this.user && !!this.$route?.meta?.sensitiveView && this.roleWatermarkEnabled;
    },
    watermarkText() {
      const name = this.user?.name || 'Unknown User';
      const role = this.user?.role || 'UNKNOWN_ROLE';
      const email = this.user?.email || 'no-email';
      const routeName = this.$route?.name || 'unknown-route';
      return `${name} • ${email} • ${role} • ${routeName} • ${this.watermarkTimestamp}`;
    },
    watermarkTiles() {
      return Array.from({ length: 18 }, (_, index) => index);
    },
    collapsedBrandInitials() {
      const raw = (this.branding.app_short_name || this.branding.app_name || 'NC').trim();
      if (!raw) return 'NC';
      const parts = raw.split(/\s+/).filter(Boolean);
      if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
      }
      return parts.slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
    },
  },
  methods: {
    fetchUnreadReplies() {
      axios.get('/api/dashboard/whatsapp-replies')
        .then(res => {
          this.unreadWhatsappReplies = res.data || [];
        })
        .catch(err => {
          console.error('Failed to load unread replies:', err);
        });
    },
    openWhatsappReplies() {
      this.unreadWhatsappReplies = [];
      this.$router.push({ name: 'whatsapp-replies' });
    },
    markAllAsRead() {
      this.unreadWhatsappReplies = [];
    },
    toggleTheme() {
      this.currentTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
      this.applyTheme(this.currentTheme);
    },
    applyTheme(theme) {
      document.documentElement.setAttribute('data-bs-theme', theme);
      localStorage.setItem('nexus_theme', theme);
    },
    hasAnyRole(roles = []) {
      return this.currentRoleCodes.some((role) => roles.includes(role));
    },
    isActive(name) {
      return this.$route.name === name;
    },
    toggleSidebar() {
      this.isSidebarCollapsed = !this.isSidebarCollapsed;
    },
    refreshWatermarkTimestamp() {
      this.watermarkTimestamp = new Intl.DateTimeFormat('en-ZA', {
        dateStyle: 'medium',
        timeStyle: 'short',
      }).format(new Date());
    },
    toastClass(variant) {
      return {
        success: 'text-bg-success',
        danger: 'text-bg-danger',
        warning: 'text-bg-warning',
        info: 'text-bg-primary',
      }[variant] || 'text-bg-primary';
    },
    setToastRef(el) {
      if (!el) return;
      const id = el.dataset.toastId;
      if (id) {
        this.toastRefs[id] = el;
      }
    },
    handleToastEvent(event) {
      const detail = event.detail || {};
      const toast = {
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        title: detail.title || '',
        message: detail.message || '',
        variant: detail.variant || 'info',
      };

      this.toasts.push(toast);

      this.$nextTick(() => {
        const el = this.toastRefs[toast.id];
        if (!el) return;

        const instance = new Toast(el, { delay: 4500 });
        el.addEventListener('hidden.bs.toast', () => {
          this.toasts = this.toasts.filter((item) => item.id !== toast.id);
          delete this.toastRefs[toast.id];
        }, { once: true });
        instance.show();
      });
    },
    applyBranding(branding = {}) {
      this.branding = {
        app_name: branding.app_name || 'NexusCRM',
        app_short_name: branding.app_short_name || 'NC',
        app_tagline: branding.app_tagline || 'Mini CRM Console',
        app_logo_url: branding.app_logo_url || '',
      };
      document.title = this.branding.app_name;
      localStorage.setItem('nexus_branding', JSON.stringify(this.branding));
    },
    loadStoredBranding() {
      const stored = localStorage.getItem('nexus_branding');
      if (!stored) return;
      try {
        this.applyBranding(JSON.parse(stored));
      } catch (e) {
        // ignore malformed cache
      }
    },
    async loadBranding() {
      try {
        const res = await axios.get('/api/settings/branding');
        this.applyBranding(res.data || {});
      } catch (e) {
        // keep fallback branding
      }
    },
    handleBrandingUpdated(event) {
      this.applyBranding(event.detail || {});
    },
    handleAuthUserUpdated(event) {
      this.user = event.detail || null;
    },
    handleSensitiveContextMenu(event) {
      if (!this.showSensitiveWatermark) {
        return;
      }

      if (event.target?.closest?.('input, textarea, select, button, [contenteditable="true"]')) {
        return;
      }

      event.preventDefault();
    },
    async logout() {
      try {
        await axios.post('/api/logout');
      } catch (e) {
        // ignore errors; still clear client-side
      }
      localStorage.removeItem('nexus_user');
      this.$router.push({ name: 'login' });
    },
  },
};
</script>

<style scoped>
.nav-link {
  border-radius: 0.375rem;
  color: #ffffff;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  white-space: nowrap;
}
.nav-link.active {
  background-color: #0d6efd;
  color: #fff;
}
.nav-link:hover {
  background-color: #5a5c5e;
}

.sidebar {
  width: 230px;
  min-width: 230px;
  max-width: 230px;
  flex: 0 0 230px;
  transition: width 0.2s ease;
}
.sidebar-static-logo {
  width: 168px;
  max-width: 100%;
  height: auto;
  display: block;
}
.sidebar-static-logo--collapsed {
  width: 44px;
}
.sidebar-collapsed {
  width: 72px;
  min-width: 72px;
  max-width: 72px;
  flex-basis: 72px;
}
.sidebar-collapsed .nav-link {
  justify-content: center;
  padding-left: 0.75rem;
  padding-right: 0.75rem;
}
.nav-item-sub .nav-link {
  padding-left: 2.25rem;
}
.sidebar-collapsed .nav-item-sub .nav-link {
  padding-left: 0.75rem;
}
.sidebar-collapsed .nav-label,
.sidebar-collapsed .sidebar-section-title {
  display: none;
}
.sidebar-collapsed .nav-link i {
  margin-right: 0;
}

.nav-label,
.sidebar-section-title {
  white-space: nowrap;
}

.sensitive-surface {
  position: relative;
  overflow-x: hidden;
}

.sensitive-watermark-layer {
  position: fixed;
  inset: 0;
  pointer-events: none;
  display: grid;
  grid-template-columns: repeat(3, minmax(220px, 1fr));
  gap: 2rem;
  padding: 1.5rem;
  opacity: 0.12;
  z-index: 1030;
}

.sensitive-watermark-tile {
  align-self: center;
  justify-self: center;
  transform: rotate(-24deg);
  font-size: 0.9rem;
  line-height: 1.35;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #12294f;
  font-weight: 700;
  text-align: center;
  white-space: normal;
  max-width: 320px;
}

.print-security-notice {
  display: none;
}

.page-content-shell {
  position: relative;
  font-size: 0.84rem;
}

.page-content-shell .card,
.page-content-shell .table,
.page-content-shell .form-label,
.page-content-shell .form-control,
.page-content-shell .form-select,
.page-content-shell .btn,
.page-content-shell .pagination,
.page-content-shell .badge,
.page-content-shell .nav-link,
.page-content-shell .dropdown-item,
.page-content-shell .modal-content,
.page-content-shell small {
  font-size: 0.84rem;
}

.page-content-shell .table th,
.page-content-shell .table td {
  font-size: 0.8rem;
  padding-top: 0.45rem;
  padding-bottom: 0.45rem;
}

.page-content-shell .btn-group-sm > .btn,
.page-content-shell .btn-sm {
  font-size: 0.78rem;
}

@media print {
  .sensitive-watermark-layer {
    opacity: 0.18;
  }

  .print-security-notice {
    display: block;
    position: sticky;
    top: 0;
    z-index: 3;
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    border: 2px solid #8b0000;
    color: #8b0000;
    background: #fff5f5;
    font-weight: 700;
  }
}
</style>
