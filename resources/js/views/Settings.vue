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
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="twilio-tab" data-bs-toggle="tab" data-bs-target="#twilio" type="button">
          Twilio Config
        </button>
      </li>
      <li class="nav-item" role="presentation">
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

      <!-- TWILIO CONFIG TAB -->
      <div class="tab-pane fade" id="twilio">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">Twilio Configuration</h5>
            <small class="text-muted">Stored securely in the database instead of .env.</small>
          </div>
          <button class="btn btn-primary btn-sm" @click="saveTwilio" :disabled="twilio.saving">
            <span v-if="twilio.saving" class="spinner-border spinner-border-sm me-1"></span>
            Save
          </button>
        </div>

        <div class="card shadow-sm">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Account SID</label>
                <input v-model="twilio.form.twilio_sid" type="text" class="form-control" placeholder="ACxxxx" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Auth Token</label>
                <input v-model="twilio.form.twilio_auth_token" type="password" class="form-control" placeholder="••••••" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Messaging Service SID (MSID)</label>
                <input v-model="twilio.form.twilio_msg_sid" type="text" class="form-control" placeholder="MGxxxx" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Default Template SID</label>
                <input v-model="twilio.form.twilio_template_sid" type="text" class="form-control" placeholder="HXxxxx" />
              </div>
              <div class="col-md-6">
                <label class="form-label">WhatsApp From</label>
                <input v-model="twilio.form.twilio_whatsapp_from" type="text" class="form-control" placeholder="+1234567890" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Status Callback URL</label>
                <input v-model="twilio.form.twilio_status_callback" type="text" class="form-control" placeholder="https://your-app.example.com/api/twilio/status" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- WHATSAPP TEMPLATES TAB -->
      <div class="tab-pane fade" id="whatsapp-templates">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">WhatsApp Templates</h5>
            <small class="text-muted">Manage Twilio Content templates and approvals.</small>
          </div>
          <button class="btn btn-primary btn-sm" @click="startCreate">
            <i class="bi bi-plus-circle me-1"></i>
            New Template
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
                      <button class="btn btn-outline-primary" title="Edit" @click="editTemplate(t)">
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button class="btn btn-outline-success" title="Submit" @click="submitTemplate(t)">
                        <i class="bi bi-upload"></i>
                      </button>
                      <button class="btn btn-outline-danger" title="Delete" @click="deleteTemplate(t)">
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
      twilio: {
        saving: false,
        form: {
          twilio_sid: '',
          twilio_auth_token: '',
          twilio_msg_sid: '',
          twilio_template_sid: '',
          twilio_whatsapp_from: '',
          twilio_status_callback: '',
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
      this.loadWhatsappTemplates();
      this.templateModal = new Modal(this.$refs.templateModalRef);
      this.loadTwilioSettings();
    },
  computed: {
    firstMediaUrl() {
      const urls = (this.wa.form.media_urls || '')
        .split(',')
        .map((u) => u.trim())
        .filter(Boolean);
      return urls.length ? urls[0] : null;
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
    // Twilio settings
    loadTwilioSettings() {
      axios
        .get('/api/settings')
        .then((res) => {
          if (res.data) {
            this.twilio.form = {
              twilio_sid: res.data.twilio_sid || '',
              twilio_auth_token: res.data.twilio_auth_token || '',
              twilio_msg_sid: res.data.twilio_msg_sid || '',
              twilio_template_sid: res.data.twilio_template_sid || '',
              twilio_whatsapp_from: res.data.twilio_whatsapp_from || '',
              twilio_status_callback: res.data.twilio_status_callback || '',
            };
          }
        })
        .catch(() => {
          // Ignore load errors; likely missing settings until saved once
        });
    },
    saveTwilio() {
      if (!this.twilio.form.twilio_sid || !this.twilio.form.twilio_auth_token) {
        alert('Account SID and Auth Token are required.');
        return;
      }
      this.twilio.saving = true;
      axios
        .post('/api/settings', this.twilio.form)
        .then(() => {
          alert('Twilio settings saved.');
        })
        .catch((err) => {
          alert('Failed to save Twilio settings: ' + (err.response?.data?.message || err.message));
        })
        .finally(() => {
          this.twilio.saving = false;
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
</style>
