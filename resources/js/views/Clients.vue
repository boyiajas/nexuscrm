<template>
  <div class="clients-page">
    <!-- Header + actions -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-people me-2"></i>Clients</h2>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" @click="openImportModal" :disabled="!canManage">
          <i class="bi bi-file-earmark-arrow-up me-1"></i> Import CSV / Excel
        </button>
        <router-link
          class="btn btn-outline-info btn-sm"
          :to="{ name: 'import-uploads' }"
        >
          <i class="bi bi-shield-check me-1"></i> Import Uploads
        </router-link>
        <button class="btn btn-outline-secondary btn-sm" @click="exportCsv">
          <i class="bi bi-file-earmark-arrow-down me-1"></i> Export CSV
        </button>
        <button class="btn btn-primary btn-sm" @click="openCreateModal" :disabled="!canManage">
          <i class="bi bi-plus-circle me-1"></i> Add Client
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="applyFilters">
          <div class="col-md-3">
            <label class="form-label">Search</label>
            <input
              v-model="filters.q"
              type="text"
              class="form-control"
              placeholder="Name, email, phone..."
            />
          </div>

          <div class="col-md-3">
            <label class="form-label">Department</label>
            <select v-model="filters.department" class="form-select">
              <option value="">All</option>
              <option v-for="d in departmentOptions" :key="d.id" :value="d.name">
                {{ d.name }}
              </option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Import Batch</label>
            <select v-model="filters.import_batch_number" class="form-select">
              <option value="">All batches</option>
              <option v-for="batch in clientBatchOptions" :key="batch" :value="batch">
                {{ batch }}
              </option>
            </select>
          </div>

          <div class="col-md-3" v-if="canChooseBank">
            <label class="form-label">Bank</label>
            <select v-model="filters.bank_id" class="form-select">
              <option value="">All</option>
              <option v-for="bank in banks" :key="bank.id" :value="bank.id">
                {{ bank.name }}
              </option>
            </select>
          </div>

          <div class="col-md-3 text-md-end">
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

    <!-- Clients table -->
    <div class="card shadow-sm">
      <div v-if="canManage" class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 py-2 border-bottom bg-light-subtle">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            @click="selectAllVisibleClients"
            :disabled="clients.length === 0"
          >
            <i class="bi bi-check2-square me-1"></i> Select All Visible
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            @click="clearSelectedClients"
            :disabled="selectedClientIds.length === 0"
          >
            Clear
          </button>
          <small v-if="selectedClientIds.length > 0" class="text-muted">
            {{ selectedClientIds.length }} selected
          </small>
        </div>

        <button
          type="button"
          class="btn btn-danger btn-sm"
          @click="removeSelectedClients"
          :disabled="selectedClientIds.length === 0"
        >
          <i class="bi bi-trash me-1"></i> Delete Selected
        </button>
        <button
          type="button"
          class="btn btn-outline-danger btn-sm"
          @click="removeBatchClients"
          :disabled="!currentBatchFilter"
        >
          <i class="bi bi-layers me-1"></i> Delete Batch
        </button>
      </div>

      <div class="card-body p-0 clients-table-wrap">
        <TableLoadingWrapper :loading="loading" message="Loading clients..." min-height="320px">
          <table class="table table-sm table-hover mb-0 align-middle clients-table">
            <thead class="table-light">
              <tr>
                <th v-if="canManage" class="clients-col-select"></th>
                <th class="clients-col-name">Name</th>
                <th class="clients-col-email">Email Personal</th>
                <th class="clients-col-cell">Cell</th>
                <th class="clients-col-bank">Bank</th>
                <th class="clients-col-import">Import Batch</th>
                <th class="clients-col-created">Import / Created By</th>
                <th class="clients-col-departments">Departments</th>
                <th class="clients-col-actions text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in clients" :key="c.id">
                <td v-if="canManage" class="clients-col-select">
                  <input
                    :id="`client-select-${c.id}`"
                    class="form-check-input"
                    type="checkbox"
                    :checked="selectedClientIds.includes(c.id)"
                    @change="toggleClientSelection(c.id, $event.target.checked)"
                  />
                </td>
                <td class="clients-col-name">
                  <div class="clients-cell-text" :title="c.name">
                    {{ c.name }}
                  </div>
                </td>
                <td class="clients-col-email">
                  <div class="clients-cell-text" :title="c.email || '-'">
                    {{ c.email || '-' }}
                  </div>
                </td>
                <td class="clients-col-cell">
                  <div class="clients-cell-text" :title="c.phone || '-'">
                    {{ c.phone || '-' }}
                  </div>
                </td>
                <td class="clients-col-bank">
                  <div class="clients-cell-text" :title="c.bank_name || '-'">
                    {{ c.bank_name || '-' }}
                  </div>
                </td>
                <td class="clients-col-import">
                  <span v-if="c.import_batch_number" class="badge bg-light text-dark border">
                    {{ c.import_batch_number }}
                  </span>
                  <span v-else class="text-muted">-</span>
                </td>
                <td class="clients-col-created">
                  <div class="clients-cell-text" :title="c.created_by_label || '-'">
                    {{ c.created_by_label || '-' }}
                  </div>
                </td>
                <td class="clients-col-departments">
                  <template v-if="c.departments && c.departments.length">
                    <span
                      v-for="d in c.departments"
                      :key="d.id"
                      class="badge bg-light text-dark border me-1"
                    >
                      {{ d.name }}
                    </span>
                  </template>
                  <span v-else class="text-muted">-</span>
                </td>
                <td class="clients-col-actions text-end">
                  <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-primary" title="Edit" @click="openEditModal(c)" :disabled="!canManage">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-outline-danger" title="Delete" @click="remove(c)" :disabled="!canManage">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && clients.length === 0">
                <td :colspan="canManage ? 9 : 8" class="text-center text-muted py-3">
                  No clients found.
                </td>
              </tr>
            </tbody>
          </table>
        </TableLoadingWrapper>
      </div>

      <!-- Pagination -->
      <div class="card-footer d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <small class="text-muted">
            Showing {{ pagination.from || 0 }}–{{ pagination.to || 0 }}
            of {{ pagination.total || 0 }}
          </small>

          <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 text-muted">Rows per page</label>
            <select
              v-model.number="pageSize"
              class="form-select form-select-sm clients-page-size"
              @change="changePageSize"
            >
              <option v-for="size in pageSizeOptions" :key="size" :value="size">
                {{ size }}
              </option>
            </select>
          </div>
        </div>

        <ul class="pagination mb-0 pagination-sm">
          <li class="page-item" :class="{ disabled: !pagination.prevPage }">
            <button class="page-link" @click="goToPage(pagination.prevPage)">«</button>
          </li>

          <li
            v-for="p in pagination.pages"
            :key="p.key"
            class="page-item"
            :class="{ active: p.active, disabled: p.ellipsis }"
          >
            <button class="page-link" @click="goToPage(p.page)" :disabled="p.ellipsis">
              {{ p.label }}
            </button>
          </li>

          <li class="page-item" :class="{ disabled: !pagination.nextPage }">
            <button class="page-link" @click="goToPage(pagination.nextPage)">»</button>
          </li>
        </ul>
      </div>
    </div>

    <div class="modal fade" tabindex="-1" ref="importModalRef">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import Clients</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <div class="alert alert-info py-2">
              Upload a CSV or Excel `.xlsx` file. The selected departments below will be attached to every imported client.
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">File <span class="text-danger">*</span></label>
                <input
                  ref="importFileInput"
                  type="file"
                  class="form-control"
                  accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                  @change="onImportFileSelected"
                />
                <small class="text-muted">Supported formats: `.csv` and `.xlsx`.</small>
              </div>

              <div class="col-md-6" v-if="canChooseBank">
                <label class="form-label">Bank <span class="text-danger">*</span></label>
                <select v-model="importForm.bank_id" class="form-select">
                  <option value="">Select bank</option>
                  <option v-for="bank in banks" :key="bank.id" :value="bank.id">
                    {{ bank.name }}
                  </option>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label">Departments <span class="text-danger">*</span></label>
                <vue-multiselect
                  v-model="importSelectedDepartments"
                  :options="departmentOptions"
                  :multiple="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  placeholder="Select one or more departments for this import"
                  label="name"
                  track-by="id"
                  :searchable="true"
                  class="mb-2"
                >
                  <template #noResult>No departments found</template>
                  <template #noOptions>No departments available</template>
                </vue-multiselect>

                <div class="d-flex justify-content-between">
                  <small class="text-muted">
                    Choose one or more departments to attach to all imported clients.
                  </small>
                  <div>
                    <button
                      type="button"
                      class="btn btn-link btn-sm p-0 me-2"
                      @click="selectAllImportDepartments"
                    >
                      Select all
                    </button>
                    <button
                      type="button"
                      class="btn btn-link btn-sm p-0 text-danger"
                      @click="clearImportDepartments"
                    >
                      Clear
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="importForm.uploading">
              Cancel
            </button>
            <button type="button" class="btn btn-success" @click="submitImport" :disabled="importForm.uploading">
              <span v-if="importForm.uploading" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-upload me-1"></i>
              Import Clients
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">
              {{ isEdit ? 'Edit Client' : 'Add Client' }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="save">
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Name <span class="text-danger">*</span></label>
                  <input v-model="form.name" type="text" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input v-model="form.email" type="email" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input v-model="form.phone" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">ID Number</label>
                  <input
                    v-model="form.id_number"
                    type="text"
                    class="form-control"
                    @copy.prevent
                    @cut.prevent
                    @paste.prevent
                    @drop.prevent
                    autocomplete="off"
                  />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Bank <span class="text-danger">*</span></label>
                  <select v-model="form.bank_id" class="form-select" :disabled="!canChooseBank">
                    <option value="">Select bank</option>
                    <option v-for="bank in banks" :key="bank.id" :value="bank.id">
                      {{ bank.name }}
                    </option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Bank Name</label>
                  <input v-model="form.bank_name" type="text" class="form-control" :readonly="!!form.bank_id" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Account Number</label>
                  <input
                    v-model="form.account_number"
                    type="text"
                    class="form-control"
                    @copy.prevent
                    @cut.prevent
                    @paste.prevent
                    @drop.prevent
                    autocomplete="off"
                  />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Branch Code</label>
                  <input v-model="form.branch_code" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Assigned Portfolio Owner</label>
                  <select v-model="form.assigned_to_id" class="form-select" :disabled="!canChooseAssignee">
                    <option value="">Unassigned</option>
                    <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">
                      {{ assignee.name }} ({{ assignee.role }})
                    </option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Tags (comma separated)</label>
                  <input
                    v-model="tagsInput"
                    type="text"
                    class="form-control"
                    placeholder="VIP, Overdue, ..."
                  />
                </div>
              </div>

              <div class="card border mb-3">
                <div class="card-header bg-light fw-semibold">WhatsApp Compliance</div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Lawful Basis</label>
                      <select v-model="form.whatsapp_contact_basis" class="form-select">
                        <option value="">Select lawful basis</option>
                        <option value="bank_instruction">Bank Instruction</option>
                        <option value="opt_in">Explicit Opt-In</option>
                        <option value="contract">Contract</option>
                        <option value="legitimate_interest">Legitimate Interest</option>
                        <option value="consent_refresh">Consent Refresh</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Opt-In Source</label>
                      <input v-model="form.whatsapp_opt_in_source" type="text" class="form-control" placeholder="bank_import, signed consent, call recording..." />
                    </div>
                    <div class="col-12">
                      <label class="form-label">Lawful Basis Details</label>
                      <textarea v-model="form.whatsapp_contact_basis_details" class="form-control" rows="2" placeholder="Describe the source of permission or lawful basis for WhatsApp contact."></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Opt-In Date</label>
                      <input v-model="form.whatsapp_opted_in_at" type="datetime-local" class="form-control" />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label d-block">Suppression</label>
                      <div class="form-check mt-2">
                        <input id="client-whatsapp-opted-out" v-model="form.whatsapp_opted_out" class="form-check-input" type="checkbox" />
                        <label class="form-check-label" for="client-whatsapp-opted-out">
                          Block this client from WhatsApp messaging
                        </label>
                      </div>
                    </div>
                    <div class="col-12" v-if="form.whatsapp_opted_out">
                      <label class="form-label">Opt-Out / Suppression Reason</label>
                      <input v-model="form.whatsapp_opt_out_reason" type="text" class="form-control" placeholder="STOP, customer request, legal restriction..." />
                    </div>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Departments <span class="text-danger">*</span></label>
                <vue-multiselect
                  v-model="selectedDepartments"
                  :options="departmentOptions"
                  :multiple="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  placeholder="Select one or more departments"
                  label="name"
                  track-by="id"
                  :searchable="true"
                  :allow-empty="false"
                  class="mb-2"
                >
                  <template slot="noResult">No departments found</template>
                  <template slot="noOptions">No departments available</template>
                </vue-multiselect>
                
                <div class="d-flex justify-content-between">
                  <small class="text-muted">
                    Select at least one department
                  </small>
                  <div>
                    <button
                      type="button"
                      class="btn btn-link btn-sm p-0 me-2"
                      @click="selectAllDepartments"
                    >
                      Select all
                    </button>
                    <button
                      type="button"
                      class="btn btn-link btn-sm p-0 text-danger"
                      @click="clearDepartments"
                    >
                      Clear
                    </button>
                  </div>
                </div>
              </div>

              <div class="text-end">
                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                  Cancel
                </button>
                <button type="submit" class="btn btn-primary" :disabled="selectedDepartments.length === 0">
                  Save
                </button>
              </div>

            </form>
          </div>

        </div>
      </div>
    </div>

    <ExportRequestModal ref="exportRequestModal" />
    <ConfirmationModal ref="confirmModal" />
  </div>
</template>

<script>
import axios, { syncAuthenticatedUser } from '../axios';
import VueMultiselect from 'vue-multiselect';
import ExportRequestModal from '../components/ExportRequestModal.vue';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: 'ClientsView',
  components: {
    VueMultiselect,
    ExportRequestModal,
    ConfirmationModal,
    TableLoadingWrapper,
  },
  data() {
    return {
      clients: [],
      loading: false,
      banks: [],
      assignees: [],
      departmentOptions: [],
      clientBatchOptions: [],
      filters: {
        q: '',
        department: '',
        import_batch_number: '',
        bank_id: '',
      },
      pageSize: 25,
      pageSizeOptions: [25, 50, 100, 200, 300, 500, 1000],
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
        email: '',
        phone: '',
        bank_id: '',
        bank_name: '',
        assigned_to_id: '',
        department_ids: [], // Changed from single department to array of IDs
        tags: [],
        whatsapp_contact_basis: '',
        whatsapp_contact_basis_details: '',
        whatsapp_opted_in_at: '',
        whatsapp_opt_in_source: '',
        whatsapp_opted_out: false,
        whatsapp_opt_out_reason: '',
      },
      selectedDepartments: [], // Array of department objects for VueMultiselect
      importSelectedDepartments: [],
      tagsInput: '',
      isEdit: false,
      modal: null,
      importModal: null,
      importForm: {
        file: null,
        bank_id: '',
        department_ids: [],
        uploading: false,
      },
      selectedClientIds: [],
    };
  },
  async mounted() {
    this.modal = createManagedModal(this.$refs.modalRef);
    this.importModal = createManagedModal(this.$refs.importModalRef);
    await this.syncCurrentUser();
    this.fetchBanks();
    this.fetchAssignees();
    this.fetchDepartments();
    this.fetchClients();
  },
  beforeUnmount() {
    disposeManagedModal(this.modal);
    disposeManagedModal(this.importModal);
  },
  computed: {
    currentUser() {
      const stored = localStorage.getItem('nexus_user');
      if (!stored) return null;
      try {
        return JSON.parse(stored);
      } catch {
        return null;
      }
    },
    canManage() {
      const role = this.currentUser?.role;
      return ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AGENT', 'STAFF'].includes(role);
    },
    canChooseBank() {
      return ['SUPER_ADMIN', 'ADMIN'].includes(this.currentUser?.role);
    },
    canChooseAssignee() {
      return ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER'].includes(this.currentUser?.role);
    },
    selectedBankName() {
      if (!this.filters.bank_id) return '';
      return this.banks.find((bank) => Number(bank.id) === Number(this.filters.bank_id))?.name || '';
    },
    currentBatchFilter() {
      return String(this.filters.import_batch_number || '').trim();
    },
  },
  watch: {
    // Sync selectedDepartments with form.department_ids
    selectedDepartments: {
      handler(newVal) {
        // Extract IDs from selected department objects
        this.form.department_ids = newVal.map(dept => dept.id);
      },
      deep: true
    },
    importSelectedDepartments: {
      handler(newVal) {
        this.importForm.department_ids = newVal.map((dept) => dept.id);
      },
      deep: true,
    }
  },
  methods: {
    async syncCurrentUser() {
      try {
        await syncAuthenticatedUser();
      } catch (error) {
        console.error('Failed to sync current user before loading clients:', error);
      }
    },
    // pagination helpers
    buildPagination(data) {
      this.pagination.currentPage = data.current_page;
      this.pagination.lastPage = data.last_page;
      this.pagination.total = data.total;
      this.pagination.from = data.from;
      this.pagination.to = data.to;
      this.pageSize = Number(data.per_page || this.pageSize);

      this.pagination.prevPage = data.current_page > 1 ? data.current_page - 1 : null;
      this.pagination.nextPage = data.current_page < data.last_page ? data.current_page + 1 : null;

      this.pagination.pages = this.buildPaginationItems(data.current_page, data.last_page);
    },
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
    goToPage(page) {
      if (!page || page === this.pagination.currentPage) return;
      this.fetchClients(page);
    },

    // data loading
    fetchDepartments() {
      axios.get('/api/departments', { params: { per_page: 200 } }).then((res) => {
        this.departmentOptions = res.data.data || res.data;
      });
    },
    fetchBanks() {
      axios.get('/api/banks', { params: { per_page: 200 } }).then((res) => {
        this.banks = res.data.data || res.data;
      });
    },
    fetchAssignees() {
      if (!this.canManage) return;
      axios.get('/api/users-assignees').then((res) => {
        this.assignees = res.data.data || res.data;
      });
    },
    fetchClients(page = 1) {
      this.loading = true;
      const params = {
        page,
        search: this.filters.q || undefined,
        department: this.filters.department || undefined,
        import_batch_number: this.filters.import_batch_number || undefined,
        bank_id: this.filters.bank_id || undefined,
        per_page: this.pageSize,
      };

      return axios.get('/api/clients', { params }).then((res) => {
        this.clients = res.data.data || res.data;
        this.clientBatchOptions = res.data.batch_options || [];
        this.selectedClientIds = [];
        if (res.data.data) {
          this.buildPagination(res.data);
        } else {
          // not paginated
          this.pagination = {
            currentPage: 1,
            lastPage: 1,
            total: this.clients.length,
            from: 1,
            to: this.clients.length,
            prevPage: null,
            nextPage: null,
            pages: [1],
          };
        }
      }).catch((error) => {
        console.error('Failed to fetch clients:', error);
        notify.error(error.response?.data?.message || 'Failed to load clients.', 'Clients');
        throw error;
      }).finally(() => {
        this.loading = false;
      });
    },

    applyFilters() {
      this.fetchClients(1);
    },
    resetFilters() {
      this.filters = { q: '', department: '', import_batch_number: '', bank_id: '' };
      this.fetchClients(1);
    },
    changePageSize() {
      this.fetchClients(1);
    },

    // CRUD
    openCreateModal() {
      if (!this.canManage) return;

      this.isEdit = false;
      this.form = {
        id: null,
        name: '',
        email: '',
        phone: '',
        bank_id: this.canChooseBank ? '' : (this.currentUser?.bank_id || ''),
        id_number: '',
        bank_name: '',
        account_number: '',
        branch_code: '',
        assigned_to_id: this.canChooseAssignee ? '' : (this.currentUser?.id || ''),
        department_ids: [],
        tags: [],
        whatsapp_contact_basis: 'bank_instruction',
        whatsapp_contact_basis_details: '',
        whatsapp_opted_in_at: '',
        whatsapp_opt_in_source: 'manual_entry',
        whatsapp_opted_out: false,
        whatsapp_opt_out_reason: '',
      };
      this.selectedDepartments = [];
      this.tagsInput = '';
      this.modal.show();
    },
    openEditModal(client) {
      if (!this.canManage) return;

      this.isEdit = true;
      
      // Load full client data with departments
      axios.get(`/api/clients/${client.id}`).then((response) => {
        const fullClient = response.data;
        
        this.form = {
          id: fullClient.id,
          name: fullClient.name,
          email: fullClient.email || '',
          phone: fullClient.phone || '',
          bank_id: fullClient.bank_id || '',
          id_number: fullClient.id_number || '',
          bank_name: fullClient.bank_name || '',
          account_number: fullClient.account_number || '',
          branch_code: fullClient.branch_code || '',
          assigned_to_id: fullClient.assigned_to_id || '',
          department_ids: fullClient.departments ? fullClient.departments.map(d => d.id) : [],
          tags: fullClient.tags || [],
          whatsapp_contact_basis: fullClient.whatsapp_contact_basis || '',
          whatsapp_contact_basis_details: fullClient.whatsapp_contact_basis_details || '',
          whatsapp_opted_in_at: this.toDateTimeLocal(fullClient.whatsapp_opted_in_at),
          whatsapp_opt_in_source: fullClient.whatsapp_opt_in_source || '',
          whatsapp_opted_out: !!fullClient.whatsapp_opted_out_at,
          whatsapp_opt_out_reason: fullClient.whatsapp_opt_out_reason || '',
        };
        
        // Set selected departments for VueMultiselect
        this.selectedDepartments = this.departmentOptions.filter(dept => 
          this.form.department_ids.includes(dept.id)
        );
        
        this.tagsInput = (fullClient.tags || []).join(', ');
        this.modal.show();
      }).catch(error => {
        console.error('Failed to load client details:', error);
        notify.error('Failed to load client details.', 'Clients');
      });
    },
    
    save() {
      if (!this.canManage) return;

      // Validate at least one department is selected
      if (this.selectedDepartments.length === 0) {
        notify.warning('Please select at least one department.', 'Clients');
        return;
      }

      if (this.canChooseBank && !this.form.bank_id) {
        notify.warning('Please select a bank for this client.', 'Clients');
        return;
      }

      if (this.form.bank_id) {
        const selectedBank = this.banks.find((bank) => bank.id === Number(this.form.bank_id));
        if (selectedBank) {
          this.form.bank_name = selectedBank.name;
        }
      }
      
      // convert tagsInput to array
      this.form.tags = this.tagsInput
        .split(',')
        .map((t) => t.trim())
        .filter((t) => t.length > 0);

      if (!this.form.whatsapp_opted_out) {
        this.form.whatsapp_opt_out_reason = '';
      }

      if (this.isEdit) {
        axios.put(`/api/clients/${this.form.id}`, this.form).then(() => {
          this.modal.hide();
          this.fetchClients(this.pagination.currentPage);
        }).catch(error => {
          console.error('Failed to update client:', error);
          notify.error('Failed to update client: ' + (error.response?.data?.message || error.message), 'Clients');
        });
      } else {
        axios.post('/api/clients', this.form).then(() => {
          this.modal.hide();
          this.fetchClients(1);
          notify.success('Client created successfully.', 'Clients');
        }).catch(error => {
          console.error('Failed to create client:', error);
          notify.error('Failed to create client: ' + (error.response?.data?.message || error.message), 'Clients');
        });
      }
    },
    
    remove(client) {
      if (!this.canManage) return;

      this.$refs.confirmModal.open({
        title: 'Delete Client',
        message: `Delete client "${client.name}"? This action cannot be undone.`,
        confirmLabel: 'Delete Client',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            await axios.delete(`/api/clients/${client.id}`);
            await this.fetchClients(this.pagination.currentPage);
            notify.success(`Client "${client.name}" deleted.`, 'Clients');
          } catch (error) {
            console.error('Failed to delete client:', error);
            notify.error('Failed to delete client: ' + (error.response?.data?.message || error.message), 'Clients');
            throw error;
          }
        },
      });
    },
    toggleClientSelection(clientId, isSelected) {
      if (isSelected) {
        if (!this.selectedClientIds.includes(clientId)) {
          this.selectedClientIds = [...this.selectedClientIds, clientId];
        }
        return;
      }

      this.selectedClientIds = this.selectedClientIds.filter((id) => id !== clientId);
    },
    selectAllVisibleClients() {
      this.selectedClientIds = this.clients.map((client) => client.id);
    },
    clearSelectedClients() {
      this.selectedClientIds = [];
    },
    removeSelectedClients() {
      if (!this.canManage || this.selectedClientIds.length === 0) return;

      const selectedIds = [...this.selectedClientIds];
      const selectedCount = selectedIds.length;

      this.$refs.confirmModal.open({
        title: 'Delete Selected Clients',
        message: `Delete ${selectedCount} selected client${selectedCount === 1 ? '' : 's'}? This action cannot be undone.`,
        confirmLabel: 'Delete Selected',
        confirmVariant: 'danger',
        onConfirm: async () => {
          const results = await Promise.allSettled(
            selectedIds.map((id) => axios.delete(`/api/clients/${id}`))
          );

          const failed = results.filter((result) => result.status === 'rejected');
          const deletedCount = results.length - failed.length;

          this.clearSelectedClients();
          await this.fetchClients(1);

          if (deletedCount > 0) {
            notify.success(
              `Deleted ${deletedCount} client${deletedCount === 1 ? '' : 's'}.`,
              'Clients'
            );
          }

          if (failed.length > 0) {
            const firstError =
              failed[0]?.reason?.response?.data?.message ||
              failed[0]?.reason?.message ||
              'One or more selected clients could not be deleted.';

            notify.error(
              `${failed.length} client${failed.length === 1 ? '' : 's'} could not be deleted. ${firstError}`,
              'Clients'
            );
          }
        },
      });
    },
    removeBatchClients() {
      if (!this.canManage) return;

      const batchNumber = this.currentBatchFilter;
      if (!batchNumber) {
        notify.warning('Enter or select an import batch number first.', 'Clients');
        return;
      }

      this.$refs.confirmModal.open({
        title: 'Delete Clients By Batch',
        message: `Delete all accessible clients from batch ${batchNumber}? This will also remove them from any campaigns. This action cannot be undone.`,
        confirmLabel: 'Delete Batch',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            const response = await axios.delete('/api/clients/delete-batch', {
              data: {
                import_batch_number: batchNumber,
              },
            });

            this.clearSelectedClients();
            this.filters.import_batch_number = '';
            await this.fetchClients(1);

            notify.success(
              `Deleted ${response.data?.deleted_count || 0} client(s) from batch "${batchNumber}".`,
              'Clients'
            );
          } catch (error) {
            console.error('Failed to delete clients by batch:', error);
            notify.error(
              'Failed to delete batch clients: ' + (error.response?.data?.message || error.message),
              'Clients'
            );
            throw error;
          }
        },
      });
    },
    
    // Department selection helpers
    selectAllDepartments() {
      if (!Array.isArray(this.departmentOptions)) return;
      this.selectedDepartments = [...this.departmentOptions];
    },
    
    clearDepartments() {
      this.selectedDepartments = [];
    },

    // Import / Export
    openImportModal() {
      if (!this.canManage) return;
      this.importForm = {
        file: null,
        bank_id: this.canChooseBank ? (this.filters.bank_id || '') : (this.currentUser?.bank_id || ''),
        department_ids: [],
        uploading: false,
      };
      this.importSelectedDepartments = [];
      if (this.$refs.importFileInput) {
        this.$refs.importFileInput.value = '';
      }
      this.importModal.show();
    },
    onImportFileSelected(event) {
      const file = event.target.files?.[0] || null;
      this.importForm.file = file;
    },
    selectAllImportDepartments() {
      if (!Array.isArray(this.departmentOptions)) return;
      this.importSelectedDepartments = [...this.departmentOptions];
    },
    clearImportDepartments() {
      this.importSelectedDepartments = [];
    },
    submitImport() {
      if (!this.importForm.file) {
        notify.warning('Please choose a CSV or Excel file to import.', 'Clients');
        return;
      }

      if (this.canChooseBank && !this.importForm.bank_id) {
        notify.warning('Please select a bank for this import.', 'Clients');
        return;
      }

      if (!this.importSelectedDepartments.length) {
        notify.warning('Please select at least one department for this import.', 'Clients');
        return;
      }

      const formData = new FormData();
      formData.append('file', this.importForm.file);
      if (this.importForm.bank_id) {
        formData.append('bank_id', this.importForm.bank_id);
      }
      for (const departmentId of this.importForm.department_ids) {
        formData.append('department_ids[]', departmentId);
      }

      this.importForm.uploading = true;

      axios
        .post('/api/clients/import', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        .then((response) => {
          const data = response.data;
          notify.success(
            `Import completed for batch ${data.import_batch_number || '-'}. Imported: ${data.imported || 0}, created: ${data.created || 0}, updated: ${data.updated || 0}, duplicates: ${data.duplicates || 0}, skipped: ${data.skipped || 0}.`,
            'Clients'
          );
          if (data.errors && data.errors.length > 0) {
            console.warn('Import errors:', data.errors);
          }
          this.importModal.hide();
          this.fetchClients(1);
        })
        .catch(error => {
          console.error('Import failed:', error);
          notify.error('Import failed: ' + (error.response?.data?.message || error.message), 'Clients');
        })
        .finally(() => {
          this.importForm.uploading = false;
          if (this.$refs.importFileInput) {
            this.$refs.importFileInput.value = '';
          }
          this.importForm.file = null;
        });
    },
    exportCsv() {
      this.$refs.exportRequestModal.open({
        dataset: 'clients',
        datasetLabel: 'Clients',
        filters: {
          search: this.filters.q || '',
          department: this.filters.department || '',
          import_batch_number: this.filters.import_batch_number || '',
          bank_id: this.filters.bank_id || '',
        },
        summaryRows: [
          { label: 'Search', value: this.filters.q || 'All clients' },
          { label: 'Department', value: this.filters.department || 'All departments' },
          { label: 'Import Batch', value: this.filters.import_batch_number || 'All batches' },
          { label: 'Bank Scope', value: this.selectedBankName || 'Current access scope' },
        ],
        fallbackName: 'clients.csv',
      });
    },
    toDateTimeLocal(value) {
      if (!value) return '';
      const normalized = String(value).replace(' ', 'T');
      return normalized.slice(0, 16);
    },
  },
};
</script>

