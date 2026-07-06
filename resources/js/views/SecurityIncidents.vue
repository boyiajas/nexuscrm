<template>
  <div>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-2">
      <div>
        <h2 class="h4 mb-0"><i class="bi bi-shield-exclamation me-2"></i>Security Incidents</h2>
        <small class="text-muted">Track security, privacy, export, malware, and breach-related incidents inside the CRM.</small>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" @click="fetchIncidents">
          <i class="bi bi-arrow-repeat me-1"></i> Refresh
        </button>
        <button v-if="canCreate" class="btn btn-primary btn-sm" @click="openCreateModal">
          <i class="bi bi-plus-circle me-1"></i> Report Incident
        </button>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="fetchIncidents(1)">
          <div class="col-md-3">
            <label class="form-label">Search</label>
            <input v-model.trim="filters.q" type="text" class="form-control" placeholder="Reference, title, module..." />
          </div>
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select v-model="filters.status" class="form-select">
              <option value="all">All</option>
              <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Type</label>
            <select v-model="filters.type" class="form-select">
              <option value="all">All</option>
              <option v-for="option in typeOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Severity</label>
            <select v-model="filters.severity" class="form-select">
              <option value="all">All</option>
              <option v-for="option in severityOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div v-if="canAccessAllBanks" class="col-md-2">
            <label class="form-label">Bank</label>
            <select v-model="filters.bank_id" class="form-select">
              <option value="">All banks</option>
              <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
            </select>
          </div>
          <div class="col-md-1 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Bank</th>
              <th>Type</th>
              <th>Severity</th>
              <th>Status</th>
              <th>Assigned</th>
              <th>Created</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="incident in incidents" :key="incident.id">
              <td>
                <div class="fw-semibold">{{ incident.reference }}</div>
                <small class="text-muted">{{ incident.title }}</small>
              </td>
              <td>{{ incident.bank?.name || 'Global / Shared' }}</td>
              <td><span class="text-capitalize">{{ humanize(incident.type) }}</span></td>
              <td><span class="badge" :class="severityBadge(incident.severity)">{{ incident.severity }}</span></td>
              <td><span class="badge" :class="statusBadge(incident.status)">{{ incident.status }}</span></td>
              <td>{{ incident.assignee?.name || 'Unassigned' }}</td>
              <td>{{ formatDateTime(incident.created_at) }}</td>
              <td class="text-end">
                <button class="btn btn-outline-secondary btn-sm" @click="openDetail(incident)">
                  <i class="bi bi-eye"></i>
                </button>
              </td>
            </tr>
            <tr v-if="!incidents.length">
              <td colspan="8" class="text-center text-muted py-4">No security incidents found.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing {{ pagination.from || 0 }}-{{ pagination.to || 0 }} of {{ pagination.total || 0 }}</small>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="!pagination.prevPage" @click="fetchIncidents(pagination.prevPage)">Prev</button>
          <button class="btn btn-outline-secondary" :disabled="!pagination.nextPage" @click="fetchIncidents(pagination.nextPage)">Next</button>
        </div>
      </div>
    </div>

    <div class="modal fade" tabindex="-1" ref="createModalRef">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Report Security Incident</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form @submit.prevent="createIncident">
            <div class="modal-body">
              <div class="row g-3">
                <div v-if="canAccessAllBanks" class="col-md-6">
                  <label class="form-label">Bank</label>
                  <select v-model="createForm.bank_id" class="form-select">
                    <option value="">Global / Shared</option>
                    <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Type</label>
                  <select v-model="createForm.type" class="form-select" required>
                    <option v-for="option in typeOptions" :key="option" :value="option">{{ humanize(option) }}</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Severity</label>
                  <select v-model="createForm.severity" class="form-select" required>
                    <option v-for="option in severityOptions" :key="option" :value="option">{{ option }}</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Affected Module</label>
                  <input v-model.trim="createForm.affected_module" type="text" class="form-control" placeholder="Clients / Campaigns / WhatsApp" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Affected Records</label>
                  <input v-model="createForm.affected_records_count" type="number" min="0" class="form-control" />
                </div>
                <div class="col-md-8">
                  <label class="form-label">Title</label>
                  <input v-model.trim="createForm.title" type="text" class="form-control" required maxlength="255" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Assign To</label>
                  <select v-model="createForm.assigned_to_user_id" class="form-select">
                    <option value="">Unassigned</option>
                    <option v-for="assignee in assignableUsers" :key="assignee.id" :value="assignee.id">{{ assignee.name }} ({{ assignee.role }})</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Description</label>
                  <textarea v-model.trim="createForm.description" class="form-control" rows="5" required maxlength="10000"></textarea>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input v-model="createForm.suspected_personal_data_exposed" class="form-check-input" type="checkbox" id="suspectedDataExposed" />
                    <label class="form-check-label" for="suspectedDataExposed">Personal data may be exposed</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input v-model="createForm.regulator_notification_required" class="form-check-input" type="checkbox" id="regulatorNotificationRequired" />
                    <label class="form-check-label" for="regulatorNotificationRequired">Regulator notification required</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input v-model="createForm.bank_notification_required" class="form-check-input" type="checkbox" id="bankNotificationRequired" />
                    <label class="form-check-label" for="bankNotificationRequired">Bank notification required</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="savingCreate">
                <span v-if="savingCreate" class="spinner-border spinner-border-sm me-1"></span>
                Save Incident
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" tabindex="-1" ref="detailModalRef">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" v-if="selectedIncident">
          <div class="modal-header">
            <h5 class="modal-title">{{ selectedIncident.reference }} • {{ selectedIncident.title }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-3">
                <label class="form-label text-muted small text-uppercase">Bank</label>
                <div>{{ selectedIncident.bank?.name || 'Global / Shared' }}</div>
              </div>
              <div class="col-md-3">
                <label class="form-label text-muted small text-uppercase">Type</label>
                <div>{{ humanize(selectedIncident.type) }}</div>
              </div>
              <div class="col-md-3">
                <label class="form-label text-muted small text-uppercase">Severity</label>
                <div><span class="badge" :class="severityBadge(selectedIncident.severity)">{{ selectedIncident.severity }}</span></div>
              </div>
              <div class="col-md-3">
                <label class="form-label text-muted small text-uppercase">Status</label>
                <div><span class="badge" :class="statusBadge(selectedIncident.status)">{{ selectedIncident.status }}</span></div>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small text-uppercase">Reported By</label>
                <div>{{ selectedIncident.reporter?.name || 'Unknown' }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small text-uppercase">Assigned To</label>
                <div>{{ selectedIncident.assignee?.name || 'Unassigned' }}</div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label text-muted small text-uppercase">Description</label>
              <div class="border rounded p-3 bg-light">{{ selectedIncident.description }}</div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Affected Module</div>
                <div class="fw-semibold">{{ selectedIncident.affected_module || '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Affected Records</div>
                <div class="fw-semibold">{{ selectedIncident.affected_records_count ?? '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Contained At</div>
                <div class="fw-semibold">{{ formatDateTime(selectedIncident.contained_at) || '-' }}</div>
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <span class="badge" :class="selectedIncident.suspected_personal_data_exposed ? 'bg-danger' : 'bg-secondary'">
                  {{ selectedIncident.suspected_personal_data_exposed ? 'Personal Data Exposure Suspected' : 'No Exposure Flag' }}
                </span>
              </div>
              <div class="col-md-4">
                <span class="badge" :class="selectedIncident.regulator_notification_required ? 'bg-warning text-dark' : 'bg-secondary'">
                  {{ selectedIncident.regulator_notification_required ? 'Regulator Notice Required' : 'No Regulator Notice Flag' }}
                </span>
              </div>
              <div class="col-md-4">
                <span class="badge" :class="selectedIncident.bank_notification_required ? 'bg-warning text-dark' : 'bg-secondary'">
                  {{ selectedIncident.bank_notification_required ? 'Bank Notice Required' : 'No Bank Notice Flag' }}
                </span>
              </div>
            </div>

            <div v-if="canManage" class="card shadow-sm mb-4">
              <div class="card-body">
                <h6 class="mb-3">Update Incident</h6>
                <div class="row g-3">
                  <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select v-model="updateForm.status" class="form-select">
                      <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Severity</label>
                    <select v-model="updateForm.severity" class="form-select">
                      <option v-for="option in severityOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Assign To</label>
                    <select v-model="updateForm.assigned_to_user_id" class="form-select">
                      <option value="">Unassigned</option>
                      <option v-for="assignee in assignableUsers" :key="assignee.id" :value="assignee.id">{{ assignee.name }} ({{ assignee.role }})</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Affected Records</label>
                    <input v-model="updateForm.affected_records_count" type="number" min="0" class="form-control" />
                  </div>
                  <div class="col-md-4">
                    <div class="form-check">
                      <input v-model="updateForm.suspected_personal_data_exposed" class="form-check-input" type="checkbox" id="detailDataExposed" />
                      <label class="form-check-label" for="detailDataExposed">Personal data may be exposed</label>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-check">
                      <input v-model="updateForm.regulator_notification_required" class="form-check-input" type="checkbox" id="detailRegulatorNotification" />
                      <label class="form-check-label" for="detailRegulatorNotification">Regulator notification required</label>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-check">
                      <input v-model="updateForm.bank_notification_required" class="form-check-input" type="checkbox" id="detailBankNotification" />
                      <label class="form-check-label" for="detailBankNotification">Bank notification required</label>
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Update Note</label>
                    <textarea v-model.trim="updateForm.note" class="form-control" rows="3" placeholder="Add context for this update..."></textarea>
                  </div>
                  <div class="col-12 text-end">
                    <button class="btn btn-primary btn-sm" @click="saveIncidentUpdate" :disabled="savingUpdate">
                      <span v-if="savingUpdate" class="spinner-border spinner-border-sm me-1"></span>
                      Save Update
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="canManage" class="card shadow-sm mb-4">
              <div class="card-body">
                <h6 class="mb-3">Add Timeline Event</h6>
                <div class="row g-3 align-items-end">
                  <div class="col-md-3">
                    <label class="form-label">Event Type</label>
                    <select v-model="eventForm.event_type" class="form-select">
                      <option value="note">note</option>
                      <option value="triaged">triaged</option>
                      <option value="contained">contained</option>
                      <option value="notification_sent">notification_sent</option>
                      <option value="resolved">resolved</option>
                      <option value="evidence_preserved">evidence_preserved</option>
                    </select>
                  </div>
                  <div class="col-md-7">
                    <label class="form-label">Note</label>
                    <textarea v-model.trim="eventForm.note" class="form-control" rows="2"></textarea>
                  </div>
                  <div class="col-md-2 text-end">
                    <button class="btn btn-outline-primary btn-sm w-100" @click="addEvent" :disabled="savingEvent || !eventForm.note">
                      <span v-if="savingEvent" class="spinner-border spinner-border-sm me-1"></span>
                      Add Event
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="card shadow-sm">
              <div class="card-body">
                <h6 class="mb-3">Incident Timeline</h6>
                <div v-if="selectedIncident.events?.length">
                  <div v-for="event in selectedIncident.events" :key="event.id" class="border-start border-3 ps-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                      <div>
                        <div class="fw-semibold">{{ humanize(event.event_type) }}</div>
                        <div class="small text-muted">{{ event.user?.name || 'System' }} • {{ formatDateTime(event.created_at) }}</div>
                      </div>
                    </div>
                    <div v-if="event.note" class="mt-2">{{ event.note }}</div>
                  </div>
                </div>
                <div v-else class="text-muted small">No incident events recorded yet.</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';

export default {
  name: 'SecurityIncidentsView',
  data() {
    return {
      incidents: [],
      banks: [],
      assignableUsers: [],
      filters: {
        q: '',
        status: 'all',
        type: 'all',
        severity: 'all',
        bank_id: '',
      },
      pagination: {
        from: 0,
        to: 0,
        total: 0,
        prevPage: null,
        nextPage: null,
      },
      typeOptions: ['security_breach', 'privacy_breach', 'malware_detection', 'export_abuse', 'unauthorized_access', 'whatsapp_misdirect', 'system_outage', 'other'],
      severityOptions: ['low', 'medium', 'high', 'critical'],
      statusOptions: ['open', 'investigating', 'contained', 'notified', 'resolved', 'closed'],
      selectedIncident: null,
      createModal: null,
      detailModal: null,
      savingCreate: false,
      savingUpdate: false,
      savingEvent: false,
      createForm: {
        bank_id: '',
        type: 'security_breach',
        severity: 'medium',
        title: '',
        description: '',
        affected_module: '',
        affected_records_count: '',
        suspected_personal_data_exposed: false,
        regulator_notification_required: false,
        bank_notification_required: false,
        assigned_to_user_id: '',
      },
      updateForm: {
        status: 'open',
        severity: 'medium',
        assigned_to_user_id: '',
        affected_records_count: '',
        suspected_personal_data_exposed: false,
        regulator_notification_required: false,
        bank_notification_required: false,
        note: '',
      },
      eventForm: {
        event_type: 'note',
        note: '',
      },
    };
  },
  computed: {
    currentUser() {
      try {
        return JSON.parse(localStorage.getItem('nexus_user') || '{}');
      } catch {
        return {};
      }
    },
    canManage() {
      return ['SUPER_ADMIN', 'ADMIN', 'COMPLIANCE_OFFICER'].includes(this.currentUser.role);
    },
    canCreate() {
      return ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'COMPLIANCE_OFFICER'].includes(this.currentUser.role);
    },
    canAccessAllBanks() {
      return ['SUPER_ADMIN', 'ADMIN'].includes(this.currentUser.role);
    },
  },
  mounted() {
    this.createModal = createManagedModal(this.$refs.createModalRef);
    this.detailModal = createManagedModal(this.$refs.detailModalRef);
    this.fetchIncidents();
    this.loadBanks();
    this.loadAssignableUsers();
  },
  beforeUnmount() {
    disposeManagedModal(this.createModal);
    disposeManagedModal(this.detailModal);
  },
  methods: {
    async fetchIncidents(page = 1) {
      const { data } = await axios.get('/api/security-incidents', {
        params: {
          page,
          q: this.filters.q || undefined,
          status: this.filters.status,
          type: this.filters.type,
          severity: this.filters.severity,
          bank_id: this.filters.bank_id || undefined,
        },
      });

      this.incidents = data.data || [];
      this.pagination = {
        from: data.from,
        to: data.to,
        total: data.total,
        prevPage: data.prev_page_url ? data.current_page - 1 : null,
        nextPage: data.next_page_url ? data.current_page + 1 : null,
      };
    },
    async loadBanks() {
      if (!this.canAccessAllBanks) return;
      const { data } = await axios.get('/api/banks', { params: { per_page: 200 } });
      this.banks = data.data || data || [];
    },
    async loadAssignableUsers() {
      const { data } = await axios.get('/api/users-assignees');
      this.assignableUsers = (data || []).filter((user) => ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AUDITOR', 'COMPLIANCE_OFFICER', 'READ_ONLY_REVIEWER'].includes(user.role));
    },
    resetFilters() {
      this.filters = { q: '', status: 'all', type: 'all', severity: 'all', bank_id: '' };
      this.fetchIncidents(1);
    },
    openCreateModal() {
      this.createForm = {
        bank_id: '',
        type: 'security_breach',
        severity: 'medium',
        title: '',
        description: '',
        affected_module: '',
        affected_records_count: '',
        suspected_personal_data_exposed: false,
        regulator_notification_required: false,
        bank_notification_required: false,
        assigned_to_user_id: '',
      };
      this.createModal.show();
    },
    async createIncident() {
      this.savingCreate = true;
      try {
        await axios.post('/api/security-incidents', {
          ...this.createForm,
          bank_id: this.createForm.bank_id || null,
          assigned_to_user_id: this.createForm.assigned_to_user_id || null,
          affected_records_count: this.createForm.affected_records_count === '' ? null : Number(this.createForm.affected_records_count),
        });
        this.createModal.hide();
        notify.success('Security incident created.', 'Security Incidents');
        this.fetchIncidents();
      } catch (error) {
        notify.error(error.response?.data?.message || 'Failed to create security incident.', 'Security Incidents');
      } finally {
        this.savingCreate = false;
      }
    },
    async openDetail(incident) {
      try {
        const { data } = await axios.get(`/api/security-incidents/${incident.id}`);
        this.selectedIncident = data;
        this.updateForm = {
          status: data.status,
          severity: data.severity,
          assigned_to_user_id: data.assigned_to_user_id || '',
          affected_records_count: data.affected_records_count ?? '',
          suspected_personal_data_exposed: !!data.suspected_personal_data_exposed,
          regulator_notification_required: !!data.regulator_notification_required,
          bank_notification_required: !!data.bank_notification_required,
          note: '',
        };
        this.eventForm = { event_type: 'note', note: '' };
        this.detailModal.show();
      } catch (error) {
        notify.error(error.response?.data?.message || 'Failed to load incident.', 'Security Incidents');
      }
    },
    async saveIncidentUpdate() {
      if (!this.selectedIncident || !this.canManage) return;
      this.savingUpdate = true;
      try {
        const { data } = await axios.put(`/api/security-incidents/${this.selectedIncident.id}`, {
          ...this.updateForm,
          assigned_to_user_id: this.updateForm.assigned_to_user_id || null,
          affected_records_count: this.updateForm.affected_records_count === '' ? null : Number(this.updateForm.affected_records_count),
        });
        this.selectedIncident = await this.reloadIncident(data.id);
        this.updateForm.note = '';
        notify.success('Incident updated.', 'Security Incidents');
        this.fetchIncidents();
      } catch (error) {
        notify.error(error.response?.data?.message || 'Failed to update incident.', 'Security Incidents');
      } finally {
        this.savingUpdate = false;
      }
    },
    async addEvent() {
      if (!this.selectedIncident || !this.canManage || !this.eventForm.note) return;
      this.savingEvent = true;
      try {
        await axios.post(`/api/security-incidents/${this.selectedIncident.id}/events`, this.eventForm);
        this.selectedIncident = await this.reloadIncident(this.selectedIncident.id);
        this.eventForm = { event_type: 'note', note: '' };
        notify.success('Incident event added.', 'Security Incidents');
        this.fetchIncidents();
      } catch (error) {
        notify.error(error.response?.data?.message || 'Failed to add incident event.', 'Security Incidents');
      } finally {
        this.savingEvent = false;
      }
    },
    async reloadIncident(id) {
      const { data } = await axios.get(`/api/security-incidents/${id}`);
      return data;
    },
    humanize(value) {
      return String(value || '').replace(/_/g, ' ');
    },
    formatDateTime(value) {
      if (!value) return '';
      return String(value).replace('T', ' ').slice(0, 16);
    },
    severityBadge(severity) {
      return {
        low: 'bg-secondary',
        medium: 'bg-primary',
        high: 'bg-warning text-dark',
        critical: 'bg-danger',
      }[severity] || 'bg-secondary';
    },
    statusBadge(status) {
      return {
        open: 'bg-danger',
        investigating: 'bg-warning text-dark',
        contained: 'bg-primary',
        notified: 'bg-info text-dark',
        resolved: 'bg-success',
        closed: 'bg-secondary',
      }[status] || 'bg-secondary';
    },
  },
};
</script>
