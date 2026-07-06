<template>
  <div class="modal fade" tabindex="-1" ref="modalRef">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Request Sensitive Export</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-warning small">
            This export contains sensitive data. Provide a business justification before the request is submitted.
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Dataset</label>
              <input class="form-control" :value="config.datasetLabel || '-'" readonly />
            </div>
            <div class="col-md-6">
              <label class="form-label">Target</label>
              <input class="form-control" :value="targetDisplay" readonly />
            </div>
          </div>

          <div v-if="summaryRows.length" class="card border-0 bg-light mb-3">
            <div class="card-body py-3">
              <div class="row g-3">
                <div v-for="row in summaryRows" :key="row.label" class="col-md-6">
                  <div class="small text-muted text-uppercase">{{ row.label }}</div>
                  <div class="fw-semibold">{{ row.value || '-' }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label">Justification</label>
            <textarea
              v-model.trim="justification"
              class="form-control"
              rows="5"
              maxlength="2000"
              placeholder="Explain why this export is needed, who it is for, and what business purpose it serves."
            ></textarea>
            <div class="form-text">{{ justification.length }}/2000 characters</div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="submitting">
            Cancel
          </button>
          <button type="button" class="btn btn-primary" @click="submit" :disabled="submitting || justification.length < 10">
            <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
            {{ submitting ? 'Submitting...' : 'Submit Request' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import { downloadProtectedFile } from '../utils/download';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';

export default {
  name: 'ExportRequestModal',
  data() {
    return {
      modal: null,
      submitting: false,
      justification: '',
      config: {
        dataset: '',
        datasetLabel: '',
        targetType: null,
        targetId: null,
        filters: {},
        summaryRows: [],
        fallbackName: 'download.csv',
      },
    };
  },
  computed: {
    summaryRows() {
      return this.config.summaryRows || [];
    },
    targetDisplay() {
      if (this.config.targetType && this.config.targetId) {
        return `${this.config.targetType} #${this.config.targetId}`;
      }
      return 'General dataset';
    },
  },
  mounted() {
    this.modal = createManagedModal(this.$refs.modalRef);
  },
  beforeUnmount() {
    disposeManagedModal(this.modal);
  },
  methods: {
    open(config = {}) {
      this.config = {
        dataset: '',
        datasetLabel: '',
        targetType: null,
        targetId: null,
        filters: {},
        summaryRows: [],
        fallbackName: 'download.csv',
        ...config,
      };
      this.justification = '';
      this.modal.show();
    },
    async submit() {
      if (this.justification.length < 10 || this.submitting) return;

      this.submitting = true;
      try {
        const res = await axios.post('/api/export-requests', {
          dataset: this.config.dataset,
          target_type: this.config.targetType,
          target_id: this.config.targetId,
          filters: this.config.filters || {},
          justification: this.justification,
        });

        if (res.data?.mode === 'download' && res.data?.request?.download_url) {
          await downloadProtectedFile(res.data.request.download_url, this.config.fallbackName || 'download.csv');
          notify.success('Export downloaded successfully.', 'Export');
        } else {
          notify.success(res.data?.message || 'Export request submitted for approval.', 'Export Request');
          this.$router.push({ name: 'export-requests' });
        }

        this.$emit('submitted', res.data);
        this.modal.hide();
      } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to submit export request.', 'Export Request');
        this.$emit('error', error);
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
