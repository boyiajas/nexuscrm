<template>
  <div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <button class="btn btn-outline-secondary btn-sm mb-2" @click="goBack">
          ← Back to Campaign
        </button>
        <h2 class="h4 mb-0">WhatsApp Template Preview</h2>
        <small class="text-muted">
          Campaign #{{ campaignId }}
        </small>
      </div>
      <div class="text-end">
        <button
          class="btn btn-success btn-sm"
          :disabled="!selectedTemplate"
          @click="useThisTemplate"
        >
          <i class="bi bi-check-circle me-1"></i>
          Use This Template
        </button>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <!-- Template selector -->
        <div class="mb-3">
          <label class="form-label">WhatsApp Template</label>
          <select
            v-model="selectedTemplateId"
            class="form-select"
            @change="onTemplateChange"
          >
            <option value="">-- Select a template --</option>
            <option
              v-for="t in templates"
              :key="t.id"
              :value="t.id"
            >
              {{ t.name }} ({{ t.language }}) – {{ t.category || 'N/A' }}
            </option>
          </select>
        </div>

        <div v-if="loading" class="text-center my-4">
          <div class="spinner-border spinner-border-sm" role="status"></div>
          <span class="ms-2 small text-muted">Loading template...</span>
        </div>

        <div v-if="error" class="alert alert-danger py-2">
          {{ error }}
        </div>

        <!-- Preview -->
        <div v-if="selectedTemplate" class="mt-3">
          <h5 class="mb-2">
            <i class="bi bi-whatsapp text-success me-1"></i>
            {{ selectedTemplate.name }}
          </h5>

          <p class="small text-muted mb-1">
            Language: <strong>{{ selectedTemplate.language || 'N/A' }}</strong>
            <span class="ms-2">Category: <strong>{{ selectedTemplate.category || 'N/A' }}</strong></span>
          </p>

          <div
                v-if="selectedTemplate.media_urls && selectedTemplate.media_urls.length"
                class="mb-3"
            >
                <h6 class="mb-1">Header Image</h6>
                <img
                :src="selectedTemplate.media_urls[0]"
                alt="WhatsApp template media"
                class="img-fluid rounded border"
                style="max-height: 260px; object-fit: contain;"
                />
                <small class="text-muted d-block mt-1">
                Source: {{ selectedTemplate.media_urls[0] }}
                </small>
            </div>

          <div class="card mb-3">
            <div class="card-header py-2">
              <strong>Body Preview</strong>
            </div>
            <div class="card-body py-2">
              <pre class="mb-0 small">{{ selectedTemplate.body_preview }}</pre>
            </div>
          </div>

          <!-- Variables (placeholders) -->
          <div v-if="Object.keys(selectedTemplate.variables || {}).length" class="mb-3">
            <h6 class="mb-1">Template Variables</h6>
            <ul class="small mb-0">
              <li v-for="(label, key) in selectedTemplate.variables" :key="key">
                <code v-text="'{{' + key + '}}'"></code>
                → {{ label }}
              </li>
            </ul>
          </div>
        </div>

        <div v-else-if="!loading" class="text-muted small">
          Select a template from the dropdown to preview it.
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';

export default {
  name: 'WhatsappTemplatePreview',
  data() {
    return {
      templates: [],
      selectedTemplateId: '',
      selectedTemplateDetails: null,
      loading: false,
      error: null,
    };
  },
  computed: {
    campaignId() {
      return this.$route.params.id;
    },
    selectedTemplate() {
      return this.templates.find((t) => t.id === this.selectedTemplateId) || null;
    },
  },
  created() {
    this.loadTemplates();
  },
  methods: {
    loadTemplates() {
      this.loading = true;
      this.error = null;

      axios
        .get('/api/whatsapp-templates')
        .then((res) => {
          this.templates = res.data.data || res.data;

          // Preselect from route param if present
          const paramSid = this.$route.params.templateSid || this.$route.query.whatsapp_template;
          if (paramSid && this.templates.some((t) => t.id === paramSid)) {
            this.selectedTemplateId = paramSid;
          } else if (this.templates.length) {
            this.selectedTemplateId = this.templates[0].id;
          }
        })
        .catch((err) => {
          console.error('Failed to load WhatsApp templates', err);
          this.error = err.response?.data?.message || err.message;
        })
        .finally(() => {
          this.loading = false;
        });
    },
    onTemplateChange() {
      // You could call /api/whatsapp-templates/{id} here
      // if you want additional deep details per template.
      // For now we just rely on the basic list data.
    },
    goBack() {
      this.$router.push({
        name: 'campaign.show',
        params: { id: this.campaignId },
      });
    },
    useThisTemplate() {
      if (!this.selectedTemplateId) {
        alert('Please select a template first.');
        return;
      }

      // Navigate back to campaign show with the chosen template in query
      this.$router.push({
        name: 'campaign.show',
        params: { id: this.campaignId },
        query: { whatsapp_template: this.selectedTemplateId },
      });
    },
  },
};
</script>
