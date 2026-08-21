import { Modal } from 'bootstrap';

export function cleanupModalArtifacts(force = false) {
  const visibleModals = document.querySelectorAll('.modal.show').length;
  if (!force && visibleModals > 0) return;

  document.body.classList.remove('modal-open');
  document.body.style.removeProperty('padding-right');
  document.body.style.paddingRight = '0px';
  document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
}

export function createManagedModal(element, options = {}) {
  if (!element) return null;

  cleanupModalArtifacts();

  const modal = Modal.getOrCreateInstance(element, options);
  const handleHidden = () => cleanupModalArtifacts();
  const handleShow = () => cleanupModalArtifacts();

  element.addEventListener('hidden.bs.modal', handleHidden);
  element.addEventListener('show.bs.modal', handleShow);

  modal.__cleanupHandlers = { element, handleHidden, handleShow };

  return modal;
}

export function disposeManagedModal(modal) {
  if (!modal) return;

  const handlers = modal.__cleanupHandlers;
  if (handlers?.element) {
    handlers.element.removeEventListener('hidden.bs.modal', handlers.handleHidden);
    handlers.element.removeEventListener('show.bs.modal', handlers.handleShow);
  }

  modal.dispose();
  cleanupModalArtifacts();
}
