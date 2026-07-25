<template>
  <div class="table-loading-wrapper" :style="{ '--table-loading-min-height': minHeight }">
    <div class="table-loading-content" :class="{ 'is-loading': loading }">
      <slot />
    </div>

    <transition name="table-loading-fade">
      <div
        v-if="loading"
        class="table-loading-overlay"
        role="status"
        aria-live="polite"
        aria-busy="true"
      >
        <div class="table-loading-indicator">
          <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
          <span>{{ message }}</span>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'TableLoadingWrapper',
  props: {
    loading: {
      type: Boolean,
      default: false,
    },
    message: {
      type: String,
      default: 'Loading data...',
    },
    minHeight: {
      type: String,
      default: '220px',
    },
  },
};
</script>

<style scoped>
.table-loading-wrapper {
  position: relative;
  min-height: var(--table-loading-min-height, 220px);
}

.table-loading-content {
  transition: opacity 0.18s ease, filter 0.18s ease;
}

.table-loading-content.is-loading {
  opacity: 0.35;
  filter: saturate(0.8);
  pointer-events: none;
  user-select: none;
}

.table-loading-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: linear-gradient(
    180deg,
    rgba(255, 255, 255, 0.74) 0%,
    rgba(247, 251, 255, 0.88) 100%
  );
  z-index: 5;
}

.table-loading-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.75rem 1rem;
  border: 1px solid #d8e7ff;
  border-radius: 0.8rem;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 0.35rem 1rem rgba(31, 61, 122, 0.12);
  color: #35507a;
  font-weight: 600;
  white-space: nowrap;
}

.table-loading-fade-enter-active,
.table-loading-fade-leave-active {
  transition: opacity 0.18s ease;
}

.table-loading-fade-enter-from,
.table-loading-fade-leave-to {
  opacity: 0;
}
</style>
