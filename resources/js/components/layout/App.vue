<template>
  <router-view />
</template>

<script>
export default {
  name: 'App',
};
</script>

<style>
/* global styles if needed */
.modal-header {
  background-color: rgb(60, 60, 113);
  color: #fff;
  padding-top: 0.35rem !important;
  padding-bottom: 0.35rem !important;
}
.modal-header .modal-title {
  color: #fff;
}
.modal-header .btn-close {
  filter: invert(1);
}
</style>
