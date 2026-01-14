<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3" style="background-color:#0087ff0f">
      <div>
        <h2 class="h4 mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard Overview</h2>
        <p class="text-muted mb-0">
          High-level view of clients, campaigns and communication activity.
        </p>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" @click="fetchData">
          <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
        <button class="btn btn-primary btn-sm">
          <i class="bi bi-plus-circle me-1"></i> New Campaign
        </button>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3" v-for="card in summaryCards" :key="card.key">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="text-muted small text-uppercase">{{ card.label }}</div>
                <div class="h4 mb-0">{{ card.value }}</div>
              </div>
              <div class="rounded-circle d-flex align-items-center justify-content-center"
                   :class="card.iconBg"
                   style="width: 40px; height: 40px;">
                <i :class="card.icon"></i>
              </div>
            </div>
            <small class="text-muted">{{ card.subtitle }}</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="row g-3 mb-4">
      <!-- Campaign activity (line chart) -->
      <div class="col-lg-8">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title mb-0">Campaign Activity (Last 7 days)</h5>
            </div>

            <!-- Show chart only when we actually have labels/data -->
            <div v-if="dailyCampaigns.labels.length && dailyCampaigns.data.length" style="height: 180px;">
              <canvas ref="campaignChart" height="130"></canvas>
            </div>
            <div v-else class="text-muted text-center py-4">
              No campaign activity data available for the last 7 days.
            </div>
          </div>
        </div>
      </div>

      <!-- Channel breakdown -->
      <div class="col-lg-4">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title mb-3">Channel Breakdown</h5>

            <div v-for="item in channelItems" :key="item.label" class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <span>{{ item.label }}</span>
                <span class="fw-semibold">{{ item.value }}</span>
              </div>
              <div class="progress" style="height: 6px;">
                <div
                  class="progress-bar"
                  role="progressbar"
                  :style="{ width: item.percent + '%' }"
                ></div>
              </div>
            </div>

            <small class="text-muted">
              Based on number of campaigns using each channel.
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent activity + delivery stats -->
    <div class="row g-3">
      <!-- Recent activity -->
      <div class="col-lg-7">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title mb-0">Recent Activity</h5>
            </div>

            <ul class="list-group list-group-flush">
              <li
                v-for="item in recentActivity"
                :key="item.id"
                class="list-group-item d-flex justify-content-between align-items-start"
              >
                <div class="ms-0">
                  <div class="fw-semibold">
                    {{ item.user_name || 'System' }}
                    <span class="text-muted">· {{ item.module }}</span>
                  </div>
                  <div>{{ item.action }}</div>
                </div>
                <small class="text-muted ms-3 text-nowrap">
                  {{ item.logged_at }}
                </small>
              </li>

              <li v-if="recentActivity.length === 0" class="list-group-item text-muted">
                No recent activity logged.
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Delivery stats -->
      <div class="col-lg-5">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title mb-3">Delivery Statistics</h5>

            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <span>Overall Delivery Rate</span>
                <span class="fw-bold">{{ summary.delivery_rate }}%</span>
              </div>
              <div class="progress" style="height: 10px;">
                <div
                  class="progress-bar bg-success"
                  role="progressbar"
                  :style="{ width: summary.delivery_rate + '%' }"
                ></div>
              </div>
            </div>

            <div class="row text-center mt-3">
              <div class="col-4">
                <div class="h5 mb-0">{{ summary.total_delivered }}</div>
                <small class="text-muted">Delivered</small>
              </div>
              <div class="col-4">
                <div class="h5 mb-0">{{ summary.total_failed }}</div>
                <small class="text-muted">Failed</small>
              </div>
              <div class="col-4">
                <div class="h5 mb-0">{{ summary.total_pending }}</div>
                <small class="text-muted">Pending</small>
              </div>
            </div>

            <hr />

            <p class="small text-muted mb-0">
              Stats are aggregated across WhatsApp, Email and SMS campaigns.
            </p>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from '../axios';
import {
  Chart,
  LineController,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale);

