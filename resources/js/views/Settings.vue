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
        <button class="nav-link" id="meta-tab" data-bs-toggle="tab" data-bs-target="#meta" type="button">
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
                          <label class="form-label">Departments</label>
                          <vue-multiselect
                            v-model="selectedDepartments"
                            :options="departmentOptions"
                            :multiple="true"
                            :close-on-select="false"
                            :clear-on-select="false"
                            :searchable="true"
                            :allow-empty="true"
                            :disabled="isStaffRole"
                            placeholder="Select one or more departments"
                            label="name"
                            track-by="id"
                            class="mb-2"
                          >
                            <template #noResult>No departments found</template>
                            <template #noOptions>No departments available</template>
                          </vue-multiselect>
                          <small class="text-muted d-block mt-1" v-if="isStaffRole">
                            Users with the STAFF role cannot change their department assignment.
                          </small>
                          <small class="text-muted d-block mt-1" v-else>
                            Hold Ctrl or Cmd to select multiple departments. The first selected department remains the primary department for legacy scoping.
                          </small>
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

        <div class="card mt-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h6 class="mb-1">Recent Sessions</h6>
                <small class="text-muted">Track current and recent device access to this account.</small>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" @click="loadSessions">
                Refresh
              </button>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Last Login</div>
                <div class="fw-semibold">{{ form.last_login_at || '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Last Login IP</div>
                <div class="fw-semibold">{{ form.last_login_ip || '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Password Updated</div>
                <div class="fw-semibold">{{ form.password_changed_at || '-' }}</div>
              </div>
            </div>

            <div v-if="sessions.length" class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Device / Browser</th>
                    <th>IP</th>
                    <th>Auth</th>
                    <th>Authenticated</th>
                    <th>Last Activity</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="session in sessions" :key="session.id">
                    <td class="small">{{ session.user_agent || '-' }}</td>
                    <td>{{ session.ip_address || '-' }}</td>
                    <td>{{ session.authentication_method || '-' }}</td>
                    <td>{{ session.authenticated_at || '-' }}</td>
                    <td>{{ session.last_activity_at || '-' }}</td>
                    <td>
                      <span v-if="session.is_current" class="badge bg-primary">Current</span>
                      <span v-else-if="session.logged_out_at" class="badge bg-secondary">{{ session.logout_reason || 'Closed' }}</span>
                      <span v-else class="badge bg-success">Active</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-muted small">No tracked sessions yet.</div>
          </div>
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
                    <label class="form-label">Password Max Age (days)</label>
                    <input v-model="system.form.password_max_age_days" type="number" min="0" max="3650" class="form-control" placeholder="90" />
                    <small class="text-muted">Set to 0 to disable forced password expiry.</small>
                  </div>
                  <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center border rounded p-3 bg-light">
                      <div>
                        <div class="fw-semibold">Malware scanning on bank imports</div>
                        <div class="small text-muted">When enabled, uploaded debtor CSV files are scanned through the configured ClamAV daemon before import starts.</div>
                      </div>
                      <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" v-model="system.form.enable_import_malware_scanning">
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Scanner Socket Path</label>
                    <input v-model="system.form.malware_scanner_socket_path" type="text" class="form-control" placeholder="/var/run/clamav/clamd.ctl" />
                    <small class="text-muted">Preferred for a local daemon. Leave blank to use TCP host/port.</small>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Scanner Host</label>
                    <input v-model="system.form.malware_scanner_host" type="text" class="form-control" placeholder="127.0.0.1" :disabled="!!system.form.malware_scanner_socket_path" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Scanner Port</label>
                    <input v-model="system.form.malware_scanner_port" type="number" min="1" max="65535" class="form-control" placeholder="3310" :disabled="!!system.form.malware_scanner_socket_path" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Scanner Timeout (seconds)</label>
                    <input v-model="system.form.malware_scanner_timeout_seconds" type="number" min="1" max="120" class="form-control" placeholder="15" />
                  </div>
                  <div class="col-12">
                    <label class="form-label">Admin IP Allowlist</label>
                    <textarea
                      v-model="system.form.admin_ip_allowlist"
                      class="form-control"
                      rows="3"
                      placeholder="One IP or CIDR per line, e.g.&#10;196.12.10.0/24&#10;105.23.14.9"
                    ></textarea>
                    <small class="text-muted">Applies to SUPER_ADMIN and ADMIN access when populated.</small>
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
      <div class="tab-pane fade" id="meta" v-if="isSuperAdmin">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">Meta WhatsApp Configuration</h5>
            <small class="text-muted">Store the Cloud API credentials in the database and use the webhook values below in Meta.</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" @click="validateMetaPermissions" :disabled="meta.validating || meta.saving">
              <span v-if="meta.validating" class="spinner-border spinner-border-sm me-1"></span>
              Validate Permissions
            </button>
            <button class="btn btn-primary btn-sm" @click="saveMeta" :disabled="meta.saving || meta.validating">
              <span v-if="meta.saving" class="spinner-border spinner-border-sm me-1"></span>
              Save
            </button>
          </div>
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
                <input
                  v-model="meta.form.meta_app_secret"
                  type="password"
                  class="form-control"
                  placeholder="••••••"
                  @copy.prevent
                  @cut.prevent
                  @paste.prevent
                  @drop.prevent
                  autocomplete="off"
                />
              </div>
              <div class="col-md-6">
                <label class="form-label">Access Token</label>
                <input
                  v-model="meta.form.meta_access_token"
                  type="password"
                  class="form-control"
                  placeholder="EA..."
                  @copy.prevent
                  @cut.prevent
                  @paste.prevent
                  @drop.prevent
                  autocomplete="off"
                />
              </div>
              <div class="col-md-3">
                <label class="form-label">Meta Environment</label>
                <select v-model="meta.form.meta_environment" class="form-select">
                  <option value="development">Development</option>
                  <option value="staging">Staging</option>
                  <option value="production">Production</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Token Last Rotated</label>
                <input v-model="meta.form.meta_token_last_rotated_at" type="datetime-local" class="form-control" />
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
                <input
                  v-model="meta.form.meta_webhook_verify_token"
                  type="text"
                  class="form-control"
                  placeholder="Generated verify token"
                  @copy.prevent
                  @cut.prevent
                  @paste.prevent
                  @drop.prevent
                  autocomplete="off"
                />
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
              <div class="col-md-6">
                <label class="form-label">Token Expires At</label>
                <input v-model="meta.form.meta_token_expires_at" type="datetime-local" class="form-control" />
              </div>
              <div class="col-12">
                <label class="form-label">Token Rotation Notes</label>
                <textarea v-model="meta.form.meta_token_rotation_notes" class="form-control" rows="2" placeholder="Document who rotated the token, source system, approval ref, or change ticket."></textarea>
              </div>
              <div class="col-12" v-if="metaTokenWarning">
                <div class="alert alert-warning mb-0">
                  {{ metaTokenWarning }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h6 class="mb-1">Permission Governance</h6>
                <small class="text-muted">Validate the configured Meta token against the minimum WhatsApp scopes required by this CRM.</small>
              </div>
              <span v-if="meta.permissions_status" class="badge" :class="permissionStatusBadge(meta.permissions_status)">
                {{ meta.permissions_status }}
              </span>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Last Checked</div>
                <div class="fw-semibold">{{ meta.permissions_last_checked_at || '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Token Valid</div>
                <div class="fw-semibold">{{ meta.permissions_snapshot?.is_valid === false ? 'No' : (meta.permissions_snapshot?.is_valid === true ? 'Yes' : '-') }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">App Match</div>
                <div class="fw-semibold">{{ meta.permissions_snapshot?.app_id_matches === false ? 'No' : (meta.permissions_snapshot?.app_id_matches === true ? 'Yes' : '-') }}</div>
              </div>
            </div>

            <div class="row g-3" v-if="meta.permissions_snapshot">
              <div class="col-md-6">
                <label class="form-label">Granted Scopes</label>
                <div class="d-flex flex-wrap gap-2">
                  <span v-for="scope in meta.permissions_snapshot.granted_scopes || []" :key="scope" class="badge bg-success-subtle text-success-emphasis border">
                    {{ scope }}
                  </span>
                  <span v-if="!(meta.permissions_snapshot.granted_scopes || []).length" class="text-muted small">No granted scopes returned.</span>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Missing Required Scopes</label>
                <div class="d-flex flex-wrap gap-2">
                  <span v-for="scope in meta.permissions_snapshot.missing_required_scopes || []" :key="scope" class="badge bg-danger-subtle text-danger-emphasis border">
                    {{ scope }}
                  </span>
                  <span v-if="!(meta.permissions_snapshot.missing_required_scopes || []).length" class="text-muted small">None.</span>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Missing Recommended Scopes</label>
                <div class="d-flex flex-wrap gap-2">
                  <span v-for="scope in meta.permissions_snapshot.missing_recommended_scopes || []" :key="scope" class="badge bg-warning-subtle text-warning-emphasis border">
                    {{ scope }}
                  </span>
                  <span v-if="!(meta.permissions_snapshot.missing_recommended_scopes || []).length" class="text-muted small">None.</span>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Token Expiry From Meta</label>
                <div class="fw-semibold">{{ meta.permissions_snapshot.expires_at || '-' }}</div>
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
            <small class="text-muted">View, search, and create WhatsApp templates synced with Meta.</small>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" @click="loadWhatsappTemplates" :disabled="wa.loading">
              <span v-if="wa.loading" class="spinner-border spinner-border-sm me-1"></span>
              Refresh
            </button>
            <button type="button" class="btn btn-primary btn-sm" @click="startCreate">
              <i class="bi bi-plus-circle me-1"></i>
              Create Template
            </button>
          </div>
        </div>

        <div class="card shadow-sm mb-3">
          <div class="card-body border-bottom">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Search</label>
                <input v-model.trim="wa.filters.search" type="text" class="form-control form-control-sm" placeholder="Search name, preview, language..." />
              </div>
              <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select v-model="wa.filters.status" class="form-select form-select-sm">
                  <option value="">All statuses</option>
                  <option v-for="status in wa.availableStatuses" :key="status" :value="status">
                    {{ status }}
                  </option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Category</label>
                <select v-model="wa.filters.category" class="form-select form-select-sm">
                  <option value="">All categories</option>
                  <option v-for="category in wa.availableCategories" :key="category" :value="category">
                    {{ category }}
                  </option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Language</label>
                <select v-model="wa.filters.language" class="form-select form-select-sm">
                  <option value="">All languages</option>
                  <option v-for="language in wa.availableLanguages" :key="language" :value="language">
                    {{ language }}
                  </option>
                </select>
              </div>
              <div class="col-12 d-flex justify-content-between align-items-center pt-1">
                <small class="text-muted">{{ filteredWhatsappTemplates.length }} of {{ wa.templates.length }} templates</small>
                <button
                  v-if="wa.filters.search || wa.filters.status || wa.filters.category || wa.filters.language"
                  type="button"
                  class="btn btn-link btn-sm p-0"
                  @click="resetWhatsappTemplateFilters"
                >
                  Clear filters
                </button>
              </div>
            </div>
          </div>
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
                  <th style="width: 120px;" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in filteredWhatsappTemplates" :key="t.sid">
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
                      <button
                        type="button"
                        class="btn btn-outline-primary"
                        title="View template details"
                        @click="viewTemplate(t)"
                        :disabled="wa.viewingSid === t.sid"
                      >
                        <span v-if="wa.viewingSid === t.sid" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="bi bi-eye"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="filteredWhatsappTemplates.length === 0">
                  <td colspan="6" class="text-center text-muted py-3">
                    No templates match the current filters.
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
            <h5 class="modal-title">
              {{ wa.viewOnly ? 'WhatsApp Template Details' : (wa.form.sid ? 'Edit WhatsApp Template' : 'Create WhatsApp Template') }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Friendly Name</label>
                <input v-model="wa.form.friendly_name" type="text" class="form-control" placeholder="appointment_reminder" :readonly="wa.viewOnly" />
                <small v-if="!wa.viewOnly" class="text-muted">Use lowercase letters, numbers, and underscores for best Meta compatibility.</small>
              </div>
              <div class="col-md-3">
                <label class="form-label">Language</label>
                <input v-model="wa.form.language" type="text" class="form-control" placeholder="en_US" :readonly="wa.viewOnly" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Category</label>
                <select v-model="wa.form.category" class="form-select" :disabled="wa.viewOnly">
                  <option value="utility">Utility</option>
                  <option value="marketing">Marketing</option>
                  <option value="authentication">Authentication</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Body</label>
                <textarea v-model="wa.form.body" class="form-control" rows="6" placeholder="Hi {{1}}, your order {{2}} is ready for pickup." :readonly="wa.viewOnly"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Media URLs</label>
                <input v-model="wa.form.media_urls" type="text" class="form-control" placeholder="https://example.com/image.jpg" :readonly="wa.viewOnly" />
              </div>
              <div class="col-md-4" v-if="wa.form.header_format">
                <label class="form-label">Header Format</label>
                <input :value="wa.form.header_format" type="text" class="form-control" readonly />
              </div>
              <div class="col-12" v-if="wa.form.header_text">
                <label class="form-label">Header Text</label>
                <input :value="wa.form.header_text" type="text" class="form-control" readonly />
              </div>
              <div class="col-12" v-if="wa.form.footer_text">
                <label class="form-label">Footer Text</label>
                <input :value="wa.form.footer_text" type="text" class="form-control" readonly />
              </div>
              <div class="col-12" v-if="firstMediaUrl">
                <label class="form-label">Header Preview</label>
                <div class="border rounded p-2 text-center bg-light">
                  <img
                    v-if="wa.form.header_format === 'IMAGE' || !wa.form.header_format"
                    :src="firstMediaUrl"
                    alt="WhatsApp media preview"
                    class="img-fluid"
                    style="max-height: 220px; object-fit: contain;"
                  />
                  <video
                    v-else-if="wa.form.header_format === 'VIDEO'"
                    :src="firstMediaUrl"
                    class="img-fluid"
                    style="max-height: 220px; object-fit: contain;"
                    controls
                    preload="metadata"
                  ></video>
                  <a
                    v-else-if="wa.form.header_format === 'DOCUMENT'"
                    :href="firstMediaUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-outline-secondary"
                  >
                    Open document
                  </a>
                </div>
              </div>
              <div class="col-12" v-if="wa.form.buttons.length">
                <label class="form-label">Buttons</label>
                <div class="d-flex gap-2 flex-wrap">
                  <span v-for="(button, idx) in wa.form.buttons" :key="idx" class="badge bg-light text-dark border">
                    {{ button.text || button.type || 'Button' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="wa.saving">Close</button>
            <button v-if="!wa.viewOnly" class="btn btn-primary" @click="saveTemplate" :disabled="wa.saving">
              <span v-if="wa.saving" class="spinner-border spinner-border-sm me-1"></span>
              {{ wa.form.sid ? 'Update Template' : 'Create & Submit To Meta' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <ConfirmationModal ref="confirmModal" />
  </div>
</template>

<script>
import axios from '../axios';
import VueMultiselect from 'vue-multiselect';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: 'SettingsView',
  components: {
    VueMultiselect,
    ConfirmationModal,
  },
  data() {
    return {
      form: {
        name: '',
        email: '',
        department: '',
        department_ids: [],
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
        last_login_at: null,
        last_login_ip: null,
        password_changed_at: null,
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
      sessions: [],
      departmentOptions: [],
      selectedDepartments: [],
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
          admin_ip_allowlist: '',
          password_max_age_days: 90,
          enable_import_malware_scanning: false,
          malware_scanner_socket_path: '',
          malware_scanner_host: '127.0.0.1',
          malware_scanner_port: 3310,
          malware_scanner_timeout_seconds: 15,
          app_logo_path: '',
          app_logo_url: '',
        },
      },
      meta: {
        saving: false,
        validating: false,
        form: {
          whatsapp_provider: 'meta',
          meta_app_id: '',
          meta_app_secret: '',
          meta_access_token: '',
          meta_environment: 'production',
          meta_token_last_rotated_at: '',
          meta_token_expires_at: '',
          meta_token_rotation_notes: '',
          meta_whatsapp_business_account_id: '',
          meta_whatsapp_phone_number_id: '',
          meta_whatsapp_display_phone_number: '',
          meta_webhook_verify_token: '',
        },
        permissions_last_checked_at: null,
        permissions_status: null,
        permissions_snapshot: null,
      },
      wa: {
        templates: [],
        loading: false,
        saving: false,
        viewOnly: false,
        viewingSid: null,
        filters: {
          search: '',
          status: '',
          category: '',
          language: '',
        },
        availableStatuses: [],
        availableCategories: [],
        availableLanguages: [],
        form: {
          sid: null,
          friendly_name: '',
          body: '',
          language: 'en_US',
          category: 'utility',
          media_urls: '',
          header_format: '',
          header_text: '',
          footer_text: '',
          buttons: [],
        },
      },
      templateModal: null,
    };
  },
  mounted() {
      this.loadUser();
      this.loadMFA();
      this.loadSessions();
      this.templateModal = createManagedModal(this.$refs.templateModalRef);
      if (this.isSuperAdmin) {
        this.loadAdminSettings();
        this.loadWhatsappTemplates();
      }
      this.loadDepartmentOptions();
    },
  beforeUnmount() {
    disposeManagedModal(this.templateModal);
  },
  watch: {
    selectedDepartments: {
      handler(newValue) {
        this.form.department_ids = Array.isArray(newValue)
          ? newValue.map((department) => department.id)
          : [];
      },
      deep: true,
    },
  },
  computed: {
    isSuperAdmin() {
      const stored = localStorage.getItem('nexus_user');
      if (!stored) return false;
      try {
        return ['SUPER_ADMIN', 'ADMIN'].includes(JSON.parse(stored)?.role);
      } catch {
        return false;
      }
    },
    isStaffRole() {
      return this.form.role === 'STAFF';
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
    metaTokenWarning() {
      const expiresAt = this.meta.form.meta_token_expires_at;
      if (!expiresAt) {
        return this.meta.form.meta_environment === 'production'
          ? 'Production Meta credentials should include a tracked token expiry date and rotation record.'
          : '';
      }

      const expiryDate = new Date(expiresAt);
      if (Number.isNaN(expiryDate.getTime())) {
        return '';
      }

      const diffMs = expiryDate.getTime() - Date.now();
      const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

      if (diffDays < 0) {
        return 'The configured Meta access token is past its tracked expiry date and should be rotated immediately.';
      }

      if (diffDays <= 14) {
        return `The configured Meta access token expires in ${diffDays} day(s). Plan token rotation before bulk messaging is affected.`;
      }

      return '';
    },
    filteredWhatsappTemplates() {
      const search = (this.wa.filters.search || '').trim().toLowerCase();
      return this.wa.templates.filter((template) => {
        const matchesSearch = !search || [
          template.name,
          template.body_preview,
          template.language,
          template.category,
          template.status,
        ]
          .filter(Boolean)
          .some((value) => String(value).toLowerCase().includes(search));

        const matchesStatus = !this.wa.filters.status || (template.status || '').toLowerCase() === this.wa.filters.status.toLowerCase();
        const matchesCategory = !this.wa.filters.category || (template.category || '').toLowerCase() === this.wa.filters.category.toLowerCase();
        const matchesLanguage = !this.wa.filters.language || (template.language || '').toLowerCase() === this.wa.filters.language.toLowerCase();

        return matchesSearch && matchesStatus && matchesCategory && matchesLanguage;
      });
    },
  },
  methods: {
    // Load profile
    loadUser() {
      axios.get('/api/user').then((res) => {
        const fallback = { ...this.form };
        const user = res.data || {};
        this.form = Object.assign(fallback, user, {
          department_ids: Array.isArray(user.departments) ? user.departments.map((department) => department.id) : [],
          active: user.status ? user.status === 'Active' : fallback.active,
        });
        this.syncSelectedDepartments();
      });
    },
    loadDepartmentOptions() {
      axios.get('/api/user/department-options').then((res) => {
        this.departmentOptions = res.data || [];
        this.syncSelectedDepartments();
      }).catch(() => {
        this.departmentOptions = [];
        this.selectedDepartments = [];
      });
    },
    syncSelectedDepartments() {
      if (!Array.isArray(this.departmentOptions) || !this.departmentOptions.length) {
        return;
      }

      const selectedIds = Array.isArray(this.form.department_ids) ? this.form.department_ids.map(Number) : [];
      this.selectedDepartments = this.departmentOptions.filter((department) => selectedIds.includes(Number(department.id)));
    },
    loadSessions() {
      axios.get('/api/user/sessions').then((res) => {
        this.sessions = res.data || [];
      }).catch(() => {
        this.sessions = [];
      });
    },
    updateAccount() {
      const payload = {
        name: this.form.name,
        email: this.form.email,
        username: this.form.username,
        first_name: this.form.first_name,
        middle_initial: this.form.middle_initial,
        last_name: this.form.last_name,
        primary_phone: this.form.primary_phone,
        secondary_phone: this.form.secondary_phone,
        inactivity_timeout: this.form.inactivity_timeout,
        is_provider: this.form.is_provider,
        is_time_clock_user: this.form.is_time_clock_user,
        department_ids: this.form.department_ids,
      };

      axios.put('/api/user', payload).then((res) => {
        const updatedUser = res.data || {};
        const stored = localStorage.getItem('nexus_user');
        if (stored) {
          try {
            const parsed = JSON.parse(stored);
            parsed.name = updatedUser.name ?? parsed.name;
            parsed.email = updatedUser.email ?? parsed.email;
            localStorage.setItem('nexus_user', JSON.stringify(parsed));
          } catch {
            // Ignore local storage sync issues
          }
        }
        this.form = Object.assign({}, this.form, updatedUser, {
          department_ids: Array.isArray(updatedUser.departments) ? updatedUser.departments.map((department) => department.id) : this.form.department_ids,
          active: updatedUser.status ? updatedUser.status === 'Active' : this.form.active,
        });
        this.syncSelectedDepartments();
        notify.success('Account updated successfully.', 'Settings');
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
        notify.success('OTP sent to your email.', 'Settings');
      });
    },
    verifyOtp() {
      axios.post('/api/mfa/verify-email', { code: this.otpCode }).then(() => {
        notify.success('MFA enabled successfully.', 'Settings');
        this.showOtpForm = false;
        this.loadMFA();
      });
    },

    disableMFA() {
      this.$refs.confirmModal.open({
        title: 'Disable MFA',
        message: 'Disable MFA for this account? This reduces login protection until MFA is enabled again.',
        confirmLabel: 'Disable MFA',
        confirmVariant: 'danger',
        onConfirm: async () => {
          await axios.post('/api/mfa/disable');
          notify.success('MFA disabled.', 'Settings');
          this.loadMFA();
        },
      });
    },

    savePrefs() {
      notify.info('Preferences saved (setup backend later).', 'Settings');
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
        admin_ip_allowlist: settings.admin_ip_allowlist || '',
        password_max_age_days: settings.password_max_age_days ?? 90,
        enable_import_malware_scanning: !!settings.enable_import_malware_scanning,
        malware_scanner_socket_path: settings.malware_scanner_socket_path || '',
        malware_scanner_host: settings.malware_scanner_host || '127.0.0.1',
        malware_scanner_port: settings.malware_scanner_port ?? 3310,
        malware_scanner_timeout_seconds: settings.malware_scanner_timeout_seconds ?? 15,
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
        meta_environment: settings.meta_environment || 'production',
        meta_token_last_rotated_at: this.toDateTimeLocal(settings.meta_token_last_rotated_at),
        meta_token_expires_at: this.toDateTimeLocal(settings.meta_token_expires_at),
        meta_token_rotation_notes: settings.meta_token_rotation_notes || '',
        meta_whatsapp_business_account_id: settings.meta_whatsapp_business_account_id || '',
        meta_whatsapp_phone_number_id: settings.meta_whatsapp_phone_number_id || '',
        meta_whatsapp_display_phone_number: settings.meta_whatsapp_display_phone_number || '',
        meta_webhook_verify_token: settings.meta_webhook_verify_token || '',
      };
      this.meta.permissions_last_checked_at = settings.meta_permissions_last_checked_at || null;
      this.meta.permissions_status = settings.meta_permissions_status || null;
      this.meta.permissions_snapshot = settings.meta_permissions_snapshot || null;

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
      payload.append('admin_ip_allowlist', this.system.form.admin_ip_allowlist || '');
      payload.append('password_max_age_days', this.system.form.password_max_age_days ?? '');
      payload.append('enable_import_malware_scanning', this.system.form.enable_import_malware_scanning ? '1' : '0');
      payload.append('malware_scanner_socket_path', this.system.form.malware_scanner_socket_path || '');
      payload.append('malware_scanner_host', this.system.form.malware_scanner_host || '');
      payload.append('malware_scanner_port', this.system.form.malware_scanner_port ?? '');
      payload.append('malware_scanner_timeout_seconds', this.system.form.malware_scanner_timeout_seconds ?? '');
      payload.append('remove_app_logo', this.system.removeLogo ? '1' : '0');
      if (this.system.logoFile) {
        payload.append('app_logo', this.system.logoFile);
      }

      axios
        .post('/api/settings', payload)
        .then((res) => {
          this.applyAdminSettings(res.data || {});
          notify.success('System settings saved.', 'Settings');
        })
        .catch((err) => {
          notify.error('Failed to save system settings: ' + (err.response?.data?.message || err.message), 'Settings');
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
          this.wa.availableStatuses = [...new Set(this.wa.templates.map((t) => t.status).filter(Boolean))].sort();
          this.wa.availableCategories = [...new Set(this.wa.templates.map((t) => t.category).filter(Boolean))].sort();
          this.wa.availableLanguages = [...new Set(this.wa.templates.map((t) => t.language).filter(Boolean))].sort();
        })
        .catch(() => {
          this.wa.templates = [];
          this.wa.availableStatuses = [];
          this.wa.availableCategories = [];
          this.wa.availableLanguages = [];
        })
        .finally(() => {
          this.wa.loading = false;
        });
    },
    resetWhatsappTemplateFilters() {
      this.wa.filters = {
        search: '',
        status: '',
        category: '',
        language: '',
      };
    },
    startCreate() {
      this.resetForm();
      this.wa.viewOnly = false;
      if (this.templateModal) {
        this.templateModal.show();
      }
    },
    viewTemplate(t) {
      this.wa.viewOnly = true;
      this.wa.viewingSid = t.sid;
      this.wa.saving = true;
      axios
        .get(`/api/whatsapp-templates/${encodeURIComponent(t.sid)}`)
        .then((res) => {
          const template = res.data?.template || {};
          this.wa.form = {
            sid: template.id || t.sid,
            friendly_name: template.name || t.name,
            body: template.preview || t.body_preview || '',
            language: template.language || t.language || 'en',
            category: (template.category || t.category || 'utility').toLowerCase(),
            media_urls: Array.isArray(template.media_urls)
              ? template.media_urls.join(',')
              : Array.isArray(t.media_urls)
                ? t.media_urls.join(',')
                : '',
            header_format: template.header_format || t.header_format || '',
            header_text: template.header_text || t.header_text || '',
            footer_text: template.footer_text || t.footer_text || '',
            buttons: Array.isArray(template.buttons)
              ? template.buttons
              : (Array.isArray(t.buttons) ? t.buttons : []),
          };
          this.templateModal?.show();
        })
        .catch((err) => {
          notify.error('Failed to load template details: ' + (err.response?.data?.message || err.message), 'Settings');
        })
        .finally(() => {
          this.wa.saving = false;
          this.wa.viewingSid = null;
        });
    },
    editTemplate(t) {
      this.wa.viewOnly = false;
      this.wa.form = {
        sid: t.sid,
        friendly_name: t.name,
        body: t.body_preview || '',
        language: t.language || 'en',
        category: (t.category || 'utility').toLowerCase(),
        media_urls: (t.media_urls || []).join(','),
        header_format: t.header_format || '',
        header_text: t.header_text || '',
        footer_text: t.footer_text || '',
        buttons: Array.isArray(t.buttons) ? t.buttons : [],
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
        language: 'en_US',
        category: 'utility',
        media_urls: '',
        header_format: '',
        header_text: '',
        footer_text: '',
        buttons: [],
      };
    },
    saveTemplate() {
      if (!this.wa.form.friendly_name || !this.wa.form.body) {
        notify.warning('Please provide a friendly name and body.', 'Settings');
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
          notify.success(this.wa.form.sid ? 'Template updated.' : 'Template created and submitted to Meta.', 'Settings');
          this.resetForm();
          this.loadWhatsappTemplates();
          this.templateModal?.hide();
        })
        .catch((err) => {
          notify.error('Failed to save template: ' + (err.response?.data?.message || err.message), 'Settings');
        })
        .finally(() => {
          this.wa.saving = false;
        });
    },
    submitTemplate(t) {
      this.$refs.confirmModal.open({
        title: 'Submit Template',
        message: `Submit "${t.name}" to Meta for WhatsApp approval?`,
        confirmLabel: 'Submit to Meta',
        confirmVariant: 'primary',
        onConfirm: async () => {
          try {
            await axios.post(`/api/whatsapp-templates/${t.sid}/submit`, { category: t.category || 'utility' });
            notify.success('Template submitted for approval.', 'Settings');
            this.loadWhatsappTemplates();
          } catch (err) {
            notify.error('Failed to submit template: ' + (err.response?.data?.message || err.message), 'Settings');
            throw err;
          }
        },
      });
    },
    deleteTemplate(t) {
      this.$refs.confirmModal.open({
        title: 'Delete Template',
        message: `Delete template "${t.name}"? This action cannot be undone.`,
        confirmLabel: 'Delete Template',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            await axios.delete(`/api/whatsapp-templates/${t.sid}`);
            this.loadWhatsappTemplates();
            notify.success(`Template "${t.name}" deleted.`, 'Settings');
          } catch (err) {
            notify.error('Failed to delete template: ' + (err.response?.data?.message || err.message), 'Settings');
            throw err;
          }
        },
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
        notify.warning('Access token, business account ID, and phone number ID are required.', 'Settings');
        return;
      }
      this.meta.saving = true;
      axios
        .post('/api/settings', this.meta.form)
        .then((res) => {
          this.applyAdminSettings(res.data || {});
          notify.success('Meta WhatsApp settings saved.', 'Settings');
        })
        .catch((err) => {
        notify.error('Failed to save Meta WhatsApp settings: ' + (err.response?.data?.message || err.message), 'Settings');
        })
        .finally(() => {
          this.meta.saving = false;
        });
    },
    validateMetaPermissions() {
      this.meta.validating = true;
      axios
        .post('/api/settings/meta/validate')
        .then((res) => {
          if (res.data?.settings) {
            this.applyAdminSettings(res.data.settings);
          }
          notify.success(res.data?.message || 'Meta permissions validated.', 'Settings');
        })
        .catch((err) => {
          if (err.response?.data?.settings) {
            this.applyAdminSettings(err.response.data.settings);
          }
          notify.error(err.response?.data?.message || err.message, 'Settings');
        })
        .finally(() => {
          this.meta.validating = false;
        });
    },
    permissionStatusBadge(status) {
      if (status === 'healthy') return 'bg-success';
      if (status === 'warning') return 'bg-warning text-dark';
      if (status === 'error') return 'bg-danger';
      return 'bg-secondary';
    },
    toDateTimeLocal(value) {
      if (!value) return '';
      return String(value).replace(' ', 'T').slice(0, 16);
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
