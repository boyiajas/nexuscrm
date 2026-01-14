<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-person-gear me-2"></i>Users</h2>
      <button class="btn btn-primary btn-sm" @click="openCreateModal">
        + Add User
      </button>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Department</th>
              <th>Status</th>
              <th style="width: 120px;" class="text-end">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td>{{ u.name }}</td>
              <td>{{ u.email }}</td>
              <td>
                <span class="badge bg-secondary">{{ u.role }}</span>
              </td>
              <td>{{ u.department || '-' }}</td>
              <td>
                <span
                  :class="u.status === 'Active' ? 'badge bg-success' : 'badge bg-danger'"
                >
                  {{ u.status }}
                </span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary me-1" @click="openEditModal(u)">
                  Edit
                </button>

                <button class="btn btn-sm btn-outline-danger" @click="remove(u)">
                  Delete
                </button>
              </td>
            </tr>

            <tr v-if="users.length === 0">
              <td colspan="6" class="text-center text-muted py-3">
                No users found.
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

    <!-- MODAL -->
    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">
              {{ isEdit ? 'Edit User' : 'Add User' }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="save">

              <div class="mb-3">
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-control" required />
              </div>

              <div class="mb-3">
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-control" required />
              </div>

              <!-- Password only required when creating -->
              <div class="mb-3" v-if="!isEdit">
                <label class="form-label">Password</label>
                <input v-model="form.password" type="password" class="form-control" required minlength="6" />
              </div>

              <div class="mb-3">
                <label class="form-label">Role</label>
                <select v-model="form.role" class="form-select" required>
                  <option value="SUPER_ADMIN">SUPER_ADMIN</option>
                  <option value="MANAGER">MANAGER</option>
                  <option value="STAFF">STAFF</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Department</label>
                <select v-model="form.department" class="form-select">
                  <option value="">None</option>
                  <option v-for="d in departments" :key="d.id" :value="d.name">
                    {{ d.name }}
                  </option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-select">
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>

              <div class="text-end">
                <button class="btn btn-primary">Save</button>
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
  name: "UsersView",

  data() {
    return {
      users: [],
      departments: [],
      isEdit: false,
      form: {
        id: null,
        name: "",
        email: "",
        password: "",
        role: "STAFF",
        department: "",
        status: "Active",
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

      modal: null,
    };
  },

  mounted() {
    this.modal = new Modal(this.$refs.modalRef);
    this.fetchUsers();
    this.fetchDepartments();
  },

  methods: {
    // -------------------------
    // Pagination Helpers
    // -------------------------
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
      this.fetchUsers(page);
    },

    // -------------------------
    // Fetch Users + Departments
    // -------------------------
    fetchUsers(page = 1) {
      axios.get("/api/users", { params: { page } }).then((res) => {
        this.users = res.data.data;
        this.buildPagination(res.data);
      });
    },

    fetchDepartments() {
      axios.get("/api/departments", { params: { per_page: 200 } }).then((res) => {
        this.departments = res.data.data || res.data;
      });
    },

    // -------------------------
    // CRUD Operations
    // -------------------------
    openCreateModal() {
      this.isEdit = false;
      this.form = {
        id: null,
        name: "",
        email: "",
        password: "",
        role: "STAFF",
        department: "",
        status: "Active",
      };
      this.modal.show();
    },

    openEditModal(u) {
      this.isEdit = true;
      this.form = { ...u, password: "" };
      this.modal.show();
    },

    save() {
      if (this.isEdit) {
        axios.put(`/api/users/${this.form.id}`, this.form).then(() => {
          this.modal.hide();
          this.fetchUsers();
        });
      } else {
        axios.post("/api/users", this.form).then(() => {
          this.modal.hide();
          this.fetchUsers();
        });
      }
    },

    remove(u) {
      if (!confirm(`Delete user "${u.name}"?`)) return;

      axios.delete(`/api/users/${u.id}`).then(() => {
        this.fetchUsers();
      });
    },
  },
};
</script>
