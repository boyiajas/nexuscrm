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
        <div class="w-100 px-3">
          <div class="table-loading-indicator mb-3">
            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            <span>{{ message }}</span>
          </div>

          <!-- Skeleton Lines -->
          <div class="skeleton-table-wrapper w-100 d-flex flex-column gap-2 opacity-75">
            <div class="skeleton-shimmer" style="height: 24px; width: 100%; border-radius: 4px;"></div>
            <div class="skeleton-shimmer" style="height: 18px; width: 85%; border-radius: 4px;"></div>
            <div class="skeleton-shimmer" style="height: 18px; width: 92%; border-radius: 4px;"></div>
            <div class="skeleton-shimmer" style="height: 18px; width: 78%; border-radius: 4px;"></div>
          </div>
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
  transition: opacity 0.2s ease, filter 0.2s ease;
}

.table-loading-content.is-loading {
  opacity: 0.25;
  filter: blur(1px);
  pointer-events: none;
  user-select: none;
}

.table-loading-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  background: var(--bg-surface);
  opacity: 0.92;
  z-index: 5;
  border-radius: var(--radius-md);
}

.table-loading-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.6rem 1.2rem;
  border: 1px solid var(--border-medium);
  border-radius: 9999px;
  background: var(--bg-surface-elevated);
  box-shadow: var(--shadow-md);
  color: var(--text-primary);
  font-weight: 600;
  font-size: 0.85rem;
  white-space: nowrap;
}

.table-loading-fade-enter-active,
.table-loading-fade-leave-active {
  transition: opacity 0.2s ease;
}

.table-loading-fade-enter-from,
.table-loading-fade-leave-to {
  opacity: 0;
}
</style>
