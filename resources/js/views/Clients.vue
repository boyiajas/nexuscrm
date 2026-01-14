<template>
  <div>
    <!-- Header + actions -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-people me-2"></i>Clients</h2>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" @click="triggerImport">
          <i class="bi bi-file-earmark-arrow-up me-1"></i> Import CSV
        </button>
        <button class="btn btn-outline-secondary btn-sm" @click="exportCsv">
          <i class="bi bi-file-earmark-arrow-down me-1"></i> Export CSV
        </button>
        <button class="btn btn-primary btn-sm" @click="openCreateModal">
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
                <button class="btn btn-sm btn-outline-primary me-1" @click="openEditModal(c)">
                  Edit
                </button>
                <button class="btn btn-sm btn-outline-danger" @click="remove(c)">
                  Delete
                </button>
              </td>
            </tr>
            <tr v-if="clients.length === 0">
              <td colspan="6" class="text-center text-muted py-3">
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
      <div class="modal-dialog">
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

  </div>
</template>

<script>
import axios from '../axios';
import { Modal } from 'bootstrap';
import VueMultiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: 'ClientsView',
  components: {
    VueMultiselect,
  },
  data() {
    return {
      clients: [],
      departmentOptions: [],
      filters: {
        q: '',
        department: '',
        tag: '',
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
        department_ids: [], // Changed from single department to array of IDs
        tags: [],
      },
      selectedDepartments: [], // Array of department objects for VueMultiselect
      tagsInput: '',
      isEdit: false,
      modal: null,
    };
  },
  mounted() {
    this.modal = new Modal(this.$refs.modalRef);
    this.fetchDepartments();
    this.fetchClients();
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
    fetchClients(page = 1) {
      const params = {
        page,
        q: this.filters.q || undefined,
        department: this.filters.department || undefined,
        tag: this.filters.tag || undefined,
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
      });
    },

    applyFilters() {
      this.fetchClients(1);
    },
    resetFilters() {
      this.filters = { q: '', department: '', tag: '' };
      this.fetchClients(1);
    },

    // CRUD
    openCreateModal() {
      this.isEdit = false;
      this.form = {
        id: null,
        name: '',
        email: '',
        phone: '',
        department_ids: [],
        tags: [],
      };
      this.selectedDepartments = [];
      this.tagsInput = '';
      this.modal.show();
    },
    openEditModal(client) {
      this.isEdit = true;
      
      // Load full client data with departments
      axios.get(`/api/clients/${client.id}`).then((response) => {
        const fullClient = response.data;
        
        this.form = {
          id: fullClient.id,
          name: fullClient.name,
          email: fullClient.email || '',
          phone: fullClient.phone || '',
          department_ids: fullClient.departments ? fullClient.departments.map(d => d.id) : [],
          tags: fullClient.tags || [],
        };
        
        // Set selected departments for VueMultiselect
        this.selectedDepartments = this.departmentOptions.filter(dept => 
          this.form.department_ids.includes(dept.id)
        );
        
        this.tagsInput = (fullClient.tags || []).join(', ');
        this.modal.show();
      }).catch(error => {
        console.error('Failed to load client details:', error);
        alert('Failed to load client details');
      });
    },
    
    save() {
      // Validate at least one department is selected
      if (this.selectedDepartments.length === 0) {
        alert('Please select at least one department');
        return;
      }
      
      // convert tagsInput to array
      this.form.tags = this.tagsInput
        .split(',')
        .map((t) => t.trim())
        .filter((t) => t.length > 0);

      if (this.isEdit) {
        axios.put(`/api/clients/${this.form.id}`, this.form).then(() => {
          this.modal.hide();
          this.fetchClients(this.pagination.currentPage);
        }).catch(error => {
          console.error('Failed to update client:', error);
          alert('Failed to update client: ' + (error.response?.data?.message || error.message));
        });
      } else {
        axios.post('/api/clients', this.form).then(() => {
          this.modal.hide();
          this.fetchClients(1);
        }).catch(error => {
          console.error('Failed to create client:', error);
          alert('Failed to create client: ' + (error.response?.data?.message || error.message));
        });
      }
    },
    
    remove(client) {
      if (!confirm(`Delete client "${client.name}"?`)) return;
      axios.delete(`/api/clients/${client.id}`).then(() => {
        this.fetchClients(this.pagination.currentPage);
      }).catch(error => {
        console.error('Failed to delete client:', error);
        alert('Failed to delete client: ' + (error.response?.data?.message || error.message));
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
      this.$refs.importInput.click();
    },
    handleImport(event) {
      const file = event.target.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append('file', file);

      axios
        .post('/api/clients/import', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        .then((response) => {
          const data = response.data;
          alert(`Import completed. Imported: ${data.imported || 0} clients`);
          if (data.errors && data.errors.length > 0) {
            console.warn('Import errors:', data.errors);
          }
          this.fetchClients(1);
        })
        .catch(error => {
          console.error('Import failed:', error);
          alert('Import failed: ' + (error.response?.data?.message || error.message));
        })
        .finally(() => {
          event.target.value = '';
        });
    },
    exportCsv() {
      const params = new URLSearchParams({
        q: this.filters.q || '',
        department: this.filters.department || '',
        tag: this.filters.tag || '',
      }).toString();

      window.open('/api/clients/export?' + params, '_blank');
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