<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-diagram-3 me-2"></i>Departments</h2>
      <button class="btn btn-primary btn-sm" @click="openCreateModal">
        + Add Department
      </button>
    </div>

    <div class="card shadow-sm border mb-4">
      <div class="card-body p-0">
        <TableLoadingWrapper :loading="loading" message="Loading departments...">
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th class="ps-4">Name</th>
              <th>Description</th>
              <th>WhatsApp Numbers</th>
              <th style="width: 120px;" class="text-end pe-4">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="d in departments" :key="d.id">
              <td class="ps-4 py-3">{{ d.name }}</td>
              <td>{{ d.description || '-' }}</td>
              <td>
                <div v-if="d.primary_whatsapp_number">
                  <span class="badge bg-primary me-1" title="Primary">{{ d.primary_whatsapp_number }}</span>
                  <span
                    v-for="num in d.secondary_whatsapp_numbers || []"
                    :key="num"
                    class="badge bg-light text-dark border me-1"
                    title="Secondary"
                  >
                    {{ num }}
                  </span>
                </div>
                <span v-else class="text-muted">Default</span>
              </td>
              <td class="text-end pe-4">
                <div class="btn-group btn-group-sm" role="group">
                  <button class="btn btn-light text-info border-0 p-1 px-2" title="Stats" @click="$refs.statsModal.open(d)">
                    <i class="bi bi-bar-chart"></i>
                  </button>
                  <button class="btn btn-light text-secondary border-0 p-1 px-2" title="Edit" @click="openEditModal(d)">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <button class="btn btn-light text-danger border-0 p-1 px-2" title="Delete" @click="remove(d)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!loading && departments.length === 0">
              <td colspan="4" class="text-center text-muted py-5">
                No departments found.
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
            <select class="form-select form-select-sm w-auto" v-model="perPage" @change="fetchDepartments(1)">
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
              <option value="200">200</option>
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

    <!-- MODAL -->
    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">
              {{ isEdit ? 'Edit Department' : 'Add Department' }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="save">
              <div class="mb-3">
                <label class="form-label">Department Name</label>
                <input v-model="form.name" type="text" class="form-control" required />
              </div>

              <div class="mb-3">
                <label class="form-label">Description (optional)</label>
                <textarea v-model="form.description" class="form-control" rows="3"></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Primary WhatsApp Number</label>
                <select v-model="form.primary_whatsapp_number" class="form-select" :disabled="senders.length === 0">
                  <option value="">-- Use System Default --</option>
                  <option v-for="s in senders" :key="s.number" :value="s.number">
                    {{ s.number }} <span v-if="s.label">({{ s.label }})</span>
                  </option>
                </select>
                <small class="text-muted">The main number used for outbound messaging.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Secondary WhatsApp Numbers</label>
                <div class="input-group">
                  <select v-model="newNumber" class="form-select" :disabled="senders.length === 0">
                    <option value="">-- Select WhatsApp sender --</option>
                    <option v-for="s in senders" :key="s.number" :value="s.number">
                      {{ s.number }} <span v-if="s.label">({{ s.label }})</span>
                    </option>
                  </select>
                  <button class="btn btn-outline-primary" type="button" @click="addNumber" :disabled="!newNumber">
                    + Add WhatsApp Number
                  </button>
                </div>
                <div class="mt-2">
                  <span
                    v-for="num in form.secondary_whatsapp_numbers"
                    :key="num"
                    class="badge bg-secondary me-1"
                  >
                    {{ num }}
                    <i class="bi bi-x ms-1" role="button" @click="removeNumber(num)"></i>
                  </span>
                </div>
              </div>

              <div class="text-end">
                <button class="btn btn-primary">
                  Save
                </button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>

    <ConfirmationModal ref="confirmModal" />
    <DepartmentWhatsappStats ref="statsModal" />
  </div>
</template>

<script>
import axios from '../axios';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import DepartmentWhatsappStats from '../components/DepartmentWhatsappStats.vue';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';

export default {
  name: "DepartmentsView",
  components: {
    ConfirmationModal,
    TableLoadingWrapper,
    DepartmentWhatsappStats,
  },

  data() {
    return {
      departments: [],
      loading: false,
      senders: [],
      newNumber: '',

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
        name: "",
        description: "",
        primary_whatsapp_number: "",
        secondary_whatsapp_numbers: [],
      },
      isEdit: false,
      modal: null,
    };
  },

  mounted() {
    this.modal = createManagedModal(this.$refs.modalRef);
    this.fetchDepartments();
    this.fetchSenders();
  },
  beforeUnmount() {
    disposeManagedModal(this.modal);
  },

  methods: {
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

    fetchDepartments(page = 1) {
      this.loading = true;
      axios.get("/api/departments", { params: { page, per_page: this.perPage } }).then((res) => {
        this.departments = res.data.data;
        this.buildPagination(res.data);
      }).finally(() => {
        this.loading = false;
      });
    },
    fetchSenders() {
      axios.get('/api/whatsapp/senders').then((res) => {
        this.senders = res.data || [];
      }).catch(() => {
        this.senders = [];
      });
    },

    goToPage(page) {
      if (!page || page === this.pagination.currentPage) return;
      this.fetchDepartments(page);
    },

    openCreateModal() {
      this.isEdit = false;
      this.form = { id: null, name: "", description: "", primary_whatsapp_number: "", secondary_whatsapp_numbers: [] };
      this.newNumber = '';
      this.modal.show();
    },

    openEditModal(d) {
      this.isEdit = true;
      this.form = {
        id: d.id,
        name: d.name,
        description: d.description || '',
        primary_whatsapp_number: d.primary_whatsapp_number || "",
        secondary_whatsapp_numbers: d.secondary_whatsapp_numbers ? [...d.secondary_whatsapp_numbers] : [],
      };
      this.newNumber = '';
      this.modal.show();
    },
    addNumber() {
      if (!this.newNumber) return;
      if (!this.form.secondary_whatsapp_numbers.includes(this.newNumber) && this.newNumber !== this.form.primary_whatsapp_number) {
        this.form.secondary_whatsapp_numbers.push(this.newNumber);
      }
      this.newNumber = '';
    },
    removeNumber(num) {
      this.form.secondary_whatsapp_numbers = this.form.secondary_whatsapp_numbers.filter((n) => n !== num);
    },

    save() {
      if (this.isEdit) {
        axios.put(`/api/departments/${this.form.id}`, this.form).then(() => {
          this.modal.hide();
          this.fetchDepartments();
          notify.success('Department updated successfully.', 'Departments');
        });
      } else {
        axios.post("/api/departments", this.form).then(() => {
          this.modal.hide();
          this.fetchDepartments();
          notify.success('Department created successfully.', 'Departments');
        });
      }
    },

    remove(dep) {
      this.$refs.confirmModal.open({
        title: 'Delete Department',
        message: `Delete department "${dep.name}"? This action cannot be undone.`,
        confirmLabel: 'Delete Department',
        confirmVariant: 'danger',
        onConfirm: async () => {
          await axios.delete(`/api/departments/${dep.id}`);
          this.fetchDepartments();
          notify.success(`Department "${dep.name}" deleted.`, 'Departments');
        },
      });
    },
  },
};
</script>
