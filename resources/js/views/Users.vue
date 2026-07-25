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
        <TableLoadingWrapper :loading="loading" message="Loading users...">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Bank</th>
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
                  <div class="d-flex flex-wrap gap-1">
                    <span
                      v-for="code in roleCodesForUser(u)"
                      :key="`${u.id}-${code}`"
                      class="badge bg-secondary"
                    >
                      {{ roleNameForUser(u, code) }}
                    </span>
                  </div>
                </td>
                <td>{{ u.bank?.name || '-' }}</td>
                <td>{{ u.department || '-' }}</td>
                <td>
                  <span
                    :class="u.status === 'Active' ? 'badge bg-success' : 'badge bg-danger'"
                  >
                    {{ u.status }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary" title="View" @click="openProfileModal(u)">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-primary" title="Edit" @click="openEditModal(u)">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-outline-danger" title="Delete" @click="remove(u)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="!loading && users.length === 0">
                <td colspan="7" class="text-center text-muted py-3">
                  No users found.
                </td>
              </tr>
            </tbody>
          </table>
        </TableLoadingWrapper>
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

    <!-- Profile Modal -->
    <div class="modal fade" tabindex="-1" ref="profileModalRef">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-person-badge me-1"></i>
              User Profile — {{ profile.name || 'User' }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-lg-6">
                <div class="card h-100">
                  <div class="card-body">
                    <h6 class="mb-3">Contact Information</h6>
                    <div class="d-flex align-items-center gap-3 mb-3">
                      <div class="avatar-placeholder text-center border rounded p-3 text-muted small">
                        <i class="bi bi-person fs-2 d-block"></i>
                        Profile photo
                      </div>
                      <div>
                        <div class="fw-semibold">{{ profile.name || 'Name' }}</div>
                        <div class="text-muted small">{{ profile.email }}</div>
                        <div class="text-muted small">{{ profile.username || 'Username' }}</div>
                      </div>
                    </div>
                    <div class="row g-2 mb-2">
                      <div class="col-md-5">
                        <label class="form-label small text-muted">First Name</label>
                        <div class="fw-semibold">{{ profile.first_name || '-' }}</div>
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small text-muted">M.I.</label>
                        <div class="fw-semibold">{{ profile.middle_initial || '-' }}</div>
                      </div>
                      <div class="col-md-5">
                        <label class="form-label small text-muted">Last Name</label>
                        <div class="fw-semibold">{{ profile.last_name || '-' }}</div>
                      </div>
                    </div>
                    <div class="row g-3">
                      <div class="col-md-12">
                        <label class="form-label small text-muted">Email</label>
                        <div class="fw-semibold">{{ profile.email || '-' }}</div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small text-muted">Primary Phone</label>
                        <div class="fw-semibold">{{ profile.primary_phone || '-' }}</div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small text-muted">Secondary Phone</label>
                        <div class="fw-semibold">{{ profile.secondary_phone || '-' }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-6">
                <div class="card h-100">
                  <div class="card-body">
                    <h6 class="mb-3">Working Information</h6>
                    <div class="row g-3">
                      <div class="col-md-12">
                        <label class="form-label small text-muted">Bank</label>
                        <div class="fw-semibold">{{ profile.bank_name || '-' }}</div>
                      </div>
                      <div class="col-md-12">
                        <label class="form-label small text-muted">Department</label>
                        <div class="fw-semibold">{{ profile.department || '-' }}</div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small text-muted">Roles</label>
                        <div class="d-flex flex-wrap gap-1">
                          <span
                            v-for="code in profile.role_codes"
                            :key="`profile-role-${code}`"
                            class="badge bg-secondary"
                          >
                            {{ roleLabel(code) }}
                          </span>
                          <span v-if="!profile.role_codes.length" class="fw-semibold">-</span>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small text-muted">Inactivity Timeout</label>
                        <div class="fw-semibold">{{ profile.inactivity_timeout ? profile.inactivity_timeout + ' minutes' : '-' }}</div>
                        <small class="text-muted">HIPAA recommends 10 min timeout.</small>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small text-muted">Is Provider</label>
                        <div class="fw-semibold">{{ profile.is_provider ? 'Yes' : 'No' }}</div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small text-muted">Time clock user</label>
                        <div class="fw-semibold">{{ profile.is_time_clock_user ? 'Yes' : 'No' }}</div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small text-muted">Status</label>
                        <div class="fw-semibold">{{ profile.status || 'Active' }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button v-if="!isEdit" class="btn btn-primary" @click="openEditModal(profile)">Edit</button>
            <button v-else class="btn btn-primary" @click="openEditModal(profile)">Edit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL -->
    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog modal-xl">
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

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label">First Name</label>
                  <input v-model="form.first_name" type="text" class="form-control" />
                </div>
                <div class="col-md-2">
                  <label class="form-label">M.I.</label>
                  <input v-model="form.middle_initial" type="text" class="form-control" maxlength="1" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last Name</label>
                  <input v-model="form.last_name" type="text" class="form-control" />
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-control" required />
              </div>

              <!-- Password only required when creating -->
              <div class="mb-3" v-if="!isEdit">
                <label class="form-label">Password</label>
                <input v-model="form.password" type="password" class="form-control" required minlength="12" />
                <small class="text-muted">At least 12 characters with upper/lowercase letters, a number, and a symbol.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Username</label>
                <input v-model="form.username" type="text" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label">Roles</label>
                <vue-multiselect
                  v-model="selectedRoles"
                  :options="roles"
                  :multiple="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  :searchable="true"
                  :allow-empty="false"
                  placeholder="Select one or more roles"
                  label="name"
                  track-by="id"
                >
                  <template #option="{ option }">
                    <div>
                      <strong>{{ option.name }}</strong>
                      <div class="small text-muted">
                        {{ option.code }} · WhatsApp limit {{ formatRoleLimit(option.whatsapp_daily_limit) }}
                      </div>
                    </div>
                  </template>
                  <template #tag="{ option, remove }">
                    <span class="multiselect__tag">
                      <span>{{ option.name }}</span>
                      <i class="multiselect__tag-icon" @click="remove(option)"></i>
                    </span>
                  </template>
                </vue-multiselect>
                <small class="text-muted d-block mt-1">
                  The first applicable primary role is derived automatically for legacy access checks.
                </small>
              </div>

              <div class="mb-3">
                <label class="form-label">Bank</label>
                <select v-model="form.bank_id" class="form-select" :disabled="!canChooseBankForForm">
                  <option value="">None</option>
                  <option v-for="bank in banks" :key="bank.id" :value="bank.id">
                    {{ bank.name }}
                  </option>
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

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Primary Phone</label>
                  <input v-model="form.primary_phone" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Secondary Phone</label>
                  <input v-model="form.secondary_phone" type="text" class="form-control" />
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Inactivity Timeout (minutes)</label>
                  <input v-model="form.inactivity_timeout" type="number" min="1" class="form-control" />
                </div>
                <div class="col-md-3 d-flex align-items-center">
                  <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" v-model="form.is_provider" />
                    <label class="form-check-label">Is Provider</label>
                  </div>
                </div>
                <div class="col-md-3 d-flex align-items-center">
                  <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" v-model="form.is_time_clock_user" />
                    <label class="form-check-label">Time clock user</label>
                  </div>
                </div>
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
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: "UsersView",
  components: {
    VueMultiselect,
    ConfirmationModal,
    TableLoadingWrapper,
  },

  data() {
    return {
      users: [],
      loading: false,
      departments: [],
      banks: [],
      roles: [],
      selectedRoles: [],
      isEdit: false,
      rolePriority: [
        'SUPER_ADMIN',
        'ADMIN',
        'MANAGER',
        'CALL_CENTRE_MANAGER',
        'TEAM_LEADER',
        'AGENT',
        'STAFF',
        'AUDITOR',
        'COMPLIANCE_OFFICER',
        'READ_ONLY_REVIEWER',
      ],
      form: {
        id: null,
        name: "",
        email: "",
        password: "",
        role: "AGENT",
        role_ids: [],
        bank_id: "",
        department: "",
        status: "Active",
        first_name: "",
        middle_initial: "",
        last_name: "",
        username: "",
        primary_phone: "",
        secondary_phone: "",
        inactivity_timeout: "",
        is_provider: false,
        is_time_clock_user: false,
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
      profileModal: null,
      profile: {
        name: "",
        email: "",
        username: "",
        first_name: "",
        middle_initial: "",
        last_name: "",
        primary_phone: "",
        secondary_phone: "",
        bank_name: "",
        department: "",
        role_codes: [],
        inactivity_timeout: "",
        is_provider: false,
        is_time_clock_user: false,
        status: "",
      },
    };
  },

  mounted() {
    this.modal = createManagedModal(this.$refs.modalRef);
    this.profileModal = createManagedModal(this.$refs.profileModalRef);
    this.fetchUsers();
    this.fetchDepartments();
    this.fetchBanks();
    this.fetchRoles();
  },
  beforeUnmount() {
    disposeManagedModal(this.modal);
    disposeManagedModal(this.profileModal);
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
    currentUserRoleCodes() {
      if (Array.isArray(this.currentUser?.role_codes) && this.currentUser.role_codes.length) {
        return this.currentUser.role_codes;
      }

      if (this.currentUser?.role) {
        return [this.currentUser.role];
      }

      return [];
    },
    canChooseBankForForm() {
      return this.currentUserRoleCodes.some((role) => ['SUPER_ADMIN', 'ADMIN'].includes(role));
    },
    primarySelectedRoleCode() {
      return this.resolvePrimaryRoleCode(
        this.selectedRoles.map((role) => role.code)
      );
    },
  },

  watch: {
    selectedRoles: {
      handler(newValue) {
        this.form.role_ids = Array.isArray(newValue) ? newValue.map((role) => role.id) : [];
        this.form.role = this.resolvePrimaryRoleCode(
          Array.isArray(newValue) ? newValue.map((role) => role.code) : []
        );
      },
      deep: true,
    },
  },

  methods: {
    roleLabel(code) {
      const role = this.roles.find((item) => item.code === code);
      if (role?.name) return role.name;
      return String(code || '').replace(/_/g, ' ');
    },
    formatRoleLimit(value) {
      return value ? Number(value).toLocaleString() : '-';
    },
    roleCodesForUser(user) {
      if (Array.isArray(user?.role_codes) && user.role_codes.length) {
        return user.role_codes;
      }
      return user?.role ? [user.role] : [];
    },
    roleNameForUser(user, code) {
      if (Array.isArray(user?.roles) && user.roles.length) {
        const role = user.roles.find((item) => item.code === code);
        if (role?.name) return role.name;
      }

      if (Array.isArray(user?.role_names) && user.role_names.length) {
        const codes = this.roleCodesForUser(user);
        const index = codes.indexOf(code);
        if (index > -1 && user.role_names[index]) {
          return user.role_names[index];
        }
      }

      return this.roleLabel(code);
    },
    resolvePrimaryRoleCode(roleCodes) {
      const uniqueCodes = [...new Set((roleCodes || []).filter(Boolean))];

      for (const preferred of this.rolePriority) {
        if (uniqueCodes.includes(preferred)) {
          return preferred;
        }
      }

      return uniqueCodes[0] || 'AGENT';
    },
    syncSelectedRolesFromUser(user = null) {
      const codes = this.roleCodesForUser(user);
      this.selectedRoles = this.roles.filter((role) => codes.includes(role.code));
      this.form.role_ids = this.selectedRoles.map((role) => role.id);
      this.form.role = this.resolvePrimaryRoleCode(codes);
    },
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
      this.loading = true;
      axios.get("/api/users", { params: { page } }).then((res) => {
        this.users = res.data.data;
        this.buildPagination(res.data);
      }).finally(() => {
        this.loading = false;
      });
    },

    fetchDepartments() {
      axios.get("/api/departments", { params: { per_page: 200 } }).then((res) => {
        this.departments = res.data.data || res.data;
      });
    },
    fetchBanks() {
      axios.get("/api/banks", { params: { per_page: 200 } }).then((res) => {
        this.banks = res.data.data || res.data;
      });
    },
    fetchRoles() {
      axios.get('/api/roles', { params: { all: 1, active_only: 1 } }).then((res) => {
        this.roles = res.data || [];
        if (this.selectedRoles.length || this.form.role_ids?.length || this.form.role) {
          this.syncSelectedRolesFromUser({
            role_codes: this.selectedRoles.length
              ? this.selectedRoles.map((role) => role.code)
              : (this.form.role_ids?.length
                ? this.roles.filter((role) => this.form.role_ids.includes(role.id)).map((role) => role.code)
                : [this.form.role]),
          });
        }
      }).catch(() => {
        this.roles = [];
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
        role: "AGENT",
        role_ids: [],
        bank_id: "",
        department: "",
        status: "Active",
        first_name: "",
        middle_initial: "",
        last_name: "",
        username: "",
        primary_phone: "",
        secondary_phone: "",
        inactivity_timeout: "",
        is_provider: false,
        is_time_clock_user: false,
      };
      this.selectedRoles = this.roles.filter((role) => role.code === 'AGENT');
      this.modal.show();
    },

    openEditModal(u) {
      this.isEdit = true;
      this.form = {
        id: u.id,
        name: u.name || "",
        email: u.email || "",
        password: "",
        role: u.role || "AGENT",
        role_ids: [],
        bank_id: u.bank_id || "",
        department: u.department || "",
        status: u.status || "Active",
        first_name: u.first_name || "",
        middle_initial: u.middle_initial || "",
        last_name: u.last_name || "",
        username: u.username || "",
        primary_phone: u.primary_phone || "",
        secondary_phone: u.secondary_phone || "",
        inactivity_timeout: u.inactivity_timeout || "",
        is_provider: !!u.is_provider,
        is_time_clock_user: !!u.is_time_clock_user,
      };
      this.syncSelectedRolesFromUser(u);
      this.modal.show();
    },

    save() {
      if (!this.selectedRoles.length) {
        notify.warning('Please assign at least one role to this user.', 'Users');
        return;
      }

      if (!['SUPER_ADMIN', 'ADMIN'].includes(this.primarySelectedRoleCode) && !this.form.bank_id) {
        notify.warning('Please assign a bank to this user.', 'Users');
        return;
      }

      const payload = {
        ...this.form,
        role: this.primarySelectedRoleCode,
        role_ids: this.selectedRoles.map((role) => role.id),
      };

      if (this.isEdit) {
        axios.put(`/api/users/${this.form.id}`, payload).then(() => {
          this.modal.hide();
          this.fetchUsers();
          notify.success('User updated successfully.', 'Users');
        }).catch((error) => {
          notify.error(error.response?.data?.message || 'Failed to update user.', 'Users');
        });
      } else {
        axios.post("/api/users", payload).then(() => {
          this.modal.hide();
          this.fetchUsers();
          notify.success('User created successfully.', 'Users');
        }).catch((error) => {
          notify.error(error.response?.data?.message || 'Failed to create user.', 'Users');
        });
      }
    },

    remove(u) {
      this.$refs.confirmModal.open({
        title: 'Delete User',
        message: `Delete user "${u.name}"? This will remove their access to the CRM.`,
        confirmLabel: 'Delete User',
        confirmVariant: 'danger',
        onConfirm: async () => {
          await axios.delete(`/api/users/${u.id}`);
          this.fetchUsers();
          notify.success(`User "${u.name}" deleted.`, 'Users');
        },
      });
    },
    openProfileModal(user) {
      this.profile = {
        name: user.name,
        email: user.email,
        username: user.username || "",
        first_name: user.first_name || "",
        middle_initial: user.middle_initial || "",
        last_name: user.last_name || "",
        primary_phone: user.primary_phone || "",
        secondary_phone: user.secondary_phone || "",
        department: user.department || "",
        bank_name: user.bank?.name || "",
        role_codes: this.roleCodesForUser(user),
        inactivity_timeout: user.inactivity_timeout || "",
        is_provider: !!user.is_provider,
        is_time_clock_user: !!user.is_time_clock_user,
        status: user.status || "Active",
      };
      if (!this.profileModal) {
        this.profileModal = createManagedModal(this.$refs.profileModalRef);
      }
      this.profileModal.show();
    },
  },
};
</script>
