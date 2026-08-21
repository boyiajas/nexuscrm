<template>
  <div class="row g-0 rounded overflow-hidden shadow-sm chat-wrapper" style="height: calc(100vh - 120px); border: 1px solid #dee2e6;">
    <!-- Sessions list -->
    <div class="col-md-4 border-end d-flex flex-column bg-white h-100">
      <!-- Sidebar Header -->
      <div class="sidebar-header d-flex justify-content-between align-items-center p-3">
        <div class="d-flex align-items-center">
          <div class="avatar bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
            <i class="bi bi-chat-text-fill"></i>
          </div>
          <span class="fw-semibold">Live Chats</span>
        </div>
        <select v-model="filterStatus" class="form-select form-select-sm w-auto shadow-none border-0 bg-transparent fw-semibold text-muted" @change="fetchSessions">
          <option value="all">All</option>
          <option value="active">Active</option>
          <option value="closed">Closed</option>
          <option value="unread">Unread</option>
          <option value="read">Read</option>
        </select>
      </div>

      <!-- Search Bar -->
      <div class="p-2 border-bottom sidebar-search">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0 text-muted">
            <i class="bi bi-search"></i>
          </span>
          <input
            v-model="sidebarSearch"
            type="text"
            class="form-control border-start-0 shadow-none bg-white"
            placeholder="Search name, phone, account..."
            @input="onSidebarSearchInput"
          />
        </div>
      </div>

      <!-- Chat List -->
      <div class="chat-list flex-grow-1 overflow-auto">
        <div
          v-for="session in sessions"
          :key="session.id"
          class="chat-list-item d-flex p-3 border-bottom position-relative"
          :class="{ 'active-chat': activeSession && activeSession.id === session.id }"
          @click="openSession(session, $event)"
        >
          <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
            <i class="bi bi-person-fill"></i>
          </div>
          <div class="flex-grow-1 overflow-hidden">
            <div class="d-flex justify-content-between align-items-baseline mb-1">
              <span class="fw-semibold text-truncate">{{ session.client_name }}</span>
              <small class="text-muted timestamp">{{ session.updated_at ? session.updated_at.split('T')[0] : '' }}</small>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted text-truncate d-block w-100 pe-2">
                <i v-if="session.last_message === 'quick reply'" class="bi bi-reply-fill text-muted me-1"></i>
                {{ session.last_message || 'No messages yet' }}
              </small>
              <div class="d-flex align-items-center gap-2">
                <span v-if="session.unread_count > 0" class="badge rounded-pill bg-success unread-badge">{{ session.unread_count }}</span>
                <div class="dropdown chat-list-dropdown">
                  <i class="bi bi-chevron-down text-muted" style="cursor: pointer; font-size: 1.1rem; transform: translateY(2px); display: inline-block;" data-bs-toggle="dropdown" aria-expanded="false"></i>
                  <ul class="dropdown-menu shadow border-0" style="min-width: 220px;">
                    <li><a class="dropdown-item py-2" href="#" @click.prevent="showContactInfo(session)"><i class="bi bi-person-vcard text-primary me-3"></i>Client Info</a></li>
                    <li><a class="dropdown-item py-2" href="#" @click.prevent="toggleSearch()"><i class="bi bi-search text-muted me-3"></i>Search</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li class="dropdown-header text-uppercase small fw-bold text-muted">Opt-In Status</li>
                    <li>
                      <a class="dropdown-item py-1 text-success d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(session, 'yes')">
                        <span><i class="bi bi-check-circle-fill me-2"></i>Opt-In: Yes</span>
                        <i v-if="(session.client?.opt_in || session.opt_in) === 'yes'" class="bi bi-check2"></i>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item py-1 text-danger d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(session, 'no')">
                        <span><i class="bi bi-x-circle-fill me-2"></i>Opt-In: No</span>
                        <i v-if="(session.client?.opt_in || session.opt_in) === 'no'" class="bi bi-check2"></i>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item py-1 text-secondary d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(session, 'none')">
                        <span><i class="bi bi-dash-circle me-2"></i>Opt-In: None</span>
                        <i v-if="!session.client?.opt_in || session.client?.opt_in === 'none' || session.opt_in === 'none'" class="bi bi-check2"></i>
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="#" @click.prevent="clearChat(session)"><i class="bi bi-eraser text-muted me-3"></i>Clear chat</a></li>
                    <li><a class="dropdown-item py-2 text-danger" href="#" @click.prevent="deleteSession(session)"><i class="bi bi-trash text-danger me-3"></i>Delete chat</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="#" @click.prevent="blockClient(session)"><i class="bi bi-slash-circle text-danger me-3"></i>Block</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="sessions.length === 0" class="p-4 text-center text-muted small">
          No chat sessions found.
        </div>
      </div>
    </div>

    <!-- Chat window -->
    <div class="col-md-8 d-flex flex-column bg-chat h-100">
      <div v-if="activeSession" class="d-flex flex-column h-100">
        <!-- Chat Header -->
        <div class="chat-header p-3 d-flex align-items-center border-bottom shadow-sm z-index-1">
          <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
            <i class="bi bi-person-fill"></i>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">{{ activeSession.client_name }}</div>
              <span v-if="activeSession.client?.easy_pay_number" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1" title="Easy Pay Number">
                <i class="bi bi-credit-card me-1"></i>EasyPay: {{ activeSession.client.easy_pay_number }}
              </span>
              <span v-if="(activeSession.client?.opt_in || activeSession.opt_in) === 'yes'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" title="Opted In">
                <i class="bi bi-check-circle-fill me-1"></i>Opt-In: Yes
              </span>
              <span v-else-if="(activeSession.client?.opt_in || activeSession.opt_in) === 'no'" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1" title="Opted Out">
                <i class="bi bi-x-circle-fill me-1"></i>Opt-In: No
              </span>
              <span v-else class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-1" title="Unset">
                <i class="bi bi-dash-circle me-1"></i>Opt-In: None
              </span>
            </div>
            <small class="text-muted">
              {{ activeSession.platform }}
              <span v-if="activeSession.client?.easy_pay_number"> • EasyPay: <strong class="text-dark">{{ activeSession.client.easy_pay_number }}</strong></span>
              <span v-if="activeSession.agent"> • Assigned to {{ activeSession.agent.name }}</span>
            </small>
          </div>
          <div class="ms-auto text-muted d-flex gap-3 fs-5 align-items-center">
            <i class="bi bi-search" style="cursor: pointer;" title="Search"></i>
            <div class="dropdown">
              <i class="bi bi-three-dots-vertical" style="cursor: pointer;" data-bs-toggle="dropdown" aria-expanded="false" title="Menu"></i>
              <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 220px;">
                <li><a class="dropdown-item py-2" href="#" @click.prevent="showContactInfo(activeSession)"><i class="bi bi-person-vcard me-2 text-primary"></i>Client Info</a></li>
                <li><a class="dropdown-item py-2" href="#" @click.prevent="toggleSearch()"><i class="bi bi-search me-2 text-muted"></i>Search</a></li>
                <li><hr class="dropdown-divider"></li>
                <li class="dropdown-header text-uppercase small fw-bold text-muted">Set Opt-In Status</li>
                <li>
                  <a class="dropdown-item py-1 text-success d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(activeSession, 'yes')">
                    <span><i class="bi bi-check-circle-fill me-2"></i>Opt-In: Yes</span>
                    <i v-if="(activeSession.client?.opt_in || activeSession.opt_in) === 'yes'" class="bi bi-check2"></i>
                  </a>
                </li>
                <li>
                  <a class="dropdown-item py-1 text-danger d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(activeSession, 'no')">
                    <span><i class="bi bi-x-circle-fill me-2"></i>Opt-In: No</span>
                    <i v-if="(activeSession.client?.opt_in || activeSession.opt_in) === 'no'" class="bi bi-check2"></i>
                  </a>
                </li>
                <li>
                  <a class="dropdown-item py-1 text-secondary d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(activeSession, 'none')">
                    <span><i class="bi bi-dash-circle me-2"></i>Opt-In: None</span>
                    <i v-if="!activeSession.client?.opt_in || activeSession.client?.opt_in === 'none' || activeSession.opt_in === 'none'" class="bi bi-check2"></i>
                  </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2" href="#" @click.prevent="clearChat(activeSession)"><i class="bi bi-eraser me-2 text-muted"></i>Clear chat</a></li>
                <li><a class="dropdown-item py-2 text-danger" href="#" @click.prevent="deleteSession(activeSession)"><i class="bi bi-trash text-danger me-2"></i>Delete chat</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="#" @click.prevent="blockClient(activeSession)"><i class="bi bi-slash-circle text-danger me-2"></i>Block</a></li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Search Bar Overlay -->
        <div v-if="isSearching" class="p-2 border-bottom bg-white d-flex align-items-center" style="z-index: 2;">
          <i class="bi bi-search text-muted ms-2"></i>
          <input type="text" class="form-control border-0 shadow-none ms-2" placeholder="Search messages..." v-model="searchQuery" ref="searchInput">
          <i class="bi bi-x-lg text-muted ms-2" style="cursor:pointer;" @click="closeSearch()"></i>
        </div>

        <!-- Messages Area -->
        <div class="chat-messages flex-grow-1 overflow-auto p-4" ref="messagesContainer">
          <div
            v-for="msg in filteredMessages"
            :key="msg.id"
            class="message-wrapper d-flex mb-1"
            :class="msg.sender === 'agent' ? 'justify-content-end' : 'justify-content-start'"
          >
            <div class="chat-bubble position-relative shadow-sm" :class="msg.sender === 'agent' ? 'bubble-out' : 'bubble-in'">
              <div class="message-content">
                {{ msg.content }}
              </div>
              <div class="message-meta d-flex justify-content-end align-items-center mt-1">
                <small class="timestamp text-muted ms-3">
                  {{ formatTime(msg.sent_at || msg.created_at) }}
                </small>
                <i v-if="msg.sender === 'agent'" class="bi bi-check-all ms-1 text-primary" style="font-size: 1.1em;"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Composer -->
        <div class="chat-composer p-3 border-top">
          <form @submit.prevent="sendMessage" class="d-flex align-items-center">
            <button type="button" class="btn btn-link text-muted fs-4 p-0 me-3 shadow-none"><i class="bi bi-emoji-smile"></i></button>
            <button type="button" class="btn btn-link text-muted fs-4 p-0 me-3 shadow-none"><i class="bi bi-paperclip"></i></button>
            <input
              v-model="newMessage"
              type="text"
              class="form-control rounded-pill border-0 shadow-none py-2 px-4 flex-grow-1 me-3"
              style="background-color: #ffffff;"
              placeholder="Type a message"
              :disabled="!activeSession || !canManageChat"
            />
            <button type="submit" class="btn text-muted fs-4 p-0 shadow-none" :disabled="!activeSession || !newMessage.trim() || !canManageChat">
              <i class="bi bi-send-fill" :class="{'text-primary': newMessage.trim()}"></i>
            </button>
          </form>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="h-100 d-flex flex-column align-items-center justify-content-center bg-chat text-muted">
        <div class="text-center">
          <i class="bi bi-whatsapp" style="font-size: 5rem; color: #d1d7db;"></i>
          <h4 class="mt-4 fw-light text-secondary">WhatsApp Web</h4>
          <p class="small text-muted mt-2">Select a chat session to start messaging.</p>
        </div>
      </div>
    </div>
    
    <!-- Client Info Modal -->
    <div class="modal fade" id="contactInfoModal" tabindex="-1" ref="contactInfoModal">
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow border-0">
          <div class="modal-header border-bottom py-3">
            <h5 class="modal-title h6 mb-0 text-dark fw-bold">
              <i class="bi bi-person-vcard text-primary me-2"></i>Client Information
            </h5>
            <button type="button" class="btn-close" @click="closeContactInfoModal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4" v-if="contactInfoSession">
            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
              <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 52px; height: 52px; font-size: 1.8rem;">
                <i class="bi bi-person-fill"></i>
              </div>
              <div>
                <h5 class="mb-1 text-dark fw-bold">{{ contactInfoSession.client_name }}</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <span v-if="contactInfoSession.client?.status" class="badge bg-secondary">
                    {{ contactInfoSession.client.status }}
                  </span>
                  <span v-if="(contactInfoSession.client?.opt_in || contactInfoSession.opt_in) === 'yes'" class="badge bg-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Opt-In: Yes
                  </span>
                  <span v-else-if="(contactInfoSession.client?.opt_in || contactInfoSession.opt_in) === 'no'" class="badge bg-danger">
                    <i class="bi bi-x-circle-fill me-1"></i>Opt-In: No
                  </span>
                  <span v-else class="badge bg-secondary">
                    <i class="bi bi-dash-circle me-1"></i>Opt-In: None
                  </span>
                </div>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Easy Pay Number</label>
                <div class="fw-bold text-primary font-monospace bg-light p-2 rounded border">
                  {{ contactInfoSession.client?.easy_pay_number || '-' }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Account Number</label>
                <div class="fw-semibold text-dark bg-light p-2 rounded border font-monospace">
                  {{ contactInfoSession.client?.account_number || '-' }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">ID Number</label>
                <div class="text-dark bg-light p-2 rounded border small">
                  {{ contactInfoSession.client?.id_number || '-' }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Phone Number</label>
                <div class="text-dark bg-light p-2 rounded border small">
                  {{ contactInfoSession.client?.phone || contactInfoSession.phone || '-' }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Outstanding Balance</label>
                <div class="fw-semibold text-danger bg-light p-2 rounded border">
                  {{ formatCurrency(contactInfoSession.client?.outstanding_balance) }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Arrears Amount</label>
                <div class="fw-semibold text-dark bg-light p-2 rounded border">
                  {{ formatCurrency(contactInfoSession.client?.arrears_amount) }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Installment Amount</label>
                <div class="text-dark bg-light p-2 rounded border small">
                  {{ formatCurrency(contactInfoSession.client?.installment_amount) }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Bank / Institution</label>
                <div class="text-dark bg-light p-2 rounded border small text-truncate">
                  {{ contactInfoSession.client?.bank?.name || contactInfoSession.client?.bank_name || '-' }}
                </div>
              </div>

              <div class="col-12">
                <label class="small text-muted fw-medium d-block mb-1">Email Address</label>
                <div class="text-dark bg-light p-2 rounded border small">
                  {{ contactInfoSession.client?.email || '-' }}
                </div>
              </div>

              <div class="col-12" v-if="contactInfoSession.client?.opt_in_updated_at || contactInfoSession.client?.whatsapp_opted_in_at">
                <label class="small text-muted fw-medium d-block mb-1">Opt-In Updated Timestamp</label>
                <div class="text-muted bg-light p-2 rounded border small">
                  <i class="bi bi-clock me-1"></i>
                  {{ contactInfoSession.client?.opt_in_updated_at || contactInfoSession.client?.whatsapp_opted_in_at }}
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light py-2">
            <button type="button" class="btn btn-secondary btn-sm" @click="closeContactInfoModal">Close</button>
          </div>
        </div>
      </div>
    </div>
    
  </div>
</template>

<script>
import axios from '../axios';
import { notify } from '../utils/notify';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import './Chat.css';

export default {
  name: 'ChatView',
  data() {
    return {
      sessions: [],
      activeSession: null,
      messages: [],
      newMessage: '',
      filterStatus: 'all',
      sidebarSearch: '',
      searchTimeout: null,
      pollingInterval: null,
      isSearching: false,
      searchQuery: '',
      contactInfoSession: null,
      modalInstance: null,
      showClientInfoModal: false,
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
    currentRoleCodes() {
      if (Array.isArray(this.currentUser?.role_codes) && this.currentUser.role_codes.length) {
        return this.currentUser.role_codes;
      }

      return this.currentUser?.role ? [this.currentUser.role] : [];
    },
    canManageChat() {
      return this.hasPermission('send_whatsapp');
    },
    filteredMessages() {
      if (!this.searchQuery) return this.messages;
      const query = this.searchQuery.toLowerCase();
      return this.messages.filter(msg => msg.content && msg.content.toLowerCase().includes(query));
    }
  },
  mounted() {
    this.fetchSessions().then(() => {
      this.handleQueryClient();
    });
    this.startPolling();
  },
  beforeUnmount() {
    this.stopPolling();
    if (this.modalInstance) {
      try {
        disposeManagedModal(this.modalInstance);
      } catch (e) {
        // ignore
      }
      this.modalInstance = null;
    }
  },
  methods: {
    hasPermission(permCode) {
      if (!this.currentUser) return false;
      if (this.currentRoleCodes.includes('SUPER_ADMIN') || this.currentRoleCodes.includes('ADMIN')) {
        return true;
      }

      if (Array.isArray(this.currentUser.permission_codes)) {
        return this.currentUser.permission_codes.includes(permCode);
      }

      return false;
    },
    fetchSessions() {
      return axios
        .get('/api/chat/sessions', {
          params: {
            status: this.filterStatus,
            search: this.sidebarSearch,
            per_page: 100,
          },
        })
        .then((res) => {
          this.sessions = res.data.data || res.data;
        });
    },
    onSidebarSearchInput() {
      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout);
      }
      this.searchTimeout = setTimeout(() => {
        this.fetchSessions();
      }, 300);
    },
    openSession(session, event = null) {
      if (event && event.target.closest('.chat-list-dropdown')) {
        return;
      }
      axios.get(`/api/chat/sessions/${session.id}`).then((res) => {
        this.activeSession = res.data;
        this.messages = res.data.messages || [];
        this.$nextTick(this.scrollToBottom);
      });
    },
    startPolling() {
      this.stopPolling();
      this.pollingInterval = setInterval(() => {
        this.pollData();
      }, 5000);
    },
    stopPolling() {
      if (this.pollingInterval) {
        clearInterval(this.pollingInterval);
        this.pollingInterval = null;
      }
    },
    pollData() {
      // Soft refresh sidebar (only when not actively typing search)
      if (!this.sidebarSearch) {
        axios
          .get('/api/chat/sessions', {
            params: {
              status: this.filterStatus,
              search: this.sidebarSearch,
              per_page: 100,
            },
          })
          .then((res) => {
            this.sessions = res.data.data || res.data;
          });
      }

      // Soft refresh active session messages
      if (this.activeSession) {
        axios.get(`/api/chat/sessions/${this.activeSession.id}`).then((res) => {
          const fetchedMessages = res.data.messages || [];
          if (fetchedMessages.length > this.messages.length) {
            this.messages = fetchedMessages;
            this.$nextTick(this.scrollToBottom);
          }
        });
      }
    },
    handleQueryClient() {
      const clientId = this.$route.query.client_id;
      const sessionId = this.$route.query.session_id;

      if (sessionId) {
        axios.get(`/api/chat/sessions/${sessionId}`).then((res) => {
          this.activeSession = res.data;
          this.messages = res.data.messages || [];
          this.fetchSessions();
          this.$nextTick(this.scrollToBottom);
        }).catch((err) => {
          console.error('Unable to open chat session by ID', err);
        });
        return;
      }

      if (!clientId || !this.canManageChat) return;

      axios
        .post('/api/chat/session-for-client', {
          client_id: clientId,
          platform: 'whatsapp',
        })
        .then((res) => {
          this.activeSession = res.data;
          this.messages = res.data.messages || [];
          this.fetchSessions();
          this.$nextTick(this.scrollToBottom);
        })
        .catch((err) => {
          console.error('Unable to open chat for client', err);
        });
    },
    sendMessage() {
      if (!this.canManageChat) return;

      const content = this.newMessage.trim();
      if (!content || !this.activeSession) return;

      axios
        .post(`/api/chat/sessions/${this.activeSession.id}/messages`, {
          content,
          is_template: false,
        })
        .then((res) => {
          this.messages.push(res.data);
          this.newMessage = '';
          this.$nextTick(this.scrollToBottom);
          this.fetchSessions(); // refresh preview/unread
        });
    },
    scrollToBottom() {
      const el = this.$refs.messagesContainer;
      if (el) {
        el.scrollTop = el.scrollHeight;
      }
    },
    deleteSession(session) {
      if (!this.canManageChat || !session) return;
      if (confirm(`Are you sure you want to delete the chat session with ${session.client_name}? This will permanently remove the chat history.`)) {
        axios.delete(`/api/chat/sessions/${session.id}`).then(() => {
          if (this.activeSession && this.activeSession.id === session.id) {
            this.activeSession = null;
            this.messages = [];
          }
          this.fetchSessions();
        }).catch((err) => {
          console.error('Failed to delete session', err);
          alert('Failed to delete chat session.');
        });
      }
    },
    clearChat(session) {
      if (!this.canManageChat || !session) return;
      if (confirm(`Are you sure you want to clear the chat history for ${session.client_name}?`)) {
        axios.post(`/api/chat/sessions/${session.id}/clear`).then(() => {
          if (this.activeSession && this.activeSession.id === session.id) {
            this.messages = [];
          }
          this.fetchSessions();
        }).catch((err) => {
          console.error('Failed to clear chat', err);
          alert('Failed to clear chat session.');
        });
      }
    },
    blockClient(session) {
      if (!this.canManageChat || !session) return;
      if (confirm(`Are you sure you want to block ${session.client_name}? They will be opted out of WhatsApp communications.`)) {
        axios.post(`/api/chat/sessions/${session.id}/block`).then(() => {
          if (this.activeSession && this.activeSession.id === session.id) {
            this.activeSession = null;
            this.messages = [];
          }
          this.fetchSessions();
        }).catch((err) => {
          console.error('Failed to block client', err);
          alert('Failed to block client.');
        });
      }
    },
    setOptIn(session, status) {
      if (!session) return;
      axios.post(`/api/chat/sessions/${session.id}/opt-in`, { opt_in: status }).then((res) => {
        if (session.client) {
          session.client.opt_in = status;
          session.client.opt_in_updated_at = res.data.opt_in_updated_at;
        }
        session.opt_in = status;
        if (this.activeSession && this.activeSession.id === session.id) {
          if (this.activeSession.client) {
            this.activeSession.client.opt_in = status;
            this.activeSession.client.opt_in_updated_at = res.data.opt_in_updated_at;
          }
          this.activeSession.opt_in = status;
        }
        notify.success(`Opt-In status updated to ${status.toUpperCase()}.`, 'Compliance');
      }).catch((err) => {
        console.error('Failed to update Opt-In status', err);
        notify.error(err.response?.data?.message || 'Failed to update Opt-In status.', 'Compliance');
      });
    },
    showContactInfo(session) {
      if (!session) return;
      const openModal = (data) => {
        this.contactInfoSession = data;
        this.$nextTick(() => {
          if (this.$refs.contactInfoModal) {
            if (!this.modalInstance) {
              this.modalInstance = createManagedModal(this.$refs.contactInfoModal);
            }
            if (this.modalInstance) {
              this.modalInstance.show();
            }
          }
        });
      };

      if (session.id) {
        axios.get(`/api/chat/sessions/${session.id}`).then((res) => {
          openModal(res.data);
        }).catch(() => {
          openModal(session);
        });
      } else {
        openModal(session);
      }
    },
    closeContactInfoModal() {
      if (this.modalInstance) {
        this.modalInstance.hide();
      }
    },
    formatCurrency(val) {
      if (val === null || val === undefined || val === '') return '-';
      const num = Number(val);
      if (isNaN(num)) return '-';
      return 'R ' + num.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    toggleSearch() {
      this.isSearching = true;
      this.$nextTick(() => {
        if (this.$refs.searchInput) {
          this.$refs.searchInput.focus();
        }
      });
    },
    closeSearch() {
      this.isSearching = false;
      this.searchQuery = '';
    },
    formatTime(datetimeString) {
      if (!datetimeString) return '';
      const date = new Date(datetimeString);
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
  },
};
</script>

<style scoped>
/* Preserve existing multiselect tag color if used elsewhere in the view */
:deep(.multiselect__tag) {
  background: #0d6efd;
}
</style>
