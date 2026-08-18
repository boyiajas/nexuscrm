<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-bank me-2"></i>Banks</h2>
      <button class="btn btn-primary btn-sm" @click="openCreateModal">
        + Add Bank
      </button>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="applyFilters">
          <div class="col-md-5">
            <label class="form-label">Search</label>
            <input
              v-model="filters.search"
              type="text"
              class="form-control"
              placeholder="Bank name or code..."
            />
          </div>

          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select v-model="filters.status" class="form-select">
              <option value="All">All</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>

          <div class="col-md-4 text-md-end">
            <button type="submit" class="btn btn-primary btn-sm me-2">Apply</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm border mb-4">
      <div class="card-body p-0">
        <TableLoadingWrapper :loading="loading" message="Loading banks...">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4">Name</th>
                <th>Code</th>
                <th>Departments</th>
                <th>Status</th>
                <th>Created</th>
                <th style="width: 120px;" class="text-end pe-4">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="bank in banks" :key="bank.id">
                <td class="ps-4 py-1">{{ bank.name }}</td>
                <td><code>{{ bank.code }}</code></td>
                <td>
                  <span v-if="!bank.departments || bank.departments.length === 0" class="text-muted small">None</span>
                  <div v-else class="d-flex gap-1 flex-wrap">
                    <span v-for="d in bank.departments" :key="d.id" class="badge bg-light text-dark border">
                      {{ d.name }}
                    </span>
                  </div>
                </td>
                <td>
                  <span class="badge" :class="bank.status === 'Active' ? 'bg-success' : 'bg-secondary'">
                    {{ bank.status }}
                  </span>
                </td>
                <td>{{ formatDate(bank.created_at) }}</td>
                <td class="text-end pe-4">
                  <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-light text-secondary border-0 p-1 px-2" title="Edit" @click="openEditModal(bank)">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-light text-danger border-0 p-1 px-2" title="Delete" @click="remove(bank)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="!loading && banks.length === 0">
                <td colspan="6" class="text-center text-muted py-5">
                  No banks found.
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </TableLoadingWrapper>
      </div>

      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <small class="text-muted">
            Showing {{ pagination.from || 0 }}–{{ pagination.to || 0 }}
            of {{ pagination.total || 0 }}
          </small>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted">Rows:</small>
            <select class="form-select form-select-sm w-auto" v-model="perPage" @change="fetchBanks(1)">
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
              <option value="250">250</option>
              <option value="500">500</option>
              <option value="1000">1000</option>
            </select>
          </div>
        </div>

        <ul class="pagination mb-0 pagination-sm">
          <li class="page-item" :class="{ disabled: !pagination.prevPage }">
            <button class="page-link" @click="goToPage(pagination.prevPage)">«</button>
          </li>

          <li
            v-for="p in pagination.pages"
            :key="p"
            class="page-item"
            :class="{ active: p === pagination.currentPage }"
          >
            <button class="page-link" @click="goToPage(p)">{{ p }}</button>
          </li>

          <li class="page-item" :class="{ disabled: !pagination.nextPage }">
            <button class="page-link" @click="goToPage(pagination.nextPage)">»</button>
          </li>
        </ul>
      </div>
    </div>

    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ isEdit ? 'Edit Bank' : 'Add Bank' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="save">
              <div class="mb-3">
                <label class="form-label">Bank Name</label>
                <input v-model="form.name" type="text" class="form-control" required />
              </div>

              <div class="mb-3">
                <label class="form-label">Code</label>
                <input v-model="form.code" type="text" class="form-control" required />
                <small class="text-muted">Use a unique short code or slug.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Departments</label>
                <VueMultiselect
                  v-model="form.department_ids"
                  :options="departments.map(d => d.id)"
                  :custom-label="(id) => (departments.find(d => d.id === id) || {}).name || id"
                  :multiple="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  placeholder="Select departments"
                />
              </div>

              <div class="mb-3">
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-select">
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>

              <div class="text-end">
                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                  Cancel
                </button>
                <button class="btn btn-primary">Save</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <ConfirmationModal ref="confirmModal" />
  </div>
</template>

<script>
import axios from '../axios';
import VueMultiselect from 'vue-multiselect';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';

