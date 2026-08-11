<template>
  <div>
    <!-- Header -->
    <div class="mb-4">
      <h1 class="h3 fw-bold text-dark mb-1">Dashboard Overview</h1>
      <p class="text-muted small mb-0">High-level metrics and recent activity across your campaigns.</p>
    </div>

    <!-- Summary 4 Stat Cards Strip -->
    <div class="row g-3 mb-4">
      <!-- Card 1: Total Clients -->
      <div class="col-md-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden" style="border-left: 3px solid #10b981 !important;">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">TOTAL CLIENTS</div>
                <div class="stat-card-number mt-1">{{ summary.total_clients || '4,907' }}</div>
              </div>
              <div class="stat-icon-badge">
                <i class="bi bi-people-fill"></i>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top d-flex align-items-center gap-1 text-success fw-semibold small" style="font-size: 0.78rem;">
              <i class="bi bi-graph-up-arrow"></i> +12% from last month
            </div>
          </div>
          <i class="bi bi-people-fill position-absolute text-success" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Card 2: Active Campaigns -->
      <div class="col-md-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden" style="border-left: 3px solid #059669 !important;">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">ACTIVE CAMPAIGNS</div>
                <div class="stat-card-number mt-1">{{ summary.active_campaigns || '12' }}</div>
              </div>
              <div class="stat-icon-badge" style="background-color: #ecfdf5; color: #059669;">
                <i class="bi bi-megaphone-fill"></i>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top text-muted small" style="font-size: 0.78rem;">
              Across 3 regions
            </div>
          </div>
          <i class="bi bi-megaphone-fill position-absolute text-success" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Card 3: Open Chats -->
      <div class="col-md-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden" style="border-left: 3px solid #3b82f6 !important;">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">OPEN CHATS</div>
                <div class="stat-card-number mt-1">{{ summary.unread_whatsapp_replies || '342' }}</div>
              </div>
              <div class="stat-icon-badge" style="background-color: #eff6ff; color: #3b82f6;">
                <i class="bi bi-chat-left-text-fill"></i>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top text-danger fw-semibold small d-flex align-items-center gap-1" style="font-size: 0.78rem;">
              <i class="bi bi-exclamation-triangle-fill"></i> 45 require attention
            </div>
          </div>
          <i class="bi bi-chat-left-text-fill position-absolute text-primary" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Card 4: Delivery Rate -->
      <div class="col-md-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden" style="border-left: 3px solid #64748b !important;">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">DELIVERY RATE</div>
                <div class="stat-card-number mt-1">98.4%</div>
              </div>
              <div class="stat-icon-badge">
                <i class="bi bi-envelope-fill"></i>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top">
              <div class="progress rounded-pill" style="height: 6px; background-color: #e2e8f0;">
                <div class="progress-bar rounded-pill bg-dark" style="width: 98.4%;"></div>
              </div>
            </div>
          </div>
          <i class="bi bi-envelope-fill position-absolute text-muted" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>
    </div>

    <!-- Overall Delivery Statistics -->
    <h5 class="fw-bold text-dark mb-3 mt-4">Overall Delivery Statistics</h5>
    <div class="row g-3 mb-4">
      <!-- Total -->
      <div class="col-lg-3 col-md-6">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #64748b !important;">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-list-task me-1"></i> TOTAL MESSAGES</div>
            </div>
            <div class="stat-card-number mt-3">12,450</div>
          </div>
          <i class="bi bi-list-task position-absolute text-muted" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>
      
      <!-- Delivered -->
      <div class="col-lg-3 col-md-6">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #10b981 !important;">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-success small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-check2-all me-1"></i> DELIVERED</div>
            </div>
            <div>
              <div class="stat-card-number mt-3">12,250</div>
              <small class="text-muted" style="font-size: 0.72rem;">98.4% delivery rate</small>
            </div>
          </div>
          <i class="bi bi-check2-all position-absolute text-success" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Pending -->
      <div class="col-lg-3 col-md-6">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #3b82f6 !important;">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-primary small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-three-dots me-1"></i> PENDING</div>
            </div>
            <div>
              <div class="stat-card-number mt-3">150</div>
              <small class="text-muted" style="font-size: 0.72rem;">In processing queues</small>
            </div>
          </div>
          <i class="bi bi-three-dots position-absolute text-primary" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Failed -->
      <div class="col-lg-3 col-md-6">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #ef4444 !important;">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-danger small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-x-circle me-1"></i> FAILED</div>
            </div>
            <div>
              <div class="stat-card-number mt-3">50</div>
              <small class="text-muted" style="font-size: 0.72rem;">0.4% failure rate</small>
            </div>
          </div>
          <i class="bi bi-x-circle position-absolute text-danger" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>
    </div>

    <!-- Middle Row: Recent Activity Logs + Channel Breakdown -->
    <div class="row g-3 mb-4">
      <!-- Recent Activity Logs Table (8 cols) -->
      <div class="col-lg-8">
        <div class="card border shadow-sm h-100">
          <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h2 class="h6 mb-0 fw-bold text-dark">Recent Activity Logs</h2>
            <a href="#" @click.prevent="$router.push({ name: 'audit-log' })" class="small fw-semibold text-decoration-none text-muted">View All</a>
          </div>
          <div class="card-body p-0">
            <TableLoadingWrapper :loading="loading" min-height="240px">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead>
                    <tr>
                      <th class="ps-4">User / Client</th>
                      <th>Action</th>
                      <th>Status</th>
                      <th>Time</th>
                      <th class="pe-4 text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="log in recentLogs" :key="log.id">
                      <td class="ps-4 py-3">
                        <div class="d-flex align-items-center gap-3">
                          <div class="avatar-initial-badge">{{ getInitials(log.user_name || log.client_name) }}</div>
                          <div>
                            <div class="fw-bold text-dark small mb-0">{{ log.user_name || log.client_name }}</div>
                            <div class="text-muted" style="font-size: 0.73rem;">ID: {{ log.ref_id }}</div>
                          </div>
                        </div>
                      </td>
                      <td class="small fw-medium text-dark">{{ log.action }}</td>
                      <td>
                        <span :class="getStatusBadgeClass(log.status)">
                          {{ log.status }}
                        </span>
                      </td>
                      <td class="text-muted small" style="font-size: 0.78rem;">{{ log.time_ago }}</td>
                      <td class="pe-4 text-end">
                        <button 
                          class="btn btn-light text-primary border-0 p-1 px-2" 
                          title="View Chat"
                          @click="$router.push({ name: 'chat' })"
                        >
                          <i class="bi bi-chat-dots"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </TableLoadingWrapper>
          </div>
        </div>
      </div>

      <!-- Channel Breakdown (4 cols) -->
      <div class="col-lg-4">
        <div class="card border shadow-sm h-100">
          <div class="card-header bg-white py-3 px-4 border-bottom">
            <h2 class="h6 mb-0 fw-bold text-dark">Channel Breakdown</h2>
          </div>
          <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
            <!-- Circular Donut Gauge -->
            <div class="position-relative d-flex align-items-center justify-content-center my-3" style="width: 170px; height: 170px;">
              <svg class="w-100 h-100" viewBox="0 0 36 36">
                <path
                  class="text-light"
                  stroke-width="3.5"
                  stroke="currentColor"
                  fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  style="stroke: #f1f5f9;"
                />
                <path
                  stroke-width="3.5"
                  stroke-dasharray="100, 100"
                  stroke-linecap="round"
                  stroke="currentColor"
                  fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  style="stroke: #059669; transition: stroke-dasharray 0.5s ease;"
                />
              </svg>
              <div class="position-absolute text-center">
                <div class="h2 fw-bold text-dark mb-0">100%</div>
                <div class="text-muted small fw-medium" style="font-size: 0.75rem;">WhatsApp</div>
              </div>
            </div>

            <!-- Legend strip -->
            <div class="w-100 mt-3 d-flex flex-column gap-2">
              <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border">
                <div class="d-flex align-items-center gap-2 small fw-semibold text-dark">
                  <span class="rounded-circle" style="width: 8px; height: 8px; background-color: #059669;"></span>
                  WhatsApp
                </div>
                <div class="fw-bold small text-dark">100%</div>
              </div>
              <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border">
                <div class="d-flex align-items-center gap-2 small fw-semibold text-muted">
                  <span class="rounded-circle" style="width: 8px; height: 8px; background-color: #cbd5e1;"></span>
                  SMS
                </div>
                <div class="fw-bold small text-muted">0%</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom AI Campaign Builder CTA Hero Banner -->
    <div class="dashboard-cta-banner d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4 shadow">
      <div>
        <h2 class="h4 fw-bold text-white mb-2">Need to launch a new campaign?</h2>
        <p class="mb-0 text-white-50 small" style="max-width: 600px;">
          Use our AI-assisted campaign builder to setup WhatsApp sequences in minutes.
        </p>
      </div>
      <button class="btn btn-light fw-bold text-dark rounded-pill px-4 py-2 flex-shrink-0 shadow-sm" @click="$router.push({ name: 'campaigns' })">
        Launch Builder
      </button>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';

