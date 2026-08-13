<template>
  <div>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-2">
      <div>
        <h2 class="h4 mb-0"><i class="bi bi-shield-check me-2"></i>Import Uploads</h2>
        <small class="text-muted">Track debtor import uploads, malware scan results, and import outcomes.</small>
      </div>
      <button class="btn btn-outline-secondary btn-sm" @click="fetchUploads">
        <i class="bi bi-arrow-repeat me-1"></i> Refresh
      </button>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="fetchUploads(1)">
          <div class="col-md-4">
            <label class="form-label">Search</label>
            <input v-model.trim="filters.q" type="text" class="form-control" placeholder="Filename, batch number, signature, error..." />
          </div>
          <div class="col-md-2">
            <label class="form-label">Import Status</label>
            <select v-model="filters.import_status" class="form-select">
              <option value="all">All</option>
              <option v-for="option in importStatuses" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Scan Status</label>
            <select v-model="filters.scan_status" class="form-select">
              <option value="all">All</option>
              <option v-for="option in scanStatuses" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div class="col-md-2" v-if="canAccessAllBanks">
            <label class="form-label">Bank</label>
            <select v-model="filters.bank_id" class="form-select">
              <option value="">All banks</option>
              <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm border mb-4">
      <div class="card-body p-0">
        <TableLoadingWrapper :loading="loading" message="Loading import uploads...">
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th class="ps-4">File</th>
              <th>Batch</th>
              <th>Bank</th>
              <th>Uploaded By</th>
              <th>Scan</th>
              <th>Import</th>
              <th>Summary</th>
              <th class="pe-4">Created</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="upload in uploads" :key="upload.id">
              <td class="ps-4 py-1">
                <div class="fw-semibold">{{ upload.original_filename }}</div>
                <small class="text-muted">#{{ upload.id }} • {{ formatSize(upload.size_bytes) }}</small>
              </td>
              <td>
                <span v-if="upload.import_batch_number" class="badge bg-light text-dark border">
                  {{ upload.import_batch_number }}
                </span>
                <span v-else class="text-muted">-</span>
              </td>
              <td>{{ upload.bank?.name || 'Global / Shared' }}</td>
              <td>
                <div>{{ upload.user?.name || 'Unknown' }}</div>
                <small class="text-muted">{{ upload.user?.role || '-' }}</small>
              </td>
              <td>
                <span class="badge" :class="scanBadge(upload.scan_status)">{{ upload.scan_status || 'n/a' }}</span>
                <div class="small text-muted mt-1" v-if="upload.scan_signature">{{ upload.scan_signature }}</div>
                <div class="small text-muted mt-1" v-else-if="upload.scan_message">{{ upload.scan_message }}</div>
              </td>
              <td>
                <span class="badge" :class="importBadge(upload.import_status)">{{ upload.import_status }}</span>
                <div class="small text-muted mt-1" v-if="upload.error_message">{{ upload.error_message }}</div>
              </td>
              <td class="small">
                <template v-if="upload.import_summary">
                  <div>Imported: {{ upload.import_summary.imported ?? 0 }}</div>
                  <div>Created: {{ upload.import_summary.created ?? 0 }}</div>
                  <div>Updated: {{ upload.import_summary.updated ?? 0 }}</div>
                  <div>Duplicates: {{ upload.import_summary.duplicates ?? 0 }}</div>
                  <div>Skipped: {{ upload.import_summary.skipped ?? 0 }}</div>
                </template>
                <span v-else class="text-muted">-</span>
              </td>
              <td>
                <div>{{ formatDateTime(upload.created_at) }}</div>
                <small class="text-muted" v-if="upload.scanned_at">Scanned: {{ formatDateTime(upload.scanned_at) }}</small>
                <small class="text-muted d-block" v-if="upload.imported_at">Imported: {{ formatDateTime(upload.imported_at) }}</small>
              </td>
            </tr>
            <tr v-if="!loading && !uploads.length">
              <td colspan="8" class="text-center text-muted py-5">No import uploads found.</td>
            </tr>
          </tbody>
        </table>
        </div>
        </TableLoadingWrapper>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <small class="text-muted">Showing {{ pagination.from || 0 }}-{{ pagination.to || 0 }} of {{ pagination.total || 0 }}</small>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted">Rows:</small>
            <select class="form-select form-select-sm w-auto" v-model="perPage" @change="fetchUploads(1)">
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
              <option value="200">200</option>
              <option value="500">500</option>
              <option value="1000">1000</option>
            </select>
          </div>
        </div>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="!pagination.prevPage" @click="fetchUploads(pagination.prevPage)">Prev</button>
          <button class="btn btn-outline-secondary" :disabled="!pagination.nextPage" @click="fetchUploads(pagination.nextPage)">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';

