<template>
  <div>
    <!-- Header + actions -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-people me-2"></i>Clients</h2>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" @click="triggerImport" :disabled="!canManage">
          <i class="bi bi-file-earmark-arrow-up me-1"></i> Import CSV
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
            <label class="form-label">Tags (contains)</label>
            <input
              v-model="filters.tag"
              type="text"
              class="form-control"
              placeholder="VIP, overdue..."
            />
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
      <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Bank</th>
              <th>WhatsApp Compliance</th>
              <th>Masked Sensitive Data</th>
              <th>Departments</th>
              <th>Tags</th>
              <th style="width: 130px;" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in clients" :key="c.id">
              <td>{{ c.name }}</td>
              <td>{{ c.email || '-' }}</td>
              <td>{{ c.phone || '-' }}</td>
              <td>{{ c.bank_name || '-' }}</td>
              <td>
                <span
                  class="badge"
                  :class="{
                    'bg-success': c.whatsapp_compliance_status === 'Eligible',
                    'bg-warning text-dark': c.whatsapp_compliance_status === 'Missing Lawful Basis',
                    'bg-danger': c.whatsapp_compliance_status === 'Suppressed',
                  }"
                >
                  {{ c.whatsapp_compliance_status || 'Unknown' }}
                </span>
                <div class="small text-muted mt-1" v-if="c.whatsapp_opt_in_source || c.whatsapp_opt_out_reason">
                  {{ c.whatsapp_opt_out_reason || c.whatsapp_opt_in_source }}
                </div>
              </td>
              <td>
                <div class="small">
                  <div>ID: {{ c.id_number_masked || '-' }}</div>
                  <div>Account: {{ c.account_number_masked || '-' }}</div>
                </div>
              </td>
              <td>
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
              <td>
                <span
                  v-for="tag in (c.tags || [])"
                  :key="tag"
                  class="badge bg-light text-dark border me-1"
                >
                  {{ tag }}
                </span>
                <span v-if="!c.tags || c.tags.length === 0" class="text-muted">-</span>
              </td>
              <td class="text-end">
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
            <tr v-if="clients.length === 0">
              <td colspan="9" class="text-center text-muted py-3">
                No clients found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
          Showing {{ pagination.from || 0 }}–{{ pagination.to || 0 }}
          of {{ pagination.total || 0 }}
        </small>

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
            <button class="page-link" @click="goToPage(p)">
              {{ p }}
            </button>
          </li>

          <li class="page-item" :class="{ disabled: !pagination.nextPage }">
            <button class="page-link" @click="goToPage(pagination.nextPage)">»</button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Hidden file input for import -->
    <input
      ref="importInput"
      type="file"
      class="d-none"
      accept=".csv,text/csv"
      @change="handleImport"
    />

    <!-- Create/Edit Modal -->
    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">
              {{ isEdit ? 'Edit Client' : 'Add Client' }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="save">

              <div class="mb-3">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input v-model="form.name" type="text" class="form-control" required />
              </div>

              <div class="mb-3">
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label">Phone</label>
                <input v-model="form.phone" type="text" class="form-control" />
              </div>

              <div class="mb-3">
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

              <div class="row g-3 mb-3">
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
              </div>
              <div class="mb-3">
                <label class="form-label">Branch Code</label>
                <input v-model="form.branch_code" type="text" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label">Assigned Portfolio Owner</label>
                <select v-model="form.assigned_to_id" class="form-select" :disabled="!canChooseAssignee">
                  <option value="">Unassigned</option>
                  <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">
                    {{ assignee.name }} ({{ assignee.role }})
                  </option>
                </select>
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

              <div class="mb-3">
                <label class="form-label">Tags (comma separated)</label>
                <input
                  v-model="tagsInput"
                  type="text"
                  class="form-control"
                  placeholder="VIP, Overdue, ..."
                />
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
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: 'ClientsView',
  components: {
    VueMultiselect,
    ExportRequestModal,
    ConfirmationModal,
  },
  data() {
    return {
      clients: [],
      banks: [],
      assignees: [],
      departmentOptions: [],
      filters: {
        q: '',
        department: '',
        tag: '',
        bank_id: '',
      },
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
      tagsInput: '',
      isEdit: false,
      modal: null,
    };
  },
  async mounted() {
    this.modal = createManagedModal(this.$refs.modalRef);
    await this.syncCurrentUser();
    this.fetchBanks();
    this.fetchAssignees();
    this.fetchDepartments();
    this.fetchClients();
  },
  beforeUnmount() {
    disposeManagedModal(this.modal);
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
  },
  watch: {
    // Sync selectedDepartments with form.department_ids
    selectedDepartments: {
      handler(newVal) {
        // Extract IDs from selected department objects
        this.form.department_ids = newVal.map(dept => dept.id);
      },
      deep: true
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

      this.pagination.prevPage = data.current_page > 1 ? data.current_page - 1 : null;
      this.pagination.nextPage = data.current_page < data.last_page ? data.current_page + 1 : null;

      const pages = [];
      for (let i = 1; i <= data.last_page; i++) pages.push(i);
      this.pagination.pages = pages;
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
      const params = {
        page,
        search: this.filters.q || undefined,
        department: this.filters.department || undefined,
        tag: this.filters.tag || undefined,
        bank_id: this.filters.bank_id || undefined,
      };

      axios.get('/api/clients', { params }).then((res) => {
        this.clients = res.data.data || res.data;
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
      });
    },

    applyFilters() {
      this.fetchClients(1);
    },
    resetFilters() {
      this.filters = { q: '', department: '', tag: '', bank_id: '' };
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
            this.fetchClients(this.pagination.currentPage);
            notify.success(`Client "${client.name}" deleted.`, 'Clients');
          } catch (error) {
            console.error('Failed to delete client:', error);
            notify.error('Failed to delete client: ' + (error.response?.data?.message || error.message), 'Clients');
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
    triggerImport() {
      if (!this.canManage) return;

      this.$refs.importInput.click();
    },
    handleImport(event) {
      const file = event.target.files[0];
      if (!file) return;

      if (this.canChooseBank && !this.filters.bank_id) {
        notify.warning('Select a bank filter before importing clients.', 'Clients');
        event.target.value = '';
        return;
      }

      const formData = new FormData();
      formData.append('file', file);
      if (this.filters.bank_id) {
        formData.append('bank_id', this.filters.bank_id);
      }

      axios
        .post('/api/clients/import', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        .then((response) => {
          const data = response.data;
          notify.success(
            `Import completed. Imported: ${data.imported || 0}, created: ${data.created || 0}, updated: ${data.updated || 0}, duplicates: ${data.duplicates || 0}, skipped: ${data.skipped || 0}.`,
            'Clients'
          );
          if (data.errors && data.errors.length > 0) {
            console.warn('Import errors:', data.errors);
          }
          this.fetchClients(1);
        })
        .catch(error => {
          console.error('Import failed:', error);
          notify.error('Import failed: ' + (error.response?.data?.message || error.message), 'Clients');
        })
        .finally(() => {
          event.target.value = '';
        });
    },
    exportCsv() {
      this.$refs.exportRequestModal.open({
        dataset: 'clients',
        datasetLabel: 'Clients',
        filters: {
          search: this.filters.q || '',
          department: this.filters.department || '',
          tag: this.filters.tag || '',
          bank_id: this.filters.bank_id || '',
        },
        summaryRows: [
          { label: 'Search', value: this.filters.q || 'All clients' },
          { label: 'Department', value: this.filters.department || 'All departments' },
          { label: 'Tag Filter', value: this.filters.tag || 'No tag filter' },
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
</style>
