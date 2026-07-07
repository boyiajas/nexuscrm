<template>
  <div class="d-flex min-vh-100 bg-light">
    <!-- SIDEBAR -->
    <nav
      class="border-end bg-gradient sidebar"
      :class="{ 'sidebar-collapsed': isSidebarCollapsed }"
      style="background-color: #070735;"
    >
      <div class="p-3 border-bottom d-flex align-items-center gap-2">
        <img
          :src="publicLogoSrc"
          alt="Strauss Recovery Solutions"
          class="sidebar-static-logo"
          :class="{ 'sidebar-static-logo--collapsed': isSidebarCollapsed }"
        />
      </div>

      <ul class="nav nav-pills flex-column p-2">
        <li class="nav-item" v-if="canViewClients">
          <router-link
            :to="{ name: 'dashboard' }"
            class="nav-link"
            :class="{ active: isActive('dashboard') }"
          >
            <i class="bi bi-speedometer2 me-2"></i>
            <span class="nav-label">Dashboard</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="canViewCampaigns">
          <router-link
            :to="{ name: 'clients' }"
            class="nav-link"
            :class="{ active: isActive('clients') }"
          >
            <i class="bi bi-people me-2"></i>
            <span class="nav-label">Clients</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="canViewChat">
          <router-link
            :to="{ name: 'campaigns' }"
            class="nav-link"
            :class="{ active: isActive('campaigns') }"
          >
            <i class="bi bi-bullseye me-2"></i>
            <span class="nav-label">Campaigns</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="canViewAuditLog">
          <router-link
            :to="{ name: 'chat' }"
            class="nav-link"
            :class="{ active: isActive('chat') }"
          >
            <i class="bi bi-chat-dots me-2"></i>
            <span class="nav-label">Live Chat</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="canViewImportUploads">
          <router-link
            :to="{ name: 'import-uploads' }"
            class="nav-link"
            :class="{ active: isActive('import-uploads') }"
          >
            <i class="bi bi-file-earmark-medical me-2"></i>
            <span class="nav-label">Import Uploads</span>
          </router-link>
        </li>

        <li class="nav-item">
          <router-link
            :to="{ name: 'audit-log' }"
            class="nav-link"
            :class="{ active: isActive('audit-log') }"
          >
            <i class="bi bi-activity me-2"></i>
            <span class="nav-label">Audit Log</span>
          </router-link>
        </li>

        <li class="nav-item">
          <router-link
            :to="{ name: 'security-incidents' }"
            class="nav-link"
            :class="{ active: isActive('security-incidents') }"
            v-if="canViewSecurityIncidents"
          >
            <i class="bi bi-shield-exclamation me-2"></i>
            <span class="nav-label">Security Incidents</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="canViewComplianceConsole">
          <router-link
            :to="{ name: 'compliance-console' }"
            class="nav-link"
            :class="{ active: isActive('compliance-console') }"
          >
            <i class="bi bi-journal-check me-2"></i>
            <span class="nav-label">Compliance Console</span>
          </router-link>
        </li>

        <li class="nav-item">
          <router-link
            :to="{ name: 'export-requests' }"
            class="nav-link"
            :class="{ active: isActive('export-requests') }"
          >
            <i class="bi bi-shield-lock me-2"></i>
            <span class="nav-label">Export Requests</span>
          </router-link>
        </li>

        <li class="nav-item mt-2" v-if="canViewAutomation">
          <div class="small text-uppercase text-white px-3 mb-1 sidebar-section-title">
            Automation
          </div>
        </li>
        <li class="nav-item nav-item-sub" v-if="canViewAutomation">
          <router-link
            :to="{ name: 'whatsapp-flows' }"
            class="nav-link"
            :class="{ active: isActive('whatsapp-flows') }"
          >
            <i class="bi bi-robot me-2"></i>
            <span class="nav-label">WhatsApp Flows</span>
          </router-link>
        </li>

        <li class="nav-item mt-2" v-if="canViewAdminSection">
          <div class="small text-uppercase text-white px-3 mb-1 sidebar-section-title">
            Admin
          </div>
        </li>

        <li class="nav-item" v-if="canManageBanks">
          <router-link
            :to="{ name: 'banks' }"
            class="nav-link"
            :class="{ active: isActive('banks') }"
          >
            <i class="bi bi-bank me-2"></i>
            <span class="nav-label">Banks</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="canManageDepartments">
          <router-link
            :to="{ name: 'departments' }"
            class="nav-link"
            :class="{ active: isActive('departments') }"
          >
            <i class="bi bi-diagram-3 me-2"></i>
            <span class="nav-label">Departments</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="canManageUsers">
          <router-link
            :to="{ name: 'users' }"
            class="nav-link"
            :class="{ active: isActive('users') }"
          >
            <i class="bi bi-person-gear me-2"></i>
            <span class="nav-label">Users</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="canManageSettings">
          <router-link
            :to="{ name: 'settings' }"
            class="nav-link"
            :class="{ active: isActive('settings') }"
          >
            <i class="bi bi-gear me-2"></i>
            <span class="nav-label">Settings</span>
          </router-link>
        </li>
      </ul>
    </nav>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-grow-1 d-flex flex-column">
      <!-- TOP NAVBAR -->
      <header class="navbar navbar-light bg-white border-bottom px-3">
        <div class="d-flex align-items-center gap-2">
          <button
            class="btn btn-sm btn-outline-secondary"
            type="button"
            @click="toggleSidebar"
          >
            <i :class="isSidebarCollapsed ? 'bi bi-arrow-bar-right' : 'bi bi-arrow-bar-left'"></i>
          </button>
          <span class="fw-semibold">Welcome, {{ user?.name || 'User' }}</span>
        </div>

        <div class="d-flex align-items-center gap-3">
          <small class="text-muted d-none d-sm-inline">
            Role: {{ user?.role || 'AGENT' }}
          </small>
          <button class="btn btn-sm btn-outline-danger" @click="logout">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
          </button>
        </div>
      </header>

      <!-- PAGE CONTENT -->
      <main
        class="flex-grow-1 p-3 position-relative"
        :class="{ 'sensitive-surface': showSensitiveWatermark }"
        style="background-color:#0087ff0f"
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
      isSidebarCollapsed: false,
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
    currentRole() {
      return this.user?.role || 'AGENT';
    },
    canViewClients() {
      return !['AUDITOR', 'READ_ONLY_REVIEWER'].includes(this.currentRole);
    },
    canViewCampaigns() {
      return !['AUDITOR', 'READ_ONLY_REVIEWER'].includes(this.currentRole);
    },
    canViewChat() {
      return !['AUDITOR', 'READ_ONLY_REVIEWER'].includes(this.currentRole);
    },
    canViewAuditLog() {
      return ['SUPER_ADMIN', 'ADMIN', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER'].includes(this.currentRole);
    },
    canViewImportUploads() {
      return ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AGENT', 'STAFF'].includes(this.currentRole);
    },
    canViewSecurityIncidents() {
      return ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER'].includes(this.currentRole);
    },
    canViewComplianceConsole() {
      return ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER'].includes(this.currentRole);
    },
    canViewAutomation() {
      return !['AUDITOR', 'READ_ONLY_REVIEWER'].includes(this.currentRole);
    },
    canManageBanks() {
      return ['SUPER_ADMIN', 'ADMIN'].includes(this.currentRole);
    },
    canManageDepartments() {
      return ['SUPER_ADMIN', 'ADMIN'].includes(this.currentRole);
    },
    canManageUsers() {
      return ['SUPER_ADMIN', 'ADMIN'].includes(this.currentRole);
    },
    canManageSettings() {
      return ['SUPER_ADMIN', 'ADMIN'].includes(this.currentRole);
    },
    canViewAdminSection() {
      return this.canManageBanks || this.canManageDepartments || this.canManageUsers || this.canManageSettings;
    },
    showSensitiveWatermark() {
      return !!this.user && !!this.$route?.meta?.sensitiveView;
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
