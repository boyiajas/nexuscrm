<template>
  <div class="d-flex min-vh-100 bg-light">
    <!-- SIDEBAR -->
    <nav
      class="border-end bg-gradient sidebar"
      :class="{ 'sidebar-collapsed': isSidebarCollapsed }"
      style="background-color: #070735;"
    >
      <div class="p-3 border-bottom d-flex align-items-center gap-2">
        <div class="brand-mark fw-bold text-white text-center flex-shrink-0">
          {{ isSidebarCollapsed ? 'N' : 'NC' }}
        </div>
        <div v-if="!isSidebarCollapsed">
          <div class="fw-bold text-white">NexusCRM</div>
          <small class="text-white">Mini CRM Console</small>
        </div>
      </div>

      <ul class="nav nav-pills flex-column p-2">
        <li class="nav-item">
          <router-link
            :to="{ name: 'dashboard' }"
            class="nav-link"
            :class="{ active: isActive('dashboard') }"
          >
            <i class="bi bi-speedometer2 me-2"></i>
            <span class="nav-label">Dashboard</span>
          </router-link>
        </li>

        <li class="nav-item">
          <router-link
            :to="{ name: 'clients' }"
            class="nav-link"
            :class="{ active: isActive('clients') }"
          >
            <i class="bi bi-people me-2"></i>
            <span class="nav-label">Clients</span>
          </router-link>
        </li>

        <li class="nav-item">
          <router-link
            :to="{ name: 'campaigns' }"
            class="nav-link"
            :class="{ active: isActive('campaigns') }"
          >
            <i class="bi bi-bullseye me-2"></i>
            <span class="nav-label">Campaigns</span>
          </router-link>
        </li>

        <li class="nav-item">
          <router-link
            :to="{ name: 'chat' }"
            class="nav-link"
            :class="{ active: isActive('chat') }"
          >
            <i class="bi bi-chat-dots me-2"></i>
            <span class="nav-label">Live Chat</span>
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

        <li class="nav-item mt-2">
          <div class="small text-uppercase text-white px-3 mb-1 sidebar-section-title">
            Automation
          </div>
        </li>
        <li class="nav-item nav-item-sub">
          <router-link
            :to="{ name: 'whatsapp-flows' }"
            class="nav-link"
            :class="{ active: isActive('whatsapp-flows') }"
          >
            <i class="bi bi-robot me-2"></i>
            <span class="nav-label">WhatsApp Flows</span>
          </router-link>
        </li>

        <li class="nav-item mt-2">
          <div class="small text-uppercase text-white px-3 mb-1 sidebar-section-title">
            Admin
          </div>
        </li>

        <li class="nav-item">
          <router-link
            :to="{ name: 'departments' }"
            class="nav-link"
            :class="{ active: isActive('departments') }"
          >
            <i class="bi bi-diagram-3 me-2"></i>
            <span class="nav-label">Departments</span>
          </router-link>
        </li>

        <li class="nav-item">
          <router-link
            :to="{ name: 'users' }"
            class="nav-link"
            :class="{ active: isActive('users') }"
          >
            <i class="bi bi-person-gear me-2"></i>
            <span class="nav-label">Users</span>
          </router-link>
        </li>

        <li class="nav-item">
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
            Role: {{ user?.role || 'STAFF' }}
          </small>
          <button class="btn btn-sm btn-outline-danger" @click="logout">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
          </button>
        </div>
      </header>

      <!-- PAGE CONTENT -->
      <main class="flex-grow-1 p-3" style="background-color:#0087ff0f">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script>
import axios from '../../axios';
// Icon CSS
import 'bootstrap-icons/font/bootstrap-icons.css';  

export default {
  name: 'MainLayout',
  data() {
    return {
      user: null,
      isSidebarCollapsed: false,
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
  },
  methods: {
    isActive(name) {
      return this.$route.name === name;
    },
    toggleSidebar() {
      this.isSidebarCollapsed = !this.isSidebarCollapsed;
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
.sidebar .brand-mark {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background-color: rgba(255, 255, 255, 0.1);
  display: inline-flex;
  align-items: center;
  justify-content: center;
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
</style>
