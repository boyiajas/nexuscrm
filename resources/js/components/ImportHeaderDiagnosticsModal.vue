<template>
  <div class="modal fade" tabindex="-1" ref="modalRef">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-2">
            <h5 class="modal-title mb-0">
              <i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>
              Import Column Requirements & Diagnostics
            </h5>
            <span v-if="activeUpload" class="badge bg-light text-dark border">
              {{ activeUpload.original_filename }}
            </span>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body p-3">
          <!-- Top Navigation Tabs -->
          <ul class="nav nav-pills mb-3 gap-1">
            <li class="nav-item" v-if="hasDiagnostics">
              <button
                class="nav-link btn-sm py-1 px-3"
                :class="{ active: currentTab === 'diagnostics' }"
                @click="currentTab = 'diagnostics'"
              >
                <i class="bi bi-cpu me-1"></i>
                File Analysis
                <span v-if="isMissingRequired" class="badge bg-danger ms-1">Action Needed</span>
                <span v-else-if="hasDiagnostics" class="badge bg-success ms-1">Valid</span>
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link btn-sm py-1 px-3"
                :class="{ active: currentTab === 'supported' }"
                @click="currentTab = 'supported'"
              >
                <i class="bi bi-list-check me-1"></i> Supported Columns & Aliases
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link btn-sm py-1 px-3"
                :class="{ active: currentTab === 'template' }"
                @click="currentTab = 'template'"
              >
                <i class="bi bi-download me-1"></i> Sample Template
              </button>
            </li>
          </ul>

          <!-- TAB 1: FILE DIAGNOSTICS & MISSING COLUMNS -->
          <div v-if="currentTab === 'diagnostics'">
            <!-- Alert for Missing Required Column -->
            <div v-if="isMissingRequired" class="alert alert-danger border-danger d-flex align-items-start mb-3">
              <i class="bi bi-exclamation-octagon-fill fs-3 text-danger me-3 mt-1"></i>
              <div>
                <h6 class="alert-heading fw-bold mb-1">Missing Required Column: Client Name</h6>
                <p class="mb-2 small">
                  The uploaded file failed because it does <strong>not</strong> contain a column to identify the client's name.
                  Every imported debtor must have at least a name column so they can be addressed and contacted.
                </p>
                <div class="small fw-semibold">
                  Required Action: Ensure your CSV or Excel file has a column named one of the following:
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1 me-1">Name</span>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">First Name</span>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">Full Name</span>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">Client Name</span>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">Debtor Name</span>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Surname</span>
                </div>
              </div>
            </div>

            <!-- Success Alert if File is Valid -->
            <div v-else-if="hasDiagnostics && diagnostics.is_valid" class="alert alert-success d-flex align-items-center mb-3">
              <i class="bi bi-check-circle-fill fs-4 text-success me-3"></i>
              <div>
                <h6 class="alert-heading fw-bold mb-0">Header Validation Passed</h6>
                <small>All required columns are present. Recognized {{ diagnostics.total_recognized || 0 }} out of {{ diagnostics.total_detected || 0 }} detected columns.</small>
              </div>
            </div>

            <!-- Error message from upload record if present -->
            <div v-if="activeUpload?.error_message && !isMissingRequired" class="alert alert-warning py-2 mb-3 small">
              <strong>Error Message:</strong> {{ activeUpload.error_message }}
            </div>

            <!-- Key Requirements Check Table -->
            <div class="card mb-3 border">
              <div class="card-header bg-light py-2">
                <span class="fw-semibold small text-uppercase">Requirements Checklist</span>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light small">
                      <tr>
                        <th style="width: 25%;">Required Field</th>
                        <th style="width: 20%;">Status</th>
                        <th>Matched Column in File</th>
                        <th>Accepted Header Variations</th>
                      </tr>
                    </thead>
                    <tbody class="small">
                      <tr :class="hasNameColumn ? 'table-success-subtle' : 'table-danger-subtle'">
                        <td>
                          <strong>Client Name</strong>
                          <span class="badge bg-danger ms-1">Mandatory</span>
                        </td>
                        <td>
                          <span v-if="hasNameColumn" class="badge bg-success">
                            <i class="bi bi-check-lg me-1"></i> Found
                          </span>
                          <span v-else class="badge bg-danger">
                            <i class="bi bi-x-lg me-1"></i> Missing
                          </span>
                        </td>
                        <td>
                          <span v-if="matchedNameColumn" class="fw-bold text-success">
                            <code>{{ matchedNameColumn.raw }}</code> &rarr; {{ matchedNameColumn.label }}
                          </span>
                          <span v-else class="text-danger fw-semibold">
                            Not found in file
                          </span>
                        </td>
                        <td>
                          <code>Name</code>, <code>First Name</code>, <code>Full Name</code>, <code>Client Name</code>, <code>Debtor Name</code>, <code>Debtor</code>, <code>Surname</code>, <code>Known As</code>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <strong>Phone / Cell</strong>
                          <span class="badge bg-secondary ms-1">Recommended</span>
                        </td>
                        <td>
                          <span v-if="matchedPhoneColumn" class="badge bg-success">
                            <i class="bi bi-check-lg me-1"></i> Found
                          </span>
                          <span v-else class="badge bg-secondary">Optional</span>
                        </td>
                        <td>
                          <span v-if="matchedPhoneColumn" class="text-success">
                            <code>{{ matchedPhoneColumn.raw }}</code> &rarr; {{ matchedPhoneColumn.label }}
                          </span>
                          <span v-else class="text-muted">Not mapped</span>
                        </td>
                        <td>
                          <code>Cell</code>, <code>Cell Phone</code>, <code>Cellphone</code>, <code>Mobile</code>, <code>Phone</code>, <code>Contact Number</code>, <code>Patient Cell</code>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <strong>Account Number</strong>
                          <span class="badge bg-secondary ms-1">Recommended</span>
                        </td>
                        <td>
                          <span v-if="matchedAccountColumn" class="badge bg-success">
                            <i class="bi bi-check-lg me-1"></i> Found
                          </span>
                          <span v-else class="badge bg-secondary">Optional</span>
                        </td>
                        <td>
                          <span v-if="matchedAccountColumn" class="text-success">
                            <code>{{ matchedAccountColumn.raw }}</code> &rarr; {{ matchedAccountColumn.label }}
                          </span>
                          <span v-else class="text-muted">Not mapped</span>
                        </td>
                        <td>
                          <code>Account Number</code>, <code>Acc No</code>, <code>Account No</code>, <code>Acc Code</code>, <code>Old Account Number</code>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <strong>ID Number</strong>
                          <span class="badge bg-secondary ms-1">Recommended</span>
                        </td>
                        <td>
                          <span v-if="matchedIdColumn" class="badge bg-success">
                            <i class="bi bi-check-lg me-1"></i> Found
                          </span>
                          <span v-else class="badge bg-secondary">Optional</span>
                        </td>
                        <td>
                          <span v-if="matchedIdColumn" class="text-success">
                            <code>{{ matchedIdColumn.raw }}</code> &rarr; {{ matchedIdColumn.label }}
                          </span>
                          <span v-else class="text-muted">Not mapped</span>
                        </td>
                        <td>
                          <code>ID Number</code>, <code>ID No</code>, <code>ID</code>, <code>Identity Number</code>, <code>IDNumber</code>, <code>Patient ID</code>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Detected Columns Pill Cloud -->
            <div class="card border">
              <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold small text-uppercase">
                  Detected Columns in File ({{ diagnostics.detected_headers?.length || 0 }})
                </span>
                <div class="small">
                  <span class="badge bg-success-subtle text-success border border-success-subtle me-1">
                    {{ diagnostics.recognized_columns?.length || 0 }} Mapped
                  </span>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                    {{ diagnostics.unsupported_headers?.length || 0 }} Extra / Unmapped
                  </span>
                </div>
              </div>
              <div class="card-body p-3" style="max-height: 220px; overflow-y: auto;">
                <div class="d-flex flex-wrap gap-1">
                  <!-- Mapped columns -->
                  <span
                    v-for="(col, idx) in diagnostics.recognized_columns || []"
                    :key="'rec-' + idx"
                    class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2"
                    :title="'Mapped to CRM field: ' + col.label"
                  >
                    <i class="bi bi-check me-1"></i>{{ col.raw }} &rarr; <span class="fw-bold">{{ col.label }}</span>
                  </span>

                  <!-- Unmapped columns -->
                  <span
                    v-for="(col, idx) in diagnostics.unsupported_headers || []"
                    :key="'unrec-' + idx"
                    class="badge bg-light text-muted border py-1 px-2"
                    title="Extra column - ignored during import"
                  >
                    {{ col }}
                  </span>
                </div>
                <div class="small text-muted mt-2">
                  <i class="bi bi-info-circle me-1"></i>
                  Green badges indicate recognized debtor fields. Extra / unmapped columns are safely skipped and will not cause the import to fail.
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: SUPPORTED COLUMNS REFERENCE -->
          <div v-if="currentTab === 'supported'">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small text-muted">
                NexusCRM accepts all standard South African bank debtor export headers (Capfin, FinChoice, Ackermans, Tenacity, ABSA, FNB, etc.).
              </div>
              <input
                v-model.trim="searchQuery"
                type="text"
                class="form-control form-control-sm w-auto"
                placeholder="Search columns..."
                style="min-width: 220px;"
              />
            </div>

            <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
              <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light sticky-top small">
                  <tr>
                    <th style="width: 22%;">Field Name</th>
                    <th style="width: 15%;">Requirement</th>
                    <th style="width: 38%;">Accepted Header Aliases</th>
                    <th>Format / Example</th>
                  </tr>
                </thead>
                <tbody class="small">
                  <tr
                    v-for="field in filteredSupportedFields"
                    :key="field.id"
                    :class="{ 'table-danger-subtle': field.required }"
                  >
                    <td>
                      <strong :class="{ 'text-danger': field.required }">{{ field.name }}</strong>
                    </td>
                    <td>
                      <span v-if="field.required" class="badge bg-danger">Required</span>
                      <span v-else class="badge bg-secondary-subtle text-secondary border">Optional</span>
                    </td>
                    <td>
                      <div class="d-flex flex-wrap gap-1">
                        <code v-for="alias in field.aliases" :key="alias" class="bg-light px-1 rounded border">
                          {{ alias }}
                        </code>
                      </div>
                    </td>
                    <td class="text-muted">{{ field.example }}</td>
                  </tr>
                  <tr v-if="!filteredSupportedFields.length">
                    <td colspan="4" class="text-center text-muted py-3">No matching fields found.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- TAB 3: SAMPLE TEMPLATE -->
          <div v-if="currentTab === 'template'">
            <div class="card mb-3 border">
              <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-file-earmark-arrow-down me-2 text-primary"></i>Starter CSV Template</h6>
                <p class="small text-muted mb-3">
                  You can download a starter CSV template pre-formatted with all required and recommended column headers.
                  Open it in Microsoft Excel, Google Sheets, or LibreOffice Calc, paste your client records, and save as <code>.csv</code> or <code>.xlsx</code>.
                </p>

                <div class="p-3 bg-light rounded border mb-3">
                  <div class="small fw-bold text-secondary mb-1">Template Headers Preview:</div>
                  <code class="small text-break">
                    Account Number,Name,Surname,Title,Initials,ID Number,Cell,Email,Outstanding Balance,Arrears Amount,Installment Amount,Settlement Amount,EasyPay Number,Store Number,Branch Code,Bank Name,Account Type
                  </code>
                </div>

                <button class="btn btn-primary btn-sm" @click="downloadSampleCsv">
                  <i class="bi bi-download me-1"></i> Download Sample CSV Template
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-outline-primary btn-sm" @click="downloadSampleCsv">
            <i class="bi bi-download me-1"></i> Download Sample CSV
          </button>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';