<style scoped>
/* Optional custom styling for the multiselect */
:deep(.multiselect) {
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
}

:deep(.multiselect:focus-within) {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

:deep(.multiselect__tags) {
  min-height: 38px;
  border: none;
}

:deep(.multiselect__tag) {
  background: #0d6efd;
}

:deep(.multiselect__tag-icon:after) {
  color: white;
}

:deep(.multiselect__tag-icon:hover) {
  background: #0b5ed7;
}

:deep(.multiselect__option--highlight) {
  background: #0d6efd;
}

:deep(.multiselect__option--highlight:after) {
  background: #0d6efd;
}

.clients-table-wrap {
  overflow-x: auto;
}

.clients-table {
  width: 100%;
  min-width: 1120px;
  table-layout: fixed;
}

.clients-table th,
.clients-table td {
  white-space: nowrap;
}

.clients-col-select {
  width: 48px;
}

.clients-col-name {
  width: 18%;
}

.clients-col-email {
  width: 24%;
}

.clients-col-cell {
  width: 12%;
}

.clients-col-bank {
  width: 11%;
}

.clients-col-import {
  width: 15%;
}

.clients-col-created {
  width: 14%;
}

.clients-col-departments {
  width: 9%;
}

.clients-col-actions {
  width: 96px;
}

.clients-cell-text {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
}

.clients-page .card,
.clients-page .table,
.clients-page .form-label,
.clients-page .form-control,
.clients-page .form-select,
.clients-page .btn,
.clients-page .pagination,
.clients-page .badge,
.clients-page small {
  font-size: 0.84rem;
}

.clients-page .table th,
.clients-page .table td {
  font-size: 0.8rem;
  padding-top: 0.45rem;
  padding-bottom: 0.45rem;
}

.clients-page .btn-group-sm > .btn,
.clients-page .btn-sm {
  font-size: 0.78rem;
}

.clients-page-size {
  width: 110px;
}
</style>
