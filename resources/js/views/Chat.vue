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
        </select>
      </div>

      <!-- Search Bar -->
      <div class="p-2 border-bottom sidebar-search">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0 text-muted">
            <i class="bi bi-search"></i>
          </span>
          <input type="text" class="form-control border-start-0 shadow-none bg-white" placeholder="Search or start new chat">
        </div>
      </div>

      <!-- Chat List -->
      <div class="chat-list flex-grow-1 overflow-auto">
        <div
          v-for="session in sessions"
          :key="session.id"
          class="chat-list-item d-flex p-3 border-bottom position-relative"
          :class="{ 'active-chat': activeSession && activeSession.id === session.id }"
          @click="openSession(session)"
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
              <small class="text-muted text-truncate d-block w-100 me-2">
                <i v-if="session.last_message === 'quick reply'" class="bi bi-reply-fill text-muted me-1"></i>
                {{ session.last_message || 'No messages yet' }}
              </small>
              <span v-if="session.unread_count > 0" class="badge rounded-pill bg-success unread-badge">{{ session.unread_count }}</span>
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
            <div class="fw-semibold">{{ activeSession.client_name }}</div>
            <small class="text-muted">
              {{ activeSession.platform }} <span v-if="activeSession.agent">• Assigned to {{ activeSession.agent.name }}</span>
            </small>
          </div>
          <div class="ms-auto text-muted d-flex gap-3 fs-5">
            <i class="bi bi-search" style="cursor: pointer;"></i>
            <i class="bi bi-three-dots-vertical" style="cursor: pointer;"></i>
          </div>
        </div>

        <!-- Messages Area -->
        <div class="chat-messages flex-grow-1 overflow-auto p-4" ref="messagesContainer">
          <div
            v-for="msg in messages"
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
  </div>
</template>

<script>
import axios from '../axios';
import './Chat.css';

export default {
  name: 'ChatView',
  data() {
    return {
      sessions: [],
      activeSession: null,
      messages: [],
      newMessage: '',
      filterStatus: 'active',
    };
  },
  computed: {
    canManageChat() {
      const stored = localStorage.getItem('nexus_user');
      if (!stored) return false;

      const role = JSON.parse(stored)?.role;
      return ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CALL_CENTRE_MANAGER', 'TEAM_LEADER', 'AGENT', 'STAFF'].includes(role);
    },
  },
  mounted() {
    this.fetchSessions().then(() => {
      this.handleQueryClient();
    });
  },
  methods: {
    fetchSessions() {
      return axios
        .get('/api/chat/sessions', { params: { status: this.filterStatus } })
        .then((res) => {
          this.sessions = res.data.data || res.data;
        });
    },
    openSession(session) {
      axios.get(`/api/chat/sessions/${session.id}`).then((res) => {
        this.activeSession = res.data;
        this.messages = res.data.messages || [];
        this.$nextTick(this.scrollToBottom);
      });
    },
    handleQueryClient() {
      const clientId = this.$route.query.client_id;
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
