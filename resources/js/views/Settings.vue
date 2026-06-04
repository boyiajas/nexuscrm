<template>
  <div>
    <h2 class="h4 mb-3" style="background-color:#0087ff0f"><i class="bi bi-gear me-2"></i>Settings</h2>

    <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button">
          User Account
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
          Security & MFA
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button">
          Preferences
        </button>
      </li>
      <li class="nav-item" role="presentation" v-if="isSuperAdmin">
        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
          System Settings
        </button>
      </li>
      <li class="nav-item" role="presentation" v-if="isSuperAdmin">
        <button class="nav-link" id="twilio-tab" data-bs-toggle="tab" data-bs-target="#twilio" type="button">
          Meta WhatsApp
        </button>
      </li>
      <li class="nav-item" role="presentation" v-if="isSuperAdmin">
        <button class="nav-link" id="whatsapp-templates-tab" data-bs-toggle="tab" data-bs-target="#whatsapp-templates" type="button">
          WhatsApp Templates
        </button>
      </li>
    </ul>

    <div class="tab-content p-3 border bg-white rounded shadow-sm">

      <!-- ACCOUNT TAB -->
      <div class="tab-pane fade show active" id="account">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">User Account Information</h5>
          <div class="form-check form-switch">
            <label class="form-check-label me-2">Active user</label>
            <input class="form-check-input" type="checkbox" v-model="form.active">
          </div>
        </div>
        <small class="text-muted d-block mb-3">Basic information and working information for this user.</small>

        <div class="account-layout">
            <form @submit.prevent="updateAccount">
              <div class="row g-3 account-row">
                <div class="col-md-6">
                  <div class="card h-100 account-card">
                    <div class="card-body">
                      <h6 class="mb-3">Contact Information</h6>
                      <div class="mb-3 d-flex gap-3 align-items-center">
                        <div class="avatar-placeholder text-center border rounded p-3 text-muted small">
                          <i class="bi bi-person fs-2 d-block"></i>
                          Upload available after creating the user.
                        </div>
                      </div>
                      <div class="row g-2 mb-2">
                        <div class="col-md-5">
                          <label class="form-label">First Name *</label>
                          <input v-model="form.first_name" type="text" class="form-control" required />
                        </div>
                        <div class="col-md-2">
                          <label class="form-label">M.I.</label>
                          <input v-model="form.middle_initial" type="text" class="form-control" maxlength="1" />
                        </div>
                        <div class="col-md-5">
                          <label class="form-label">Last Name *</label>
                          <input v-model="form.last_name" type="text" class="form-control" required />
                        </div>
                      </div>

                      <div class="row g-3">
                        <div class="col-md-12">
                          <label class="form-label">Username</label>
                          <input v-model="form.username" type="text" class="form-control" />
                        </div>
                        <div class="col-md-12">
                          <label class="form-label">Email *</label>
                          <input v-model="form.email" type="email" class="form-control" required />
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Primary Phone Number</label>
                          <input v-model="form.primary_phone" type="text" class="form-control" placeholder="(xxx) xxx-xxxx" />
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Secondary Phone Number</label>
                          <input v-model="form.secondary_phone" type="text" class="form-control" placeholder="(xxx) xxx-xxxx" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="card h-100 account-card">
                    <div class="card-body">
                      <h6 class="mb-3">Working Information</h6>
                      <div class="row g-3">
                        <div class="col-md-12">
                          <label class="form-label">Department</label>
                          <input v-model="form.department" type="text" class="form-control" />
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Role</label>
                          <input v-model="form.role" type="text" class="form-control" disabled />
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Inactivity Timeout *</label>
                          <select v-model="form.inactivity_timeout" class="form-select">
                            <option value="">Select time...</option>
                            <option value="5">5 minutes</option>
                            <option value="10">10 minutes</option>
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                          </select>
                          <small class="text-muted">HIPAA recommends a 10 minute timeout.</small>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label d-block">Is Provider</label>
                          <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" v-model="form.is_provider">
                            <label class="form-check-label">No / Yes</label>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label d-block">Time clock user</label>
                          <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" v-model="form.is_time_clock_user">
                            <label class="form-check-label">No / Yes</label>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="text-end mt-3">
                <button class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-outline-secondary ms-2" @click="loadUser">Cancel</button>
              </div>
            </form>
        </div>
      </div>

      <!-- SECURITY TAB -->
      <div class="tab-pane fade" id="security">
        <h5 class="mb-3">Two-Factor Authentication</h5>

        <div v-if="mfa.enabled" class="alert alert-success">
          MFA Enabled ({{ mfa.type }})
        </div>
        <div v-else class="alert alert-warning">
          MFA is currently disabled.
        </div>

        <div class="mt-3 d-flex gap-2">
          <button
            class="btn btn-outline-primary"
            @click="enableEmailMFA"
            v-if="!mfa.enabled"
          >
            Enable Email OTP
          </button>

          <button
            class="btn btn-outline-danger"
            @click="disableMFA"
            v-if="mfa.enabled"
          >
            Disable MFA
          </button>
        </div>

        <div v-if="showOtpForm" class="mt-4">
          <h6>Enter the code sent to your email</h6>
          <form @submit.prevent="verifyOtp" class="row g-2">
            <div class="col-auto">
              <input v-model="otpCode" type="text" class="form-control" maxlength="6" placeholder="123456" />
            </div>
            <div class="col-auto">
              <button class="btn btn-success">Verify</button>
            </div>
          </form>
        </div>
      </div>

      <!-- PREFERENCES TAB -->
      <div class="tab-pane fade" id="preferences">
        <h5 class="mb-3">User Preferences</h5>

        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" v-model="prefs.darkMode">
          <label class="form-check-label">Enable Dark Mode</label>
        </div>

        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" v-model="prefs.notifications">
          <label class="form-check-label">Enable Notifications</label>
        </div>

        <div class="mt-3 text-end">
          <button class="btn btn-primary" @click="savePrefs">Save Preferences</button>
        </div>
      </div>

      <!-- SYSTEM SETTINGS TAB -->
      <div class="tab-pane fade" id="system" v-if="isSuperAdmin">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">System Settings</h5>
            <small class="text-muted">Update your CRM name, logo, tagline, and support details.</small>
          </div>
          <button class="btn btn-primary btn-sm" @click="saveSystemSettings" :disabled="system.saving">
            <span v-if="system.saving" class="spinner-border spinner-border-sm me-1"></span>
            Save
          </button>
        </div>

        <div class="row g-3">
          <div class="col-lg-7">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-8">
                    <label class="form-label">Application Name</label>
                    <input v-model="system.form.app_name" type="text" class="form-control" placeholder="NexusCRM" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Short Name</label>
                    <input v-model="system.form.app_short_name" type="text" class="form-control" placeholder="NC" maxlength="8" />
                  </div>
                  <div class="col-12">
                    <label class="form-label">Tagline</label>
                    <input v-model="system.form.app_tagline" type="text" class="form-control" placeholder="Mini CRM Console" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Company Name</label>
                    <input v-model="system.form.company_name" type="text" class="form-control" placeholder="Iconis" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Support Email</label>
                    <input v-model="system.form.support_email" type="email" class="form-control" placeholder="support@example.com" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Support Phone</label>
                    <input v-model="system.form.support_phone" type="text" class="form-control" placeholder="+1 555 000 0000" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Logo</label>
                    <input type="file" class="form-control" accept="image/*" @change="onSystemLogoChange" />
                    <small class="text-muted">PNG, JPG, WEBP, or SVG image up to 2 MB.</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h6 class="mb-0">Brand Preview</h6>
                  <button
                    v-if="systemLogoPreview"
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    @click="removeSystemLogo"
                  >
                    Remove Logo
                  </button>
                </div>

                <div class="border rounded p-3 bg-light">
                  <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="brand-preview-mark">
                      <img
                        v-if="systemLogoPreview"
                        :src="systemLogoPreview"
                        alt="Application logo preview"
                        class="brand-preview-logo"
                      />
                      <span v-else>{{ systemBrandInitials }}</span>
                    </div>
                    <div>
                      <div class="fw-bold fs-5">{{ system.form.app_name || 'NexusCRM' }}</div>
                      <div class="text-muted small">{{ system.form.app_tagline || 'Mini CRM Console' }}</div>
                    </div>
                  </div>
                  <div class="small text-muted">
                    <div>{{ system.form.company_name || 'Company name not set' }}</div>
                    <div>{{ system.form.support_email || 'Support email not set' }}</div>
                    <div>{{ system.form.support_phone || 'Support phone not set' }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- META CONFIG TAB -->
      <div class="tab-pane fade" id="twilio" v-if="isSuperAdmin">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">Meta WhatsApp Configuration</h5>
            <small class="text-muted">Store the Cloud API credentials in the database and use the webhook values below in Meta.</small>
          </div>
          <button class="btn btn-primary btn-sm" @click="saveMeta" :disabled="meta.saving">
            <span v-if="meta.saving" class="spinner-border spinner-border-sm me-1"></span>
            Save
          </button>
        </div>

        <div class="card shadow-sm">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">App ID</label>
                <input v-model="meta.form.meta_app_id" type="text" class="form-control" placeholder="347591848299284" />
              </div>
              <div class="col-md-6">
                <label class="form-label">App Secret</label>
                <input v-model="meta.form.meta_app_secret" type="password" class="form-control" placeholder="••••••" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Access Token</label>
                <input v-model="meta.form.meta_access_token" type="password" class="form-control" placeholder="EA..." />
              </div>
              <div class="col-md-6">
                <label class="form-label">WhatsApp Business Account ID</label>
                <input v-model="meta.form.meta_whatsapp_business_account_id" type="text" class="form-control" placeholder="158344407357891" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number ID</label>
                <input v-model="meta.form.meta_whatsapp_phone_number_id" type="text" class="form-control" placeholder="108317352375882" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Display Phone Number</label>
                <input v-model="meta.form.meta_whatsapp_display_phone_number" type="text" class="form-control" placeholder="+1 555 003 2209" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Webhook Verify Token</label>
                <input v-model="meta.form.meta_webhook_verify_token" type="text" class="form-control" placeholder="Generated verify token" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Provider</label>
                <input v-model="meta.form.whatsapp_provider" type="text" class="form-control" readonly />
              </div>
              <div class="col-12">
                <label class="form-label">Webhook Callback URL</label>
                <input :value="webhookCallbackUrl" type="text" class="form-control" readonly />
                <small class="text-muted">Use this exact callback URL when configuring the Meta webhook.</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- WHATSAPP TEMPLATES TAB -->
      <div class="tab-pane fade" id="whatsapp-templates" v-if="isSuperAdmin">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">WhatsApp Templates</h5>
            <small class="text-muted">View the templates synced from Meta. Creation and approval stay in WhatsApp Manager for now.</small>
          </div>
          <button class="btn btn-primary btn-sm" @click="startCreate" disabled title="Create templates in Meta WhatsApp Manager">
            <i class="bi bi-plus-circle me-1"></i>
            Managed In Meta
          </button>
        </div>

        <div class="card shadow-sm mb-3">
          <div class="card-body p-0">
            <div v-if="wa.loading" class="p-3 text-center text-muted">
              <span class="spinner-border spinner-border-sm me-2"></span>
              Loading templates...
            </div>
            <table v-else class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th>Language</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th>Preview</th>
                  <th style="width: 180px;" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in wa.templates" :key="t.sid">
                  <td class="fw-semibold">{{ t.name }}</td>
                  <td>{{ t.language || '-' }}</td>
                  <td>{{ t.category || '-' }}</td>
                  <td>
                    <span class="badge" :class="statusBadge(t.status)">
                      {{ t.status || 'Unknown' }}
                    </span>
                  </td>
                  <td>
                    <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;">
                      {{ t.body_preview || 'No preview' }}
                    </small>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                      <button class="btn btn-outline-primary" title="Edit in Meta WhatsApp Manager" disabled>
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button class="btn btn-outline-success" title="Submit in Meta WhatsApp Manager" disabled>
                        <i class="bi bi-upload"></i>
                      </button>
                      <button class="btn btn-outline-danger" title="Delete in Meta WhatsApp Manager" disabled>
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="wa.templates.length === 0">
                  <td colspan="6" class="text-center text-muted py-3">
                    No templates found.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- WhatsApp Template Modal -->
    <div class="modal fade" tabindex="-1" ref="templateModalRef">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ wa.form.sid ? 'Edit WhatsApp Template' : 'Create WhatsApp Template' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Friendly Name</label>
                <input v-model="wa.form.friendly_name" type="text" class="form-control" placeholder="Appointment Reminder" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Language</label>
                <input v-model="wa.form.language" type="text" class="form-control" placeholder="en" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Category</label>
                <select v-model="wa.form.category" class="form-select">
                  <option value="utility">Utility</option>
                  <option value="marketing">Marketing</option>
                  <option value="authentication">Authentication</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Body</label>
                <textarea v-model="wa.form.body" class="form-control" rows="4" placeholder="Hi {{1}}, your order {{2}} is ready for pickup."></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Media URLs (optional, comma separated)</label>
                <input v-model="wa.form.media_urls" type="text" class="form-control" placeholder="https://example.com/image.jpg" />
              </div>
              <div class="col-12" v-if="firstMediaUrl">
                <label class="form-label">Preview</label>
                <div class="border rounded p-2 text-center bg-light">
                  <img :src="firstMediaUrl" alt="WhatsApp media preview" class="img-fluid" style="max-height: 220px; object-fit: contain;" />
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="wa.saving">Close</button>
            <button class="btn btn-primary" @click="saveTemplate" :disabled="wa.saving">
              <span v-if="wa.saving" class="spinner-border spinner-border-sm me-1"></span>
              {{ wa.form.sid ? 'Update Template' : 'Create Template' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../axios';
import { Modal } from 'bootstrap';

export default {
  name: 'SettingsView',
  data() {
    return {
      form: {
        name: '',
        email: '',
        department: '',
        role: '',
        first_name: '',
        middle_initial: '',
        last_name: '',
        username: '',
        primary_phone: '',
        secondary_phone: '',
        inactivity_timeout: '10',
        is_provider: false,
        is_time_clock_user: false,
        active: true,
      },
      mfa: {
        enabled: false,
        type: null,
      },
      showOtpForm: false,
      otpCode: '',
      prefs: {
        darkMode: false,
        notifications: true,
      },
      system: {
        saving: false,
        logoFile: null,
        logoPreviewUrl: null,
        removeLogo: false,
        form: {
          app_name: 'NexusCRM',
          app_short_name: 'NC',
          app_tagline: 'Mini CRM Console',
          company_name: '',
          support_email: '',
          support_phone: '',
          app_logo_path: '',
          app_logo_url: '',
        },
      },
      meta: {
        saving: false,
        form: {
          whatsapp_provider: 'meta',
          meta_app_id: '',
          meta_app_secret: '',
          meta_access_token: '',
          meta_whatsapp_business_account_id: '',
          meta_whatsapp_phone_number_id: '',
          meta_whatsapp_display_phone_number: '',
          meta_webhook_verify_token: '',
        },
      },
      wa: {
        templates: [],
        loading: false,
        saving: false,
        form: {
          sid: null,
          friendly_name: '',
          body: '',
          language: 'en',
          category: 'utility',
          media_urls: '',
        },
      },
      templateModal: null,
    };
  },
    mounted() {
      this.loadUser();
      this.loadMFA();
      this.templateModal = new Modal(this.$refs.templateModalRef);
      if (this.isSuperAdmin) {
        this.loadAdminSettings();
        this.loadWhatsappTemplates();
      }
    },
  computed: {
    isSuperAdmin() {
      const stored = localStorage.getItem('nexus_user');
      if (!stored) return false;
      try {
        return JSON.parse(stored)?.role === 'SUPER_ADMIN';
      } catch {
        return false;
      }
    },
    firstMediaUrl() {
      const urls = (this.wa.form.media_urls || '')
        .split(',')
        .map((u) => u.trim())
        .filter(Boolean);
      return urls.length ? urls[0] : null;
    },
    systemLogoPreview() {
      if (this.system.removeLogo) return null;
      return this.system.logoPreviewUrl || this.system.form.app_logo_url || null;
    },
    systemBrandInitials() {
      const raw = (this.system.form.app_short_name || this.system.form.app_name || 'NC').trim();
      if (!raw) return 'NC';
      const parts = raw.split(/\s+/).filter(Boolean);
      if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
      }
      return parts.slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
    },
    webhookCallbackUrl() {
      return `${window.location.origin}/api/whatsapp/webhook`;
    },
  },
  methods: {
    // Load profile
    loadUser() {
      axios.get('/api/user').then((res) => {
        const fallback = { ...this.form };
        this.form = Object.assign(fallback, res.data || {});
      });
    },
    updateAccount() {
      axios.put('/api/user', this.form).then(() => {
        alert('Account updated successfully');
      });
    },

    // Load MFA state
    loadMFA() {
      axios.get('/api/mfa/status').then((res) => {
        this.mfa.enabled = res.data.mfa_enabled;
        this.mfa.type = res.data.mfa_type;
      });
    },

    enableEmailMFA() {
      axios.post('/api/mfa/setup-email').then(() => {
        this.showOtpForm = true;
        alert('OTP sent to your email');
      });
    },
    verifyOtp() {
      axios.post('/api/mfa/verify-email', { code: this.otpCode }).then(() => {
        alert('MFA enabled successfully');
        this.showOtpForm = false;
        this.loadMFA();
      });
    },

    disableMFA() {
      if (!confirm('Disable MFA?')) return;

      axios.post('/api/mfa/disable').then(() => {
        alert('MFA disabled');
        this.loadMFA();
      });
    },

    savePrefs() {
      alert('Preferences saved (setup backend later)');
    },

    applyBranding(settings) {
      const branding = {
        app_name: settings.app_name || 'NexusCRM',
        app_short_name: settings.app_short_name || 'NC',
        app_tagline: settings.app_tagline || 'Mini CRM Console',
        company_name: settings.company_name || '',
        support_email: settings.support_email || '',
        support_phone: settings.support_phone || '',
        app_logo_url: settings.app_logo_url || '',
      };

      localStorage.setItem('nexus_branding', JSON.stringify(branding));
      window.dispatchEvent(new CustomEvent('branding-updated', { detail: branding }));
      document.title = branding.app_name;
    },
    applyAdminSettings(settings) {
      this.system.form = {
        app_name: settings.app_name || 'NexusCRM',
        app_short_name: settings.app_short_name || 'NC',
        app_tagline: settings.app_tagline || 'Mini CRM Console',
        company_name: settings.company_name || '',
        support_email: settings.support_email || '',
        support_phone: settings.support_phone || '',
        app_logo_path: settings.app_logo_path || '',
        app_logo_url: settings.app_logo_url || '',
      };
      this.system.logoFile = null;
      this.system.logoPreviewUrl = null;
      this.system.removeLogo = false;

      this.meta.form = {
        whatsapp_provider: settings.whatsapp_provider || 'meta',
        meta_app_id: settings.meta_app_id || '',
        meta_app_secret: settings.meta_app_secret || '',
        meta_access_token: settings.meta_access_token || '',
        meta_whatsapp_business_account_id: settings.meta_whatsapp_business_account_id || '',
        meta_whatsapp_phone_number_id: settings.meta_whatsapp_phone_number_id || '',
        meta_whatsapp_display_phone_number: settings.meta_whatsapp_display_phone_number || '',
        meta_webhook_verify_token: settings.meta_webhook_verify_token || '',
      };

      this.applyBranding(settings);
    },
    loadAdminSettings() {
      axios
        .get('/api/settings')
        .then((res) => {
          if (res.data) {
            this.applyAdminSettings(res.data);
          }
        })
        .catch(() => {
          // Ignore load errors until settings are created.
        });
    },
    onSystemLogoChange(event) {
      const file = event.target.files?.[0] || null;
      this.system.logoFile = file;
      this.system.removeLogo = false;
      this.system.logoPreviewUrl = file ? URL.createObjectURL(file) : null;
    },
    removeSystemLogo() {
      this.system.logoFile = null;
      this.system.logoPreviewUrl = null;
      this.system.removeLogo = true;
    },
    saveSystemSettings() {
      this.system.saving = true;
      const payload = new FormData();
      payload.append('app_name', this.system.form.app_name || '');
      payload.append('app_short_name', this.system.form.app_short_name || '');
      payload.append('app_tagline', this.system.form.app_tagline || '');
      payload.append('company_name', this.system.form.company_name || '');
      payload.append('support_email', this.system.form.support_email || '');
      payload.append('support_phone', this.system.form.support_phone || '');
      payload.append('remove_app_logo', this.system.removeLogo ? '1' : '0');
      if (this.system.logoFile) {
        payload.append('app_logo', this.system.logoFile);
      }

      axios
        .post('/api/settings', payload)
        .then((res) => {
          this.applyAdminSettings(res.data || {});
          alert('System settings saved.');
        })
        .catch((err) => {
          alert('Failed to save system settings: ' + (err.response?.data?.message || err.message));
        })
        .finally(() => {
          this.system.saving = false;
        });
    },

    // WhatsApp templates
    loadWhatsappTemplates() {
      this.wa.loading = true;
      axios
        .get('/api/whatsapp-templates', { params: { approved: false } })
        .then((res) => {
          this.wa.templates = res.data || [];
        })
        .catch(() => {
          this.wa.templates = [];
        })
        .finally(() => {
          this.wa.loading = false;
        });
    },
    startCreate() {
      this.resetForm();
      if (this.templateModal) {
        this.templateModal.show();
      }
    },
    editTemplate(t) {
      this.wa.form = {
        sid: t.sid,
        friendly_name: t.name,
        body: t.body_preview || '',
        language: t.language || 'en',
        category: (t.category || 'utility').toLowerCase(),
        media_urls: (t.media_urls || []).join(','),
      };
      if (this.templateModal) {
        this.templateModal.show();
      }
    },
    resetForm() {
      this.wa.form = {
        sid: null,
        friendly_name: '',
        body: '',
        language: 'en',
        category: 'utility',
        media_urls: '',
      };
    },
    saveTemplate() {
      if (!this.wa.form.friendly_name || !this.wa.form.body) {
        alert('Please provide a friendly name and body.');
        return;
      }

      const payload = {
        friendly_name: this.wa.form.friendly_name,
        body: this.wa.form.body,
        language: this.wa.form.language,
        category: this.wa.form.category,
        media_urls: this.wa.form.media_urls
          ? this.wa.form.media_urls.split(',').map((m) => m.trim()).filter(Boolean)
          : [],
      };

      this.wa.saving = true;
      const request = this.wa.form.sid
        ? axios.put(`/api/whatsapp-templates/${this.wa.form.sid}`, payload)
        : axios.post('/api/whatsapp-templates', payload);

      request
        .then(() => {
          alert(this.wa.form.sid ? 'Template updated' : 'Template created');
          this.resetForm();
          this.loadWhatsappTemplates();
          this.templateModal?.hide();
        })
        .catch((err) => {
          alert('Failed to save template: ' + (err.response?.data?.message || err.message));
        })
        .finally(() => {
          this.wa.saving = false;
        });
    },
    submitTemplate(t) {
      if (!confirm(`Submit "${t.name}" for WhatsApp approval?`)) return;

      axios
        .post(`/api/whatsapp-templates/${t.sid}/submit`, { category: t.category || 'utility' })
        .then(() => {
          alert('Template submitted for approval.');
          this.loadWhatsappTemplates();
        })
        .catch((err) => {
          alert('Failed to submit template: ' + (err.response?.data?.message || err.message));
        });
    },
    deleteTemplate(t) {
      if (!confirm(`Delete template "${t.name}"?`)) return;

      axios
        .delete(`/api/whatsapp-templates/${t.sid}`)
        .then(() => {
          this.loadWhatsappTemplates();
        })
        .catch((err) => {
          alert('Failed to delete template: ' + (err.response?.data?.message || err.message));
        });
    },
    statusBadge(status) {
      const s = (status || '').toLowerCase();
      if (s === 'approved') return 'badge bg-success';
      if (s === 'pending' || s === 'in_review') return 'badge bg-warning text-dark';
      if (s === 'rejected') return 'badge bg-danger';
      return 'badge bg-secondary';
    },
    saveMeta() {
      if (!this.meta.form.meta_access_token || !this.meta.form.meta_whatsapp_phone_number_id || !this.meta.form.meta_whatsapp_business_account_id) {
        alert('Access token, business account ID, and phone number ID are required.');
        return;
      }
      this.meta.saving = true;
      axios
        .post('/api/settings', this.meta.form)
        .then((res) => {
          this.applyAdminSettings(res.data || {});
          alert('Meta WhatsApp settings saved.');
        })
        .catch((err) => {
          alert('Failed to save Meta WhatsApp settings: ' + (err.response?.data?.message || err.message));
        })
        .finally(() => {
          this.meta.saving = false;
        });
    },
  },
};
</script>

<style scoped>
.account-layout {
  width: 100%;
  max-width: 100%;
  margin: 0;
  padding: 0;
}
.account-card {
  min-width: 0;
}
.sidebar {
  width: 230px;
  transition: width 0.2s ease;
}
.avatar-placeholder {
  width: 150px;
  height: 150px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
}
.brand-preview-mark {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  background: #e9f2ff;
  color: #0d3b8f;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.2rem;
  overflow: hidden;
}
.brand-preview-logo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