export default {
  name: 'BanksView',
  components: {
    ConfirmationModal,
    TableLoadingWrapper,
    VueMultiselect,
  },
  data() {
    return {
      banks: [],
      departments: [],
      loading: false,
      filters: {
        search: '',
        status: 'All',
      },
      perPage: 25,
      pagination: {
        currentPage: 1,
        lastPage: 1,
        total: 0,
        from: 0,
        to: 0,
        prevPage: null,
        nextPage: null,
        pages: [],
      },
      form: {
        id: null,
        name: '',
        code: '',
        status: 'Active',
        department_ids: [],
      },
      isEdit: false,
      modal: null,
    };
  },
  mounted() {
    this.modal = createManagedModal(this.$refs.modalRef);
    this.fetchBanks();
    this.fetchDepartments();
  },
  beforeUnmount() {
    disposeManagedModal(this.modal);
  },
  methods: {
    fetchDepartments() {
      axios.get('/api/departments', { params: { per_page: 100 } })
        .then((res) => {
          this.departments = res.data.data || [];
        })
        .catch((error) => {
          console.error('Failed to load departments', error);
        });
    },
    buildPagination(data) {
      this.pagination.currentPage = data.current_page;
      this.pagination.lastPage = data.last_page;
      this.pagination.total = data.total;
      this.pagination.from = data.from;
      this.pagination.to = data.to;
      this.pagination.prevPage = data.current_page > 1 ? data.current_page - 1 : null;
      this.pagination.nextPage = data.current_page < data.last_page ? data.current_page + 1 : null;

      const pages = [];
      for (let i = 1; i <= data.last_page; i++) pages.push(i);
      this.pagination.pages = pages;
    },
    formatDate(value) {
      if (!value) return '-';
      return new Date(value).toLocaleString();
    },
    fetchBanks(page = 1) {
      this.loading = true;
      axios.get('/api/banks', {
        params: {
          page,
          per_page: this.perPage,
          search: this.filters.search || undefined,
          status: this.filters.status || undefined,
        },
      }).then((res) => {
        this.banks = res.data.data || [];
        this.buildPagination(res.data);
      }).catch((error) => {
        notify.error(error.response?.data?.message || 'Failed to load banks.', 'Banks');
      }).finally(() => {
        this.loading = false;
      });
    },
    goToPage(page) {
      if (!page || page === this.pagination.currentPage) return;
      this.fetchBanks(page);
    },
    applyFilters() {
      this.fetchBanks(1);
    },
    resetFilters() {
      this.filters = { search: '', status: 'All' };
      this.fetchBanks(1);
    },
    openCreateModal() {
      this.isEdit = false;
      this.form = { id: null, name: '', code: '', status: 'Active', department_ids: [] };
      this.modal.show();
    },
    openEditModal(bank) {
      this.isEdit = true;
      this.form = {
        id: bank.id,
        name: bank.name,
        code: bank.code,
        status: bank.status || 'Active',
        department_ids: (bank.departments || []).map(d => d.id),
      };
      this.modal.show();
    },
    save() {
      const request = this.isEdit
        ? axios.put(`/api/banks/${this.form.id}`, this.form)
        : axios.post('/api/banks', this.form);

      request.then(() => {
        this.modal.hide();
        this.fetchBanks(this.isEdit ? this.pagination.currentPage : 1);
        notify.success(`Bank ${this.isEdit ? 'updated' : 'created'} successfully.`, 'Banks');
      }).catch((error) => {
        notify.error(error.response?.data?.message || `Failed to ${this.isEdit ? 'update' : 'create'} bank.`, 'Banks');
      });
    },
    remove(bank) {
      this.$refs.confirmModal.open({
        title: 'Delete Bank',
        message: `Delete bank "${bank.name}"? This action cannot be undone if the bank is unused.`,
        confirmLabel: 'Delete Bank',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            await axios.delete(`/api/banks/${bank.id}`);
            this.fetchBanks(this.pagination.currentPage);
            notify.success(`Bank "${bank.name}" deleted.`, 'Banks');
          } catch (error) {
            const usage = error.response?.data?.usage;
            const message = Array.isArray(usage) && usage.length
              ? `${error.response?.data?.message} (${usage.map((item) => `${item.label}: ${item.count}`).join(', ')})`
              : (error.response?.data?.message || 'Failed to delete bank.');
            notify.error(message, 'Banks');
            throw error;
          }
        },
      });
    },
  },
};
</script>
