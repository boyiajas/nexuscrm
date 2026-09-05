<template>
  <div class="clients-page">
    <div>
      <!-- Header + Top Actions -->
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
          <h1 class="h3 fw-bold text-dark mb-1">Client Directory</h1>
          <p class="text-muted small mb-0">Manage and track all recovery targets.</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button class="btn btn-outline-secondary btn-sm rounded-2 d-flex align-items-center gap-1 shadow-sm" @click="exportCsv">
            <i class="bi bi-download"></i> Export
          </button>
          <button class="btn btn-dark-pill btn-sm d-flex align-items-center gap-1 shadow-sm" @click="openCreateModal" :disabled="!canCreate">
            <i class="bi bi-person-plus-fill"></i> Add Client
          </button>
          <button class="btn btn-outline-success btn-sm rounded-2" @click="openImportModal" :disabled="!canImport">
            <i class="bi bi-file-earmark-arrow-up"></i> Import
          </button>
        </div>
      </div>

      <!-- Filters Strip -->
      <div class="card shadow-sm mb-4 border">
        <div class="card-body p-3">
          <form class="d-flex flex-wrap align-items-center justify-content-between gap-3" @submit.prevent="applyFilters">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="small fw-bold text-secondary me-1"><i class="bi bi-sliders me-1"></i> Filters:</span>

              <!-- Search Input pill -->
              <input
                v-model="filters.q"
                type="text"
                class="form-control form-control-sm rounded-pill"
                style="width: 210px;"
                placeholder="Search name, phone, email, ID..."
                @input="applyFilters"
              />

              <!-- Department Filter -->
              <select v-model="filters.department" class="form-select form-select-sm rounded-pill" style="width: 150px;" @change="applyFilters">
                <option value="">All Departments</option>
                <option v-for="d in departmentOptions" :key="d.id" :value="d.name">
                  {{ d.name }}
                </option>
              </select>

              <!-- Bank Filter -->
              <select v-model="filters.bank_id" class="form-select form-select-sm rounded-pill" style="width: 140px;" @change="applyFilters">
                <option value="">All Banks</option>
                <option v-for="b in banks" :key="b.id" :value="b.id">
                  {{ b.name }}
                </option>
              </select>

              <!-- Source / Batch Filter -->
              <select v-model="filters.import_batch_number" class="form-select form-select-sm rounded-pill" style="width: 170px;" @change="applyFilters">
                <option value="">All Sources / Batches</option>
                <option value="manual">Manually Created</option>
                <optgroup label="Import Batches" v-if="clientBatchOptions.length">
                  <option v-for="batch in clientBatchOptions" :key="batch" :value="batch">
                    Batch {{ batch }}
                  </option>
                </optgroup>
              </select>

              <!-- Client Type Filter (only shows when a batch is selected) -->
              <select v-if="filters.import_batch_number && filters.import_batch_number !== 'manual'" v-model="filters.client_type" class="form-select form-select-sm rounded-pill border-info" style="width: 150px;" @change="applyFilters">
                <option value="all">All in Batch</option>
                <option value="new">New in Batch</option>
                <option value="existing">Existing in Batch</option>
              </select>

              <!-- Status Filter -->
              <select v-model="filters.status" class="form-select form-select-sm rounded-pill" style="width: 130px;" @change="applyFilters">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Pending">Pending</option>
                <option value="Inactive">Inactive</option>
              </select>

              <!-- Opt-In Filter -->
              <select v-model="filters.opt_in" class="form-select form-select-sm rounded-pill" style="width: 130px;" @change="applyFilters">
                <option value="">All Opt-In</option>
                <option value="yes">Opt-In: Yes</option>
                <option value="no">Opt-In: No</option>
                <option value="none">Opt-In: None</option>
              </select>
              <!-- Account Type Filter -->
              <input type="text" v-model="filters.account_type" class="form-control form-control-sm rounded-pill" style="width: 130px;" placeholder="Account Type..." @keyup.enter="applyFilters" @blur="applyFilters" />

              <!-- Type Filter -->
              <input type="text" v-model="filters.type" class="form-control form-control-sm rounded-pill" style="width: 100px;" placeholder="Type..." @keyup.enter="applyFilters" @blur="applyFilters" />
            </div>

            <div class="d-flex align-items-center gap-3">
              <div v-if="filters.import_batch_number && filters.import_batch_number !== 'manual'" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary shadow-sm" @click="openAssignBatchModal" :disabled="!canManage">
                  <i class="bi bi-person-lines-fill"></i> Assign Batch
                </button>
                <button type="button" class="btn btn-outline-danger shadow-sm" @click="removeBatchClients" :disabled="!canManage">
                  <i class="bi bi-trash"></i> Delete Batch
                </button>
              </div>
              <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted fw-semibold p-0" @click="resetFilters">
                Clear Filters
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Bulk Actions Bar -->
      <div v-if="selectedClientIds.length > 0" class="d-flex align-items-center justify-content-between mb-3 px-3 py-2 bg-primary bg-opacity-10 rounded border border-primary border-opacity-25 shadow-sm">
        <div>
          <span class="fw-bold text-primary">{{ selectedClientIds.length }}</span> <span class="text-secondary small fw-medium">client(s) selected</span>
        </div>
        <div class="d-flex gap-2">
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary bg-white fw-medium shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" :disabled="bulkActionLoading">
              <i class="bi bi-tag"></i> Status
            </button>
            <ul class="dropdown-menu shadow-sm">
              <li><a class="dropdown-item fw-medium text-success" href="#" @click.prevent="bulkUpdateStatus('Active')">Active</a></li>
              <li><a class="dropdown-item fw-medium text-warning" href="#" @click.prevent="bulkUpdateStatus('Pending')">Pending</a></li>
              <li><a class="dropdown-item fw-medium text-secondary" href="#" @click.prevent="bulkUpdateStatus('Inactive')">Inactive</a></li>
            </ul>
          </div>
          <button class="btn btn-sm btn-outline-primary bg-white fw-medium shadow-sm" @click="openBulkAssignModal" :disabled="bulkActionLoading">
            <i class="bi bi-person-lines-fill"></i> Assign
          </button>
          <button class="btn btn-sm btn-outline-danger bg-white fw-medium shadow-sm" @click="bulkDeleteClients" :disabled="bulkActionLoading">
            <i class="bi bi-trash"></i> Delete
          </button>
        </div>
      </div>

      <!-- Clients Data Table -->
      <div class="card shadow-sm border mb-4">
        <div class="card-body p-0">
          <TableLoadingWrapper :loading="loading" message="Loading clients..." min-height="300px">
            <div class="table-responsive">
              <table class="table table-hover mb-0 align-middle">
                <thead>
                  <tr>
                    <th v-if="canManage" style="width: 38px;" class="ps-3">
                      <input type="checkbox" class="form-check-input" :checked="isAllSelected" @change="toggleSelectAll" />
                    </th>
                    <th class="ps-4">CLIENT NAME</th>
                    <th style="width: 140px;">ID / REF</th>
                    <th style="width: 180px;">CONTACT</th>
                    <th style="width: 150px;">INSTITUTION</th>
                    <th style="width: 150px;">WA NUMBER</th>
                    <th style="width: 130px;">OPT-IN</th>
                    <th style="width: 110px;">STATUS</th>
                    <th style="width: 110px;" class="text-end pe-4">ACTIONS</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(c, idx) in clients" :key="c.id" :style="idx === 0 ? 'border-left: 3px solid #10b981;' : ''">
                    <td v-if="canManage" class="ps-3">
                      <input type="checkbox" class="form-check-input" :checked="selectedClientIds.includes(c.id)" @change="toggleClientSelection(c.id, $event.target.checked)" />
                    </td>
                    <td class="ps-4 py-1">
                      <div class="d-flex align-items-center gap-3">
                        <div class="avatar-initial-badge">{{ getInitials(c.name) }}</div>
                        <div>
                          <a
                            href="#"
                            @click.prevent="openViewModal(c)"
                            class="fw-bold text-dark text-decoration-none"
                          >
                            {{ c.name }}
                          </a>
                        </div>
                      </div>
                    </td>
                    <td class="small fw-semibold text-secondary text-nowrap">
                      NX-{{ 8000 + c.id }}-{{ getInitials(c.name) }}
                    </td>
                    <td class="text-nowrap">
                      <div class="small text-dark">{{ c.email || c.phone || '-' }}</div>
                    </td>
                    <td class="text-nowrap">
                      <div class="small fw-medium text-dark">{{ c.bank_name || 'Standard Bank' }}</div>
                    </td>
                    <td class="text-nowrap">
                      <span v-if="c.bank?.primary_whatsapp_number" class="badge bg-info-subtle text-info border" title="Bank WhatsApp Profile">
                        <i class="bi bi-whatsapp me-1"></i>{{ c.bank.primary_whatsapp_number }}
                      </span>
                      <span v-else-if="c.departments && c.departments[0]?.primary_whatsapp_number" class="badge bg-light text-dark border" title="Department WhatsApp Profile">
                        <i class="bi bi-whatsapp me-1"></i>{{ c.departments[0].primary_whatsapp_number }}
                      </span>
                      <span v-else class="text-muted small">Default</span>
                    </td>
                    <td class="text-nowrap">
                      <span v-if="c.whatsapp_opted_out_at || c.opt_in === 'no'" class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                        <i class="bi bi-x-circle me-1"></i> Opted Out
                      </span>
                      <span v-else-if="c.whatsapp_opted_in_at" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        <i class="bi bi-check-circle me-1"></i> Opted In
                      </span>
                      <span v-else class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                        <i class="bi bi-dash-circle me-1"></i> None
                      </span>
                    </td>
                    <td class="text-nowrap">
                      <span class="badge" :class="getStatusBadgeClass(c.status || 'Active')">
                        {{ c.status || 'Active' }}
                      </span>
                    </td>
                    <td class="text-end pe-4 text-nowrap">
                      <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-light text-secondary border-0 p-1 px-2" title="View" @click="openViewModal(c)">
                          <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-light text-secondary border-0 p-1 px-2" title="Edit" @click="openEditModal(c)" :disabled="!canEdit">
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-light text-danger border-0 p-1 px-2" title="Delete" @click="remove(c)" :disabled="!canDelete">
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!loading && clients.length === 0">
                    <td :colspan="canManage ? 9 : 8" class="text-center text-muted py-5">
                      No clients found.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </TableLoadingWrapper>
        </div>

        <!-- Footer Pagination Strip -->
        <div class="card-footer bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap border-top">
          <div class="d-flex align-items-center gap-3">
            <small class="text-muted fw-medium">
              Showing {{ pagination.from || 1 }} to {{ pagination.to || clients.length }} of {{ pagination.total || clients.length }} clients
            </small>
            <div class="d-flex align-items-center gap-2">
              <small class="text-muted">Rows:</small>
              <select class="form-select form-select-sm w-auto" v-model="pageSize" @change="changePageSize">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="250">250</option>
                <option value="500">500</option>
                <option value="1000">1000</option>
              </select>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2">
            <button
              class="btn btn-sm btn-light border p-1 px-2"
              :disabled="!pagination.prevPage"
              @click="goToPage(pagination.prevPage)"
            >
              <i class="bi bi-chevron-left"></i>
            </button>
            <span class="small fw-semibold px-2">Page {{ pagination.currentPage || 1 }} of {{ pagination.lastPage || 1 }}</span>
            <button
              class="btn btn-sm btn-light border p-1 px-2"
              :disabled="!pagination.nextPage"
              @click="goToPage(pagination.nextPage)"
            >
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" tabindex="-1" ref="importModalRef">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import Clients</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <div class="alert alert-info py-2">
              Upload a CSV or Excel `.xlsx` file. The selected departments below will be attached to every imported client.
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">File <span class="text-danger">*</span></label>
                <input
                  ref="importFileInput"
                  type="file"
                  class="form-control"
                  accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                  @change="onImportFileSelected"
                />
                <small class="text-muted">Supported formats: `.csv` and `.xlsx`.</small>
              </div>

              <div class="col-md-6" v-if="canChooseBank">
                <label class="form-label">Bank <span class="text-danger">*</span></label>
                <select v-model="importForm.bank_id" class="form-select">
                  <option value="">Select bank</option>
                  <option v-for="bank in banks" :key="bank.id" :value="bank.id">
                    {{ bank.name }}
                  </option>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label">Departments <span class="text-danger">*</span></label>
                <vue-multiselect
                  v-model="importSelectedDepartments"
                  :options="departmentOptions"
                  :multiple="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  placeholder="Select one or more departments for this import"
                  label="name"
                  track-by="id"
                  :searchable="true"
                  class="mb-2"
                >
                  <template #noResult>No departments found</template>
                  <template #noOptions>No departments available</template>
                </vue-multiselect>

                <div class="d-flex justify-content-between">
                  <small class="text-muted">
                    Choose one or more departments to attach to all imported clients.
                  </small>
                  <div>
                    <button
                      type="button"
                      class="btn btn-link btn-sm p-0 me-2"
                      @click="selectAllImportDepartments"
                    >
                      Select all
                    </button>
                    <button
                      type="button"
                      class="btn btn-link btn-sm p-0 text-danger"
                      @click="clearImportDepartments"
                    >
                      Clear
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="importForm.uploading">
              Cancel
            </button>
            <button type="button" class="btn btn-success" @click="submitImport" :disabled="importForm.uploading">
              <span v-if="importForm.uploading" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-upload me-1"></i>
              Import Clients
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal fade" tabindex="-1" ref="modalRef">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">
              {{ isEdit ? 'Edit Client' : 'Add Client' }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form @submit.prevent="save">
              <div class="row g-3 mb-3">
                <div class="col-md-2">
                  <label class="form-label">Title</label>
                  <input v-model="form.title" type="text" class="form-control" />
                </div>
                <div class="col-md-5">
                  <label class="form-label">First Name</label>
                  <input v-model="form.first_name" type="text" class="form-control" />
                </div>
                <div class="col-md-5">
                  <label class="form-label">Surname</label>
                  <input v-model="form.surname" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input v-model="form.email" type="email" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input v-model="form.phone" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">ID Number</label>
                  <input
                    v-model="form.id_number"
                    type="text"
                    class="form-control"
                    @copy.prevent
                    @cut.prevent
                    @paste.prevent
                    @drop.prevent
                    autocomplete="off"
                  />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Bank <span class="text-danger">*</span></label>
                  <select v-model="form.bank_id" class="form-select" :disabled="!canChooseBank">
                    <option value="">Select bank</option>
                    <option v-for="bank in banks" :key="bank.id" :value="bank.id">
                      {{ bank.name }}
                    </option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Bank Name</label>
                  <input v-model="form.bank_name" type="text" class="form-control" :readonly="!!form.bank_id" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Account Number</label>
                  <input
                    v-model="form.account_number"
                    type="text"
                    class="form-control"
                    @copy.prevent
                    @cut.prevent
                    @paste.prevent
                    @drop.prevent
                    autocomplete="off"
                  />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Account Type</label>
                  <input v-model="form.account_type" type="text" class="form-control" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Type</label>
                  <input v-model="form.type" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Branch Code</label>
                  <input v-model="form.branch_code" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Easy Pay Number</label>
                  <input v-model="form.easy_pay_number" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Store Number</label>
                  <input v-model="form.store_number" type="text" class="form-control" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Outstanding Balance</label>
                  <input v-model="form.outstanding_balance" type="number" step="0.01" class="form-control" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Arrears Amount</label>
                  <input v-model="form.arrears_amount" type="number" step="0.01" class="form-control" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Settlement Amount</label>
                  <input v-model="form.settlement_amount" type="number" step="0.01" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">3 Months Amount</label>
                  <input v-model="form.three_months_amount" type="number" step="0.01" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Installment Amount</label>
                  <input v-model="form.installment_amount" type="number" step="0.01" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last Payment Amount</label>
                  <input v-model="form.last_payment_amount" type="number" step="0.01" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Total Payment Amount</label>
                  <input v-model="form.total_payment_amount" type="number" step="0.01" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Assigned Portfolio Owner</label>
                  <select v-model="form.assigned_to_id" class="form-select" :disabled="!canChooseAssignee">
                    <option value="">Unassigned</option>
                    <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">
                      {{ assignee.name }} ({{ assignee.role }})
                    </option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Tags (comma separated)</label>
                  <input
                    v-model="tagsInput"
                    type="text"
                    class="form-control"
                    placeholder="VIP, Overdue, ..."
                  />
                </div>
              </div>

              <div class="card border mb-3">
                <div class="card-header bg-light fw-semibold">WhatsApp Compliance</div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Opt-In Status</label>
                      <select v-model="form.opt_in" class="form-select" @change="onOptInChange">
                        <option value="none">None (Unset)</option>
                        <option value="yes">Yes (Opted In)</option>
                        <option value="no">No (Opted Out / Suppressed)</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Lawful Basis</label>
                      <select v-model="form.whatsapp_contact_basis" class="form-select">
                        <option value="">Select lawful basis</option>
                        <option value="bank_instruction">Bank Instruction</option>
                        <option value="opt_in">Explicit Opt-In</option>
                        <option value="contract">Contract</option>
                        <option value="legitimate_interest">Legitimate Interest</option>
                        <option value="consent_refresh">Consent Refresh</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Opt-In Source</label>
                      <input v-model="form.whatsapp_opt_in_source" type="text" class="form-control" placeholder="bank_import, signed consent, call recording..." />
                    </div>
                    <div class="col-md-6" v-if="form.opt_in === 'no'">
                      <label class="form-label">Opt-Out / Suppression Reason</label>
                      <input v-model="form.whatsapp_opt_out_reason" type="text" class="form-control" placeholder="STOP, customer request, legal restriction..." />
                    </div>
                    <div class="col-12">
                      <label class="form-label">Lawful Basis Details</label>
                      <textarea v-model="form.whatsapp_contact_basis_details" class="form-control" rows="2" placeholder="Describe the source of permission or lawful basis for WhatsApp contact."></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Departments <span class="text-danger">*</span></label>
                <vue-multiselect
                  v-model="selectedDepartments"
                  :options="departmentOptions"
                  :multiple="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  placeholder="Select one or more departments"
                  label="name"
                  track-by="id"
                  :searchable="true"
                  :allow-empty="false"
                  class="mb-2"
                >
                  <template slot="noResult">No departments found</template>
                  <template slot="noOptions">No departments available</template>
                </vue-multiselect>
                
                <div class="d-flex justify-content-between">
                  <small class="text-muted">
                    Select at least one department
                  </small>
                  <div>
                    <button
                      type="button"
                      class="btn btn-link btn-sm p-0 me-2"
                      @click="selectAllDepartments"
                    >
                      Select all
                    </button>
                    <button
                      type="button"
                      class="btn btn-link btn-sm p-0 text-danger"
                      @click="clearDepartments"
                    >
                      Clear
                    </button>
                  </div>
                </div>
              </div>

              <div class="text-end">
                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                  Cancel
                </button>
                <button type="submit" class="btn btn-primary" :disabled="selectedDepartments.length === 0">
                  Save
                </button>
              </div>

            </form>
          </div>

        </div>
      </div>
    </div>

    <ExportRequestModal ref="exportRequestModal" />
    <ConfirmationModal ref="confirmModal" />

    <!-- Assign Batch Modal -->
    <div class="modal fade" tabindex="-1" ref="assignBatchModalRef">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Assign Batch to User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Select a user to assign all clients in batch <strong>{{ currentBatchFilter }}</strong>.</p>
            <div class="mb-3">
              <label class="form-label">Assign To</label>
              <select v-model="assignBatchForm.assigned_to_id" class="form-select">
                <option value="">Unassigned</option>
                <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">
                  {{ assignee.name }} ({{ assignee.role }})
                </option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="assignBatchForm.submitting">
              Cancel
            </button>
            <button type="button" class="btn btn-primary" @click="submitAssignBatch" :disabled="assignBatchForm.submitting">
              <span v-if="assignBatchForm.submitting" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-check2 me-1"></i>
              Assign
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Assign Selected Modal -->
    <div class="modal fade" tabindex="-1" ref="assignSelectedModalRef">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Assign Selected Clients</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Select a user to assign the <strong>{{ selectedClientIds.length }}</strong> selected client(s).</p>
            <div class="mb-3">
              <label class="form-label">Assign To</label>
              <select v-model="assignSelectedForm.assigned_to_id" class="form-select">
                <option value="">Unassigned</option>
                <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">
                  {{ assignee.name }} ({{ assignee.role }})
                </option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="bulkActionLoading">
              Cancel
            </button>
            <button type="button" class="btn btn-primary" @click="submitBulkAssign" :disabled="bulkActionLoading">
              <span v-if="bulkActionLoading" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-check2 me-1"></i>
              Assign
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- View Client Modal -->
    <div class="modal fade" tabindex="-1" ref="viewModalRef">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-person-lines-fill me-2"></i>Client Details
            </h5>
            <button type="button" class="btn-close" @click="closeViewModal" aria-label="Close"></button>
          </div>
          <div class="modal-body" v-if="viewClient">
            <div class="row g-3">
              <!-- Personal Details -->
              <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3 text-primary">Personal Information</h6>
                <div class="row">
                  <div class="col-md-6 mb-2"><strong>Name:</strong> {{ viewClient.name || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>ID Number:</strong> {{ viewClient.id_number_masked || viewClient.id_number || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Title:</strong> {{ viewClient.title || parseName(viewClient.name).title || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Initials:</strong> {{ viewClient.initials || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>First Name:</strong> {{ viewClient.first_name || parseName(viewClient.name).first_name || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Surname:</strong> {{ viewClient.surname || parseName(viewClient.name).surname || '-' }}</div>
                </div>
              </div>

              <!-- Contact Details -->
              <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3 text-primary mt-2">Contact Details</h6>
                <div class="row">
                  <div class="col-md-6 mb-2"><strong>Email:</strong> <a :href="'mailto:' + viewClient.email" v-if="viewClient.email">{{ viewClient.email }}</a><span v-else>-</span></div>
                  <div class="col-md-6 mb-2"><strong>Primary Phone:</strong> <a :href="'tel:' + viewClient.phone" v-if="viewClient.phone">{{ viewClient.phone }}</a><span v-else>-</span></div>
                  <div class="col-md-6 mb-2"><strong>Cell Phone:</strong> {{ viewClient.cell_phone || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Home Phone:</strong> {{ viewClient.home_phone || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Work Phone:</strong> {{ viewClient.work_phone || '-' }}</div>
                </div>
              </div>

              <!-- Financial Details -->
              <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3 text-primary mt-2">Financial Information</h6>
                <div class="row">
                  <div class="col-md-6 mb-2"><strong>Bank:</strong> {{ viewClient.bank_name || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Account Number:</strong> {{ viewClient.account_number_masked || viewClient.account_number || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Account Type:</strong> {{ viewClient.account_type || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Type:</strong> {{ viewClient.type || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Branch Code:</strong> {{ viewClient.branch_code || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Easy Pay Number:</strong> {{ viewClient.easy_pay_number || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Store Number:</strong> {{ viewClient.store_number || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Arrears Amount:</strong> {{ viewClient.arrears_amount ? 'R' + Number(viewClient.arrears_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (viewClient.arrears_amount || '-') }}</div>
                  <div class="col-md-6 mb-2"><strong>Outstanding Balance:</strong> {{ viewClient.outstanding_balance ? 'R' + Number(viewClient.outstanding_balance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (viewClient.outstanding_balance || '-') }}</div>
                  <div class="col-md-6 mb-2"><strong>Settlement Amount:</strong> {{ viewClient.settlement_amount ? 'R' + Number(viewClient.settlement_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (viewClient.settlement_amount || '-') }}</div>
                  <div class="col-md-6 mb-2"><strong>3 Months Amount:</strong> {{ viewClient.three_months_amount ? 'R' + Number(viewClient.three_months_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (viewClient.three_months_amount || '-') }}</div>
                  <div class="col-md-6 mb-2"><strong>Installment Amount:</strong> {{ viewClient.installment_amount ? 'R' + Number(viewClient.installment_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (viewClient.installment_amount || '-') }}</div>
                  <div class="col-md-6 mb-2"><strong>Last Payment Amount:</strong> {{ viewClient.last_payment_amount || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Total Payment Amount:</strong> {{ viewClient.total_payment_amount || '-' }}</div>
                </div>
              </div>

              <!-- System Details -->
              <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3 text-primary mt-2">System Information</h6>
                <div class="row">
                  <div class="col-md-6 mb-2"><strong>Assigned To:</strong> {{ viewClient.assigned_to_name || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Import Batch:</strong> {{ viewClient.import_batch_number || '-' }}</div>
                  <div class="col-md-6 mb-2"><strong>Departments:</strong> 
                    <span v-if="viewClient.departments && viewClient.departments.length">
                      <span v-for="d in viewClient.departments" :key="d.id" class="badge bg-light text-dark border me-1">{{ d.name }}</span>
                    </span>
                    <span v-else>-</span>
                  </div>
                  <div class="col-md-6 mb-2"><strong>Tags:</strong>
                    <span v-if="viewClient.tags && viewClient.tags.length">
                      <span v-for="t in viewClient.tags" :key="t" class="badge bg-secondary me-1">{{ t }}</span>
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
                    <span v-if="viewClient.opt_in === 'yes'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2">
                      <i class="bi bi-check-circle-fill me-1"></i>Yes
                    </span>
                    <span v-else-if="viewClient.opt_in === 'no'" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-2">
                      <i class="bi bi-x-circle-fill me-1"></i>No
                    </span>
                    <span v-else class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2">
                      <i class="bi bi-dash-circle me-1"></i>None
                    </span>
                  </div>
                  <div class="col-md-6 mb-2">
                    <strong>Opt-In Timestamp:</strong>
                    <span>{{ viewClient.opt_in_updated_at || viewClient.whatsapp_opted_in_at || viewClient.whatsapp_opted_out_at || '-' }}</span>
                  </div>
                  <div class="col-md-6 mb-2">
                    <strong>Lawful Basis:</strong> {{ viewClient.whatsapp_contact_basis || '-' }}
                  </div>
                  <div class="col-md-6 mb-2">
                    <strong>Opt-In Source:</strong> {{ viewClient.whatsapp_opt_in_source || '-' }}
                  </div>
                  <div class="col-12 mb-2" v-if="viewClient.whatsapp_opt_out_reason">
                    <strong>Opt-Out Reason:</strong> <span class="text-danger">{{ viewClient.whatsapp_opt_out_reason }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeViewModal">Close</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios, { syncAuthenticatedUser } from '../axios';
import VueMultiselect from 'vue-multiselect';
import ExportRequestModal from '../components/ExportRequestModal.vue';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: 'ClientsView',
  components: {
    VueMultiselect,
    ExportRequestModal,
    ConfirmationModal,
    TableLoadingWrapper,
  },
  data() {
    return {
      clients: [],
      loading: false,
      banks: [],
      assignees: [],
      departmentOptions: [],
      clientBatchOptions: [],
      filters: {
        q: '',
        department: '',
        import_batch_number: '',
        client_type: 'all',
        bank_id: '',
        status: '',
        opt_in: '',
        account_type: '',
        type: '',
      },
      pageSize: 25,
      pageSizeOptions: [25, 50, 100, 250, 500, 1000],
      pagination: {
        currentPage: 1,
        lastPage: 1,
        total: 0,
        from: 0,
        to: 0,
        prevPage: null,
        nextPage: null,
        pages: [],
      },
      form: {
        id: null,
        name: '',
        email: '',
        phone: '',
        bank_id: '',
        bank_name: '',
        account_type: '',
        type: '',
        assigned_to_id: '',
        department_ids: [], // Changed from single department to array of IDs
        tags: [],
        whatsapp_contact_basis: '',
        whatsapp_contact_basis_details: '',
        whatsapp_opted_in_at: '',
        whatsapp_opt_in_source: '',
        whatsapp_opted_out: false,
        whatsapp_opt_out_reason: '',
      },
      selectedDepartments: [], // Array of department objects for VueMultiselect
      importSelectedDepartments: [],
      tagsInput: '',
      isEdit: false,
      modal: null,
      importModal: null,
      viewModal: null,
      viewClient: null,
      assignBatchModal: null,
      assignBatchForm: {
        assigned_to_id: '',
        submitting: false,
      },
      assignSelectedModal: null,
      assignSelectedForm: {
        assigned_to_id: '',
      },
      bulkActionLoading: false,
      importForm: {
        file: null,
        bank_id: '',
        department_ids: [],
        uploading: false,
      },
      selectedClientIds: [],
    };
  },
  mounted() {
    this.modal = createManagedModal(this.$refs.modalRef);
    this.importModal = createManagedModal(this.$refs.importModalRef);
    this.viewModal = createManagedModal(this.$refs.viewModalRef);
    this.assignBatchModal = createManagedModal(this.$refs.assignBatchModalRef);
    this.assignSelectedModal = createManagedModal(this.$refs.assignSelectedModalRef);
    this.syncCurrentUser();
    this.fetchBanks();
    this.fetchAssignees();
    this.fetchDepartments();
    this.fetchClients();
  },
  beforeUnmount() {
    disposeManagedModal(this.modal);
    disposeManagedModal(this.importModal);
    disposeManagedModal(this.viewModal);
    disposeManagedModal(this.assignBatchModal);
    disposeManagedModal(this.assignSelectedModal);
  },
  computed: {
    currentUser() {
      const stored = localStorage.getItem('nexus_user');
      if (!stored) return null;
      try {
        return JSON.parse(stored);
      } catch {
        return null;
      }
    },
    canCreate() {
      return this.hasPermission('create_clients');
    },
    canEdit() {
      return this.hasPermission('edit_clients');
    },
    canDelete() {
      return this.hasPermission('delete_clients');
    },
    canImport() {
      return this.hasPermission('import_clients');
    },
    canManage() {
      return this.canCreate || this.canEdit || this.canDelete || this.canImport;
    },
    canChooseBank() {
      return this.hasPermission('bypass_bank_scoping') || ['SUPER_ADMIN', 'ADMIN'].includes(this.currentUser?.role);
    },
    canChooseAssignee() {
      return this.hasPermission('edit_clients');
    },
    selectedBankName() {
      if (!this.filters.bank_id) return '';
      return this.banks.find((bank) => Number(bank.id) === Number(this.filters.bank_id))?.name || '';
    },
    currentBatchFilter() {
      return String(this.filters.import_batch_number || '').trim();
    },
  },
  watch: {
    // Sync selectedDepartments with form.department_ids
    selectedDepartments: {
      handler(newVal) {
        // Extract IDs from selected department objects
        this.form.department_ids = newVal.map(dept => dept.id);
      },
      deep: true
    },
    importSelectedDepartments: {
      handler(newVal) {
        this.importForm.department_ids = newVal.map((dept) => dept.id);
      },
      deep: true,
    }
  },
  methods: {
    hasPermission(permCode) {
      if (!this.currentUser) return false;
      const roles = Array.isArray(this.currentUser.role_codes) && this.currentUser.role_codes.length
        ? this.currentUser.role_codes
        : [this.currentUser?.role].filter(Boolean);

      if (roles.includes('SUPER_ADMIN') || roles.includes('ADMIN')) {
        return true;
      }
      if (Array.isArray(this.currentUser.permission_codes)) {
        return this.currentUser.permission_codes.includes(permCode);
      }
      return false;
    },
    getInitials(name) {
      if (!name || typeof name !== 'string') return 'NX';
      const parts = name.trim().split(/\s+/).filter(Boolean);
      if (parts.length >= 2 && parts[0] && parts[1]) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
      }
      return (parts[0] || 'NX').substring(0, 2).toUpperCase();
    },
    parseName(fullName) {
      if (!fullName || typeof fullName !== 'string') {
        return { title: '', first_name: '', surname: '' };
      }
      let parts = fullName.trim().split(/\s+/).filter(Boolean);
      let title = '';
      const commonTitles = ['mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'rev', 'adv', 'sir', 'madam'];
      if (parts.length > 0 && commonTitles.includes(parts[0].replace('.', '').toLowerCase())) {
        title = parts.shift();
      }
      const first_name = parts.length > 0 ? parts.shift() : '';
      const surname = parts.join(' ');
      return { title, first_name, surname };
    },
    openViewModal(client) {
      this.viewClient = client;
      this.viewModal.show();
    },
    closeViewModal() {
      this.viewModal.hide();
      setTimeout(() => {
        this.viewClient = null;
      }, 300);
    },
    async syncCurrentUser() {
      try {
        await syncAuthenticatedUser();
      } catch (error) {
        console.error('Failed to sync current user before loading clients:', error);
      }
    },
    // pagination helpers
    buildPagination(data) {
      this.pagination.currentPage = data.current_page;
      this.pagination.lastPage = data.last_page;
      this.pagination.total = data.total;
      this.pagination.from = data.from;
      this.pagination.to = data.to;
      this.pageSize = Number(data.per_page || this.pageSize);

      this.pagination.prevPage = data.current_page > 1 ? data.current_page - 1 : null;
      this.pagination.nextPage = data.current_page < data.last_page ? data.current_page + 1 : null;

      this.pagination.pages = this.buildPaginationItems(data.current_page, data.last_page);
    },
    buildPaginationItems(currentPage, lastPage) {
      const maxVisiblePages = 20;
      const items = [];
      const addPage = (page) => {
        items.push({
          key: `page-${page}`,
          label: String(page),
          page,
          active: page === currentPage,
          ellipsis: false,
        });
      };
      const addEllipsis = (key) => {
        items.push({
          key,
          label: '...',
          page: null,
          active: false,
          ellipsis: true,
        });
      };

      if (lastPage <= maxVisiblePages) {
        for (let page = 1; page <= lastPage; page += 1) {
          addPage(page);
        }
        return items;
      }

      const innerVisiblePages = maxVisiblePages - 2;
      let start = Math.max(2, currentPage - Math.floor(innerVisiblePages / 2));
      let end = start + innerVisiblePages - 1;

      if (end > lastPage - 1) {
        end = lastPage - 1;
        start = end - innerVisiblePages + 1;
      }

      if (start < 2) {
        start = 2;
      }

      addPage(1);

      if (start > 2) {
        addEllipsis('ellipsis-left');
      }

      for (let page = start; page <= end; page += 1) {
        addPage(page);
      }

      if (end < lastPage - 1) {
        addEllipsis('ellipsis-right');
      }

      addPage(lastPage);

      return items;
    },
    goToPage(page) {
      if (!page || page === this.pagination.currentPage) return;
      this.fetchClients(page);
    },

    // data loading
    fetchDepartments() {
      axios.get('/api/departments', { params: { per_page: 200 } }).then((res) => {
        this.departmentOptions = res.data.data || res.data;
      });
    },
    fetchBanks() {
      axios.get('/api/banks', { params: { per_page: 200 } }).then((res) => {
        this.banks = res.data.data || res.data;
      });
    },
    fetchAssignees() {
      if (!this.canManage) return;
      axios.get('/api/users-assignees').then((res) => {
        this.assignees = res.data.data || res.data;
      });
    },
    fetchClients(page = 1) {
      this.loading = true;
      const params = {
        page,
        search: this.filters.q || undefined,
        department: this.filters.department || undefined,
        import_batch_number: this.filters.import_batch_number || undefined,
        client_type: this.filters.client_type || undefined,
        bank_id: this.filters.bank_id || undefined,
        status: this.filters.status || undefined,
        opt_in: this.filters.opt_in || undefined,
        per_page: this.pageSize,
      };

      return axios.get('/api/clients', { params }).then((res) => {
        const rawList = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
        this.clients = rawList;
        this.clientBatchOptions = res.data.batch_options || [];
        this.selectedClientIds = [];
        if (res.data.data && Array.isArray(res.data.data)) {
          this.buildPagination(res.data);
        } else {
          // not paginated
          this.pagination = {
            currentPage: 1,
            lastPage: 1,
            total: this.clients.length,
            from: 1,
            to: this.clients.length,
            prevPage: null,
            nextPage: null,
            pages: [1],
          };
        }
      }).catch((error) => {
        console.error('Failed to fetch clients:', error);
        this.clients = [];
        notify.error(error.response?.data?.message || 'Failed to load clients.', 'Clients');
      }).finally(() => {
        this.loading = false;
      });
    },

    applyFilters() {
      this.fetchClients(1);
    },
    resetFilters() {
      this.filters = { q: '', department: '', import_batch_number: '', client_type: 'all', bank_id: '', status: '', opt_in: '' };
      this.fetchClients(1);
    },
    changePageSize() {
      this.fetchClients(1);
    },

    // CRUD
    openCreateModal() {
      if (!this.canCreate) return;

      this.isEdit = false;
      this.form = {
        id: null,
        name: '',
        title: '',
        first_name: '',
        surname: '',
        email: '',
        phone: '',
        bank_id: this.canChooseBank ? '' : (this.currentUser?.bank_id || ''),
        id_number: '',
        bank_name: '',
        account_number: '',
        branch_code: '',
        easy_pay_number: '',
        store_number: '',
        outstanding_balance: '',
        arrears_amount: '',
        settlement_amount: '',
        three_months_amount: '',
        installment_amount: '',
        last_payment_amount: '',
        total_payment_amount: '',
        assigned_to_id: this.canChooseAssignee ? '' : (this.currentUser?.id || ''),
        department_ids: [],
        tags: [],
        opt_in: 'none',
        whatsapp_contact_basis: 'bank_instruction',
        whatsapp_contact_basis_details: '',
        whatsapp_opted_in_at: '',
        whatsapp_opt_in_source: 'manual_entry',
        whatsapp_opted_out: false,
        whatsapp_opt_out_reason: '',
      };
      this.selectedDepartments = [];
      this.tagsInput = '';
      this.modal.show();
    },
    openEditModal(client) {
      if (!this.canEdit) return;

      this.isEdit = true;
      
      // Load full client data with departments
      axios.get(`/api/clients/${client.id}`).then((response) => {
        const fullClient = response.data;
        const parsed = this.parseName(fullClient.name);
        
        this.form = {
          id: fullClient.id,
          name: fullClient.name,
          title: fullClient.title || parsed.title || '',
          first_name: fullClient.first_name || parsed.first_name || '',
          surname: fullClient.surname || parsed.surname || '',
          email: fullClient.email || '',
          phone: fullClient.phone || '',
          bank_id: fullClient.bank_id || '',
          id_number: fullClient.id_number || '',
          bank_name: fullClient.bank_name || '',
          account_type: fullClient.account_type || '',
          type: fullClient.type || '',
          account_number: fullClient.account_number || '',
          branch_code: fullClient.branch_code || '',
          easy_pay_number: fullClient.easy_pay_number || '',
          store_number: fullClient.store_number || '',
          outstanding_balance: fullClient.outstanding_balance || '',
          arrears_amount: fullClient.arrears_amount || '',
          settlement_amount: fullClient.settlement_amount || '',
          three_months_amount: fullClient.three_months_amount || '',
          installment_amount: fullClient.installment_amount || '',
          last_payment_amount: fullClient.last_payment_amount || '',
          total_payment_amount: fullClient.total_payment_amount || '',
          assigned_to_id: fullClient.assigned_to_id || '',
          department_ids: fullClient.departments ? fullClient.departments.map(d => d.id) : [],
          tags: fullClient.tags || [],
          opt_in: fullClient.opt_in || 'none',
          whatsapp_contact_basis: fullClient.whatsapp_contact_basis || '',
          whatsapp_contact_basis_details: fullClient.whatsapp_contact_basis_details || '',
          whatsapp_opted_in_at: this.toDateTimeLocal(fullClient.whatsapp_opted_in_at),
          whatsapp_opt_in_source: fullClient.whatsapp_opt_in_source || '',
          whatsapp_opted_out: !!fullClient.whatsapp_opted_out_at,
          whatsapp_opt_out_reason: fullClient.whatsapp_opt_out_reason || '',
        };
        
        // Set selected departments for VueMultiselect
        this.selectedDepartments = this.departmentOptions.filter(dept => 
          this.form.department_ids.includes(dept.id)
        );
        
        this.tagsInput = (fullClient.tags || []).join(', ');
        this.modal.show();
      }).catch(error => {
        console.error('Failed to load client details:', error);
        notify.error('Failed to load client details.', 'Clients');
      });
    },
    
    onOptInChange() {
      if (this.form.opt_in === 'no') {
        this.form.whatsapp_opted_out = true;
      } else {
        this.form.whatsapp_opted_out = false;
        this.form.whatsapp_opt_out_reason = '';
      }
    },

    save() {
      if (this.isEdit ? !this.canEdit : !this.canCreate) return;

      // Validate at least one department is selected
      if (this.selectedDepartments.length === 0) {
        notify.warning('Please select at least one department.', 'Clients');
        return;
      }

      if (this.canChooseBank && !this.form.bank_id) {
        notify.warning('Please select a bank for this client.', 'Clients');
        return;
      }

      if (this.form.bank_id) {
        const selectedBank = this.banks.find((bank) => bank.id === Number(this.form.bank_id));
        if (selectedBank) {
          this.form.bank_name = selectedBank.name;
        }
      }
      
      // convert tagsInput to array
      this.form.tags = this.tagsInput
        .split(',')
        .map((t) => t.trim())
        .filter((t) => t.length > 0);

      if (this.form.opt_in === 'no') {
        this.form.whatsapp_opted_out = true;
      } else {
        this.form.whatsapp_opted_out = false;
        this.form.whatsapp_opt_out_reason = '';
      }

      if (this.isEdit) {
        axios.put(`/api/clients/${this.form.id}`, this.form).then(() => {
          this.modal.hide();
          this.fetchClients(this.pagination.currentPage);
        }).catch(error => {
          console.error('Failed to update client:', error);
          notify.error('Failed to update client: ' + (error.response?.data?.message || error.message), 'Clients');
        });
      } else {
        axios.post('/api/clients', this.form).then(() => {
          this.modal.hide();
          this.fetchClients(1);
          notify.success('Client created successfully.', 'Clients');
        }).catch(error => {
          console.error('Failed to create client:', error);
          notify.error('Failed to create client: ' + (error.response?.data?.message || error.message), 'Clients');
        });
      }
    },
    
    remove(client) {
      if (!this.canDelete) return;

      this.$refs.confirmModal.open({
        title: 'Delete Client',
        message: `Delete client "${client.name}"? This action cannot be undone.`,
        confirmLabel: 'Delete Client',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            await axios.delete(`/api/clients/${client.id}`);
            await this.fetchClients(this.pagination.currentPage);
            notify.success(`Client "${client.name}" deleted.`, 'Clients');
          } catch (error) {
            console.error('Failed to delete client:', error);
            notify.error('Failed to delete client: ' + (error.response?.data?.message || error.message), 'Clients');
            throw error;
          }
        },
      });
    },
    toggleClientSelection(clientId, isSelected) {
      if (isSelected) {
        if (!this.selectedClientIds.includes(clientId)) {
          this.selectedClientIds = [...this.selectedClientIds, clientId];
        }
        return;
      }

      this.selectedClientIds = this.selectedClientIds.filter((id) => id !== clientId);
    },
    toggleSelectAll(event) {
      if (event.target.checked) {
        this.selectAllVisibleClients();
      } else {
        this.clearSelectedClients();
      }
    },
    selectAllVisibleClients() {
      this.selectedClientIds = this.clients.map((client) => client.id);
    },
    clearSelectedClients() {
      this.selectedClientIds = [];
    },
    removeSelectedClients() {
      if (!this.canManage || this.selectedClientIds.length === 0) return;

      const selectedIds = [...this.selectedClientIds];
      const selectedCount = selectedIds.length;

      this.$refs.confirmModal.open({
        title: 'Delete Selected Clients',
        message: `Delete ${selectedCount} selected client${selectedCount === 1 ? '' : 's'}? This action cannot be undone.`,
        confirmLabel: 'Delete Selected',
        confirmVariant: 'danger',
        onConfirm: async () => {
          const results = await Promise.allSettled(
            selectedIds.map((id) => axios.delete(`/api/clients/${id}`))
          );

          const failed = results.filter((result) => result.status === 'rejected');
          const deletedCount = results.length - failed.length;

          this.clearSelectedClients();
          await this.fetchClients(1);

          if (deletedCount > 0) {
            notify.success(
              `Deleted ${deletedCount} client${deletedCount === 1 ? '' : 's'}.`,
              'Clients'
            );
          }

          if (failed.length > 0) {
            const firstError =
              failed[0]?.reason?.response?.data?.message ||
              failed[0]?.reason?.message ||
              'One or more selected clients could not be deleted.';

            notify.error(
              `${failed.length} client${failed.length === 1 ? '' : 's'} could not be deleted. ${firstError}`,
              'Clients'
            );
          }
        },
      });
    },
    openAssignBatchModal() {
      if (!this.canChooseAssignee) return;
      if (!this.currentBatchFilter) {
        notify.warning('Enter or select an import batch number first.', 'Clients');
        return;
      }
      this.assignBatchForm.assigned_to_id = '';
      this.assignBatchForm.submitting = false;
      this.assignBatchModal.show();
    },
    getStatusBadgeClass(status) {
      const lower = status.toLowerCase();
      if (lower === 'active') return 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
      if (lower === 'pending') return 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
      if (lower === 'inactive') return 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
      return 'bg-light text-dark border';
    },
    async bulkUpdateStatus(newStatus) {
      if (this.selectedClientIds.length === 0) return;
      this.bulkActionLoading = true;
      try {
        const payload = {
          client_ids: this.selectedClientIds,
          status: newStatus,
        };
        const response = await axios.post('/api/clients/bulk-status', payload);
        notify.success(response.data.message || 'Status updated successfully.', 'Clients');
        this.selectedClientIds = [];
        this.fetchClients(this.pagination.currentPage);
      } catch (err) {
        notify.error(err.response?.data?.message || 'Failed to update client status.', 'Clients');
      } finally {
        this.bulkActionLoading = false;
      }
    },
    openBulkAssignModal() {
      if (!this.canChooseAssignee) {
        notify.warning('You do not have permission to assign clients.', 'Clients');
        return;
      }
      this.assignSelectedForm.assigned_to_id = '';
      this.assignSelectedModal.show();
    },
    async submitBulkAssign() {
      if (this.selectedClientIds.length === 0) return;
      this.bulkActionLoading = true;
      try {
        const payload = {
          client_ids: this.selectedClientIds,
          assigned_to_id: this.assignSelectedForm.assigned_to_id || null,
        };
        const response = await axios.post('/api/clients/bulk-assign', payload);
        notify.success(response.data.message || 'Clients assigned successfully.', 'Clients');
        this.assignSelectedModal.hide();
        this.selectedClientIds = [];
        this.fetchClients(this.pagination.currentPage);
      } catch (err) {
        notify.error(err.response?.data?.message || 'Failed to bulk assign clients.', 'Clients');
      } finally {
        this.bulkActionLoading = false;
      }
    },
    bulkDeleteClients() {
      if (this.selectedClientIds.length === 0) return;
      
      this.$refs.confirmModal.open({
        title: 'Delete Selected Clients',
        message: `Are you sure you want to delete ${this.selectedClientIds.length} selected client(s)? This action cannot be undone.`,
        confirmText: 'Delete Selected',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
          this.bulkActionLoading = true;
          try {
            const response = await axios.delete('/api/clients/bulk-delete', {
              data: { client_ids: this.selectedClientIds },
            });
            notify.success(response.data.message || 'Clients deleted successfully.', 'Clients');
            this.selectedClientIds = [];
            this.fetchClients(this.pagination.currentPage);
          } catch (error) {
            console.error('Error during bulk deletion:', error);
            notify.error(error.response?.data?.message || 'Failed to delete selected clients.', 'Clients');
          } finally {
            this.bulkActionLoading = false;
          }
        },
      });
    },
    async submitAssignBatch() {
      if (this.assignBatchForm.submitting) return;
      this.assignBatchForm.submitting = true;
      try {
        const response = await axios.post('/api/clients/assign-batch', {
          import_batch_number: this.currentBatchFilter,
          assigned_to_id: this.assignBatchForm.assigned_to_id || null,
        });
        
        this.assignBatchModal.hide();
        await this.fetchClients(this.pagination.currentPage);
        
        notify.success(
          `Assigned ${response.data?.updated_count || 0} client(s) to selected user.`,
          'Clients'
        );
      } catch (error) {
        console.error('Failed to assign clients by batch:', error);
        notify.error(
          'Failed to assign batch clients: ' + (error.response?.data?.message || error.message),
          'Clients'
        );
      } finally {
        this.assignBatchForm.submitting = false;
      }
    },
    removeBatchClients() {
      if (!this.canManage) return;

      const batchNumber = this.currentBatchFilter;
      if (!batchNumber) {
        notify.warning('Enter or select an import batch number first.', 'Clients');
        return;
      }

      this.$refs.confirmModal.open({
        title: 'Delete Clients By Batch',
        message: `Delete all accessible clients from batch ${batchNumber}? This will also remove them from any campaigns. This action cannot be undone.`,
        confirmLabel: 'Delete Batch',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            const response = await axios.delete('/api/clients/delete-batch', {
              data: {
                import_batch_number: batchNumber,
              },
            });

            this.clearSelectedClients();
            this.filters.import_batch_number = '';

            notify.success(
              `Batch deletion queued successfully. You can track progress on the Import Data page.`,
              'Clients'
            );
            
            this.$router.push({ name: 'import-uploads' });
          } catch (error) {
            console.error('Failed to delete clients by batch:', error);
            notify.error(
              'Failed to delete batch clients: ' + (error.response?.data?.message || error.message),
              'Clients'
            );
            throw error;
          }
        },
      });
    },
    
    // Department selection helpers
    selectAllDepartments() {
      if (!Array.isArray(this.departmentOptions)) return;
      this.selectedDepartments = [...this.departmentOptions];
    },
    
    clearDepartments() {
      this.selectedDepartments = [];
    },

    // Import / Export
    openImportModal() {
      if (!this.canManage) return;
      this.importForm = {
        file: null,
        bank_id: this.canChooseBank ? (this.filters.bank_id || '') : (this.currentUser?.bank_id || ''),
        department_ids: [],
        uploading: false,
      };
      this.importSelectedDepartments = [];
      if (this.$refs.importFileInput) {
        this.$refs.importFileInput.value = '';
      }
      this.importModal.show();
    },
    onImportFileSelected(event) {
      const file = event.target.files?.[0] || null;
      this.importForm.file = file;
    },
    selectAllImportDepartments() {
      if (!Array.isArray(this.departmentOptions)) return;
      this.importSelectedDepartments = [...this.departmentOptions];
    },
    clearImportDepartments() {
      this.importSelectedDepartments = [];
    },
    submitImport() {
      if (!this.importForm.file) {
        notify.warning('Please choose a CSV or Excel file to import.', 'Clients');
        return;
      }

      if (this.canChooseBank && !this.importForm.bank_id) {
        notify.warning('Please select a bank for this import.', 'Clients');
        return;
      }

      if (!this.importSelectedDepartments.length) {
        notify.warning('Please select at least one department for this import.', 'Clients');
        return;
      }

      const formData = new FormData();
      formData.append('file', this.importForm.file);
      if (this.importForm.bank_id) {
        formData.append('bank_id', this.importForm.bank_id);
      }
      for (const departmentId of this.importForm.department_ids) {
        formData.append('department_ids[]', departmentId);
      }

      this.importForm.uploading = true;

      axios
        .post('/api/clients/import', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        .then((response) => {
          const data = response.data;
          notify.success(
            `Import queued successfully (Batch ${data.import_batch_number || '-'}). You can track its progress on the Import Data page.`,
            'Clients'
          );
          if (data.errors && data.errors.length > 0) {
            console.warn('Import errors:', data.errors);
          }
          this.importModal.hide();
          this.$router.push({ name: 'import-uploads' });
        })
        .catch(error => {
          console.error('Import failed:', error);
          notify.error('Import failed: ' + (error.response?.data?.message || error.message), 'Clients');
        })
        .finally(() => {
          this.importForm.uploading = false;
          if (this.$refs.importFileInput) {
            this.$refs.importFileInput.value = '';
          }
          this.importForm.file = null;
        });
    },
    exportCsv() {
      this.$refs.exportRequestModal.open({
        dataset: 'clients',
        datasetLabel: 'Clients',
        filters: {
          search: this.filters.q || '',
          department: this.filters.department || '',
          import_batch_number: this.filters.import_batch_number || '',
          bank_id: this.filters.bank_id || '',
          status: this.filters.status || '',
        },
        summaryRows: [
          { label: 'Search', value: this.filters.q || 'All clients' },
          { label: 'Department', value: this.filters.department || 'All departments' },
          { label: 'Source / Batch', value: this.filters.import_batch_number === 'manual' ? 'Manually Created' : (this.filters.import_batch_number || 'All batches') },
          { label: 'Bank Scope', value: this.selectedBankName || 'Current access scope' },
          { label: 'Status', value: this.filters.status || 'All statuses' },
        ],
        fallbackName: 'clients.csv',
      });
    },
    toDateTimeLocal(value) {
      if (!value) return '';
      const normalized = String(value).replace(' ', 'T');
      return normalized.slice(0, 16);
    },
  },
};
</script>

<style scoped>
/* Optional custom styling for the multiselect */
:deep(.multiselect) {
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
}

:deep(.multiselect:focus-within) {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

:deep(.multiselect__tags) {
  min-height: 38px;
  border: none;
}

:deep(.multiselect__tag) {
  background: #0d6efd;
}

:deep(.multiselect__tag-icon:after) {
  color: white;
}

:deep(.multiselect__tag-icon:hover) {
  background: #0b5ed7;
}

:deep(.multiselect__option--highlight) {
  background: #0d6efd;
}

:deep(.multiselect__option--highlight:after) {
  background: #0d6efd;
}

.clients-table-wrap {
  overflow-x: auto;
}

.clients-table {
  width: 100%;
  min-width: 1120px;
  table-layout: fixed;
}

.clients-table th,
.clients-table td {
  white-space: nowrap;
}

.clients-col-select {
  width: 48px;
}

.clients-col-name {
  width: 15%;
}

.clients-col-email {
  width: 18%;
}

.clients-col-cell {
  width: 12%;
}

.clients-col-bank {
  width: 10%;
}

.clients-col-import {
  width: 12%;
}

.clients-col-created {
  width: 13%;
}

.clients-col-departments {
  width: 14%;
  white-space: normal !important;
}

.clients-col-actions {
  width: 96px;
}

.clients-cell-text {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
}

.clients-page .card,
.clients-page .table,
.clients-page .form-label,
.clients-page .form-control,
.clients-page .form-select,
.clients-page .btn,
.clients-page .pagination,
.clients-page .badge,
.clients-page small {
  font-size: 0.84rem;
}

.clients-page .table th,
.clients-page .table td {
  font-size: 0.8rem;
  padding-top: 0.45rem;
  padding-bottom: 0.45rem;
}

.clients-page .btn-group-sm > .btn,
.clients-page .btn-sm {
  font-size: 0.78rem;
}

.clients-page-size {
  width: 110px;
}
</style>
