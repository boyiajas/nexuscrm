<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <button class="btn btn-outline-secondary btn-sm mb-2" @click="$router.back()">
          ← Back
        </button>
        <h2 class="h4 mb-0">
          {{ campaign?.name || 'Campaign' }}
        </h2>
        <small class="text-muted">
          Status:
          <span class="badge" :class="statusBadgeClass(campaign?.status)">
            {{ campaign?.status }}
          </span>
          <span v-if="campaign?.bank?.name">
            ·
            <span class="badge bg-primary-subtle text-primary border ms-1">
              {{ campaign.bank.name }}
            </span>
          </span>
          <span v-if="campaign && campaign.departments && campaign.departments.length">
            ·
            <span
              v-for="d in campaign.departments"
              :key="d.id"
              class="badge bg-light text-dark border ms-1"
            >
              {{ d.name }}
            </span>
          </span>
        </small>
      </div>

      <div class="text-end">
        <button class="btn btn-outline-success btn-sm me-2" @click="sendNow" :disabled="!canSend">
          <i class="bi bi-send-check me-1"></i>
          Send Now
        </button>
        <button class="btn btn-outline-primary btn-sm" @click="refreshAll">
          <i class="bi bi-arrow-clockwise me-1"></i>
          Refresh
        </button>
      </div>
    </div>

    <!-- Overview cards -->
    <div class="row g-3 mb-3">
        <div class="col-md-3" v-for="card in overviewCards" :key="card.label">
            <div class="card shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                
                <!-- Left content -->
                <div>
                <div class="text-muted small text-uppercase mb-1">{{ card.label }}</div>
                <div class="h4 mb-0">{{ card.value }}</div>
                <small class="text-muted">{{ card.subtitle }}</small>
                </div>

                <!-- Right-side icon -->
                <div class="ms-3 d-flex align-items-center justify-content-center"
                    style="width: 38px; height: 38px; border-radius: 8px; background: #f5f5f5;">
                <i :class="card.icon" class="fs-4 text-secondary"></i>
                </div>

            </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-clients" type="button">
          Clients
        </button>
      </li>
      <li class="nav-item" v-if="channels.whatsapp">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-whatsapp" type="button">
          WhatsApp
        </button>
      </li>
      <li class="nav-item" v-if="channels.email">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-email" type="button">
          Email
        </button>
      </li>
      <li class="nav-item" v-if="channels.sms">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sms" type="button">
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
              <div>
                <button class="btn btn-sm btn-outline-secondary me-2" @click="exportClients">
                  <i class="bi bi-filetype-csv me-1"></i>
                  Export CSV
                </button>
                <button class="btn btn-sm btn-primary" @click="openAddClientsModal" :disabled="!canManageCampaign">
                  <i class="bi bi-person-plus me-1"></i>
                  Add Clients to Campaign
                </button>
              </div>
            </div>

            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Bank</th>
                  <th>Assigned Owner</th>
                  <th>Departments</th>
                  <th>WhatsApp</th>
                  <th>Email</th>
                  <th>SMS</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cl in clients" :key="cl.id">
                  <td>{{ cl.name }}</td>
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
                </tr>
                <tr v-if="clients.length === 0">
                  <td colspan="9" class="text-center text-muted py-3">
                    No clients added to this campaign yet.
                  </td>
                </tr>
              </tbody>
            </table>
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

            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Template</th>
                  <th>Status</th>
                  <th>Sent At</th>
                  <th>Total</th>
                  <th>Delivered</th>
                  <th>Failed</th>
                  <th>Pending</th>
                  <th>Chat Request</th>
                  <th>Responses</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="w in whatsappMessages" :key="w.id">
                  <td>
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

            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Subject</th>
                  <th>Status</th>
                  <th>Sent At</th>
                  <th>Total</th>
                  <th>Delivered</th>
                  <th>Bounced</th>
                  <th>Opened</th>
                  <th>Clicked</th>
                  <th class="text-end">Dashboard</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="m in emails" :key="m.id">
                  <td>
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

            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Subject</th>
                  <th>Text</th>
                  <th>Status</th>
                  <th>Sent At</th>
                  <th>Total</th>
                  <th>Delivered</th>
                  <th>Failed</th>
                  <th>Pending</th>
                  <th class="text-end">Dashboard</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in smsMessages" :key="s.id">
                  <td>{{ s.subject || '-' }}</td>
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

    <!-- Mini Dashboard Modal (WhatsApp / Email / SMS) -->
    <div class="modal fade" tabindex="-1" ref="recipientsModalRef">
      <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 95vw; width: 1500px;">
        <div class="modal-content">
          <div class="modal-header flex-column flex-md-row align-items-start justify-content-between gap-3 bg-light border-bottom py-3">
            <div class="me-md-3">
              <h5 class="modal-title d-flex align-items-center gap-2 mb-2">
                <span v-if="recipientModal.channel === 'WhatsApp'" class="p-2 rounded bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                  <i class="bi bi-whatsapp fs-5"></i>
                </span>
                <span v-else-if="recipientModal.channel === 'Email'" class="p-2 rounded bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                  <i class="bi bi-envelope-paper fs-5"></i>
                </span>
                <span v-else-if="recipientModal.channel === 'SMS'" class="p-2 rounded bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                  <i class="bi bi-chat-left-text fs-5"></i>
                </span>
                <span class="fw-bold text-dark">
                  {{ recipientModal.title }}
                </span>
              </h5>
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-white text-dark border px-2 py-1">
                  <i class="bi bi-tag text-muted me-1"></i>
                  <strong>Template / Subject:</strong> {{ recipientModal.meta.template_name || recipientModal.meta.subject || '-' }}
                </span>
                <span class="badge ms-1 px-2 py-1" :class="statusColor(recipientModal.meta.status || 'sent')">
                  <i class="bi bi-info-circle me-1"></i>
                  {{ recipientModal.meta.status || 'Sent' }}
                </span>
                <span v-if="recipientModal.meta.reply_number" class="badge bg-white text-dark border px-2 py-1">
                  <i class="bi bi-telephone text-muted me-1"></i>
                  <strong>From/Reply:</strong> {{ recipientModal.meta.reply_number }}
                </span>
                <span v-if="recipientModal.meta.scheduled_at" class="badge bg-info-subtle text-info border border-info px-2 py-1">
                  <i class="bi bi-clock-history me-1"></i>
                  Scheduled: {{ recipientModal.meta.scheduled_at }}
                </span>
                <span v-if="recipientModal.meta.enable_live_chat" class="badge bg-success-subtle text-success border border-success px-2 py-1">
                  <i class="bi bi-chat-dots me-1"></i> Live Chat Active
                </span>
                <span v-if="recipientModal.meta.track_responses" class="badge bg-primary-subtle text-primary border border-primary px-2 py-1">
                  <i class="bi bi-graph-up me-1"></i> Tracking Enabled
                </span>
              </div>
            </div>
            <div class="d-flex align-items-start gap-2 align-self-end align-self-md-start">
              <button
                v-if="recipientModal.meta && recipientModal.meta.can_send"
                type="button"
                class="btn btn-sm btn-success px-3 shadow-sm"
                @click="sendBatchNow"
                :disabled="sendingBatch"
              >
                <span v-if="sendingBatch" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-send-check me-1"></i>
                Send Now
              </button>
              <button type="button" class="btn-close mt-1" data-bs-dismiss="modal"></button>
            </div>
          </div>

          <div class="modal-body p-3 bg-light">

            <!-- Ultra-compact stats strip -->
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 bg-white p-2 px-3 rounded-3 shadow-sm border">
              <span class="text-muted small text-uppercase fw-bold me-2 d-flex align-items-center">
                <i class="bi bi-bar-chart-fill text-primary me-2 fs-6"></i>Summary:
              </span>
              <div
                v-for="stat in recipientSummaryCards"
                :key="stat.label"
                class="badge bg-light text-dark border d-flex align-items-center gap-2 px-3 py-2 fw-normal"
              >
                <i :class="stat.icon || 'bi bi-collection'"></i>
                <span class="text-muted small fw-semibold">{{ stat.label }}:</span>
                <strong class="text-dark fw-bold ms-1" style="font-size: 0.9rem;">{{ stat.value }}</strong>
              </div>
            </div>

            <!-- WhatsApp-only agent chart -->
            <div
              v-if="recipientModal.channel === 'WhatsApp' && recipientModal.agents && recipientModal.agents.length"
              class="mb-4"
            >
              <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-3">
                  <h6 class="card-title fw-bold mb-3 d-flex align-items-center text-dark">
                    <i class="bi bi-people-fill text-primary me-2"></i>
                    Requests by Agent
                  </h6>
                  <div class="row g-3">
                    <div v-for="agent in recipientModal.agents" :key="agent.agent_id" class="col-md-6 col-lg-4">
                      <div class="p-2 border rounded bg-light">
                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                          <span>{{ agent.agent_name }}</span>
                          <span class="badge bg-primary">{{ agent.count }} request(s)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                          <div
                            class="progress-bar bg-primary"
                            role="progressbar"
                            :style="{ width: agentPercent(agent) + '%' }"
                          ></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Recipients table -->
            <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
              <div class="card-body p-0">
                <div class="p-3 bg-white border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                  <h6 class="m-0 fw-bold text-dark">
                    <i class="bi bi-people me-2 text-primary"></i>
                    Recipients List <span class="text-muted fw-normal">({{ filteredRecipients.length }})</span>
                  </h6>
                  <div class="input-group input-group-sm" style="max-width: 360px;">
                    <span class="input-group-text bg-light border-end-0">
                      <i class="bi bi-search text-muted"></i>
                    </span>
                    <input
                      v-model="recipientModal.filter"
                      type="text"
                      class="form-control border-start-0"
                      placeholder="Search name, email, phone, status..."
                    />
                    <button
                      class="btn btn-outline-secondary"
                      type="button"
                      @click="recipientModal.filter = ''"
                      :disabled="!recipientModal.filter"
                    >
                      Clear
                    </button>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-hover table-nowrap mb-0 align-middle">
                    <thead class="table-light text-uppercase small text-muted">
                      <tr>
                        <th>Client</th>
                        <th v-if="recipientModal.channel !== 'WhatsApp'">Email</th>
                        <th v-if="recipientModal.channel !== 'Email'">Phone</th>
                        <th>Bank</th>
                        <th>Assigned Owner</th>
                        <th>Departments</th>
                        <th v-if="recipientModal.channel === 'WhatsApp'">Response / Flow</th>
                        <th>Status</th>
                        <th>Delivered At</th>
                        <th v-if="recipientModal.channel === 'WhatsApp'" class="text-end">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="r in filteredRecipients" :key="r.id">
                        <td class="fw-semibold text-dark">{{ r.client_name || '-' }}</td>
                        <td v-if="recipientModal.channel !== 'WhatsApp'" class="text-muted">{{ r.email || '-' }}</td>
                        <td v-if="recipientModal.channel !== 'Email'" class="text-muted">{{ r.phone || '-' }}</td>
                        <td>{{ r.bank_name || campaign?.bank?.name || '-' }}</td>
                        <td>{{ r.assigned_to_name || '-' }}</td>
                        <td>
                          <span v-if="r.department_names" class="badge bg-light text-dark border">
                            {{ r.department_names }}
                          </span>
                          <span v-else class="text-muted small">-</span>
                        </td>
                        <td v-if="recipientModal.channel === 'WhatsApp'">
                          <span v-if="r.reply_label || r.last_response" class="badge bg-success-subtle text-success border border-success px-2 py-1 me-1">
                            <i class="bi bi-chat-text me-1"></i>{{ r.reply_label || r.last_response }}
                          </span>
                          <span v-else-if="r.current_flow_step_id" class="badge bg-info-subtle text-info border border-info px-2 py-1">
                            <i class="bi bi-diagram-3 me-1"></i>Step #{{ r.current_flow_step_id }}
                          </span>
                          <span v-else class="text-muted small">No response</span>
                        </td>
                        <td>
                          <span class="badge px-2 py-1 text-uppercase" :class="statusColor(r.status)">
                            {{ r.status || 'Pending' }}
                          </span>
                        </td>
                        <td class="text-muted small">{{ r.delivered_at || '-' }}</td>
                        <td v-if="recipientModal.channel === 'WhatsApp'" class="text-end">
                          <button
                            class="btn btn-sm btn-outline-primary shadow-sm"
                            @click="openClientChat(r)"
                            :disabled="!r.client_id"
                            title="Open WhatsApp Live Chat"
                          >
                            <i class="bi bi-chat-dots"></i>
                          </button>
                        </td>
                      </tr>
                      <tr v-if="filteredRecipients.length === 0">
                        <td :colspan="recipientModal.channel === 'WhatsApp' ? 9 : 8" class="text-center text-muted py-5">
                          <i class="bi bi-folder2-open d-block fs-2 mb-2 text-secondary"></i>
                          No recipients found matching your filter criteria.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

          </div>

          <div class="modal-footer justify-content-end">
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-bs-dismiss="modal"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Clients Modal (unchanged except already using VueMultiselect) -->
    <div class="modal fade" tabindex="-1" ref="addClientsModalRef">
      <div class="modal-dialog modal-lg">
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                <i class="bi bi-whatsapp me-1 text-success"></i>
                Add WhatsApp Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
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
                <!-- Template preview -->
                <div v-if="whatsappForm.mode === 'template' && currentWhatsappTemplate" class="mb-3">
                  <div class="card border-success">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                      <strong>Template Preview</strong>
                      <small class="text-muted">
                        {{ currentWhatsappTemplate.language || 'N/A' }}
                        <span v-if="currentWhatsappTemplate.category">
                          · {{ currentWhatsappTemplate.category }}
                        </span>
                      </small>
                    </div>
                    <div class="card-body py-2">
                      <div
                        v-if="
                          currentWhatsappTemplate.media_urls &&
                          currentWhatsappTemplate.media_urls.length
                        "
                        class="mb-2 text-center"
                      >
                        <img
                          v-if="currentWhatsappTemplate.header_format === 'IMAGE'"
                          :src="currentWhatsappTemplate.media_urls[0]"
                          alt="WhatsApp template media"
                          class="img-fluid rounded border"
                          style="max-height: 200px; object-fit: contain;"
                        />
                        <video
                          v-else-if="currentWhatsappTemplate.header_format === 'VIDEO'"
                          :src="currentWhatsappTemplate.media_urls[0]"
                          class="img-fluid rounded border"
                          style="max-height: 220px; object-fit: contain;"
                          controls
                          preload="metadata"
                        ></video>
                        <div
                          v-else-if="currentWhatsappTemplate.header_format === 'DOCUMENT'"
                          class="border rounded p-3 bg-light"
                        >
                          <i class="bi bi-file-earmark-arrow-down fs-4 d-block mb-2"></i>
                          <a
                            :href="currentWhatsappTemplate.media_urls[0]"
                            target="_blank"
                            rel="noopener noreferrer"
                          >
                            Open template document
                          </a>
                        </div>
                      </div>

                      <div v-if="currentWhatsappTemplate.header_text" class="small fw-semibold mb-2">
                        {{ currentWhatsappTemplate.header_text }}
                      </div>

                      <pre class="mb-0 small">{{ currentWhatsappTemplate.body_preview }}</pre>

                      <div v-if="currentWhatsappTemplate.footer_text" class="small text-muted mt-2">
                        {{ currentWhatsappTemplate.footer_text }}
                      </div>

                      <div
                        v-if="currentWhatsappTemplate.buttons && currentWhatsappTemplate.buttons.length"
                        class="mt-3 d-flex gap-2 flex-wrap"
                      >
                        <span
                          v-for="(button, idx) in currentWhatsappTemplate.buttons"
                          :key="idx"
                          class="badge bg-light text-dark border"
                        >
                          {{ button.text || button.type || 'Button' }}
                        </span>
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
                                <option value="client.name">Client Name</option>
                                <option value="client.phone">Client Phone</option>
                                <option value="client.email">Client Email</option>
                                <option value="client.id_number">Client ID Number</option>
                                <option value="client.account_number">Client Account Number</option>
                                <option value="client.bank_name">Client Bank</option>
                                <option value="client.branch_code">Client Branch Code</option>
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

      // channel messages
      whatsappMessages: [],
      emails: [],
      smsMessages: [],
      editingWhatsappMessageId: null,

      // dashboard modal
      recipientsModal: null,
      sendingBatch: false,
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
    canManageCampaign() {
      const stored = localStorage.getItem('nexus_user');
      if (!stored) return false;

      const role = JSON.parse(stored)?.role;
      return ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AGENT', 'STAFF'].includes(role);
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
    this.refreshAll();
  },
  beforeUnmount() {
    disposeManagedModal(this.recipientsModal);
    disposeManagedModal(this.addClientsModal);
    disposeManagedModal(this.addWhatsappModal);
    disposeManagedModal(this.addEmailModal);
    disposeManagedModal(this.addSmsModal);
    cleanupManagedModalArtifacts(true);
  },
  methods: {
    getTemplateVariable(key) {
      if (!this.whatsappForm.templateVariables[key]) {
        this.whatsappForm.templateVariables[key] = { source: '', custom_value: '' };
      }
      return this.whatsappForm.templateVariables[key];
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
      });
    },
    fetchStats() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/stats`).then((res) => {
        this.stats = res.data;
      });
    },
    fetchClients() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/clients`).then((res) => {
        this.clients = res.data.data || res.data;
      });
    },
    fetchWhatsApp() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/whatsapp-messages`).then((res) => {
        this.whatsappMessages = res.data.data || res.data;
      });
    },
    fetchEmails() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/emails`).then((res) => {
        this.emails = res.data.data || res.data;
      });
    },
    fetchSms() {
      const id = this.$route.params.id;
      axios.get(`/api/campaigns/${id}/sms-messages`).then((res) => {
        this.smsMessages = res.data.data || res.data;
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
    openClientChat(recipient) {
      if (!recipient.client_id) return;
      this.closeRecipientsModal(() => {
        this.$router.push({
          name: 'chat',
          query: { client_id: recipient.client_id },
        });
      });
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

      this.whatsappForm = {
        mode: isFlow ? 'flow' : 'template',
        templateId: templateId || '',
        flowId: isFlow ? (message.whatsapp_flow_id || message.flow_id || message.flowId || message.flow?.id || '') : '',
        templateVariables: parsedVariables,
        selectedClients: [],
        trackResponses: false,
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
