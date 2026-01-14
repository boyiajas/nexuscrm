<template>
  <div>
    <!-- Header + actions -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-2" style="background-color:#0087ff0f">
      <div>
        <h2 class="h4 mb-0"><i class="bi bi-activity me-2"></i>Audit Log</h2>
        <small class="text-muted">Track who did what, where and when.</small>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" @click="fetchLogs">
          <i class="bi bi-arrow-repeat me-1"></i> Refresh
        </button>
        <button class="btn btn-outline-success btn-sm" @click="exportCsv">
          <i class="bi bi-file-earmark-arrow-down me-1"></i> Export CSV
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="applyFilters">
          <div class="col-md-3">
            <label class="form-label">Module</label>
            <select v-model="filters.module" class="form-select">
              <option value="all">All</option>
              <option v-for="m in moduleOptions" :key="m" :value="m">
                {{ m }}
              </option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">User</label>
            <select v-model="filters.user_id" class="form-select">
              <option value="all">All</option>
              <option v-for="u in userOptions" :key="u.id" :value="u.id">
                {{ u.name }}
              </option>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">From</label>
            <input v-model="filters.date_from" type="date" class="form-control" />
          </div>

          <div class="col-md-2">
            <label class="form-label">To</label>
            <input v-model="filters.date_to" type="date" class="form-control" />
          </div>

          <div class="col-md-2">
            <label class="form-label">Search</label>
            <input
              v-model="filters.q"
              type="text"
              class="form-control"
              placeholder="Action, IP, etc..."
            />
          </div>

          <div class="col-12 d-flex justify-content-end mt-2">
            <button type="submit" class="btn btn-primary btn-sm me-2">
              Apply
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetFilters">
              Reset
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th style="width: 5%;">ID</th>
              <th style="width: 20%;">User</th>
              <th style="width: 15%;">Module</th>
              <th>Action</th>
              <th style="width: 15%;">IP Address</th>
              <th style="width: 18%;">Logged At</th>
              <th style="width: 6%;" class="text-end">View</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in logs" :key="item.id">
              <td>#{{ item.id }}</td>
              <td>{{ item.user_name || 'System' }}</td>
              <td>{{ item.module }}</td>
              <td class="text-truncate" style="max-width: 260px;">
                {{ item.action }}
              </td>
              <td>{{ item.ip_address || '-' }}</td>
              <td>{{ item.logged_at }}</td>
              <td class="text-end">
                <button
                  class="btn btn-sm btn-outline-primary"
                  @click="openDetail(item)"
                >
                  <i class="bi bi-eye"></i>
                </button>
              </td>
            </tr>
            <tr v-if="logs.length === 0">
              <td colspan="7" class="text-center text-muted py-3">
                No audit entries for this filter.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
          Showing
          {{ pagination.from || 0 }}–{{ pagination.to || 0 }}
          of
          {{ pagination.total || 0 }}
          entries
        </small>

        <nav>
          <ul class="pagination mb-0 pagination-sm">
            <li class="page-item" :class="{ disabled: !pagination.prevPage }">
              <button class="page-link" @click="goToPage(pagination.prevPage)" :disabled="!pagination.prevPage">
                «
              </button>
            </li>

            <li
              v-for="page in pagination.pages"
              :key="page"
              class="page-item"
              :class="{ active: page === pagination.currentPage }"
            >
              <button class="page-link" @click="goToPage(page)">
                {{ page }}
              </button>
            </li>

            <li class="page-item" :class="{ disabled: !pagination.nextPage }">
              <button class="page-link" @click="goToPage(pagination.nextPage)" :disabled="!pagination.nextPage">
                »
              </button>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" tabindex="-1" ref="detailModalRef">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" v-if="detail">
          <div class="modal-header">
            <h5 class="modal-title">Audit Log #{{ detail.id }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <dl class="row mb-0">
              <dt class="col-sm-3">User</dt>
              <dd class="col-sm-9">{{ detail.user_name || 'System' }}</dd>

              <dt class="col-sm-3">Module</dt>
              <dd class="col-sm-9">{{ detail.module }}</dd>

              <dt class="col-sm-3">Action</dt>
              <dd class="col-sm-9">{{ detail.action }}</dd>

              <dt class="col-sm-3">IP Address</dt>
              <dd class="col-sm-9">{{ detail.ip_address || '-' }}</dd>

              <dt class="col-sm-3">Logged At</dt>
              <dd class="col-sm-9">{{ detail.logged_at }}</dd>

              <dt class="col-sm-3">Created At</dt>
              <dd class="col-sm-9">{{ detail.created_at }}</dd>

              <dt class="col-sm-3">Updated At</dt>
              <dd class="col-sm-9">{{ detail.updated_at }}</dd>

              <dt class="col-sm-3">Meta</dt>
              <dd class="col-sm-9">
                <pre class="bg-light border rounded p-2 small mb-0">
{{ formattedMeta }}
                </pre>
              </dd>
            </dl>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from '../axios';
