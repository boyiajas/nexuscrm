<template>
  <div>
    <div class="fade-in-up">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div>
        <button class="btn btn-link btn-sm p-0 text-decoration-none text-muted fw-semibold mb-1" @click="$router.back()">
          <i class="bi bi-arrow-left me-1"></i> Back to Campaigns
        </button>
        <h1 class="h3 fw-bold text-dark mb-1">
          {{ campaign?.name || 'July Payment Notification' }}
        </h1>
        <div class="small text-muted d-flex align-items-center gap-2 flex-wrap">
          <span>Campaign Details · Deployed {{ campaign?.created_at || 'Jul 01, 2024' }}</span>
          <span class="badge-status-active">
            {{ campaign?.status || 'Active' }}
          </span>
          <span v-if="campaign?.bank?.name" class="badge bg-light text-dark border">
            {{ campaign.bank.name }}
          </span>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm rounded-2 d-flex align-items-center gap-1 shadow-sm" @click="sendNow" :disabled="!canSend">
          <i class="bi bi-send-check"></i> Send Now
        </button>
        <button class="btn btn-outline-secondary btn-sm rounded-2 d-flex align-items-center gap-1 shadow-sm" @click="refreshAll">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
      </div>
    </div>

    <!-- Overview Stat Cards Strip -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #64748b !important;">
          <div class="card-body p-3 d-flex justify-content-between align-items-start position-relative z-1">
            <div>
              <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">TOTAL CLIENTS</div>
              <div class="stat-card-number mt-1">{{ stats.total_clients || clients.length || campaign?.total_recipients || 0 }}</div>
            </div>
            <div class="stat-icon-badge">
              <i class="bi bi-people-fill"></i>
            </div>
          </div>
          <i class="bi bi-people-fill position-absolute text-muted" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #10b981 !important;">
          <div class="card-body p-3 d-flex justify-content-between align-items-start position-relative z-1">
            <div>
              <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">WHATSAPP</div>
              <div class="stat-card-number mt-1">{{ totalWhatsappQueued }}</div>
              <small class="text-muted" style="font-size: 0.75rem;">Total messages queued</small>
            </div>
            <div class="stat-icon-badge" style="background-color: #ecfdf5; color: #059669;">
              <i class="bi bi-whatsapp"></i>
            </div>
          </div>
          <i class="bi bi-whatsapp position-absolute text-success" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border shadow-sm h-100 opacity-75 position-relative overflow-hidden" style="border-left: 3px solid #3b82f6 !important;">
          <div class="card-body p-3 d-flex justify-content-between align-items-center position-relative z-1">
            <div>
              <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">EMAIL METRICS</div>
              <div class="h5 fw-bold text-muted mb-0 mt-1">N/A</div>
            </div>
            <div class="stat-icon-badge text-muted">
              <i class="bi bi-envelope"></i>
            </div>
          </div>
          <i class="bi bi-envelope position-absolute text-muted" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border shadow-sm h-100 opacity-75 position-relative overflow-hidden" style="border-left: 3px solid #8b5cf6 !important;">
          <div class="card-body p-3 d-flex justify-content-between align-items-center position-relative z-1">
            <div>
              <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">SMS METRICS</div>
              <div class="h5 fw-bold text-muted mb-0 mt-1">N/A</div>
            </div>
            <div class="stat-icon-badge text-muted">
              <i class="bi bi-chat-dots"></i>
            </div>
          </div>
          <i class="bi bi-chat-dots position-absolute text-muted" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
        </div>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-pills gap-1 mb-4 p-1 rounded-3 bg-white border shadow-sm">
      <li class="nav-item">
        <button class="nav-link active px-4 py-2 fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-clients" type="button">
          Clients
        </button>
      </li>
      <li class="nav-item" v-if="channels.whatsapp">
        <button class="nav-link px-4 py-2 fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-whatsapp" type="button">
          WhatsApp
        </button>
      </li>
      <li class="nav-item" v-if="channels.email">
        <button class="nav-link px-4 py-2 fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-email" type="button">
          Email
        </button>
      </li>
      <li class="nav-item" v-if="channels.sms">
        <button class="nav-link px-4 py-2 fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-sms" type="button">
          SMS
        </button>
      </li>
    </ul>

    <div class="tab-content">

      <!-- Clients TAB -->
      <div class="tab-pane fade show active" id="tab-clients">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="card-title mb-0">Clients in this campaign</h5>
              <div class="d-flex gap-2">
                <button 
                  v-if="selectedClients.length > 0"
                  class="btn btn-sm btn-outline-danger" 
                  @click="removeSelectedClients" 
                  :disabled="!canManageCampaign"
                >
                  <i class="bi bi-trash me-1"></i>
                  Remove Selected ({{ selectedClients.length }})
                </button>
                <button class="btn btn-sm btn-outline-secondary" @click="exportClients">
                  <i class="bi bi-filetype-csv me-1"></i>
                  Export CSV
                </button>
                <button class="btn btn-sm btn-primary" @click="openAddClientsModal" :disabled="!canManageCampaign">
                  <i class="bi bi-person-plus me-1"></i>
                  Add Clients to Campaign
                </button>
              </div>
            </div>
            <!-- Bulk Actions Bar -->
            <div v-if="selectedClients.length > 0" class="d-flex align-items-center justify-content-between mb-3 px-3 py-2 bg-primary bg-opacity-10 rounded border border-primary border-opacity-25 shadow-sm">
              <div>
                <span class="fw-bold text-primary">{{ selectedClients.length }}</span> <span class="text-secondary small fw-medium">client(s) selected</span>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-danger bg-white fw-medium shadow-sm" @click="bulkRemoveClients" :disabled="bulkActionLoading">
                  <i class="bi bi-person-dash"></i> Remove from Campaign
                </button>
              </div>
            </div>

            <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4" style="width: 40px;">
                    <div class="form-check m-0">
                      <input class="form-check-input" type="checkbox" :checked="selectAllClients" @change="toggleSelectAllClients($event)">
                    </div>
                  </th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Bank</th>
                  <th>Assigned Owner</th>
                  <th>Departments</th>
                  <th>Opt-In Status</th>
                  <th>WhatsApp</th>
                  <th>Email</th>
                  <th>SMS</th>
                  <th class="pe-4 text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cl in clients" :key="cl.id">
                  <td class="ps-4 py-1">
                    <div class="form-check m-0">
                      <input class="form-check-input" type="checkbox" :value="cl.id" v-model="selectedClients">
                    </div>
                  </td>
                  <td class="py-3">{{ cl.name }}</td>
                  <td>{{ cl.email || '-' }}</td>
                  <td>{{ cl.phone || '-' }}</td>
                  <td>{{ cl.bank_name || campaign?.bank?.name || '-' }}</td>
                  <td>{{ cl.assigned_to_name || '-' }}</td>
                  <td>
                    <template v-if="cl.departments && cl.departments.length">
                      <span
                        v-for="d in cl.departments"
                        :key="d.id"
                        class="badge bg-light text-dark border me-1"
                      >
                        {{ d.name }}
                      </span>
                    </template>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td>
                    <span v-if="cl.whatsapp_opted_out_at || cl.opt_in === 'no'" class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                      <i class="bi bi-x-circle me-1"></i> Opted Out
                    </span>
                    <span v-else-if="cl.whatsapp_opted_in_at" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                      <i class="bi bi-check-circle me-1"></i> Opted In
                    </span>
                    <span v-else class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                      <i class="bi bi-dash-circle me-1"></i> None
                    </span>
                  </td>
                  <td>
                    <span class="badge" :class="statusColor(cl.whatsapp_status)">
                      {{ cl.whatsapp_status || '-' }}
                    </span>
                  </td>
                  <td>
                    <span class="badge" :class="statusColor(cl.email_status)">
                      {{ cl.email_status || '-' }}
                    </span>
                  </td>
                  <td>
                    <span class="badge" :class="statusColor(cl.sms_status)">
                      {{ cl.sms_status || '-' }}
                    </span>
                  </td>
                  <td class="pe-4 text-end">
                    <div class="d-flex justify-content-end gap-1">
                      <button class="btn btn-light text-primary border-0 p-1 px-2" @click="viewClient(cl)" title="View Client">
                        <i class="bi bi-eye"></i>
                      </button>
                      <button class="btn btn-light text-danger border-0 p-1 px-2" @click="removeClient(cl)" :disabled="!canManageCampaign" title="Remove Client">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="clients.length === 0">
                  <td colspan="11" class="text-center text-muted py-3">
                    No clients added to this campaign yet.
                  </td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>

      <!-- WhatsApp TAB -->
      <div class="tab-pane fade" id="tab-whatsapp" v-if="channels.whatsapp">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="card-title mb-0">
                <i class="bi bi-whatsapp me-1 text-success"></i>
                WhatsApp Sends
              </h5>
              <div class="d-flex gap-2">
                <button
                  class="btn btn-sm btn-outline-primary"
                  @click="openAddWhatsappTemplateModal"
                  :disabled="whatsappModalLoading || !canManageCampaign"
                >
                  <span
                    v-if="whatsappModalLoading"
                    class="spinner-border spinner-border-sm me-1"
                  ></span>
                  <i v-else class="bi bi-plus-circle me-1"></i>
                  <span v-if="whatsappModalLoading">Loading...</span>
                  <span v-else>Add WhatsApp Template</span>
                </button>
                <button class="btn btn-sm btn-outline-secondary" @click="exportWhatsApp">
                  <i class="bi bi-filetype-csv me-1"></i>
                  Export CSV
                </button>
              </div>
            </div>

            <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4">Template</th>
                  <th>Status</th>
                  <th>Sent At</th>
                  <th>Total</th>
                  <th>Delivered</th>
                  <th>Failed</th>
                  <th>Pending</th>
                  <th>Chat Request</th>
                  <th>Responses</th>
                  <th class="text-end pe-4">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="w in whatsappMessages" :key="w.id">
                  <td class="ps-4 py-1">
                    <div class="fw-semibold">
                      <span v-if="isFlowSend(w)">
                        {{ w.flow_name || w.template_name || '(Flow)' }}
                        <span class="badge bg-info text-dark ms-1">Flow</span>
                      </span>
                      <span v-else>
                        {{ w.template_name || '(No name)' }}
                        <span class="badge bg-light text-dark border ms-1">Template</span>
                      </span>
                    </div>
                    <small class="text-muted">
                      <span v-if="isFlowSend(w)">Flow definition</span>
                      <span v-else>WhatsApp template</span>
                    </small>
                  </td>
                  <td>
                    <span class="badge" :class="statusColor(whatsappStatus(w))">
                      {{ whatsappStatus(w) }}
                    </span>
                  </td>
                  <td>{{ w.sent_at || '-' }}</td>
                  <td>{{ w.total }}</td>
                  <td>{{ w.delivered }}</td>
                  <td>{{ w.failed }}</td>
                  <td>{{ w.pending }}</td>
                  <td>
                    <span :class="w.enable_live_chat ? 'badge bg-success' : 'badge bg-secondary'">
                      {{ w.enable_live_chat ? 'Enabled' : 'Disabled' }}
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-success me-1">Yes: {{ w.yes_responses_count || 0 }}</span>
                    <span class="badge bg-secondary">No: {{ w.no_responses_count || 0 }}</span>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                      <button
                        class="btn btn-outline-success"
                        v-if="canSendWhatsapp(w)"
                        @click="sendDraftWhatsapp(w)"
                        title="Send"
                      >
                        <i class="bi bi-send-check"></i>
                      </button>
                      <button
                        class="btn btn-outline-primary"
                        @click="viewRecipients('WhatsApp', w)"
                        title="View Dashboard"
                      >
                        <i class="bi bi-bar-chart-line"></i>
                      </button>
                      <button
                        class="btn btn-outline-secondary"
                        @click="editWhatsappTemplate(w)"
                        :disabled="!whatsappTemplateId(w)"
                        title="Edit Template"
                      >
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button
                        class="btn btn-outline-danger"
                        @click="deleteWhatsappTemplate(w)"
                        :disabled="!whatsappTemplateId(w)"
                        title="Delete Template"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="whatsappMessages.length === 0">
                  <td colspan="10" class="text-center text-muted py-3">
                    No WhatsApp sends yet.
                  </td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Email TAB -->
      <div class="tab-pane fade" id="tab-email" v-if="channels.email">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="card-title mb-0">
                <i class="bi bi-envelope-paper me-1 text-primary"></i>
                Emails
              </h5>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" @click="openAddEmailTemplateModal" :disabled="!canManageCampaign">
                  <i class="bi bi-plus-circle me-1"></i>
                  Add Email Template
                </button>
                <button class="btn btn-sm btn-outline-secondary" @click="exportEmails">
                  <i class="bi bi-filetype-csv me-1"></i>
                  Export CSV
                </button>
              </div>
            </div>

            <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4">Subject</th>
                  <th>Status</th>
                  <th>Sent At</th>
                  <th>Total</th>
                  <th>Delivered</th>
                  <th>Bounced</th>
                  <th>Opened</th>
                  <th>Clicked</th>
                  <th class="text-end pe-4">Dashboard</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="m in emails" :key="m.id">
                  <td class="ps-4 py-1">
                    <div class="fw-semibold">{{ m.subject || '(No subject)' }}</div>
                    <small class="text-muted">Email batch</small>
                  </td>
                  <td>
                    <span class="badge" :class="statusColor(m.status || 'sent')">
                      {{ m.status || 'Sent' }}
                    </span>
                  </td>
                  <td>{{ m.sent_at || '-' }}</td>
                  <td>{{ m.total }}</td>
                  <td>{{ m.delivered }}</td>
                  <td>{{ m.bounced }}</td>
                  <td>{{ m.opened }}</td>
                  <td>{{ m.clicked }}</td>
                  <td class="text-end">
                    <button
                      class="btn btn-sm btn-outline-primary"
                      @click="viewRecipients('Email', m)"
                    >
                      <i class="bi bi-bar-chart-line me-1"></i>
                      View Dashboard
                    </button>
                  </td>
                </tr>
                <tr v-if="emails.length === 0">
                  <td colspan="9" class="text-center text-muted py-3">
                    No emails sent yet.
                  </td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>

      <!-- SMS TAB -->
      <div class="tab-pane fade" id="tab-sms" v-if="channels.sms">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="card-title mb-0">
                <i class="bi bi-chat-left-text me-1 text-info"></i>
                SMS Messages
              </h5>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" @click="openAddSmsTemplateModal" :disabled="!canManageCampaign">
                  <i class="bi bi-plus-circle me-1"></i>
                  Add SMS Template
                </button>
                <button class="btn btn-sm btn-outline-secondary" @click="exportSms">
                  <i class="bi bi-filetype-csv me-1"></i>
                  Export CSV
                </button>
              </div>
            </div>

            <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4">Subject</th>
                  <th>Text</th>
                  <th>Status</th>
                  <th>Sent At</th>
                  <th>Total</th>
                  <th>Delivered</th>
                  <th>Failed</th>
                  <th>Pending</th>
                  <th class="text-end pe-4">Dashboard</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in smsMessages" :key="s.id">
                  <td class="ps-4 py-1">{{ s.subject || '-' }}</td>
                  <td class="text-truncate" style="max-width: 260px;">
                    {{ s.text }}
                  </td>
                  <td>
                    <span class="badge" :class="statusColor(s.status || 'sent')">
                      {{ s.status || 'Sent' }}
                    </span>
                  </td>
                  <td>{{ s.sent_at }}</td>
                  <td>{{ s.total }}</td>
                  <td>{{ s.delivered }}</td>
                  <td>{{ s.failed }}</td>
                  <td>{{ s.pending }}</td>
                  <td class="text-end">
                    <button
                      class="btn btn-sm btn-outline-primary"
                      @click="viewRecipients('SMS', s)"
                    >
                      <i class="bi bi-bar-chart-line me-1"></i>
                      View Dashboard
                    </button>
                  </td>
                </tr>
                <tr v-if="smsMessages.length === 0">
                  <td colspan="9" class="text-center text-muted py-3">
                    No SMS messages sent yet.
                  </td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

    <!-- Mini Dashboard Modal (WhatsApp / Email / SMS) -->
    <div class="modal fade" tabindex="-1" ref="recipientsModalRef">
      <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 92vw; width: 1400px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <!-- Modal Header -->
          <div class="modal-header bg-white border-bottom px-4 py-3 align-items-center justify-content-between">
            <div>
              <div class="small text-muted d-flex align-items-center gap-1 mb-1" style="font-size: 0.78rem;">
                <span>Campaigns</span> <i class="bi bi-chevron-right text-muted" style="font-size: 0.65rem;"></i>
                <span>{{ recipientModal.channel || 'WhatsApp' }}</span> <i class="bi bi-chevron-right text-muted" style="font-size: 0.65rem;"></i>
                <span class="fw-semibold text-dark">Batch #{{ recipientModal.meta.id || '4892' }}</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <h2 class="h4 fw-bold text-dark mb-0">Batch Overview</h2>
                <span class="badge-status-delivered px-3 py-1 text-uppercase fw-bold" style="letter-spacing: 0.05em; font-size: 0.72rem;">
                  {{ recipientModal.meta.status || 'QUEUED' }}
                </span>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <button
                type="button"
                class="btn btn-outline-secondary btn-sm rounded-2 px-3 fw-semibold d-flex align-items-center gap-1 shadow-sm"
                @click="sendBatchNow"
                :disabled="sendingBatch"
              >
                <i class="bi bi-pause-btn me-1"></i> Pause Batch
              </button>
              <button
                type="button"
                class="btn btn-danger btn-sm rounded-2 px-3 fw-semibold d-flex align-items-center gap-1 shadow-sm"
                style="background-color: #b91c1c; border-color: #b91c1c;"
                data-bs-dismiss="modal"
              >
                <i class="bi bi-x-circle me-1"></i> Cancel
              </button>
              <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="modal-body p-4" style="background-color: #f8fafc;">
            <!-- Top Cards Strip Row -->
            <div class="row g-3 mb-4">
              <!-- Template Details Card (Col 4) -->
              <div class="col-lg-4">
                <div class="card card-accent-dark h-100 border shadow-sm">
                  <div class="card-body p-2 d-flex flex-column justify-content-center">
                    <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                      <i class="bi bi-file-earmark-text text-muted"></i> Template Details
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-0">
                      <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">TEMPLATE NAME</div>
                      <div class="fw-bold text-dark" style="font-size: 0.85rem;">{{ recipientModal.meta.template_name || 'N/A' }}</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-0">
                      <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.65rem;">CHANNEL</div>
                      <div class="fw-semibold text-dark d-flex align-items-center gap-1" style="font-size: 0.8rem;">
                        <i v-if="recipientModal.channel === 'WhatsApp'" class="bi bi-whatsapp text-success"></i>
                        <i v-else-if="recipientModal.channel === 'Email'" class="bi bi-envelope-paper text-primary"></i>
                        <i v-else-if="recipientModal.channel === 'SMS'" class="bi bi-chat-left-text text-info"></i>
                        {{ recipientModal.channel || 'Unknown' }}
                      </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-0" v-if="recipientModal.channel === 'WhatsApp'">
                      <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.65rem;">SENDER / FROM</div>
                      <div class="fw-semibold text-dark" style="font-size: 0.8rem;">{{ recipientModal.meta.reply_number || '-' }}</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                      <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.65rem;">SCHEDULED FOR</div>
                      <div class="fw-semibold text-dark" style="font-size: 0.8rem;">{{ recipientModal.meta.scheduled_at || 'Immediate' }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Metrics Strip Cards (4 Cards, Col 2 each) -->
              <div class="col-lg-2 col-md-3">
                <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #64748b !important;">
                  <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-list-task me-1"></i> TOTAL</div>
                    </div>
                    <div class="stat-card-number mt-3">{{ recipientModal.summary.total || 0 }}</div>
                  </div>
                  <i class="bi bi-people-fill position-absolute text-muted" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
                </div>
              </div>

              <div class="col-lg-2 col-md-3">
                <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #10b981 !important;">
                  <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="text-success small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-check2-all me-1"></i> DELIVERED</div>
                    </div>
                    <div>
                      <div class="stat-card-number mt-3">{{ recipientModal.summary.delivered || 0 }}</div>
                      <small class="text-muted" style="font-size: 0.72rem;">{{ recipientModal.summary.total ? ((recipientModal.summary.delivered || 0) / recipientModal.summary.total * 100).toFixed(0) : 0 }}% completion</small>
                    </div>
                  </div>
                  <i class="bi bi-check2-all position-absolute text-success" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
                </div>
              </div>

              <div class="col-lg-2 col-md-3">
                <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #3b82f6 !important;">
                  <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="text-primary small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-three-dots me-1"></i> PENDING</div>
                    </div>
                    <div>
                      <div class="stat-card-number mt-3">{{ recipientModal.summary.pending || 0 }}</div>
                      <small class="text-muted" style="font-size: 0.72rem;">{{ recipientModal.summary.total ? ((recipientModal.summary.pending || 0) / recipientModal.summary.total * 100).toFixed(0) : 0 }}% remaining</small>
                    </div>
                  </div>
                  <i class="bi bi-three-dots position-absolute text-primary" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
                </div>
              </div>

              <div class="col-lg-2 col-md-3">
                <div class="card border shadow-sm h-100 position-relative overflow-hidden" style="border-left: 3px solid #ef4444 !important;">
                  <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="text-danger small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="bi bi-exclamation-circle me-1"></i> FAILED</div>
                    </div>
                    <div>
                      <div class="stat-card-number mt-3">{{ recipientModal.summary.failed || 0 }}</div>
                      <small class="text-muted" style="font-size: 0.72rem;">{{ recipientModal.summary.total ? ((recipientModal.summary.failed || 0) / recipientModal.summary.total * 100).toFixed(0) : 0 }}% error rate</small>
                    </div>
                  </div>
                  <i class="bi bi-exclamation-circle position-absolute text-danger" style="bottom: -15px; right: -5px; font-size: 4.5rem; opacity: 0.1; z-index: 0; pointer-events: none;"></i>
                </div>
              </div>
            </div>

            <!-- Recipients Table Section Card -->
            <div class="card border shadow-sm">
              <div class="card-header bg-white py-3 px-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 border-bottom">
                <h3 class="h6 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                  <i class="bi bi-people-fill text-muted"></i> Recipients
                </h3>

                <div class="d-flex align-items-center gap-2">
                  <!-- Search input -->
                  <div class="header-search-bar py-1 px-3" style="width: 240px;">
                    <i class="bi bi-search text-muted"></i>
                    <input
                      v-model="recipientModal.filter"
                      type="text"
                      class="header-search-input"
                      placeholder="Search phone, ID..."
                    />
                  </div>

                  <button class="btn btn-sm btn-light border rounded-2 p-1 px-2 text-secondary" title="Filter">
                    <i class="bi bi-sliders"></i>
                  </button>

                  <button class="btn btn-sm btn-light border rounded-2 p-1 px-2 text-secondary" title="Download CSV" @click="exportWhatsApp">
                    <i class="bi bi-download"></i>
                  </button>
                </div>
              </div>

              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0 align-middle">
                    <thead>
                      <tr>
                        <th class="ps-4" style="width: 50px;">#</th>
                        <th>PHONE NUMBER</th>
                        <th>BANK / CONTEXT</th>
                        <th>DEPARTMENT</th>
                        <th>OPT-IN</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">ACTIONS</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(r, idx) in filteredRecipients" :key="r.id">
                        <td class="ps-4 text-muted small fw-bold">{{ idx + 1 }}</td>
                        <td class="fw-bold text-dark">{{ r.phone || r.client_name || '+1 (555) 019-2834' }}</td>
                        <td>
                          <span :class="idx % 2 === 0 ? 'badge bg-primary-subtle text-primary border' : 'badge bg-danger-subtle text-danger border'" class="px-2 py-1 fw-bold" style="font-size: 0.75rem;">
                            ● {{ r.bank_name || (idx % 2 === 0 ? 'Chase National' : 'Wells Auto') }}
                          </span>
                        </td>
                        <td class="small fw-medium text-dark">{{ r.department_names || (idx % 2 === 0 ? 'Retail Recovery' : 'Auto Finance') }}</td>
                        <td>
                          <span v-if="r.opt_in === 'no' || r.whatsapp_opted_out" class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.72rem;">
                            <i class="bi bi-x-circle me-1"></i> Opted Out
                          </span>
                          <span v-else-if="r.whatsapp_opted_in_at" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem;">
                            <i class="bi bi-check-circle me-1"></i> Opted In
                          </span>
                          <span v-else class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="font-size: 0.72rem;">
                            <i class="bi bi-dash-circle me-1"></i> None
                          </span>
                        </td>
                        <td>
                          <span :class="getStatusBadgeClass(r.status)">
                            <i v-if="getStatusBadgeClass(r.status).includes('pending')" class="bi bi-clock-history me-1"></i>
                            <i v-else-if="getStatusBadgeClass(r.status).includes('delivered')" class="bi bi-check2-all me-1"></i>
                            <i v-else-if="getStatusBadgeClass(r.status).includes('failed')" class="bi bi-exclamation-circle me-1"></i>
                            {{ r.status || 'Queued' }}
                          </span>
                        </td>
                        <td class="text-end pe-4">
                          <button
                            class="btn btn-light text-secondary border-0 p-1 px-2 me-1"
                            @click="previewClientMessage(r)"
                            title="Preview Message"
                          >
                            <i class="bi bi-eye"></i>
                          </button>
                          <button
                            class="btn btn-light text-success border-0 p-1 px-2"
                            @click="openClientChat(r)"
                            title="Open Chat"
                          >
                            <i class="bi bi-whatsapp"></i>
                          </button>
                        </td>
                      </tr>
                      <tr v-if="filteredRecipients.length === 0">
                        <td colspan="6" class="text-center text-muted py-5">
                          No recipients found.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Footer Strip -->
              <div class="card-footer bg-white py-3 px-4 d-flex justify-content-between align-items-center border-top">
                <small class="text-muted fw-medium">
                  Showing {{ filteredRecipients.length }} of {{ filteredRecipients.length }} records
                </small>

                <div class="d-flex align-items-center gap-1">
                  <button class="btn btn-sm btn-light border p-1 px-2" disabled>
                    <i class="bi bi-chevron-left"></i>
                  </button>
                  <button class="btn btn-sm btn-light border p-1 px-2" disabled>
                    <i class="bi bi-chevron-right"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Clients Modal (unchanged except already using VueMultiselect) -->
    <div class="modal fade" tabindex="-1" ref="addClientsModalRef">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add Clients to Campaign</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <p class="text-muted small mb-3">
              Select one or more clients to attach to this campaign.
              Use <strong>Select All</strong> to add all clients from the list.
              <br>
              <small>Only shows clients from departments matching this campaign.</small>
            </p>

            <!-- Search and filter -->
            <div class="row mb-3">
              <div class="col-md-6">
                <div class="input-group input-group-sm">
                  <span class="input-group-text">
                    <i class="bi bi-search"></i>
                  </span>
                  <input
                    v-model="clientSearch"
                    type="text"
                    class="form-control"
                    placeholder="Search clients by name, email, phone..."
                    @input="filterClients"
                  />
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-check-inline">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    id="showSelectedOnly"
                    v-model="showSelectedOnly"
                    @change="filterClients"
                  />
                  <label class="form-check-label" for="showSelectedOnly">
                    Show selected only
                  </label>
                </div>
              </div>
            </div>

            <!-- VueMultiselect for client selection -->
            <div class="mb-3">
              <label class="form-label">Select Clients</label>
              <vue-multiselect
                v-model="selectedClients"
                :options="filteredAvailableClients"
                :multiple="true"
                :close-on-select="false"
                :clear-on-select="false"
                placeholder="Type to search clients..."
                label="nameWithDetails"
                track-by="id"
                :searchable="true"
                :allow-empty="true"
                :show-labels="false"
                :loading="loadingClients"
                @search-change="filterClients"
                class="mb-2"
              >
                <template #noResult>No clients found</template>
                <template #noOptions>Type to search clients</template>
                <template #option="{ option }">
                  <div class="client-option">
                    <strong>{{ option.name }}</strong>
                    <div class="small text-muted">
                      <span v-if="option.email">{{ option.email }}</span>
                      <span v-if="option.email && option.phone"> • </span>
                      <span v-if="option.phone">{{ option.phone }}</span>
                    </div>
                    <div class="small">
                      <span
                        v-for="dept in (option.departments || [])"
                        :key="dept.id"
                        class="badge bg-light text-dark border me-1"
                      >
                        {{ dept.name }}
                      </span>
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

              <!-- Selection summary -->
              <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted">
                  <span v-if="selectedClients.length > 0">
                    {{ selectedClients.length }} client(s) selected
                  </span>
                  <span v-else>
                    No clients selected
                  </span>
                  · {{ filteredAvailableClients.length }} available
                </small>
                <div>
                  <button
                    type="button"
                    class="btn btn-link btn-sm p-0 me-2"
                    @click="selectAllFilteredClients"
                    :disabled="filteredAvailableClients.length === 0"
                  >
                    Select all shown
                  </button>
                  <button
                    type="button"
                    class="btn btn-link btn-sm p-0 text-danger"
                    @click="clearSelection"
                  >
                    Clear all
                  </button>
                </div>
              </div>
            </div>

            <!-- Selected clients preview -->
            <div v-if="selectedClients.length > 0" class="border rounded p-3 mb-3">
              <h6 class="mb-2">Selected Clients ({{ selectedClients.length }})</h6>
              <div class="selected-clients-container">
                <div
                  v-for="client in selectedClients"
                  :key="client.id"
                  class="selected-client-item"
                >
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <strong>{{ client.name }}</strong>
                      <div class="small text-muted">
                        {{ client.email || client.phone || 'No contact details' }}
                      </div>
                    </div>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      @click="removeFromSelection(client)"
                    >
                      <i class="bi bi-x"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-bs-dismiss="modal"
              :disabled="addClientsForm.saving"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="saveClientsToCampaign"
              :disabled="addClientsForm.saving || selectedClients.length === 0"
            >
              <span
                v-if="addClientsForm.saving"
                class="spinner-border spinner-border-sm me-1"
              ></span>
              Add {{ selectedClients.length }} Client(s)
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add WhatsApp Template Modal -->
    <div class="modal fade" tabindex="-1" ref="addWhatsappModalRef">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                <i class="bi bi-whatsapp me-1 text-success"></i>
                Add WhatsApp Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-lg-8 col-md-7 border-end pe-4">

                <p class="text-muted small">
                  Choose whether to send a WhatsApp template or a saved WhatsApp Flow. Leave recipients empty to target all campaign clients.
                </p>

                <!-- Mode toggle -->
                <div class="mb-3">
                  <div class="btn-group btn-group-sm" role="group">
                    <button
                      type="button"
                      class="btn"
                      :class="whatsappForm.mode === 'template' ? 'btn-success' : 'btn-outline-success'"
                      @click="whatsappForm.mode = 'template'"
                    >
                      <i class="bi bi-stack me-1"></i>
                      Template
                    </button>
                    <button
                      type="button"
                      class="btn"
                      :class="whatsappForm.mode === 'flow' ? 'btn-success' : 'btn-outline-success'"
                      @click="whatsappForm.mode = 'flow'"
                    >
                      <i class="bi bi-diagram-3 me-1"></i>
                      Flow
                    </button>
                  </div>
                </div>

                <!-- Template select -->
                <div class="mb-3" v-if="whatsappForm.mode === 'template'">
                  <label class="form-label">WhatsApp Template</label>
                  <select
                    v-model="whatsappForm.templateId"
                    @change="handleWhatsappTemplateChange"
                    class="form-select"
                  >
                    <option value="">-- Select a template --</option>
                    <option
                      v-for="t in whatsappTemplates"
                      :key="t.id"
                      :value="t.id"
                    >
                      {{ t.name }} ({{ t.language }}) – {{ t.category }}
                      <span v-if="t.media_urls && t.media_urls.length"> 📷</span>
                    </option>
                  </select>
                  <small class="text-muted">
                    Templates are synced from your connected WhatsApp account.
                  </small>
                </div>
                <!-- Open full preview / configure page -->
                <div class="mb-3" v-if="whatsappForm.mode === 'template'">
                  <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    @click="goToWhatsappTemplatePreview"
                  >
                    <i class="bi bi-eye me-1"></i>
                    Preview / Configure Template
                  </button>
                </div>

                <!-- Flow select -->
                <div class="mb-3" v-if="whatsappForm.mode === 'flow'">
                  <label class="form-label">WhatsApp Flow</label>
                  <select
                    v-model="whatsappForm.flowId"
                    class="form-select"
                  >
                    <option value="">-- Select a flow --</option>
                    <option
                      v-for="f in whatsappFlows"
                      :key="f.id"
                      :value="f.id"
                    >
                      {{ f.name }} ({{ f.status || 'active' }})
                    </option>
                  </select>
                  <small class="text-muted">
                    Saved flows with branching Yes/No logic.
                  </small>
                </div>
                <div class="mb-3" v-if="whatsappForm.mode === 'flow' && currentWhatsappFlow">
                  <div class="card border-info">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                      <strong>Flow Preview</strong>
                      <small class="text-muted">{{ currentWhatsappFlow.template_name || 'Flow steps' }}</small>
                    </div>
                    <div class="card-body">
                      <ol class="small mb-0">
                        <li v-for="(step, idx) in (currentWhatsappFlow.flow_definition || [])" :key="idx">
                          <strong>{{ step.label || step.id }}</strong>
                          <div class="text-muted">{{ step.message }}</div>
                        </li>
                      </ol>
                    </div>
                  </div>
                  <div class="mt-2">
                    <router-link class="btn btn-outline-secondary btn-sm" :to="{ name: 'whatsapp-flows' }">
                      <i class="bi bi-diagram-3 me-1"></i> Open Flows Page
                    </router-link>
                  </div>
                </div>

                <!-- Recipients via vue-multiselect -->
                <div class="mb-3">
                <label class="form-label">Recipients (Campaign Clients)</label>
                <vue-multiselect
                    v-model="whatsappForm.selectedClients"
                    :options="campaignClientOptions"
                    :multiple="true"
                    :close-on-select="false"
                    :clear-on-select="false"
                    placeholder="Select campaign clients (leave empty for ALL)"
                    label="nameWithDetails"
                    track-by="id"
                    :searchable="true"
                    :show-labels="false"
                    class="mb-1"
                >
                    <template #noResult>No clients found</template>
                    <template #noOptions>No clients loaded</template>
                    <template #option="{ option }">
                    <div class="client-option">
                        <strong>{{ option.name }}</strong>
                        <div class="small text-muted">
                        <span v-if="option.email">{{ option.email }}</span>
                        <span v-if="option.email && option.phone"> • </span>
                        <span v-if="option.phone">{{ option.phone }}</span>
                        </div>
                        <div class="small">
                        <span
                            v-for="dept in (option.departments || [])"
                            :key="dept.id"
                            class="badge bg-light text-dark border me-1"
                        >
                            {{ dept.name }}
                        </span>
                        </div>
                    </div>
                    </template>
                </vue-multiselect>

                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">
                    Leave empty to send to <strong>all {{ clients.length }}</strong> campaign clients.
                    </small>
                    <div>
                    <button
                        type="button"
                        class="btn btn-link btn-sm p-0 me-2"
                        @click="selectAllCampaignClients('whatsapp')"
                        :disabled="campaignClientOptions.length === 0"
                    >
                        Select all
                    </button>
                    <button
                        type="button"
                        class="btn btn-link btn-sm p-0 text-danger"
                        @click="clearCampaignClientSelection('whatsapp')"
                    >
                        Clear
                    </button>
                    </div>
                </div>
                </div>

                <!-- Options -->
                <div class="row">
                <div class="col-md-6">
                    <div class="form-check mb-2">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="waTrackResponses"
                        v-model="whatsappForm.trackResponses"
                    />
                    <label class="form-check-label" for="waTrackResponses">
                        Track client responses (Yes/No flow)
                    </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-2">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="waEnableLiveChat"
                        v-model="whatsappForm.enableLiveChat"
                    />
                    <label class="form-check-label" for="waEnableLiveChat">
                        Offer live chat with agent on reply
                    </label>
                    </div>
                </div>
                </div>

            
                </div>
                <div class="col-lg-4 col-md-5 ps-4">
<!-- Template preview -->
                <div v-if="whatsappForm.mode === 'template' && currentWhatsappTemplate" class="mb-3">
                  <div class="card border-success shadow-sm">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-white border-bottom-0">
                      <strong>Template Preview</strong>
                      <div>
                        <div class="form-check form-switch d-inline-block mb-0">
                          <input class="form-check-input" type="checkbox" id="sampleDataToggle" v-model="whatsappForm.showSamplePreview">
                          <label class="form-check-label small text-muted ms-1" for="sampleDataToggle">Preview with sample data</label>
                        </div>
                      </div>
                    </div>
                    <div class="card-body p-0 d-flex flex-column" style="background-color: #e5ddd5; position: relative;">
                      <!-- WhatsApp Header -->
                      <div class="bg-white d-flex align-items-center px-3 py-2 shadow-sm position-relative" style="z-index: 2;">
                        <i class="bi bi-arrow-left me-3 text-secondary"></i>
                        <div class="bg-secondary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                          <i class="bi bi-person-fill text-secondary fs-4"></i>
                        </div>
                        <div class="lh-1">
                          <div class="fw-bold text-dark d-flex align-items-center gap-1 mb-1" style="font-size: 0.95rem;">
                            {{ campaign?.whatsapp_profile_name || 'Strauss Recovery Solutions' }}
                            <i class="bi bi-patch-check-fill text-success" style="font-size: 0.85rem;" title="Official business account"></i>
                          </div>
                          <div class="text-muted" style="font-size: 0.75rem;">{{ campaign?.whatsapp_from || '+27 82 123 4567' }}</div>
                        </div>
                      </div>

                      <div class="p-4 flex-grow-1 position-relative">
                        <div style="opacity: 0.05; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cGF0aCBkPSJNMCAwaDQwMHY0MDBIMHoiIGZpbGw9Im5vbmUiLz48Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjM1IiBmaWxsPSIjMDAwIi8+PC9zdmc+'); background-size: 200px; pointer-events: none;"></div>
                        
                        <div class="bg-white rounded position-relative shadow-sm" style="max-width: 85%; border-top-left-radius: 0 !important; padding: 0.5rem; margin-left: 10px; z-index: 1;">
                        <svg viewBox="0 0 8 13" width="8" height="13" style="position: absolute; top: 0; left: -8px; color: white;">
                          <path opacity="1" fill="currentColor" d="M1.533 3.568 8 12.193V1H2.812C1.042 1 .474 2.156 1.533 3.568z"></path>
                        </svg>

                        <div
                          v-if="
                            currentWhatsappTemplate.media_urls &&
                            currentWhatsappTemplate.media_urls.length
                          "
                          class="mb-2"
                        >
                          <img
                            v-if="currentWhatsappTemplate.header_format === 'IMAGE'"
                            :src="currentWhatsappTemplate.media_urls[0]"
                            alt="WhatsApp template media"
                            class="img-fluid rounded"
                            style="width: 100%; object-fit: cover;"
                          />
                          <video
                            v-else-if="currentWhatsappTemplate.header_format === 'VIDEO'"
                            :src="currentWhatsappTemplate.media_urls[0]"
                            class="img-fluid rounded"
                            style="width: 100%; object-fit: cover;"
                            controls
                            preload="metadata"
                          ></video>
                          <div
                            v-else-if="currentWhatsappTemplate.header_format === 'DOCUMENT'"
                            class="border rounded p-3 bg-light text-center"
                          >
                            <i class="bi bi-file-earmark-arrow-down fs-3 d-block text-secondary"></i>
                          </div>
                        </div>

                        <div v-if="currentWhatsappTemplate.header_text" class="fw-bold text-dark mb-1" style="font-size: 0.95rem; line-height: 1.3;">
                          {{ previewHeaderText }}
                        </div>

                        <div class="text-dark" style="font-size: 0.9rem; line-height: 1.4; white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">{{ previewBodyText }}<span class="d-inline-block" style="width: 40px;"></span></div>
                        
                        <div class="d-flex justify-content-between align-items-end mt-1">
                            <div class="text-muted" style="font-size: 0.75rem;">
                                {{ currentWhatsappTemplate.footer_text || '' }}
                            </div>
                            <div class="text-muted text-end" style="font-size: 0.65rem; margin-top: -15px; margin-right: 4px;">
                                09:31
                            </div>
                        </div>

                      </div>
                      
                      <div
                        v-if="currentWhatsappTemplate.buttons && currentWhatsappTemplate.buttons.length"
                        class="mt-1 d-flex flex-column gap-1"
                        style="max-width: 85%; margin-left: 10px; z-index: 1; position: relative;"
                      >
                        <div
                          v-for="(button, idx) in currentWhatsappTemplate.buttons"
                          :key="idx"
                          class="bg-white rounded shadow-sm text-center py-2 fw-semibold cursor-pointer hover-shadow transition"
                          style="color: #00a884; font-size: 0.9rem; border: 1px solid rgba(0,0,0,0.05);"
                        >
                          <i v-if="button.type === 'QUICK_REPLY'" class="bi bi-reply-fill me-1"></i>
                          <i v-if="button.type === 'URL'" class="bi bi-box-arrow-up-right me-1"></i>
                          <i v-if="button.type === 'PHONE_NUMBER'" class="bi bi-telephone-fill me-1"></i>
                          {{ button.text || 'Button' }}
                        </div>
                      </div>
                      </div>
                    </div>
                  </div>
                </div>
                
<!-- Template variables mapping -->
                <div v-if="whatsappForm.mode === 'template' && currentWhatsappTemplate && Object.keys(currentWhatsappTemplate.variables || {}).length > 0" class="mb-3">
                  <label class="form-label d-flex align-items-center">
                    Template Variables
                    <span class="badge bg-secondary ms-2" title="Map these template variables to client data">
                      {{ Object.keys(currentWhatsappTemplate.variables).length }} Variable(s)
                    </span>
                  </label>
                  <div class="card bg-light shadow-sm border-0 rounded-3">
                    <div class="card-body p-3">
                      <div class="row g-3">
                        <div
                          v-for="(val, key) in currentWhatsappTemplate.variables"
                          :key="key"
                          class="col-12"
                        >
                          <div class="d-flex align-items-start gap-2">
                            <div class="pt-1" style="min-width: 50px;">
                              <span class="badge bg-primary bg-gradient rounded-pill px-2 py-1 shadow-sm w-100 text-center">
                                {{ '{' + '{' + key + '}' + '}' }}
                              </span>
                            </div>
                            <div class="flex-grow-1">
                              <select
                                v-model="getTemplateVariable(key).source"
                                class="form-select form-select-sm shadow-sm border-0"
                                style="max-width: 320px; background-color: #ffffff;"
                              >
                                <option value="">-- Select mapping --</option>
                                <option value="client.title">Client Title</option>
                                <option value="client.first_name">Client First Name</option>
                                <option value="client.surname">Client Surname</option>
                                <option value="client.phone">Client Phone</option>
                                <option value="client.email">Client Email</option>
                                <option value="client.id_number">Client ID Number</option>
                                <option value="client.account_number">Client Account Number</option>
                                <option value="client.easy_pay_number">Client Easy Pay Number</option>
                                <option value="client.bank_name">Client Bank</option>
                                <option value="client.branch_code">Client Branch Code</option>
                                <option value="client.outstanding_balance">Client Outstanding Balance</option>
                                <option value="client.arrears_amount">Client Arrears Amount</option>
                                <option value="client.settlement_amount">Client Settlement Amount</option>
                                <option value="client.three_months_amount">Client 3 Months Amount</option>
                                <option value="client.installment_amount">Client Installment Amount</option>
                                <option value="campaign.name">Campaign Name</option>
                                <option value="campaign.status">Campaign Status</option>
                                <option value="custom">Custom Value...</option>
                              </select>
                              <div v-if="getTemplateVariable(key).source === 'custom'" class="mt-2 position-relative" style="max-width: 320px;">
                                <input
                                  type="text"
                                  v-model="getTemplateVariable(key).custom_value"
                                  class="form-control form-control-sm border-primary shadow-sm pe-4"
                                  :placeholder="'Enter static text for {' + '{' + key + '}' + '}'"
                                />
                                <i class="bi bi-pencil-square position-absolute top-50 end-0 translate-middle-y me-2 text-primary" style="pointer-events: none;"></i>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                
                </div>
              </div>
</div>
            <div class="modal-footer">
                <button
                type="button"
                class="btn btn-outline-secondary"
                data-bs-dismiss="modal"
                :disabled="whatsappForm.sending"
                >
                Cancel
                </button>
                 <!-- Save only (do not send yet) -->
                <button
                    type="button"
                    class="btn btn-outline-success"
                    @click="saveWhatsappTemplate(false)"
                    :disabled="whatsappForm.sending || !canSubmitWhatsapp"
                >
                    <span
                    v-if="whatsappForm.sending && whatsappForm.action === 'save'"
                    class="spinner-border spinner-border-sm me-1"
                    ></span>
                    <i v-else class="bi bi-save me-1"></i>
                    Save Only
                </button>
                <!-- Queue WhatsApp send immediately -->
                <button
                    type="button"
                    class="btn btn-success"
                    @click="saveWhatsappTemplate(true)"
                    :disabled="whatsappForm.sending || !canSubmitWhatsapp"
                >
                    <span
                    v-if="whatsappForm.sending && whatsappForm.action === 'queue'"
                    class="spinner-border spinner-border-sm me-1"
                    ></span>
                    <i v-else class="bi bi-send-check me-1"></i>
                    Queue WhatsApp Send
                </button>
            </div>
            </div>
        </div>
    </div>


    <!-- Add Email Template Modal -->
    <div class="modal fade" tabindex="-1" ref="addEmailModalRef">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-envelope-paper me-1 text-primary"></i>
              Add Email Template
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small">
              Create a new email or reuse one of your existing templates, then choose
              which campaign clients to send it to.
            </p>

            <!-- Mode toggle -->
            <div class="mb-3">
              <div class="btn-group btn-group-sm" role="group">
                <button
                  type="button"
                  class="btn"
                  :class="emailForm.mode === 'new' ? 'btn-primary' : 'btn-outline-primary'"
                  @click="emailForm.mode = 'new'"
                >
                  <i class="bi bi-pencil-square me-1"></i>
                  New Email
                </button>
                <button
                  type="button"
                  class="btn"
                  :class="emailForm.mode === 'template' ? 'btn-primary' : 'btn-outline-primary'"
                  @click="emailForm.mode = 'template'"
                >
                  <i class="bi bi-stack me-1"></i>
                  Existing Template
                </button>
              </div>
            </div>

            <!-- New email form -->
            <div v-if="emailForm.mode === 'new'">
              <div class="mb-3">
                <label class="form-label">Subject</label>
                <input
                  v-model="emailForm.subject"
                  type="text"
                  class="form-control"
                  placeholder="Subject line..."
                />
              </div>
              <div class="mb-3">
                <label class="form-label">Body</label>
                <textarea
                  v-model="emailForm.body"
                  class="form-control"
                  rows="6"
                  placeholder="Write your email message here..."
                ></textarea>
              </div>
            </div>

            <!-- Existing template select -->
            <div v-else>
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Email Template</label>
                    <select
                      v-model="emailForm.templateId"
                      class="form-select"
                    >
                      <option value="">-- Select a template --</option>
                      <option
                        v-for="t in emailTemplates"
                        :key="t.id"
                        :value="t.id"
                      >
                        {{ t.name }} – {{ t.subject }}
                      </option>
                    </select>
                    <small class="text-muted">
                      Saved templates from previous campaigns.
                    </small>
                  </div>
                </div>
                <div class="col-md-6" v-if="currentEmailTemplate">
                  <div class="card">
                    <div class="card-header py-2">
                      <strong>Template Preview</strong>
                    </div>
                    <div class="card-body">
                      <div class="mb-2">
                        <strong>Subject:</strong>
                        {{ currentEmailTemplate.subject }}
                      </div>
                      <div class="border rounded p-2 small bg-light">
                        <pre class="mb-0">{{ currentEmailTemplate.body_preview }}</pre>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <hr />

            <!-- Client selection -->
            <div class="mb-3">
              <label class="form-label">Recipients</label>
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="radio"
                  id="emailClientsAll"
                  value="all"
                  v-model="emailForm.clientsMode"
                />
                <label class="form-check-label" for="emailClientsAll">
                  All clients in this campaign ({{ clients.length }})
                </label>
              </div>
              <div class="form-check mb-2">
                <input
                  class="form-check-input"
                  type="radio"
                  id="emailClientsSelected"
                  value="selected"
                  v-model="emailForm.clientsMode"
                />
                <label class="form-check-label" for="emailClientsSelected">
                  Selected clients only
                </label>
              </div>

              <div v-if="emailForm.clientsMode === 'selected'">
                <select
                  class="form-select"
                  multiple
                  size="8"
                  v-model="emailForm.selectedClientIds"
                >
                  <option
                    v-for="c in clients"
                    :key="c.id"
                    :value="c.id"
                  >
                    {{ c.name }} – {{ c.email || c.phone || 'No contact' }}
                  </option>
                </select>
                <small class="text-muted">
                  Hold <kbd>Ctrl</kbd> / <kbd>Cmd</kbd> to select multiple.
                </small>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-bs-dismiss="modal"
              :disabled="emailForm.sending"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="saveEmailTemplate"
              :disabled="emailForm.sending || !emailFormIsValid"
            >
              <span
                v-if="emailForm.sending"
                class="spinner-border spinner-border-sm me-1"
              ></span>
              <i v-else class="bi bi-send-check me-1"></i>
              Queue Email Send
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add SMS Template Modal -->
    <div class="modal fade" tabindex="-1" ref="addSmsModalRef">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-chat-left-text me-1 text-info"></i>
              Add SMS Template
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small">
              Compose a simple SMS with <strong>subject</strong> and <strong>message</strong>.
              No templates are used for SMS.
            </p>

            <div class="mb-3">
              <label class="form-label">Subject (internal label only)</label>
              <input
                v-model="smsForm.subject"
                type="text"
                class="form-control"
                placeholder="End of year party reminder..."
              />
            </div>

            <div class="mb-3">
              <label class="form-label">Message</label>
              <textarea
                v-model="smsForm.text"
                class="form-control"
                rows="4"
                placeholder="Your SMS text (160 characters per segment)..."
              ></textarea>
              <small class="text-muted">
                Avoid very long messages; SMS is billed per segment.
              </small>
            </div>

            <!-- Client selection -->
            <div class="mb-3">
              <label class="form-label">Recipients</label>
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="radio"
                  id="smsClientsAll"
                  value="all"
                  v-model="smsForm.clientsMode"
                />
                <label class="form-check-label" for="smsClientsAll">
                  All clients in this campaign ({{ clients.length }})
                </label>
              </div>
              <div class="form-check mb-2">
                <input
                  class="form-check-input"
                  type="radio"
                  id="smsClientsSelected"
                  value="selected"
                  v-model="smsForm.clientsMode"
                />
                <label class="form-check-label" for="smsClientsSelected">
                  Selected clients only
                </label>
              </div>

              <div v-if="smsForm.clientsMode === 'selected'">
                <select
                  class="form-select"
                  multiple
                  size="8"
                  v-model="smsForm.selectedClientIds"
                >
                  <option
                    v-for="c in clients"
                    :key="c.id"
                    :value="c.id"
                  >
                    {{ c.name }} – {{ c.phone || 'No phone' }}
                  </option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-bs-dismiss="modal"
              :disabled="smsForm.sending"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-info text-white"
              @click="saveSmsTemplate"
              :disabled="smsForm.sending || !smsFormIsValid"
            >
              <span
                v-if="smsForm.sending"
                class="spinner-border spinner-border-sm me-1"
              ></span>
              <i v-else class="bi bi-send-check me-1"></i>
              Queue SMS Send
            </button>
          </div>
        </div>
      </div>
    </div>

    <ExportRequestModal ref="exportRequestModal" />
    <ConfirmationModal ref="confirmModal" />

    <!-- Recipient Message Preview Modal -->
    <div class="modal fade" id="recipientMessagePreviewModal" tabindex="-1" aria-hidden="true" ref="recipientMessagePreviewModalRef">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-chat-left-text me-2"></i> Message Preview
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0 d-flex flex-column" style="background-color: #e5ddd5; position: relative;">
            <!-- WhatsApp Header -->
            <div class="bg-white d-flex align-items-center px-3 py-2 shadow-sm position-relative" style="z-index: 2;">
              <i class="bi bi-arrow-left me-3 text-secondary"></i>
              <div class="bg-secondary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-person-fill text-secondary fs-4"></i>
              </div>
              <div class="lh-1">
                <div class="fw-bold text-dark d-flex align-items-center gap-1 mb-1" style="font-size: 0.95rem;">
                  {{ campaign?.whatsapp_profile_name || 'Strauss Recovery Solutions' }}
                  <i class="bi bi-patch-check-fill text-success" style="font-size: 0.85rem;" title="Official business account"></i>
                </div>
                <div class="text-muted" style="font-size: 0.75rem;">{{ recipientModal.meta.reply_number || campaign?.whatsapp_from || '+27 82 123 4567' }}</div>
              </div>
            </div>
            
            <div class="p-4 position-relative">
              <div style="opacity: 0.05; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cGF0aCBkPSJNMCAwaDQwMHY0MDBIMHoiIGZpbGw9Im5vbmUiLz48Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjM1IiBmaWxsPSIjMDAwIi8+PC9zdmc+'); background-size: 200px; pointer-events: none;"></div>
              
              <div class="bg-white rounded position-relative shadow-sm" style="max-width: 85%; border-top-left-radius: 0 !important; padding: 0.5rem; margin-left: 10px; z-index: 1;">
                <svg viewBox="0 0 8 13" width="8" height="13" style="position: absolute; top: 0; left: -8px; color: white;">
                  <path opacity="1" fill="currentColor" d="M1.533 3.568 8 12.193V1H2.812C1.042 1 .474 2.156 1.533 3.568z"></path>
                </svg>

                <div class="text-dark" style="font-size: 0.9rem; line-height: 1.4; white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">{{ previewRecipientMessage }}<span class="d-inline-block" style="width: 40px;"></span></div>
                
                <div class="d-flex justify-content-between align-items-end mt-1">
                    <div class="text-muted text-end w-100" style="font-size: 0.65rem; margin-top: -15px; margin-right: 4px;">
                        {{ previewRecipientTime }}
                    </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import VueMultiselect from 'vue-multiselect';
import ExportRequestModal from '../components/ExportRequestModal.vue';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import { cleanupModalArtifacts as cleanupManagedModalArtifacts, createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: 'CampaignShowView',
  components: {
    VueMultiselect,
    ExportRequestModal,
    ConfirmationModal,
  },
  data() {
    return {
      previewRecipientMessage: '',
      previewRecipientTime: '',
      recipientPreviewModal: null,
      campaign: null,
      stats: {
        total_clients: 0,
        whatsapp_sent: 0,
        email_sent: 0,
        sms_sent: 0,
        delivered: 0,
        failed: 0,
        pending: 0,
      },

      // clients in campaign
      clients: [],
      selectedClients: [],

      // channel messages
      whatsappMessages: [],
      emails: [],
      smsMessages: [],
      editingWhatsappMessageId: null,

      // dashboard modal
      recipientsModal: null,
      sendingBatch: false,
      retryingBatch: false,
      recipientModal: {
        title: '',
        channel: '',
        summary: {
          total: 0,
          delivered: 0,
          failed: 0,
          pending: 0,
          replies: 0,
        },
        rows: [],
        agents: [],
        meta: {
          id: null,
          template_name: null,
          subject: null,
          status: null,
          can_send: false,
        },
        filter: '',
      },

      // add clients modal
      addClientsModal: null,
      availableClients: [],
      filteredAvailableClients: [],
      selectedClients: [],
      clientSearch: '',
      showSelectedOnly: false,
      loadingClients: false,
      addClientsForm: {
        saving: false,
      },

      // WhatsApp template modal
      addWhatsappModal: null,
      whatsappModalLoading: false,
      whatsappTemplates: [],
      whatsappFlows: [],
      whatsappForm: {
        mode: 'template', // 'template' | 'flow'
        templateId: '',
        flowId: '',
        templateVariables: {},
        clientsMode: 'all',
        selectedClients: [],
        trackResponses: false,
        enableLiveChat: false,
        showSamplePreview: false,
        sending: false,
      },

      // Email template modal
      addEmailModal: null,
      emailTemplates: [],
      emailForm: {
        mode: 'new', // 'new' | 'template'
        subject: '',
        body: '',
        templateId: '',
        clientsMode: 'all',
        selectedClients: [],
        sending: false,
      },

      // SMS template modal
      addSmsModal: null,
      smsForm: {
        subject: '',
        text: '',
        clientsMode: 'all',
        selectedClients: [],
        sending: false,
      },
    };
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
    canManageCampaign() {
      if (!this.currentUser) return false;
      return this.hasPermission('edit_campaigns') || this.hasPermission('create_campaigns');
    },
    previewHeaderText() {
        if (!this.currentWhatsappTemplate || !this.currentWhatsappTemplate.header_text) return '';
        let text = this.currentWhatsappTemplate.header_text;
        if (!this.whatsappForm.showSamplePreview) return text;
        return text.replace(/{{(\d+)}}/g, (match, p1) => {
            const key = `header_${p1}`;
            return this.resolveSampleVariable(key);
        });
    },
    previewBodyText() {
        if (!this.currentWhatsappTemplate || !this.currentWhatsappTemplate.body_preview) return '';
        let text = this.currentWhatsappTemplate.body_preview;
        if (!this.whatsappForm.showSamplePreview) return text;
        return text.replace(/{{(\d+)}}/g, (match, p1) => {
            const key = `body_${p1}`;
            return this.resolveSampleVariable(key);
        });
    },
    goToWhatsappTemplatePreview() {
        if (!this.whatsappForm.templateId) {
            notify.warning('Please select a WhatsApp template first.', 'Campaigns');
            return;
        }

        const campaignId = this.$route.params.id;

        // Close modal visually if needed
        if (this.addWhatsappModal) {
            this.addWhatsappModal.hide();
        }

        // Navigate to dedicated preview page
        this.$router.push({
            name: 'WhatsappTemplatePreview',
            params: {
            id: campaignId,
            templateSid: this.whatsappForm.templateId,
            },
        });
    },

    overviewCards() {
        const cards = [
            {
            label: 'Clients',
            value: this.stats.total_clients || 0,
            subtitle: 'Total clients in this campaign',
            icon: 'bi bi-people-fill',
            show: true,
            },
            {
            label: 'WhatsApp',
            value: this.stats.whatsapp_sent || 0,
            subtitle: 'Messages sent via WhatsApp',
            icon: 'bi bi-whatsapp text-success',
            show: this.channels.whatsapp,
            },
            {
            label: 'Email',
            value: this.stats.email_sent || 0,
            subtitle: 'Messages sent via email',
            icon: 'bi bi-envelope-paper text-primary',
            show: this.channels.email,
            },
            {
            label: 'SMS',
            value: this.stats.sms_sent || 0,
            subtitle: 'Messages sent via ZoomConnect',
            icon: 'bi bi-chat-left-text text-info',
            show: this.channels.sms,
            },
        ];
        return cards.filter(c => c.show);
    },

    totalWhatsappQueued() {
      if (this.stats && (this.stats.whatsapp_sent !== undefined && this.stats.whatsapp_sent !== null)) {
        return this.stats.whatsapp_sent;
      }
      return (this.whatsappMessages || []).reduce((acc, w) => acc + (Number(w.total) || 0), 0);
    },

    canSend() {
      if (!this.canManageCampaign || !this.campaign) return false;
      return ['Draft', 'Scheduled', 'Active'].includes(this.campaign.status);
    },
    recipientSummaryCards() {
      const s = this.recipientModal.summary || {};
      const ch = this.recipientModal.channel;
      if (ch === 'Email') {
        return [
          { label: 'Total', value: s.total || 0, icon: 'bi bi-collection text-primary', color: 'primary' },
          { label: 'Delivered', value: s.delivered || 0, icon: 'bi bi-check-circle-fill text-success', color: 'success' },
          { label: 'Bounced', value: s.bounced || 0, icon: 'bi bi-exclamation-triangle-fill text-danger', color: 'danger' },
          { label: 'Opened', value: s.opened || 0, icon: 'bi bi-envelope-open-fill text-info', color: 'info' },
          { label: 'Clicked', value: s.clicked || 0, icon: 'bi bi-cursor-fill text-warning', color: 'warning' },
        ];
      }
      if (ch === 'SMS') {
        return [
          { label: 'Total', value: s.total || 0, icon: 'bi bi-collection text-primary', color: 'primary' },
          { label: 'Delivered', value: s.delivered || 0, icon: 'bi bi-check-circle-fill text-success', color: 'success' },
          { label: 'Failed', value: s.failed || 0, icon: 'bi bi-x-circle-fill text-danger', color: 'danger' },
          { label: 'Pending', value: s.pending || 0, icon: 'bi bi-hourglass-split text-warning', color: 'warning' },
        ];
      }
      // WhatsApp
      const cards = [
        { label: 'Total', value: s.total || 0, icon: 'bi bi-collection text-primary', color: 'primary' },
        { label: 'Delivered', value: s.delivered || 0, icon: 'bi bi-check-circle-fill text-success', color: 'success' },
        { label: 'Failed', value: s.failed || 0, icon: 'bi bi-x-circle-fill text-danger', color: 'danger' },
        { label: 'Pending', value: s.pending || 0, icon: 'bi bi-hourglass-split text-warning', color: 'warning' },
        { label: 'Replies', value: s.replies || 0, icon: 'bi bi-chat-dots-fill text-info', color: 'info' },
      ];
      if (s.yes_count !== undefined || s.no_count !== undefined) {
        cards.push({ label: 'Yes Replies', value: s.yes_count || 0, icon: 'bi bi-hand-thumbs-up-fill text-success', color: 'success' });
        cards.push({ label: 'No Replies', value: s.no_count || 0, icon: 'bi bi-hand-thumbs-down-fill text-danger', color: 'danger' });
      }
      if (s.delivery_rate !== undefined) {
        cards.push({ label: 'Delivery Rate', value: `${s.delivery_rate || 0}%`, icon: 'bi bi-graph-up-arrow text-primary', color: 'primary' });
      }
      return cards;
    },
    selectAllClients() {
      return this.clients.length > 0 && this.selectedClients.length === this.clients.length;
    },
    filteredRecipients() {
      const rows = this.recipientModal.rows || [];
      const q = (this.recipientModal.filter || '').trim().toLowerCase();
      if (!q) return rows;
      return rows.filter((r) => {
        return [
          r.client_name,
          r.email,
          r.phone,
          r.department_names,
          r.status,
        ]
          .filter(Boolean)
          .some((val) => String(val).toLowerCase().includes(q));
      });
    },
    currentWhatsappTemplate() {
        if (!this.whatsappForm.templateId) return null;
        return this.whatsappTemplates.find(
            (t) => t.id === this.whatsappForm.templateId
        ) || null;
    },
    currentWhatsappFlow() {
      if (!this.whatsappForm.flowId) return null;
      return this.whatsappFlows.find((f) => f.id === this.whatsappForm.flowId) || null;
    },
    canSubmitWhatsapp() {
      if (!this.canManageCampaign) return false;

      if (this.whatsappForm.mode === 'template') {
        return !!this.whatsappForm.templateId;
      }
      if (this.whatsappForm.mode === 'flow') {
        return !!this.whatsappForm.flowId;
      }
      return false;
    },
    currentEmailTemplate() {
      return this.emailTemplates.find(t => t.id === this.emailForm.templateId) || null;
    },
    campaignClientOptions() {
        return (this.clients || []).map((c) => ({
        ...c,
        nameWithDetails: `${c.name} (${c.email || c.phone || 'No contact details'})`,
        }));
    },
    channels() {
      const arr = this.campaign?.channels || [];
      const lower = Array.isArray(arr) ? arr.map((c) => String(c).toLowerCase()) : [];
      return {
        whatsapp: lower.includes('whatsapp'),
        email: lower.includes('email'),
        sms: lower.includes('sms'),
      };
    },
    emailFormIsValid() {
        if (this.emailForm.mode === 'new') {
        return !!this.emailForm.subject && !!this.emailForm.body;
        }
        // template mode
        return !!this.emailForm.templateId;
    },
    smsFormIsValid() {
        return !!this.smsForm.subject && !!this.smsForm.text;
    },
  },
  mounted() {
    this.recipientsModal = createManagedModal(this.$refs.recipientsModalRef);
    this.addClientsModal = createManagedModal(this.$refs.addClientsModalRef);
    this.addWhatsappModal = createManagedModal(this.$refs.addWhatsappModalRef);
    this.addEmailModal = createManagedModal(this.$refs.addEmailModalRef);
    this.addSmsModal = createManagedModal(this.$refs.addSmsModalRef);
    this.recipientPreviewModal = createManagedModal(this.$refs.recipientMessagePreviewModalRef);
    this.refreshAll();
  },
  beforeUnmount() {
    disposeManagedModal(this.recipientsModal);
    disposeManagedModal(this.addClientsModal);
    disposeManagedModal(this.addWhatsappModal);
    disposeManagedModal(this.addEmailModal);
    disposeManagedModal(this.addSmsModal);
    disposeManagedModal(this.recipientPreviewModal);
    cleanupManagedModalArtifacts(true);
  },
  methods: {
    hasPermission(permCode) {
      if (!this.currentUser) return false;
      const roles = Array.isArray(this.currentUser.role_codes) && this.currentUser.role_codes.length
        ? this.currentUser.role_codes
        : [this.currentUser?.role].filter(Boolean);

      if (roles.includes('SUPER_ADMIN') || roles.includes('ADMIN')) {
        return true;
      }
      if (Array.isArray(this.currentUser.permission_codes)) {
        return this.currentUser.permission_codes.includes(permCode);
      }
      return false;
    },
    toggleSelectAllClients(event) {
      if (event.target.checked) {
        this.selectedClients = this.clients.map(c => c.id);
      } else {
        this.selectedClients = [];
      }
    },
    removeClient(client) {
      if(confirm(`Are you sure you want to remove ${client.name} from this campaign?`)) {
        this.clients = this.clients.filter(c => c.id !== client.id);
        this.selectedClients = this.selectedClients.filter(id => id !== client.id);
      }
    },
    removeSelectedClients() {
      if(confirm(`Are you sure you want to remove ${this.selectedClients.length} clients from this campaign?`)) {
        this.clients = this.clients.filter(c => !this.selectedClients.includes(c.id));
        this.selectedClients = [];
      }
    },
    viewClient(client) {
      this.$router.push({ name: 'clients', query: { id: client.id } });
    },
    handleWhatsappTemplateChange() {
      // Avoid overwriting if we are editing and haven't actually changed the template
      const currentVars = this.whatsappForm.templateVariables || {};
      this.whatsappForm.templateVariables = { ...currentVars };

      const tpl = this.currentWhatsappTemplate;
      if (tpl && tpl.variables) {
        Object.keys(tpl.variables).forEach((key) => {
          if (!this.whatsappForm.templateVariables[key]) {
            this.whatsappForm.templateVariables[key] = { source: '', custom_value: '' };
          }
        });
      }
    },
    getTemplateVariable(key) {
      if (!this.whatsappForm.templateVariables[key]) {
        this.whatsappForm.templateVariables[key] = { source: '', custom_value: '' };
      }
      return this.whatsappForm.templateVariables[key];
    },
    resolveSampleVariable(key) {
      const mapping = this.whatsappForm.templateVariables[key];
      if (!mapping || !mapping.source) return `{{${key}}}`;
      
      const source = mapping.source;
      if (source === 'custom') return mapping.custom_value || `{{${key}}}`;
      
      const client = this.clients[0];
      if (!client) return `{{${key}}}`;
      
      const parts = source.split('.');
      if (parts[0] === 'client') {
          let val = client[parts[1]];
          if (parts[1] === 'first_name' && !val && client.name) {
              val = client.name.split(' ')[0];
          }
          if (parts[1] === 'surname' && !val && client.name) {
              const nameParts = client.name.split(' ');
              val = nameParts.length > 1 ? nameParts.slice(1).join(' ') : '';
          }
          let finalVal = val !== undefined && val !== null ? String(val) : '';
          return finalVal.trim() === '' ? ' ' : finalVal;
      } else if (parts[0] === 'campaign') {
          let val = this.campaign ? this.campaign[parts[1]] : null;
          let finalVal = val !== undefined && val !== null ? String(val) : '';
          return finalVal.trim() === '' ? ' ' : finalVal;
      }
      return `{{${key}}}`;
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
    statusColor(status) {
      if (!status) return 'bg-light text-dark';
      const s = status.toLowerCase();
      if (s.includes('delivered') || s.includes('sent') || s.includes('success')) return 'bg-success';
      if (s.includes('fail') || s.includes('bounce') || s.includes('error')) return 'bg-danger';
      if (s.includes('pending') || s.includes('queue')) return 'bg-warning text-dark';
      if (s.includes('draft')) return 'bg-secondary';
      return 'bg-secondary';
    },
    whatsappTemplateId(message) {
      if (!message) return null;
      return message.template_sid || message.template_id || message.templateId || message.templateSid || null;
    },
    isFlowSend(message) {
      if (!message) return false;
      return !!(message.whatsapp_flow_id || message.flow_id || message.flowId || message.flow);
    },
    canSendWhatsapp(message) {
      if (!this.canManageCampaign || !message) return false;
      return !message.sent_at;
    },
    whatsappStatus(message) {
      if (!message) return 'Draft';
      if (message.status) return message.status;
      if (!message.sent_at) return 'Draft';
      if (message.pending > 0) return 'Pending';
      if (message.failed > 0 && message.delivered === 0) return 'Failed';
      if (message.delivered > 0 && message.pending === 0) return 'Delivered';
      return 'Sent';
    },
    refreshAll() {
      this.fetchCampaign();
      this.fetchStats();
      this.fetchClients();
      this.fetchWhatsApp();
      this.fetchEmails();
      this.fetchSms();
    },
    fetchCampaign() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}`).then((res) => {
        this.campaign = res.data;
      }).catch((err) => {
        console.error('Failed to fetch campaign:', err);
      });
    },
    fetchStats() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/stats`).then((res) => {
        this.stats = res.data || {};
      }).catch(() => {});
    },
    fetchClients() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/clients`).then((res) => {
        this.clients = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
      }).catch(() => {
        this.clients = [];
      });
    },
    fetchWhatsApp() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/whatsapp-messages`).then((res) => {
        this.whatsappMessages = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
      }).catch(() => {
        this.whatsappMessages = [];
      });
    },
    fetchEmails() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/emails`).then((res) => {
        this.emails = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
      }).catch(() => {
        this.emails = [];
      });
    },
    fetchSms() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/sms-messages`).then((res) => {
        this.smsMessages = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
      }).catch(() => {
        this.smsMessages = [];
      });
    },
    sendNow() {
      if (!this.campaign) return;
      this.$refs.confirmModal.open({
        title: 'Send Campaign Now',
        message: `Send campaign "${this.campaign.name}" now? This will queue all eligible channel batches.`,
        confirmLabel: 'Queue Send',
        confirmVariant: 'primary',
        onConfirm: async () => {
          await axios.post(`/api/campaigns/${this.campaign.id}/send`);
          notify.success('Send job queued.', 'Campaigns');
          this.refreshAll();
        },
      });
    },

    // Mini dashboard
    viewRecipients(channel, sendRow) {
      const id = this.$route.params.id;

      let url = '';
      if (channel === 'WhatsApp') {
        url = `/api/campaigns/${id}/whatsapp-messages/${sendRow.id}/recipients`;
      } else if (channel === 'Email') {
        url = `/api/campaigns/${id}/emails/${sendRow.id}/recipients`;
      } else if (channel === 'SMS') {
        url = `/api/campaigns/${id}/sms-messages/${sendRow.id}/recipients`;
      }

      this.recipientModal.title = `${channel} Batch – ${sendRow.sent_at || 'Not yet sent'}`;
      this.recipientModal.channel = channel;
      this.recipientModal.summary = {
        total: sendRow.total || 0,
        delivered: sendRow.delivered || 0,
        failed: sendRow.failed || 0,
        pending: sendRow.pending || 0,
        replies: sendRow.replies_count || sendRow.replies || 0,
      };
      this.recipientModal.rows = [];
      this.recipientModal.agents = [];
      this.recipientModal.meta = {
        id: sendRow.id,
        template_name: sendRow.template_name || null,
        preview_body: sendRow.preview_body || null,
        subject: sendRow.subject || null,
        status: sendRow.status || 'Sent',
        can_send: !!sendRow.can_send,
        reply_number: sendRow.whatsapp_from || sendRow.from || this.campaign?.whatsapp_from || null,
      };

      axios.get(url).then((res) => {
        if (res.data.summary) {
          this.recipientModal.summary = Object.assign({ replies: 0 }, res.data.summary);
        }
        this.recipientModal.rows = res.data.recipients || [];
        this.recipientModal.agents = res.data.agents || [];
        if (res.data.meta) {
          this.recipientModal.meta = Object.assign({}, this.recipientModal.meta, res.data.meta);
        }
        // Derive replies from rows if not provided
        const replyCount = (this.recipientModal.rows || []).filter((r) => r.last_response || r.response || r.reply).length;
        if (!this.recipientModal.summary || typeof this.recipientModal.summary.replies === 'undefined') {
          this.recipientModal.summary = Object.assign({}, this.recipientModal.summary, { replies: replyCount });
        } else {
          this.recipientModal.summary.replies = this.recipientModal.summary.replies || replyCount;
        }
        this.recipientsModal.show();
      });
    },
    agentPercent(agent) {
      const total = (this.recipientModal.agents || []).reduce((sum, a) => sum + (a.count || 0), 0);
      if (!total) return 0;
      return Math.round(((agent.count || 0) / total) * 100);
    },
    sendBatchNow() {
      if (!this.recipientModal.meta || !this.recipientModal.meta.id) return;
      const id = this.$route.params.id;
      const messageId = this.recipientModal.meta.id;
      let url = '';

      if (this.recipientModal.channel === 'WhatsApp') {
        url = `/api/campaigns/${id}/whatsapp-messages/${messageId}/send`;
      } else if (this.recipientModal.channel === 'Email') {
        url = `/api/campaigns/${id}/emails/${messageId}/send`;
      } else if (this.recipientModal.channel === 'SMS') {
        url = `/api/campaigns/${id}/sms-messages/${messageId}/send`;
      }

      this.sendingBatch = true;
      axios.post(url).then(() => {
        notify.success('Send job queued for this batch.', 'Campaigns');
        this.fetchStats();
      }).finally(() => {
        this.sendingBatch = false;
      });
    },
    retryFailedBatch() {
      if (!this.recipientModal.meta || !this.recipientModal.meta.id) return;
      const id = this.$route.params.id;
      const messageId = this.recipientModal.meta.id;

      this.retryingBatch = true;
      axios.post(`/api/campaigns/${id}/whatsapp-messages/${messageId}/retry-failed`).then(() => {
        notify.success('Retry job queued for failed recipients.', 'Campaigns');
        this.fetchWhatsApp();
        this.fetchStats();
        
        // Refresh the modal content
        const url = `/api/campaigns/${id}/whatsapp-messages/${messageId}/recipients`;
        axios.get(url).then((res) => {
          if (res.data.summary) {
            this.recipientModal.summary = Object.assign({ replies: 0 }, res.data.summary);
          }
          this.recipientModal.rows = res.data.recipients || [];
          this.recipientModal.agents = res.data.agents || [];
          if (res.data.meta) {
            this.recipientModal.meta = Object.assign({}, this.recipientModal.meta, res.data.meta);
          }
        });
      }).catch((error) => {
        console.error('Failed to retry batch:', error);
        notify.error('Failed to retry batch: ' + (error.response?.data?.message || error.message), 'Campaigns');
      }).finally(() => {
        this.retryingBatch = false;
      });
    },
    cleanupModalArtifacts() {
      cleanupManagedModalArtifacts(true);
    },
    closeRecipientsModal(next) {
      const modalEl = this.$refs.recipientsModalRef;
      if (!modalEl || !this.recipientsModal) {
        this.cleanupModalArtifacts();
        next();
        return;
      }

      let completed = false;
      const done = () => {
        if (completed) return;
        completed = true;
        modalEl.removeEventListener('hidden.bs.modal', done);
        this.cleanupModalArtifacts();
        next();
      };

      modalEl.addEventListener('hidden.bs.modal', done, { once: true });
      this.recipientsModal.hide();

      // Fallback in case Bootstrap does not emit hidden before navigation timing changes.
      window.setTimeout(done, 250);
    },
    previewClientMessage(recipient) {
      this.previewRecipientMessage = recipient.resolved_preview_body || recipient.message_body || recipient.body || recipient.message || this.recipientModal.meta.preview_body || this.recipientModal.meta.template_name || 'Template message content not available.';
      const d = new Date();
      this.previewRecipientTime = d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
      
      this.recipientPreviewModal.show();
    },
    openClientChat(recipient) {
      if (!recipient.client_id) return;
      this.closeRecipientsModal(() => {
        this.$router.push({
          name: 'chat',
          query: { client_id: recipient.client_id },
        });
      });
    },
    getStatusBadgeClass(status) {
      if (!status) return 'badge-status-pending text-primary bg-primary-subtle';
      const s = status.toLowerCase();
      if (s === 'delivered' || s === 'sent' || s === 'completed' || s === 'active') return 'badge-status-delivered text-success bg-success-subtle';
      if (s === 'failed' || s === 'error') return 'badge-status-failed text-danger bg-danger-subtle';
      return 'badge-status-pending text-primary bg-primary-subtle';
    },

    // Export helpers
    exportClients() {
      const id = this.$route.params.id;
      this.requestCampaignExport('campaign_clients', id);
    },
    exportWhatsApp() {
      const id = this.$route.params.id;
      this.requestCampaignExport('campaign_whatsapp_messages', id);
    },
    exportEmails() {
      const id = this.$route.params.id;
      this.requestCampaignExport('campaign_emails', id);
    },
    exportSms() {
      const id = this.$route.params.id;
      this.requestCampaignExport('campaign_sms_messages', id);
    },
    requestCampaignExport(dataset, campaignId) {
      const labels = {
        campaign_clients: 'Campaign Clients',
        campaign_whatsapp_messages: 'Campaign WhatsApp Recipients',
        campaign_emails: 'Campaign Email Recipients',
        campaign_sms_messages: 'Campaign SMS Recipients',
      };

      this.$refs.exportRequestModal.open({
        dataset,
        datasetLabel: labels[dataset] || dataset,
        targetType: 'campaign',
        targetId: Number(campaignId),
        filters: {},
        summaryRows: [
          { label: 'Campaign', value: this.campaign?.name || `#${campaignId}` },
          { label: 'Bank Scope', value: this.campaign?.bank?.name || 'Campaign bank' },
          { label: 'Status', value: this.campaign?.status || '-' },
          { label: 'Current Totals', value: `${this.stats.total_clients || 0} clients` },
        ],
        fallbackName: `${dataset}.csv`,
      });
    },

    bulkRemoveClients() {
      if (this.selectedClients.length === 0) return;

      this.$refs.confirmModal.open({
        title: 'Remove Selected Clients',
        message: `Are you sure you want to remove ${this.selectedClients.length} client(s) from this campaign?`,
        confirmText: 'Remove Clients',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
          this.bulkActionLoading = true;
          try {
            await axios.post(`/api/campaigns/${this.$route.params.id}/detach-clients`, {
              client_ids: this.selectedClients,
            });
            notify.success('Clients removed successfully', 'Campaigns');
            this.selectedClients = [];
            this.fetchCampaign();
            this.fetchClients(this.clientsPagination.currentPage);
          } catch (err) {
            notify.error(err.response?.data?.message || 'Failed to remove clients', 'Campaigns');
          } finally {
            this.bulkActionLoading = false;
          }
        },
      });
    },

    // Add Clients
    openAddClientsModal() {
      const id = this.$route.params.id;

      this.selectedClients = [];
      this.clientSearch = '';
      this.showSelectedOnly = false;
      this.addClientsForm.saving = false;
      this.loadingClients = true;

      axios
        .get(`/api/campaigns/${id}/available-clients`)
        .then((res) => {
          const clients = res.data.data || res.data;
          this.availableClients = clients.map(client => ({
            ...client,
            nameWithDetails: `${client.name} (${client.email || client.phone || 'No contact details'})`,
          }));
          this.filteredAvailableClients = [...this.availableClients];
        })
        .catch((error) => {
          console.error('Failed to load available clients:', error);
          this.availableClients = [];
          this.filteredAvailableClients = [];
        })
        .finally(() => {
          this.loadingClients = false;
          this.addClientsModal.show();
        });
    },
    filterClients() {
      let filtered = [];

      if (!this.clientSearch.trim()) {
        filtered = [...this.availableClients];
      } else {
        const search = this.clientSearch.toLowerCase();
        filtered = this.availableClients.filter(client =>
          client.name.toLowerCase().includes(search) ||
          (client.email && client.email.toLowerCase().includes(search)) ||
          (client.phone && client.phone.includes(search)) ||
          (client.departments && client.departments.some(dept =>
            dept.name.toLowerCase().includes(search)
          ))
        );
      }

      if (this.showSelectedOnly) {
        const selectedIds = new Set(this.selectedClients.map(c => c.id));
        filtered = filtered.filter(c => selectedIds.has(c.id));
      }

      this.filteredAvailableClients = filtered;
    },
    selectAllFilteredClients() {
      const selectedIds = new Set(this.selectedClients.map(c => c.id));
      this.filteredAvailableClients.forEach(client => {
        if (!selectedIds.has(client.id)) {
          this.selectedClients.push(client);
        }
      });
    },
    clearSelection() {
      this.selectedClients = [];
      this.filterClients();
    },
    removeFromSelection(client) {
      const index = this.selectedClients.findIndex(c => c.id === client.id);
      if (index > -1) {
        this.selectedClients.splice(index, 1);
      }
      this.filterClients();
    },
    saveClientsToCampaign() {
      if (this.selectedClients.length === 0) {
        notify.warning('Please select at least one client.', 'Campaigns');
        return;
      }

      const id = this.$route.params.id;
      this.addClientsForm.saving = true;

      axios
        .post(`/api/campaigns/${id}/attach-clients`, {
          add_all: false,
          client_ids: this.selectedClients.map(c => c.id),
        })
        .then((response) => {
          notify.success(`Successfully added ${response.data.attached_count || this.selectedClients.length} client(s) to the campaign.`, 'Campaigns');
          this.addClientsModal.hide();
          this.fetchClients();
          this.fetchStats();
        })
        .catch((error) => {
          console.error('Failed to add clients:', error);
          notify.error('Failed to add clients: ' + (error.response?.data?.message || error.message), 'Campaigns');
        })
        .finally(() => {
          this.addClientsForm.saving = false;
        });
    },

    // WhatsApp Template flow
    openAddWhatsappTemplateModal() {
      this.whatsappModalLoading = true;
      this.editingWhatsappMessageId = null;
      this.whatsappForm = {
        mode: 'template',
        templateId: '',
        flowId: '',
        templateVariables: {},
        selectedClients: [],
        trackResponses: false,
        enableLiveChat: false,
        sending: false,
        action: null,
      };

      const preSelected = this.$route.query.whatsapp_template;

      Promise.all([
        axios.get('/api/whatsapp-templates'),
        axios.get('/api/whatsapp-flows').catch(() => ({ data: [] })),
      ]).then(([tplRes, flowRes]) => {
        this.whatsappTemplates = tplRes.data.data || tplRes.data || [];
        this.whatsappFlows = flowRes.data.data || flowRes.data || [];

        if (preSelected && this.whatsappTemplates.some((t) => t.id === preSelected)) {
          this.whatsappForm.templateId = preSelected;
        }
        if (!this.whatsappTemplates.length) {
          notify.warning('No approved WhatsApp templates were returned from Meta for the configured WhatsApp Business Account.', 'Campaigns');
        }
      }).catch((error) => {
        console.error('Failed to load WhatsApp templates from Meta', error);
        notify.error('Failed to load WhatsApp templates: ' + (error.response?.data?.message || error.message), 'Campaigns');
      }).finally(() => {
        this.addWhatsappModal.show();
        this.whatsappModalLoading = false;
      });
    },
    saveWhatsappTemplate(sendNow = true) {
      const isTemplate = this.whatsappForm.mode === 'template';
      const isFlow = this.whatsappForm.mode === 'flow';
      if (isTemplate && !this.whatsappForm.templateId) return;
      if (isFlow && !this.whatsappForm.flowId) return;

      const id = this.$route.params.id;
      const hasSelection = this.whatsappForm.selectedClients.length > 0;
      const payload = {
        mode: this.whatsappForm.mode,
        template_id: isTemplate ? this.whatsappForm.templateId : null,
        flow_id: isFlow ? this.whatsappForm.flowId : null,
        template_variables: isTemplate ? this.whatsappForm.templateVariables : {},
        clients_mode: hasSelection ? 'selected' : 'all',
        client_ids: hasSelection ? this.whatsappForm.selectedClients.map((c) => c.id) : [],
        send_now: sendNow,
        enable_live_chat: this.whatsappForm.enableLiveChat,
      };

      this.whatsappForm.sending = true;
      this.whatsappForm.action = sendNow ? 'queue' : 'save';

      const request = this.editingWhatsappMessageId
        ? axios.put(`/api/campaigns/${id}/whatsapp-messages/${this.editingWhatsappMessageId}`, payload)
        : axios.post(`/api/campaigns/${id}/whatsapp-messages`, payload);

      request
        .then(() => {
          const msg = sendNow
            ? 'WhatsApp batch queued successfully.'
            : 'WhatsApp batch saved successfully (not yet sent).';
          notify.success(msg, 'Campaigns');

          this.addWhatsappModal.hide();
          this.fetchWhatsApp();
          this.fetchStats();
          this.editingWhatsappMessageId = null;
        })
        .catch((error) => {
          console.error('Failed to queue WhatsApp batch:', error);
          notify.error('Failed to queue WhatsApp batch: ' + (error.response?.data?.message || error.message), 'Campaigns');
        })
        .finally(() => {
          this.whatsappForm.sending = false;
          this.whatsappForm.action = null;
        });
    },
    editWhatsappTemplate(message) {
      const isFlow = !!(message.whatsapp_flow_id || message.flow_id || message.flowId || message.flow);
      const templateId = this.whatsappTemplateId(message);
      this.editingWhatsappMessageId = message.id;
      let parsedVariables = {};
      try {
        parsedVariables = (typeof message.template_variables === 'string') 
            ? JSON.parse(message.template_variables) 
            : (message.template_variables || {});
      } catch (e) {
        parsedVariables = {};
      }

      // Auto-migrate old numeric keys to the new format
      const template = this.whatsappTemplates.find((t) => t.id === templateId);
      if (template && template.variables) {
        const expectedKeys = Object.keys(template.variables);
        if (expectedKeys.length > 0 && parsedVariables['1'] && !parsedVariables[expectedKeys[0]]) {
            const newParsed = {};
            let i = 1;
            for (const key of expectedKeys) {
                if (parsedVariables[String(i)]) {
                    newParsed[key] = parsedVariables[String(i)];
                }
                i++;
            }
            parsedVariables = newParsed;
        }
      }

      this.whatsappForm = {
        mode: isFlow ? 'flow' : 'template',
        templateId: templateId || '',
        flowId: isFlow ? (message.whatsapp_flow_id || message.flow_id || message.flowId || message.flow?.id || '') : '',
        templateVariables: parsedVariables,
        selectedClients: [],
        trackResponses: true,
        enableLiveChat: !!message.enable_live_chat,
        sending: false,
        action: null,
      };

      const campaignId = this.$route.params.id;

      Promise.all([
        this.whatsappTemplates.length ? Promise.resolve({ data: this.whatsappTemplates }) : axios.get('/api/whatsapp-templates').catch(() => ({ data: [] })),
        this.whatsappFlows.length ? Promise.resolve({ data: this.whatsappFlows }) : axios.get('/api/whatsapp-flows').catch(() => ({ data: [] })),
      ]).then(([tplRes, flowRes]) => {
        this.whatsappTemplates = tplRes.data.data || tplRes.data || [];
        this.whatsappFlows = flowRes.data.data || flowRes.data || [];
        this.whatsappForm.templateId = templateId || '';
      }).finally(() => {
        axios
          .get(`/api/campaigns/${campaignId}/whatsapp-messages/${message.id}/recipients`)
          .then((res) => {
            const recipients = res.data.recipients || [];
            const selectedIds = recipients.map((r) => r.client_id).filter(Boolean);
            const optionsMap = new Map(this.campaignClientOptions.map((c) => [c.id, c]));
            this.whatsappForm.selectedClients = selectedIds.map((id) => optionsMap.get(id)).filter(Boolean);
          })
          .finally(() => {
            this.addWhatsappModal.show();
          });
      });
    },
    deleteWhatsappTemplate(message) {
      const campaignId = this.$route.params.id;
      this.$refs.confirmModal.open({
        title: 'Delete WhatsApp Batch',
        message: `Delete this WhatsApp batch "${message.template_name || ''}"? This action cannot be undone.`,
        confirmLabel: 'Delete Batch',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            await axios.delete(`/api/campaigns/${campaignId}/whatsapp-messages/${message.id}`);
            this.fetchWhatsApp();
            if (this.editingWhatsappMessageId === message.id) {
              this.editingWhatsappMessageId = null;
            }
            notify.success('WhatsApp batch deleted.', 'Campaigns');
          } catch (err) {
            notify.error('Failed to delete batch: ' + (err.response?.data?.message || err.message), 'Campaigns');
            throw err;
          }
        },
      });
    },
    sendDraftWhatsapp(message) {
      if (!this.canSendWhatsapp(message)) return;
      const campaignId = this.$route.params.id;
      axios
        .post(`/api/campaigns/${campaignId}/whatsapp-messages/${message.id}/send`)
        .then(() => {
          notify.success('Batch sent successfully.', 'Campaigns');
          this.fetchWhatsApp();
          this.fetchStats();
        })
        .catch((err) => {
          notify.error('Failed to send batch: ' + (err.response?.data?.message || err.message), 'Campaigns');
        });
    },


    // Email Template flow
    openAddEmailTemplateModal() {
        this.emailForm = {
            mode: 'new',
            subject: '',
            body: '',
            templateId: '',
            selectedClients: [],
            sending: false,
        };

        axios.get('/api/email-templates').then((res) => {
            this.emailTemplates = res.data.data || res.data;
        }).catch(() => {
            this.emailTemplates = [];
        }).finally(() => {
            this.addEmailModal.show();
        });
    },

    saveEmailTemplate() {
        if (!this.emailFormIsValid) return;

        const id = this.$route.params.id;
        const hasSelection = this.emailForm.selectedClients.length > 0;

        this.emailForm.sending = true;

        axios.post(`/api/campaigns/${id}/emails`, {
            mode: this.emailForm.mode,
            subject: this.emailForm.mode === 'new' ? this.emailForm.subject : null,
            body: this.emailForm.mode === 'new' ? this.emailForm.body : null,
            template_id: this.emailForm.mode === 'template' ? this.emailForm.templateId : null,
            clients_mode: hasSelection ? 'selected' : 'all',
            client_ids: hasSelection
            ? this.emailForm.selectedClients.map((c) => c.id)
            : [],
        }).then(() => {
            notify.success('Email batch queued successfully.', 'Campaigns');
            this.addEmailModal.hide();
            this.fetchEmails();
            this.fetchStats();
        }).catch((error) => {
            console.error('Failed to queue email batch:', error);
            notify.error('Failed to queue email batch: ' + (error.response?.data?.message || error.message), 'Campaigns');
        }).finally(() => {
            this.emailForm.sending = false;
        });
    },


    // SMS Template flow
    openAddSmsTemplateModal() {
    this.smsForm = {
        subject: '',
        text: '',
        selectedClients: [],
        sending: false,
    };
    this.addSmsModal.show();
    },
    saveSmsTemplate() {
        if (!this.smsFormIsValid) return;

        const id = this.$route.params.id;
        const hasSelection = this.smsForm.selectedClients.length > 0;

        this.smsForm.sending = true;

        axios.post(`/api/campaigns/${id}/sms-messages`, {
            subject: this.smsForm.subject,
            text: this.smsForm.text,
            clients_mode: hasSelection ? 'selected' : 'all',
            client_ids: hasSelection
            ? this.smsForm.selectedClients.map((c) => c.id)
            : [],
        }).then(() => {
            notify.success('SMS batch queued successfully.', 'Campaigns');
            this.addSmsModal.hide();
            this.fetchSms();
            this.fetchStats();
        }).catch((error) => {
            console.error('Failed to queue SMS batch:', error);
            notify.error('Failed to queue SMS batch: ' + (error.response?.data?.message || error.message), 'Campaigns');
        }).finally(() => {
            this.smsForm.sending = false;
        });
    },

    selectAllCampaignClients(channel) {
        const all = this.campaignClientOptions;

        if (channel === 'whatsapp') {
            this.whatsappForm.selectedClients = [...all];
        } else if (channel === 'email') {
            this.emailForm.selectedClients = [...all];
        } else if (channel === 'sms') {
            this.smsForm.selectedClients = [...all];
        }
        },

    clearCampaignClientSelection(channel) {
        if (channel === 'whatsapp') {
            this.whatsappForm.selectedClients = [];
        } else if (channel === 'email') {
            this.emailForm.selectedClients = [];
        } else if (channel === 'sms') {
            this.smsForm.selectedClients = [];
        }
    },

  },
};
</script>

