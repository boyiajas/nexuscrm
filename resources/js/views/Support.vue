<template>
  <div class="support-page-container">
    <!-- PAGE HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
      <div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-3">
            <i class="bi bi-headset fs-5"></i>
          </span>
          <div>
            <h1 class="h4 mb-0 fw-bold text-dark">Support & System Updates</h1>
            <p class="text-muted small mb-0">Submit assistance tickets, track resolution progress, and discover newly added system capabilities.</p>
          </div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <button
          v-if="activeTab === 'tickets'"
          class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm"
          :disabled="loading"
          @click="fetchTickets(currentPage)"
        >
          <i class="bi bi-arrow-clockwise" :class="{ 'spin-icon': loading }"></i>
          <span>Refresh</span>
        </button>
        <button
          class="btn btn-primary btn-sm d-flex align-items-center gap-2 shadow-sm px-3"
          @click="openCreateTicketModal"
        >
          <i class="bi bi-plus-lg"></i>
          <span>Create Ticket</span>
        </button>
      </div>
    </div>

    <!-- NAVIGATION TABS -->
    <ul class="nav nav-pills custom-support-tabs mb-4 p-1 bg-white rounded-3 border shadow-sm">
      <li class="nav-item">
        <button
          class="nav-link d-flex align-items-center gap-2 px-4 py-2"
          :class="{ active: activeTab === 'tickets' }"
          type="button"
          @click="activeTab = 'tickets'"
        >
          <i class="bi bi-ticket-perforated"></i>
          <span class="fw-semibold">Support Tickets</span>
          <span v-if="counts.open > 0" class="badge bg-warning text-dark rounded-pill ms-1">{{ counts.open }} open</span>
        </button>
      </li>
      <li class="nav-item">
        <button
          class="nav-link d-flex align-items-center gap-2 px-4 py-2"
          :class="{ active: activeTab === 'updates' }"
          type="button"
          @click="activeTab = 'updates'"
        >
          <i class="bi bi-stars text-warning"></i>
          <span class="fw-semibold">New System Updates</span>
          <span class="badge bg-primary rounded-pill ms-1">New Features</span>
        </button>
      </li>
    </ul>

    <!-- TAB 1: SUPPORT TICKETS -->
    <div v-if="activeTab === 'tickets'">
      <!-- METRICS CARDS -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-3 stat-card stat-total">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted small fw-medium">Total Tickets</span>
                <h3 class="fw-bold mb-0 mt-1">{{ counts.total }}</h3>
              </div>
              <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                <i class="bi bi-ticket-detailed fs-4"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-3 stat-card stat-open">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted small fw-medium">Open & Awaiting</span>
                <h3 class="fw-bold mb-0 mt-1 text-warning">{{ counts.open }}</h3>
              </div>
              <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                <i class="bi bi-hourglass-split fs-4"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-3 stat-card stat-progress">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted small fw-medium">In Progress</span>
                <h3 class="fw-bold mb-0 mt-1 text-info">{{ counts.in_progress }}</h3>
              </div>
              <div class="stat-icon bg-info bg-opacity-10 text-info rounded-circle p-3">
                <i class="bi bi-gear-wide-connected fs-4"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-3 stat-card stat-resolved">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted small fw-medium">Resolved & Closed</span>
                <h3 class="fw-bold mb-0 mt-1 text-success">{{ counts.resolved + counts.closed }}</h3>
              </div>
              <div class="stat-icon bg-success bg-opacity-10 text-success rounded-circle p-3">
                <i class="bi bi-check-circle-fill fs-4"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- FILTERS ROW -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
          <div class="row g-2 align-items-center">
            <div class="col-md-4">
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input
                  v-model="filters.search"
                  type="text"
                  class="form-control bg-light border-start-0"
                  placeholder="Search by ticket #, subject, or keyword..."
                  @keyup.enter="fetchTickets(1)"
                />
              </div>
            </div>
            <div class="col-6 col-md-2">
              <select v-model="filters.status" class="form-select form-select-sm" @change="fetchTickets(1)">
                <option value="all">All Statuses</option>
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <select v-model="filters.priority" class="form-select form-select-sm" @change="fetchTickets(1)">
                <option value="all">All Priorities</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <select v-model="filters.category" class="form-select form-select-sm" @change="fetchTickets(1)">
                <option value="all">All Categories</option>
                <option value="Live Chat & WhatsApp">Live Chat & WhatsApp</option>
                <option value="Campaigns & Dispatch">Campaigns & Dispatch</option>
                <option value="Clients & Imports">Clients & Imports</option>
                <option value="Access & Permissions">Access & Permissions</option>
                <option value="Bug Report">Bug Report</option>
                <option value="Feature Request">Feature Request</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-6 col-md-2 d-flex justify-content-end gap-1">
              <button class="btn btn-sm btn-primary w-100" @click="fetchTickets(1)">Filter</button>
              <button class="btn btn-sm btn-outline-secondary" title="Reset Filters" @click="resetFilters">
                <i class="bi bi-arrow-counterclockwise"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- TICKETS TABLE CARD -->
      <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="text-muted small mt-2">Loading support tickets...</div>
          </div>

          <div v-else-if="tickets.length === 0" class="text-center py-5">
            <div class="bg-light rounded-circle d-inline-flex p-3 mb-2 text-muted">
              <i class="bi bi-ticket-perforated fs-1"></i>
            </div>
            <h6 class="fw-bold mb-1">No support tickets found</h6>
            <p class="text-muted small mb-3">You don't have any tickets matching your filter criteria.</p>
            <button class="btn btn-sm btn-primary" @click="openCreateTicketModal">
              <i class="bi bi-plus-lg me-1"></i> Create Support Ticket
            </button>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="bg-light text-muted small text-uppercase">
                <tr>
                  <th class="ps-4 py-3" style="width: 140px;">Ticket #</th>
                  <th class="py-3">Subject & Category</th>
                  <th class="py-3">Priority</th>
                  <th class="py-3">Status</th>
                  <th class="py-3">Submitted By</th>
                  <th class="py-3">Updated</th>
                  <th class="pe-4 py-3 text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="ticket in tickets"
                  :key="ticket.id"
                  class="cursor-pointer"
                  @click="openTicketDetails(ticket)"
                >
                  <td class="ps-4">
                    <span class="fw-bold font-monospace text-dark">{{ ticket.ticket_number }}</span>
                    <div v-if="ticket.bank" class="small text-muted" style="font-size: 0.75rem;">
                      {{ ticket.bank.name }}
                    </div>
                  </td>
                  <td>
                    <div class="fw-semibold text-dark text-truncate" style="max-width: 320px;">
                      {{ ticket.subject }}
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                      <span class="badge bg-light text-secondary border small" style="font-size: 0.7rem;">
                        {{ ticket.category }}
                      </span>
                      <span v-if="ticket.messages_count > 1" class="text-muted small" style="font-size: 0.72rem;">
                        <i class="bi bi-chat-dots me-1"></i>{{ ticket.messages_count }} messages
                      </span>
                    </div>
                  </td>
                  <td>
                    <span class="badge" :class="priorityBadgeClass(ticket.priority)">
                      <i class="bi me-1" :class="priorityIcon(ticket.priority)"></i>
                      {{ capitalize(ticket.priority) }}
                    </span>
                  </td>
                  <td>
                    <span class="badge" :class="statusBadgeClass(ticket.status)">
                      {{ formatStatus(ticket.status) }}
                    </span>
                  </td>
                  <td>
                    <div class="small fw-semibold text-dark">{{ ticket.user ? ticket.user.name : 'Unknown' }}</div>
                    <div class="small text-muted" style="font-size: 0.72rem;">{{ ticket.user ? ticket.user.role : '' }}</div>
                  </td>
                  <td>
                    <div class="small text-muted">{{ formatDate(ticket.updated_at) }}</div>
                  </td>
                  <td class="pe-4 text-end" @click.stop>
                    <button class="btn btn-sm btn-outline-primary" @click="openTicketDetails(ticket)">
                      <i class="bi bi-chat-left-text me-1"></i> View
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- PAGINATION -->
          <div v-if="totalTickets > perPage" class="p-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small">Showing {{ tickets.length }} of {{ totalTickets }} tickets</span>
            <div class="btn-group btn-group-sm">
              <button
                class="btn btn-outline-secondary"
                :disabled="currentPage === 1"
                @click="fetchTickets(currentPage - 1)"
              >
                Previous
              </button>
              <button
                class="btn btn-outline-secondary"
                :disabled="currentPage === lastPage"
                @click="fetchTickets(currentPage + 1)"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: NEW SYSTEM UPDATES & FEATURE SHOWCASE -->
    <div v-if="activeTab === 'updates'">
      <!-- SHOWCASE BANNER -->
      <div class="p-4 rounded-3 text-white mb-4 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <span class="badge bg-warning text-dark fw-bold mb-2 text-uppercase" style="letter-spacing: 0.05em; font-size: 0.7rem;">
              <i class="bi bi-rocket-takeoff-fill me-1"></i> {{ systemName }} Release Hub
            </span>
            <h2 class="h3 fw-bold mb-1">What's New in {{ systemName }}</h2>
            <p class="text-secondary small mb-0" style="color: #94a3b8 !important; max-width: 650px;">
              Explore the latest features and architectural updates recently deployed to {{ systemName }}. Click any feature's direct action button to immediately take advantage of the capability in your workflow.
            </p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-white text-dark py-2 px-3 fw-bold border">
              <i class="bi bi-shield-check text-success me-1"></i> {{ systemName }} v2.8
            </span>
          </div>
        </div>
      </div>

      <!-- FEATURE CATEGORY PILLS -->
      <div class="d-flex flex-wrap gap-2 mb-4">
        <button
          v-for="cat in updateCategories"
          :key="cat"
          class="btn btn-sm rounded-pill px-3 fw-semibold"
          :class="selectedUpdateCategory === cat ? 'btn-primary' : 'btn-white border text-secondary bg-white'"
          @click="selectedUpdateCategory = cat"
        >
          {{ cat }}
        </button>
      </div>

      <!-- FEATURE CARDS LIST -->
      <div class="row g-4">
        <div
          v-for="(feature, idx) in filteredFeatures"
          :key="idx"
          class="col-lg-6"
        >
          <div class="card h-100 border-0 shadow-sm rounded-3 feature-card">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
              <div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge" :class="feature.badgeClass">
                      <i :class="feature.icon" class="me-1"></i> {{ feature.category }}
                    </span>
                    <span class="badge bg-light text-muted border small">{{ feature.releaseDate }}</span>
                  </div>
                  <span v-if="feature.highlight" class="badge bg-success bg-opacity-10 text-success fw-bold">
                    <i class="bi bi-check-all me-1"></i> Deployed
                  </span>
                </div>

                <h5 class="fw-bold text-dark mb-2">{{ feature.title }}</h5>
                <p class="text-muted small mb-3" style="line-height: 1.55;">
                  {{ feature.description }}
                </p>

                <div class="bg-light rounded-3 p-3 mb-3 border border-light-subtle">
                  <div class="fw-semibold small text-primary mb-1">
                    <i class="bi bi-lightbulb-fill me-1 text-warning"></i> How to take advantage of this:
                  </div>
                  <div class="small text-secondary" style="font-size: 0.82rem;">
                    {{ feature.howToUse }}
                  </div>
                </div>
              </div>

              <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="small text-muted" style="font-size: 0.75rem;">
                  <i class="bi bi-check2-circle text-success me-1"></i> Available for your role
                </span>
                <router-link
                  v-if="feature.route"
                  :to="{ name: feature.route }"
                  class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 fw-semibold"
                >
                  <span>{{ feature.actionText }}</span>
                  <i class="bi bi-arrow-right"></i>
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CREATE TICKET MODAL -->
    <div
      v-if="showCreateModal"
      class="modal fade show d-block"
      style="background-color: rgba(15, 23, 42, 0.6); z-index: 1055;"
      tabindex="-1"
    >
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
          <div class="modal-header bg-light border-bottom px-4 py-3">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-2">
                <i class="bi bi-ticket-detailed"></i>
              </span>
              <div>
                <h5 class="modal-title fw-bold text-dark mb-0">Create Support Ticket</h5>
                <small class="text-muted">Submit an inquiry, report a bug, or request assistance from the team</small>
              </div>
            </div>
            <button type="button" class="btn-close" @click="showCreateModal = false"></button>
          </div>
          <div class="modal-body p-4">
            <form @submit.prevent="submitCreateTicket">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label small fw-semibold text-dark">Subject <span class="text-danger">*</span></label>
                  <input
                    v-model="newTicket.subject"
                    type="text"
                    class="form-control"
                    placeholder="Brief summary of the issue or request..."
                    required
                  />
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold text-dark">Category <span class="text-danger">*</span></label>
                  <select v-model="newTicket.category" class="form-select" required>
                    <option value="" disabled>Select category...</option>
                    <option value="Live Chat & WhatsApp">Live Chat & WhatsApp</option>
                    <option value="Campaigns & Dispatch">Campaigns & Dispatch</option>
                    <option value="Clients & Imports">Clients & Imports</option>
                    <option value="Access & Permissions">Access & Permissions</option>
                    <option value="Bug Report">Bug Report</option>
                    <option value="Feature Request">Feature Request</option>
                    <option value="Other">Other</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold text-dark">Priority</label>
                  <select v-model="newTicket.priority" class="form-select">
                    <option value="low">Low (General inquiry, cosmetic issue)</option>
                    <option value="medium">Medium (Normal workflow assistance)</option>
                    <option value="high">High (Feature not working properly)</option>
                    <option value="urgent">Urgent (Production outage or critical block)</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label small fw-semibold text-dark">Detailed Description <span class="text-danger">*</span></label>
                  <textarea
                    v-model="newTicket.description"
                    class="form-control"
                    rows="5"
                    placeholder="Please explain the issue in detail. Include client numbers, campaign IDs, or steps to reproduce if applicable..."
                    required
                  ></textarea>
                  <div class="form-text text-muted small">
                    Provide as much context as possible to help our team resolve your request quickly.
                  </div>
                </div>
              </div>

              <div v-if="createError" class="alert alert-danger mt-3 mb-0 small">
                {{ createError }}
              </div>

              <div class="modal-footer px-0 pb-0 pt-4 border-top mt-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="showCreateModal = false">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4" :disabled="submittingTicket">
                  <span v-if="submittingTicket" class="spinner-border spinner-border-sm me-1" role="status"></span>
                  <span>{{ submittingTicket ? 'Submitting...' : 'Submit Ticket' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- TICKET DETAILS & REPLY DRAWER/MODAL -->
    <div
      v-if="selectedTicket"
      class="modal fade show d-block"
      style="background-color: rgba(15, 23, 42, 0.6); z-index: 1055;"
      tabindex="-1"
    >
      <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
          <!-- HEADER -->
          <div class="modal-header bg-light border-bottom px-4 py-3">
            <div class="d-flex align-items-center gap-2">
              <span class="badge font-monospace fs-6 bg-dark text-white px-2 py-1">
                {{ selectedTicket.ticket_number }}
              </span>
              <div>
                <h5 class="modal-title fw-bold text-dark mb-0">{{ selectedTicket.subject }}</h5>
                <small class="text-muted">
                  Submitted by {{ selectedTicket.user ? selectedTicket.user.name : 'Unknown' }} • {{ formatDate(selectedTicket.created_at) }}
                </small>
              </div>
            </div>
            <button type="button" class="btn-close" @click="selectedTicket = null"></button>
          </div>

          <!-- BODY -->
          <div class="modal-body p-4">
            <!-- STATUS & METADATA BAR -->
            <div class="p-3 bg-light rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 border">
              <div class="d-flex align-items-center gap-3">
                <div>
                  <span class="text-muted small d-block" style="font-size: 0.72rem;">STATUS</span>
                  <span class="badge" :class="statusBadgeClass(selectedTicket.status)">
                    {{ formatStatus(selectedTicket.status) }}
                  </span>
                </div>
                <div>
                  <span class="text-muted small d-block" style="font-size: 0.72rem;">PRIORITY</span>
                  <span class="badge" :class="priorityBadgeClass(selectedTicket.priority)">
                    {{ capitalize(selectedTicket.priority) }}
                  </span>
                </div>
                <div>
                  <span class="text-muted small d-block" style="font-size: 0.72rem;">CATEGORY</span>
                  <span class="small fw-semibold text-dark">{{ selectedTicket.category }}</span>
                </div>
                <div v-if="selectedTicket.bank">
                  <span class="text-muted small d-block" style="font-size: 0.72rem;">BANK</span>
                  <span class="small fw-semibold text-dark">{{ selectedTicket.bank.name }}</span>
                </div>
              </div>

              <!-- STATUS UPDATE CONTROLS (Staff / Admin or Creator) -->
              <div class="d-flex align-items-center gap-2">
                <select
                  v-model="statusUpdateValue"
                  class="form-select form-select-sm"
                  style="width: auto;"
                  @change="updateTicketStatus"
                >
                  <option value="open">Mark as Open</option>
                  <option value="in_progress">Mark In Progress</option>
                  <option value="resolved">Mark as Resolved</option>
                  <option value="closed">Close Ticket</option>
                </select>
              </div>
            </div>

            <!-- THREADED MESSAGES TIMELINE -->
            <div class="mb-4">
              <h6 class="fw-bold text-dark mb-3">
                <i class="bi bi-chat-left-dots me-1 text-primary"></i> Conversation History
              </h6>

              <div class="d-flex flex-column gap-3">
                <div
                  v-for="msg in selectedTicket.messages"
                  :key="msg.id"
                  class="p-3 rounded-3 border"
                  :class="msg.is_staff_reply ? 'bg-primary bg-opacity-10 border-primary border-opacity-25 ms-4' : 'bg-white me-4'"
                >
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <div
                        class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold small"
                        :class="msg.is_staff_reply ? 'bg-primary' : 'bg-secondary'"
                        style="width: 28px; height: 28px; font-size: 0.75rem;"
                      >
                        {{ msg.user ? msg.user.name.charAt(0) : 'U' }}
                      </div>
                      <div>
                        <span class="fw-semibold text-dark small">{{ msg.user ? msg.user.name : 'User' }}</span>
                        <span v-if="msg.is_staff_reply" class="badge bg-primary text-white ms-1 small" style="font-size: 0.65rem;">
                          Support Staff
                        </span>
                      </div>
                    </div>
                    <span class="text-muted small" style="font-size: 0.72rem;">{{ formatDate(msg.created_at) }}</span>
                  </div>
                  <div class="text-dark small" style="white-space: pre-wrap; line-height: 1.5;">
                    {{ msg.message }}
                  </div>
                </div>
              </div>
            </div>

            <!-- REPLY INPUT COMPOSER -->
            <div class="p-3 bg-light rounded-3 border">
              <label class="form-label small fw-semibold text-dark mb-2">Add Reply</label>
              <textarea
                v-model="replyMessage"
                class="form-control mb-2"
                rows="3"
                placeholder="Type your reply or additional details here..."
              ></textarea>
              <div class="d-flex justify-content-end">
                <button
                  class="btn btn-primary btn-sm px-4"
                  :disabled="sendingReply || !replyMessage.trim()"
                  @click="sendReply"
                >
                  <span v-if="sendingReply" class="spinner-border spinner-border-sm me-1" role="status"></span>
                  <i v-else class="bi bi-send me-1"></i>
                  <span>{{ sendingReply ? 'Sending...' : 'Send Reply' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Support',
  data() {
    return {
      branding: {
        app_name: 'SR Solution',
        app_short_name: 'SR',
        app_tagline: '',
      },
      activeTab: 'tickets',
      loading: false,
      tickets: [],
      currentPage: 1,
      lastPage: 1,
      totalTickets: 0,
      perPage: 15,
      counts: {
        total: 0,
        open: 0,
        in_progress: 0,
        resolved: 0,
        closed: 0,
      },
      filters: {
        search: '',
        status: 'all',
        priority: 'all',
        category: 'all',
      },
      showCreateModal: false,
      submittingTicket: false,
      createError: null,
      newTicket: {
        subject: '',
        category: '',
        priority: 'medium',
        description: '',
      },
      selectedTicket: null,
      replyMessage: '',
      sendingReply: false,
      statusUpdateValue: 'open',

      // System updates tab data
      selectedUpdateCategory: 'All Updates',
      updateCategories: [
        'All Updates',
        'Live Chat',
        'WhatsApp & Meta',
        'Campaigns',
        'Security & RBAC',
        'Analytics & Data',
      ],
      features: [
        {
          title: 'Automated Flow Greeting & Template Reply Auto-Responder',
          category: 'WhatsApp & Meta',
          badgeClass: 'bg-success text-white',
          icon: 'bi-robot',
          releaseDate: 'September 2026',
          highlight: true,
          description:
            'When a campaign message is dispatched from the Flow tab on the Add WhatsApp Template modal, the system automatically auto-responds to customer replies with the flow\'s initial Greeting step (e.g. requesting debtor ID or verification). Subsequent replies advance through decision branches or linear steps. Messages sent via the standard Template tab do not trigger flow auto-replies, and opt-out responses (such as STOP) are strictly excluded to ensure compliance.',
          howToUse:
            'In Campaign Details, click "Add WhatsApp Template" and switch to the "Flow" tab to select a saved flow. When recipients reply to the dispatched flow message, the Greeting step is automatically delivered and logged in Live Chat for agent review.',
          actionText: 'Manage WhatsApp Flows',
          route: 'whatsapp-flows',
        },
        {
          title: 'Interactive WhatsApp Template Previews in Flow & Campaign Modals',
          category: 'Campaigns',
          badgeClass: 'bg-info text-white',
          icon: 'bi-phone-flip',
          releaseDate: 'September 2026',
          highlight: true,
          description:
            'Both the "Create WhatsApp Flow" modal and the Campaign "Add WhatsApp Template" modal (on both Template and Flow tabs) now feature side-by-side smartphone previews. Preview header media (images, videos, documents), bold headers, variable substitution with sample data toggle, and quick-reply action buttons in real time before sending.',
          howToUse:
            'Open WhatsApp Flows and click "New Flow" to see the live right-hand preview update as you pick approved templates. In Campaign Details, the Add WhatsApp Template modal now provides full smartphone previews for both individual templates and flows.',
          actionText: 'View WhatsApp Flows',
          route: 'whatsapp-flows',
        },
        {
          title: 'Live Chat CRM Directory Search & Client Discovery',
          category: 'Live Chat',
          badgeClass: 'bg-primary text-white',
          icon: 'bi-chat-dots-fill',
          releaseDate: 'September 2026',
          highlight: true,
          description:
            'Live Chat search now queries both active conversations and all CRM clients in your assigned bank and department. Seamlessly discover clients and start a chat with one click.',
          howToUse:
            'Navigate to Live Chat, type any client name, phone number, ID, or account number in the search bar. Directory clients will appear with a "Start chat" badge. Click any result to initiate the conversation.',
          actionText: 'Open Live Chat',
          route: 'chat',
        },
        {
          title: 'Inbound WhatsApp Client Linking & Creation',
          category: 'Live Chat',
          badgeClass: 'bg-primary text-white',
          icon: 'bi-person-plus-fill',
          releaseDate: 'September 2026',
          highlight: true,
          description:
            'When an unknown client messages your WhatsApp number, agents can directly link the session to an existing client record or create a brand new client with phone number prefilled.',
          howToUse:
            'In Live Chat, open any unknown session. Click the "Link Client" or "Create Client" button in the contact sidebar to attach or record the new client profile.',
          actionText: 'Go to Chat',
          route: 'chat',
        },
        {
          title: 'Direct Meta Cloud API & Multi-WABA Bank Isolation',
          category: 'WhatsApp & Meta',
          badgeClass: 'bg-success text-white',
          icon: 'bi-whatsapp',
          releaseDate: 'September 2026',
          highlight: true,
          description:
            'Direct Cloud API integration with dedicated WhatsApp Business Accounts (WABA) mapped specifically per bank, ensuring tenant isolation and verified sender names.',
          howToUse:
            'Administrators can view verified business numbers, registered templates, and health status in Settings under the WhatsApp & WABA sections.',
          actionText: 'View Settings',
          route: 'settings',
        },
        {
          title: 'Live Chat Emergency Lockdown & Custom Messages',
          category: 'Security & RBAC',
          badgeClass: 'bg-danger text-white',
          icon: 'bi-shield-lock-fill',
          releaseDate: 'September 2026',
          highlight: true,
          description:
            'Instantly lock down live chat messaging during emergencies, maintenance, or holiday hours with a custom notification displayed to agents and clients.',
          howToUse:
            'Head to Settings -> System Settings. Toggle the "Lock All Live Chats" switch and customize the disabled placeholder message.',
          actionText: 'System Settings',
          route: 'settings',
        },
        {
          title: 'Fair-Share Campaign Dispatcher & Queue Monitoring',
          category: 'Campaigns',
          badgeClass: 'bg-info text-white',
          icon: 'bi-cpu-fill',
          releaseDate: 'August 2026',
          highlight: true,
          description:
            'Smart campaign dispatcher prevents worker starvation and balances message throughput fairly across multiple concurrent campaigns with real-time queue health monitoring.',
          howToUse:
            'Monitor worker load, retry failed jobs, and inspect queue depths from the Queue Monitor console.',
          actionText: 'Queue Monitor',
          route: 'queue-jobs',
        },
        {
          title: 'WhatsApp Template Phone Simulation & Export',
          category: 'Campaigns',
          badgeClass: 'bg-info text-white',
          icon: 'bi-phone',
          releaseDate: 'August 2026',
          highlight: true,
          description:
            'Realistic smartphone mockups preview template variables in real-time. Export approved templates to CSV/Excel for compliance archives.',
          howToUse:
            'When creating or editing campaigns, click "Preview on Phone" to test variable substitution, or use the Export button to download template catalogs.',
          actionText: 'View Campaigns',
          route: 'campaigns',
        },
        {
          title: 'Client Multi-Batch Assignment & Combinatorial Filtering',
          category: 'Analytics & Data',
          badgeClass: 'bg-secondary text-white',
          icon: 'bi-layers-fill',
          releaseDate: 'August 2026',
          highlight: true,
          description:
            'Clients can seamlessly belong to multiple import batches. Target campaigns by specific batch combinations without client duplication or conflicting states.',
          howToUse:
            'Go to Clients or Import Data. Select batches from the filter dropdown to cross-reference overlapping client cohorts.',
          actionText: 'View Clients',
          route: 'clients',
        },
        {
          title: 'Analytics Dashboard with Dynamic Timeframes & Cost Tracking',
          category: 'Analytics & Data',
          badgeClass: 'bg-secondary text-white',
          icon: 'bi-bar-chart-line-fill',
          releaseDate: 'August 2026',
          highlight: true,
          description:
            'Interactive charts for Delivered (Read) vs Delivered (Unread) messages, dynamic date range filtering (Today, 7D, 30D, Custom), and South Africa Meta list rate cost analytics.',
          howToUse:
            'Open Analytics / Reports, switch between tabs to analyze conversion rates, delivery efficiency, and cost breakdowns.',
          actionText: 'Explore Analytics',
          route: 'analytics',
        },
        {
          title: 'Dual-Authorization Sensitive Data Export Workflow',
          category: 'Security & RBAC',
          badgeClass: 'bg-danger text-white',
          icon: 'bi-download',
          releaseDate: 'July 2026',
          highlight: true,
          description:
            'Strict governance workflow requiring written justification and dual manager approval before sensitive client data files can be exported or downloaded.',
          howToUse:
            'Submit export requests directly from the Exports console. Designated approvers will review and grant one-time download tokens.',
          actionText: 'Export Requests',
          route: 'export-requests',
        },
        {
          title: 'Centralized Security Incidents & Compliance Console',
          category: 'Security & RBAC',
          badgeClass: 'bg-danger text-white',
          icon: 'bi-shield-exclamation',
          releaseDate: 'July 2026',
          highlight: true,
          description:
            'Real-time tracking of security alerts, role escalations, unauthorized access attempts, and regulatory compliance logs.',
          howToUse:
            'Security managers can view open incidents, assign severity levels, and log remediation steps in the Incidents console.',
          actionText: 'Incidents Console',
          route: 'security-incidents',
        },
      ],
    };
  },
  computed: {
    filteredFeatures() {
      if (this.selectedUpdateCategory === 'All Updates') {
        return this.features;
      }
      return this.features.filter(f => f.category === this.selectedUpdateCategory);
    },
    systemName() {
      return this.branding.app_name || 'SR Solution';
    },
  },
  mounted() {
    this.loadBranding();
    this.fetchTickets();
    window.addEventListener('branding-updated', this.handleBrandingUpdated);
  },
  beforeUnmount() {
    window.removeEventListener('branding-updated', this.handleBrandingUpdated);
  },
  methods: {
    handleBrandingUpdated(e) {
      if (e?.detail) {
        this.applyBranding(e.detail);
      }
    },
    applyBranding(branding = {}) {
      this.branding = {
        app_name: branding.app_name || 'SR Solution',
        app_short_name: branding.app_short_name || 'SR',
        app_tagline: branding.app_tagline || '',
      };
    },
    async loadBranding() {
      const stored = localStorage.getItem('nexus_branding');
      if (stored) {
        try {
          this.applyBranding(JSON.parse(stored));
        } catch (_) {}
      }
      try {
        const res = await axios.get('/api/settings/branding');
        if (res.data) {
          this.applyBranding(res.data);
        }
      } catch (_) {}
    },
    async fetchTickets(page = 1) {
      this.loading = true;
      try {
        const res = await axios.get('/api/support/tickets', {
          params: {
            page,
            search: this.filters.search,
            status: this.filters.status,
            priority: this.filters.priority,
            category: this.filters.category,
          },
        });
        this.tickets = res.data.data;
        this.currentPage = res.data.current_page;
        this.lastPage = res.data.last_page;
        this.totalTickets = res.data.total;
        this.counts = res.data.counts || this.counts;
      } catch (err) {
        console.error('Failed to fetch support tickets:', err);
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters.search = '';
      this.filters.status = 'all';
      this.filters.priority = 'all';
      this.filters.category = 'all';
      this.fetchTickets(1);
    },
    openCreateTicketModal() {
      this.createError = null;
      this.newTicket = {
        subject: '',
        category: '',
        priority: 'medium',
        description: '',
      };
      this.showCreateModal = true;
    },
    async submitCreateTicket() {
      if (!this.newTicket.subject.trim() || !this.newTicket.description.trim() || !this.newTicket.category) {
        this.createError = 'Please fill out all required fields.';
        return;
      }
      this.submittingTicket = true;
      this.createError = null;
      try {
        await axios.post('/api/support/tickets', this.newTicket);
        this.showCreateModal = false;
        this.fetchTickets(1);
      } catch (err) {
        this.createError = err.response?.data?.message || 'Failed to submit ticket. Please check input.';
      } finally {
        this.submittingTicket = false;
      }
    },
    async openTicketDetails(ticket) {
      try {
        const res = await axios.get(`/api/support/tickets/${ticket.id}`);
        this.selectedTicket = res.data;
        this.statusUpdateValue = this.selectedTicket.status;
        this.replyMessage = '';
      } catch (err) {
        console.error('Failed to load ticket details:', err);
      }
    },
    async sendReply() {
      if (!this.replyMessage.trim() || !this.selectedTicket) return;
      this.sendingReply = true;
      try {
        const res = await axios.post(`/api/support/tickets/${this.selectedTicket.id}/reply`, {
          message: this.replyMessage,
        });
        if (!this.selectedTicket.messages) {
          this.selectedTicket.messages = [];
        }
        this.selectedTicket.messages.push(res.data);
        this.replyMessage = '';
        this.fetchTickets(this.currentPage);
      } catch (err) {
        console.error('Failed to send reply:', err);
      } finally {
        this.sendingReply = false;
      }
    },
    async updateTicketStatus() {
      if (!this.selectedTicket) return;
      try {
        const res = await axios.patch(`/api/support/tickets/${this.selectedTicket.id}/status`, {
          status: this.statusUpdateValue,
        });
        this.selectedTicket.status = res.data.status;
        this.selectedTicket.resolved_at = res.data.resolved_at;
        this.fetchTickets(this.currentPage);
      } catch (err) {
        console.error('Failed to update ticket status:', err);
      }
    },
    statusBadgeClass(status) {
      switch (status) {
        case 'open':
          return 'bg-warning text-dark';
        case 'in_progress':
          return 'bg-info text-dark';
        case 'resolved':
          return 'bg-success text-white';
        case 'closed':
          return 'bg-secondary text-white';
        default:
          return 'bg-light text-dark';
      }
    },
    formatStatus(status) {
      if (!status) return '';
      return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    },
    priorityBadgeClass(priority) {
      switch (priority) {
        case 'urgent':
          return 'bg-danger text-white';
        case 'high':
          return 'bg-warning text-dark';
        case 'medium':
          return 'bg-primary text-white';
        case 'low':
          return 'bg-light text-secondary border';
        default:
          return 'bg-light text-dark';
      }
    },
    priorityIcon(priority) {
      switch (priority) {
        case 'urgent':
          return 'bi-exclamation-octagon-fill';
        case 'high':
          return 'bi-arrow-up-circle-fill';
        case 'medium':
          return 'bi-dash-circle';
        case 'low':
          return 'bi-arrow-down-circle';
        default:
          return 'bi-circle';
      }
    },
    capitalize(str) {
      if (!str) return '';
      return str.charAt(0).toUpperCase() + str.slice(1);
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
  },
};
</script>

<style scoped>
.support-page-container {
  width: 100%;
}

.custom-support-tabs .nav-link {
  color: #64748b;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.custom-support-tabs .nav-link:hover {
  color: #0f172a;
  background-color: #f8fafc;
}

.custom-support-tabs .nav-link.active {
  color: #0284c7;
  background-color: #e0f2fe;
}

.stat-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08) !important;
}

.feature-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: 1px solid rgba(226, 232, 240, 0.8) !important;
}

.feature-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.1) !important;
  border-color: #cbd5e1 !important;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
