<template>
  <div>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-2">
      <div>
        <h2 class="h4 mb-0"><i class="bi bi-shield-lock me-2"></i>Export Requests</h2>
        <small class="text-muted">Sensitive exports are requested, approved, and downloaded through this workflow.</small>
      </div>
      <button class="btn btn-outline-secondary btn-sm" @click="fetchRequests">
        <i class="bi bi-arrow-repeat me-1"></i> Refresh
      </button>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="fetchRequests(1)">
          <div class="col-md-3">
            <label class="form-label">Dataset</label>
            <select v-model="filters.dataset" class="form-select">
              <option value="all">All</option>
              <option v-for="option in datasetOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select v-model="filters.status" class="form-select">
              <option value="all">All</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="downloaded">Downloaded</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Search</label>
            <input v-model="filters.q" type="text" class="form-control" placeholder="Bank, user, dataset..." />
          </div>
          <div class="col-md-1">
            <label class="form-label">From</label>
            <input v-model="filters.date_from" type="date" class="form-control" />
          </div>
          <div class="col-md-1">
            <label class="form-label">To</label>
            <input v-model="filters.date_to" type="date" class="form-control" />
          </div>
          <div class="col-md-2 d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Dataset</th>
              <th>Bank</th>
              <th>Requested By</th>
              <th>Status</th>
              <th>Scope</th>
              <th>Created</th>
              <th>Justification</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>#{{ item.id }}</td>
              <td>
                <div class="fw-semibold">{{ item.dataset_label }}</div>
                <small class="text-muted" v-if="item.target_type && item.target_id">
                  {{ item.target_type }} #{{ item.target_id }}
                </small>
              </td>
              <td>{{ item.bank_name || 'All / Scoped by user' }}</td>
              <td>
                <div>{{ item.requested_by_name }}</div>
                <small class="text-muted">{{ item.requested_by_role }}</small>
              </td>
              <td>
                <span class="badge" :class="statusBadge(item.status)">
                  {{ item.status }}
                </span>
              </td>
              <td style="max-width: 260px;">
                <div v-for="row in item.scope_summary || []" :key="`${item.id}-${row.label}`" class="small">
                  <span class="text-muted">{{ row.label }}:</span> {{ row.value }}
                </div>
                <small v-if="(!item.scope_summary || item.scope_summary.length === 0)" class="text-muted">Default scope</small>
              </td>
              <td>{{ item.created_at }}</td>
              <td style="max-width: 280px;">
                <div class="text-truncate" :title="item.justification">{{ item.justification }}</div>
                <small v-if="item.rejection_reason" class="text-danger d-block mt-1">
                  Rejected: {{ item.rejection_reason }}
                </small>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button
                    class="btn btn-outline-secondary"
                    @click="openDetail(item)"
                  >
                    <i class="bi bi-eye"></i>
                  </button>
                  <button
                    v-if="item.download_url"
                    class="btn btn-outline-success"
                    @click="download(item)"
                    :disabled="rowBusyId === item.id"
                  >
                    <span v-if="rowBusyId === item.id" class="spinner-border spinner-border-sm"></span>
                    <i v-else class="bi bi-download"></i>
                  </button>
                  <button
                    v-if="canApprove && item.status === 'pending'"
                    class="btn btn-outline-primary"
                    @click="approve(item)"
                    :disabled="rowBusyId === item.id"
                  >
                    <i class="bi bi-check2"></i>
                  </button>
                  <button
                    v-if="canApprove && item.status === 'pending'"
                    class="btn btn-outline-danger"
                    @click="openDetail(item)"
                    :disabled="rowBusyId === item.id"
                  >
                    <i class="bi bi-x-lg"></i>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="requests.length === 0">
              <td colspan="9" class="text-center text-muted py-4">No export requests found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
          Showing {{ pagination.from || 0 }}-{{ pagination.to || 0 }} of {{ pagination.total || 0 }}
        </small>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="!pagination.prevPage" @click="fetchRequests(pagination.prevPage)">Prev</button>
          <button class="btn btn-outline-secondary" :disabled="!pagination.nextPage" @click="fetchRequests(pagination.nextPage)">Next</button>
        </div>
      </div>
    </div>

    <div class="modal fade" tabindex="-1" ref="detailModalRef">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" v-if="selectedRequest">
          <div class="modal-header">
            <h5 class="modal-title">Export Request #{{ selectedRequest.id }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label text-muted small text-uppercase">Dataset</label>
                <div class="fw-semibold">{{ selectedRequest.dataset_label }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small text-uppercase">Status</label>
                <div>
                  <span class="badge" :class="statusBadge(selectedRequest.status)">
                    {{ selectedRequest.status }}
                  </span>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small text-uppercase">Bank</label>
                <div>{{ selectedRequest.bank_name || 'All / scoped by user' }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small text-uppercase">Target</label>
                <div>{{ selectedRequest.target_label }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small text-uppercase">Requested By</label>
                <div>{{ selectedRequest.requested_by_name }} <small class="text-muted">({{ selectedRequest.requested_by_role }})</small></div>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small text-uppercase">Created</label>
                <div>{{ selectedRequest.created_at }}</div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label text-muted small text-uppercase">Justification</label>
              <div class="border rounded p-3 bg-light">{{ selectedRequest.justification }}</div>
            </div>

            <div class="mb-3">
              <label class="form-label text-muted small text-uppercase">Scope Summary</label>
              <div class="border rounded p-3 bg-light">
                <div v-for="row in selectedRequest.scope_summary || []" :key="`${selectedRequest.id}-${row.label}`" class="mb-2 small">
                  <span class="text-muted text-uppercase">{{ row.label }}</span>
                  <div class="fw-semibold">{{ row.value }}</div>
                </div>
                <div v-if="!selectedRequest.scope_summary || selectedRequest.scope_summary.length === 0" class="small text-muted">
                  Default export scope
                </div>
              </div>
            </div>

            <div v-if="selectedRequest.rejection_reason" class="mb-3">
              <label class="form-label text-muted small text-uppercase">Rejection Reason</label>
              <div class="border rounded p-3 bg-light text-danger">{{ selectedRequest.rejection_reason }}</div>
            </div>

            <div v-if="canApprove && selectedRequest.status === 'pending'" class="mb-0">
              <label class="form-label">Rejection Reason</label>
              <textarea
                v-model.trim="rejectionReason"
                class="form-control"
                rows="3"
                maxlength="1000"
                placeholder="Provide a reason if you intend to reject this export request."
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button
              v-if="selectedRequest.download_url"
              type="button"
              class="btn btn-outline-success"
              @click="download(selectedRequest)"
              :disabled="rowBusyId === selectedRequest.id"
            >
              <span v-if="rowBusyId === selectedRequest.id" class="spinner-border spinner-border-sm me-1"></span>
              Download
            </button>
            <button
              v-if="canApprove && selectedRequest.status === 'pending'"
              type="button"
              class="btn btn-outline-danger"
              @click="rejectFromModal"
              :disabled="rowBusyId === selectedRequest.id || rejectionReason.length < 3"
            >
              Reject
            </button>
            <button
              v-if="canApprove && selectedRequest.status === 'pending'"
              type="button"
              class="btn btn-primary"
              @click="approve(selectedRequest)"
              :disabled="rowBusyId === selectedRequest.id"
            >
              Approve
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import { downloadProtectedFile } from '../utils/download';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';

export default {
  name: 'ExportRequestsView',
  data() {
    return {
      requests: [],
      rowBusyId: null,
      selectedRequest: null,
      detailModal: null,
      rejectionReason: '',
      filters: {
        dataset: 'all',
        status: 'all',
        q: '',
        date_from: '',
        date_to: '',
      },
      pagination: {
        currentPage: 1,
        prevPage: null,
        nextPage: null,
        total: 0,
        from: 0,
        to: 0,
      },
      datasetOptions: [
        { value: 'clients', label: 'Clients' },
        { value: 'audit_logs', label: 'Audit Logs' },
        { value: 'campaign_clients', label: 'Campaign Clients' },
        { value: 'campaign_whatsapp_messages', label: 'Campaign WhatsApp Messages' },
        { value: 'campaign_emails', label: 'Campaign Emails' },
        { value: 'campaign_sms_messages', label: 'Campaign SMS Messages' },
      ],
    };
  },
  computed: {
    currentUser() {
      try {
        return JSON.parse(localStorage.getItem('nexus_user') || '{}');
      } catch (e) {
        return {};
      }
    },
    canApprove() {
      return ['SUPER_ADMIN', 'ADMIN', 'COMPLIANCE_OFFICER'].includes(this.currentUser.role);
    },
  },
  mounted() {
    this.detailModal = createManagedModal(this.$refs.detailModalRef);
    this.fetchRequests();
  },
  beforeUnmount() {
    disposeManagedModal(this.detailModal);
  },
  methods: {
    fetchRequests(page = 1) {
      this.pagination.currentPage = page;
      axios.get('/api/export-requests', {
        params: {
          page,
          per_page: 20,
          dataset: this.filters.dataset,
          status: this.filters.status,
          q: this.filters.q || undefined,
          date_from: this.filters.date_from || undefined,
          date_to: this.filters.date_to || undefined,
        },
      }).then((res) => {
        const data = res.data;
        this.requests = data.data || [];
        this.pagination.currentPage = data.current_page;
        this.pagination.prevPage = data.current_page > 1 ? data.current_page - 1 : null;
        this.pagination.nextPage = data.current_page < data.last_page ? data.current_page + 1 : null;
        this.pagination.total = data.total;
        this.pagination.from = data.from;
        this.pagination.to = data.to;
      });
    },
    resetFilters() {
      this.filters = { dataset: 'all', status: 'all', q: '', date_from: '', date_to: '' };
      this.fetchRequests(1);
    },
    statusBadge(status) {
      return {
        pending: 'bg-warning text-dark',
        approved: 'bg-primary',
        rejected: 'bg-danger',
        downloaded: 'bg-success',
      }[status] || 'bg-secondary';
    },
    approve(item) {
      this.rowBusyId = item.id;
      axios.post(`/api/export-requests/${item.id}/approve`)
        .then(() => {
          if (this.selectedRequest?.id === item.id) {
            this.detailModal.hide();
          }
          notify.success('Export request approved.', 'Export Request');
          this.fetchRequests(this.pagination.currentPage);
        })
        .catch((error) => notify.error(error.response?.data?.message || 'Approval failed.', 'Export Request'))
        .finally(() => {
          this.rowBusyId = null;
        });
    },
    reject(item, reason) {
      this.rowBusyId = item.id;
      axios.post(`/api/export-requests/${item.id}/reject`, { reason })
        .then(() => {
          notify.success('Export request rejected.', 'Export Request');
          this.fetchRequests(this.pagination.currentPage);
        })
        .catch((error) => notify.error(error.response?.data?.message || 'Rejection failed.', 'Export Request'))
        .finally(() => {
          this.rowBusyId = null;
        });
    },
    rejectFromModal() {
      if (!this.selectedRequest || this.rejectionReason.length < 3) return;
      this.reject(this.selectedRequest, this.rejectionReason);
      this.detailModal.hide();
    },
    openDetail(item) {
      this.selectedRequest = item;
      this.rejectionReason = '';
      this.detailModal.show();
    },
    download(item) {
      if (!item.download_url) return;
      this.rowBusyId = item.id;
      downloadProtectedFile(item.download_url, `${item.dataset}.csv`)
        .then(() => {
          notify.success('Export downloaded successfully.', 'Export');
        })
        .catch((error) => {
          notify.error(error.response?.data?.message || 'Download failed.', 'Export');
        })
        .finally(() => {
          this.rowBusyId = null;
          this.fetchRequests(this.pagination.currentPage);
        });
    },
  },
};
</script>