<style scoped>
/* VueMultiselect styling */
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
  padding: 8px 40px 8px 8px;
}

:deep(.multiselect__tag) {
  background: #0d6efd;
  color: white;
  padding: 4px 26px 4px 10px;
  margin: 2px;
  border-radius: 4px;
}

:deep(.multiselect__tag-icon:after) {
  color: white;
}

:deep(.multiselect__tag-icon:hover) {
  background: #0b5ed7;
}

:deep(.multiselect__option--highlight) {
  background: #0d6efd;
  color: white;
}

:deep(.multiselect__option--highlight:after) {
  background: #0d6efd;
}

:deep(.multiselect__option--selected) {
  background: #e7f1ff;
  color: #0d6efd;
  font-weight: 600;
}

:deep(.multiselect__option--selected.multiselect__option--highlight) {
  background: #0d6efd;
  color: white;
}

.client-option {
  padding: 8px 4px;
}

.client-option .badge {
  font-size: 0.7em;
  padding: 2px 6px;
  margin-top: 2px;
}

/* Selected clients preview */
.selected-clients-container {
  max-height: 200px;
  overflow-y: auto;
}

.selected-client-item {
  padding: 8px 12px;
  border-bottom: 1px solid #e9ecef;
}

.selected-client-item:last-child {
  border-bottom: none;
}

.selected-client-item .btn-outline-danger {
  width: 24px;
  height: 24px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