export default {
  name: 'DashboardView',
  data() {
    return {
      summary: {
        total_clients: 0,
        active_campaigns: 0,
        completed_campaigns: 0,
        open_chats: 0,
        delivery_rate: 0,
        total_delivered: 0,
        total_failed: 0,
        total_pending: 0,
      },
      channels: {
        WhatsApp: 0,
        Email: 0,
        SMS: 0,
      },
      recentActivity: [],
      dailyCampaigns: {
        labels: [],
        data: [],
      },
      chartInstance: null,
    };
  },
  computed: {
    summaryCards() {
      return [
        {
          key: 'clients',
          label: 'Total Clients',
          value: this.summary.total_clients,
          subtitle: 'Active in all departments',
          icon: 'bi bi-people-fill',
          iconBg: 'bg-primary text-white',
        },
        {
          key: 'campaigns',
          label: 'Active Campaigns',
          value: this.summary.active_campaigns,
          subtitle: `${this.summary.completed_campaigns} completed`,
          icon: 'bi bi-megaphone-fill',
          iconBg: 'bg-success text-white',
        },
        {
          key: 'chats',
          label: 'Open Chats',
          value: this.summary.open_chats,
          subtitle: 'Clients waiting for response',
          icon: 'bi bi-chat-dots-fill',
          iconBg: 'bg-warning text-dark',
        },
        {
          key: 'delivery',
          label: 'Delivery Rate',
          value: this.summary.delivery_rate + '%',
          subtitle: 'All channels combined',
          icon: 'bi bi-graph-up-arrow',
          iconBg: 'bg-info text-white',
        },
      ];
    },
    channelItems() {
      const total =
        this.channels.WhatsApp + this.channels.Email + this.channels.SMS || 1;

      const makePercent = (count) =>
        total === 0 ? 0 : Math.round((count / total) * 100);

      return [
        {
          label: 'WhatsApp',
          value: this.channels.WhatsApp,
          percent: makePercent(this.channels.WhatsApp),
        },
        {
          label: 'Email',
          value: this.channels.Email,
          percent: makePercent(this.channels.Email),
        },
        {
          label: 'SMS',
          value: this.channels.SMS,
          percent: makePercent(this.channels.SMS),
        },
      ];
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    async fetchData() {
      try {
        const res = await axios.get('/api/dashboard');

        this.summary = res.data.summary || this.summary;
        this.channels = res.data.channels || this.channels;

        // Limit recent activity on the dashboard so it doesn't grow too tall
        const recent = res.data.recent_activity || [];
        this.recentActivity = recent.slice(0, 10);

        this.prepareDailyCampaigns(res.data.daily_campaigns || { labels: [], data: [] });

        // Wait for DOM to render the canvas after updating dailyCampaigns
        this.$nextTick(() => {
          this.renderChart();
        });
      } catch (e) {
        console.error('Failed to load dashboard data', e);
      }
    },

    prepareDailyCampaigns(rawDaily) {
      let labels = Array.isArray(rawDaily.labels) ? rawDaily.labels : [];
      let data = Array.isArray(rawDaily.data) ? rawDaily.data : [];

      // Keep them in sync
      const len = Math.min(labels.length, data.length);

      if (len === 0) {
        this.dailyCampaigns = { labels: [], data: [] };
        return;
      }

      // Only last 7 points max
      const start = len > 7 ? len - 7 : 0;
      labels = labels.slice(start, len);
      data = data.slice(start, len);

      this.dailyCampaigns = { labels, data };
    },

    renderChart() {
      // Canvas might not be in the DOM yet during the first load
      if (!this.$refs.campaignChart) {
        return;
      }

      // If no data, just destroy chart if exists and bail
      if (!this.dailyCampaigns.labels.length || !this.dailyCampaigns.data.length) {
        if (this.chartInstance) {
          this.chartInstance.destroy();
          this.chartInstance = null;
        }
        return;
      }

      const ctx = this.$refs.campaignChart.getContext('2d');

      if (this.chartInstance) {
        this.chartInstance.destroy();
      }

      this.chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
          labels: this.dailyCampaigns.labels,
          datasets: [
            {
              label: 'Campaigns created',
              data: this.dailyCampaigns.data,
              tension: 0.3,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0,
              },
            },
          },
          plugins: {
            legend: {
              display: false,
            },
          },
        },
      });
    },
  },
};
</script>