export default {
  name: 'ImportHeaderDiagnosticsModal',
  data() {
    return {
      modal: null,
      activeUpload: null,
      customDiagnostics: null,
      currentTab: 'supported',
      searchQuery: '',
      supportedFields: [
        {
          id: 'name',
          name: 'Client Name',
          required: true,
          aliases: ['Name', 'First Name', 'Full Name', 'Client Name', 'Debtor Name', 'Debtor', 'Customer Name', 'Known As', 'Surname'],
          example: 'John Smith, or first name + surname',
        },
        {
          id: 'surname',
          name: 'Surname',
          required: false,
          aliases: ['Surname', 'Last Name', 'Lastname'],
          example: 'Smith, Mngadi, Erasmus',
        },
        {
          id: 'title',
          name: 'Title',
          required: false,
          aliases: ['Title'],
          example: 'MR, MS, MRS, DR',
        },
        {
          id: 'initials',
          name: 'Initials',
          required: false,
          aliases: ['Initials'],
          example: 'J, T P, S',
        },
        {
          id: 'account_number',
          name: 'Account Number',
          required: false,
          aliases: ['Account Number', 'Acc No', 'Account_Number', 'Acc Code', 'Acc Number', 'Old Account Number'],
          example: '1020304050, 4112580',
        },
        {
          id: 'id_number',
          name: 'ID Number',
          required: false,
          aliases: ['ID Number', 'ID No', 'ID', 'Identity Number', 'IDNumber', 'Patient ID', 'SA ID'],
          example: '8501015000085 (13-digit SA ID)',
        },
        {
          id: 'cell_phone',
          name: 'Cell Phone',
          required: false,
          aliases: ['Cell', 'Cell Phone', 'Cellphone', 'Mobile', 'Mobile Number', 'Cell No', 'Patient Cell', 'Phone'],
          example: '0821234567, +27821234567',
        },
        {
          id: 'phone',
          name: 'Landline / Phone',
          required: false,
          aliases: ['Phone', 'Phone Number', 'Contact Number', 'Tel'],
          example: '0112345678',
        },
        {
          id: 'home_phone',
          name: 'Home Phone',
          required: false,
          aliases: ['Home Phone', 'Home', 'Home No', 'Home Tel', 'Patient Home'],
          example: '0219447700',
        },
        {
          id: 'work_phone',
          name: 'Work Phone',
          required: false,
          aliases: ['Work Phone', 'Work', 'Work No', 'Work Tel', 'Patient Work'],
          example: '0315551234',
        },
        {
          id: 'email',
          name: 'Email Address',
          required: false,
          aliases: ['Email', 'Email Address', 'Email Personal', 'Email - Personal', 'Patient EMail Personal'],
          example: 'client@example.com',
        },
        {
          id: 'email_work',
          name: 'Work Email',
          required: false,
          aliases: ['Email Work', 'Email - Work', 'Patient EMail Work'],
          example: 'client@work.co.za',
        },
        {
          id: 'outstanding_balance',
          name: 'Outstanding Balance',
          required: false,
          aliases: ['Outstanding Balance', 'Outstanding_balance', 'Balance', 'Current Balance', 'Current Liability', 'Total Due'],
          example: '14463.95, 2500.00',
        },
        {
          id: 'arrears_amount',
          name: 'Arrears Amount',
          required: false,
          aliases: ['Arrears Amount', 'Arrears', 'Arrear Amount'],
          example: '2500.00',
        },
        {
          id: 'installment_amount',
          name: 'Installment Amount',
          required: false,
          aliases: ['Installment Amount', 'Installment', 'Instalment Amount', 'Instalment'],
          example: '500.00',
        },
        {
          id: 'settlement_amount',
          name: 'Settlement Amount',
          required: false,
          aliases: ['Settlement Amount', 'Settlement'],
          example: '8500.00',
        },
        {
          id: 'three_months_amount',
          name: '3-Month Plan Amount',
          required: false,
          aliases: ['3 Months', '3 Month', '3months', 'Three Months Amount'],
          example: '1200.00',
        },
        {
          id: 'easy_pay_number',
          name: 'EasyPay Number',
          required: false,
          aliases: ['EasyPay Number', 'Easy Pay Number', 'Easypay', 'Easy_Pay_Number'],
          example: '951119541125809',
        },
        {
          id: 'store_number',
          name: 'Store Number',
          required: false,
          aliases: ['Store Number', 'Store No', 'Store Code'],
          example: 'ACKERMANS 3rd PL, ERA02434',
        },
        {
          id: 'branch_code',
          name: 'Branch Code',
          required: false,
          aliases: ['Branch Code', 'BranchCode', 'Branch No'],
          example: '051001, 632005',
        },
        {
          id: 'bank_name',
          name: 'Bank Name',
          required: false,
          aliases: ['Bank Name', 'Bank'],
          example: 'Finchoice, Tenacity, Capfin',
        },
        {
          id: 'account_type',
          name: 'Account Type',
          required: false,
          aliases: ['Account Type', 'Acc Type', 'Type'],
          example: '24 Month Personal, MobiMoney',
        },
        {
          id: 'last_payment_amount',
          name: 'Last Payment Amount',
          required: false,
          aliases: ['Last Payment Amount', 'Last Payment'],
          example: '265.00',
        },
        {
          id: 'total_payment_amount',
          name: 'Total Payment Amount',
          required: false,
          aliases: ['Total Payment Amount', 'Total Payment'],
          example: '1500.00',
        },
        {
          id: 'department',
          name: 'Department Name or IDs',
          required: false,
          aliases: ['Department', 'Department IDs'],
          example: 'Call Center, Legal, or 1,2',
        },
      ],
    };
  },
  computed: {
    diagnostics() {
      return this.customDiagnostics || this.activeUpload?.import_summary?.header_diagnostics || null;
    },
    hasDiagnostics() {
      return !!this.diagnostics;
    },
    isMissingRequired() {
      if (!this.diagnostics) {
        return this.activeUpload?.import_status === 'import_failed' &&
          String(this.activeUpload?.error_message || '').toLowerCase().includes('name');
      }
      return !this.diagnostics.is_valid || (this.diagnostics.missing_required_headers && this.diagnostics.missing_required_headers.length > 0);
    },
    hasNameColumn() {
      if (!this.diagnostics) return false;
      return !!this.diagnostics.has_required_name;
    },
    matchedNameColumn() {
      if (!this.diagnostics?.recognized_columns) return null;
      return this.diagnostics.recognized_columns.find(col => 
        ['name', 'first_name', 'surname'].includes(col.normalized)
      );
    },
    matchedPhoneColumn() {
      if (!this.diagnostics?.recognized_columns) return null;
      return this.diagnostics.recognized_columns.find(col => 
        ['cell_phone', 'cell', 'phone'].includes(col.normalized)
      );
    },
    matchedAccountColumn() {
      if (!this.diagnostics?.recognized_columns) return null;
      return this.diagnostics.recognized_columns.find(col => 
        ['account_number', 'acc_code', 'old_account_number'].includes(col.normalized)
      );
    },
    matchedIdColumn() {
      if (!this.diagnostics?.recognized_columns) return null;
      return this.diagnostics.recognized_columns.find(col => 
        ['id_number'].includes(col.normalized)
      );
    },
    filteredSupportedFields() {
      if (!this.searchQuery) return this.supportedFields;
      const q = this.searchQuery.toLowerCase();
      return this.supportedFields.filter(f => 
        f.name.toLowerCase().includes(q) ||
        f.aliases.some(a => a.toLowerCase().includes(q)) ||
        f.example.toLowerCase().includes(q)
      );
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
      this.activeUpload = config.upload || null;
      this.customDiagnostics = config.diagnostics || null;
      if (this.customDiagnostics || this.activeUpload?.import_summary?.header_diagnostics || this.activeUpload?.import_status === 'import_failed') {
        this.currentTab = 'diagnostics';
      } else {
        this.currentTab = config.mode || 'supported';
      }
      this.modal.show();
    },
    downloadSampleCsv() {
      const headers = [
        'Account Number',
        'Name',
        'Surname',
        'Title',
        'Initials',
        'ID Number',
        'Cell',
        'Email',
        'Outstanding Balance',
        'Arrears Amount',
        'Installment Amount',
        'Settlement Amount',
        'EasyPay Number',
        'Store Number',
        'Branch Code',
        'Bank Name',
        'Account Type',
      ];
      const sampleRow = [
        'ACC-100234',
        'John',
        'Smith',
        'MR',
        'J',
        '8501015000085',
        '0821234567',
        'john.smith@example.com',
        '1500.00',
        '250.00',
        '500.00',
        '1200.00',
        '951119500000001',
        'STORE12',
        '051001',
        'Standard Bank',
        'Personal Loan',
      ];

      const csvContent = headers.join(',') + '\n' + sampleRow.join(',') + '\n';
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', 'clients_import_sample_template.csv');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
      notify.success('Sample template downloaded.', 'Import');
    },
  },
};
</script>

<style scoped>
.table-danger-subtle {
  background-color: #fff2f2;
}
.table-success-subtle {
  background-color: #f0fdf4;
}
</style>