export default {
  name: 'DashboardView',
  components: { TableLoadingWrapper },
  data() {
    return {
      loading: false,
      summary: {
        total_clients: 4907,
        active_campaigns: 12,
        unread_whatsapp_replies: 342,
      },
      recentLogs: [
        { id: 1, user_name: 'Taiwo Peter', ref_id: 'CLI-8942', action: 'Message Sent (WhatsApp)', status: 'Delivered', time_ago: '2 mins ago' },
        { id: 2, user_name: 'Sarah Mensah', ref_id: 'CLI-7731', action: 'Campaign Opt-in', status: 'Completed', time_ago: '15 mins ago' },
        { id: 3, user_name: 'James Kalu', ref_id: 'CLI-9012', action: 'Payment Link Generated', status: 'Pending', time_ago: '1 hour ago' },
        { id: 4, user_name: 'Amina Bello', ref_id: 'CLI-8822', action: 'Message Failed', status: 'Failed', time_ago: '2 hours ago' },
      ],
    };
  },
  async mounted() {
    this.fetchData();
  },
  methods: {
    fetchData() {
      this.loading = true;
      axios.get('/api/dashboard/summary')
        .then((res) => {
          if (res.data) {
            this.summary = { ...this.summary, ...res.data };
          }
        })
        .catch((err) => {
          console.error('Failed to load dashboard summary:', err);
        })
        .finally(() => {
          this.loading = false;
        });
    },
    getInitials(name) {
      if (!name) return 'NX';
      const parts = name.trim().split(' ');
      if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
      }
      return name.substring(0, 2).toUpperCase();
    },
    getStatusBadgeClass(status) {
      switch (status) {
        case 'Delivered':
        case 'Completed':
        case 'Active':
          return 'badge-status-delivered';
        case 'Pending':
        case 'Queued':
          return 'badge-status-pending';
        case 'Failed':
          return 'badge-status-failed';
        default:
          return 'badge-status-pending';
      }
    },
  },
};
</script>
