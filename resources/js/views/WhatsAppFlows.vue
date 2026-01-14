<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="h4 mb-1"><i class="bi bi-diagram-3 me-2"></i>WhatsApp Flows</h2>
        <p class="text-muted mb-0">
          Automate common WhatsApp journeys with approved Twilio templates.
        </p>
      </div>
      <button class="btn btn-primary btn-sm" @click="openModal">
        <i class="bi bi-plus-circle me-1"></i> New Flow
      </button>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">All WhatsApp Flows</h5>
          <span class="badge bg-secondary">{{ flows.length }}</span>
        </div>

        <div v-if="flows.length">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Template</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="flow in flows" :key="flow.id">
                  <td class="fw-semibold">{{ flow.name }}</td>
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
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <button class="btn btn-outline-secondary" @click="openDiagram(flow)" title="View diagram">
                        <i class="bi bi-diagram-3"></i>
                      </button>
                      <button class="btn btn-outline-primary" @click="startEdit(flow)" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-outline-danger" @click="deleteFlow(flow)" title="Delete">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="text-center text-muted py-4">
          No WhatsApp flows yet. Create your first flow to get started.
        </div>
      </div>
    </div>

    <!-- Create flow modal -->
    <div class="modal fade" tabindex="-1" :class="{ show: showModal }" style="display: block;" v-if="showModal">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Create WhatsApp Flow</h5>
            <button type="button" class="btn-close" @click="closeModal"></button>
          </div>
          <form @submit.prevent="saveFlow">
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">Flow name</label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="flowForm.name"
                    placeholder="e.g. Standard Bank – Call / WhatsApp follow-up"
                    required
                  />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select class="form-select" v-model="flowForm.status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Description</label>
                  <textarea
                    class="form-control"
                    rows="2"
                    v-model="flowForm.description"
                    placeholder="Short summary for the team"
                  ></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label">Twilio approved template</label>
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
                <div class="col-12" v-if="imageMedia.length">
                  <label class="form-label d-flex align-items-center gap-2">
                    <i class="bi bi-image"></i> Template media preview
                  </label>
                  <div class="d-flex gap-2 flex-wrap">
                    <div v-for="(url, idx) in imageMedia" :key="idx" class="border rounded p-2 bg-light" style="max-width: 200px;">
                      <div class="small text-muted text-truncate" :title="url">Asset {{ idx + 1 }}</div>
                      <div class="template-media">
                        <img :src="url" class="img-fluid rounded" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label class="form-label mb-0">Flow steps</label>
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
                    <textarea
                      class="form-control form-control-sm mt-2"
                      rows="2"
                      v-model="step.message"
                      placeholder="Message or prompt for this step"
                    ></textarea>
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
                  Based on the uploaded flow outline (Standard Bank tab) with call / WhatsApp follow-ups. Add or remove steps to match your process.
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="saving">
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
                <div class="small mb-2">{{ step.message }}</div>
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
  </div>
</template>

<script>
import { h } from 'vue';
import axios from '../axios';

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
]);

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
        h('div', { class: 'small mb-2 text-muted' }, step.message || ''),
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
    TreeNode,
  },
  data() {
    return {
      flows: [],
      templates: [],
      saving: false,
      showModal: false,
      editingFlowId: null,
      diagramFlow: null,
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
      },
    };
  },
  computed: {
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
    async fetchFlows() {
      try {
        const res = await axios.get('/api/whatsapp-flows');
        this.flows = res.data || [];
      } catch (e) {
        console.error('Failed to load WhatsApp flows', e);
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
    async fetchTemplates() {
      try {
        const res = await axios.get('/api/whatsapp-templates?approved=1');
        this.templates = res.data || [];
      } catch (e) {
        console.error('Failed to load templates', e);
      }
    },
    syncTemplateMeta() {
      const tpl = this.templates.find((t) => t.sid === this.flowForm.template_sid);
      if (tpl) {
        this.flowForm.template_name = tpl.name;
        this.flowForm.template_language = tpl.language;
        // Try to use lightweight media from the list response, then fetch full template for richer preview
        const listMedia = tpl.media_urls || tpl.media || [];
        this.templatePreview = { media: listMedia };
        this.fetchTemplatePreview(tpl.sid);
      } else {
        this.templatePreview = { media: [] };
      }
    },
    async fetchTemplatePreview(templateSid) {
      if (!templateSid) return;
      try {
        const res = await axios.get(`/api/whatsapp-templates/${templateSid}`);
        const template = res.data?.template || {};
        const types = template.types || {};
        let media = [];
        if (Array.isArray(types['twilio/media']?.media)) {
          media = types['twilio/media'].media;
        } else if (Array.isArray(types['twilio/card']?.media)) {
          media = types['twilio/card'].media;
        }
        this.templatePreview = { media };
      } catch (e) {
        console.error('Failed to load template preview', e);
      }
    },
    openModal() {
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
      this.templatePreview = { media: [] };
      this.editingFlowId = null;
    },
    addStep() {
      const id = `step-${Date.now()}`;
      this.flowForm.steps.push({
        id,
        label: 'New Step',
        message: '',
        decision: false,
        yesLabel: '',
        noLabel: '',
        yesNextId: null,
        noNextId: null,
      });
    },
    removeStep(idx) {
      if (this.flowForm.steps.length === 1) return;
      this.flowForm.steps.splice(idx, 1);
    },
    startEdit(flow) {
      this.editingFlowId = flow.id;
      this.flowForm = {
        name: flow.name,
        description: flow.description,
        template_sid: flow.template_sid,
        template_name: flow.template_name,
        template_language: flow.template_language,
        status: flow.status || 'active',
        steps: Array.isArray(flow.flow_definition)
          ? JSON.parse(JSON.stringify(flow.flow_definition)).map((s) => ({
              decision: false,
              yesLabel: '',
              noLabel: '',
              yesNextId: null,
              noNextId: null,
              ...s,
            }))
          : defaultSteps(),
      };
      this.templatePreview = { media: flow.media || [] };
      this.showModal = true;
      this.syncTemplateMeta();
    },
    async deleteFlow(flow) {
      const ok = confirm(`Delete flow "${flow.name}"? This cannot be undone.`);
      if (!ok) return;
      try {
        await axios.delete(`/api/whatsapp-flows/${flow.id}`);
        await this.fetchFlows();
      } catch (e) {
        console.error('Failed to delete flow', e);
      }
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
      this.saving = true;
      try {
        const payload = {
          name: this.flowForm.name,
          description: this.flowForm.description,
          template_sid: this.flowForm.template_sid,
          template_name: this.flowForm.template_name,
          template_language: this.flowForm.template_language,
          status: this.flowForm.status || 'active',
          flow_definition: this.flowForm.steps,
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
      } finally {
        this.saving = false;
      }
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
