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
    <div class="card shadow-sm border mb-4">
      <div class="card-body p-0">
        <TableLoadingWrapper :loading="loading" message="Loading audit log...">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4" style="width: 5%;">ID</th>
              <th style="width: 20%;">User</th>
              <th style="width: 15%;">Module</th>
              <th>Action</th>
              <th style="width: 15%;">IP Address</th>
              <th style="width: 18%;">Logged At</th>
              <th style="width: 6%;" class="text-end pe-4">View</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in logs" :key="item.id">
              <td class="ps-4 py-1">#{{ item.id }}</td>
              <td>{{ item.user_name || 'System' }}</td>
              <td>{{ item.module }}</td>
              <td class="text-truncate" style="max-width: 260px;">
                {{ item.action }}
              </td>
              <td>{{ item.ip_address || '-' }}</td>
              <td>{{ item.logged_at }}</td>
              <td class="text-end pe-4">
                <button
                  class="btn btn-light text-primary border-0 p-1 px-2"
                  @click="openDetail(item)"
                >
                  <i class="bi bi-eye"></i>
                </button>
              </td>
            </tr>
            <tr v-if="!loading && logs.length === 0">
              <td colspan="7" class="text-center text-muted py-5">
                No audit entries for this filter.
              </td>
            </tr>
          </tbody>
        </table>
        </div>
        </TableLoadingWrapper>
      </div>

      <!-- Pagination -->
      <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">
          Showing
          {{ pagination.from || 0 }}–{{ pagination.to || 0 }}
          of
          {{ pagination.total || 0 }}
          entries
        </small>

        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 text-muted">Rows per page</label>
            <select
              v-model.number="pageSize"
              class="form-select form-select-sm"
              style="width: auto;"
              @change="changePageSize"
            >
              <option v-for="size in pageSizeOptions" :key="size" :value="size">
                {{ size }}
              </option>
            </select>
          </div>

          <nav>
            <ul class="pagination mb-0 pagination-sm">
              <li class="page-item" :class="{ disabled: !pagination.prevPage }">
                <button class="page-link" @click="goToPage(pagination.prevPage)" :disabled="!pagination.prevPage">
                  «
                </button>
              </li>

              <li
                v-for="p in pagination.pages"
                :key="p.key"
                class="page-item"
                :class="{ active: p.active, disabled: p.ellipsis }"
              >
                <button class="page-link" @click="!p.ellipsis && goToPage(p.page)" :disabled="p.ellipsis">
                  {{ p.label }}
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

    <ExportRequestModal ref="exportRequestModal" />
  </div>
</template>

<script>
import axios from '../axios';
import ExportRequestModal from '../components/ExportRequestModal.vue';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import { createManagedModal, disposeManagedModal } from '../utils/modal';

export default {
  name: 'AuditLogView',
  components: {
    ExportRequestModal,
    TableLoadingWrapper,
  },
  data() {
    return {
      logs: [],
      loading: false,
      pageSize: 25,
      pageSizeOptions: [25, 50, 100, 200, 500, 1000],
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
    selectedUserName() {
      if (!this.filters.user_id || this.filters.user_id === 'all') return '';
      return this.userOptions.find((user) => Number(user.id) === Number(this.filters.user_id))?.name || '';
    },
    dateRangeLabel() {
      if (this.filters.date_from && this.filters.date_to) {
        return `${this.filters.date_from} to ${this.filters.date_to}`;
      }
      if (this.filters.date_from) {
        return `From ${this.filters.date_from}`;
      }
      if (this.filters.date_to) {
        return `Up to ${this.filters.date_to}`;
      }
      return 'All dates';
    },
  },
  mounted() {
    this.detailModal = createManagedModal(this.$refs.detailModalRef);
    this.fetchLogs();
    this.fetchUsersForFilter();
  },
  beforeUnmount() {
    disposeManagedModal(this.detailModal);
  },
  methods: {
    buildPaginationItems(currentPage, lastPage) {
      const maxVisiblePages = 20;
      const items = [];
      const addPage = (page) => {
        items.push({
          key: `page-${page}`,
          label: String(page),
          page,
          active: page === currentPage,
          ellipsis: false,
        });
      };
      const addEllipsis = (key) => {
        items.push({
          key,
          label: '...',
          page: null,
          active: false,
          ellipsis: true,
        });
      };

      if (lastPage <= maxVisiblePages) {
        for (let page = 1; page <= lastPage; page += 1) {
          addPage(page);
        }
        return items;
      }

      const innerVisiblePages = maxVisiblePages - 2;
      let start = Math.max(2, currentPage - Math.floor(innerVisiblePages / 2));
      let end = start + innerVisiblePages - 1;

      if (end > lastPage - 1) {
        end = lastPage - 1;
        start = end - innerVisiblePages + 1;
      }

      if (start < 2) {
        start = 2;
      }

      addPage(1);

      if (start > 2) {
        addEllipsis('ellipsis-left');
      }

      for (let page = start; page <= end; page += 1) {
        addPage(page);
      }

      if (end < lastPage - 1) {
        addEllipsis('ellipsis-right');
      }

      addPage(lastPage);

      return items;
    },
    buildQueryParams(extra = {}) {
      const params = {
        module: this.filters.module,
        user_id: this.filters.user_id,
        date_from: this.filters.date_from || undefined,
        date_to: this.filters.date_to || undefined,
        q: this.filters.q || undefined,
        page: this.pagination.currentPage,
        per_page: this.pageSize,
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
      this.loading = true;

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
          this.pageSize = Number(data.per_page || this.pageSize);
          this.pagination.pages = this.buildPaginationItems(data.current_page, data.last_page);

          // Derive module options from returned logs (you can also load from backend if you prefer)
          const modulesSet = new Set();
          this.logs.forEach((l) => {
            if (l.module) modulesSet.add(l.module);
          });
          this.moduleOptions = Array.from(modulesSet);
        })
        .finally(() => {
          this.loading = false;
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
    changePageSize() {
      this.fetchLogs(1);
    },
    exportCsv() {
      const params = this.buildQueryParams();
      delete params.page;
      delete params.per_page;

      this.$refs.exportRequestModal.open({
        dataset: 'audit_logs',
        datasetLabel: 'Audit Logs',
        filters: params,
        summaryRows: [
          { label: 'Module', value: this.filters.module || 'All' },
          { label: 'User', value: this.selectedUserName || 'All users' },
          { label: 'Date Range', value: this.dateRangeLabel },
          { label: 'Search', value: this.filters.q || 'No search filter' },
        ],
        fallbackName: 'audit_logs.csv',
      });
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
