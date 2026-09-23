<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="h4 mb-1"><i class="bi bi-diagram-3 me-2"></i>WhatsApp Flows</h2>
        <p class="text-muted mb-0">
          Automate common WhatsApp journeys with approved Meta templates.
        </p>
      </div>
      <button class="btn btn-primary btn-sm" @click="openModal" :disabled="!canManageFlows">
        <i class="bi bi-plus-circle me-1"></i> New Flow
      </button>
    </div>

    <div class="card shadow-sm border mb-4">
      <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <div class="d-flex align-items-center gap-2">
          <h5 class="card-title mb-0">All WhatsApp Flows</h5>
          <span class="badge bg-secondary">{{ filteredFlows.length }}</span>
        </div>
        <div class="input-group input-group-sm" style="max-width: 350px;">
          <span class="input-group-text bg-light border-end-0">
            <i class="bi bi-search text-muted"></i>
          </span>
          <input
            v-model="search"
            type="text"
            class="form-control border-start-0 ps-0"
            placeholder="Search flows..."
            @input="currentPage = 1"
          />
        </div>
      </div>
      <div class="card-body p-0">
        <TableLoadingWrapper :loading="loadingFlows" message="Loading WhatsApp flows..." min-height="220px">
          <div v-if="filteredFlows.length">
            <div class="table-responsive">
              <table class="table table-hover mb-0 align-middle">
                <thead>
                  <tr>
                    <th class="ps-4">Name</th>
                    <th>Template</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end pe-4">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="flow in paginatedFlows" :key="flow.id">
                    <td class="ps-4 py-3 fw-semibold">{{ flow.name }}</td>
                    <td>
                      <div class="small">
                        <div class="fw-semibold">{{ flow.template_name || flow.template_sid }}</div>
                        <div class="text-muted">Lang: {{ flow.template_language || 'n/a' }}</div>
                      </div>
                    </td>
                    <td>
                      <span class="badge" :class="flow.status === 'active' ? 'bg-success' : 'bg-secondary'">
                        {{ flow.status }}
                      </span>
                    </td>
                    <td class="text-muted small">
                      {{ formatDate(flow.created_at) }}
                    </td>
                    <td class="text-end pe-4">
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-light text-secondary border-0 p-1 px-2" @click="openDiagram(flow)" title="View diagram">
                          <i class="bi bi-diagram-3"></i>
                        </button>
                        <button class="btn btn-light text-secondary border-0 p-1 px-2" @click="startEdit(flow)" title="Edit" :disabled="!canManageFlows">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-light text-danger border-0 p-1 px-2" @click="deleteFlow(flow)" title="Delete" :disabled="!canManageFlows">
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else-if="!loadingFlows" class="text-center text-muted py-5">
            No WhatsApp flows found.
          </div>
        </TableLoadingWrapper>
      </div>

      <!-- Footer Strip with Rows Per Page & Pagination -->
      <div class="card-footer bg-white py-3 px-4 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 border-top">
        <div class="d-flex align-items-center gap-3">
          <small class="text-muted fw-medium">
            {{ paginationInfo }}
          </small>
          <div class="d-flex align-items-center gap-2">
            <label class="small text-muted fw-medium mb-0">Rows per page:</label>
            <select
              v-model.number="perPage"
              class="form-select form-select-sm border-secondary-subtle"
              style="width: 85px; font-size: 0.8rem;"
              @change="currentPage = 1"
            >
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="250">250</option>
              <option :value="500">500</option>
              <option :value="1000">1000</option>
            </select>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2">
          <button
            class="btn btn-sm btn-light border p-1 px-2"
            :disabled="currentPage <= 1"
            @click="currentPage--"
            title="Previous Page"
          >
            <i class="bi bi-chevron-left"></i>
          </button>
          <span class="small fw-semibold text-dark px-1">
            Page {{ currentPage }} of {{ totalPages }}
          </span>
          <button
            class="btn btn-sm btn-light border p-1 px-2"
            :disabled="currentPage >= totalPages"
            @click="currentPage++"
            title="Next Page"
          >
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Create flow modal -->
    <div class="modal fade" tabindex="-1" :class="{ show: showModal }" style="display: block;" v-if="showModal">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ editingFlowId ? 'Edit WhatsApp Flow' : 'Create WhatsApp Flow' }}</h5>
            <button type="button" class="btn-close" @click="closeModal"></button>
          </div>
          <form @submit.prevent="saveFlow">
            <div class="modal-body">
              <div class="row g-4">
                <!-- Left Column: Flow Details & Steps -->
                <div class="col-lg-7">
                  <div class="row g-3">
                    <div class="col-md-8">
                      <label class="form-label fw-semibold">Flow name</label>
                      <input
                        type="text"
                        class="form-control"
                        v-model="flowForm.name"
                        placeholder="e.g. Standard Bank – Call / WhatsApp follow-up"
                        required
                      />
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Status</label>
                      <select class="form-select" v-model="flowForm.status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label fw-semibold">Description</label>
                      <textarea
                        class="form-control"
                        rows="2"
                        v-model="flowForm.description"
                        placeholder="Short summary for the team"
                      ></textarea>
                    </div>
                    <div class="col-12">
                      <label class="form-label fw-semibold">Approved WhatsApp template</label>
                      <select class="form-select" v-model="flowForm.template_sid" @change="syncTemplateMeta" required>
                        <option value="" disabled>Select an approved template</option>
                        <option v-for="tpl in templates" :key="tpl.sid" :value="tpl.sid">
                          {{ tpl.name }} — {{ tpl.language }} ({{ tpl.status }})
                        </option>
                      </select>
                      <div v-if="flowForm.template_name" class="form-text">
                        Using {{ flowForm.template_name }} · Lang: {{ flowForm.template_language || 'n/a' }}
                      </div>
                    </div>
                  </div>

                  <!-- Flow steps builder -->
                  <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <label class="form-label mb-0 fw-semibold">Flow steps</label>
                      <button type="button" class="btn btn-outline-primary btn-sm" @click="addStep">
                        <i class="bi bi-plus-lg me-1"></i>Add Step
                      </button>
                    </div>
                    <div class="border rounded p-2 flow-steps">
                      <div
                        v-for="(step, idx) in flowForm.steps"
                        :key="step.id"
                        class="mb-2 p-2 rounded bg-light"
                      >
                        <div class="d-flex justify-content-between align-items-center">
                          <div>
                            <span class="fw-semibold">{{ idx + 1 }}.</span>
                            <input
                              class="form-control form-control-sm d-inline-block w-auto ms-2"
                              v-model="step.label"
                              placeholder="Step title"
                            />
                          </div>
                          <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary text-uppercase">{{ step.id }}</span>
                            <button
                              type="button"
                              class="btn btn-sm btn-outline-danger"
                              @click="removeStep(idx)"
                              :disabled="flowForm.steps.length === 1"
                            >
                              <i class="bi bi-trash"></i>
                            </button>
                          </div>
                        </div>
                        <div class="row g-2 mt-1 align-items-end">
                          <div class="col-md-5">
                            <label class="form-label small fw-semibold mb-1">Auto reply with</label>
                            <select class="form-select form-select-sm" v-model="step.reply_type">
                              <option value="message">Message</option>
                              <option value="template">WhatsApp template</option>
                            </select>
                          </div>
                          <div v-if="step.reply_type === 'template'" class="col-md-7">
                            <label class="form-label small fw-semibold mb-1">Approved template</label>
                            <select
                              class="form-select form-select-sm"
                              v-model="step.template_sid"
                              @change="handleStepTemplateChange(step)"
                              required
                            >
                              <option value="" disabled>Select an approved template</option>
                              <option v-for="tpl in templates" :key="tpl.sid" :value="tpl.sid">
                                {{ tpl.name }} — {{ tpl.language }}
                              </option>
                            </select>
                          </div>
                        </div>

                        <textarea
                          v-if="step.reply_type !== 'template'"
                          class="form-control form-control-sm mt-2"
                          rows="2"
                          v-model="step.message"
                          placeholder="Message or prompt for this step"
                          required
                        ></textarea>

                        <div v-else-if="step.template_sid" class="step-template-card mt-2">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                              <div class="fw-semibold small">{{ step.template_name || step.template_sid }}</div>
                              <div class="text-muted" style="font-size: 0.72rem;">
                                {{ step.template_language || 'n/a' }} · Approved template
                              </div>
                            </div>
                            <i class="bi bi-whatsapp text-success fs-5"></i>
                          </div>
                          <div class="step-template-preview">
                            <div v-if="step.template_header_text" class="fw-bold mb-1">
                              {{ renderStepTemplateText(step, step.template_header_text, 'header') }}
                            </div>
                            <div class="text-body" style="white-space: pre-wrap;">
                              {{ renderStepTemplateText(step, step.template_preview, 'body') || 'Template preview unavailable.' }}
                            </div>
                            <div v-if="step.template_footer_text" class="text-muted mt-2" style="font-size: 0.72rem;">
                              {{ step.template_footer_text }}
                            </div>
                          </div>

                          <div v-if="stepTemplateVariableKeys(step).length" class="mt-3">
                            <div class="small fw-semibold mb-2">Template values</div>
                            <div
                              v-for="key in stepTemplateVariableKeys(step)"
                              :key="key"
                              class="row g-2 align-items-center mb-2"
                            >
                              <div class="col-md-3">
                                <span class="badge bg-primary-subtle text-primary border w-100">{{ key }}</span>
                              </div>
                              <div class="col-md-9">
                                <select
                                  class="form-select form-select-sm"
                                  v-model="getStepTemplateVariable(step, key).source"
                                >
                                  <option value="">Select value</option>
                                  <option v-for="option in templateVariableSources" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                  </option>
                                </select>
                                <input
                                  v-if="getStepTemplateVariable(step, key).source === 'custom'"
                                  class="form-control form-control-sm mt-2"
                                  type="text"
                                  v-model="getStepTemplateVariable(step, key).custom_value"
                                  placeholder="Enter custom value"
                                />
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="mt-2 p-2 border rounded bg-white">
                          <label class="form-label small fw-semibold mb-1">
                            Expected client replies <span class="text-muted fw-normal">(optional)</span>
                          </label>
                          <textarea
                            class="form-control form-control-sm"
                            rows="2"
                            v-model="step.expected_replies_text"
                            placeholder="For example: opt-in, accept, 1"
                          ></textarea>
                          <div class="form-text" style="font-size: 0.72rem;">
                            Enter one value per line or separate values with commas. The flow advances only when the client's text, button title, or button ID matches one of these values.
                          </div>
                        </div>
                        <div class="form-check mt-2">
                          <input
                            class="form-check-input"
                            type="checkbox"
                            v-model="step.decision"
                            :id="`decision-${step.id}`"
                          />
                          <label class="form-check-label" :for="`decision-${step.id}`">
                            This step branches on Yes / No
                          </label>
                        </div>
                        <div v-if="step.decision" class="mt-2">
                          <div class="row g-2">
                            <div class="col-md-6">
                              <label class="form-label small text-success mb-1">If YES</label>
                              <textarea
                                class="form-control form-control-sm"
                                rows="2"
                                v-model="step.yesLabel"
                                placeholder="e.g. Call immediately"
                              ></textarea>
                              <label class="form-label small text-muted mt-1 mb-1">Next step on YES</label>
                              <select class="form-select form-select-sm" v-model="step.yesNextId">
                                <option :value="null">Continue to next listed</option>
                                <option
                                  v-for="opt in stepOptions(step.id)"
                                  :key="opt.id"
                                  :value="opt.id"
                                >
                                  {{ opt.label }} ({{ opt.id }})
                                </option>
                              </select>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label small text-danger mb-1">If NO</label>
                              <textarea
                                class="form-control form-control-sm"
                                rows="2"
                                v-model="step.noLabel"
                                placeholder="e.g. Ask for best time or continue on WhatsApp"
                              ></textarea>
                              <label class="form-label small text-muted mt-1 mb-1">Next step on NO</label>
                              <select class="form-select form-select-sm" v-model="step.noNextId">
                                <option :value="null">Continue to next listed</option>
                                <option
                                  v-for="opt in stepOptions(step.id)"
                                  :key="opt.id"
                                  :value="opt.id"
                                >
                                  {{ opt.label }} ({{ opt.id }})
                                </option>
                              </select>
                            </div>
                          </div>
                        </div>
                        <div v-if="step.hint" class="small text-muted mt-1">
                          {{ step.hint }}
                        </div>
                      </div>
                    </div>
                    <div class="form-text">
                      Based on the flow outline with greeting and follow-ups. Add or remove steps to match your process.
                    </div>
                  </div>
                </div>

                <!-- Right Column: WhatsApp Template Preview -->
                <div class="col-lg-5 ps-lg-3 border-start">
                  <div v-if="flowForm.template_sid" class="mb-3">
                    <div class="card border-success shadow-sm">
                      <div class="card-header py-2 d-flex justify-content-between align-items-center bg-white border-bottom-0">
                        <div class="d-flex align-items-center gap-2">
                          <strong style="font-size: 0.9rem;">Template Preview</strong>
                          <span class="badge bg-success bg-opacity-10 text-success border small" style="font-size: 0.72rem;">
                            Approved
                          </span>
                        </div>
                        <div>
                          <div class="form-check form-switch d-inline-block mb-0">
                            <input class="form-check-input" type="checkbox" id="flowSampleDataToggle" v-model="showSamplePreview">
                            <label class="form-check-label small text-muted ms-1" for="flowSampleDataToggle" style="font-size: 0.75rem;">Sample data</label>
                          </div>
                        </div>
                      </div>

                      <div class="card-body p-0 d-flex flex-column" style="background-color: #e5ddd5; position: relative; min-height: 380px;">
                        <!-- WhatsApp Phone Header -->
                        <div class="bg-white d-flex align-items-center px-3 py-2 shadow-sm position-relative" style="z-index: 2;">
                          <i class="bi bi-arrow-left me-3 text-secondary"></i>
                          <div class="bg-secondary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-person-fill text-secondary fs-4"></i>
                          </div>
                          <div class="lh-1">
                            <div class="fw-bold text-dark d-flex align-items-center gap-1 mb-1" style="font-size: 0.9rem;">
                              {{ businessName }}
                              <i class="bi bi-patch-check-fill text-success" style="font-size: 0.82rem;" title="Official business account"></i>
                            </div>
                            <div class="text-muted" style="font-size: 0.72rem;">+27 61 477 4098</div>
                          </div>
                        </div>

                        <!-- WhatsApp Chat Area -->
                        <div class="p-3 flex-grow-1 position-relative">
                          <div style="opacity: 0.05; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cGF0aCBkPSJNMCAwaDQwMHY0MDBIMHoiIGZpbGw9Im5vbmUiLz48Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjM1IiBmaWxsPSIjMDAwIi8+PC9zdmc+'); background-size: 200px; pointer-events: none;"></div>

                          <div class="bg-white rounded position-relative shadow-sm" style="max-width: 90%; border-top-left-radius: 0 !important; padding: 0.6rem; margin-left: 8px; z-index: 1;">
                            <svg viewBox="0 0 8 13" width="8" height="13" style="position: absolute; top: 0; left: -8px; color: white;">
                              <path opacity="1" fill="currentColor" d="M1.533 3.568 8 12.193V1H2.812C1.042 1 .474 2.156 1.533 3.568z"></path>
                            </svg>

                            <!-- Media Header -->
                            <div v-if="previewMedia.length" class="mb-2">
                              <img
                                v-if="previewHeaderFormat === 'IMAGE'"
                                :src="previewMedia[0]"
                                alt="Template media"
                                class="img-fluid rounded"
                                style="width: 100%; max-height: 180px; object-fit: cover;"
                              />
                              <video
                                v-else-if="previewHeaderFormat === 'VIDEO'"
                                :src="previewMedia[0]"
                                class="img-fluid rounded"
                                style="width: 100%; max-height: 180px; object-fit: cover;"
                                controls
                                preload="metadata"
                              ></video>
                              <div
                                v-else-if="previewHeaderFormat === 'DOCUMENT'"
                                class="border rounded p-3 bg-light text-center"
                              >
                                <i class="bi bi-file-earmark-arrow-down fs-3 d-block text-secondary"></i>
                              </div>
                            </div>

                            <!-- Header text -->
                            <div v-if="previewHeaderText" class="fw-bold text-dark mb-1" style="font-size: 0.92rem; line-height: 1.3;">
                              {{ previewHeaderText }}
                            </div>

                            <!-- Body text -->
                            <div class="text-dark" style="font-size: 0.88rem; line-height: 1.4; white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                              {{ previewBodyText || 'Select a template to view text content.' }}<span class="d-inline-block" style="width: 35px;"></span>
                            </div>

                            <!-- Footer and Timestamp -->
                            <div class="d-flex justify-content-between align-items-end mt-2">
                              <div class="text-muted" style="font-size: 0.72rem;">
                                {{ previewFooterText || '' }}
                              </div>
                              <div class="text-muted text-end" style="font-size: 0.65rem; margin-top: -15px; margin-right: 4px;">
                                09:31
                              </div>
                            </div>
                          </div>

                          <!-- Action / Quick Reply Buttons -->
                          <div
                            v-if="previewButtons.length"
                            class="mt-2 d-flex flex-column gap-1"
                            style="max-width: 90%; margin-left: 8px; z-index: 1; position: relative;"
                          >
                            <div
                              v-for="(button, idx) in previewButtons"
                              :key="idx"
                              class="bg-white rounded shadow-sm text-center py-2 fw-semibold"
                              style="color: #00a884; font-size: 0.85rem; border: 1px solid rgba(0,0,0,0.05);"
                            >
                              <i v-if="button.type === 'QUICK_REPLY'" class="bi bi-reply-fill me-1"></i>
                              <i v-if="button.type === 'URL'" class="bi bi-box-arrow-up-right me-1"></i>
                              <i v-if="button.type === 'PHONE_NUMBER'" class="bi bi-telephone-fill me-1"></i>
                              {{ button.text || 'Button' }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Empty State -->
                  <div v-else class="card border border-dashed text-center p-4 bg-light rounded-3">
                    <div class="py-5">
                      <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm mb-3" style="width: 56px; height: 56px;">
                        <i class="bi bi-phone text-muted fs-3"></i>
                      </div>
                      <h6 class="fw-bold text-secondary mb-1">No Template Selected</h6>
                      <p class="text-muted small mb-0" style="max-width: 240px; margin: 0 auto; font-size: 0.82rem;">
                        Select an approved WhatsApp template on the left to preview the customer smartphone experience.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="saving || !canManageFlows">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                Save Flow
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div v-if="showModal" class="modal-backdrop fade show"></div>

    <!-- Diagram modal -->
    <div class="modal fade" tabindex="-1" :class="{ show: !!diagramFlow }" style="display: block;" v-if="diagramFlow">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-diagram-3 me-2"></i>Flow Diagram — {{ diagramFlow.name }}
            </h5>
            <button type="button" class="btn-close" @click="diagramFlow = null"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small mb-2">Visual tree of this flow's steps and decisions.</p>
            <div v-if="diagramRoot" class="flow-diagram">
              <ul class="org-tree">
                <TreeNode :node="diagramRoot" :depth="0" />
              </ul>
            </div>
            <div v-else-if="diagramSteps.length" class="flow-diagram">
              <div v-for="(step, idx) in diagramSteps" :key="idx" class="diagram-node mb-3">
                <div class="small text-muted">{{ step.id }}</div>
                <div class="fw-semibold">{{ step.label || 'Step' }}</div>
                <div class="small mb-2">{{ flowStepSummary(step) }}</div>
              </div>
            </div>
            <div v-else class="text-muted">No steps available.</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" type="button" @click="diagramFlow = null">Close</button>
          </div>
        </div>
      </div>
    </div>
    <div v-if="diagramFlow" class="modal-backdrop fade show"></div>
    <ConfirmationModal ref="confirmModal" />
  </div>
</template>

<script>
import { h } from 'vue';
import axios from '../axios';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import { notify } from '../utils/notify';

const normalizeStep = (step = {}) => {
  const expectedReplies = Array.isArray(step.expected_replies) ? step.expected_replies : [];

  return {
    reply_type: 'message',
    message: '',
    expected_replies: expectedReplies,
    expected_replies_text: expectedReplies.join('\n'),
    template_sid: '',
    template_name: '',
    template_language: '',
    template_preview: '',
    template_header_text: '',
    template_footer_text: '',
    template_variables: {},
    decision: false,
    yesLabel: '',
    noLabel: '',
    yesNextId: null,
    noNextId: null,
    ...step,
    expected_replies: [...expectedReplies],
    expected_replies_text: expectedReplies.join('\n'),
    template_variables: JSON.parse(JSON.stringify(step.template_variables || {})),
  };
};

const defaultSteps = () => ([
  {
    id: 'greeting',
    label: 'Greeting',
    message: 'Good Morning | Good Day | Good Afternoon',
    decision: false,
    yesLabel: '',
    noLabel: '',
    yesNextId: null,
    noNextId: null,
  },
  {
    id: 'intro',
    label: 'Introduction',
    message: 'You have reached Strauss Daly Attorneys, how may I assist you?',
    decision: false,
  },
  {
    id: 'flow1',
    label: 'Verification',
    message: 'Please verify ID number and Date of Birth so we can locate your record.',
    decision: false,
  },
  {
    id: 'flow2',
    label: 'Reason',
    message: 'How can we help you today? (Debtor to type the reason.)',
    decision: false,
  },
  {
    id: 'flow3',
    label: 'Call Availability',
    message: 'Are you available for a phone call? Reply 1) YES 2) NO',
    decision: true,
    yesLabel: 'Make a call immediately and continue assisting.',
    noLabel: 'Ask for a suitable time for a call or move to WhatsApp.',
    yesNextId: 'flow3_yes',
    noNextId: 'flow3_no_call',
  },
  {
    id: 'flow3_yes',
    label: 'If YES',
    message: 'Make a call immediately and continue assisting.',
    hint: 'Direct call handover.',
    decision: false,
    yesLabel: '',
    noLabel: '',
    yesNextId: null,
    noNextId: null,
  },
  {
    id: 'flow3_no_call',
    label: 'If NO (schedule call)',
    message: 'Ask for a suitable time for a call. Confirm date/time and book the callback.',
    decision: false,
    yesLabel: '',
    noLabel: '',
    yesNextId: null,
    noNextId: null,
  },
  {
    id: 'flow3_no_whatsapp',
    label: 'If prefers WhatsApp',
    message: 'Ask: 1) Best time to chat? 2) Do you want to continue on WhatsApp? Follow flow 2 and resolve.',
    decision: false,
    yesLabel: '',
    noLabel: '',
    yesNextId: null,
    noNextId: null,
  },
  {
    id: 'closing',
    label: 'Closing',
    message: 'Thank you for your time. One of our consultants will follow up shortly if needed.',
    decision: false,
    yesLabel: '',
    noLabel: '',
    yesNextId: null,
    noNextId: null,
  },
]).map((step) => normalizeStep(step));

const flowStepSummary = (step = {}) => (
  step.reply_type === 'template'
    ? `Template: ${step.template_name || step.template_sid || 'Not selected'}`
    : (step.message || '')
);

const templateVariableSources = [
  { value: 'client.name', label: 'Client Full Name' },
  { value: 'client.title', label: 'Client Title' },
  { value: 'client.first_name', label: 'Client First Name' },
  { value: 'client.surname', label: 'Client Surname' },
  { value: 'client.phone', label: 'Client Phone' },
  { value: 'client.email', label: 'Client Email' },
  { value: 'client.id_number', label: 'Client ID Number' },
  { value: 'client.account_number', label: 'Client Account Number' },
  { value: 'client.easy_pay_number', label: 'Client Easy Pay Number' },
  { value: 'client.bank_name', label: 'Client Bank' },
  { value: 'client.branch_code', label: 'Client Branch Code' },
  { value: 'client.outstanding_balance', label: 'Outstanding Balance' },
  { value: 'client.arrears_amount', label: 'Arrears Amount' },
  { value: 'client.settlement_amount', label: 'Settlement Amount' },
  { value: 'client.three_months_amount', label: 'Three Months Amount' },
  { value: 'client.installment_amount', label: 'Installment Amount' },
  { value: 'campaign.name', label: 'Campaign Name' },
  { value: 'campaign.status', label: 'Campaign Status' },
  { value: 'custom', label: 'Custom Value' },
];

const TreeNode = {
  name: 'TreeNode',
  props: {
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
    branch: { type: String, default: null },
  },
  render() {
    const node = this.node || {};
    const step = node.step || {};
    const depth = this.depth || 0;
    const branch = this.branch;

    const children = Array.isArray(node.children)
      ? node.children.map((child, idx) =>
          h(TreeNode, { node: child.node, depth: depth + 1, branch: child.label, key: idx })
        )
      : [];

    const cardClasses = ['org-card'];
    if (step.decision) cardClasses.push('decision');
    if (branch) cardClasses.push(branch === 'YES' ? 'branch-yes' : 'branch-no');

    return h('li', { class: 'org-li' }, [
      h('div', { class: cardClasses.join(' ') }, [
        h('div', { class: 'small text-muted text-uppercase mb-1' }, step.id || ''),
        h('div', { class: 'fw-semibold' }, step.label || 'Step'),
        h('div', { class: 'small mb-2 text-muted' }, flowStepSummary(step)),
        step.decision
          ? h('div', { class: 'decision-grid' }, [
              h('div', { class: 'decision-card yes' }, [
                h('div', { class: 'text-success small fw-semibold' }, 'YES'),
                h('div', { class: 'small' }, step.yesLabel || 'Continue on Yes path'),
                step.yesNextId ? h('div', { class: 'small text-muted' }, `➡ ${step.yesNextId}`) : null,
              ]),
              h('div', { class: 'decision-card no' }, [
                h('div', { class: 'text-danger small fw-semibold' }, 'NO'),
                h('div', { class: 'small' }, step.noLabel || 'Continue on No path'),
                step.noNextId ? h('div', { class: 'small text-muted' }, `➡ ${step.noNextId}`) : null,
              ]),
            ])
          : null,
      ]),
      children.length ? h('ul', { class: 'org-children' }, children) : null,
    ]);
  },
};

export default {
  name: 'WhatsAppFlows',
  components: {
    ConfirmationModal,
    TableLoadingWrapper,
    TreeNode,
  },
  data() {
    return {
      flows: [],
      loadingFlows: false,
      templates: [],
      templateVariableSources,
      saving: false,
      showModal: false,
      editingFlowId: null,
      diagramFlow: null,
      search: '',
      perPage: 25,
      currentPage: 1,
      flowForm: {
        name: '',
        description: '',
        template_sid: '',
        template_name: '',
        template_language: '',
        status: 'active',
        steps: defaultSteps(),
      },
      templatePreview: {
        media: [],
        header_format: null,
        header_text: null,
        footer_text: null,
        body_preview: '',
        buttons: [],
        variables: [],
      },
      showSamplePreview: false,
    };
  },
  computed: {
    businessName() {
      try {
        const raw = localStorage.getItem('nexus_system_settings');
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed.system_name) return parsed.system_name;
        }
      } catch (e) {}
      return 'Strauss Recovery Solutions';
    },
    selectedTemplate() {
      if (!this.flowForm.template_sid) return null;
      return this.templates.find((t) => t.sid === this.flowForm.template_sid || t.id === this.flowForm.template_sid) || null;
    },
    previewHeaderText() {
      const tpl = this.selectedTemplate || this.templatePreview;
      let text = this.templatePreview.header_text || tpl.header_text || '';
      if (!this.showSamplePreview || !text) return text;
      return text.replace(/{{(\d+)}}/g, (match, p1) => `Sample ${p1}`);
    },
    previewBodyText() {
      const tpl = this.selectedTemplate || {};
      let text = this.templatePreview.body_preview || tpl.body_preview || tpl.preview || '';
      if (!this.showSamplePreview || !text) return text;
      return text.replace(/{{(\d+)}}/g, (match, p1) => {
        const samples = { '1': 'John Doe', '2': 'R1,250.00', '3': 'R450.00' };
        return samples[p1] || `[Variable ${p1}]`;
      });
    },
    previewButtons() {
      return this.templatePreview.buttons?.length
        ? this.templatePreview.buttons
        : (this.selectedTemplate?.buttons || []);
    },
    previewMedia() {
      return this.templatePreview.media?.length
        ? this.templatePreview.media
        : (this.selectedTemplate?.media_urls || this.selectedTemplate?.media || []);
    },
    previewHeaderFormat() {
      return this.templatePreview.header_format || this.selectedTemplate?.header_format || null;
    },
    previewFooterText() {
      return this.templatePreview.footer_text || this.selectedTemplate?.footer_text || null;
    },
    filteredFlows() {
      if (!this.search.trim()) {
        return this.flows;
      }
      const q = this.search.toLowerCase().trim();
      return this.flows.filter((f) =>
        (f.name && f.name.toLowerCase().includes(q)) ||
        (f.template_name && f.template_name.toLowerCase().includes(q)) ||
        (f.template_sid && f.template_sid.toLowerCase().includes(q)) ||
        (f.status && f.status.toLowerCase().includes(q)) ||
        (f.description && f.description.toLowerCase().includes(q))
      );
    },
    totalPages() {
      return Math.ceil(this.filteredFlows.length / this.perPage) || 1;
    },
    paginatedFlows() {
      const start = (this.currentPage - 1) * this.perPage;
      return this.filteredFlows.slice(start, start + this.perPage);
    },
    paginationInfo() {
      const total = this.filteredFlows.length;
      if (total === 0) return 'Showing 0 of 0 records';
      const start = (this.currentPage - 1) * this.perPage + 1;
      const end = Math.min(this.currentPage * this.perPage, total);
      return `Showing ${start} to ${end} of ${total} records`;
    },
    currentUser() {
      try {
        return JSON.parse(localStorage.getItem('nexus_user') || '{}');
      } catch {
        return {};
      }
    },
    currentRoleCodes() {
      if (Array.isArray(this.currentUser?.role_codes) && this.currentUser.role_codes.length) {
        return this.currentUser.role_codes;
      }

      return this.currentUser?.role ? [this.currentUser.role] : [];
    },
    canManageFlows() {
      return this.hasPermission('manage_whatsapp_flows');
    },
    imageMedia() {
      return this.templatePreview?.media || [];
    },
    diagramSteps() {
      if (!this.diagramFlow) return [];
      const steps = this.diagramFlow.flow_definition;
      if (Array.isArray(steps)) return steps;
      if (typeof steps === 'string') {
        try {
          const parsed = JSON.parse(steps);
          return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
          return [];
        }
      }
      return [];
    },
    diagramRoot() {
      const steps = this.diagramSteps;
      if (!steps.length) return null;
      const map = new Map();
      steps.forEach((s) => map.set(s.id, s));

      const findIndex = (id) => steps.findIndex((s) => s.id === id);

      const buildNode = (stepId, visited = new Set()) => {
        const step = map.get(stepId);
        if (!step || visited.has(stepId)) return null;
        const nextVisited = new Set(visited);
        nextVisited.add(stepId);

        const idx = findIndex(stepId);
        const defaultNext = steps[idx + 1]?.id || null;

        const children = [];
        if (step.decision) {
          const yesId = step.yesNextId || defaultNext;
          const noId = step.noNextId || defaultNext;
          if (yesId) {
            const child = buildNode(yesId, nextVisited);
            if (child) children.push({ label: 'YES', node: child });
          }
          if (noId) {
            const child = buildNode(noId, nextVisited);
            if (child) children.push({ label: 'NO', node: child });
          }
        } else if (defaultNext) {
          const child = buildNode(defaultNext, nextVisited);
          if (child) children.push({ label: null, node: child });
        }

        return { step, children };
      };

      const rootId = steps[0]?.id;
      return rootId ? buildNode(rootId) : null;
    },
  },
  mounted() {
    this.fetchFlows();
    this.fetchTemplates();
  },
  methods: {
    hasPermission(permCode) {
      if (!this.currentUser) return false;
      if (this.currentRoleCodes.includes('SUPER_ADMIN')) {
        return true;
      }

      if (Array.isArray(this.currentUser.permission_codes)) {
        return this.currentUser.permission_codes.includes(permCode);
      }

      return false;
    },
    async fetchFlows() {
      this.loadingFlows = true;
      try {
        const res = await axios.get('/api/whatsapp-flows');
        this.flows = res.data || [];
      } catch (e) {
        console.error('Failed to load WhatsApp flows', e);
      } finally {
        this.loadingFlows = false;
      }
    },
    stepOptions(excludeId) {
      return (this.flowForm.steps || [])
        .filter((s) => s.id !== excludeId)
        .map((s) => ({
          id: s.id,
          label: s.label || s.id,
        }));
    },
    selectedStepTemplate(step) {
      if (!step?.template_sid) return null;
      return this.templates.find((template) => (
        template.sid === step.template_sid || template.id === step.template_sid
      )) || null;
    },
    handleStepTemplateChange(step) {
      const template = this.selectedStepTemplate(step);
      if (!template) return;

      step.template_name = template.name || template.friendly_name || template.sid;
      step.template_language = template.language || '';
      step.template_preview = template.body_preview || template.preview || '';
      step.template_header_text = template.header_text || '';
      step.template_footer_text = template.footer_text || '';

      const currentMappings = step.template_variables || {};
      step.template_variables = Object.keys(template.variables || {}).reduce((mappings, key) => {
        const current = currentMappings[key] || {};
        mappings[key] = {
          source: current.source || '',
          custom_value: current.custom_value || '',
        };
        return mappings;
      }, {});
    },
    stepTemplateVariableKeys(step) {
      const template = this.selectedStepTemplate(step);
      return Object.keys(template?.variables || step?.template_variables || {});
    },
    getStepTemplateVariable(step, key) {
      if (!step.template_variables || typeof step.template_variables !== 'object') {
        step.template_variables = {};
      }
      if (!step.template_variables[key]) {
        step.template_variables[key] = { source: '', custom_value: '' };
      }
      return step.template_variables[key];
    },
    renderStepTemplateText(step, text, prefix) {
      return String(text || '').replace(/{{(\d+)}}/g, (match, number) => {
        const key = `${prefix}_${number}`;
        const mapping = step.template_variables?.[key];
        if (!mapping?.source) return match;
        if (mapping.source === 'custom') return mapping.custom_value || match;
        const option = this.templateVariableSources.find((item) => item.value === mapping.source);
        return option ? `[${option.label}]` : match;
      });
    },
    flowStepSummary,
    parseExpectedReplies(value) {
      const seen = new Set();
      return String(value || '')
        .split(/[\n,]+/)
        .map((reply) => reply.trim())
        .filter((reply) => {
          if (!reply) return false;
          const key = reply.toLocaleLowerCase();
          if (seen.has(key)) return false;
          seen.add(key);
          return true;
        });
    },
    serializeFlowSteps() {
      return this.flowForm.steps.map((step) => {
        const { expected_replies_text: expectedRepliesText, ...storedStep } = step;
        return {
          ...storedStep,
          expected_replies: this.parseExpectedReplies(expectedRepliesText),
        };
      });
    },
    async fetchTemplates() {
      try {
        const res = await axios.get('/api/whatsapp-templates?approved=1');
        this.templates = res.data || [];
      } catch (e) {
        console.error('Failed to load templates', e);
      }
    },
    syncTemplateMeta() {
      const tpl = this.templates.find((t) => t.sid === this.flowForm.template_sid || t.id === this.flowForm.template_sid);
      if (tpl) {
        this.flowForm.template_name = tpl.name;
        this.flowForm.template_language = tpl.language;
        const listMedia = tpl.media_urls || tpl.media || [];
        this.templatePreview = {
          media: Array.isArray(listMedia) ? listMedia : [],
          header_format: tpl.header_format || null,
          header_text: tpl.header_text || null,
          footer_text: tpl.footer_text || null,
          body_preview: tpl.body_preview || tpl.preview || '',
          buttons: tpl.buttons || [],
          variables: tpl.variables || [],
        };
        this.fetchTemplatePreview(tpl.sid || tpl.id);
      } else {
        this.templatePreview = {
          media: [],
          header_format: null,
          header_text: null,
          footer_text: null,
          body_preview: '',
          buttons: [],
          variables: [],
        };
      }
    },
    async fetchTemplatePreview(templateSid) {
      if (!templateSid) return;
      try {
        const res = await axios.get(`/api/whatsapp-templates/${templateSid}`);
        const template = res.data?.template || {};
        this.templatePreview = {
          media: template.media_urls || template.media || this.templatePreview.media || [],
          header_format: template.header_format || this.templatePreview.header_format,
          header_text: template.header_text || this.templatePreview.header_text,
          footer_text: template.footer_text || this.templatePreview.footer_text,
          body_preview: template.preview || template.body_preview || this.templatePreview.body_preview || '',
          buttons: template.buttons || this.templatePreview.buttons || [],
          variables: template.variables || this.templatePreview.variables || [],
        };
      } catch (e) {
        console.error('Failed to load template preview', e);
      }
    },
    openModal() {
      if (!this.canManageFlows) return;

      this.resetForm();
      this.showModal = true;
    },
    closeModal() {
      if (!this.saving) {
        this.showModal = false;
      }
    },
    resetForm() {
      this.flowForm = {
        name: '',
        description: '',
        template_sid: '',
        template_name: '',
        template_language: '',
        status: 'active',
        steps: defaultSteps(),
      };
      this.templatePreview = {
        media: [],
        header_format: null,
        header_text: null,
        footer_text: null,
        body_preview: '',
        buttons: [],
        variables: [],
      };
      this.showSamplePreview = false;
      this.editingFlowId = null;
    },
    addStep() {
      const id = `step-${Date.now()}`;
      this.flowForm.steps.push(normalizeStep({
        id,
        label: 'New Step',
      }));
    },
    removeStep(idx) {
      if (this.flowForm.steps.length === 1) return;
      this.flowForm.steps.splice(idx, 1);
    },
    startEdit(flow) {
      if (!this.canManageFlows) return;

      this.editingFlowId = flow.id;
      this.flowForm = {
        name: flow.name,
        description: flow.description,
        template_sid: flow.template_sid,
        template_name: flow.template_name,
        template_language: flow.template_language,
        status: flow.status || 'active',
        steps: Array.isArray(flow.flow_definition)
          ? JSON.parse(JSON.stringify(flow.flow_definition)).map((step) => normalizeStep(step))
          : defaultSteps(),
      };
      this.templatePreview = {
        media: flow.media || [],
        header_format: flow.header_format || null,
        header_text: flow.header_text || null,
        footer_text: flow.footer_text || null,
      };
      this.showModal = true;
      this.syncTemplateMeta();
    },
    async deleteFlow(flow) {
      if (!this.canManageFlows) return;

      this.$refs.confirmModal.open({
        title: 'Delete WhatsApp Flow',
        message: `Delete flow "${flow.name}"? This action cannot be undone.`,
        confirmLabel: 'Delete Flow',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            await axios.delete(`/api/whatsapp-flows/${flow.id}`);
            await this.fetchFlows();
            notify.success(`Flow "${flow.name}" deleted.`, 'WhatsApp Flows');
          } catch (e) {
            console.error('Failed to delete flow', e);
            notify.error('Failed to delete flow.', 'WhatsApp Flows');
            throw e;
          }
        },
      });
    },
    async openDiagram(flow) {
      this.diagramFlow = null;
      try {
        const res = await axios.get(`/api/whatsapp-flows/${flow.id}`);
        this.diagramFlow = res.data || flow;
      } catch (e) {
        console.error('Failed to load flow for diagram, using cached flow', e);
        this.diagramFlow = flow;
      }
    },
    async saveFlow() {
      if (!this.canManageFlows) return;

      const validationError = this.validateFlowSteps();
      if (validationError) {
        notify.error(validationError, 'WhatsApp Flows');
        return;
      }

      this.saving = true;
      try {
        const payload = {
          name: this.flowForm.name,
          description: this.flowForm.description,
          template_sid: this.flowForm.template_sid,
          template_name: this.flowForm.template_name,
          template_language: this.flowForm.template_language,
          status: this.flowForm.status || 'active',
          flow_definition: this.serializeFlowSteps(),
        };

        if (this.editingFlowId) {
          await axios.put(`/api/whatsapp-flows/${this.editingFlowId}`, payload);
        } else {
          await axios.post('/api/whatsapp-flows', payload);
        }
        await this.fetchFlows();
        this.resetForm();
        this.showModal = false;
      } catch (e) {
        console.error('Failed to save WhatsApp flow', e);
        notify.error(e.response?.data?.message || 'Failed to save WhatsApp flow.', 'WhatsApp Flows');
      } finally {
        this.saving = false;
      }
    },
    validateFlowSteps() {
      for (const [index, step] of this.flowForm.steps.entries()) {
        const stepName = step.label || `Step ${index + 1}`;
        if (step.reply_type === 'template') {
          if (!step.template_sid) {
            return `Select an approved template for ${stepName}.`;
          }
          for (const key of this.stepTemplateVariableKeys(step)) {
            const mapping = step.template_variables?.[key];
            if (!mapping?.source) {
              return `Select a value for ${key} in ${stepName}.`;
            }
            if (mapping.source === 'custom' && !String(mapping.custom_value || '').trim()) {
              return `Enter a custom value for ${key} in ${stepName}.`;
            }
          }
        } else if (!String(step.message || '').trim()) {
          return `Enter a message for ${stepName}.`;
        }
      }
      return null;
    },
    formatDate(value) {
      if (!value) return '';
      return new Date(value).toLocaleString();
    },
  },
};
</script>

