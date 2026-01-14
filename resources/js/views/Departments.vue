<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-diagram-3 me-2"></i>Departments</h2>
      <button class="btn btn-primary btn-sm" @click="openCreateModal">
        + Add Department
      </button>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>WhatsApp Numbers</th>
              <th style="width: 120px;" class="text-end">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="d in departments" :key="d.id">
              <td>{{ d.name }}</td>
              <td>{{ d.description || '-' }}</td>
              <td>
                <span
                  v-for="num in d.whatsapp_numbers || []"
                  :key="num"
                  class="badge bg-light text-dark border me-1"
                >
                  {{ num }}
                </span>
                <span v-if="!d.whatsapp_numbers || d.whatsapp_numbers.length === 0" class="text-muted">Default</span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary me-1" @click="openEditModal(d)">
                  Edit
                </button>
                <button class="btn btn-sm btn-outline-danger" @click="remove(d)">
                  Delete
                </button>
              </td>
            </tr>

            <tr v-if="departments.length === 0">
              <td colspan="3" class="text-center text-muted py-3">
                No departments found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

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
                <label class="form-label">WhatsApp Numbers</label>
                <div class="input-group">
                  <select v-model="newNumber" class="form-select" :disabled="senders.length === 0">
                    <option value="">-- Select WhatsApp sender --</option>
                    <option v-for="s in senders" :key="s.number" :value="s.number">
                      {{ s.number }} <span v-if="s.label">({{ s.label }})</span>
                    </option>
                  </select>
                  <button class="btn btn-outline-primary" type="button" @click="addNumber" :disabled="!newNumber">
                    Add
                  </button>
                </div>
                <small class="text-muted">Pick one or more Twilio WhatsApp numbers for this department. If none are set, the system default will be used.</small>
                <div class="mt-2">
                  <span
                    v-for="num in form.whatsapp_numbers"
                    :key="num"
                    class="badge bg-secondary me-1"
                  >
                    {{ num }}
                    <i class="bi bi-x ms-1" role="button" @click="removeNumber(num)"></i>
                  </span>
                  <span v-if="form.whatsapp_numbers.length === 0" class="text-muted">Default</span>
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

  </div>
</template>

<script>
import axios from '../axios';
import { Modal } from 'bootstrap';

export default {
  name: "DepartmentsView",

  data() {
    return {
      departments: [],
      senders: [],
      newNumber: '',

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
        whatsapp_numbers: [],
      },
      isEdit: false,
      modal: null,
    };
  },

  mounted() {
    this.modal = new Modal(this.$refs.modalRef);
    this.fetchDepartments();
    this.fetchSenders();
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
      axios.get("/api/departments", { params: { page } }).then((res) => {
        this.departments = res.data.data;
        this.buildPagination(res.data);
      });
    },
    fetchSenders() {
      axios.get('/api/twilio/whatsapp-senders').then((res) => {
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
      this.form = { id: null, name: "", description: "", whatsapp_numbers: [] };
      this.newNumber = '';
      this.modal.show();
    },

    openEditModal(d) {
      this.isEdit = true;
      this.form = {
        id: d.id,
        name: d.name,
        description: d.description || '',
        whatsapp_numbers: d.whatsapp_numbers ? [...d.whatsapp_numbers] : [],
      };
      this.newNumber = '';
      this.modal.show();
    },
    addNumber() {
      if (!this.newNumber) return;
      if (!this.form.whatsapp_numbers.includes(this.newNumber)) {
        this.form.whatsapp_numbers.push(this.newNumber);
      }
      this.newNumber = '';
    },
    removeNumber(num) {
      this.form.whatsapp_numbers = this.form.whatsapp_numbers.filter((n) => n !== num);
    },

    save() {
      if (this.isEdit) {
        axios.put(`/api/departments/${this.form.id}`, this.form).then(() => {
          this.modal.hide();
          this.fetchDepartments();
        });
      } else {
        axios.post("/api/departments", this.form).then(() => {
          this.modal.hide();
          this.fetchDepartments();
        });
      }
    },

    remove(dep) {
      if (!confirm(`Delete department "${dep.name}"?`)) return;

      axios.delete(`/api/departments/${dep.id}`).then(() => {
        this.fetchDepartments();
      });
    },
  },
};
</script>
