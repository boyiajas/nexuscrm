<template>
  <div>
    <div class="fade-in-up">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div>
        <h1 class="h3 fw-bold text-dark mb-1">Account Settings</h1>
        <p class="text-muted small mb-0">Manage your profile, security, and preference configurations.</p>
      </div>

      <div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
          {{ userRoleName }}
        </span>
      </div>
    </div>

    <!-- Main Navigation Tabs -->
    <ul class="nav nav-pills gap-1 mb-4 p-1 rounded-3 bg-white border shadow-sm flex-wrap" id="settingsTabs" role="tablist">
      <li class="nav-item" role="presentation" v-if="canAccessUserAccount">
        <button
          class="nav-link px-3 py-2 fw-semibold"
          :class="{ active: activeMainTab === 'account' }"
          @click="activeMainTab = 'account'"
          id="account-tab"
          data-bs-toggle="tab"
          data-bs-target="#account"
          type="button"
        >
          <i class="bi bi-person me-1"></i> User Account
        </button>
      </li>
      <li class="nav-item" role="presentation" v-if="canAccessSystem">
        <button
          class="nav-link px-3 py-2 fw-semibold"
          :class="{ active: activeMainTab === 'system' }"
          @click="activeMainTab = 'system'"
          id="system-tab"
          data-bs-toggle="tab"
          data-bs-target="#system"
          type="button"
        >
          <i class="bi bi-cpu me-1"></i> System Settings
        </button>
      </li>
      <li class="nav-item" role="presentation" v-if="canAccessMetaWhatsapp">
        <button
          class="nav-link px-3 py-2 fw-semibold"
          :class="{ active: activeMainTab === 'meta' }"
          @click="activeMainTab = 'meta'"
          id="meta-tab"
          data-bs-toggle="tab"
          data-bs-target="#meta"
          type="button"
        >
          <i class="bi bi-whatsapp me-1"></i> Meta WhatsApp
        </button>
      </li>
      <li class="nav-item" role="presentation" v-if="canAccessWabaProfiles">
        <button
          class="nav-link px-3 py-2 fw-semibold"
          :class="{ active: activeMainTab === 'whatsapp-profiles' }"
          @click="activeMainTab = 'whatsapp-profiles'"
          id="whatsapp-profiles-tab"
          data-bs-toggle="tab"
          data-bs-target="#whatsapp-profiles"
          type="button"
        >
          <i class="bi bi-person-lines-fill me-1"></i> WABA Profiles
        </button>
      </li>
      <li class="nav-item" role="presentation" v-if="canAccessWabaNumbers">
        <button
          class="nav-link px-3 py-2 fw-semibold"
          :class="{ active: activeMainTab === 'whatsapp-numbers' }"
          @click="activeMainTab = 'whatsapp-numbers'"
          id="whatsapp-numbers-tab"
          data-bs-toggle="tab"
          data-bs-target="#whatsapp-numbers"
          type="button"
        >
          <i class="bi bi-telephone-fill me-1"></i> WABA Numbers
        </button>
      </li>
      <li class="nav-item" role="presentation" v-if="canAccessWabaTemplates">
        <button
          class="nav-link px-3 py-2 fw-semibold"
          :class="{ active: activeMainTab === 'whatsapp-templates' }"
          @click="activeMainTab = 'whatsapp-templates'"
          id="whatsapp-templates-tab"
          data-bs-toggle="tab"
          data-bs-target="#whatsapp-templates"
          type="button"
        >
          <i class="bi bi-file-earmark-text-fill me-1"></i> WABA Templates
        </button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- ACCOUNT TAB (Mockup 5 Two-Column Layout) -->
      <div class="tab-pane fade" :class="{ 'show active': activeMainTab === 'account' }" id="account" v-if="canAccessUserAccount">
        <div class="row g-4">
          <!-- Left Column (Sub-navigation) -->
          <div class="col-lg-3">
            <div class="card border shadow-sm p-2">
              <div class="nav flex-column nav-pills gap-1">
                <button 
                  class="nav-link text-start fw-semibold py-2 px-3 d-flex align-items-center gap-2" 
                  :class="activeAccountTab === 'personal' ? 'active' : 'text-secondary'" 
                  @click="activeAccountTab = 'personal'"
                >
                  <i class="bi bi-person"></i> Personal Info
                </button>
                <button 
                  class="nav-link text-start fw-semibold py-2 px-3 d-flex align-items-center gap-2" 
                  :class="activeAccountTab === 'security' ? 'active' : 'text-secondary'" 
                  @click="activeAccountTab = 'security'"
                >
                  <i class="bi bi-shield-lock"></i> Security
                </button>
                <button 
                  class="nav-link text-start fw-semibold py-2 px-3 d-flex align-items-center gap-2" 
                  :class="activeAccountTab === 'preferences' ? 'active' : 'text-secondary'" 
                  @click="activeAccountTab = 'preferences'"
                >
                  <i class="bi bi-sliders"></i> Preferences
                </button>
              </div>
            </div>
          </div>

          <!-- Right Column (Profile Header & Form Cards) -->
          <div class="col-lg-9">
            <!-- User Profile Banner Card -->
            <div class="card border shadow-sm mb-4" v-if="activeAccountTab === 'personal'">
              <div class="card-body p-4 d-flex align-items-center gap-4">
                <div class="position-relative">
                  <img
                    :src="avatarPreview || form.avatar_url || 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=200&auto=format&fit=crop'"
                    alt="Profile Avatar"
                    class="rounded-circle border"
                    style="width: 72px; height: 72px; object-fit: cover; cursor: pointer;"
                    @click="$refs.avatarInput.click()"
                  />
                  <input type="file" ref="avatarInput" class="d-none" accept="image/*" @change="onAvatarSelected" />
                  <div class="position-absolute bottom-0 end-0 bg-primary rounded-circle border border-white d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; cursor: pointer;" @click="$refs.avatarInput.click()">
                    <i class="bi bi-camera-fill text-white" style="font-size: 0.75rem;"></i>
                  </div>
                </div>
                <div>
                  <h2 class="h5 fw-bold text-dark mb-1">{{ form.first_name }} {{ form.last_name }}</h2>
                  <div class="text-muted small mb-2">{{ form.email }}</div>
                  <span class="badge bg-light text-dark border">
                    <i class="bi bi-building me-1 text-primary"></i> Standard Bank
                  </span>
                </div>
              </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="updateAccount" v-if="activeAccountTab === 'personal'">
              <!-- Personal Information Card -->
              <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                  <h3 class="h6 mb-0 fw-bold text-dark">Personal Information</h3>
                </div>
                <div class="card-body p-4">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">First Name *</label>
                      <input v-model="form.first_name" type="text" class="form-control" required />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">Last Name *</label>
                      <input v-model="form.last_name" type="text" class="form-control" required />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">Email Address *</label>
                      <input v-model="form.email" type="email" class="form-control" required />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">Primary Phone</label>
                      <input v-model="form.primary_phone" type="text" class="form-control" placeholder="+267 71 234 567" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Working Information Card -->
              <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                  <h3 class="h6 mb-0 fw-bold text-dark">Working Information</h3>
                </div>
                <div class="card-body p-4">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">Assigned Role</label>
                      <input type="text" class="form-control text-capitalize" :value="userRoleName" readonly />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">Primary Location</label>
                      <input type="text" class="form-control" value="Gaborone HQ" />
                    </div>
                    <div class="col-md-12">
                      <label class="form-label small fw-bold text-secondary">Time Zone</label>
                      <select class="form-select">
                        <option>(GMT+02:00) Central Africa Time (CAT)</option>
                        <option>(GMT+00:00) UTC</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Action Button -->
              <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-dark-pill px-4 py-2 shadow-sm" :disabled="savingAccount">
                  <i class="bi bi-check-circle me-1" v-if="!savingAccount"></i> Save Configuration
                </button>
              </div>
            </form>

            <!-- SECURITY SUB-TAB CONTENT -->
            <div v-if="activeAccountTab === 'security'">
              <!-- Two-Factor Authentication Card -->
              <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                  <h3 class="h6 mb-0 fw-bold text-dark">Two-Factor Authentication</h3>
                </div>
                <div class="card-body p-4">
                  <div v-if="mfa.enabled" class="alert alert-success d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-check-circle-fill"></i> MFA Enabled ({{ mfa.type }})
                  </div>
                  <div v-else class="alert alert-warning d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i> MFA is currently disabled.
                  </div>

                  <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary shadow-sm" @click="enableEmailMFA" v-if="!mfa.enabled">
                      Enable Email OTP
                    </button>
                    <button class="btn btn-outline-danger shadow-sm" @click="disableMFA" v-if="mfa.enabled">
                      Disable MFA
                    </button>
                  </div>

                  <div v-if="showOtpForm" class="mt-4 p-3 border rounded bg-light">
                    <h6 class="fw-semibold mb-2">Enter the code sent to your email</h6>
                    <form @submit.prevent="verifyOtp" class="row g-2 align-items-center">
                      <div class="col-auto">
                        <input v-model="otpCode" type="text" class="form-control" maxlength="6" placeholder="123456" />
                      </div>
                      <div class="col-auto">
                        <button class="btn btn-success shadow-sm">Verify</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <!-- Recent Sessions Card -->
              <div class="card border shadow-sm mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                  <div>
                    <h3 class="h6 mb-0 fw-bold text-dark">Recent Sessions</h3>
                    <small class="text-muted" style="font-size: 0.75rem;">Track current and recent device access to this account.</small>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-secondary rounded-2 shadow-sm" @click="loadSessions">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                  </button>
                </div>
                <div class="card-body p-0">
                  <div class="p-4 border-bottom bg-light">
                    <div class="row g-3">
                      <div class="col-md-4">
                        <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Last Login</div>
                        <div class="fw-semibold text-dark mt-1">{{ form.last_login_at || '-' }}</div>
                      </div>
                      <div class="col-md-4">
                        <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Last Login IP</div>
                        <div class="fw-semibold text-dark mt-1">{{ form.last_login_ip || '-' }}</div>
                      </div>
                      <div class="col-md-4">
                        <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Password Updated</div>
                        <div class="fw-semibold text-dark mt-1">{{ form.password_changed_at || '-' }}</div>
                      </div>
                    </div>
                  </div>

                  <TableLoadingWrapper :loading="sessionsLoading" message="Loading sessions..." min-height="180px">
                    <div v-if="sessions.length" class="table-responsive">
                      <table class="table table-hover align-middle mb-0">
                        <thead>
                          <tr>
                            <th class="ps-4">Device / Browser</th>
                            <th>IP</th>
                            <th>Auth</th>
                            <th>Authenticated</th>
                            <th>Last Activity</th>
                            <th class="pe-4 text-end">Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="session in sessions" :key="session.id">
                            <td class="ps-4 py-3 small">{{ session.user_agent || '-' }}</td>
                            <td>{{ session.ip_address || '-' }}</td>
                            <td>{{ session.authentication_method || '-' }}</td>
                            <td>{{ session.authenticated_at || '-' }}</td>
                            <td>{{ session.last_activity_at || '-' }}</td>
                            <td class="pe-4 text-end">
                              <span v-if="session.is_current" class="badge bg-primary">Current</span>
                              <span v-else-if="session.logged_out_at" class="badge bg-secondary">{{ session.logout_reason || 'Closed' }}</span>
                              <span v-else class="badge bg-success">Active</span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else-if="!sessionsLoading" class="text-center text-muted small p-4">No tracked sessions yet.</div>
                  </TableLoadingWrapper>
                </div>
              </div>
            </div>
            
            <!-- PREFERENCES SUB-TAB CONTENT -->
              <div v-if="activeAccountTab === 'preferences'">
                <div class="card border shadow-sm mb-4">
                  <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h3 class="h6 mb-0 fw-bold text-dark">User Preferences</h3>
                  </div>
                  <div class="card-body p-4">
                    <div class="form-check form-switch mb-4">
                      <input class="form-check-input" type="checkbox" v-model="prefs.darkMode" id="prefDarkMode" style="cursor: pointer;">
                      <label class="form-check-label fw-semibold" for="prefDarkMode" style="cursor: pointer;">Enable Dark Mode</label>
                      <div class="small text-muted mt-1">Switch the application interface to a darker color palette.</div>
                    </div>

                    <div class="form-check form-switch mb-4">
                      <input class="form-check-input" type="checkbox" v-model="prefs.notifications" id="prefNotifications" style="cursor: pointer;">
                      <label class="form-check-label fw-semibold" for="prefNotifications" style="cursor: pointer;">Enable Notifications</label>
                      <div class="small text-muted mt-1">Receive alerts for new chats, system updates, and task completions.</div>
                    </div>

                    <div class="d-flex justify-content-end border-top pt-3 mt-2">
                      <button class="btn btn-dark-pill px-4 py-2 shadow-sm" @click="savePrefs">
                        <i class="bi bi-check-circle me-1"></i> Save Preferences
                      </button>
                    </div>
                  </div>
                </div>
              </div>
          </div>
        </div>
      </div>

      <!-- (Security Tab removed, contents moved to Account -> Security sub-tab) -->

      <!-- SYSTEM SETTINGS TAB -->
      <div class="tab-pane fade" :class="{ 'show active': activeMainTab === 'system' }" id="system" v-if="canAccessSystem">
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
        <div class="row g-3 mt-1">
          <div class="col-12">
            <div class="card shadow-sm border-danger">
              <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="bi bi-shield-lock-fill me-2"></i>Live Chat Emergency Lock</h6>
              </div>
              <div class="card-body">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch" id="globalChatLock" v-model="system.form.live_chat_locked" />
                  <label class="form-check-label text-danger fw-bold" for="globalChatLock">Lock all Live Chat communication</label>
                  <div class="form-text">When enabled, agents will not be able to send outbound messages. Inbound messages will still be received.</div>
                </div>
                <div>
                  <label class="form-label" :class="{'text-danger': system.form.live_chat_locked}">Disabled Message</label>
                  <input v-model="system.form.live_chat_locked_message" type="text" class="form-control" :class="{'border-danger': system.form.live_chat_locked}" placeholder="Live chat is temporarily disabled." :disabled="!system.form.live_chat_locked" />
                  <small class="text-muted">This message will be displayed in the chat input area for all agents.</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- META CONFIG TAB -->
      <div class="tab-pane fade" :class="{ 'show active': activeMainTab === 'meta' }" id="meta" v-if="canAccessMetaWhatsapp">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">Meta WhatsApp Configuration</h5>
            <small class="text-muted">Store the Cloud API credentials in the database and use the webhook values below in Meta.</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-info btn-sm" @click="subscribeWebhook" :disabled="meta.subscribingWebhook || meta.saving">
              <span v-if="meta.subscribingWebhook" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-broadcast me-1"></i> Subscribe Webhooks
            </button>
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
                <input v-model="meta.form.meta_token_last_rotated_at" type="datetime-local" class="form-control" min="2000-01-01T00:00" />
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
                <label class="form-label">Manual Daily WhatsApp Limit</label>
                <input
                  v-model.number="meta.form.meta_daily_whatsapp_limit"
                  type="number"
                  min="1"
                  class="form-control"
                  placeholder="5000"
                />
                <small class="text-muted">Leave blank to treat the system-wide daily cap as unlimited. This value is enforced against bulk WhatsApp sends.</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Webhook Verify Token</label>
                <input
                  v-model="meta.form.meta_webhook_verify_token"
                  type="text"
                  class="form-control"
                  placeholder="Generated verify token"
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
                <input v-model="meta.form.meta_token_expires_at" type="datetime-local" class="form-control" min="2000-01-01T00:00" />
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
                <h6 class="mb-1">Current Meta Number Health</h6>
                <small class="text-muted">Live WhatsApp phone details fetched from the configured Meta Cloud API number.</small>
              </div>
              <div class="small text-muted">
                Last fetched: {{ meta.phone_profile?.fetched_at || '-' }}
              </div>
            </div>

            <div v-if="meta.phone_profile?.fetch_error" class="alert alert-warning mb-3">
              Unable to fetch live Meta number details: {{ meta.phone_profile.fetch_error }}
            </div>

            <div class="row g-3">
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Messaging Limit Tier</div>
                <div class="fw-semibold">{{ metaMessagingTierLabel }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Throughput</div>
                <div class="fw-semibold">{{ metaThroughputLabel }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Quality Rating</div>
                <div class="fw-semibold">{{ meta.phone_profile?.quality_rating || '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Verified Name</div>
                <div class="fw-semibold">{{ meta.phone_profile?.verified_name || '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Display Number</div>
                <div class="fw-semibold">{{ meta.phone_profile?.display_phone_number || meta.form.meta_whatsapp_display_phone_number || '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Name Status</div>
                <div class="fw-semibold">{{ meta.phone_profile?.name_status || '-' }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted text-uppercase">Code Verification</div>
                <div class="fw-semibold">{{ meta.phone_profile?.code_verification_status || '-' }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h6 class="mb-1">Daily WhatsApp Sending Limit</h6>
                <small class="text-muted">Manual CRM-side daily cap used for campaign send validation and remaining-cap display.</small>
              </div>
              <span class="badge" :class="whatsappLimitStatusBadge">
                {{ whatsappLimitStatusLabel }}
              </span>
            </div>

            <div class="row g-3">
              <div class="col-md-3">
                <div class="small text-muted text-uppercase">Configured Daily Cap</div>
                <div class="fw-semibold">{{ whatsappSystemLimitLabel }}</div>
              </div>
              <div class="col-md-3">
                <div class="small text-muted text-uppercase">Sent Today</div>
                <div class="fw-semibold">{{ whatsappDailyLimitSummary?.system_used ?? 0 }}</div>
              </div>
              <div class="col-md-3">
                <div class="small text-muted text-uppercase">Remaining Today</div>
                <div class="fw-semibold">{{ whatsappSystemRemainingLabel }}</div>
              </div>
              <div class="col-md-3">
                <div class="small text-muted text-uppercase">Effective Per-User Cap</div>
                <div class="fw-semibold">{{ whatsappEffectiveLimitLabel }}</div>
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

      <!-- WHATSAPP PROFILES TAB -->
      <div class="tab-pane fade" :class="{ 'show active': activeMainTab === 'whatsapp-profiles' }" id="whatsapp-profiles" v-if="canAccessWabaProfiles">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">WhatsApp Profiles</h5>
            <small class="text-muted">Manage credentials for multiple WhatsApp Business Accounts.</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" @click="fetchWhatsappProfiles" :disabled="wp.loading">
              <span v-if="wp.loading" class="spinner-border spinner-border-sm me-1"></span>
              Refresh
            </button>
            <button class="btn btn-primary btn-sm" @click="openAddProfileModal">
              <i class="bi bi-plus-circle me-1"></i> Add Profile
            </button>
          </div>
        </div>

        <div class="card shadow-sm border mb-4">
          <div class="card-body p-0">
            <div v-if="wp.loading" class="p-4 text-center text-muted">
              <span class="spinner-border spinner-border-sm me-2"></span>
              Loading profiles...
            </div>
            <div v-else-if="!wp.profiles.length" class="p-4 text-center text-muted">
              No WhatsApp profiles saved. Add one to get started.
            </div>
            <div v-else class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4">Profile Name</th>
                  <th>App ID</th>
                  <th>WABA ID</th>
                  <th>Display Number</th>
                  <th class="text-end pe-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="profile in wp.profiles" :key="profile.id">
                  <td class="ps-4 py-3 fw-semibold">
                    {{ profile.name }}
                    <span v-if="profile.waba_id === meta.form.meta_whatsapp_business_account_id" class="badge bg-success ms-2">Active</span>
                  </td>
                  <td class="text-muted small">{{ profile.app_id }}</td>
                  <td class="text-muted small">{{ profile.waba_id }}</td>
                  <td>{{ profile.display_phone_number || profile.phone_number_id }}</td>
                  <td class="text-end pe-4">
                    <button class="btn btn-light text-secondary border-0 p-1 px-2 me-2" @click="editProfile(profile)">Edit</button>
                    <button class="btn btn-light text-danger border-0 p-1 px-2 me-2" @click="deleteProfile(profile)">Delete</button>
                    <button class="btn btn-light text-primary border-0 p-1 px-2" @click="activateProfile(profile)" :disabled="profile.waba_id === meta.form.meta_whatsapp_business_account_id || wp.activating === profile.id">
                      <span v-if="wp.activating === profile.id" class="spinner-border spinner-border-sm me-1"></span>
                      Set Active
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>

      <!-- WHATSAPP NUMBERS TAB -->
      <div class="tab-pane fade" :class="{ 'show active': activeMainTab === 'whatsapp-numbers' }" id="whatsapp-numbers" v-if="canAccessWabaNumbers">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">WhatsApp Phone Numbers</h5>
            <small class="text-muted">Manage phone numbers associated with your WhatsApp Business Account (WABA).</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" @click="fetchWhatsappNumbers" :disabled="wn.loading">
              <span v-if="wn.loading" class="spinner-border spinner-border-sm me-1"></span>
              Refresh
            </button>
            <button class="btn btn-primary btn-sm" @click="openAddNumberModal">
              <i class="bi bi-plus-circle me-1"></i> Add Number
            </button>
          </div>
        </div>

        <div class="card shadow-sm border mb-4">
          <div class="card-body p-0">
            <div v-if="wn.loading" class="p-4 text-center text-muted">
              <span class="spinner-border spinner-border-sm me-2"></span>
              Loading phone numbers...
            </div>
            <div v-else-if="!wn.numbers.length" class="p-4 text-center text-muted">
              No WhatsApp phone numbers found for this WABA.
            </div>
            <div v-else class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4">Display Number</th>
                  <th>Phone Number ID</th>
                  <th>Verified Name</th>
                  <th>Quality Rating</th>
                  <th>Status</th>
                  <th>Messaging Tier</th>
                  <th class="text-end pe-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="num in wn.numbers" :key="num.id">
                  <td class="ps-4 py-3 fw-semibold">{{ num.display_phone_number }}</td>
                  <td class="text-muted small font-monospace">{{ num.id }}</td>
                  <td>{{ num.verified_name || '-' }}</td>
                  <td>
                    <span class="badge" :class="qualityRatingBadge(num.quality_rating)">
                      {{ num.quality_rating || 'UNKNOWN' }}
                    </span>
                  </td>
                  <td>
                    <div class="small">Code: <strong>{{ num.code_verification_status }}</strong></div>
                    <div class="small text-muted">Name: {{ num.name_status }}</div>
                  </td>
                  <td>{{ num.messaging_limit_tier || '-' }}</td>
                  <td class="text-end pe-4">
                    <button 
                      v-if="num.code_verification_status === 'UNVERIFIED'"
                      class="btn btn-light text-warning border-0 p-1 px-2" 
                      @click="openVerifyNumberModal(num)">
                      Verify
                    </button>
                    <button
                      v-if="num.code_verification_status === 'VERIFIED' && num.platform_type === 'NOT_APPLICABLE'"
                      class="btn btn-light text-success border-0 p-1 px-2 ms-2"
                      @click="registerNumberOnMeta(num)"
                      :disabled="num.registering"
                    >
                      <span v-if="num.registering" class="spinner-border spinner-border-sm me-1"></span>
                      Complete Registration
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>

      <!-- WHATSAPP TEMPLATES TAB -->
      <div class="tab-pane fade" :class="{ 'show active': activeMainTab === 'whatsapp-templates' }" id="whatsapp-templates" v-if="canAccessWabaTemplates">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1">WhatsApp Templates</h5>
            <small class="text-muted">View, search, and create WhatsApp templates synced with Meta.</small>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" @click="syncWhatsappTemplates" :disabled="wa.loading">
              <span v-if="wa.loading" class="spinner-border spinner-border-sm me-1"></span>
              Refresh
            </button>
            <button type="button" class="btn btn-secondary btn-sm" @click="openMigrateModal" :disabled="wa.selected.length === 0">
              <i class="bi bi-arrow-right-circle me-1"></i>
              Migrate Selected ({{ wa.selected.length }})
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
            <!-- Bulk Actions Bar -->
            <div v-if="wa.selected.length > 0" class="d-flex align-items-center justify-content-between mb-3 px-3 py-2 bg-primary bg-opacity-10 rounded border border-primary border-opacity-25 shadow-sm mx-3 mt-3">
              <div>
                <span class="fw-bold text-primary">{{ wa.selected.length }}</span> <span class="text-secondary small fw-medium">template(s) selected</span>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-danger bg-white fw-medium shadow-sm" @click="bulkDeleteTemplates" :disabled="wa.bulkActionLoading">
                  <i class="bi bi-trash"></i> Delete Templates
                </button>
              </div>
            </div>

            <div v-else class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-4" style="width: 40px;">
                    <div class="form-check m-0">
                      <input class="form-check-input" type="checkbox" :checked="wa.selected.length > 0 && wa.selected.length === filteredWhatsappTemplates.length" @change="toggleSelectAllTemplates" />
                    </div>
                  </th>
                  <th>Name</th>
                  <th>Language</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th>Preview</th>
                  <th style="width: 120px;" class="text-end pe-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in filteredWhatsappTemplates" :key="t.sid">
                  <td class="ps-4 py-1">
                    <div class="form-check m-0">
                      <input class="form-check-input" type="checkbox" :value="t.meta_id || t.sid" v-model="wa.selected" />
                    </div>
                  </td>
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
                  <td class="text-end pe-4">
                    <div class="btn-group btn-group-sm" role="group">
                      <button
                        type="button"
                        class="btn btn-light text-primary border-0 p-1 px-2"
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
                  <td colspan="6" class="text-center text-muted py-5">
                    No templates match the current filters.
                  </td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

    <!-- WhatsApp Template Modal -->
    <div class="modal fade" tabindex="-1" ref="templateModalRef">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ wa.viewOnly ? 'WhatsApp Template Details' : (wa.form.sid ? 'Edit WhatsApp Template' : 'Create WhatsApp Template') }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-lg-7 col-md-6 border-end pe-4">
                <!-- FORM INPUTS -->
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
                  <div class="col-12" v-if="wa.form.variables && Object.keys(wa.form.variables).length > 0">
                    <label class="form-label d-flex align-items-center">
                      Template Variables
                      <span class="badge bg-secondary ms-2">{{ Object.keys(wa.form.variables).length }} Variable(s)</span>
                    </label>
                    <div class="d-flex flex-wrap gap-2">
                      <span v-for="(val, key) in wa.form.variables" :key="key" class="badge bg-light text-dark border shadow-sm">
                        {{ '{' + '{' + key + '}' + '}' }}
                      </span>
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
              
              <!-- PREVIEW PANE -->
              <div class="col-lg-5 col-md-6 ps-4">
                <div class="mb-3">
                  <div class="card border-success shadow-sm">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-white border-bottom-0">
                      <strong>Template Preview</strong>
                    </div>
                    <div class="card-body p-0 d-flex flex-column" style="background-color: #e5ddd5; position: relative;">
                      <!-- WhatsApp Header -->
                      <div class="bg-white d-flex align-items-center px-3 py-2 shadow-sm position-relative" style="z-index: 2;">
                        <i class="bi bi-arrow-left me-3 text-secondary"></i>
                        <div class="bg-secondary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                          <i class="bi bi-person-fill text-secondary fs-4"></i>
                        </div>
                        <div class="lh-1">
                          <div class="fw-bold text-dark d-flex align-items-center gap-1 mb-1" style="font-size: 0.95rem;">
                            {{ system.form.app_name || 'Strauss Recovery Solutions' }}
                            <i class="bi bi-patch-check-fill text-success" style="font-size: 0.85rem;" title="Official business account"></i>
                          </div>
                          <div class="text-muted" style="font-size: 0.75rem;">{{ meta.form.meta_whatsapp_display_phone_number || '+27 82 123 4567' }}</div>
                        </div>
                      </div>

                      <div class="p-4 flex-grow-1 position-relative">
                        <div style="opacity: 0.05; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cGF0aCBkPSJNMCAwaDQwMHY0MDBIMHoiIGZpbGw9Im5vbmUiLz48Y2lyY2xlIGN4PSIyMDAiIGN5PSIyMDAiIHI9IjM1IiBmaWxsPSIjMDAwIi8+PC9zdmc+'); background-size: 200px; pointer-events: none;"></div>
                        
                        <div class="bg-white rounded position-relative shadow-sm" style="max-width: 85%; border-top-left-radius: 0 !important; padding: 0.5rem; margin-left: 10px; z-index: 1;">
                          <svg viewBox="0 0 8 13" width="8" height="13" style="position: absolute; top: 0; left: -8px; color: white;">
                            <path opacity="1" fill="currentColor" d="M1.533 3.568 8 12.193V1H2.812C1.042 1 .474 2.156 1.533 3.568z"></path>
                          </svg>

                          <!-- Media Preview -->
                          <div
                            v-if="firstMediaUrl"
                            class="mb-2"
                          >
                            <img
                              v-if="wa.form.header_format === 'IMAGE' || !wa.form.header_format"
                              :src="firstMediaUrl"
                              alt="WhatsApp media preview"
                              class="img-fluid rounded"
                              style="width: 100%; object-fit: cover;"
                            />
                            <video
                              v-else-if="wa.form.header_format === 'VIDEO'"
                              :src="firstMediaUrl"
                              class="img-fluid rounded"
                              style="width: 100%; object-fit: cover;"
                              controls
                              preload="metadata"
                            ></video>
                            <div
                              v-else-if="wa.form.header_format === 'DOCUMENT'"
                              class="border rounded p-3 bg-light text-center"
                            >
                              <i class="bi bi-file-earmark-arrow-down fs-3 d-block text-secondary"></i>
                            </div>
                          </div>

                          <div v-if="wa.form.header_text" class="fw-bold text-dark mb-1" style="font-size: 0.95rem; line-height: 1.3;">
                            {{ wa.form.header_text }}
                          </div>

                          <div class="text-dark" style="font-size: 0.9rem; line-height: 1.4; white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">{{ wa.form.body || 'Template message body will appear here.' }}<span class="d-inline-block" style="width: 40px;"></span></div>
                          
                          <div class="d-flex justify-content-between align-items-end mt-1">
                              <div class="text-muted" style="font-size: 0.75rem;">
                                  {{ wa.form.footer_text || '' }}
                              </div>
                              <div class="text-muted text-end" style="font-size: 0.65rem; margin-top: -15px; margin-right: 4px;">
                                  09:31
                              </div>
                          </div>
                        </div>
                        
                        <!-- Buttons Preview -->
                        <div
                          v-if="wa.form.buttons && wa.form.buttons.length"
                          class="mt-1 d-flex flex-column gap-1"
                          style="max-width: 85%; margin-left: 10px; z-index: 1; position: relative;"
                        >
                          <div
                            v-for="(button, idx) in wa.form.buttons"
                            :key="idx"
                            class="bg-white rounded shadow-sm text-center py-2 fw-semibold cursor-pointer hover-shadow transition"
                            style="color: #00a884; font-size: 0.9rem; border: 1px solid rgba(0,0,0,0.05);"
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

    </div>

    <!-- ADD NUMBER MODAL -->
    <div class="modal fade" tabindex="-1" ref="addNumberModalRef">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add WhatsApp Number</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info small">
              This adds a phone number to your Meta WhatsApp Business Account. It must be a valid number capable of receiving an SMS or Voice call for verification.
            </div>
            <div class="mb-3">
              <label class="form-label">Country Code (e.g. 1 for US, 27 for SA)</label>
              <input v-model="wn.addForm.cc" type="text" class="form-control" placeholder="1" />
            </div>
            <div class="mb-3">
              <label class="form-label">Phone Number (without country code)</label>
              <input v-model="wn.addForm.phone_number" type="text" class="form-control" placeholder="5551234567" />
            </div>
            <div class="mb-3">
              <label class="form-label">Verified Name (Optional)</label>
              <input v-model="wn.addForm.verified_name" type="text" class="form-control" placeholder="My Business Name" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="wn.saving">Cancel</button>
            <button type="button" class="btn btn-primary" @click="submitAddNumber" :disabled="wn.saving || !wn.addForm.cc || !wn.addForm.phone_number">
              <span v-if="wn.saving" class="spinner-border spinner-border-sm me-1"></span>
              Add Number
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- VERIFY NUMBER MODAL -->
    <div class="modal fade" tabindex="-1" ref="verifyNumberModalRef">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Verify WhatsApp Number</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Verifying: <strong>{{ wn.verifyForm.display_phone_number }}</strong></p>
            
            <div v-if="!wn.verifyForm.codeSent">
              <div class="mb-3">
                <label class="form-label">Verification Method</label>
                <select v-model="wn.verifyForm.method" class="form-select">
                  <option value="SMS">SMS</option>
                  <option value="VOICE">Voice Call</option>
                </select>
              </div>
              <button class="btn btn-outline-primary" @click="requestVerificationCode" :disabled="wn.saving">
                <span v-if="wn.saving" class="spinner-border spinner-border-sm me-1"></span>
                Send Verification Code
              </button>
            </div>
            
            <div v-else class="mt-3">
              <div class="alert alert-success small">Code sent via {{ wn.verifyForm.method }}. Please enter the 6-digit code below.</div>
              <div class="mb-3">
                <label class="form-label">6-Digit Code</label>
                <input v-model="wn.verifyForm.code" type="text" class="form-control" placeholder="123456" maxlength="6" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="wn.saving">Cancel</button>
            <button v-if="wn.verifyForm.codeSent" type="button" class="btn btn-success" @click="submitVerificationCode" :disabled="wn.saving || wn.verifyForm.code.length < 6">
              <span v-if="wn.saving" class="spinner-border spinner-border-sm me-1"></span>
              Verify Number
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- WHATSAPP MIGRATE MODAL -->
    <div class="modal fade" id="whatsappMigrateModal" tabindex="-1" ref="migrateModalRef">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Migrate WhatsApp Templates</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="wa.migrating"></button>
          </div>
          <div class="modal-body">
            <p class="mb-3">
              You are about to migrate <strong>{{ wa.selected.length }}</strong> template(s) to another WhatsApp Business Account.
            </p>
            
            <div class="mb-3">
              <label class="form-label">Destination WABA ID Source</label>
              <select v-model="wa.migrateForm.destinationType" class="form-select" :disabled="wa.migrating">
                <option value="profile">Select from Saved Profiles</option>
                <option value="custom">Enter Custom WABA ID</option>
              </select>
            </div>

            <div v-if="wa.migrateForm.destinationType === 'profile'" class="mb-3">
              <label class="form-label">Select Profile</label>
              <select v-model="wa.migrateForm.profile_id" class="form-select" :disabled="wa.migrating">
                <option value="" disabled>-- Select a Profile --</option>
                <option v-for="profile in wp.profiles" :key="profile.id" :value="profile.waba_id">
                  {{ profile.name }} (WABA: {{ profile.waba_id }})
                </option>
              </select>
            </div>

            <div v-if="wa.migrateForm.destinationType === 'custom'" class="mb-3">
              <label class="form-label">Custom WABA ID</label>
              <input v-model.trim="wa.migrateForm.custom_waba_id" type="text" class="form-control" placeholder="e.g. 1455412218881488" :disabled="wa.migrating" />
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="wa.migrating">Cancel</button>
            <button type="button" class="btn btn-primary" @click="submitMigration" :disabled="wa.migrating || !hasValidMigrationDestination">
              <span v-if="wa.migrating" class="spinner-border spinner-border-sm me-1"></span>
              Start Migration
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- WHATSAPP PROFILE MODAL -->
    <div class="modal fade" id="whatsappProfileModal" tabindex="-1" ref="profileModalRef">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form @submit.prevent="submitProfile">
            <div class="modal-header">
              <h5 class="modal-title">{{ wp.form.id ? 'Edit Profile' : 'Add WhatsApp Profile' }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Profile Name *</label>
                  <input v-model="wp.form.name" type="text" class="form-control" placeholder="e.g. Iconis CRM" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">App ID *</label>
                  <input v-model="wp.form.app_id" type="text" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">App Secret *</label>
                  <input v-model="wp.form.app_secret" type="password" class="form-control" :required="!wp.form.id" :placeholder="wp.form.id ? 'Leave blank to keep existing' : ''" />
                </div>
                <div class="col-12">
                  <label class="form-label">System User Access Token *</label>
                  <input v-model="wp.form.access_token" type="password" class="form-control" :required="!wp.form.id" :placeholder="wp.form.id ? 'Leave blank to keep existing' : ''" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">WhatsApp Business Account ID *</label>
                  <input v-model="wp.form.waba_id" type="text" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone Number ID *</label>
                  <input v-model="wp.form.phone_number_id" type="text" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Display Phone Number</label>
                  <input v-model="wp.form.display_phone_number" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Webhook Verify Token *</label>
                  <input v-model="wp.form.webhook_verify_token" type="password" class="form-control" :required="!wp.form.id" :placeholder="wp.form.id ? 'Leave blank to keep existing' : ''" />
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="wp.saving">
                <span v-if="wp.saving" class="spinner-border spinner-border-sm me-1"></span>
                Save Profile
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <ConfirmationModal ref="confirmModal" />
</template>

<script>
import axios from '../axios';
import VueMultiselect from 'vue-multiselect';
import ConfirmationModal from '../components/ConfirmationModal.vue';
import TableLoadingWrapper from '../components/TableLoadingWrapper.vue';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import { notify } from '../utils/notify';
import 'vue-multiselect/dist/vue-multiselect.min.css';

export default {
  name: 'SettingsView',
  components: {
    VueMultiselect,
    ConfirmationModal,
    TableLoadingWrapper,
  },
  data() {
    return {
      activeAccountTab: 'personal',
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
        avatar_url: null,
      },
      avatarFile: null,
      avatarPreview: null,
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
      sessionsLoading: false,
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
          live_chat_locked: false,
          live_chat_locked_message: 'Live chat is temporarily disabled.',
        },
      },
      meta: {
        saving: false,
        validating: false,
        subscribingWebhook: false,
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
          meta_daily_whatsapp_limit: null,
          meta_webhook_verify_token: '',
        },
        permissions_last_checked_at: null,
        permissions_status: null,
        permissions_snapshot: null,
        phone_profile: null,
        daily_limit_summary: null,
      },
      wp: {
        loading: false,
        saving: false,
        activating: null,
        profiles: [],
        modal: null,
        form: {
          id: null,
          name: '',
          app_id: '',
          app_secret: '',
          access_token: '',
          waba_id: '',
          phone_number_id: '',
          display_phone_number: '',
          webhook_verify_token: '',
        }
      },
      wn: {
        loading: false,
        saving: false,
        numbers: [],
        addForm: {
          cc: '',
          phone_number: '',
          verified_name: '',
        },
        verifyForm: {
          id: null,
          display_phone_number: '',
          method: 'SMS',
          code: '',
          codeSent: false,
        },
        addNumberModal: null,
        verifyNumberModal: null,
      },
      wa: {
        templates: [],
        selected: [],
        loading: false,
        saving: false,
        bulkActionLoading: false,
        migrating: false,
        migrateModal: null,
        migrateForm: {
          destinationType: 'profile',
          profile_id: '',
          custom_waba_id: '',
        },
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
          variables: {},
        },
      },
      templateModal: null,
      activeMainTab: 'account',
      currentUserData: null,
    };
  },
  created() {
    const stored = localStorage.getItem('nexus_user');
    if (stored) {
      try {
        this.currentUserData = JSON.parse(stored);
      } catch (e) {}
    }
    this.handleAuthUserUpdated = (e) => {
      if (e.detail) {
        this.currentUserData = e.detail;
        this.checkActiveTab();
      }
    };
    window.addEventListener('auth-user-updated', this.handleAuthUserUpdated);
  },
  mounted() {
      this.loadUser();
      this.loadMFA();
      this.loadSessions();
      this.templateModal = createManagedModal(this.$refs.templateModalRef);
      this.wn.addNumberModal = createManagedModal(this.$refs.addNumberModalRef);
      this.wn.verifyNumberModal = createManagedModal(this.$refs.verifyNumberModalRef);
      this.wp.modal = createManagedModal(this.$refs.profileModalRef);
      this.wa.migrateModal = createManagedModal(this.$refs.migrateModalRef);

      this.checkActiveTab();

      if (this.canAccessSystem || this.canAccessMetaWhatsapp) {
        this.loadAdminSettings();
      }
      if (this.canAccessWabaProfiles) {
        this.fetchWhatsappProfiles();
      }
      if (this.canAccessWabaTemplates) {
        this.loadWhatsappTemplates();
      }
      if (this.canAccessWabaNumbers) {
        this.fetchWhatsappNumbers();
      }
      this.loadDepartmentOptions();
    },
  beforeUnmount() {
    window.removeEventListener('auth-user-updated', this.handleAuthUserUpdated);
    disposeManagedModal(this.templateModal);
    disposeManagedModal(this.wn.addNumberModal);
    disposeManagedModal(this.wn.verifyNumberModal);
    disposeManagedModal(this.wp.modal);
    disposeManagedModal(this.wa.migrateModal);
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
    currentUser() {
      if (this.currentUserData) return this.currentUserData;
      const stored = localStorage.getItem('nexus_user');
      if (!stored) return null;
      try {
        return JSON.parse(stored);
      } catch {
        return null;
      }
    },
    userRoleName() {
      const user = this.currentUser;
      if (Array.isArray(user?.role_names) && user.role_names.length) {
        return user.role_names.join(', ');
      }
      return (user?.role || 'USER').replace(/_/g, ' ');
    },
    canAccessUserAccount() {
      return this.hasPermission('settings_user_account');
    },
    canAccessSystem() {
      return this.hasPermission('settings_system') || this.hasPermission('manage_system_settings');
    },
    canAccessMetaWhatsapp() {
      return this.hasPermission('settings_meta_whatsapp');
    },
    canAccessWabaProfiles() {
      return this.hasPermission('settings_waba_profile');
    },
    canAccessWabaNumbers() {
      return this.hasPermission('settings_waba_numbers');
    },
    canAccessWabaTemplates() {
      return this.hasPermission('settings_waba_templates');
    },
    hasValidMigrationDestination() {
      if (this.wa.migrateForm.destinationType === 'profile') {
        return !!this.wa.migrateForm.profile_id;
      }
      return !!this.wa.migrateForm.custom_waba_id;
    },
    isSuperAdmin() {
      const user = this.currentUser;
      if (!user) return false;
      const roles = Array.isArray(user?.role_codes) && user.role_codes.length
        ? user.role_codes
        : [user?.role].filter(Boolean);
      return roles.some((role) => ['SUPER_ADMIN', 'ADMIN'].includes(role));
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
    metaMessagingTierLabel() {
      const tier = this.meta.phone_profile?.messaging_limit_tier;
      if (!tier) {
        return this.metaThroughputLabel !== '-' ? `Unavailable from Meta, throughput ${this.metaThroughputLabel}` : '-';
      }

      return String(tier)
        .replace(/^TIER_/i, 'Tier ')
        .replace(/_/g, ' ')
        .trim();
    },
    metaThroughputLabel() {
      const throughput = this.meta.phone_profile?.throughput;
      if (!throughput) {
        return '-';
      }

      if (typeof throughput === 'string') {
        return throughput.replace(/_/g, ' ').trim();
      }

      if (typeof throughput === 'object') {
        const level = throughput.level || throughput.tier || throughput.name || null;
        const mps = throughput.messages_per_second || throughput.mps || throughput.value || null;

        if (level && mps) {
          return `${String(level).replace(/_/g, ' ').trim()} (${mps})`;
        }

        if (level) {
          return String(level).replace(/_/g, ' ').trim();
        }

        if (mps) {
          return String(mps);
        }
      }

      return '-';
    },
    whatsappDailyLimitSummary() {
      return this.meta.daily_limit_summary || null;
    },
    whatsappLimitStatusLabel() {
      return this.whatsappDailyLimitSummary?.status
        ? String(this.whatsappDailyLimitSummary.status).toUpperCase()
        : 'UNAVAILABLE';
    },
    whatsappLimitStatusBadge() {
      switch (this.whatsappDailyLimitSummary?.status) {
        case 'healthy':
          return 'bg-success';
        case 'warning':
          return 'bg-warning text-dark';
        case 'low':
          return 'bg-warning text-dark';
        case 'critical':
          return 'bg-danger';
        case 'unlimited':
          return 'bg-primary';
        default:
          return 'bg-secondary';
      }
    },
    whatsappSystemLimitLabel() {
      const value = this.whatsappDailyLimitSummary?.system_limit ?? this.meta.form.meta_daily_whatsapp_limit;
      return value ? Number(value).toLocaleString() : 'Unlimited';
    },
    whatsappSystemRemainingLabel() {
      const value = this.whatsappDailyLimitSummary?.system_remaining;
      return value === null || value === undefined ? 'Unlimited' : Number(value).toLocaleString();
    },
    whatsappEffectiveLimitLabel() {
      const value = this.whatsappDailyLimitSummary?.effective_limit;
      return value === null || value === undefined ? 'Unlimited' : Number(value).toLocaleString();
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
    hasPermission(permCode) {
      if (this.isSuperAdmin) return true;
      const user = this.currentUser;
      if (!user) return false;
      if (Array.isArray(user.permission_codes)) {
        return user.permission_codes.includes(permCode);
      }
      return false;
    },
    switchToTab(tabId) {
      const btn = document.getElementById(`${tabId}-tab`);
      if (btn) {
        btn.click();
      }
    },
    checkActiveTab() {
      if (this.canAccessUserAccount) {
        this.activeMainTab = 'account';
      } else if (this.canAccessSystem) {
        this.activeMainTab = 'system';
      } else if (this.canAccessMetaWhatsapp) {
        this.activeMainTab = 'meta';
      } else if (this.canAccessWabaProfiles) {
        this.activeMainTab = 'whatsapp-profiles';
      } else if (this.canAccessWabaNumbers) {
        this.activeMainTab = 'whatsapp-numbers';
      } else if (this.canAccessWabaTemplates) {
        this.activeMainTab = 'whatsapp-templates';
      }
    },
    // Load profile
    loadUser() {
      axios.get('/api/user').then((res) => {
        const fallback = { ...this.form };
        const user = res.data || {};
        this.currentUserData = user;
        try {
          localStorage.setItem('nexus_user', JSON.stringify(user));
        } catch (e) {}
        this.form = Object.assign(fallback, user, {
          department_ids: Array.isArray(user.departments) ? user.departments.map((department) => department.id) : [],
          active: user.status ? user.status === 'Active' : fallback.active,
          avatar_url: user.avatar_url || null,
        });
        if (user.preferences) {
          this.prefs = Object.assign({}, this.prefs, user.preferences);
        }
        this.syncSelectedDepartments();
        this.checkActiveTab();
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
      this.sessionsLoading = true;
      axios.get('/api/user/sessions').then((res) => {
        this.sessions = res.data || [];
      }).catch(() => {
        this.sessions = [];
      }).finally(() => {
        this.sessionsLoading = false;
      });
    },
    onAvatarSelected(event) {
      const file = event.target.files[0];
      if (!file) return;
      this.avatarFile = file;
      this.avatarPreview = URL.createObjectURL(file);
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

      const formData = new FormData();
      formData.append('_method', 'PUT');
      Object.keys(payload).forEach(key => {
        if (payload[key] !== null && payload[key] !== undefined) {
          if (Array.isArray(payload[key])) {
            payload[key].forEach(val => formData.append(`${key}[]`, val));
          } else if (typeof payload[key] === 'boolean') {
            formData.append(key, payload[key] ? 1 : 0);
          } else {
            formData.append(key, payload[key]);
          }
        }
      });

      if (this.avatarFile) {
        formData.append('avatar', this.avatarFile);
      }

      axios.post('/api/user', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      }).then((res) => {
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

    savePrefs() {
      const payload = {
        preferences: this.prefs,
      };
      
      const formData = new FormData();
      formData.append('_method', 'PUT');
      
      // Need to stringify preferences for multipart, or we can just send as JSON if we don't have avatar
      // Let's just use axios.put since there's no file
      axios.put('/api/user', payload).then((res) => {
        notify.success('Preferences saved.', 'Settings');
      }).catch((err) => {
        notify.error('Failed to save preferences.', 'Settings');
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
        live_chat_locked: settings.live_chat_locked || false,
        live_chat_locked_message: settings.live_chat_locked_message || 'Live chat is temporarily disabled.',
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
        meta_daily_whatsapp_limit: settings.meta_daily_whatsapp_limit ?? null,
        meta_webhook_verify_token: settings.meta_webhook_verify_token || '',
      };
      this.meta.permissions_last_checked_at = settings.meta_permissions_last_checked_at || null;
      this.meta.permissions_status = settings.meta_permissions_status || null;
      this.meta.permissions_snapshot = settings.meta_permissions_snapshot || null;
      this.meta.phone_profile = settings.meta_phone_profile || null;
      this.meta.daily_limit_summary = settings.whatsapp_daily_limit_summary || null;

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

    // WhatsApp templates — load from local DB cache (fast, no Meta API call)
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
    // Refresh: pull latest templates from Meta API and save to DB
    syncWhatsappTemplates() {
      this.wa.loading = true;
      axios
        .post('/api/whatsapp-templates/sync')
        .then((res) => {
          notify.success(`Synced ${res.data.count || 0} templates from Meta.`, 'WhatsApp Templates');
          this.loadWhatsappTemplates();
        })
        .catch((err) => {
          notify.error(err.response?.data?.message || 'Failed to sync templates from Meta.', 'WhatsApp Templates');
          this.wa.loading = false;
        });
    },
    bulkDeleteTemplates() {
      if (this.wa.selected.length === 0) return;

      this.$refs.confirmModal.open({
        title: 'Delete Selected Templates',
        message: `Are you sure you want to delete ${this.wa.selected.length} template(s)? This will also attempt to delete them from Meta. This action cannot be undone.`,
        confirmText: 'Delete Templates',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
          this.wa.bulkActionLoading = true;
          try {
            await axios.delete('/api/whatsapp-templates/bulk-delete', {
              data: { template_ids: this.wa.selected },
            });
            notify.success('Templates deleted successfully.', 'Settings');
            this.wa.selected = [];
            this.fetchWhatsappTemplates();
          } catch (error) {
            console.error('Error during bulk deletion:', error);
            notify.error(error.response?.data?.message || 'Failed to delete selected templates.', 'Settings');
          } finally {
            this.wa.bulkActionLoading = false;
          }
        },
      });
    },
    toggleSelectAllTemplates(event) {
      if (event.target.checked) {
        this.wa.selected = this.filteredWhatsappTemplates.map(t => t.meta_id || t.sid);
      } else {
        this.wa.selected = [];
      }
    },
    openMigrateModal() {
      if (!this.wa.selected.length) return;
      this.wa.migrateForm.custom_waba_id = '';
      this.wa.migrateForm.profile_id = '';
      this.wa.migrateForm.destinationType = 'profile';
      this.wa.migrateModal?.show();
    },
    submitMigration() {
      if (!this.hasValidMigrationDestination) return;

      const destinationId = this.wa.migrateForm.destinationType === 'profile'
        ? this.wa.migrateForm.profile_id
        : this.wa.migrateForm.custom_waba_id;

      this.wa.migrating = true;
      axios.post('/api/whatsapp-templates/migrate', {
        destination_waba_id: destinationId,
        template_ids: this.wa.selected,
      })
      .then(res => {
        notify.success('Templates migrated successfully.', 'Migration');
        this.wa.migrateModal?.hide();
        this.wa.selected = [];
      })
      .catch(err => {
        notify.error('Migration failed: ' + (err.response?.data?.message || err.message), 'Migration');
      })
      .finally(() => {
        this.wa.migrating = false;
      });
    },
    resetWhatsappTemplateFilters() {
      this.wa.filters = {
        search: '',
        status: '',
        category: '',
        language: '',
      };
      this.wa.waModal = null;

      // WhatsApp Numbers
      this.wn = {
        loading: false,
        saving: false,
        numbers: [],
        addForm: {
          cc: '',
          phone_number: '',
          verified_name: '',
        },
        verifyForm: {
          id: '',
          display_phone_number: '',
          method: 'SMS',
          code: '',
          codeSent: false,
        }
      };
      this.wn.addNumberModal = null;
      this.wn.verifyNumberModal = null;
    },

    // WhatsApp Profiles Methods
    fetchWhatsappProfiles() {
      this.wp.loading = true;
      axios.get('/api/settings/whatsapp-accounts')
        .then(res => {
          this.wp.profiles = res.data || [];
        })
        .catch(err => {
          notify.error('Failed to load WhatsApp profiles: ' + (err.response?.data?.message || err.message), 'Settings');
        })
        .finally(() => {
          this.wp.loading = false;
        });
    },
    openAddProfileModal() {
      this.wp.form = {
        id: null,
        name: '',
        app_id: '',
        app_secret: '',
        access_token: '',
        waba_id: '',
        phone_number_id: '',
        display_phone_number: '',
        webhook_verify_token: '',
      };
      this.wp.modal?.show();
    },
    editProfile(profile) {
      this.wp.form = {
        id: profile.id,
        name: profile.name,
        app_id: profile.app_id,
        app_secret: '',
        access_token: '',
        waba_id: profile.waba_id,
        phone_number_id: profile.phone_number_id,
        display_phone_number: profile.display_phone_number,
        webhook_verify_token: '',
      };
      this.wp.modal?.show();
    },
    submitProfile() {
      this.wp.saving = true;
      const request = this.wp.form.id
        ? axios.put(`/api/settings/whatsapp-accounts/${this.wp.form.id}`, this.wp.form)
        : axios.post('/api/settings/whatsapp-accounts', this.wp.form);

      request
        .then(() => {
          notify.success(this.wp.form.id ? 'Profile updated.' : 'Profile created.', 'Settings');
          this.wp.modal?.hide();
          this.fetchWhatsappProfiles();
        })
        .catch(err => {
          notify.error('Failed to save profile: ' + (err.response?.data?.message || err.message), 'Settings');
        })
        .finally(() => {
          this.wp.saving = false;
        });
    },
    deleteProfile(profile) {
      this.$refs.confirmModal.open({
        title: 'Delete Profile',
        message: `Are you sure you want to delete the profile "${profile.name}"?`,
        confirmLabel: 'Delete',
        confirmVariant: 'danger',
        onConfirm: async () => {
          try {
            await axios.delete(`/api/settings/whatsapp-accounts/${profile.id}`);
            notify.success('Profile deleted.', 'Settings');
            this.fetchWhatsappProfiles();
          } catch (err) {
            notify.error('Failed to delete profile: ' + (err.response?.data?.message || err.message), 'Settings');
          }
        },
      });
    },
    activateProfile(profile) {
      this.$refs.confirmModal.open({
        title: 'Activate Profile',
        message: `This will overwrite your current active Meta WhatsApp credentials with the credentials from "${profile.name}". Do you want to continue?`,
        confirmLabel: 'Yes, Set Active',
        confirmVariant: 'primary',
        onConfirm: async () => {
          this.wp.activating = profile.id;
          try {
            await axios.post(`/api/settings/whatsapp-accounts/${profile.id}/activate`);
            notify.success(`Profile "${profile.name}" is now active.`, 'Settings');
            this.loadAdminSettings(); // Reload global settings so UI updates
            this.fetchWhatsappNumbers(); // Fetch numbers for new WABA
          } catch (err) {
            notify.error('Failed to activate profile: ' + (err.response?.data?.message || err.message), 'Settings');
          } finally {
            this.wp.activating = null;
          }
        },
      });
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
            variables: template.variables || t.variables || {},
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
    // WhatsApp Numbers Methods
    async fetchWhatsappNumbers() {
      this.wn.loading = true;
      try {
        const { data } = await axios.get('/api/settings/meta/phone-numbers');
        this.wn.numbers = data || [];
      } catch (err) {
        notify.error('Failed to load WhatsApp phone numbers: ' + (err.response?.data?.message || err.message), 'Settings');
      } finally {
        this.wn.loading = false;
      }
    },
    openAddNumberModal() {
      this.wn.addForm = { cc: '', phone_number: '', verified_name: '' };
      this.wn.addNumberModal?.show();
    },
    async submitAddNumber() {
      this.wn.saving = true;
      try {
        await axios.post('/api/settings/meta/phone-numbers', this.wn.addForm);
        notify.success('Phone number successfully added to WABA.', 'Settings');
        this.wn.addNumberModal?.hide();
        this.fetchWhatsappNumbers();
      } catch (err) {
        notify.error('Failed to add phone number: ' + (err.response?.data?.message || err.message), 'Settings');
      } finally {
        this.wn.saving = false;
      }
    },
    openVerifyNumberModal(num) {
      this.wn.verifyForm = {
        id: num.id,
        display_phone_number: num.display_phone_number,
        method: 'SMS',
        code: '',
        codeSent: false,
      };
      this.wn.verifyNumberModal?.show();
    },
    async requestVerificationCode() {
      this.wn.saving = true;
      try {
        await axios.post('/api/settings/meta/phone-numbers/request-verification', {
          phone_number_id: this.wn.verifyForm.id,
          method: this.wn.verifyForm.method,
        });
        notify.success('Verification code requested via ' + this.wn.verifyForm.method, 'Settings');
        this.wn.verifyForm.codeSent = true;
      } catch (err) {
        notify.error('Failed to request verification code: ' + (err.response?.data?.message || err.message), 'Settings');
      } finally {
        this.wn.saving = false;
      }
    },
    async submitVerificationCode() {
      this.wn.saving = true;
      try {
        await axios.post('/api/settings/meta/phone-numbers/verify', {
          phone_number_id: this.wn.verifyForm.id,
          code: this.wn.verifyForm.code,
        });
        notify.success('Phone number verified successfully!', 'Settings');
        this.wn.verifyNumberModal?.hide();
        this.fetchWhatsappNumbers();
      } catch (err) {
        notify.error('Failed to verify phone number: ' + (err.response?.data?.message || err.message), 'Settings');
      } finally {
        this.wn.saving = false;
      }
    },
    async registerNumberOnMeta(num) {
      if (this.$set) {
        this.$set(num, 'registering', true);
      } else {
        num.registering = true;
      }
      try {
        await axios.post('/api/settings/meta/phone-numbers/register', {
          phone_number_id: num.id,
          pin: '123456'
        });
        notify.success('Number successfully registered on Cloud API!', 'Settings');
        this.fetchWhatsappNumbers();
      } catch (err) {
        notify.error('Failed to register number: ' + (err.response?.data?.message || err.message), 'Settings');
      } finally {
        num.registering = false;
      }
    },
    qualityRatingBadge(rating) {
      const r = (rating || '').toUpperCase();
      if (r === 'HIGH') return 'bg-success';
      if (r === 'MEDIUM') return 'bg-warning';
      if (r === 'LOW') return 'bg-danger';
      return 'bg-secondary';
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
    subscribeWebhook() {
      this.meta.subscribingWebhook = true;
      axios
        .post('/api/settings/meta/subscribe-webhook')
        .then((res) => {
          notify.success(res.data?.message || 'Webhook subscribed to Meta WABA.', 'Settings');
        })
        .catch((err) => {
          notify.error(err.response?.data?.message || err.message, 'Settings');
        })
        .finally(() => {
          this.meta.subscribingWebhook = false;
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
