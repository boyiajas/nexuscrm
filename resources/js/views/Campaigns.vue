<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4" style="background-color:#0087ff0f">
      <h2 class="h4 mb-0"><i class="bi bi-bullseye me-2"></i>Campaigns</h2>
      <button
        v-if="canManage"
        class="btn btn-primary btn-sm"
        @click="openCreateModal"
      >
        + New Campaign
      </button>
    </div>

    <!-- Campaigns table -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Department</th>
              <th>Channels</th>
              <th>Status</th>
              <th>Recipients</th>
              <th>Created</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in campaigns" :key="c.id">
              <td>
                <router-link
                  :to="{ name: 'campaign.show', params: { id: c.id } }"
                  class="fw-semibold text-decoration-none"
                >
                  {{ c.name }}
                </router-link>
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
                <span v-else class="text-muted">
                    All / Global
                </span>
                </td>
              <td>
                <span
                  v-for="ch in c.channels || []"
                  :key="ch"
                  class="badge bg-secondary me-1"
                >
                  {{ ch }}
                </span>
                <span v-if="!c.channels || c.channels.length === 0" class="text-muted">
                  -
                </span>
              </td>
              <td>
                <span class="badge"
                      :class="statusBadgeClass(c.status)">
                  {{ c.status }}
                </span>
              </td>
              <td>{{ c.total_recipients || 0 }}</td>
              <td>{{ c.created_at }}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                  <button
                    class="btn btn-outline-primary"
                    title="Edit"
                    @click="openEditModal(c)"
                    :disabled="!canManage"
                  >
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <button
                    class="btn btn-outline-success"
                    title="Send"
                    @click="sendCampaign(c)"
                    :disabled="!canSend(c)"
                  >
                    <i class="bi bi-send-check"></i>
                  </button>
                  <button
                    class="btn btn-outline-danger"
                    title="Delete"
                    @click="deleteCampaign(c)"
                    :disabled="!canManage"
                  >
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="campaigns.length === 0">
              <td colspan="7" class="text-center py-4 text-muted">
                No campaigns.
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
            <button class="page-link" @click="goToPage(pagination.prevPage)">
              «
            </button>
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
            <button class="page-link" @click="goToPage(pagination.nextPage)">
              »
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Create / Edit Campaign Modal -->
    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">
              {{ isEdit ? 'Edit Campaign' : 'New Campaign' }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="save">

              <div v-if="formErrors.length" class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                  <li v-for="err in formErrors" :key="err">{{ err }}</li>
                </ul>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input
                    v-model="form.name"
                    type="text"
                    class="form-control"
                    required
                  />
                </div>

                <div class="col-md-6">
                    <label class="form-label">Departments</label>
                    <vue-multiselect
                    v-model="selectedDepartments"
                    :options="departments"
                    :multiple="true"
                    :close-on-select="false"
                    :clear-on-select="false"
                    placeholder="Select departments"
                    label="name"
                    track-by="id"
                    >
                    <template slot="noResult">No departments found</template>
                    </vue-multiselect>

                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">
                            Leave empty for <strong>All / Global</strong> departments.
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

                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <select
                    v-model="form.status"
                    class="form-select"
                  >
                    <option value="Draft">Draft</option>
                    <option value="Scheduled">Scheduled</option>
                    <option value="Active">Active</option>
                    <option value="Paused">Paused</option>
                    <option value="Completed">Completed</option>
                  </select>
                </div>

                <div class="col-md-6" v-if="form.channels.includes('WhatsApp')">
                  <label class="form-label">WhatsApp From</label>
                  <select v-model="form.whatsapp_from" class="form-select">
                    <option value="">Default</option>
                    <option
                      v-for="num in availableWhatsappNumbers"
                      :key="num"
                      :value="num"
                    >
                      {{ num }}
                    </option>
                  </select>
                  <small class="text-muted">Defaults to department or system WhatsApp number if not selected.</small>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Scheduled Send At (optional)</label>
                  <input
                    v-model="form.scheduled_at"
                    type="datetime-local"
                    class="form-control"
                  />
                </div>

                <div class="col-12">
                  <label class="form-label d-block">Channels</label>
                  <div class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      id="whatsappCheck"
                      value="WhatsApp"
                      v-model="form.channels"
                    />
                    <label class="form-check-label" for="whatsappCheck">
                      WhatsApp
                    </label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      id="emailCheck"
                      value="Email"
                      v-model="form.channels"
                    />
                    <label class="form-check-label" for="emailCheck">
                      Email
                    </label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      id="smsCheck"
                      value="SMS"
                      v-model="form.channels"
                    />
                    <label class="form-check-label" for="smsCheck">
                      SMS
                    </label>
                  </div>
                </div>

              </div>

              <div class="text-end mt-3">
                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                  Cancel
                </button>
                <button class="btn btn-primary">
                  Save Campaign
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
import VueMultiselect from "vue-multiselect";
import { useToast } from 'vue-toastification';
import 'vue-multiselect/dist/vue-multiselect.min.css'; // Import styles

export default {
  name: 'CampaignsView',
  components: {
    VueMultiselect,
  },
  data() {
    return {
      department_ids: [], 
      campaigns: [],
      departments: [],
      availableWhatsappNumbers: [],
      formErrors: [],
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
      isEdit: false,
      form: this.emptyForm(),
      modal: null,
    };
  },
  computed: {
    canManage() {
      const stored = localStorage.getItem('nexus_user');
      if (!stored) return false;
      try {
        const user = JSON.parse(stored);
        return ['SUPER_ADMIN', 'MANAGER'].includes(user.role);
      } catch {
        return false;
      }
    },

    selectedDepartments: {
        get() {
        if (!Array.isArray(this.form.department_ids)) return [];
        return this.departments.filter(d =>
            this.form.department_ids.includes(d.id)
        );
        },
        set(selected) {
        this.form.department_ids = selected.map(d => d.id);
        this.refreshWhatsappNumbers();
        },
    },
  },
  mounted() {
    this.modal = new Modal(this.$refs.modalRef);
    this.fetchCampaigns();
    this.fetchDepartments();
  },
  methods: {
    emptyForm() {
      return {
        id: null,
        name: '',
        department_ids: [], 
        status: 'Draft',
        scheduled_at: '',
        channels: [],
        whatsapp_from: '',
        whatsapp_template: '',
        track_whatsapp_responses: false,
        enable_live_chat: false,
        email_subject: '',
        email_body: '',
        sms_text: '',
      };
    },
    statusBadgeClass(status) {
      switch (status) {
        case 'Draft':
          return 'bg-secondary';
        case 'Scheduled':
          return 'bg-warning text-dark';
        case 'Active':
          return 'bg-success';
        case 'Paused':
          return 'bg-info';
        case 'Completed':
          return 'bg-dark';
        default:
          return 'bg-light text-dark';
      }
    },
    canSend(c) {
      // Keep simple: only Draft or Scheduled can be sent
      return this.canManage && ['Draft', 'Scheduled', 'Active'].includes(c.status);
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
    goToPage(page) {
      if (!page || page === this.pagination.currentPage) return;
      this.fetchCampaigns(page);
    },
    fetchCampaigns(page = 1) {
      axios.get('/api/campaigns', { params: { page } }).then((res) => {
        this.campaigns = res.data.data || res.data;
        if (res.data.data) {
          this.buildPagination(res.data);
        } else {
          this.pagination = {
            currentPage: 1,
            lastPage: 1,
            total: this.campaigns.length,
            from: 1,
            to: this.campaigns.length,
            prevPage: null,
            nextPage: null,
            pages: [1],
          };
        }
      });
    },
    fetchDepartments() {
      axios.get('/api/departments', { params: { per_page: 200 } }).then((res) => {
        this.departments = res.data.data || res.data;
        this.refreshWhatsappNumbers();
      });
    },
    refreshWhatsappNumbers() {
      const selectedIds = this.form.department_ids || [];
      const nums = [];
      this.departments.forEach((d) => {
        if (selectedIds.length === 0 || selectedIds.includes(d.id)) {
          (d.whatsapp_numbers || []).forEach((n) => {
            if (n && !nums.includes(n)) nums.push(n);
          });
        }
      });
      this.availableWhatsappNumbers = nums;
      // clear selection if not available
      if (this.form.whatsapp_from && !nums.includes(this.form.whatsapp_from)) {
        this.form.whatsapp_from = '';
      }
    },
    openCreateModal() {
      this.isEdit = false;
      this.form = this.emptyForm();
      this.refreshWhatsappNumbers();
      this.formErrors = [];
      this.modal.show();
    },
    openEditModal(c) {
      this.isEdit = true;
      this.formErrors = [];

       // Use many-to-many departments; fallback to legacy single department if present
      const deptIds = Array.isArray(c.departments) && c.departments.length
        ? c.departments.map(d => d.id)
        : (c.department ? [c.department.id] : []);

      this.form = {
        id: c.id,
        name: c.name,
        department_ids: deptIds,
        status: c.status || 'Draft',
        scheduled_at: c.scheduled_at || '',
        channels: c.channels || [],
        whatsapp_from: c.whatsapp_from || '',
        whatsapp_template: c.whatsapp_template || '',
        track_whatsapp_responses: !!c.track_whatsapp_responses,
        enable_live_chat: !!c.enable_live_chat,
        email_subject: c.email_subject || '',
        email_body: c.email_body || '',
        sms_text: c.sms_text || '',
      };
      this.refreshWhatsappNumbers();
      this.modal.show();
    },
    selectAllDepartments() {
      if (!Array.isArray(this.departments)) return;
      this.form.department_ids = this.departments.map(d => d.id);
    },

    clearDepartments() {
      this.form.department_ids = [];
    },
    save() {
      this.formErrors = this.validateForm();
      if (this.formErrors.length) return;

      const payload = { ...this.form };
      const request = this.isEdit
        ? axios.put(`/api/campaigns/${this.form.id}`, payload)
        : axios.post('/api/campaigns', payload);

      request
        .then(() => {
          this.modal.hide();
          this.fetchCampaigns(this.isEdit ? this.pagination.currentPage : 1);
        })
        .catch((err) => {
          const message = err.response?.data?.message || 'Unable to save campaign.';
          this.formErrors = [message];
        });
    },
    validateForm() {
      const errors = [];
      if (!this.form.name || !this.form.name.trim()) {
        errors.push('Name is required.');
      }
      if (!Array.isArray(this.form.channels) || this.form.channels.length === 0) {
        errors.push('Select at least one channel (WhatsApp, Email, or SMS).');
      }
      return errors;
    },
    sendCampaign(c) {
      if (!confirm(`Send campaign "${c.name}" now?`)) return;

      axios.post(`/api/campaigns/${c.id}/send`).then(() => {
        alert('Send job queued. WhatsApp / Email / SMS will be processed in background.');
        this.fetchCampaigns(this.pagination.currentPage);
      });
    },
    deleteCampaign(c) {
      if (!confirm(`Delete campaign "${c.name}"?`)) return;

      axios.delete(`/api/campaigns/${c.id}`).then(() => {
        this.fetchCampaigns(this.pagination.currentPage);
      });
    },
  },
};
</script>
