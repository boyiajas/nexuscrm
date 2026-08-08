<template>
  <div>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-2">
      <div>
        <h2 class="h4 mb-0"><i class="bi bi-journal-check me-2"></i>Compliance Console</h2>
        <small class="text-muted">Manage privacy rights, complaints, officer contacts, retention actions, and secure bank transfer profiles.</small>
      </div>
      <button class="btn btn-outline-secondary btn-sm" @click="fetchOverview">
        <i class="bi bi-arrow-repeat me-1"></i> Refresh
      </button>
    </div>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item" v-for="tab in tabs" :key="tab.key">
        <button class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
          {{ tab.label }}
        </button>
      </li>
    </ul>

    <div v-if="activeTab === 'rights'">
      <div class="card shadow-sm mb-3" v-if="canManage">
        <div class="card-header fw-semibold">New Data Subject Request</div>
        <div class="card-body">
          <form class="row g-2" @submit.prevent="createDsr">
            <div class="col-md-3" v-if="canAccessAllBanks">
              <label class="form-label">Bank</label>
              <select v-model="dsrForm.bank_id" class="form-select">
                <option value="">Global / Shared</option>
                <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Type</label>
              <select v-model="dsrForm.request_type" class="form-select" required>
                <option value="access">Access</option>
                <option value="correction">Correction</option>
                <option value="objection">Objection</option>
                <option value="opt_out">Opt-out</option>
                <option value="deletion">Deletion</option>
                <option value="complaint">Complaint</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Requester</label>
              <input v-model.trim="dsrForm.requester_name" class="form-control" required />
            </div>
            <div class="col-md-3">
              <label class="form-label">Email</label>
              <input v-model.trim="dsrForm.requester_email" class="form-control" type="email" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Phone</label>
              <input v-model.trim="dsrForm.requester_phone" class="form-control" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Channel</label>
              <input v-model.trim="dsrForm.received_channel" class="form-control" placeholder="Email / WhatsApp / Phone" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Assign To</label>
              <select v-model="dsrForm.assigned_to_user_id" class="form-select">
                <option value="">Unassigned</option>
                <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">{{ assignee.name }} ({{ assignee.role }})</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Due At</label>
              <input v-model="dsrForm.due_at" class="form-control" type="datetime-local" />
            </div>
            <div class="col-12">
              <label class="form-label">Details</label>
              <textarea v-model.trim="dsrForm.details" class="form-control" rows="3" required></textarea>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-primary btn-sm" :disabled="saving.dsr">Create Request</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm border mb-4">
        <TableLoadingWrapper :loading="loadingOverview" message="Loading compliance data..." min-height="180px">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead><tr><th class="ps-4">#</th><th>Bank</th><th>Type</th><th>Requester</th><th>Status</th><th>Due</th><th>Assigned</th><th class="pe-4">Actions</th></tr></thead>
              <tbody>
                <tr v-for="row in overview.data_subject_requests" :key="`dsr-${row.id}`">
                  <td class="ps-4 py-3">#{{ row.id }}</td>
                  <td>{{ row.bank?.name || 'Global / Shared' }}</td>
                  <td>{{ humanize(row.request_type) }}</td>
                  <td>{{ row.requester_name }}<div class="small text-muted">{{ row.requester_email || row.requester_phone || '-' }}</div></td>
                  <td><span class="badge bg-secondary">{{ row.status }}</span></td>
                  <td>{{ formatDateTime(row.due_at) || '-' }}</td>
                  <td>{{ row.assignee?.name || 'Unassigned' }}</td>
                  <td class="pe-4">
                    <div class="btn-group btn-group-sm" v-if="canManage">
                      <button class="btn btn-light text-primary border-0 p-1 px-2" @click="updateDsr(row, 'in_progress')">Start</button>
                      <button class="btn btn-light text-warning border-0 p-1 px-2" @click="updateDsr(row, 'waiting_bank')">Waiting Bank</button>
                      <button class="btn btn-light text-success border-0 p-1 px-2" @click="updateDsr(row, 'resolved')">Resolve</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!loadingOverview && !overview.data_subject_requests.length"><td colspan="8" class="text-center text-muted py-5">No data subject requests.</td></tr>
              </tbody>
            </table>
          </div>
        </TableLoadingWrapper>
      </div>
    </div>

    <div v-else-if="activeTab === 'complaints'">
      <div class="card shadow-sm mb-3" v-if="canManage">
        <div class="card-header fw-semibold">New Complaint Escalation</div>
        <div class="card-body">
          <form class="row g-2" @submit.prevent="createComplaint">
            <div class="col-md-3" v-if="canAccessAllBanks">
              <label class="form-label">Bank</label>
              <select v-model="complaintForm.bank_id" class="form-select">
                <option value="">Global / Shared</option>
                <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Type</label>
              <select v-model="complaintForm.complaint_type" class="form-select" required>
                <option value="service">Service</option>
                <option value="privacy">Privacy</option>
                <option value="messaging">Messaging</option>
                <option value="bank_instruction">Bank instruction</option>
                <option value="data_quality">Data quality</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Severity</label>
              <select v-model="complaintForm.severity" class="form-select" required>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Assign To</label>
              <select v-model="complaintForm.assigned_to_user_id" class="form-select">
                <option value="">Unassigned</option>
                <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">{{ assignee.name }} ({{ assignee.role }})</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Title</label>
              <input v-model.trim="complaintForm.title" class="form-control" required />
            </div>
            <div class="col-md-2 form-check mt-4">
              <input v-model="complaintForm.escalation_required" class="form-check-input" type="checkbox" id="complaintEscalation" />
              <label class="form-check-label" for="complaintEscalation">Escalation required</label>
            </div>
            <div class="col-md-2 form-check mt-4">
              <input v-model="complaintForm.regulator_notification_required" class="form-check-input" type="checkbox" id="complaintRegulator" />
              <label class="form-check-label" for="complaintRegulator">Regulator notice</label>
            </div>
            <div class="col-12">
              <label class="form-label">Details</label>
              <textarea v-model.trim="complaintForm.details" class="form-control" rows="3" required></textarea>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-primary btn-sm" :disabled="saving.complaint">Create Complaint</button>
            </div>
          </form>
        </div>
      </div>
      <div class="card shadow-sm border mb-4">
        <TableLoadingWrapper :loading="loadingOverview" message="Loading compliance data..." min-height="180px">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead><tr><th class="ps-4">#</th><th>Bank</th><th>Title</th><th>Type</th><th>Severity</th><th>Status</th><th>Assigned</th><th class="pe-4">Actions</th></tr></thead>
              <tbody>
                <tr v-for="row in overview.complaints" :key="`cmp-${row.id}`">
                  <td class="ps-4 py-3">#{{ row.id }}</td>
                  <td>{{ row.bank?.name || 'Global / Shared' }}</td>
                  <td>{{ row.title }}</td>
                  <td>{{ humanize(row.complaint_type) }}</td>
                  <td><span class="badge" :class="severityBadge(row.severity)">{{ row.severity }}</span></td>
                  <td><span class="badge bg-secondary">{{ row.status }}</span></td>
                  <td>{{ row.assignee?.name || 'Unassigned' }}</td>
                  <td class="pe-4">
                    <div class="btn-group btn-group-sm" v-if="canManage">
                      <button class="btn btn-light text-primary border-0 p-1 px-2" @click="updateComplaint(row, 'investigating')">Investigate</button>
                      <button class="btn btn-light text-warning border-0 p-1 px-2" @click="updateComplaint(row, 'escalated')">Escalate</button>
                      <button class="btn btn-light text-success border-0 p-1 px-2" @click="updateComplaint(row, 'resolved')">Resolve</button>
                      <button class="btn btn-light text-secondary border-0 p-1 px-2" @click="updateComplaint(row, 'closed')">Close</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!loadingOverview && !overview.complaints.length"><td colspan="8" class="text-center text-muted py-5">No complaint cases.</td></tr>
              </tbody>
            </table>
          </div>
        </TableLoadingWrapper>
      </div>
    </div>

    <div v-else-if="activeTab === 'officers'">
      <div class="card shadow-sm mb-3" v-if="canManage">
        <div class="card-header fw-semibold">Information Officer / Deputy</div>
        <div class="card-body">
          <form class="row g-2" @submit.prevent="createOfficer">
            <div class="col-md-3" v-if="canAccessAllBanks">
              <label class="form-label">Bank</label>
              <select v-model="officerForm.bank_id" class="form-select">
                <option value="">Global / Shared</option>
                <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Officer Type</label>
              <select v-model="officerForm.officer_type" class="form-select" required>
                <option value="information_officer">Information Officer</option>
                <option value="deputy_information_officer">Deputy Information Officer</option>
              </select>
            </div>
            <div class="col-md-3"><label class="form-label">Name</label><input v-model.trim="officerForm.name" class="form-control" required /></div>
            <div class="col-md-3"><label class="form-label">Title</label><input v-model.trim="officerForm.title" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">Email</label><input v-model.trim="officerForm.email" class="form-control" type="email" required /></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input v-model.trim="officerForm.phone" class="form-control" /></div>
            <div class="col-md-4 text-end align-self-end"><button class="btn btn-primary btn-sm" :disabled="saving.officer">Save Officer</button></div>
          </form>
        </div>
      </div>
      <div class="card shadow-sm border mb-4">
        <TableLoadingWrapper :loading="loadingOverview" message="Loading compliance data..." min-height="180px">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead><tr><th class="ps-4">#</th><th>Bank</th><th>Type</th><th>Name</th><th>Contact</th><th>Status</th><th class="pe-4">Actions</th></tr></thead>
              <tbody>
                <tr v-for="row in overview.information_officers" :key="`off-${row.id}`">
                  <td class="ps-4 py-3">#{{ row.id }}</td>
                  <td>{{ row.bank?.name || 'Global / Shared' }}</td>
                  <td>{{ humanize(row.officer_type) }}</td>
                  <td>{{ row.name }}<div class="small text-muted">{{ row.title || '-' }}</div></td>
                  <td>{{ row.email }}<div class="small text-muted">{{ row.phone || '-' }}</div></td>
                  <td><span class="badge" :class="row.status === 'active' ? 'bg-success' : 'bg-secondary'">{{ row.status }}</span></td>
                  <td class="pe-4"><button v-if="canManage" class="btn btn-light text-secondary border-0 p-1 px-2" @click="toggleOfficer(row)">{{ row.status === 'active' ? 'Deactivate' : 'Activate' }}</button></td>
                </tr>
                <tr v-if="!loadingOverview && !overview.information_officers.length"><td colspan="7" class="text-center text-muted py-5">No information officers configured.</td></tr>
              </tbody>
            </table>
          </div>
        </TableLoadingWrapper>
      </div>
    </div>

    <div v-else-if="activeTab === 'retention'">
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Retention Policies</div>
            <div class="card-body" v-if="canManage">
              <form class="row g-2" @submit.prevent="createRetentionPolicy">
                <div class="col-md-4" v-if="canAccessAllBanks">
                  <label class="form-label">Bank</label>
                  <select v-model="policyForm.bank_id" class="form-select">
                    <option value="">Global / Shared</option>
                    <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                  </select>
                </div>
                <div class="col-md-4"><label class="form-label">Dataset</label><input v-model.trim="policyForm.dataset" class="form-control" required /></div>
                <div class="col-md-4"><label class="form-label">Retention Days</label><input v-model.number="policyForm.retention_days" type="number" min="1" class="form-control" required /></div>
                <div class="col-md-4"><label class="form-label">Archive After</label><input v-model.number="policyForm.archive_after_days" type="number" min="1" class="form-control" /></div>
                <div class="col-md-4"><label class="form-label">Delete After</label><input v-model.number="policyForm.delete_after_days" type="number" min="1" class="form-control" /></div>
                <div class="col-md-4 form-check mt-4">
                  <input v-model="policyForm.legal_hold_allowed" class="form-check-input" type="checkbox" id="legalHoldAllowed" />
                  <label class="form-check-label" for="legalHoldAllowed">Legal hold allowed</label>
                </div>
                <div class="col-12"><label class="form-label">Notes</label><textarea v-model.trim="policyForm.notes" class="form-control" rows="2"></textarea></div>
                <div class="col-12 text-end"><button class="btn btn-primary btn-sm" :disabled="saving.policy">Save Policy</button></div>
              </form>
            </div>
            <TableLoadingWrapper :loading="loadingOverview" message="Loading compliance data..." min-height="180px">
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                  <thead><tr><th class="ps-4">Bank</th><th>Dataset</th><th>Retention</th><th>Status</th><th class="pe-4">Action</th></tr></thead>
                  <tbody>
                    <tr v-for="row in overview.retention_policies" :key="`pol-${row.id}`">
                      <td class="ps-4 py-3">{{ row.bank?.name || 'Global / Shared' }}</td>
                      <td>{{ row.dataset }}</td>
                      <td>{{ row.retention_days }}d<div class="small text-muted">Archive {{ row.archive_after_days || '-' }} / Delete {{ row.delete_after_days || '-' }}</div></td>
                      <td><span class="badge" :class="row.status === 'active' ? 'bg-success' : 'bg-secondary'">{{ row.status }}</span></td>
                      <td class="pe-4"><button v-if="canManage" class="btn btn-light text-secondary border-0 p-1 px-2" @click="togglePolicy(row)">{{ row.status === 'active' ? 'Deactivate' : 'Activate' }}</button></td>
                    </tr>
                    <tr v-if="!loadingOverview && !overview.retention_policies.length"><td colspan="5" class="text-center text-muted py-5">No retention policies.</td></tr>
                  </tbody>
                </table>
              </div>
            </TableLoadingWrapper>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Retention Actions</div>
            <div class="card-body" v-if="canManage">
              <form class="row g-2" @submit.prevent="createRetentionAction">
                <div class="col-md-4" v-if="canAccessAllBanks">
                  <label class="form-label">Bank</label>
                  <select v-model="actionForm.bank_id" class="form-select">
                    <option value="">Global / Shared</option>
                    <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                  </select>
                </div>
                <div class="col-md-4"><label class="form-label">Dataset</label><input v-model.trim="actionForm.dataset" class="form-control" required /></div>
                <div class="col-md-4"><label class="form-label">Action</label><select v-model="actionForm.action_type" class="form-select" required><option value="archive">Archive</option><option value="delete">Delete</option></select></div>
                <div class="col-12"><label class="form-label">Scope Summary</label><textarea v-model.trim="actionForm.scope_text" class="form-control" rows="2" placeholder="Describe bank, date range, and records affected"></textarea></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea v-model.trim="actionForm.notes" class="form-control" rows="2"></textarea></div>
                <div class="col-12 text-end"><button class="btn btn-primary btn-sm" :disabled="saving.action">Queue Action</button></div>
              </form>
            </div>
            <TableLoadingWrapper :loading="loadingOverview" message="Loading compliance data..." min-height="180px">
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                  <thead><tr><th class="ps-4">Dataset</th><th>Type</th><th>Status</th><th>Scope</th><th class="pe-4">Actions</th></tr></thead>
                  <tbody>
                    <tr v-for="row in overview.retention_actions" :key="`act-${row.id}`">
                      <td class="ps-4 py-3">{{ row.dataset }}</td>
                      <td>{{ row.action_type }}</td>
                      <td><span class="badge bg-secondary">{{ row.status }}</span></td>
                      <td class="small">{{ scopeSummary(row.scope_summary) }}</td>
                      <td class="pe-4">
                        <div class="btn-group btn-group-sm" v-if="canManage">
                          <button v-if="row.status === 'pending'" class="btn btn-light text-primary border-0 p-1 px-2" @click="approveRetentionAction(row)">Approve</button>
                          <button v-if="row.status === 'approved'" class="btn btn-light text-success border-0 p-1 px-2" @click="completeRetentionAction(row)">Complete</button>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="!loadingOverview && !overview.retention_actions.length"><td colspan="5" class="text-center text-muted py-5">No retention actions.</td></tr>
                  </tbody>
                </table>
              </div>
            </TableLoadingWrapper>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="activeTab === 'transfers'">
      <div class="card shadow-sm mb-3" v-if="canManage">
        <div class="card-header fw-semibold">Secure Bank Transfer Profile</div>
        <div class="card-body">
          <form class="row g-2" @submit.prevent="createTransferProfile">
            <div class="col-md-3" v-if="canAccessAllBanks">
              <label class="form-label">Bank</label>
              <select v-model="transferForm.bank_id" class="form-select">
                <option value="">Global / Shared</option>
                <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
              </select>
            </div>
            <div class="col-md-3"><label class="form-label">Name</label><input v-model.trim="transferForm.name" class="form-control" required /></div>
            <div class="col-md-2"><label class="form-label">Environment</label><select v-model="transferForm.environment" class="form-select"><option value="development">Development</option><option value="staging">Staging</option><option value="production">Production</option></select></div>
            <div class="col-md-2"><label class="form-label">Host</label><input v-model.trim="transferForm.host" class="form-control" required /></div>
            <div class="col-md-2"><label class="form-label">Port</label><input v-model.number="transferForm.port" class="form-control" type="number" min="1" max="65535" /></div>
            <div class="col-md-3"><label class="form-label">Username</label><input v-model.trim="transferForm.username" class="form-control" required /></div>
            <div class="col-md-3"><label class="form-label">Password</label><input v-model="transferForm.password" class="form-control" type="password" /></div>
            <div class="col-md-3"><label class="form-label">Remote Path</label><input v-model.trim="transferForm.remote_path" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">Filename Pattern</label><input v-model.trim="transferForm.filename_pattern" class="form-control" placeholder="*.csv" /></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea v-model.trim="transferForm.notes" class="form-control" rows="2"></textarea></div>
            <div class="col-12 text-end"><button class="btn btn-primary btn-sm" :disabled="saving.transfer">Save Transfer Profile</button></div>
          </form>
        </div>
      </div>
      <div class="card shadow-sm border mb-4">
        <TableLoadingWrapper :loading="loadingOverview" message="Loading compliance data..." min-height="180px">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead><tr><th class="ps-4">Bank</th><th>Profile</th><th>Endpoint</th><th>Status</th><th>Last Test</th><th>Recent Runs</th><th class="pe-4">Actions</th></tr></thead>
              <tbody>
                <tr v-for="row in overview.bank_transfer_profiles" :key="`trf-${row.id}`">
                  <td class="ps-4 py-3">{{ row.bank?.name || 'Global / Shared' }}</td>
                  <td>{{ row.name }}<div class="small text-muted">{{ row.environment }} • {{ row.protocol }}</div></td>
                  <td>{{ row.host }}:{{ row.port }}<div class="small text-muted">{{ row.remote_path || '.' }}</div></td>
                  <td><span class="badge" :class="row.status === 'active' ? 'bg-success' : 'bg-secondary'">{{ row.status }}</span></td>
                  <td>{{ formatDateTime(row.last_tested_at) || '-' }}</td>
                  <td class="small">
                    <div v-for="run in row.runs || []" :key="`run-${run.id}`">
                      {{ run.run_type }}: {{ run.status }}<span v-if="run.result_message"> • {{ run.result_message }}</span>
                    </div>
                  </td>
                  <td class="pe-4">
                    <div class="btn-group btn-group-sm" v-if="canManage">
                      <button class="btn btn-light text-secondary border-0 p-1 px-2" @click="toggleTransferProfile(row)">{{ row.status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                      <button class="btn btn-light text-primary border-0 p-1 px-2" @click="testTransferProfile(row)">Test</button>
                      <button class="btn btn-light text-success border-0 p-1 px-2" @click="syncTransferProfile(row)">Sync</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!loadingOverview && !overview.bank_transfer_profiles.length"><td colspan="7" class="text-center text-muted py-5">No bank transfer profiles.</td></tr>
              </tbody>
            </table>
          </div>
        </TableLoadingWrapper>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import { notify } from '../utils/notify';

const emptyOverview = () => ({
  data_subject_requests: [],
  complaints: [],
  information_officers: [],
  retention_policies: [],
  retention_actions: [],
  bank_transfer_profiles: [],
});

export default {
  name: 'ComplianceConsoleView',
  components: {
    TableLoadingWrapper,
  },
  data() {
    return {
      activeTab: 'rights',
      tabs: [
        { key: 'rights', label: 'Data Subject Rights' },
        { key: 'complaints', label: 'Complaints' },
        { key: 'officers', label: 'Information Officers' },
        { key: 'retention', label: 'Retention' },
        { key: 'transfers', label: 'Bank Transfers' },
      ],
      overview: emptyOverview(),
      loadingOverview: false,
      banks: [],
      assignees: [],
      saving: {
        dsr: false,
        complaint: false,
        officer: false,
        policy: false,
        action: false,
        transfer: false,
      },
      dsrForm: { bank_id: '', request_type: 'access', requester_name: '', requester_email: '', requester_phone: '', received_channel: '', assigned_to_user_id: '', due_at: '', details: '' },
      complaintForm: { bank_id: '', complaint_type: 'service', severity: 'medium', title: '', details: '', escalation_required: false, regulator_notification_required: false, assigned_to_user_id: '' },
      officerForm: { bank_id: '', officer_type: 'information_officer', name: '', title: '', email: '', phone: '' },
      policyForm: { bank_id: '', dataset: '', retention_days: 365, archive_after_days: '', delete_after_days: '', legal_hold_allowed: true, notes: '' },
      actionForm: { bank_id: '', dataset: '', action_type: 'archive', scope_text: '', notes: '' },
      transferForm: { bank_id: '', name: '', environment: 'production', host: '', port: 22, username: '', password: '', remote_path: '', filename_pattern: '*.csv', notes: '' },
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
    canAccessAllBanks() {
      return ['SUPER_ADMIN', 'ADMIN'].includes(this.currentUser.role);
    },
  },
  mounted() {
    this.fetchOverview();
    this.fetchAssignees();
    if (this.canAccessAllBanks) {
      this.fetchBanks();
    }
  },
  methods: {
    async fetchOverview() {
      this.loadingOverview = true;
      try {
        const { data } = await axios.get('/api/compliance/overview');
        this.overview = { ...emptyOverview(), ...data };
      } finally {
        this.loadingOverview = false;
      }
    },
    async fetchBanks() {
      const { data } = await axios.get('/api/banks', { params: { per_page: 200 } });
      this.banks = data.data || data || [];
    },
    async fetchAssignees() {
      const { data } = await axios.get('/api/users-assignees');
      this.assignees = data || [];
    },
    async createDsr() {
      this.saving.dsr = true;
      try {
        await axios.post('/api/compliance/data-subject-requests', this.dsrForm);
        this.dsrForm = { bank_id: '', request_type: 'access', requester_name: '', requester_email: '', requester_phone: '', received_channel: '', assigned_to_user_id: '', due_at: '', details: '' };
        notify.success('Data subject request created.', 'Compliance');
        this.fetchOverview();
      } finally {
        this.saving.dsr = false;
      }
    },
    async updateDsr(row, status) {
      await axios.put(`/api/compliance/data-subject-requests/${row.id}`, { status });
      notify.success(`Request moved to ${status}.`, 'Compliance');
      this.fetchOverview();
    },
    async createComplaint() {
      this.saving.complaint = true;
      try {
        await axios.post('/api/compliance/complaints', this.complaintForm);
        this.complaintForm = { bank_id: '', complaint_type: 'service', severity: 'medium', title: '', details: '', escalation_required: false, regulator_notification_required: false, assigned_to_user_id: '' };
        notify.success('Complaint case created.', 'Compliance');
        this.fetchOverview();
      } finally {
        this.saving.complaint = false;
      }
    },
    async updateComplaint(row, status) {
      await axios.put(`/api/compliance/complaints/${row.id}`, { status });
      notify.success(`Complaint moved to ${status}.`, 'Compliance');
      this.fetchOverview();
    },
    async createOfficer() {
      this.saving.officer = true;
      try {
        await axios.post('/api/compliance/information-officers', this.officerForm);
        this.officerForm = { bank_id: '', officer_type: 'information_officer', name: '', title: '', email: '', phone: '' };
        notify.success('Officer saved.', 'Compliance');
        this.fetchOverview();
      } finally {
        this.saving.officer = false;
      }
    },
    async toggleOfficer(row) {
      await axios.put(`/api/compliance/information-officers/${row.id}`, { status: row.status === 'active' ? 'inactive' : 'active' });
      notify.success('Officer updated.', 'Compliance');
      this.fetchOverview();
    },
    async createRetentionPolicy() {
      this.saving.policy = true;
      try {
        await axios.post('/api/compliance/retention-policies', this.policyForm);
        this.policyForm = { bank_id: '', dataset: '', retention_days: 365, archive_after_days: '', delete_after_days: '', legal_hold_allowed: true, notes: '' };
        notify.success('Retention policy saved.', 'Compliance');
        this.fetchOverview();
      } finally {
        this.saving.policy = false;
      }
    },
    async togglePolicy(row) {
      await axios.put(`/api/compliance/retention-policies/${row.id}`, { status: row.status === 'active' ? 'inactive' : 'active' });
      notify.success('Retention policy updated.', 'Compliance');
      this.fetchOverview();
    },
    async createRetentionAction() {
      this.saving.action = true;
      try {
        await axios.post('/api/compliance/retention-actions', {
          bank_id: this.actionForm.bank_id || null,
          dataset: this.actionForm.dataset,
          action_type: this.actionForm.action_type,
          scope_summary: this.actionForm.scope_text ? { summary: this.actionForm.scope_text } : null,
          notes: this.actionForm.notes || null,
        });
        this.actionForm = { bank_id: '', dataset: '', action_type: 'archive', scope_text: '', notes: '' };
        notify.success('Retention action queued.', 'Compliance');
        this.fetchOverview();
      } finally {
        this.saving.action = false;
      }
    },
    async approveRetentionAction(row) {
      await axios.post(`/api/compliance/retention-actions/${row.id}/approve`);
      notify.success('Retention action approved.', 'Compliance');
      this.fetchOverview();
    },
    async completeRetentionAction(row) {
      await axios.post(`/api/compliance/retention-actions/${row.id}/complete`, { execution_result: 'Action completed and logged in the retention workflow.' });
      notify.success('Retention action completed.', 'Compliance');
      this.fetchOverview();
    },
    async createTransferProfile() {
      this.saving.transfer = true;
      try {
        await axios.post('/api/compliance/bank-transfer-profiles', this.transferForm);
        this.transferForm = { bank_id: '', name: '', environment: 'production', host: '', port: 22, username: '', password: '', remote_path: '', filename_pattern: '*.csv', notes: '' };
        notify.success('Bank transfer profile saved.', 'Compliance');
        this.fetchOverview();
      } finally {
        this.saving.transfer = false;
      }
    },
    async toggleTransferProfile(row) {
      await axios.put(`/api/compliance/bank-transfer-profiles/${row.id}`, { status: row.status === 'active' ? 'inactive' : 'active' });
      notify.success('Transfer profile updated.', 'Compliance');
      this.fetchOverview();
    },
    async testTransferProfile(row) {
      const { data } = await axios.post(`/api/compliance/bank-transfer-profiles/${row.id}/test`);
      notify.info(data.result_message || 'Transfer test completed.', 'Compliance');
      this.fetchOverview();
    },
    async syncTransferProfile(row) {
      const { data } = await axios.post(`/api/compliance/bank-transfer-profiles/${row.id}/sync`);
      notify.info(data.result_message || 'Transfer sync completed.', 'Compliance');
      this.fetchOverview();
    },
    formatDateTime(value) {
      if (!value) return '';
      return String(value).replace('T', ' ').slice(0, 16);
    },
    humanize(value) {
      return String(value || '').replaceAll('_', ' ');
    },
    severityBadge(value) {
      return { low: 'bg-secondary', medium: 'bg-info text-dark', high: 'bg-warning text-dark', critical: 'bg-danger' }[value] || 'bg-secondary';
    },
    scopeSummary(scope) {
      if (!scope) return '-';
      if (scope.summary) return scope.summary;
      return JSON.stringify(scope);
    },
  },
};
</script>
