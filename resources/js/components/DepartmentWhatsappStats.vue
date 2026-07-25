<template>
  <div class="modal fade" tabindex="-1" ref="statsModalRef">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">WhatsApp Statistics: {{ departmentName }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Loading statistics...</p>
          </div>
          <div v-else>
            <div v-if="stats.length === 0" class="alert alert-info">
              No WhatsApp messaging data found for this department's configured numbers.
            </div>
            <div v-else>
              <div v-for="stat in stats" :key="stat.number" class="card mb-3 shadow-sm border-0">
                <div class="card-header bg-light fw-bold text-dark d-flex align-items-center">
                  <i class="bi bi-whatsapp text-success me-2"></i> {{ stat.number }}
                </div>
                <div class="card-body">
                  <div class="row text-center">
                    <div class="col">
                      <div class="text-muted small">Total Sent</div>
                      <div class="fs-4 fw-semibold">{{ stat.total_sent }}</div>
                    </div>
                    <div class="col">
                      <div class="text-muted small">Delivered</div>
                      <div class="fs-4 fw-semibold text-success">{{ stat.total_delivered }}</div>
                    </div>
                    <div class="col">
                      <div class="text-muted small">Read</div>
                      <div class="fs-4 fw-semibold text-primary">{{ stat.total_read }}</div>
                    </div>
                    <div class="col">
                      <div class="text-muted small">Responses</div>
                      <div class="fs-4 fw-semibold text-info">{{ stat.total_responses }}</div>
                    </div>
                    <div class="col">
                      <div class="text-muted small">Failed / Blocked</div>
                      <div class="fs-4 fw-semibold text-danger">{{ stat.total_failed }}</div>
                    </div>
                  </div>
                  <div class="progress mt-3" style="height: 10px;">
                    <div class="progress-bar bg-success" :style="{ width: percent(stat.total_delivered, stat.total_sent) + '%' }" title="Delivered"></div>
                    <div class="progress-bar bg-danger" :style="{ width: percent(stat.total_failed, stat.total_sent) + '%' }" title="Failed"></div>
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
import { createManagedModal, disposeManagedModal } from '../utils/modal';

export default {
  name: 'DepartmentWhatsappStats',
  data() {
    return {
      modal: null,
      departmentName: '',
      loading: false,
      stats: [],
    };
  },
  mounted() {
    this.modal = createManagedModal(this.$refs.statsModalRef);
  },
  beforeUnmount() {
    disposeManagedModal(this.modal);
  },
  methods: {
    percent(value, total) {
      if (!total) return 0;
      return Math.round((value / total) * 100);
    },
    open(department) {
      this.departmentName = department.name;
      this.loading = true;
      this.stats = [];
      this.modal.show();
      
      axios.get(`/api/departments/${department.id}/whatsapp-stats`).then(res => {
        this.stats = res.data;
      }).finally(() => {
        this.loading = false;
      });
    }
  }
}
</script>
