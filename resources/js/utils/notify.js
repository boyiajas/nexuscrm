function emitToast(message, variant = 'info', title = '') {
  window.dispatchEvent(new CustomEvent('app-toast', {
    detail: { message, variant, title },
  }));
}

export const notify = {
  success(message, title = 'Success') {
    emitToast(message, 'success', title);
  },
  error(message, title = 'Error') {
    emitToast(message, 'danger', title);
  },
  warning(message, title = 'Warning') {
    emitToast(message, 'warning', title);
  },
  info(message, title = 'Notice') {
    emitToast(message, 'info', title);
  },
};