import { Modal } from 'bootstrap';

export default {
  name: 'AuditLogView',
  data() {
    return {
      logs: [],
      filters: {
        module: 'all',
        user_id: 'all',
        date_from: '',
        date_to: '',
        q: '',
      },
      moduleOptions: [],
      userOptions: [],
      pagination: {
        currentPage: 1,
        lastPage: 1,
        prevPage: null,
        nextPage: null,
        total: 0,
        from: 0,
        to: 0,
        pages: [],
      },
      detail: null,
      detailModal: null,
    };
  },
  computed: {
    formattedMeta() {
      if (!this.detail || !this.detail.meta) return '{}';
      try {
        if (typeof this.detail.meta === 'string') {
          return JSON.stringify(JSON.parse(this.detail.meta), null, 2);
        }
        return JSON.stringify(this.detail.meta, null, 2);
      } catch (e) {
        return String(this.detail.meta);
      }
    },
  },
  mounted() {
    this.detailModal = new Modal(this.$refs.detailModalRef);
    this.fetchLogs();
    this.fetchUsersForFilter();
  },
  methods: {
    buildQueryParams(extra = {}) {
      const params = {
        module: this.filters.module,
        user_id: this.filters.user_id,
        date_from: this.filters.date_from || undefined,
        date_to: this.filters.date_to || undefined,
        q: this.filters.q || undefined,
        page: this.pagination.currentPage,
        per_page: 20,
        ...extra,
      };

      // Remove undefined keys
      Object.keys(params).forEach((k) => {
        if (params[k] === undefined || params[k] === null) delete params[k];
      });

      return params;
    },
    fetchLogs(page = 1) {
      this.pagination.currentPage = page;

      axios
        .get('/api/audit-logs', { params: this.buildQueryParams({ page }) })
        .then((res) => {
          const data = res.data;
          this.logs = data.data || [];

          this.pagination.currentPage = data.current_page;
          this.pagination.lastPage = data.last_page;
          this.pagination.total = data.total;
          this.pagination.from = data.from;
          this.pagination.to = data.to;
          this.pagination.prevPage = data.current_page > 1 ? data.current_page - 1 : null;
          this.pagination.nextPage =
            data.current_page < data.last_page ? data.current_page + 1 : null;

          // Build simple page list (you can optimize if last_page is huge)
          const pages = [];
          for (let i = 1; i <= data.last_page; i++) {
            pages.push(i);
          }
          this.pagination.pages = pages;

          // Derive module options from returned logs (you can also load from backend if you prefer)
          const modulesSet = new Set();
          this.logs.forEach((l) => {
            if (l.module) modulesSet.add(l.module);
          });
          this.moduleOptions = Array.from(modulesSet);
        });
    },
    fetchUsersForFilter() {
      // Assumes /api/users returns paginated list; we just grab first 200 for filter
      axios
        .get('/api/users', { params: { per_page: 200 } })
        .then((res) => {
          const data = res.data;
          this.userOptions = data.data || data;
        })
        .catch(() => {
          this.userOptions = [];
        });
    },
    applyFilters() {
      this.fetchLogs(1);
    },
    resetFilters() {
      this.filters = {
        module: 'all',
        user_id: 'all',
        date_from: '',
        date_to: '',
        q: '',
      };
      this.fetchLogs(1);
    },
    goToPage(page) {
      if (!page || page === this.pagination.currentPage) return;
      this.fetchLogs(page);
    },
    exportCsv() {
      const params = this.buildQueryParams();
      const query = new URLSearchParams(params).toString();
      window.open('/api/audit-logs/export?' + query, '_blank');
    },
    openDetail(item) {
      axios.get(`/api/audit-logs/${item.id}`).then((res) => {
        this.detail = res.data;
        this.detailModal.show();
      });
    },
  },
};
</script>
