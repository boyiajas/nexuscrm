<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h4 mb-0"><i class="bi bi-chat-dots me-2"></i>WhatsApp Replies</h2>
      <button class="btn btn-outline-secondary btn-sm" @click="fetchReplies">
        <i class="bi bi-arrow-repeat me-1"></i> Refresh
      </button>
    </div>

    <div class="card shadow-sm border mb-4">
      <div class="card-body p-0">
        <TableLoadingWrapper :loading="loading" message="Loading WhatsApp replies...">
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th class="ps-4">Client</th>
              <th>Phone</th>
              <th>Departments</th>
              <th>Unread</th>
              <th>Last Message</th>
              <th>Updated</th>
              <th class="text-end pe-4">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in replies" :key="r.id">
              <td class="ps-4 py-3">{{ r.client_name }}</td>
              <td>{{ r.phone || '-' }}</td>
              <td>{{ r.departments || '-' }}</td>
              <td>
                <span
                  class="badge"
                  :class="r.unread_count > 0 ? 'bg-danger' : 'bg-secondary'"
                >
                  {{ r.unread_count || 0 }}
                </span>
              </td>
              <td class="text-truncate" style="max-width: 260px;">
                {{ r.last_response || r.last_message || '-' }}
              </td>
              <td>{{ r.last_response_at || '-' }}</td>
              <td class="text-end pe-4">
                <button
                  class="btn btn-light text-secondary border-0 p-1 px-2"
                  title="Open chat"
                  @click="openChat(r)"
                  :disabled="!r.client_id"
                >
                  <i class="bi bi-chat-dots"></i>
                </button>
              </td>
            </tr>
            <tr v-if="!loading && replies.length === 0">
              <td colspan="7" class="text-center text-muted py-5">
                No replies yet.
              </td>
            </tr>
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

export default {
  name: 'WhatsappReplies',
  components: {
    TableLoadingWrapper,
  },
  data() {
    return {
      replies: [],
      loading: false,
    };
  },
  mounted() {
    this.fetchReplies();
  },
  methods: {
    fetchReplies() {
      this.loading = true;
      axios.get('/api/dashboard/whatsapp-replies').then((res) => {
        this.replies = res.data || [];
      }).finally(() => {
        this.loading = false;
      });
    },
    openChat(row) {
      if (!row.client_id) return;
      this.$router.push({ name: 'chat', query: { client_id: row.client_id } });
    },
  },
};
</script>