export default {
  name: 'ImportUploadsView',
  components: {
    TableLoadingWrapper,
  },
  data() {
    return {
      uploads: [],
      loading: false,
      banks: [],
      filters: {
        q: '',
        import_status: 'all',
        scan_status: 'all',
        bank_id: '',
      },
      importStatuses: ['uploaded', 'scanning', 'scan_passed', 'rejected_invalid', 'rejected_malware', 'scanner_error', 'imported', 'import_failed'],
      scanStatuses: ['skipped', 'clean', 'infected', 'error'],
      perPage: 25,
      pagination: {
        from: 0,
        to: 0,
        total: 0,
        prevPage: null,
        nextPage: null,
      },
    };
  },
  computed: {
    currentUser() {
      try {
        return JSON.parse(localStorage.getItem('nexus_user') || '{}');
      } catch {
        return {};
      }
    },
    canAccessAllBanks() {
      return ['SUPER_ADMIN', 'ADMIN'].includes(this.currentUser.role);
    },
  },
  mounted() {
    this.fetchUploads();
    if (this.canAccessAllBanks) {
      this.fetchBanks();
    }
  },
  methods: {
    async fetchUploads(page = 1) {
      this.loading = true;
      try {
        const { data } = await axios.get('/api/import-uploads', {
          params: {
            page,
            per_page: this.perPage,
            q: this.filters.q || undefined,
            import_status: this.filters.import_status,
            scan_status: this.filters.scan_status,
            bank_id: this.filters.bank_id || undefined,
          },
        });

        this.uploads = data.data || [];
        this.pagination = {
          from: data.from,
          to: data.to,
          total: data.total,
          prevPage: data.prev_page_url ? data.current_page - 1 : null,
          nextPage: data.next_page_url ? data.current_page + 1 : null,
        };
      } finally {
        this.loading = false;
      }
    },
    async fetchBanks() {
      const { data } = await axios.get('/api/banks', { params: { per_page: 200 } });
      this.banks = data.data || data || [];
    },
    resetFilters() {
      this.filters = { q: '', import_status: 'all', scan_status: 'all', bank_id: '' };
      this.fetchUploads(1);
    },
    formatDateTime(value) {
      if (!value) return '';
      return String(value).replace('T', ' ').slice(0, 16);
    },
    formatSize(bytes) {
      if (!bytes && bytes !== 0) return '-';
      if (bytes < 1024) return `${bytes} B`;
      if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
      return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    },
    scanBadge(status) {
      return {
        skipped: 'bg-secondary',
        clean: 'bg-success',
        infected: 'bg-danger',
        error: 'bg-warning text-dark',
      }[status] || 'bg-secondary';
    },
    importBadge(status) {
      return {
        uploaded: 'bg-secondary',
        scanning: 'bg-info text-dark',
        scan_passed: 'bg-primary',
        rejected_invalid: 'bg-warning text-dark',
        rejected_malware: 'bg-danger',
        scanner_error: 'bg-warning text-dark',
        imported: 'bg-success',
        import_failed: 'bg-danger',
      }[status] || 'bg-secondary';
    },
  },
};
</script>
