<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-person-badge me-2"></i>Roles</h2>
      <button class="btn btn-primary btn-sm" @click="openCreateModal">
        + Add Role
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
              placeholder="Role name, code, or description..."
            />
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select v-model="filters.active" class="form-select">
              <option value="">All</option>
              <option value="1">Active</option>
              <option value="0">Inactive</option>
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
        <TableLoadingWrapper :loading="loading" message="Loading roles...">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4">Name</th>
                <th>Code</th>
                <th>WhatsApp Daily Limit</th>
                <th>Watermark</th>
                <th>Status</th>
                <th>Assigned Users</th>
                <th style="width: 120px;" class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="role in roles" :key="role.id">
                <td class="ps-4 py-1">
                  <div class="fw-semibold">{{ role.name }}</div>
                  <small v-if="role.description" class="text-muted">{{ role.description }}</small>
                </td>
                <td><code>{{ role.code }}</code></td>
                <td>{{ formatLimit(role.whatsapp_daily_limit) }}</td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge" :class="role.watermark_enabled ? 'bg-info text-dark' : 'bg-secondary'">
                      {{ role.watermark_enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                    <button
                      class="btn btn-light text-secondary border-0 p-1 px-2"
                      :title="role.watermark_enabled ? 'Disable watermark' : 'Enable watermark'"
                      @click="toggleWatermark(role)"
                    >
                      <i :class="role.watermark_enabled ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                    </button>
                  </div>
                </td>
                <td>
                  <span class="badge" :class="role.is_active ? 'bg-success' : 'bg-secondary'">
                    {{ role.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>{{ role.users_count || 0 }}</td>
                <td class="text-end pe-4">
                  <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-light text-secondary border-0 p-1 px-2" title="Edit" @click="openEditModal(role)">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-light text-danger border-0 p-1 px-2" title="Delete" @click="remove(role)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && roles.length === 0">
                <td colspan="7" class="text-center text-muted py-5">No roles found.</td>
              </tr>
            </tbody>
          </table>
          </div>
        </TableLoadingWrapper>
      </div>

      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <small class="text-muted">
            Showing {{ pagination.from || 0 }}–{{ pagination.to || 0 }} of {{ pagination.total || 0 }}
          </small>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted">Rows:</small>
            <select class="form-select form-select-sm w-auto" v-model="perPage" @change="fetchRoles(1)">
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
            v-for="page in pagination.pages"
            :key="page"
            class="page-item"
            :class="{ active: page === pagination.currentPage }"
          >
            <button class="page-link" @click="goToPage(page)">{{ page }}</button>
          </li>
          <li class="page-item" :class="{ disabled: !pagination.nextPage }">
            <button class="page-link" @click="goToPage(pagination.nextPage)">»</button>
          </li>
        </ul>
      </div>
    </div>

    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock me-2 text-primary"></i>{{ isEdit ? 'Edit Role' : 'Add Role' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" style="max-height: 78vh; overflow-y: auto;">
            <form @submit.prevent="save">
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Role Name <span class="text-danger">*</span></label>
                  <input v-model="form.name" type="text" class="form-control" placeholder="e.g. Call Centre Manager" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Role Code <span class="text-danger">*</span></label>
                  <input v-model="form.code" type="text" class="form-control" placeholder="e.g. CALL_CENTRE_MANAGER" required />
                  <small class="text-muted" style="font-size: 0.75rem;">Stored as uppercase identifier.</small>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea v-model="form.description" class="form-control" rows="2" placeholder="Describe the purpose of this role..."></textarea>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">WhatsApp Daily Limit</label>
                  <input v-model.number="form.whatsapp_daily_limit" type="number" min="1" class="form-control" />
                  <small class="text-muted" style="font-size: 0.75rem;">Max daily outbound WhatsApp limit.</small>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Watermark</label>
                  <select v-model="form.watermark_enabled" class="form-select">
                    <option :value="true">Enabled</option>
                    <option :value="false">Disabled</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Status</label>
                  <select v-model="form.is_active" class="form-select">
                    <option :value="true">Active</option>
                    <option :value="false">Inactive</option>
                  </select>
                </div>
              </div>

              <!-- SYSTEM PERMISSIONS CHECKBOX MATRIX -->
              <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-key me-1 text-primary"></i> Access Levels & Permissions</h6>
                    <small class="text-muted" style="font-size: 0.78rem;">Check the capabilities enabled for users assigned to this role.</small>
                  </div>
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2" style="font-size: 0.75rem;" @click="selectAllPermissions">Select All</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size: 0.75rem;" @click="deselectAllPermissions">Deselect All</button>
                  </div>
                </div>

                <!-- Module Permission Cards -->
                <div class="d-flex flex-column gap-3">
                  <div
                    v-for="(perms, moduleName) in groupedPermissions"
                    :key="moduleName"
                    class="card border shadow-none bg-light"
                  >
                    <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
                      <div class="fw-bold text-dark small">
                        <i class="bi bi-folder2-open me-1 text-secondary"></i> {{ moduleName }}
                      </div>
                      <div class="form-check form-check-inline mb-0">
                        <input
                          class="form-check-input"
                          type="checkbox"
                          :id="'mod-' + moduleName"
                          :checked="isModuleFullySelected(moduleName)"
                          :indeterminate.prop="isModulePartiallySelected(moduleName)"
                          @change="toggleSelectAllModule(moduleName)"
                        />
                        <label class="form-check-label small text-muted" :for="'mod-' + moduleName" style="cursor: pointer; font-size: 0.78rem;">
                          Select Module
                        </label>
                      </div>
                    </div>
                    <div class="card-body p-3">
                      <div class="row g-2">
                        <div
                          v-for="perm in perms"
                          :key="perm.code"
                          class="col-md-6"
                        >
                          <div class="p-2 border rounded bg-white h-100 d-flex align-items-start gap-2">
                            <input
                              class="form-check-input mt-1 flex-shrink-0"
                              type="checkbox"
                              :id="'perm-' + perm.code"
                              :value="perm.code"
                              v-model="selectedPermissions"
                            />
                            <div>
                              <label :for="'perm-' + perm.code" class="fw-semibold text-dark mb-0 small" style="cursor: pointer; display: block;">
                                {{ perm.name }}
                              </label>
                              <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.2;">
                                {{ perm.description }}
                              </small>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="text-end mt-4 pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary me-2 px-3" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary px-4 fw-bold">Save Role & Permissions</button>
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
import axios, { syncAuthenticatedUser } from '../axios';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';

export default {
  name: 'RolesView',
  components: {
    ConfirmationModal,
    TableLoadingWrapper,
  },
  data() {
    return {
      roles: [],
      allPermissions: [],
      selectedPermissions: [],
      defaultCheckedCodes: [
        'view_clients',
        'create_clients',
        'edit_clients',
        'view_campaigns',
        'create_campaigns',
        'send_whatsapp',
        'view_live_chat',
        'request_exports',
      ],
      loading: false,
      filters: {
        search: '',
        active: '',
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
        code: '',
        name: '',
        description: '',
        whatsapp_daily_limit: 500,
        watermark_enabled: true,
        is_active: true,
      },
      isEdit: false,
      modal: null,
    };
  },
  computed: {
    groupedPermissions() {
      const groups = {};
      this.allPermissions.forEach((perm) => {
        const mod = perm.module || 'General';
        if (!groups[mod]) {
          groups[mod] = [];
        }
        groups[mod].push(perm);
      });
      return groups;
    },
  },
  mounted() {
    this.modal = createManagedModal(this.$refs.modalRef);
    this.fetchPermissions();
    this.fetchRoles();
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
      for (let page = 1; page <= data.last_page; page += 1) {
        pages.push(page);
      }
      this.pagination.pages = pages;
    },
    formatLimit(value) {
      return value ? Number(value).toLocaleString() : '-';
    },
    fetchRoles(page = 1) {
      this.loading = true;
      axios.get('/api/roles', {
        params: {
          page,
          per_page: this.perPage,
          search: this.filters.search || undefined,
          active_only: this.filters.active === '1' ? 1 : undefined,
        },
      }).then((res) => {
        this.roles = res.data.data || [];
        this.buildPagination(res.data);
      }).catch((error) => {
        notify.error(error.response?.data?.message || 'Failed to load roles.', 'Roles');
      }).finally(() => {
        this.loading = false;
      });
    },
    goToPage(page) {
      if (!page || page === this.pagination.currentPage) return;
      this.fetchRoles(page);
    },
    applyFilters() {
      this.fetchRoles(1);
    },
    resetFilters() {
      this.filters = {
        search: '',
        active: '',
      };
      this.fetchRoles(1);
    },
    fetchPermissions() {
      axios.get('/api/permissions')
        .then((res) => {
          this.allPermissions = res.data || [];
        })
        .catch((error) => {
          console.error('Failed to fetch permissions:', error);
        });
    },
    openCreateModal() {
      this.isEdit = false;
      this.selectedPermissions = [...this.defaultCheckedCodes];
      this.form = {
        id: null,
        code: '',
        name: '',
        description: '',
        whatsapp_daily_limit: 500,
        watermark_enabled: true,
        is_active: true,
      };
      this.modal.show();
    },
    openEditModal(role) {
      this.isEdit = true;
      this.selectedPermissions = Array.isArray(role.permissions) ? role.permissions.map(p => p.code) : [];
      this.form = {
        id: role.id,
        code: role.code || '',
        name: role.name || '',
        description: role.description || '',
        whatsapp_daily_limit: role.whatsapp_daily_limit || 500,
        watermark_enabled: role.watermark_enabled !== false,
        is_active: role.is_active !== false,
      };
      this.modal.show();
    },
    selectAllPermissions() {
      this.selectedPermissions = this.allPermissions.map(p => p.code);
    },
    deselectAllPermissions() {
      this.selectedPermissions = [];
    },
    isModuleFullySelected(moduleName) {
      const perms = this.groupedPermissions[moduleName] || [];
      if (!perms.length) return false;
      return perms.every(p => this.selectedPermissions.includes(p.code));
    },
    isModulePartiallySelected(moduleName) {
      const perms = this.groupedPermissions[moduleName] || [];
      if (!perms.length) return false;
      const count = perms.filter(p => this.selectedPermissions.includes(p.code)).length;
      return count > 0 && count < perms.length;
    },
    toggleSelectAllModule(moduleName) {
      const perms = this.groupedPermissions[moduleName] || [];
      const codes = perms.map(p => p.code);
      const isFully = this.isModuleFullySelected(moduleName);

      if (isFully) {
        this.selectedPermissions = this.selectedPermissions.filter(c => !codes.includes(c));
      } else {
        const set = new Set([...this.selectedPermissions, ...codes]);
        this.selectedPermissions = Array.from(set);
      }
    },
    save() {
      const payload = {
        code: this.form.code,
        name: this.form.name,
        description: this.form.description || null,
        whatsapp_daily_limit: this.form.whatsapp_daily_limit || 500,
        watermark_enabled: !!this.form.watermark_enabled,
        is_active: !!this.form.is_active,
        permissions: this.selectedPermissions,
      };

      const request = this.isEdit
        ? axios.put(`/api/roles/${this.form.id}`, payload)
        : axios.post('/api/roles', payload);

      request.then(() => {
        this.modal.hide();
        this.fetchRoles(this.isEdit ? this.pagination.currentPage : 1);
        syncAuthenticatedUser().catch(() => {});
        notify.success(`Role ${this.isEdit ? 'updated' : 'created'} successfully.`, 'Roles');
      }).catch((error) => {
        notify.error(error.response?.data?.message || `Failed to ${this.isEdit ? 'update' : 'create'} role.`, 'Roles');
      });
    },
    remove(role) {
      this.$refs.confirmModal.open({
        title: 'Delete Role',
        message: `Delete role "${role.name}"? This action cannot be undone if the role is unused.`,
        confirmLabel: 'Delete Role',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            await axios.delete(`/api/roles/${role.id}`);
            this.fetchRoles(this.pagination.currentPage);
            notify.success(`Role "${role.name}" deleted.`, 'Roles');
          } catch (error) {
            notify.error(error.response?.data?.message || 'Failed to delete role.', 'Roles');
            throw error;
          }
        },
      });
    },
    toggleWatermark(role) {
      axios.patch(`/api/roles/${role.id}/watermark`, {
        watermark_enabled: !role.watermark_enabled,
      }).then((res) => {
        const updatedRole = res.data;
        const index = this.roles.findIndex((item) => item.id === updatedRole.id);
        if (index !== -1) {
          this.roles.splice(index, 1, updatedRole);
        }
        syncAuthenticatedUser().catch(() => {});
        notify.success(
          `Watermark ${updatedRole.watermark_enabled ? 'enabled' : 'disabled'} for role "${updatedRole.name}".`,
          'Roles'
        );
      }).catch((error) => {
        notify.error(error.response?.data?.message || 'Failed to update role watermark.', 'Roles');
      });
    },
  },
};
</script>
