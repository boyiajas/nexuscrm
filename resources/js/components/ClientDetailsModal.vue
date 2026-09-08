<template>
  <div class="modal fade" tabindex="-1" ref="modalRef">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-person-lines-fill me-2"></i>Client Details
          </h5>
          <button type="button" class="btn-close" @click="close" aria-label="Close"></button>
        </div>
        <div class="modal-body" v-if="client">
          <div class="row g-3">
            <!-- Personal Details -->
            <div class="col-12">
              <h6 class="border-bottom pb-2 mb-3 text-primary">Personal Information</h6>
              <div class="row">
                <div class="col-md-6 mb-2"><strong>Name:</strong> {{ client.name || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>ID Number:</strong> {{ client.id_number_masked || client.id_number || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Title:</strong> {{ client.title || parseName(client.name).title || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Initials:</strong> {{ client.initials || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>First Name:</strong> {{ client.first_name || parseName(client.name).first_name || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Surname:</strong> {{ client.surname || parseName(client.name).surname || '-' }}</div>
              </div>
            </div>

            <!-- Contact Details -->
            <div class="col-12">
              <h6 class="border-bottom pb-2 mb-3 text-primary mt-2">Contact Details</h6>
              <div class="row">
                <div class="col-md-6 mb-2"><strong>Email:</strong> <a :href="'mailto:' + client.email" v-if="client.email">{{ client.email }}</a><span v-else>-</span></div>
                <div class="col-md-6 mb-2"><strong>Primary Phone:</strong> <a :href="'tel:' + client.phone" v-if="client.phone">{{ client.phone }}</a><span v-else>-</span></div>
                <div class="col-md-6 mb-2"><strong>Cell Phone:</strong> {{ client.cell_phone || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Home Phone:</strong> {{ client.home_phone || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Work Phone:</strong> {{ client.work_phone || '-' }}</div>
              </div>
            </div>

            <!-- Financial Details -->
            <div class="col-12">
              <h6 class="border-bottom pb-2 mb-3 text-primary mt-2">Financial Information</h6>
              <div class="row">
                <div class="col-md-6 mb-2"><strong>Bank:</strong> {{ client.bank_name || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Account Number:</strong> {{ client.account_number_masked || client.account_number || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Account Type:</strong> {{ client.account_type || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Type:</strong> {{ client.type || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Branch Code:</strong> {{ client.branch_code || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Easy Pay Number:</strong> {{ client.easy_pay_number || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Store Number:</strong> {{ client.store_number || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Arrears Amount:</strong> {{ client.arrears_amount ? 'R' + Number(client.arrears_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (client.arrears_amount || '-') }}</div>
                <div class="col-md-6 mb-2"><strong>Outstanding Balance:</strong> {{ client.outstanding_balance ? 'R' + Number(client.outstanding_balance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (client.outstanding_balance || '-') }}</div>
                <div class="col-md-6 mb-2"><strong>Settlement Amount:</strong> {{ client.settlement_amount ? 'R' + Number(client.settlement_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (client.settlement_amount || '-') }}</div>
                <div class="col-md-6 mb-2"><strong>3 Months Amount:</strong> {{ client.three_months_amount ? 'R' + Number(client.three_months_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (client.three_months_amount || '-') }}</div>
                <div class="col-md-6 mb-2"><strong>Installment Amount:</strong> {{ client.installment_amount ? 'R' + Number(client.installment_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (client.installment_amount || '-') }}</div>
                <div class="col-md-6 mb-2"><strong>Last Payment Amount:</strong> {{ client.last_payment_amount || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Total Payment Amount:</strong> {{ client.total_payment_amount || '-' }}</div>
              </div>
            </div>

            <!-- System Details -->
            <div class="col-12">
              <h6 class="border-bottom pb-2 mb-3 text-primary mt-2">System Information</h6>
              <div class="row">
                <div class="col-md-6 mb-2"><strong>Assigned To:</strong> {{ client.assigned_to_name || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Import Batch:</strong> {{ client.import_batch_number || '-' }}</div>
                <div class="col-md-6 mb-2"><strong>Departments:</strong> 
                  <span v-if="client.departments && client.departments.length">
                    <span v-for="d in client.departments" :key="d.id" class="badge bg-light text-dark border me-1">{{ d.name }}</span>
                  </span>
                  <span v-else>-</span>
                </div>
                <div class="col-md-6 mb-2"><strong>Tags:</strong>
                  <span v-if="client.tags && client.tags.length">
                    <span v-for="t in client.tags" :key="t" class="badge bg-secondary me-1">{{ t }}</span>
                  </span>
                  <span v-else>-</span>
                </div>
              </div>
            </div>

            <!-- WhatsApp & Opt-In Compliance -->
            <div class="col-12">
              <h6 class="border-bottom pb-2 mb-3 text-primary mt-2">WhatsApp & Opt-In Compliance</h6>
              <div class="row">
                <div class="col-md-6 mb-2">
                  <strong>Opt-In Status:</strong>
                  <span v-if="client.opt_in === 'yes'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2">
                    <i class="bi bi-check-circle-fill me-1"></i>Yes
                  </span>
                  <span v-else-if="client.opt_in === 'no'" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-2">
                    <i class="bi bi-x-circle-fill me-1"></i>No
                  </span>
                  <span v-else class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2">
                    <i class="bi bi-dash-circle me-1"></i>None
                  </span>
                </div>
                <div class="col-md-6 mb-2">
                  <strong>Opt-In Timestamp:</strong>
                  <span>{{ client.opt_in_updated_at || client.whatsapp_opted_in_at || client.whatsapp_opted_out_at || '-' }}</span>
                </div>
                <div class="col-md-6 mb-2">
                  <strong>Lawful Basis:</strong> {{ client.whatsapp_contact_basis || '-' }}
                </div>
                <div class="col-md-6 mb-2">
                  <strong>Opt-In Source:</strong> {{ client.whatsapp_opt_in_source || '-' }}
                </div>
                <div class="col-12 mb-2" v-if="client.whatsapp_opt_out_reason">
                  <strong>Opt-Out Reason:</strong> <span class="text-danger">{{ client.whatsapp_opt_out_reason }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="close">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import axios from '../axios';

export default {
  name: 'ClientDetailsModal',
  data() {
    return {
      modalInstance: null,
      client: null,
    };
  },
  methods: {
    open(clientData) {
      this.client = clientData;
      
      if (!this.client.departments && this.client.id) {
        axios.get(`/api/clients/${this.client.id}`).then(res => {
          this.client = res.data;
        }).catch(err => console.error("Could not fetch full client details", err));
      }

      if (!this.modalInstance) {
        this.modalInstance = createManagedModal(this.$refs.modalRef);
      }
      this.modalInstance.show();
    },
    close() {
      if (this.modalInstance) {
        this.modalInstance.hide();
      }
      setTimeout(() => {
        this.client = null;
      }, 300);
    },
    parseName(fullName) {
      if (!fullName) return { title: '', first_name: '', surname: '' };
      const parts = fullName.trim().split(/\s+/);
      let title = '';
      const possibleTitles = ['mr', 'mrs', 'ms', 'dr', 'prof', 'rev', 'mr.', 'mrs.', 'ms.', 'dr.', 'prof.', 'rev.'];
      if (parts.length > 1 && possibleTitles.includes(parts[0].toLowerCase())) {
        title = parts.shift();
      }
      let first_name = parts.length > 0 ? parts[0] : '';
      let surname = parts.length > 1 ? parts.slice(1).join(' ') : '';
      return { title, first_name, surname };
    }
  },
  unmounted() {
    disposeManagedModal(this.$refs.modalRef);
  }
};
</script>