<style scoped>
.flow-steps textarea {
  font-size: 0.9rem;
}
.step-template-card {
  padding: 0.75rem;
  border: 1px solid #cfe7dc;
  border-radius: 0.5rem;
  background: #e7f5ef;
}
.step-template-preview {
  position: relative;
  max-width: 95%;
  padding: 0.65rem 0.75rem;
  border-radius: 0.45rem 0.45rem 0.45rem 0;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
  font-size: 0.84rem;
  line-height: 1.4;
}
.step-template-preview::before {
  position: absolute;
  bottom: 0;
  left: -7px;
  width: 0;
  height: 0;
  border-top: 8px solid transparent;
  border-right: 8px solid #ffffff;
  content: '';
}
.modal-body {
  max-height: 70vh;
  overflow-y: auto;
}
.template-media img,
.template-media video {
  max-height: 160px;
  object-fit: cover;
}
:deep(.flow-diagram) {
  position: relative;
  text-align: center;
}
:deep(.org-tree),
:deep(.org-tree ul) {
  padding-top: 12px;
  position: relative;
  text-align: center;
  margin: 0 auto 6px;
  padding-left: 0;
  display: inline-block;
}
:deep(.org-tree ul) {
  display: flex;
  justify-content: center;
}
:deep(.org-tree li) {
  list-style-type: none;
  position: relative;
  padding: 12px 4px 0 4px;
  text-align: center;
}
:deep(.org-tree li::before),
:deep(.org-tree li::after) {
  content: '';
  position: absolute;
  top: 0;
  right: 50%;
  border-top: 1px solid #c0c4cc;
  width: 50%;
  height: 14px;
}
:deep(.org-tree li::after) {
  right: auto;
  left: 50%;
  border-left: 1px solid #c0c4cc;
}
:deep(.org-tree li:only-child::before),
:deep(.org-tree li:only-child::after) {
  display: none;
}
:deep(.org-tree li:only-child) {
  padding-top: 0;
}
:deep(.org-tree li:first-child::before),
:deep(.org-tree li:last-child::after) {
  border: 0 none;
}
:deep(.org-tree li:last-child::before) {
  border-right: 1px solid #c0c4cc;
  border-radius: 0 5px 0 0;
}
:deep(.org-tree li:first-child::after) {
  border-radius: 5px 0 0 0;
}
:deep(.org-tree ul ul::before) {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  border-left: 1px solid #c0c4cc;
  width: 0;
  height: 14px;
}
:deep(.org-li) {
  display: inline-block;
}
:deep(.org-card) {
  display: inline-block;
  min-width: 180px;
  background: linear-gradient(180deg, #eaf4ff 0%, #d7e9ff 100%);
  border: 1px solid #c1d7f7;
  border-radius: 8px;
  padding: 10px 12px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
  font-size: 0.92rem;
}
:deep(.org-card.decision) {
  background: linear-gradient(180deg, #ffe9d7 0%, #ffd9b8 100%);
  border-color: #f5cba7;
}
:deep(.org-card.branch-yes) {
  border-color: #a7e0b1;
}
:deep(.org-card.branch-no) {
  border-color: #f5b7b1;
}
:deep(.decision-grid) {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.25rem;
  margin-top: 0.25rem;
}
:deep(.decision-card) {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 0.5rem;
  background: #fff;
  font-size: 0.92rem;
}
:deep(.decision-card.yes) {
  border-color: #d1e7dd;
  background: #f0fff4;
}
:deep(.decision-card.no) {
  border-color: #f8d7da;
  background: #fff5f5;
}
</style>
