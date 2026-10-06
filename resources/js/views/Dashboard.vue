<template>
  <div>
    <!-- Header with Filters on the Right Section of the same row -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
      <div>
        <h1 class="h3 fw-bold text-dark mb-1">Dashboard Overview</h1>
        <p class="text-muted small mb-0">High-level metrics and recent activity across your campaigns.</p>
      </div>

      <!-- Filters: Bank Multi-Select & Date Range Dropdown -->
      <div class="d-flex flex-wrap align-items-center gap-2">
        <!-- Bank Multi-select Filter -->
        <div class="dashboard-filter-bank" style="min-width: 230px; max-width: 320px;">
          <VueMultiselect
            v-model="selectedBanks"
            :options="availableBanks"
            :multiple="true"
            :close-on-select="false"
            :clear-on-select="false"
            :preserve-search="true"
            placeholder="All Banks"
            label="name"
            track-by="id"
            :show-labels="false"
            @update:model-value="fetchData"
          >
            <template #selection="{ values, isOpen }">
              <span class="multiselect__single small text-truncate d-inline-block" style="max-width: 210px;" v-if="values.length && !isOpen">
                <i class="bi bi-bank me-1 text-primary"></i>
                {{ values.length === 1 ? values[0].name : `${values.length} banks selected` }}
              </span>
            </template>
          </VueMultiselect>
        </div>

        <!-- Date Range Filter Dropdown -->
        <div style="min-width: 140px;">
          <select
            v-model="selectedDateRange"
            class="form-select form-select-sm shadow-sm"
            style="min-height: 40px; font-weight: 500; font-size: 0.85rem;"
            @change="fetchData"
          >
            <option value="today">Today</option>
            <option value="1_week">1 Week</option>
            <option value="2_weeks">2 Weeks</option>
            <option value="3_weeks">3 Weeks</option>
            <option value="1_month">1 Month</option>
            <option value="3_months">3 Months</option>
            <option value="6_months">6 Months</option>
            <option value="1_year">1 Year</option>
          </select>
        </div>

        <!-- Refresh Button -->
        <button
          type="button"
          class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center shadow-sm"
          style="height: 40px; width: 40px;"
          title="Refresh Data"
          :disabled="loading"
          @click="fetchData"
        >
          <i class="bi bi-arrow-clockwise fs-6" :class="{ 'spin-animation': loading }"></i>
        </button>
      </div>
    </div>

    <!-- Summary 4 Stat Cards Strip -->
    <div class="row g-3 mb-4">
      <!-- Card 1: Total Clients -->
      <div class="col-md-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden" style="border-left: 3px solid #10b981 !important; cursor: pointer;" @click="$router.push({ name: 'clients' })">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">TOTAL CLIENTS</div>
                <div class="stat-card-number mt-1">{{ summary.total_clients || 0 }}</div>
              </div>
              <div class="stat-icon-badge">
                <i class="bi bi-people-fill"></i>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top d-flex align-items-center gap-1 text-success fw-semibold small" style="font-size: 0.78rem;">
              <i class="bi bi-people-fill"></i> {{ summary.active_clients || 0 }} active in selected period
            </div>
          </div>
          <i class="bi bi-people-fill position-absolute text-success" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Card 2: Active Campaigns -->
      <div class="col-md-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden" style="border-left: 3px solid #059669 !important; cursor: pointer;" @click="$router.push({ name: 'campaigns' })">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">ACTIVE CAMPAIGNS</div>
                <div class="stat-card-number mt-1">{{ summary.active_campaigns || 0 }}</div>
              </div>
              <div class="stat-icon-badge" style="background-color: #ecfdf5; color: #059669;">
                <i class="bi bi-megaphone-fill"></i>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top text-muted small" style="font-size: 0.78rem;">
              Active in selected period
            </div>
          </div>
          <i class="bi bi-megaphone-fill position-absolute text-success" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Card 3: Open Chats -->
      <div class="col-md-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden" style="border-left: 3px solid #3b82f6 !important; cursor: pointer;" @click="$router.push({ name: 'whatsapp-replies' })">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">OPEN CHATS</div>
                <div class="stat-card-number mt-1">{{ summary.open_chats || 0 }}</div>
              </div>
              <div class="stat-icon-badge" style="background-color: #eff6ff; color: #3b82f6;">
                <i class="bi bi-chat-left-text-fill"></i>
              </div>
            </div>
            <div
              class="mt-3 pt-2 border-top fw-semibold small d-flex align-items-center gap-1"
              :class="(summary.chats_requiring_attention || 0) > 0 ? 'text-danger' : 'text-muted'"
              style="font-size: 0.78rem;"
            >
              <i :class="(summary.chats_requiring_attention || 0) > 0 ? 'bi bi-exclamation-triangle-fill text-danger' : 'bi bi-check-circle-fill text-success'"></i>
              {{ summary.chats_requiring_attention || 0 }} require attention
            </div>
          </div>
          <i class="bi bi-chat-left-text-fill position-absolute text-primary" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Card 4: Delivery Rate -->
      <div class="col-md-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden" style="border-left: 3px solid #64748b !important; cursor: pointer;" @click="$router.push({ name: 'campaigns' })">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">DELIVERY RATE</div>
                <div class="stat-card-number mt-1">{{ summary.delivery_rate || 0 }}%</div>
              </div>
              <div class="stat-icon-badge">
                <i class="bi bi-envelope-fill"></i>
              </div>
            </div>
            <div class="mt-3 pt-2 border-top">
              <div class="progress rounded-pill" style="height: 6px; background-color: #e2e8f0;">
                <div class="progress-bar rounded-pill bg-dark" :style="{ width: (summary.delivery_rate || 0) + '%' }"></div>
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
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #64748b !important; cursor: pointer;" @click="$router.push({ name: 'campaigns' })">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-list-task me-1"></i> TOTAL MESSAGES</div>
            </div>
            <div class="stat-card-number mt-3">{{ summary.total_messages || 0 }}</div>
          </div>
          <i class="bi bi-list-task position-absolute text-muted" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>
      
      <!-- Delivered -->
      <div class="col-lg-3 col-md-6">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #10b981 !important; cursor: pointer;" @click="$router.push({ name: 'campaigns' })">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-success small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-check2-all me-1"></i> DELIVERED(READ)</div>
            </div>
            <div>
              <div class="stat-card-number mt-3">{{ summary.total_delivered || 0 }}</div>
              <small class="text-muted" style="font-size: 0.72rem;">{{ summary.delivery_rate || 0 }}% delivery rate</small>
            </div>
          </div>
          <i class="bi bi-check2-all position-absolute text-success" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Pending -->
      <div class="col-lg-3 col-md-6">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #3b82f6 !important; cursor: pointer;" @click="$router.push({ name: 'campaigns' })">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-primary small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-three-dots me-1"></i> DELIVERED(UNREAD)</div>
            </div>
            <div>
              <div class="stat-card-number mt-3">{{ summary.total_pending || 0 }}</div>
              <small class="text-muted" style="font-size: 0.72rem;">Unread messages</small>
            </div>
          </div>
          <i class="bi bi-three-dots position-absolute text-primary" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <!-- Failed -->
      <div class="col-lg-3 col-md-6">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #ef4444 !important; cursor: pointer;" @click="$router.push({ name: 'campaigns' })">
          <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-danger small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-x-circle me-1"></i> FAILED</div>
            </div>
            <div>
              <div class="stat-card-number mt-3">{{ summary.total_failed || 0 }}</div>
              <small class="text-muted" style="font-size: 0.72rem;">{{ summary.total_messages > 0 ? ((summary.total_failed / summary.total_messages) * 100).toFixed(1) : 0 }}% failure rate</small>
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
                      <td class="ps-4 py-1">
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
                    <tr v-if="!loading && recentLogs.length === 0">
                      <td colspan="5" class="text-center text-muted py-4">
                        <i class="bi bi-clock-history fs-4 d-block mb-1 text-secondary"></i>
                        No activity logs recorded for this period.
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
                  :stroke-dasharray="`${channelPercentages.whatsapp}, 100`"
                  stroke-linecap="round"
                  stroke="currentColor"
                  fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  style="stroke: #059669; transition: stroke-dasharray 0.5s ease;"
                />
              </svg>
              <div class="position-absolute text-center">
                <div class="h2 fw-bold text-dark mb-0">{{ channelPercentages.whatsapp }}%</div>
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
                <div class="fw-bold small text-dark">{{ channelPercentages.whatsapp }}%</div>
              </div>
              <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border">
                <div class="d-flex align-items-center gap-2 small fw-semibold text-muted">
                  <span class="rounded-circle" style="width: 8px; height: 8px; background-color: #cbd5e1;"></span>
                  SMS
                </div>
                <div class="fw-bold small text-muted">{{ channelPercentages.sms }}%</div>
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
import VueMultiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: 'DashboardView',
  components: {
    TableLoadingWrapper,
    VueMultiselect,
  },
  data() {
    return {
      loading: false,
      selectedDateRange: 'today', // by default: current day
      selectedBanks: [],
      availableBanks: [],
      summary: {
        total_clients: 0,
        active_clients: 0,
        active_campaigns: 0,
        open_chats: 0,
        chats_requiring_attention: 0,
        delivery_rate: 0,
        total_delivered: 0,
        total_failed: 0,
        total_pending: 0,
        total_messages: 0,
      },
      channels: {
        WhatsApp: 0,
        Email: 0,
        SMS: 0,
      },
      recentLogs: [],
    };
  },
  computed: {
    channelPercentages() {
      const wa = this.channels.WhatsApp || 0;
      const sms = this.channels.SMS || 0;
      const total = wa + sms;
      if (total === 0) {
        return { whatsapp: 100, sms: 0 };
      }
      const waPct = Math.round((wa / total) * 100);
      return {
        whatsapp: waPct,
        sms: 100 - waPct,
      };
    },
  },
  async mounted() {
    await this.fetchBanks();
    this.fetchData();
  },
  methods: {
    async fetchBanks() {
      try {
        const res = await axios.get('/api/banks', { params: { per_page: 200 } });
        this.availableBanks = res.data.data || res.data || [];
      } catch (err) {
        console.error('Failed to load banks for dashboard filter:', err);
      }
    },
    fetchData() {
      this.loading = true;
      const params = {
        date_range: this.selectedDateRange,
      };
      if (this.selectedBanks && this.selectedBanks.length > 0) {
        params.bank_ids = this.selectedBanks.map(b => b.id).join(',');
      }

      axios.get('/api/dashboard', { params })
        .then((res) => {
          if (res.data && res.data.summary) {
            this.summary = { ...this.summary, ...res.data.summary };
          }
          if (res.data && res.data.channels) {
            this.channels = { ...this.channels, ...res.data.channels };
          }
          if (res.data && res.data.recent_activity) {
            this.recentLogs = res.data.recent_activity.map(log => ({
              ...log,
              time_ago: log.logged_at,
              status: log.status || 'Completed',
            }));
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

<style scoped>
.dashboard-filter-bank :deep(.multiselect) {
  min-height: 40px;
}
.dashboard-filter-bank :deep(.multiselect__tags) {
  min-height: 40px;
  padding-top: 8px;
  border-radius: 0.375rem;
  border-color: #dee2e6;
  font-size: 0.85rem;
}
.dashboard-filter-bank :deep(.multiselect__placeholder) {
  margin-bottom: 0;
  padding-top: 0;
  color: #6c757d;
  font-size: 0.85rem;
}
.spin-animation {
  display: inline-block;
  animation: spin-anim 0.8s linear infinite;
}
@keyframes spin-anim {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
