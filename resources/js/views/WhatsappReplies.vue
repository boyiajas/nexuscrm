<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h4 mb-0"><i class="bi bi-chat-dots me-2"></i>WhatsApp Replies</h2>
      <button class="btn btn-outline-secondary btn-sm" @click="fetchReplies">
        <i class="bi bi-arrow-repeat me-1"></i> Refresh
      </button>
    </div>

    <div class="card shadow-sm border mb-4">
      <!-- Search Filter Bar -->
      <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <div class="input-group input-group-sm" style="max-width: 350px;">
          <span class="input-group-text bg-light border-end-0">
            <i class="bi bi-search text-muted"></i>
          </span>
          <input
            v-model="search"
            type="text"
            class="form-control border-start-0 ps-0"
            placeholder="Search by client, phone, message..."
            @input="currentPage = 1"
          />
        </div>
        <div class="small text-muted">
          Total Replies: <strong>{{ filteredReplies.length }}</strong>
        </div>
      </div>

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
            <tr v-for="r in paginatedReplies" :key="r.id">
              <td class="ps-4 py-2 fw-semibold text-dark">{{ r.client_name }}</td>
              <td>{{ r.phone || '-' }}</td>
              <td>
                <span v-if="r.departments" class="badge bg-light text-dark border">
                  {{ r.departments }}
                </span>
                <span v-else class="text-muted">-</span>
              </td>
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
              <td class="small text-muted">{{ r.last_response_at || '-' }}</td>
              <td class="text-end pe-4">
                <button
                  class="btn btn-light text-secondary border-0 p-1 px-2"
                  title="Open chat"
                  @click="openChat(r)"
                  :disabled="!r.client_id && !r.id"
                >
                  <i class="bi bi-chat-dots fs-6"></i>
                </button>
              </td>
            </tr>
            <tr v-if="!loading && filteredReplies.length === 0">
              <td colspan="7" class="text-center text-muted py-5">
                No replies found.
              </td>
            </tr>
          </tbody>
        </table>
        </div>
        </TableLoadingWrapper>
      </div>

      <!-- Footer Strip with Rows Per Page & Pagination -->
      <div class="card-footer bg-white py-3 px-4 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 border-top">
        <div class="d-flex align-items-center gap-3">
          <small class="text-muted fw-medium">
            {{ paginationInfo }}
          </small>
          <div class="d-flex align-items-center gap-2">
            <label class="small text-muted fw-medium mb-0">Rows per page:</label>
            <select
              v-model.number="perPage"
              class="form-select form-select-sm border-secondary-subtle"
              style="width: 85px; font-size: 0.8rem;"
              @change="currentPage = 1"
            >
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="250">250</option>
              <option :value="500">500</option>
              <option :value="1000">1000</option>
            </select>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2">
          <button
            class="btn btn-sm btn-light border p-1 px-2"
            :disabled="currentPage <= 1"
            @click="currentPage--"
            title="Previous Page"
          >
            <i class="bi bi-chevron-left"></i>
          </button>
          <span class="small fw-semibold text-dark px-1">
            Page {{ currentPage }} of {{ totalPages }}
          </span>
          <button
            class="btn btn-sm btn-light border p-1 px-2"
            :disabled="currentPage >= totalPages"
            @click="currentPage++"
            title="Next Page"
          >
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
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
      search: '',
      perPage: 25,
      currentPage: 1,
    };
  },
  computed: {
    filteredReplies() {
      if (!this.search.trim()) {
        return this.replies;
      }
      const q = this.search.toLowerCase().trim();
      return this.replies.filter(r =>
        (r.client_name && r.client_name.toLowerCase().includes(q)) ||
        (r.phone && r.phone.includes(q)) ||
        (r.departments && r.departments.toLowerCase().includes(q)) ||
        (r.last_response && r.last_response.toLowerCase().includes(q))
      );
    },
    totalPages() {
      return Math.ceil(this.filteredReplies.length / this.perPage) || 1;
    },
    paginatedReplies() {
      const start = (this.currentPage - 1) * this.perPage;
      return this.filteredReplies.slice(start, start + this.perPage);
    },
    paginationInfo() {
      const total = this.filteredReplies.length;
      if (total === 0) return 'Showing 0 of 0 records';
      const start = (this.currentPage - 1) * this.perPage + 1;
      const end = Math.min(this.currentPage * this.perPage, total);
      return `Showing ${start} to ${end} of ${total} records`;
    },
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
      if (row.client_id) {
        this.$router.push({ name: 'chat', query: { client_id: row.client_id } });
      } else if (row.id) {
        this.$router.push({ name: 'chat', query: { session_id: row.id } });
      }
    },
  },
};
</script>
