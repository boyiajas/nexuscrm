<template>
  <div class="row g-3">
    <!-- Sessions list -->
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-chat-dots me-2"></i> Live Chats</span>
          <select v-model="filterStatus" class="form-select form-select-sm w-auto" @change="fetchSessions">
            <option value="all">All</option>
            <option value="active">Active</option>
            <option value="closed">Closed</option>
          </select>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <li
              v-for="session in sessions"
              :key="session.id"
              class="list-group-item list-group-item-action"
              :class="{ 'bg-light': activeSession && activeSession.id === session.id }"
              @click="openSession(session)"
              style="cursor: pointer;"
            >
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold">
                    {{ session.client_name }}
                    <span v-if="session.status === 'active'" class="badge bg-success ms-1">Active</span>
                    <span v-else class="badge bg-secondary ms-1">Closed</span>
                  </div>
                  <small class="text-muted">
                    {{ session.platform }} ·
                    {{ session.agent ? session.agent.name : 'Unassigned' }}
                  </small>
                  <div class="small mt-1 text-truncate">
                    {{ session.last_message || 'No messages yet' }}
                  </div>
                </div>
                <div class="text-end">
                  <span v-if="session.unread_count > 0" class="badge bg-danger">
                    {{ session.unread_count }}
                  </span>
                </div>
              </div>
            </li>
            <li v-if="sessions.length === 0" class="list-group-item text-muted">
              No chat sessions.
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Chat window -->
    <div class="col-md-8">
      <div class="card shadow-sm h-100 d-flex flex-column">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div v-if="activeSession">
            <div class="fw-semibold">{{ activeSession.client_name }}</div>
            <small class="text-muted">
              {{ activeSession.platform }} ·
              Agent: {{ activeSession.agent ? activeSession.agent.name : 'Unassigned' }}
            </small>
          </div>
          <div v-else>
            <span class="text-muted">Select a chat session</span>
          </div>
        </div>

        <div class="card-body flex-grow-1 overflow-auto" ref="messagesContainer">
          <div v-if="!activeSession" class="text-muted text-center mt-5">
            No session selected.
          </div>

          <div v-else>
            <div v-for="msg in messages" :key="msg.id" class="mb-3">
              <div
                class="d-inline-block px-3 py-2 rounded"
                :class="msg.sender === 'agent' ? 'bg-primary text-white' : 'bg-light border'"
              >
                <div class="small fw-semibold mb-1">
                  {{ msg.sender === 'agent' ? 'You' : (msg.sender === 'system' ? 'System' : activeSession.client_name) }}
                </div>
                <div class="small">{{ msg.content }}</div>
                <div class="small text-muted mt-1" style="font-size: 0.7rem;">
                  {{ msg.sent_at || msg.created_at }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Composer -->
        <div class="card-footer">
          <form @submit.prevent="sendMessage" class="d-flex gap-2">
            <input
              v-model="newMessage"
              type="text"
              class="form-control"
              placeholder="Type a message or choose a template..."
              :disabled="!activeSession"
            />
            <button class="btn btn-primary" type="submit" :disabled="!activeSession || !newMessage.trim()">
              Send
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';

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
  mounted() {
    this.fetchSessions();
  },
  methods: {
    fetchSessions() {
      axios
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
    sendMessage() {
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
  },
};
</script>
