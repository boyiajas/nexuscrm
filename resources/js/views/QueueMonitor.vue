<template>
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.02em;">
        <i class="bi bi-cpu text-primary me-2"></i>Queue Jobs Monitor
      </h3>
      <button class="btn btn-primary btn-sm d-flex align-items-center gap-2" @click="fetchData" :disabled="loading">
        <i class="bi bi-arrow-clockwise" :class="{ 'spin': loading }"></i> Refresh
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">Pending Jobs</h6>
            <h3 class="fw-bold mb-0">{{ stats.pending }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">Failed Jobs</h6>
            <h3 class="fw-bold mb-0 text-danger">{{ stats.failed }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" :class="`bg-${status.color}-subtle border-${status.color}`">
          <div class="card-body">
            <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">Queue Worker Status</h6>
            <div class="d-flex align-items-center gap-2">
              <span class="spinner-grow spinner-grow-sm" :class="`text-${status.color}`" role="status" aria-hidden="true" v-if="status.color === 'success'"></span>
              <i class="bi bi-exclamation-triangle-fill" :class="`text-${status.color}`" v-else></i>
              <span class="fw-bold" :class="`text-${status.color}`">{{ status.message }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: activeTab === 'pending' }" href="#" @click.prevent="activeTab = 'pending'">
          <i class="bi bi-hourglass-split me-1"></i> Recent Pending Jobs
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: activeTab === 'failed' }" href="#" @click.prevent="activeTab = 'failed'">
          <i class="bi bi-x-octagon me-1"></i> Recent Failed Jobs
        </a>
      </li>
    </ul>

    <!-- Tab Content -->
    <div v-if="activeTab === 'pending'">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom-0 pt-3 pb-2">
          <h6 class="mb-0 fw-bold">Oldest Pending Jobs (Top 50)</h6>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted" style="font-size: 0.75rem; letter-spacing: 0.05em;">
              <tr>
                <th class="ps-4">ID</th>
                <th>Job Name</th>
                <th>Queue</th>
                <th>Attempts</th>
                <th>Queued At</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="job in recentPending" :key="job.id">
                <td class="ps-4 text-muted">#{{ job.id }}</td>
                <td class="fw-medium text-dark">{{ job.name }}</td>
                <td><span class="badge bg-secondary-subtle text-secondary">{{ job.queue }}</span></td>
                <td>{{ job.attempts }}</td>
                <td class="text-muted small">{{ job.created_at }}</td>
              </tr>
              <tr v-if="recentPending.length === 0">
                <td colspan="5" class="text-center py-4 text-muted">No pending jobs.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="activeTab === 'failed'">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-bold">Recent Failed Jobs (Top 50)</h6>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" @click="retryJob('all')" :disabled="recentFailed.length === 0 || processing">
              <i class="bi bi-arrow-repeat"></i> Retry All
            </button>
            <button class="btn btn-sm btn-outline-danger" @click="deleteFailedJob('all')" :disabled="recentFailed.length === 0 || processing">
              <i class="bi bi-trash"></i> Clear All Failed
            </button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted" style="font-size: 0.75rem; letter-spacing: 0.05em;">
              <tr>
                <th class="ps-4">ID</th>
                <th>Job Name</th>
                <th>Exception</th>
                <th>Failed At</th>
                <th class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="job in recentFailed" :key="job.id">
                <td class="ps-4 text-muted">#{{ job.id }}</td>
                <td class="fw-medium text-dark">
                  <div class="text-truncate" style="max-width: 150px;" :title="job.name">{{ job.name }}</div>
                  <div class="badge bg-secondary-subtle text-secondary mt-1">{{ job.queue }}</div>
                </td>
                <td>
                  <div class="text-danger small" style="max-width: 300px; white-space: normal; line-height: 1.2;">
                    {{ job.exception }}
                  </div>
                </td>
                <td class="text-muted small">{{ job.failed_at }}</td>
                <td class="text-end pe-4">
                  <div class="btn-group">
                    <button class="btn btn-sm btn-light text-primary" @click="retryJob(job.id)" title="Retry Job" :disabled="processing">
                      <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <button class="btn btn-sm btn-light text-danger" @click="deleteFailedJob(job.id)" title="Delete Failed Job" :disabled="processing">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="recentFailed.length === 0">
                <td colspan="5" class="text-center py-4 text-muted">No failed jobs.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import { Toast } from 'bootstrap';

export default {
  name: 'QueueMonitor',
  data() {
    return {
      loading: true,
      processing: false,
      activeTab: 'pending',
      stats: { pending: 0, failed: 0 },
      status: { color: 'secondary', message: 'Loading...', worker_active: false },
      recentPending: [],
      recentFailed: [],
    };
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    notify(message, type = 'info') {
      window.dispatchEvent(new CustomEvent('app-toast', {
        detail: { message, variant: type, title: 'Queue Monitor' }
      }));
    },
    async fetchData() {
      this.loading = true;
      try {
        const res = await axios.get('/api/queue-monitor');
        this.stats = res.data.stats;
        this.status = res.data.status;
        this.recentPending = res.data.recent_pending;
        this.recentFailed = res.data.recent_failed;
      } catch (err) {
        this.notify(err.response?.data?.message || 'Failed to fetch queue data.', 'danger');
      } finally {
        this.loading = false;
      }
    },
    async retryJob(id) {
      if (!confirm(`Are you sure you want to retry ${id === 'all' ? 'all failed jobs' : 'this job'}?`)) return;
      this.processing = true;
      try {
        const res = await axios.post(`/api/queue-monitor/retry/${id}`);
        this.notify(res.data.message || 'Job(s) queued for retry.', 'success');
        this.fetchData();
      } catch (err) {
        this.notify(err.response?.data?.message || 'Failed to retry job.', 'danger');
      } finally {
        this.processing = false;
      }
    },
    async deleteFailedJob(id) {
      if (!confirm(`Are you sure you want to delete ${id === 'all' ? 'all failed jobs' : 'this job'}?`)) return;
      this.processing = true;
      try {
        const res = await axios.delete(`/api/queue-monitor/failed/${id}`);
        this.notify(res.data.message || 'Failed job(s) deleted.', 'success');
        this.fetchData();
      } catch (err) {
        this.notify(err.response?.data?.message || 'Failed to delete job.', 'danger');
      } finally {
        this.processing = false;
      }
    }
  }
};
</script>

<style scoped>
.spin {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  100% { transform: rotate(360deg); }
}
</style>
