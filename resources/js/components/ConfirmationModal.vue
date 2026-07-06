<template>
  <div class="modal fade" tabindex="-1" ref="modalRef">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ state.title }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="busy"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">{{ state.message }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="busy">
            Cancel
          </button>
          <button type="button" class="btn" :class="confirmButtonClass" @click="confirm" :disabled="busy">
            <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span>
            {{ busy ? 'Working...' : state.confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { createManagedModal, disposeManagedModal } from '../utils/modal';

export default {
  name: 'ConfirmationModal',
  data() {
    return {
      modal: null,
      busy: false,
      state: {
        title: 'Confirm Action',
        message: 'Are you sure you want to continue?',
        confirmLabel: 'Confirm',
        confirmVariant: 'danger',
      },
      onConfirm: null,
    };
  },
  computed: {
    confirmButtonClass() {
      return `btn-${this.state.confirmVariant || 'danger'}`;
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
      this.state = {
        title: config.title || 'Confirm Action',
        message: config.message || 'Are you sure you want to continue?',
        confirmLabel: config.confirmLabel || 'Confirm',
        confirmVariant: config.confirmVariant || 'danger',
      };
      this.onConfirm = typeof config.onConfirm === 'function' ? config.onConfirm : null;
      this.busy = false;
      this.modal.show();
    },
    async confirm() {
      if (!this.onConfirm || this.busy) return;

      this.busy = true;
      try {
        await this.onConfirm();
        this.modal.hide();
      } finally {
        this.busy = false;
      }
    },
  },
};
</script>
