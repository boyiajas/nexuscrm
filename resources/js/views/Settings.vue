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
          <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-danger h-100">
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
          <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-warning h-100">
              <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="bi bi-person-x-fill me-2"></i>Live Chat Opt-Out Policy</h6>
              </div>
              <div class="card-body">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch" id="disableChatForOptedOut" v-model="system.form.disable_chat_for_opted_out_clients" />
                  <label class="form-check-label text-dark fw-bold" for="disableChatForOptedOut">Disable Chat Input for Opted-Out Clients</label>
                  <div class="form-text">When enabled, agents cannot send messages or templates to clients who have opted out. The input field will be disabled.</div>
                </div>
                <div>
                  <label class="form-label" :class="{'text-warning-emphasis': system.form.disable_chat_for_opted_out_clients}">Opt-Out Notice Message</label>
                  <input v-model="system.form.opted_out_chat_message" type="text" class="form-control" :class="{'border-warning': system.form.disable_chat_for_opted_out_clients}" placeholder="This client has opted out of WhatsApp communication. Messaging is disabled." :disabled="!system.form.disable_chat_for_opted_out_clients" />
                  <small class="text-muted">This notice is displayed in the chat composer placeholder and banner when an opted-out client is selected.</small>
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
                  <th>Bank</th>
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
                  <td>
                    <span v-if="profile.bank_name || profile.bank?.name" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                      <i class="bi bi-bank me-1"></i>{{ profile.bank_name || profile.bank?.name }}
                    </span>
                    <span v-else class="text-muted small">—</span>
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
            <small class="text-muted">Submit, verify, register, and manage numbers associated with your WhatsApp Business Account (WABA).</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" @click="fetchWhatsappNumbers" :disabled="wn.loading">
              <span v-if="wn.loading" class="spinner-border spinner-border-sm me-1"></span>
              Refresh
            </button>
            <button class="btn btn-primary btn-sm" @click="openAddNumberModal">
              <i class="bi bi-plus-circle me-1"></i> Onboard New Number
            </button>
          </div>
        </div>

        <div class="alert alert-primary d-flex align-items-start gap-3 mb-3">
          <i class="bi bi-info-circle-fill mt-1"></i>
          <div>
            <div class="fw-semibold">Number onboarding</div>
            <div class="small">1. Submit the number and display name to Meta. 2. Verify ownership by SMS or voice call. 3. Register it on Cloud API with your six-digit two-step verification PIN. Meta reviews the display name separately; refresh this list to see its latest approval status.</div>
          </div>
        </div>

        <div class="card shadow-sm border-warning mb-4" v-if="pendingWhatsappNumbers.length">
          <div class="card-header bg-warning-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <h6 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Pending Numbers</h6>
              <small class="text-muted">{{ pendingWhatsappNumbers.length }} number(s) still require verification, registration, or display-name approval.</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-primary" :disabled="!wn.selectedPendingIds.length || wn.pendingRequesting" @click="requestSelectedVerification('SMS')">
                <i class="bi bi-chat-left-text me-1"></i> SMS Selected
              </button>
              <button class="btn btn-sm btn-outline-primary" :disabled="!wn.selectedPendingIds.length || wn.pendingRequesting" @click="requestSelectedVerification('VOICE')">
                <i class="bi bi-telephone me-1"></i> Call Selected
              </button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-3" style="width: 42px;">
                    <input class="form-check-input" type="checkbox" :checked="allPendingNumbersSelected" @change="toggleAllPendingNumbers" aria-label="Select all pending numbers" />
                  </th>
                  <th>Number</th>
                  <th>Display Name</th>
                  <th>Verification</th>
                  <th>Name Approval</th>
                  <th>Cloud API</th>
                  <th class="text-end pe-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="num in pendingWhatsappNumbers" :key="'pending-' + num.id">
                  <td class="ps-3">
                    <input class="form-check-input" type="checkbox" v-model="wn.selectedPendingIds" :value="String(num.id)" :aria-label="'Select ' + (num.display_phone_number || num.id)" />
                  </td>
                  <td>
                    <div class="fw-semibold">{{ num.display_phone_number || '-' }}</div>
                    <div class="small text-muted font-monospace">{{ num.id }}</div>
                  </td>
                  <td>{{ num.verified_name || '-' }}</td>
                  <td><span class="badge" :class="String(num.code_verification_status).toUpperCase() === 'VERIFIED' ? 'bg-success' : 'bg-warning text-dark'">{{ num.code_verification_status || 'UNVERIFIED' }}</span></td>
                  <td><span class="badge" :class="String(num.name_status).toUpperCase() === 'APPROVED' ? 'bg-success' : 'bg-warning text-dark'">{{ num.name_status || 'PENDING' }}</span></td>
                  <td><span class="badge" :class="String(num.platform_type).toUpperCase() === 'CLOUD_API' ? 'bg-success' : 'bg-secondary'">{{ String(num.platform_type).toUpperCase() === 'CLOUD_API' ? 'REGISTERED' : 'NOT REGISTERED' }}</span></td>
                  <td class="text-end pe-3">
                    <div class="btn-group btn-group-sm" v-if="String(num.code_verification_status).toUpperCase() !== 'VERIFIED'">
                      <button class="btn btn-outline-primary" :disabled="wn.pendingRequesting" @click="requestPendingVerification(num, 'SMS', true)" title="Send verification code by SMS">SMS</button>
                      <button class="btn btn-outline-primary" :disabled="wn.pendingRequesting" @click="requestPendingVerification(num, 'VOICE', true)" title="Receive verification code by voice call">Call</button>
                      <button class="btn btn-outline-success" @click="openPendingCodeEntry(num)" title="Enter a code already received">Enter Code</button>
                    </div>
                    <button v-else-if="String(num.platform_type).toUpperCase() !== 'CLOUD_API'" class="btn btn-sm btn-outline-success" @click="openRegistrationModal(num)">
                      Register
                    </button>
                    <button class="btn btn-sm btn-outline-secondary ms-1" @click="openMetaNumberManager(num, 'edit')" title="Edit the display name in Meta WhatsApp Manager">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger ms-1" @click="openMetaNumberManager(num, 'remove')" title="Remove this number in Meta WhatsApp Manager">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="card-footer small text-muted">
            Meta does not allow phone-number deletion or display-name editing through the Graph API. Edit and remove actions open WhatsApp Manager for the selected WABA.
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
                  <th>WhatsApp Profile</th>
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
                    <span v-if="num.whatsapp_profile_name" class="fw-semibold">{{ num.whatsapp_profile_name }}</span>
                    <span v-else class="text-muted small">Not linked</span>
                  </td>
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
                      type="button"
                      class="btn btn-light border-0 p-1 px-2"
                      :class="num.is_paused ? 'text-success' : 'text-warning'"
                      :title="num.is_paused ? 'Resume this WhatsApp number' : 'Pause this WhatsApp number'"
                      :aria-label="num.is_paused ? 'Resume WhatsApp number' : 'Pause WhatsApp number'"
                      @click="toggleWhatsappNumberPause(num)"
                      :disabled="num.pauseSaving"
                    >
                      <span v-if="num.pauseSaving" class="spinner-border spinner-border-sm"></span>
                      <i v-else :class="num.is_paused ? 'bi bi-play-circle-fill' : 'bi bi-pause-circle-fill'"></i>
                    </button>
                    <button 
                      v-if="num.code_verification_status === 'UNVERIFIED'"
                      class="btn btn-light text-warning border-0 p-1 px-2 ms-2"
                      @click="openVerifyNumberModal(num)">
                      Verify
                    </button>
                    <button
                      v-if="num.code_verification_status === 'VERIFIED' && num.platform_type === 'NOT_APPLICABLE'"
                      class="btn btn-light text-success border-0 p-1 px-2 ms-2"
                      @click="openRegistrationModal(num)"
                      :disabled="wn.saving"
                    >
                      <i class="bi bi-cloud-check me-1"></i> Complete Registration
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
            <button type="button" class="btn btn-outline-success btn-sm" @click="exportWhatsappTemplates" :disabled="wa.exporting || wa.loading || wa.templates.length === 0">
              <span v-if="wa.exporting" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-file-earmark-excel me-1"></i>
              Export Excel
            </button>
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

        <div class="row g-3 mb-3">
          <div class="col-6 col-lg">
            <button type="button" class="card shadow-sm border-0 w-100 text-start template-status-card" :class="{ 'ring-active': wa.approvalView === 'all' }" @click="setTemplateApprovalView('all')">
              <div class="card-body py-3">
                <div class="small text-muted">All Templates</div>
                <div class="fs-4 fw-bold">{{ templateStatusCounts.all }}</div>
              </div>
            </button>
          </div>
          <div class="col-6 col-lg">
            <button type="button" class="card shadow-sm border-warning w-100 text-start template-status-card" :class="{ 'ring-active': wa.approvalView === 'pending' }" @click="setTemplateApprovalView('pending')">
              <div class="card-body py-3">
                <div class="small text-warning-emphasis">Awaiting Approval</div>
                <div class="fs-4 fw-bold text-warning-emphasis">{{ templateStatusCounts.pending }}</div>
              </div>
            </button>
          </div>
          <div class="col-6 col-lg">
            <button type="button" class="card shadow-sm border-success w-100 text-start template-status-card" :class="{ 'ring-active': wa.approvalView === 'approved' }" @click="setTemplateApprovalView('approved')">
              <div class="card-body py-3">
                <div class="small text-success">Approved</div>
                <div class="fs-4 fw-bold text-success">{{ templateStatusCounts.approved }}</div>
              </div>
            </button>
          </div>
          <div class="col-6 col-lg">
            <button type="button" class="card shadow-sm border-danger w-100 text-start template-status-card" :class="{ 'ring-active': wa.approvalView === 'rejected' }" @click="setTemplateApprovalView('rejected')">
              <div class="card-body py-3">
                <div class="small text-danger">Rejected</div>
                <div class="fs-4 fw-bold text-danger">{{ templateStatusCounts.rejected }}</div>
              </div>
            </button>
          </div>
          <div class="col-6 col-lg">
            <button type="button" class="card shadow-sm border-secondary w-100 text-start template-status-card" :class="{ 'ring-active': wa.approvalView === 'attention' }" @click="setTemplateApprovalView('attention')">
              <div class="card-body py-3">
                <div class="small text-muted">Needs Attention</div>
                <div class="fs-4 fw-bold text-secondary">{{ templateStatusCounts.attention }}</div>
              </div>
            </button>
          </div>
        </div>

        <div v-if="wa.approvalView === 'pending' && templateStatusCounts.pending" class="alert alert-warning py-2 small">
          <i class="bi bi-clock-history me-1"></i>
          These templates were submitted to Meta and are awaiting a review decision. Use Refresh to retrieve the latest status.
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
                      <input class="form-check-input" type="checkbox" :value="t.sid" v-model="wa.selected" />
                    </div>
                  </td>
                  <td class="fw-semibold">{{ t.name }}</td>
                  <td>{{ t.language || '-' }}</td>
                  <td>{{ t.category || '-' }}</td>
                  <td>
                    <div class="d-flex align-items-center gap-1">
                      <span class="badge" :class="statusBadge(t.status)">
                        {{ t.status || 'Unknown' }}
                      </span>
                      <button
                        type="button"
                        class="btn btn-sm btn-link p-0 ms-1 text-decoration-none"
                        :class="isPendingStatus(t.status) ? 'text-warning' : 'text-primary'"
                        :title="'View review status and delay insights for ' + t.name"
                        @click="openStatusInfoModal(t)"
                      >
                        <i class="bi bi-info-circle-fill"></i>
                      </button>
                    </div>
                    <div v-if="t.synced_at" class="small text-muted mt-1" :title="t.synced_at">Checked {{ formatTemplateCheckedAt(t.synced_at) }}</div>
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
                      <button type="button" class="btn btn-light text-warning border-0 p-1 px-2" title="Edit and resubmit template" @click="editTemplate(t)">
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button type="button" class="btn btn-light text-danger border-0 p-1 px-2" title="Delete template from Meta" @click="deleteTemplate(t)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="filteredWhatsappTemplates.length === 0">
                  <td colspan="7" class="text-center text-muted py-5">
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
    <div class="modal fade whatsapp-template-modal" tabindex="-1" ref="templateModalRef">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header py-2 px-3">
            <h5 class="modal-title fs-6 fw-bold mb-0">
              {{ wa.viewOnly ? 'WhatsApp Template Details' : (wa.form.sid ? 'Edit WhatsApp Template' : 'Create WhatsApp Template') }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-3">
            <div class="row g-3">
              <div class="col-lg-6 col-md-6 border-end pe-lg-3">
                <!-- FORM INPUTS -->
                <div v-if="!wa.viewOnly && !wa.form.sid" class="alert alert-info py-1.5 px-2.5 mb-2 small" style="font-size: 0.8rem; line-height: 1.35;">
                  Creating this template submits it directly to Meta for review. It will appear under Awaiting Approval until Meta approves or rejects it. This form currently supports text-body templates.
                </div>
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Friendly Name</label>
                    <input v-model.trim="wa.form.friendly_name" type="text" class="form-control form-control-sm" placeholder="appointment_reminder" maxlength="512" pattern="[a-z0-9_]+" :readonly="wa.viewOnly || !!wa.form.sid" />
                    <small v-if="!wa.viewOnly && !duplicateTemplateWarning" class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.25;">Use lowercase letters, numbers, and underscores for best Meta compatibility.</small>
                    <div v-if="duplicateTemplateWarning" class="text-danger small mt-0.5" style="font-size: 0.75rem;">
                      <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ duplicateTemplateWarning }}
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Language</label>
                    <input v-model="wa.form.language" type="text" class="form-control form-control-sm" placeholder="en_US" :readonly="wa.viewOnly" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Category</label>
                    <select v-model="wa.form.category" class="form-select form-select-sm" :disabled="wa.viewOnly">
                      <option value="utility">Utility</option>
                      <option value="marketing">Marketing</option>
                      <option value="authentication">Authentication</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label small fw-semibold mb-1">Body</label>
                    <textarea v-model="wa.form.body" class="form-control form-control-sm" rows="3" placeholder="Hi {{1}}, your order {{2}} is ready for pickup." :readonly="wa.viewOnly"></textarea>
                  </div>
                  <div v-if="!wa.viewOnly && templateBodyVariableIndexes.length" class="col-12">
                    <label class="form-label small fw-semibold mb-1">Variable Examples <span class="text-danger">*</span></label>
                    <div class="alert alert-warning py-1 px-2 mb-1.5 small" style="font-size: 0.75rem; line-height: 1.3;">Meta requires one realistic sample value for every body variable before it can review the template.</div>
                    <div class="row g-1.5">
                      <div v-for="index in templateBodyVariableIndexes" :key="index" class="col-md-6 mb-1">
                        <label class="form-label small mb-0.5 text-muted" style="font-size: 0.74rem;">Example for {{ templateVariablePlaceholder(index) }}</label>
                        <input v-model.trim="wa.form.body_examples[index]" type="text" class="form-control form-control-sm" :placeholder="'Sample value for ' + templateVariablePlaceholder(index)" maxlength="255" />
                      </div>
                    </div>
                  </div>
                  <div v-if="wa.viewOnly && wa.form.media_urls" class="col-12">
                    <label class="form-label small fw-semibold mb-1">Media URLs</label>
                    <input v-model="wa.form.media_urls" type="text" class="form-control form-control-sm" readonly />
                  </div>
                  <div class="col-md-4" v-if="wa.form.header_format">
                    <label class="form-label small fw-semibold mb-1">Header Format</label>
                    <input :value="wa.form.header_format" type="text" class="form-control form-control-sm" readonly />
                  </div>
                  <div class="col-12" v-if="wa.form.header_text">
                    <label class="form-label small fw-semibold mb-1">Header Text</label>
                    <input :value="wa.form.header_text" type="text" class="form-control form-control-sm" readonly />
                  </div>
                  <div class="col-12" v-if="wa.form.footer_text">
                    <label class="form-label small fw-semibold mb-1">Footer Text</label>
                    <input :value="wa.form.footer_text" type="text" class="form-control form-control-sm" readonly />
                  </div>
                  <div class="col-12" v-if="wa.form.variables && Object.keys(wa.form.variables).length > 0">
                    <label class="form-label small fw-semibold mb-1 d-flex align-items-center">
                      Template Variables
                      <span class="badge bg-secondary ms-2" style="font-size: 0.7rem;">{{ Object.keys(wa.form.variables).length }} Variable(s)</span>
                    </label>
                    <div class="d-flex flex-wrap gap-1">
                      <span v-for="(val, key) in wa.form.variables" :key="key" class="badge bg-light text-dark border shadow-xs" style="font-size: 0.74rem; font-family: monospace;">
                        {{ '{' + '{' + key + '}' + '}' }}
                      </span>
                    </div>
                  </div>
                  <!-- CUSTOM BUTTONS SECTION -->
                  <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-1.5 flex-wrap gap-1">
                      <label class="form-label mb-0 small fw-semibold d-flex align-items-center">
                        <i class="bi bi-menu-button-wide text-primary me-1"></i>
                        Buttons
                        <span v-if="wa.form.buttons.length" class="badge bg-primary bg-opacity-10 text-primary ms-1.5" style="font-size: 0.7rem;">
                          {{ wa.form.buttons.length }}
                        </span>
                        <span v-else class="text-muted small fw-normal ms-1.5" style="font-size: 0.75rem;">(Optional)</span>
                      </label>
                      <div v-if="!wa.viewOnly" class="d-flex align-items-center gap-1">
                        <button
                          type="button"
                          class="btn btn-outline-primary btn-sm py-0.5 px-2"
                          style="font-size: 0.75rem;"
                          @click="addTemplateButton('QUICK_REPLY')"
                          :disabled="wa.form.buttons.length >= 10"
                          title="Add a quick reply button"
                        >
                          <i class="bi bi-plus-lg me-1"></i>Quick Reply
                        </button>
                        <button
                          type="button"
                          class="btn btn-outline-success btn-sm py-0.5 px-2"
                          style="font-size: 0.75rem;"
                          @click="addTemplateButton('URL')"
                          :disabled="wa.form.buttons.length >= 10"
                          title="Add a website URL button"
                        >
                          <i class="bi bi-box-arrow-up-right me-1"></i>URL
                        </button>
                        <button
                          type="button"
                          class="btn btn-outline-secondary btn-sm py-0.5 px-2"
                          style="font-size: 0.75rem;"
                          @click="addTemplateButton('PHONE_NUMBER')"
                          :disabled="wa.form.buttons.length >= 10"
                          title="Add a phone call button"
                        >
                          <i class="bi bi-telephone me-1"></i>Call
                        </button>
                      </div>
                    </div>

                    <!-- Readonly View for Existing Template Details -->
                    <div v-if="wa.viewOnly">
                      <div v-if="wa.form.buttons && wa.form.buttons.length" class="d-flex flex-wrap gap-1.5">
                        <div
                          v-for="(btn, idx) in wa.form.buttons"
                          :key="idx"
                          class="badge bg-light text-dark border py-1 px-2 d-flex align-items-center gap-1.5 shadow-xs"
                          style="font-size: 0.76rem;"
                        >
                          <i v-if="String(btn.type || '').toUpperCase() === 'QUICK_REPLY'" class="bi bi-reply-fill text-success"></i>
                          <i v-else-if="String(btn.type || '').toUpperCase() === 'PHONE_NUMBER'" class="bi bi-telephone-fill text-success"></i>
                          <i v-else class="bi bi-box-arrow-up-right text-success"></i>
                          <span class="fw-semibold">{{ btn.text || 'Button ' + (idx + 1) }}</span>
                          <span class="text-muted small" v-if="btn.url">({{ btn.url }})</span>
                          <span class="text-muted small" v-else-if="btn.phone_number">({{ btn.phone_number }})</span>
                        </div>
                      </div>
                      <div v-else class="text-muted small py-1.5 px-2.5 bg-light rounded border" style="font-size: 0.75rem;">
                        No interactive buttons configured for this template.
                      </div>
                    </div>

                    <!-- Interactive Button Builder for Create & Edit -->
                    <div v-else>
                      <!-- Quick presets if no buttons added yet -->
                      <div v-if="!wa.form.buttons.length" class="py-2 px-2.5 bg-light rounded border border-dashed text-center">
                        <p class="text-muted small mb-1.5" style="font-size: 0.75rem; line-height: 1.3;">
                          Add buttons for clients to tap directly in WhatsApp (Quick Replies, Website Link, or Call phone number).
                        </p>
                        <div class="d-flex justify-content-center gap-1.5 flex-wrap">
                          <button
                            type="button"
                            class="btn btn-white btn-sm border shadow-xs py-0.5 px-2"
                            style="font-size: 0.75rem;"
                            @click="addTemplatePresetButton('Quick Reply', 'QUICK_REPLY')"
                          >
                            <i class="bi bi-reply-fill text-success me-1"></i>+ "Quick Reply"
                          </button>
                          <button
                            type="button"
                            class="btn btn-white btn-sm border shadow-xs py-0.5 px-2"
                            style="font-size: 0.75rem;"
                            @click="addTemplatePresetButton('Opt Out', 'QUICK_REPLY')"
                          >
                            <i class="bi bi-slash-circle text-danger me-1"></i>+ "Opt Out"
                          </button>
                          <button
                            type="button"
                            class="btn btn-white btn-sm border shadow-xs py-0.5 px-2"
                            style="font-size: 0.75rem;"
                            @click="addTemplatePresetButton('Visit Website', 'URL', 'https://')"
                          >
                            <i class="bi bi-box-arrow-up-right text-primary me-1"></i>+ "Visit Website"
                          </button>
                          <button
                            type="button"
                            class="btn btn-white btn-sm border shadow-xs py-0.5 px-2"
                            style="font-size: 0.75rem;"
                            @click="addTemplatePresetButton('Call Us', 'PHONE_NUMBER', '+27')"
                          >
                            <i class="bi bi-telephone-fill text-success me-1"></i>+ "Call Us"
                          </button>
                        </div>
                      </div>

                      <!-- Buttons List Form -->
                      <div v-else class="d-flex flex-column gap-1.5">
                        <div
                          v-for="(btn, index) in wa.form.buttons"
                          :key="index"
                          class="p-1.5 bg-light rounded border shadow-xs"
                        >
                          <div class="row g-1.5 align-items-center">
                            <!-- Type selector -->
                            <div class="col-md-3">
                              <select v-model="btn.type" class="form-select form-select-sm py-0.5">
                                <option value="QUICK_REPLY">Quick Reply</option>
                                <option value="URL">Website URL</option>
                                <option value="PHONE_NUMBER">Phone Number</option>
                              </select>
                            </div>

                            <!-- Button text -->
                            <div :class="btn.type === 'QUICK_REPLY' ? 'col-md-8' : 'col-md-4'">
                              <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white px-2 py-0.5">
                                  <i v-if="btn.type === 'QUICK_REPLY'" class="bi bi-reply-fill text-success"></i>
                                  <i v-else-if="btn.type === 'PHONE_NUMBER'" class="bi bi-telephone-fill text-success"></i>
                                  <i v-else class="bi bi-box-arrow-up-right text-primary"></i>
                                </span>
                                <input
                                  v-model="btn.text"
                                  type="text"
                                  class="form-control form-control-sm py-0.5"
                                  placeholder="Button Label"
                                  maxlength="25"
                                />
                              </div>
                            </div>

                            <!-- URL Input if type is URL -->
                            <div v-if="btn.type === 'URL'" class="col-md-4">
                              <input
                                v-model="btn.url"
                                type="url"
                                class="form-control form-control-sm py-0.5"
                                placeholder="https://example.com"
                              />
                            </div>

                            <!-- Phone Input if type is PHONE_NUMBER -->
                            <div v-if="btn.type === 'PHONE_NUMBER'" class="col-md-4">
                              <input
                                v-model="btn.phone_number"
                                type="tel"
                                class="form-control form-control-sm py-0.5"
                                placeholder="+27821234567"
                              />
                            </div>

                            <!-- Delete button -->
                            <div class="col-md-1 text-end">
                              <button
                                type="button"
                                class="btn btn-link btn-sm text-danger p-0 text-decoration-none"
                                @click="removeTemplateButton(index)"
                                title="Remove button"
                              >
                                <i class="bi bi-trash fs-6"></i>
                              </button>
                            </div>
                          </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-0.5">
                          <small class="text-muted" style="font-size: 0.72rem;">
                            Meta allows up to 25 chars per button. Live preview on phone.
                          </small>
                          <button
                            v-if="wa.form.buttons.length < 10"
                            type="button"
                            class="btn btn-link btn-sm p-0 text-decoration-none"
                            style="font-size: 0.74rem;"
                            @click="addTemplateButton('QUICK_REPLY')"
                          >
                            <i class="bi bi-plus-circle me-1"></i>Add another button
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- PREVIEW PANE -->
              <div class="col-lg-6 col-md-6 ps-lg-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="min-w-0 me-2">
                    <h6 class="fw-bold mb-0 text-truncate" style="max-width: 280px; font-size: 0.9rem;">
                      {{ wa.form.friendly_name || 'Template Preview' }}
                    </h6>
                    <small class="text-muted d-block" style="font-size: 0.72rem;">Preview of the message the client will receive</small>
                  </div>
                  <span :class="statusBadge(wa.form.status || 'APPROVED')" style="font-size: 0.72rem; padding: 0.25rem 0.5rem;">
                    {{ formatStatusText(wa.form.status || 'APPROVED') }}
                  </span>
                </div>

                <!-- WhatsApp Mobile Phone Preview (Matching Screenshot 2) -->
                <div class="whatsapp-phone-preview mb-2.5 shadow-sm">
                  <!-- Phone Status Bar -->
                  <div class="whatsapp-phone-statusbar d-flex align-items-center justify-content-between px-2.5">
                    <span class="fw-semibold">{{ templatePreviewTime }}</span>
                    <span class="d-flex align-items-center gap-1">
                      <i class="bi bi-reception-4"></i>
                      <i class="bi bi-wifi"></i>
                      <i class="bi bi-battery-full"></i>
                    </span>
                  </div>

                  <!-- WhatsApp Header Bar -->
                  <div class="whatsapp-phone-header d-flex align-items-center px-2 py-1.5">
                    <i class="bi bi-arrow-left text-white fs-6 me-1.5"></i>
                    <div class="whatsapp-contact-avatar rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                      <i class="bi bi-building text-white" style="font-size: 0.85rem;"></i>
                    </div>
                    <div class="min-w-0 ms-1.5 flex-grow-1">
                      <div class="text-white fw-semibold text-truncate whatsapp-contact-name">
                        {{ templatePreviewBusinessName }}
                        <i class="bi bi-patch-check-fill ms-0.5 whatsapp-verified-icon" title="Official business account"></i>
                      </div>
                      <div class="text-white-50 text-truncate whatsapp-contact-number">
                        {{ templatePreviewBusinessNumber }}
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-white ms-1.5 fs-6">
                      <i class="bi bi-camera-video"></i>
                      <i class="bi bi-telephone"></i>
                      <i class="bi bi-three-dots-vertical"></i>
                    </div>
                  </div>

                  <!-- Wallpaper with Doodle & Encrypted Note -->
                  <div class="whatsapp-chat-wallpaper position-relative p-2">
                    <div class="whatsapp-encryption-note mx-auto mb-2 px-2 py-1 text-center">
                      <i class="bi bi-lock-fill me-1"></i>Messages are end-to-end encrypted.
                    </div>
                    <div class="whatsapp-date-chip mx-auto mb-2 px-2 py-0.5 text-center">
                      TODAY
                    </div>

                    <!-- Message Bubble with Tail -->
                    <div class="whatsapp-template-message position-relative">
                      <svg viewBox="0 0 8 13" width="8" height="13" class="whatsapp-bubble-tail" aria-hidden="true">
                        <path fill="currentColor" d="M1.533 3.568 8 12.193V1H2.812C1.042 1 .474 2.156 1.533 3.568z"></path>
                      </svg>

                      <!-- Media Header Preview -->
                      <img
                        v-if="wa.form.header_format === 'IMAGE' && firstMediaUrl"
                        :src="firstMediaUrl"
                        class="w-100 rounded mb-1.5 template-header-image"
                        alt="Template header"
                      />
                      <video
                        v-else-if="wa.form.header_format === 'VIDEO' && firstMediaUrl"
                        :src="firstMediaUrl"
                        class="w-100 rounded mb-1.5 template-header-image"
                        controls
                        preload="metadata"
                      ></video>
                      <div v-else-if="wa.form.header_format === 'IMAGE'" class="whatsapp-document-preview rounded p-2 mb-1.5 text-center">
                        <i class="bi bi-image fs-4 d-block text-secondary"></i>
                        <small class="text-muted" style="font-size: 0.72rem;">Image header</small>
                      </div>
                      <div v-else-if="wa.form.header_format === 'DOCUMENT'" class="whatsapp-document-preview rounded p-2 mb-1.5 d-flex align-items-center gap-1.5">
                        <i class="bi bi-file-earmark-text-fill fs-4 text-secondary"></i>
                        <div class="min-w-0">
                          <div class="fw-semibold text-truncate" style="font-size: 0.78rem;">Template document</div>
                          <small class="text-muted" style="font-size: 0.7rem;">Document attachment</small>
                        </div>
                      </div>
                      <div v-else-if="wa.form.header_format && !['TEXT', 'IMAGE', 'DOCUMENT'].includes(wa.form.header_format)" class="whatsapp-document-preview rounded p-1.5 mb-1.5 small text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-paperclip me-1"></i>{{ wa.form.header_format }} header
                      </div>

                      <!-- Header Text -->
                      <div v-if="renderedTemplateHeader" class="fw-bold mb-1 template-message-text whatsapp-message-header" v-html="renderedTemplateHeader"></div>

                      <!-- Body Text (Formats WhatsApp markdown and replaces variables) -->
                      <div class="template-message-text whatsapp-message-body" style="white-space: pre-wrap;" v-html="renderedTemplateBody || 'No message preview is available.'"></div>

                      <!-- Footer & Time -->
                      <div class="d-flex align-items-end justify-content-between gap-2 mt-1">
                        <div v-if="wa.form.footer_text" class="text-muted whatsapp-message-footer">{{ wa.form.footer_text }}</div>
                        <span v-else></span>
                        <div class="text-muted whatsapp-message-time text-nowrap">{{ templatePreviewTime }}</div>
                      </div>

                      <!-- Interactive Action Buttons -->
                      <div v-if="wa.form.buttons?.length" class="whatsapp-template-buttons mt-1.5">
                        <div v-for="(button, index) in wa.form.buttons" :key="index" class="whatsapp-template-button text-center py-1.5">
                          <i v-if="String(button.type || '').toUpperCase() === 'QUICK_REPLY'" class="bi bi-reply-fill me-1"></i>
                          <i v-else-if="String(button.type || '').toUpperCase() === 'PHONE_NUMBER'" class="bi bi-telephone-fill me-1"></i>
                          <i v-else class="bi bi-box-arrow-up-right me-1"></i>
                          {{ button.text || button.type || 'Action' }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Template Values Card (Below Phone Preview, Matching Screenshot 2) -->
                <div class="card border rounded shadow-xs mb-1">
                  <div class="card-body p-2.5">
                    <div class="d-flex justify-content-between align-items-start mb-1.5 flex-wrap gap-1.5">
                      <div>
                        <h6 class="fw-bold mb-0" style="font-size: 0.85rem;">Template values</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.72rem;">Enter the values required for this client. The preview updates as you type.</p>
                      </div>
                      <div class="d-flex align-items-center gap-1 flex-wrap">
                        <button
                          type="button"
                          class="btn btn-outline-primary btn-sm py-0.5 px-1.5"
                          style="font-size: 0.72rem;"
                          @click="fillSampleValues"
                          title="Auto-fill realistic sample values"
                        >
                          <i class="bi bi-magic me-1"></i>Sample Values
                        </button>
                        <button
                          type="button"
                          class="btn btn-primary btn-sm py-0.5 px-1.5"
                          style="font-size: 0.72rem;"
                          @click="updatePreview"
                          title="Apply and preview variables"
                        >
                          <i class="bi bi-eye-fill me-1"></i>Preview
                        </button>
                        <button
                          v-if="hasPreviewVariablesSet"
                          type="button"
                          class="btn btn-outline-secondary btn-sm py-0.5 px-1.5"
                          style="font-size: 0.72rem;"
                          @click="clearPreviewVariables"
                          title="Clear all preview values"
                        >
                          <i class="bi bi-eraser me-1"></i>Clear
                        </button>
                        <button
                          type="button"
                          class="btn btn-outline-success btn-sm py-0.5 px-1.5"
                          style="font-size: 0.72rem;"
                          @click="toggleAddVariableInput"
                          title="Add a temporary preview variable"
                        >
                          <i class="bi bi-plus-lg me-1"></i>Add Variable
                        </button>
                      </div>
                    </div>

                    <!-- Inline Add Variable Form -->
                    <div v-if="wa.showAddVariable" class="card border-success border-opacity-50 bg-light my-2 p-2 shadow-xs">
                      <div class="small fw-bold text-success mb-1" style="font-size: 0.75rem;">
                        <i class="bi bi-plus-circle me-1"></i>Add Temporary Preview Variable
                      </div>
                      <div class="row g-1.5">
                        <div class="col-sm-5">
                          <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Variable Name</label>
                          <input
                            v-model.trim="wa.newVariableKey"
                            type="text"
                            class="form-control form-control-sm py-0.5"
                            placeholder="e.g. 1, 2, or custom_var"
                            @keydown.enter.prevent="addCustomVariable"
                          />
                        </div>
                        <div class="col-sm-5">
                          <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Sample Value</label>
                          <input
                            v-model="wa.newVariableValue"
                            type="text"
                            class="form-control form-control-sm py-0.5"
                            placeholder="e.g. John Doe"
                            @keydown.enter.prevent="addCustomVariable"
                          />
                        </div>
                        <div class="col-sm-2 d-flex align-items-end gap-1">
                          <button
                            type="button"
                            class="btn btn-success btn-sm py-0.5 flex-grow-1"
                            style="font-size: 0.75rem;"
                            @click="addCustomVariable"
                          >
                            Add
                          </button>
                          <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm py-0.5 px-2"
                            style="font-size: 0.75rem;"
                            @click="toggleAddVariableInput"
                          >
                            ✕
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- List of Template Variable Inputs -->
                    <div v-if="templateVariablesList.length" class="mt-2">
                      <div v-for="entryKey in templateVariablesList" :key="entryKey" class="mb-1.5 last-variable-field">
                        <div class="d-flex justify-content-between align-items-center mb-0.5">
                          <label class="form-label small fw-semibold mb-0" style="font-size: 0.75rem;">
                            {{ variableLabel(entryKey) }} <span class="text-danger">*</span>
                            <code class="text-muted small ms-1" style="font-size: 0.72rem;">&#123;&#123;{{ entryKey }}&#125;&#125;</code>
                          </label>
                          <button
                            v-if="wa.customVariables.includes(entryKey)"
                            type="button"
                            class="btn btn-link btn-sm text-danger p-0 text-decoration-none"
                            style="font-size: 0.72rem;"
                            @click="removeCustomVariable(entryKey)"
                            title="Remove temporary variable"
                          >
                            <i class="bi bi-x-circle me-1"></i>Remove
                          </button>
                        </div>
                        <input
                          v-model="wa.previewVariables[entryKey]"
                          type="text"
                          class="form-control form-control-sm py-0.5"
                          :placeholder="samplePlaceholder(entryKey)"
                          maxlength="1024"
                        />
                      </div>
                    </div>

                    <div v-else class="text-muted small py-2 px-2 text-center border rounded bg-light mt-1.5" style="font-size: 0.74rem;">
                      <i class="bi bi-info-circle me-1"></i>No variables found in this template. Click <strong>+ Add Variable</strong> above to test variables.
                    </div>
                  </div>
                </div>

              </div>

            </div>
          </div>
          <div class="modal-footer py-2 px-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" :disabled="wa.saving">Close</button>
            <button v-if="!wa.viewOnly" class="btn btn-sm btn-primary" @click="saveTemplate" :disabled="wa.saving || (!wa.form.sid && !!duplicateTemplateWarning)">
              <span v-if="wa.saving" class="spinner-border spinner-border-sm me-1"></span>
              {{ wa.form.sid ? 'Update Template' : 'Submit to Meta for Approval' }}
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
            <h5 class="modal-title">Onboard a WhatsApp Number</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info small">
              Submit a number to your Meta WhatsApp Business Account. The number must not already be registered with WhatsApp and must be able to receive an SMS or voice call.
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
              <label class="form-label">Business Display Name</label>
              <input v-model.trim="wn.addForm.verified_name" type="text" class="form-control" maxlength="255" placeholder="My Business Name" />
              <div class="form-text">Meta reviews this name. Approval can remain pending after the phone number has been submitted.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="wn.saving">Cancel</button>
            <button type="button" class="btn btn-primary" @click="submitAddNumber" :disabled="wn.saving || !wn.addForm.cc || !wn.addForm.phone_number">
              <span v-if="wn.saving" class="spinner-border spinner-border-sm me-1"></span>
              Submit to Meta
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- VERIFY & REGISTER NUMBER MODAL -->
    <div class="modal fade" tabindex="-1" ref="verifyNumberModalRef">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ wn.verifyForm.stage === 'register' ? 'Complete WhatsApp Registration' : 'Verify WhatsApp Number' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="wn.saving"></button>
          </div>
          <div class="modal-body">
            <p class="mb-3">Number: <strong>{{ wn.verifyForm.display_phone_number }}</strong></p>
            <template v-if="wn.verifyForm.stage === 'verify'">
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
              <div v-else>
                <div class="alert alert-success small">Code sent via {{ wn.verifyForm.method }}. Enter the six-digit code received by the number.</div>
                <div class="mb-3">
                  <label class="form-label">6-Digit Verification Code</label>
                  <input v-model.trim="wn.verifyForm.code" inputmode="numeric" autocomplete="one-time-code" type="text" class="form-control" placeholder="123456" maxlength="6" />
                </div>
              </div>
            </template>
            <template v-else>
              <div class="alert alert-warning small">Choose a six-digit two-step verification PIN. Store it securely; Meta may require it when this number is registered again.</div>
              <div class="mb-3">
                <label class="form-label">6-Digit PIN</label>
                <input v-model.trim="wn.verifyForm.pin" inputmode="numeric" autocomplete="new-password" type="password" class="form-control" maxlength="6" placeholder="Enter PIN" />
              </div>
              <div class="mb-3">
                <label class="form-label">Confirm PIN</label>
                <input v-model.trim="wn.verifyForm.pinConfirmation" inputmode="numeric" autocomplete="new-password" type="password" class="form-control" maxlength="6" placeholder="Confirm PIN" />
                <div v-if="wn.verifyForm.pinConfirmation && wn.verifyForm.pin !== wn.verifyForm.pinConfirmation" class="text-danger small mt-1">The PINs do not match.</div>
              </div>
            </template>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="wn.saving">Close</button>
            <button v-if="wn.verifyForm.stage === 'verify' && wn.verifyForm.codeSent" type="button" class="btn btn-success" @click="submitVerificationCode" :disabled="wn.saving || !/^\d{6}$/.test(wn.verifyForm.code)">
              <span v-if="wn.saving" class="spinner-border spinner-border-sm me-1"></span>
              Verify & Continue
            </button>
            <button v-if="wn.verifyForm.stage === 'register'" type="button" class="btn btn-success" @click="submitNumberRegistration" :disabled="wn.saving || !/^\d{6}$/.test(wn.verifyForm.pin) || wn.verifyForm.pin !== wn.verifyForm.pinConfirmation">
              <span v-if="wn.saving" class="spinner-border spinner-border-sm me-1"></span>
              Register on Cloud API
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
                <div class="col-md-6">
                  <label class="form-label">Profile Name *</label>
                  <input v-model="wp.form.name" type="text" class="form-control" placeholder="e.g. Iconis CRM" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Linked Bank / Institution</label>
                  <select v-model="wp.form.bank_id" class="form-select">
                    <option :value="null">-- None (Global / Shared) --</option>
                    <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                  </select>
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

    <!-- WhatsApp Template Review & Delay Info Modal -->
    <div class="modal fade" id="templateStatusInfoModal" tabindex="-1" ref="statusInfoModalRef">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" v-if="statusModal.template">
          <div class="modal-header border-bottom">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-info-circle-fill fs-5 text-primary"></i>
              <h5 class="modal-title mb-0">Template Review & Delay Status</h5>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <!-- Top Status Card -->
            <div class="card border-0 bg-light shadow-sm mb-3">
              <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                  <div>
                    <div class="text-muted small fw-semibold text-uppercase">Template Name</div>
                    <h6 class="mb-1 font-monospace fw-bold">{{ statusModal.template.name }}</h6>
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                      <span class="badge" :class="statusBadge(statusModal.template.status)">
                        <i v-if="isPendingStatus(statusModal.template.status)" class="bi bi-clock-history me-1"></i>
                        <i v-else-if="String(statusModal.template.status).toLowerCase() === 'approved'" class="bi bi-check-circle-fill me-1"></i>
                        <i v-else-if="String(statusModal.template.status).toLowerCase() === 'rejected'" class="bi bi-x-circle-fill me-1"></i>
                        Status: {{ statusModal.template.status || 'Unknown' }}
                      </span>
                      <span class="badge bg-secondary">Category: {{ (statusModal.template.category || 'N/A').toUpperCase() }}</span>
                      <span class="badge bg-secondary">Language: {{ statusModal.template.language || 'N/A' }}</span>
                      <span v-if="statusModal.template.quality_score" class="badge" :class="qualityRatingBadge(statusModal.template.quality_score)">
                        Quality: {{ statusModal.template.quality_score }}
                      </span>
                    </div>
                  </div>
                  <div class="d-flex flex-column align-items-end gap-2">
                    <button
                      type="button"
                      class="btn btn-sm btn-primary"
                      @click="checkSingleTemplateLiveStatus"
                      :disabled="statusModal.checkingLive"
                    >
                      <span v-if="statusModal.checkingLive" class="spinner-border spinner-border-sm me-1"></span>
                      <i v-else class="bi bi-arrow-repeat me-1"></i>
                      Check Live Status with Meta
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-secondary"
                      @click="openMetaManagerForTemplate(statusModal.template)"
                      title="Open Meta WhatsApp Manager"
                    >
                      <i class="bi bi-box-arrow-up-right me-1"></i> Open in WhatsApp Manager
                    </button>
                  </div>
                </div>

                <div v-if="statusModal.liveMessage" class="alert alert-success py-2 px-3 small mt-3 mb-0">
                  <i class="bi bi-check-circle me-1"></i> {{ statusModal.liveMessage }}
                </div>
                <div v-if="statusModal.liveError" class="alert alert-danger py-2 px-3 small mt-3 mb-0">
                  <i class="bi bi-exclamation-triangle me-1"></i> {{ statusModal.liveError }}
                </div>
              </div>
            </div>

            <!-- Review Delay SLA & Queue Information -->
            <div v-if="isPendingStatus(statusModal.template.status)" class="card border-warning mb-3">
              <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                  <i class="bi bi-hourglass-split me-1"></i> Review SLA & Delay Analysis
                </span>
                <span v-if="statusModal.template.synced_at" class="badge bg-warning text-dark">
                  Submitted / Checked {{ formatElapsedHours(statusModal.template.synced_at) }}
                </span>
              </div>
              <div class="card-body">
                <h6 class="fw-bold mb-2">Why is this template still showing PENDING?</h6>
                <p class="small text-muted mb-3">
                  Meta employs a two-tier review system for WhatsApp Business templates. While simple notification and OTP templates are processed in <strong>1 to 5 minutes</strong> by automated AI screening, templates containing specific content triggers are routed into Meta's <strong>Manual Human Compliance Queue</strong>.
                </p>

                <div class="row g-2 mb-3">
                  <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                      <div class="fw-semibold text-primary mb-1">
                        <i class="bi bi-robot me-1"></i> Automated Screening
                      </div>
                      <div class="small text-muted">
                        Takes <strong>1 – 5 minutes</strong>. Handles basic transactional alerts, OTP codes, and pre-approved standard utility notices.
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="p-3 border border-warning rounded bg-warning bg-opacity-10 h-100">
                      <div class="fw-semibold text-warning-emphasis mb-1">
                        <i class="bi bi-person-badge me-1"></i> Manual Human Review
                      </div>
                      <div class="small text-muted">
                        Takes <strong>24 – 48 business hours</strong>. Triggered when debt collection, legal actions, financial demands, or formatting patterns are detected. Excludes weekends and California PST holidays.
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Dynamic Keyword / Content Risk Scan -->
                <div v-if="detectedRisks.count > 0" class="alert alert-warning border-warning p-3 mb-3">
                  <div class="fw-semibold text-warning-emphasis mb-1">
                    <i class="bi bi-shield-exclamation me-1"></i> Detected Content Delay Triggers in this Template:
                  </div>
                  <div class="small text-muted mb-2">
                    The following keywords or formatting patterns in this template commonly trigger Meta's mandatory manual human review queue:
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <span v-for="(trigger, idx) in detectedRisks.triggers" :key="idx" class="badge bg-white text-danger border border-danger-subtle font-monospace py-1 px-2">
                      "{{ trigger.phrase }}" <small class="text-muted">({{ trigger.category }})</small>
                    </span>
                  </div>
                  <div class="small text-muted mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Meta flags phrases like legal action, debt collection, and consecutive variables to ensure full compliance with WhatsApp's Business Messaging Policies before approving delivery.
                  </div>
                </div>
                <div v-else class="alert alert-info border-info p-3 mb-3 small">
                  <i class="bi bi-info-circle me-1"></i>
                  No high-risk keywords detected. Meta also conducts randomized human quality audits on newly submitted templates or new business profiles, which take up to 24–48 business hours.
                </div>

                <!-- Recommendations -->
                <div class="border rounded p-3 bg-light small">
                  <div class="fw-semibold mb-1">What you can do:</div>
                  <ul class="mb-0 ps-3 text-muted">
                    <li><strong>Wait for the 48 business hours window:</strong> If submitted less than 48 hours ago (or over a weekend), Meta's human review team is likely still processing the queue.</li>
                    <li><strong>Click "Check Live Status with Meta":</strong> The button above polls Meta's server directly to catch decisions the moment Meta finishes review.</li>
                    <li><strong>If urgently needed:</strong> You can create and submit a variation with softer phrasing (e.g., removing words like "legal action" or "halt the process") for faster automated approval.</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Approved State Info -->
            <div v-else-if="String(statusModal.template.status).toLowerCase() === 'approved'" class="alert alert-success d-flex align-items-start gap-3 mb-3">
              <i class="bi bi-check-circle-fill fs-3 text-success"></i>
              <div>
                <h6 class="fw-bold mb-1">Template Approved & Active on WhatsApp</h6>
                <div class="small text-muted">
                  Meta has approved this template. It is fully certified for outbound WhatsApp broadcast campaigns and live agent chat messaging.
                </div>
              </div>
            </div>

            <!-- Rejected State Info -->
            <div v-else-if="String(statusModal.template.status).toLowerCase() === 'rejected'" class="alert alert-danger mb-3">
              <div class="d-flex align-items-start gap-3">
                <i class="bi bi-x-circle-fill fs-3 text-danger"></i>
                <div class="w-100">
                  <h6 class="fw-bold mb-1">Template Rejected by Meta</h6>
                  <div class="small mb-2">
                    Meta's reviewers rejected this template. Common reasons include non-compliant promotional content in utility templates, aggressive collection language, or formatting issues.
                  </div>
                  <div v-if="statusModal.template.rejected_reason" class="p-2 bg-white rounded border border-danger-subtle font-monospace small mb-2">
                    <strong>Rejection Code:</strong> {{ statusModal.template.rejected_reason }}
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="handleEditFromStatusModal(statusModal.template)">
                    <i class="bi bi-pencil-square me-1"></i> Edit & Resubmit Template
                  </button>
                </div>
              </div>
            </div>

            <!-- Template Content Preview -->
            <div class="card border-0 bg-light">
              <div class="card-header bg-white border-bottom fw-semibold small text-muted">
                <i class="bi bi-chat-left-text me-1"></i> Submitted Message Body Preview
              </div>
              <div class="card-body">
                <div class="p-3 bg-white rounded border font-monospace small" style="white-space: pre-wrap;">{{ statusModal.template.body_preview || 'No preview available' }}</div>
              </div>
            </div>

          </div>
          <div class="modal-footer border-top">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button
              type="button"
              class="btn btn-primary"
              @click="checkSingleTemplateLiveStatus"
              :disabled="statusModal.checkingLive"
            >
              <span v-if="statusModal.checkingLive" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-arrow-repeat me-1"></i> Check Live Status Now
            </button>
          </div>
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
      banks: [],
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
          disable_chat_for_opted_out_clients: true,
          opted_out_chat_message: 'This client has opted out of WhatsApp communication. Messaging is disabled.',
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
          bank_id: null,
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
        pendingRequesting: false,
        selectedPendingIds: [],
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
          stage: 'verify',
          pin: '',
          pinConfirmation: '',
        },
        addNumberModal: null,
        verifyNumberModal: null,
      },
      wa: {
        templates: [],
        selected: [],
        loading: false,
        syncPollTimer: null,
        exporting: false,
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
        approvalView: 'all',
        filters: {
          search: '',
          status: '',
          category: '',
          language: '',
        },
        availableStatuses: [],
        availableCategories: [],
        availableLanguages: [],
        previewVariables: {},
        customVariables: [],
        showAddVariable: false,
        newVariableKey: '',
        newVariableValue: '',
        form: {
          sid: null,
          friendly_name: '',
          body: '',
          language: 'en_US',
          category: 'utility',
          status: 'APPROVED',
          media_urls: '',
          header_format: '',
          header_text: '',
          footer_text: '',
          buttons: [],
          variables: {},
          body_examples: {},
        },
      },
      templateModal: null,
      statusModal: {
        template: null,
        checkingLive: false,
        liveMessage: null,
        liveError: null,
      },
      statusInfoModal: null,
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
      this.statusInfoModal = createManagedModal(this.$refs.statusInfoModalRef);
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
      this.fetchBanks();
    },
  beforeUnmount() {
    window.removeEventListener('auth-user-updated', this.handleAuthUserUpdated);
    disposeManagedModal(this.templateModal);
    disposeManagedModal(this.statusInfoModal);
    disposeManagedModal(this.wn.addNumberModal);
    disposeManagedModal(this.wn.verifyNumberModal);
    disposeManagedModal(this.wp.modal);
    disposeManagedModal(this.wa.migrateModal);
    if (this.wa.syncPollTimer) {
      window.clearTimeout(this.wa.syncPollTimer);
    }
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
    '$route.query.tab': {
      handler() {
        this.checkActiveTab();
      },
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
    pendingWhatsappNumbers() {
      return this.wn.numbers.filter((number) => {
        const verification = String(number.code_verification_status || '').toUpperCase();
        const nameStatus = String(number.name_status || '').toUpperCase();
        const platform = String(number.platform_type || '').toUpperCase();

        return verification !== 'VERIFIED' || nameStatus !== 'APPROVED' || platform !== 'CLOUD_API';
      });
    },
    allPendingNumbersSelected() {
      return this.pendingWhatsappNumbers.length > 0
        && this.pendingWhatsappNumbers.every((number) => this.wn.selectedPendingIds.includes(String(number.id)));
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
    templateBodyVariableIndexes() {
      const matches = [...String(this.wa.form.body || '').matchAll(/{{(\d+)}}/g)];
      return [...new Set(matches.map((match) => Number(match[1])))]
        .filter((index) => Number.isInteger(index) && index > 0)
        .sort((a, b) => a - b);
    },
    duplicateTemplateWarning() {
      if (this.wa.viewOnly || this.wa.form.sid || !this.wa.form.friendly_name) return '';
      const normalized = String(this.wa.form.friendly_name)
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '');
      if (!normalized) return '';
      const match = this.wa.templates.find(
        (t) => (t.name || '').toLowerCase() === normalized || (t.sid || '').toLowerCase() === normalized
      );
      if (match) {
        return `A template named "${normalized}" already exists in your templates list (status: ${match.status || 'unknown'}).`;
      }
      return '';
    },
    firstMediaUrl() {
      const urls = (this.wa.form.media_urls || '')
        .split(',')
        .map((u) => u.trim())
        .filter(Boolean);
      return urls.length ? urls[0] : null;
    },
    templateVariablesList() {
      const rawList = [];

      // 1. From body text placeholders: {{1}}, {{body_1}}, etc.
      const bodyMatches = String(this.wa.form.body || '').match(/{{([^}]+)}}/g) || [];
      bodyMatches.forEach((m) => {
        const key = m.replace(/^{{\s*|\s*}}$/g, '').trim();
        if (key && !rawList.includes(key)) rawList.push(key);
      });

      // 2. From header text placeholders
      const headerMatches = String(this.wa.form.header_text || '').match(/{{([^}]+)}}/g) || [];
      headerMatches.forEach((m) => {
        const key = m.replace(/^{{\s*|\s*}}$/g, '').trim();
        if (key && !rawList.includes(key)) rawList.push(key);
      });

      // 3. From wa.form.variables (if defined in template)
      if (this.wa.form.variables) {
        if (Array.isArray(this.wa.form.variables)) {
          this.wa.form.variables.forEach((k) => {
            const str = String(k || '').trim();
            if (str && !rawList.includes(str)) rawList.push(str);
          });
        } else if (typeof this.wa.form.variables === 'object') {
          Object.keys(this.wa.form.variables).forEach((k) => {
            const str = String(k || '').trim();
            if (str && !rawList.includes(str)) rawList.push(str);
          });
        }
      }

      // 4. From custom added variables
      if (Array.isArray(this.wa.customVariables)) {
        this.wa.customVariables.forEach((k) => {
          const str = String(k || '').trim();
          if (str && !rawList.includes(str)) rawList.push(str);
        });
      }

      // Deduplicate aliases: if both '1' and 'body_1' exist, prefer '1'
      const canonical = [];
      rawList.forEach((key) => {
        const bodyMatch = key.match(/^body_(\d+)$/i);
        if (bodyMatch) {
          const num = bodyMatch[1];
          if (canonical.includes(num) || rawList.includes(num)) {
            return;
          }
        }
        const headerMatch = key.match(/^header_(\d+)$/i);
        if (headerMatch) {
          const num = headerMatch[1];
          if (canonical.includes(num) || rawList.includes(num)) {
            return;
          }
        }
        if (!canonical.includes(key)) {
          canonical.push(key);
        }
      });

      return canonical.sort((a, b) => {
        const numA = parseInt(String(a).replace(/\D/g, ''), 10);
        const numB = parseInt(String(b).replace(/\D/g, ''), 10);
        if (!isNaN(numA) && !isNaN(numB) && numA !== numB) {
          return numA - numB;
        }
        return String(a).localeCompare(String(b));
      });
    },
    renderedTemplateHeader() {
      const text = this.wa.form.header_text;
      if (!text) return '';
      return this.formatWhatsappMessage(text);
    },
    renderedTemplateBody() {
      const text = this.wa.form.body || 'Template message body will appear here.';
      return this.formatWhatsappMessage(text);
    },
    templatePreviewBusinessName() {
      return this.meta?.phone_profile?.verified_name
        || this.meta?.form?.meta_whatsapp_display_name
        || this.system?.form?.company_name
        || this.system?.form?.app_name
        || 'Capfin StraussDaly Real';
    },
    templatePreviewBusinessNumber() {
      return this.meta?.phone_profile?.display_phone_number
        || this.meta?.form?.meta_whatsapp_display_phone_number
        || '+27 76 022 8742';
    },
    templatePreviewTime() {
      return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
    hasPreviewVariablesSet() {
      return Object.values(this.wa.previewVariables || {}).some((v) => String(v).trim() !== '');
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
    templateStatusCounts() {
      const counts = { all: this.wa.templates.length, pending: 0, approved: 0, rejected: 0, attention: 0 };
      this.wa.templates.forEach((template) => {
        const status = String(template.status || '').toLowerCase();
        if (['pending', 'in_review', 'in_appeal'].includes(status)) counts.pending += 1;
        else if (status === 'approved') counts.approved += 1;
        else if (status === 'rejected') counts.rejected += 1;
        else counts.attention += 1;
      });
      return counts;
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

        const status = String(template.status || '').toLowerCase();
        const matchesApprovalView = this.wa.approvalView === 'all'
          || (this.wa.approvalView === 'pending' && ['pending', 'in_review', 'in_appeal'].includes(status))
          || (this.wa.approvalView === 'approved' && status === 'approved')
          || (this.wa.approvalView === 'rejected' && status === 'rejected')
          || (this.wa.approvalView === 'attention' && !['pending', 'in_review', 'in_appeal', 'approved', 'rejected'].includes(status));
        const matchesStatus = !this.wa.filters.status || status === this.wa.filters.status.toLowerCase();
        const matchesCategory = !this.wa.filters.category || (template.category || '').toLowerCase() === this.wa.filters.category.toLowerCase();
        const matchesLanguage = !this.wa.filters.language || (template.language || '').toLowerCase() === this.wa.filters.language.toLowerCase();

        return matchesSearch && matchesApprovalView && matchesStatus && matchesCategory && matchesLanguage;
      });
    },
    detectedRisks() {
      const body = this.statusModal.template?.body_preview || '';
      return this.scanTemplateContentRisks(body);
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
      const queryTab = this.$route?.query?.tab;
      if (queryTab === 'whatsapp-templates' && this.canAccessWabaTemplates) {
        this.activeMainTab = 'whatsapp-templates';
        return;
      }
      if (queryTab === 'whatsapp-numbers' && this.canAccessWabaNumbers) {
        this.activeMainTab = 'whatsapp-numbers';
        return;
      }
      if (queryTab === 'whatsapp-profiles' && this.canAccessWabaProfiles) {
        this.activeMainTab = 'whatsapp-profiles';
        return;
      }
      if (queryTab === 'meta' && this.canAccessMetaWhatsapp) {
        this.activeMainTab = 'meta';
        return;
      }
      if (queryTab === 'system' && this.canAccessSystem) {
        this.activeMainTab = 'system';
        return;
      }
      if (queryTab === 'account' && this.canAccessUserAccount) {
        this.activeMainTab = 'account';
        return;
      }

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
        disable_chat_for_opted_out_clients: settings.disable_chat_for_opted_out_clients !== undefined ? !!settings.disable_chat_for_opted_out_clients : true,
        opted_out_chat_message: settings.opted_out_chat_message || 'This client has opted out of WhatsApp communication. Messaging is disabled.',
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
      payload.append('live_chat_locked', this.system.form.live_chat_locked ? '1' : '0');
      payload.append('live_chat_locked_message', this.system.form.live_chat_locked_message || '');
      payload.append('disable_chat_for_opted_out_clients', this.system.form.disable_chat_for_opted_out_clients ? '1' : '0');
      payload.append('opted_out_chat_message', this.system.form.opted_out_chat_message || '');
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

    setTemplateApprovalView(view) {
      this.wa.approvalView = view;
      this.wa.filters.status = '';
      this.wa.selected = [];
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
      if (this.wa.syncPollTimer) {
        window.clearTimeout(this.wa.syncPollTimer);
        this.wa.syncPollTimer = null;
      }
      axios
        .post('/api/whatsapp-templates/sync')
        .then(() => {
          notify.info('Template sync started in the background.', 'WhatsApp Templates');
          this.pollWhatsappTemplateSync(0);
        })
        .catch((err) => {
          notify.error(err.response?.data?.message || 'Failed to start the Meta template sync.', 'WhatsApp Templates');
          this.wa.loading = false;
        });
    },
    pollWhatsappTemplateSync(attempt = 0) {
      axios
        .get('/api/whatsapp-templates/sync-status')
        .then((res) => {
          const status = res.data || {};
          if (status.state === 'completed') {
            notify.success('Synced ' + (status.count || 0) + ' templates from Meta.', 'WhatsApp Templates');
            this.wa.syncPollTimer = null;
            this.loadWhatsappTemplates();
            return;
          }
          if (status.state === 'failed') {
            notify.error(status.message || 'Failed to sync templates from Meta.', 'WhatsApp Templates');
            this.wa.syncPollTimer = null;
            this.wa.loading = false;
            return;
          }
          if (attempt >= 90) {
            notify.info('The template sync is still running. You can leave this page and check again shortly.', 'WhatsApp Templates');
            this.wa.syncPollTimer = null;
            this.wa.loading = false;
            return;
          }
          this.wa.syncPollTimer = window.setTimeout(() => this.pollWhatsappTemplateSync(attempt + 1), 2000);
        })
        .catch((err) => {
          notify.error(err.response?.data?.message || 'Unable to check template sync status.', 'WhatsApp Templates');
          this.wa.syncPollTimer = null;
          this.wa.loading = false;
        });
    },
    exportWhatsappTemplates() {
      this.wa.exporting = true;
      axios
        .get('/api/whatsapp-templates/export', { responseType: 'blob' })
        .then((res) => {
          const blob = new Blob([res.data], {
            type: res.headers['content-type'] || 'application/vnd.ms-excel',
          });
          const disposition = res.headers['content-disposition'] || '';
          const fileNameMatch = disposition.match(/filename="?([^"]+)"?/i);
          const fileName = fileNameMatch?.[1] || `waba_templates_${new Date().toISOString().slice(0, 10)}.xls`;
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement('a');

          link.href = url;
          link.download = fileName;
          document.body.appendChild(link);
          link.click();
          link.remove();
          window.URL.revokeObjectURL(url);

          notify.success('WABA templates export downloaded.', 'WhatsApp Templates');
        })
        .catch((err) => {
          notify.error(err.response?.data?.message || 'Failed to export WABA templates.', 'WhatsApp Templates');
        })
        .finally(() => {
          this.wa.exporting = false;
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
        this.wa.selected = this.filteredWhatsappTemplates.map(t => t.sid);
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
      this.wa.approvalView = 'all';
      this.wa.selected = [];
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
        bank_id: null,
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
        bank_id: profile.bank_id || (profile.bank ? profile.bank.id : null),
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
    fetchBanks() {
      axios.get('/api/banks', { params: { per_page: 200 } })
        .then(res => {
          this.banks = res.data.data || res.data || [];
        })
        .catch(err => {
          console.error('Failed to load banks for settings:', err);
        });
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
      this.wa.previewVariables = {};
      this.wa.customVariables = [];
      this.wa.showAddVariable = false;
      this.wa.newVariableKey = '';
      this.wa.newVariableValue = '';
      if (this.templateModal) {
        this.templateModal.show();
      }
    },
    viewTemplate(t) {
      this.wa.viewOnly = true;
      this.wa.viewingSid = t.sid;
      this.wa.previewVariables = {};
      this.wa.customVariables = [];
      this.wa.showAddVariable = false;
      this.wa.newVariableKey = '';
      this.wa.newVariableValue = '';
      this.wa.saving = true;
      axios
        .get(`/api/whatsapp-templates/${encodeURIComponent(t.sid)}`)
        .then((res) => {
          const template = res.data?.template || {};
          const bodyExamples = this.extractTemplateBodyExamples(template)
            || this.extractTemplateBodyExamples(t)
            || {};

          this.wa.form = {
            sid: template.id || t.sid,
            friendly_name: template.name || t.name,
            body: template.preview || template.body_preview || t.body_preview || '',
            language: template.language || t.language || 'en',
            category: (template.category || t.category || 'utility').toLowerCase(),
            status: template.status || t.status || 'APPROVED',
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
            body_examples: bodyExamples,
          };

          if (bodyExamples && Object.keys(bodyExamples).length > 0) {
            this.wa.previewVariables = { ...bodyExamples };
          }

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
      this.wa.previewVariables = {};
      this.wa.customVariables = [];
      this.wa.showAddVariable = false;
      this.wa.newVariableKey = '';
      this.wa.newVariableValue = '';
      const bodyExamples = this.extractTemplateBodyExamples(t) || {};
      this.wa.form = {
        sid: t.sid,
        friendly_name: t.name,
        body: t.body_preview || '',
        language: t.language || 'en',
        category: (t.category || 'utility').toLowerCase(),
        status: t.status || 'APPROVED',
        media_urls: (t.media_urls || []).join(','),
        header_format: t.header_format || '',
        header_text: t.header_text || '',
        footer_text: t.footer_text || '',
        buttons: Array.isArray(t.buttons) ? t.buttons : [],
        variables: t.variables || {},
        body_examples: bodyExamples,
      };
      if (bodyExamples && Object.keys(bodyExamples).length > 0) {
        this.wa.previewVariables = { ...bodyExamples };
      }
      if (this.templateModal) {
        this.templateModal.show();
      }
    },
    templateVariablePlaceholder(index) {
      return '{{' + index + '}}';
    },
    extractTemplateBodyExamples(template) {
      if (!template) return {};
      const components = template.components || template.raw_whatsapp?.components || [];
      const bodyComponent = components.find((component) => String(component.type || '').toUpperCase() === 'BODY');
      const values = bodyComponent?.example?.body_text?.[0] || [];
      return values.reduce((examples, value, index) => {
        examples[index + 1] = String(value);
        return examples;
      }, {});
    },
    resetForm() {
      this.wa.previewVariables = {};
      this.wa.customVariables = [];
      this.wa.showAddVariable = false;
      this.wa.newVariableKey = '';
      this.wa.newVariableValue = '';
      this.wa.form = {
        sid: null,
        friendly_name: '',
        body: '',
        language: 'en_US',
        category: 'utility',
        status: 'APPROVED',
        media_urls: '',
        header_format: '',
        header_text: '',
        footer_text: '',
        buttons: [],
        variables: {},
        body_examples: {},
      };
    },
    formatStatusText(status) {
      if (!status) return 'Approved';
      const s = String(status).toLowerCase();
      if (s === 'approved') return 'Approved';
      if (s === 'in_review') return 'In Review';
      if (s === 'in_appeal') return 'In Appeal';
      if (s === 'pending') return 'Pending';
      if (s === 'rejected') return 'Rejected';
      return status.charAt(0).toUpperCase() + status.slice(1);
    },
    variableLabel(key) {
      const bodyMatch = String(key).match(/^(?:body_)?(\d+)$/i);
      if (bodyMatch) {
        return `Body Variable ${bodyMatch[1]}`;
      }
      const headerMatch = String(key).match(/^header_(\d+)$/i);
      if (headerMatch) {
        return `Header Variable ${headerMatch[1]}`;
      }
      return `Variable {{${key}}}`;
    },
    resolveVariableValue(key) {
      const strKey = String(key).trim();
      if (this.wa.previewVariables[strKey] !== undefined && String(this.wa.previewVariables[strKey]).trim() !== '') {
        return this.wa.previewVariables[strKey];
      }
      if (/^\d+$/.test(strKey)) {
        if (this.wa.previewVariables[`body_${strKey}`] !== undefined && String(this.wa.previewVariables[`body_${strKey}`]).trim() !== '') {
          return this.wa.previewVariables[`body_${strKey}`];
        }
        if (this.wa.previewVariables[`header_${strKey}`] !== undefined && String(this.wa.previewVariables[`header_${strKey}`]).trim() !== '') {
          return this.wa.previewVariables[`header_${strKey}`];
        }
      }
      const match = strKey.match(/^(?:body|header)_(\d+)$/i);
      if (match && this.wa.previewVariables[match[1]] !== undefined && String(this.wa.previewVariables[match[1]]).trim() !== '') {
        return this.wa.previewVariables[match[1]];
      }
      return null;
    },
    samplePlaceholder(key) {
      const lower = String(key).toLowerCase();
      if (lower.includes('name') || lower === '1' || lower === 'body_1') return 'e.g. John Doe';
      if (lower.includes('amount') || lower.includes('balance') || lower === '2' || lower === 'body_2') return 'e.g. 1,500.00';
      if (lower.includes('date') || lower === '3' || lower === 'body_3') return 'e.g. 30 September 2026';
      if (lower.includes('account') || lower.includes('bank') || lower === '4' || lower === 'body_4') return 'e.g. FINCHOICE';
      return `Value for {{${key}}}`;
    },
    fillSampleValues() {
      const updated = { ...this.wa.previewVariables };
      this.templateVariablesList.forEach((key, idx) => {
        if (this.wa.form.body_examples && this.wa.form.body_examples[key]) {
          updated[key] = this.wa.form.body_examples[key];
          return;
        }
        const numMatch = String(key).match(/\d+/);
        const index = numMatch ? parseInt(numMatch[0], 10) : idx + 1;
        if (index === 1) updated[key] = 'John Doe';
        else if (index === 2) updated[key] = '1,500.00';
        else if (index === 3) updated[key] = '30 September 2026';
        else if (index === 4) updated[key] = 'FINCHOICE';
        else if (index === 5) updated[key] = '4107204509';
        else updated[key] = `Sample ${index}`;
      });
      this.wa.previewVariables = updated;
      notify.success('Sample values loaded for template preview.', 'Template Preview');
    },
    clearPreviewVariables() {
      const cleared = {};
      this.templateVariablesList.forEach((key) => {
        cleared[key] = '';
      });
      this.wa.previewVariables = cleared;
      notify.info('Template preview values cleared.', 'Template Preview');
    },
    toggleAddVariableInput() {
      this.wa.showAddVariable = !this.wa.showAddVariable;
      if (this.wa.showAddVariable) {
        this.wa.newVariableKey = '';
        this.wa.newVariableValue = '';
      }
    },
    addCustomVariable() {
      const key = String(this.wa.newVariableKey || '').trim().replace(/[{}]/g, '');
      if (!key) {
        notify.warning('Please enter a variable name.', 'Template Preview');
        return;
      }
      if (!this.wa.customVariables.includes(key)) {
        this.wa.customVariables.push(key);
      }
      if (this.wa.newVariableValue) {
        this.wa.previewVariables[key] = this.wa.newVariableValue;
      } else if (this.wa.previewVariables[key] === undefined) {
        this.wa.previewVariables[key] = '';
      }
      this.wa.newVariableKey = '';
      this.wa.newVariableValue = '';
      this.wa.showAddVariable = false;
      notify.success(`Variable {{${key}}} added to preview.`, 'Template Preview');
    },
    removeCustomVariable(key) {
      this.wa.customVariables = this.wa.customVariables.filter((k) => k !== key);
      const updated = { ...this.wa.previewVariables };
      delete updated[key];
      this.wa.previewVariables = updated;
      notify.info(`Variable {{${key}}} removed from preview.`, 'Template Preview');
    },
    updatePreview() {
      notify.info('Template preview updated with current variable values.', 'Template Preview');
    },
    formatWhatsappMessage(text) {
      if (!text) return '';
      let formatted = this.escapeHtml(text);

      formatted = formatted.replace(/{{([^}]+)}}/g, (match, rawKey) => {
        const key = rawKey.trim();
        const value = this.resolveVariableValue(key);
        if (value !== null && value !== undefined && String(value).trim() !== '') {
          return this.escapeHtml(value);
        }
        return `{{${key}}}`;
      });

      formatted = formatted.replace(/\*([^*\n\r]+)\*/g, '<strong>$1</strong>');
      formatted = formatted.replace(/_([^_\n\r]+)_/g, '<em>$1</em>');
      formatted = formatted.replace(/~([^~\n\r]+)~/g, '<del>$1</del>');
      formatted = formatted.replace(/```([^`]+)```/g, '<code>$1</code>');

      return formatted;
    },
    escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    },
    addTemplateButton(type = 'QUICK_REPLY') {
      if (!Array.isArray(this.wa.form.buttons)) {
        this.wa.form.buttons = [];
      }
      if (this.wa.form.buttons.length >= 10) {
        notify.warning('Meta allows a maximum of 10 buttons.', 'Settings');
        return;
      }
      let defaultText = 'Quick Reply';
      let url = '';
      let phoneNumber = '';
      if (type === 'URL') {
        defaultText = 'Visit Website';
        url = 'https://';
      } else if (type === 'PHONE_NUMBER') {
        defaultText = 'Call Us';
        phoneNumber = '+27';
      }
      this.wa.form.buttons.push({
        type,
        text: defaultText,
        url,
        phone_number: phoneNumber,
      });
    },
    addTemplatePresetButton(text, type, value = '') {
      if (!Array.isArray(this.wa.form.buttons)) {
        this.wa.form.buttons = [];
      }
      if (this.wa.form.buttons.length >= 10) {
        notify.warning('Meta allows a maximum of 10 buttons.', 'Settings');
        return;
      }
      this.wa.form.buttons.push({
        type,
        text,
        url: type === 'URL' ? value : '',
        phone_number: type === 'PHONE_NUMBER' ? value : '',
      });
    },
    removeTemplateButton(index) {
      if (Array.isArray(this.wa.form.buttons)) {
        this.wa.form.buttons.splice(index, 1);
      }
    },
    saveTemplate() {
      if (!this.wa.form.friendly_name || !this.wa.form.body) {
        notify.warning('Please provide a friendly name and body.', 'Settings');
        return;
      }

      const normalizedName = String(this.wa.form.friendly_name)
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '');

      if (!normalizedName) {
        notify.warning('Please provide a valid friendly name (lowercase letters, numbers, underscores).', 'Settings');
        return;
      }

      if (!this.wa.form.sid) {
        const existing = this.wa.templates.find(
          (t) => (t.name || '').toLowerCase() === normalizedName || (t.sid || '').toLowerCase() === normalizedName
        );
        if (existing) {
          notify.warning(`A template named "${normalizedName}" already exists (status: ${existing.status || 'unknown'}). Please choose a unique name or edit the existing template.`, 'Settings');
          return;
        }
      }

      const indexes = this.templateBodyVariableIndexes;
      if (indexes.length > 0) {
        const maxIndex = Math.max(...indexes);
        if (indexes[0] !== 1 || indexes.length !== maxIndex) {
          notify.warning('Template variables must start at {{1}} and be sequential (e.g. {{1}}, {{2}}, {{3}}) without skipping numbers.', 'Settings');
          return;
        }
      }

      const bodyExamples = indexes.map((index) => this.wa.form.body_examples[index] || '');
      if (bodyExamples.some((example) => !example.trim())) {
        notify.warning('Provide a realistic example value for every template variable.', 'Settings');
        return;
      }

      const buttons = [];
      if (Array.isArray(this.wa.form.buttons)) {
        for (const btn of this.wa.form.buttons) {
          const text = String(btn.text || '').trim();
          if (!text) continue;
          if (text.length > 25) {
            notify.warning(`Button text "${text}" exceeds Meta maximum limit of 25 characters.`, 'Settings');
            return;
          }
          const type = String(btn.type || 'QUICK_REPLY').toUpperCase();
          const item = {
            type,
            text,
          };
          if (type === 'URL') {
            const url = String(btn.url || '').trim();
            if (!url || !/^https?:\/\//i.test(url)) {
              notify.warning(`Please provide a valid website address (starting with https:// or http://) for button "${text}".`, 'Settings');
              return;
            }
            item.url = url;
          } else if (type === 'PHONE_NUMBER') {
            const phone = String(btn.phone_number || '').trim();
            if (!phone) {
              notify.warning(`Please enter a phone number with country code for button "${text}".`, 'Settings');
              return;
            }
            item.phone_number = phone;
          }
          buttons.push(item);
        }
      }

      const payload = {
        friendly_name: normalizedName,
        body: this.wa.form.body,
        language: this.wa.form.language,
        category: this.wa.form.category,
        media_urls: [],
        body_examples: bodyExamples,
        buttons: buttons,
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
          const message = err.response?.data?.message || err.message;
          notify.error('Failed to save template: ' + message, 'Settings');
          if (err.response?.status === 409) {
            this.loadWhatsappTemplates();
            this.templateModal?.hide();
          }
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
    toggleAllPendingNumbers(event) {
      this.wn.selectedPendingIds = event.target.checked
        ? this.pendingWhatsappNumbers.map((number) => String(number.id))
        : [];
    },
    async requestPendingVerification(num, method, openCodeEntry = false) {
      this.wn.pendingRequesting = true;
      try {
        await axios.post('/api/settings/meta/phone-numbers/request-verification', {
          phone_number_id: num.id,
          method,
        });
        notify.success(`Verification code requested by ${method === 'VOICE' ? 'voice call' : 'SMS'} for ${num.display_phone_number || num.id}.`, 'Settings');
        if (openCodeEntry) {
          this.openPendingCodeEntry(num, method);
        }
        return true;
      } catch (err) {
        notify.error(`Could not request a code for ${num.display_phone_number || num.id}: ${err.response?.data?.message || err.message}`, 'Settings');
        return false;
      } finally {
        this.wn.pendingRequesting = false;
      }
    },
    async requestSelectedVerification(method) {
      const selected = this.pendingWhatsappNumbers.filter((number) =>
        this.wn.selectedPendingIds.includes(String(number.id))
        && String(number.code_verification_status || '').toUpperCase() !== 'VERIFIED'
      );
      if (!selected.length) {
        notify.warning('Select at least one unverified number.', 'Settings');
        return;
      }

      let requested = 0;
      for (const number of selected) {
        if (await this.requestPendingVerification(number, method, false)) {
          requested += 1;
        }
      }
      if (requested > 0) {
        notify.success(`${requested} verification code request(s) submitted. Use Enter Code beside each number to finish verification.`, 'Settings');
      }
    },
    openPendingCodeEntry(num, method = 'SMS') {
      this.wn.verifyForm = {
        id: num.id,
        display_phone_number: num.display_phone_number || num.id,
        method,
        code: '',
        codeSent: true,
        stage: 'verify',
        pin: '',
        pinConfirmation: '',
      };
      this.wn.verifyNumberModal?.show();
    },
    openMetaNumberManager(num, action) {
      const wabaId = this.meta.form.meta_whatsapp_business_account_id;
      const query = new URLSearchParams({
        asset_id: wabaId || '',
        business_id: wabaId || '',
        tab: 'phone-numbers',
      });
      window.open(`https://business.facebook.com/latest/whatsapp_manager/phone_numbers/?${query.toString()}`, '_blank', 'noopener,noreferrer');
      notify.info(
        `Meta requires ${action === 'remove' ? 'number removal' : 'display-name editing'} to be completed in WhatsApp Manager.`,
        'Settings'
      );
    },
    openAddNumberModal() {
      this.wn.addForm = { cc: '', phone_number: '', verified_name: '' };
      this.wn.addNumberModal?.show();
    },
    async submitAddNumber() {
      this.wn.saving = true;
      try {
        const { data } = await axios.post('/api/settings/meta/phone-numbers', {
          cc: String(this.wn.addForm.cc || '').replace(/\D/g, ''),
          phone_number: String(this.wn.addForm.phone_number || '').replace(/\D/g, ''),
          verified_name: this.wn.addForm.verified_name || null,
        });
        notify.success('Number submitted to Meta. Verify ownership to continue onboarding.', 'Settings');
        this.wn.addNumberModal?.hide();
        await this.fetchWhatsappNumbers();

        const createdId = data?.data?.id || data?.data?.phone_number_id;
        if (createdId) {
          const addedNumber = this.wn.numbers.find((number) => String(number.id) === String(createdId));
          window.setTimeout(() => this.openVerifyNumberModal(addedNumber || {
            id: createdId,
            display_phone_number: `+${this.wn.addForm.cc}${this.wn.addForm.phone_number}`,
          }), 250);
        }
      } catch (err) {
        notify.error('Failed to submit phone number: ' + (err.response?.data?.message || err.message), 'Settings');
      } finally {
        this.wn.saving = false;
      }
    },
    openVerifyNumberModal(num) {
      this.wn.verifyForm = {
        id: num.id,
        display_phone_number: num.display_phone_number || num.id,
        method: 'SMS',
        code: '',
        codeSent: false,
        stage: 'verify',
        pin: '',
        pinConfirmation: '',
      };
      this.wn.verifyNumberModal?.show();
    },
    openRegistrationModal(num) {
      this.wn.verifyForm = {
        id: num.id,
        display_phone_number: num.display_phone_number || num.id,
        method: 'SMS',
        code: '',
        codeSent: false,
        stage: 'register',
        pin: '',
        pinConfirmation: '',
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
        notify.success('Number verified. Set its registration PIN to complete onboarding.', 'Settings');
        this.wn.verifyForm.stage = 'register';
        this.wn.verifyForm.pin = '';
        this.wn.verifyForm.pinConfirmation = '';
        await this.fetchWhatsappNumbers();
      } catch (err) {
        notify.error('Failed to verify phone number: ' + (err.response?.data?.message || err.message), 'Settings');
      } finally {
        this.wn.saving = false;
      }
    },
    async submitNumberRegistration() {
      if (!/^\d{6}$/.test(this.wn.verifyForm.pin) || this.wn.verifyForm.pin !== this.wn.verifyForm.pinConfirmation) {
        notify.warning('Enter matching six-digit registration PINs.', 'Settings');
        return;
      }

      this.wn.saving = true;
      try {
        await axios.post('/api/settings/meta/phone-numbers/register', {
          phone_number_id: this.wn.verifyForm.id,
          pin: this.wn.verifyForm.pin,
        });
        notify.success('Number registered on WhatsApp Cloud API. Meta display-name approval may still be pending.', 'Settings');
        this.wn.verifyNumberModal?.hide();
        await this.fetchWhatsappNumbers();
      } catch (err) {
        notify.error('Failed to register number: ' + (err.response?.data?.message || err.message), 'Settings');
      } finally {
        this.wn.saving = false;
      }
    },
    toggleWhatsappNumberPause(num) {
      const shouldPause = !num.is_paused;
      const rating = String(num.quality_rating || 'UNKNOWN').toUpperCase();
      const number = num.display_phone_number || num.id;

      this.$refs.confirmModal.open({
        title: shouldPause ? 'Pause WhatsApp Number' : 'Resume WhatsApp Number',
        message: shouldPause
          ? `Pause ${number}? Its current quality rating is ${rating}. Users will still be able to save campaign batches as drafts, but no templates can be sent from this number.`
          : `Resume ${number}? Users will be allowed to send WhatsApp templates from this number again. Current quality rating: ${rating}.`,
        confirmLabel: shouldPause ? 'Pause Number' : 'Resume Number',
        confirmVariant: shouldPause ? 'warning' : 'success',
        onConfirm: async () => {
          num.pauseSaving = true;
          try {
            const { data } = await axios.patch(
              `/api/settings/meta/phone-numbers/${encodeURIComponent(num.id)}/pause`,
              {
                paused: shouldPause,
                display_phone_number: num.display_phone_number,
                quality_rating: num.quality_rating,
              }
            );
            Object.assign(num, data.number || {}, { pauseSaving: false });
            notify.success(data.message, 'Settings');
          } catch (err) {
            num.pauseSaving = false;
            notify.error('Failed to update WhatsApp number: ' + (err.response?.data?.message || err.message), 'Settings');
            throw err;
          }
        },
      });
    },
    qualityRatingBadge(rating) {
      const r = String(rating || '').trim().toUpperCase();
      if (['GREEN', 'HIGH'].includes(r)) return 'bg-success text-white';
      if (['YELLOW', 'MEDIUM'].includes(r)) return 'bg-warning text-dark';
      if (['RED', 'LOW'].includes(r)) return 'bg-danger text-white';
      return 'bg-secondary';
    },
    formatTemplateCheckedAt(value) {
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString();
    },
    statusBadge(status) {
      const s = (status || '').toLowerCase();
      if (s === 'approved') return 'badge bg-success';
      if (['pending', 'in_review', 'in_appeal'].includes(s)) return 'badge bg-warning text-dark';
      if (s === 'rejected') return 'badge bg-danger';
      if (['paused', 'disabled', 'flagged', 'pending_deletion'].includes(s)) return 'badge bg-secondary';
      return 'badge bg-secondary';
    },
    isPendingStatus(status) {
      const s = String(status || '').toLowerCase();
      return ['pending', 'in_review', 'in_appeal'].includes(s);
    },
    openStatusInfoModal(template) {
      this.statusModal.template = template;
      this.statusModal.liveMessage = null;
      this.statusModal.liveError = null;
      this.statusModal.checkingLive = false;
      this.statusInfoModal?.show();
    },
    async checkSingleTemplateLiveStatus() {
      if (!this.statusModal.template) return;
      const t = this.statusModal.template;
      const lookupKey = t.sid || t.name;
      this.statusModal.checkingLive = true;
      this.statusModal.liveMessage = null;
      this.statusModal.liveError = null;

      try {
        const { data } = await axios.post(`/api/whatsapp-templates/${encodeURIComponent(lookupKey)}/check-status`);
        const updated = data.template || {};

        const idx = this.wa.templates.findIndex((item) => item.sid === t.sid || item.id === t.id);
        if (idx !== -1) {
          this.wa.templates[idx] = Object.assign({}, this.wa.templates[idx], updated);
        }
        this.statusModal.template = Object.assign({}, t, updated);

        this.statusModal.liveMessage = `Live status from Meta: ${data.live_status || updated.status || 'Updated'}. ${data.message || ''}`;
        notify.success(`Template status from Meta: ${data.live_status || updated.status || 'Checked'}`, 'Meta Review Status');
      } catch (err) {
        const errorMsg = err.response?.data?.message || err.message;
        this.statusModal.liveError = errorMsg;
        notify.error('Failed to check live status: ' + errorMsg, 'Meta Review Status');
      } finally {
        this.statusModal.checkingLive = false;
      }
    },
    scanTemplateContentRisks(body) {
      if (!body) return { triggers: [], count: 0 };
      const text = String(body).toLowerCase();
      const detected = [];

      const debtTriggers = [
        'past due', 'overdue', 'halt the process', 'legal action', 'attorney',
        'summons', 'court', 'proceedings', 'lawyer', 'arrears', 'settlement',
        'outstanding balance', 'default', 'debt', 'debtor', 'ptp', 'final notice',
        'demand for payment', 'section 129'
      ];

      for (const phrase of debtTriggers) {
        if (text.includes(phrase)) {
          detected.push({ phrase, category: 'Debt Collection / Legal Enforcement Language' });
        }
      }

      if (/{{(\d+)}}\s*{{(\d+)}}/.test(body) || /\*{{(\d+)}}\s*{{(\d+)}}\*/.test(body)) {
        detected.push({
          phrase: 'Consecutive parameters (e.g. {{1}} {{2}})',
          category: 'Parameter Formatting (Meta requires text between variables)',
        });
      }

      return {
        triggers: detected,
        count: detected.length,
      };
    },
    openMetaManagerForTemplate(template) {
      const wabaId = this.meta.form.meta_whatsapp_business_account_id;
      const query = new URLSearchParams({
        asset_id: wabaId || '',
        business_id: wabaId || '',
        tab: 'message-templates',
      });
      window.open(`https://business.facebook.com/latest/whatsapp_manager/message_templates/?${query.toString()}`, '_blank', 'noopener,noreferrer');
    },
    formatElapsedHours(syncedAt) {
      if (!syncedAt) return null;
      const date = new Date(syncedAt);
      if (Number.isNaN(date.getTime())) return null;
      const diffMs = Date.now() - date.getTime();
      const hours = Math.max(0, Math.floor(diffMs / (1000 * 60 * 60)));
      if (hours < 1) {
        const mins = Math.max(1, Math.floor(diffMs / (1000 * 60)));
        return `${mins} minute(s) ago`;
      }
      if (hours < 24) {
        return `${hours} hour(s) ago`;
      }
      const days = Math.floor(hours / 24);
      const remHours = hours % 24;
      return `${days} day(s)${remHours > 0 ? ` ${remHours} hr(s)` : ''} ago (${hours} hrs total)`;
    },
    handleEditFromStatusModal(template) {
      this.statusInfoModal?.hide();
      this.editTemplate(template);
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
.template-status-card {
  appearance: none;
  background: var(--bs-body-bg);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.template-status-card:hover {
  transform: translateY(-1px);
}
.template-status-card.ring-active {
  box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.3) !important;
}

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

/* WhatsApp Mobile Phone Device Preview */
.whatsapp-phone-preview {
  max-width: 350px;
  margin: 0 auto;
  overflow: hidden;
  border: 4px solid #263238;
  border-radius: 18px;
  background: #efeae2;
}

.whatsapp-phone-statusbar {
  height: 22px;
  color: #ffffff;
  background: #075e54;
  font-size: 0.64rem;
}

.whatsapp-phone-header {
  min-height: 46px;
  background: #008069;
}

.whatsapp-contact-avatar {
  width: 32px;
  height: 32px;
  background: #607d8b;
}

.whatsapp-contact-name {
  font-size: 0.82rem;
  line-height: 1.15;
}

.whatsapp-contact-number {
  margin-top: 1px;
  font-size: 0.65rem;
}

.whatsapp-verified-icon {
  color: #8edfd2;
  font-size: 0.74rem;
}

.whatsapp-chat-wallpaper {
  min-height: 160px;
  max-height: 320px;
  overflow-y: auto;
  background-color: #efeae2;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120'%3E%3Cg fill='none' stroke='%238b9b93' stroke-opacity='.11' stroke-width='1.3'%3E%3Cpath d='M14 18c7-5 15 4 10 11s-15 3-14-5m71-13 8 8-8 8-8-8zm-40 39c4-7 15-5 16 3s-10 13-15 7m45 13c8-2 13 8 7 14s-15 0-12-8M9 88c8-6 18 3 12 11S5 102 6 94m50-3 9 9m-9 0 9-9m37 2c4-7 14-3 12 5s-13 8-14 0'/%3E%3Cpath d='M36 7c3 7 11 8 16 3m-20 63c8 0 12 7 8 13m34-48c5 6 13 5 17-1m13 39c-7 2-9 10-4 15'/%3E%3C/g%3E%3C/svg%3E");
}

.whatsapp-encryption-note {
  width: fit-content;
  max-width: 90%;
  border-radius: 6px;
  color: #6b6252;
  background: #ffeecd;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.08);
  font-size: 0.6rem;
}

.whatsapp-date-chip {
  width: fit-content;
  border-radius: 6px;
  color: #54656f;
  background: #ffffffd9;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.08);
  font-size: 0.6rem;
}

.whatsapp-template-message {
  width: 90%;
  padding: 6px 8px 4px;
  margin-left: 6px;
  border-radius: 0 8px 8px 8px;
  color: #111b21;
  background: #ffffff;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.14);
}

.whatsapp-bubble-tail {
  position: absolute;
  top: 0;
  left: -8px;
  color: #ffffff;
}

.whatsapp-message-header {
  font-size: 0.84rem;
  line-height: 1.25;
}

.whatsapp-message-body {
  font-size: 0.78rem;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  word-break: break-word;
  line-height: 1.35;
}

.whatsapp-message-footer {
  font-size: 0.65rem;
}

.whatsapp-message-time {
  font-size: 0.58rem;
}

.whatsapp-document-preview {
  background: #f0f2f5;
  border: 1px solid #e1e5e7;
}

.whatsapp-template-buttons {
  margin-right: -8px;
  margin-left: -8px;
  margin-bottom: -4px;
}

.whatsapp-template-button {
  color: #00a884;
  border-top: 1px solid #e9edef;
  font-size: 0.74rem;
  font-weight: 600;
  cursor: pointer;
  background: #ffffff;
}

.whatsapp-template-button:hover {
  background: rgba(0, 0, 0, 0.03);
}

.template-header-image {
  max-height: 140px;
  object-fit: cover;
}

/* Modal Compact Overrides for Template Editor */
.whatsapp-template-modal .modal-content {
  border-radius: 12px;
}
.whatsapp-template-modal .form-label {
  margin-bottom: 0.25rem;
  font-size: 0.8rem;
}
.whatsapp-template-modal .form-control,
.whatsapp-template-modal .form-select {
  font-size: 0.82rem;
}
</style>
