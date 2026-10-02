<template>
  <div class="meta-billing-container py-3">
    <!-- TOP HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <h4 class="fw-bold mb-0 text-dark">Meta Billing & Charges</h4>
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
            <i class="bi bi-patch-check-fill me-1"></i> {{ config.review_status || 'APPROVED' }}
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
            <i class="bi bi-meta me-1"></i> {{ config.status || 'ACTIVE' }}
          </span>
        </div>
        <p class="text-muted small mb-0">
          Direct Facebook Meta Graph API telemetry, Ad Account marketing spend, and WhatsApp Cloud API cost ledger
        </p>
      </div>

      <!-- ACTIONS & FILTERS -->
      <div class="d-flex flex-wrap align-items-center gap-2">
        <!-- Date Range Filter -->
        <div class="input-group input-group-sm" style="width: 175px;">
          <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar3"></i></span>
          <select class="form-select border-start-0 shadow-none fw-medium" v-model="dateRange" @change="fetchBilling">
            <option value="last_30_days">Last 30 Days</option>
            <option value="this_month">This Month</option>
            <option value="3_months">3 Months</option>
            <option value="6_months">6 Months</option>
            <option value="1_year">1 Year</option>
            <option value="all_time">All Time</option>
          </select>
        </div>

        <!-- Bank / Institution Filter -->
        <div class="input-group input-group-sm" style="width: 190px;" v-if="banks.length > 0">
          <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-bank"></i></span>
          <select class="form-select border-start-0 shadow-none fw-medium text-truncate" v-model="selectedBankId" @change="fetchBilling">
            <option value="all">All Institutions</option>
            <option v-for="b in banks" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>

        <!-- Sync Live from Meta Button -->
        <button
          class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 shadow-sm"
          @click="syncFromMeta"
          :disabled="syncing || loading"
        >
          <span v-if="syncing" class="spinner-border spinner-border-sm me-1" role="status"></span>
          <i v-else class="bi bi-arrow-repeat me-1"></i>
          <span>{{ syncing ? 'Syncing...' : 'Sync Meta' }}</span>
        </button>

        <!-- Export Statement CSV Button -->
        <button class="btn btn-sm btn-dark d-flex align-items-center gap-1 shadow-sm" @click="exportStatement">
          <i class="bi bi-cloud-download me-1"></i> Export Statement
        </button>
      </div>
    </div>

    <!-- META IDENTITY SUBHEADER BANNER -->
    <div class="card border-0 shadow-sm mb-4 bg-light">
      <div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex flex-wrap align-items-center gap-4">
          <div class="d-flex align-items-center gap-2">
            <div class="bg-primary text-white rounded p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
              <i class="bi bi-meta fs-5"></i>
            </div>
            <div>
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">APP ID</div>
              <div class="fw-bold text-dark font-monospace small">{{ config.app_id || '347591848299284' }}</div>
            </div>
          </div>

          <div class="border-start ps-3 d-flex align-items-center gap-2">
            <div>
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">WHATSAPP BUSINESS ACCOUNT ID</div>
              <div class="fw-bold text-dark font-monospace small">{{ config.waba_id || '406811385845304' }}</div>
            </div>
          </div>

          <div class="border-start ps-3 d-none d-md-block" v-if="config.owner_business">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">OWNER BUSINESS</div>
            <div class="fw-semibold text-dark small">
              {{ config.owner_business.name }}
              <span class="badge bg-success-subtle text-success ms-1" style="font-size: 0.65rem;">
                <i class="bi bi-check-circle-fill"></i> Verified
              </span>
            </div>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2 text-muted small">
          <i class="bi bi-clock-history"></i>
          <span>Synced: <strong>{{ formattedLastSync }}</strong></span>
        </div>
      </div>
    </div>

    <!-- LOADING STATE -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading Meta Billing...</span>
      </div>
      <p class="text-muted small mt-2">Pulling live Meta account billing and usage telemetry...</p>
    </div>

    <div v-else>
      <!-- EXECUTIVE KPI SUMMARY CARDS -->
      <div class="row g-3 mb-4">
        <!-- Card 1: Total WhatsApp Incurred Cost -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">WHATSAPP CLOUD CHARGES</span>
                <span class="badge bg-success-subtle text-success"><i class="bi bi-whatsapp"></i> Cloud API</span>
              </div>
              <h3 class="fw-black mb-1 text-dark">{{ whatsappSummary.total_spend || '$0.00' }}</h3>
              <div class="d-flex justify-content-between text-muted small">
                <span>{{ whatsappSummary.dispatched || '0' }} msgs sent</span>
                <span class="text-dark fw-semibold">{{ whatsappSummary.avg_cost_per_delivered || '$0.00' }} / deliv</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 2: Meta Ad Account Spend -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">META AD SPEND</span>
                <span class="badge" :class="adInsights.has_permission ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning-emphasis'">
                  <i class="bi bi-badge-ad"></i> {{ adInsights.has_permission ? 'Marketing API' : 'Permission Req' }}
                </span>
              </div>
              <h3 class="fw-black mb-1 text-dark">
                {{ adInsights.insights ? adInsights.insights.spend : '$0.00' }}
              </h3>
              <div class="text-muted small text-truncate">
                <span v-if="adInsights.insights">
                  {{ adInsights.insights.impressions }} views · {{ adInsights.insights.clicks }} clicks
                </span>
                <span v-else class="text-warning-emphasis">
                  <i class="bi bi-info-circle me-1"></i> Requires ads_read scope
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 3: Total Deliveries & Quality -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">DELIVERY SUCCESS RATE</span>
                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-all"></i> Verified</span>
              </div>
              <h3 class="fw-black mb-1 text-success">{{ whatsappSummary.delivery_rate || '0.0%' }}</h3>
              <div class="d-flex justify-content-between text-muted small">
                <span>{{ whatsappSummary.delivered || '0' }} delivered</span>
                <span>{{ whatsappSummary.read || '0' }} read ({{ whatsappSummary.read_rate || '0%' }})</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 4: Meta Free Tier Allowance -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">FREE TIER ALLOWANCE</span>
                <span class="badge bg-info-subtle text-info"><i class="bi bi-gift"></i> Meta Free Tier</span>
              </div>
              <h3 class="fw-black mb-1 text-dark">
                {{ whatsappSummary.free_tier_conversations_used || 0 }}
                <span class="fs-6 fw-normal text-muted">/ 1,000</span>
              </h3>
              <div class="text-muted small">
                Monthly free customer service conversations included
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- MAIN TABS NAVIGATION -->
      <ul class="nav nav-pills custom-nav-pills gap-2 mb-4 p-1 bg-white rounded shadow-sm border" role="tablist">
        <li class="nav-item" role="presentation">
          <button
            class="nav-link px-4 py-2 fw-semibold active"
            id="pills-whatsapp-tab"
            data-bs-toggle="pill"
            data-bs-target="#pills-whatsapp"
            type="button"
            role="tab"
          >
            <i class="bi bi-whatsapp me-2"></i> WhatsApp Charges & Usage Ledger
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link px-4 py-2 fw-semibold"
            id="pills-adspend-tab"
            data-bs-toggle="pill"
            data-bs-target="#pills-adspend"
            type="button"
            role="tab"
          >
            <i class="bi bi-badge-ad me-2"></i> Meta Ad Account Marketing Spend
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link px-4 py-2 fw-semibold"
            id="pills-telemetry-tab"
            data-bs-toggle="pill"
            data-bs-target="#pills-telemetry"
            type="button"
            role="tab"
          >
            <i class="bi bi-shield-lock me-2"></i> Meta Account Telemetry & Portals
          </button>
        </li>
      </ul>

      <!-- TABS CONTENT -->
      <div class="tab-content">
        <!-- TAB 1: WHATSAPP CHARGES & USAGE LEDGER -->
        <div class="tab-pane fade show active" id="pills-whatsapp" role="tabpanel">
          <!-- CATEGORY SPEND BREAKDOWN CARDS -->
          <div class="row g-3 mb-4">
            <!-- Marketing -->
            <div class="col-12 col-md-6 col-xl-3" v-if="categories.marketing">
              <div class="card border-0 shadow-sm h-100 border-start border-4 border-dark">
                <div class="card-body p-3">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="fw-bold small text-dark"><i class="bi bi-circle-fill text-dark me-1" style="font-size:0.5rem"></i> Marketing Tier</span>
                    <span class="fw-black text-dark">{{ categories.marketing.cost }}</span>
                  </div>
                  <div class="text-muted small mb-2" style="font-size:0.75rem;">
                    {{ categories.marketing.messages }} msgs @ {{ categories.marketing.rate }}/msg
                  </div>
                  <div class="progress mb-2" style="height: 4px;">
                    <div class="progress-bar bg-dark" :style="{ width: categories.marketing.pct + '%' }"></div>
                  </div>
                  <p class="text-muted mb-0" style="font-size: 0.72rem; line-height: 1.3;">
                    {{ categories.marketing.description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Utility -->
            <div class="col-12 col-md-6 col-xl-3" v-if="categories.utility">
              <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body p-3">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="fw-bold small text-success"><i class="bi bi-circle-fill text-success me-1" style="font-size:0.5rem"></i> Utility Tier</span>
                    <span class="fw-black text-success">{{ categories.utility.cost }}</span>
                  </div>
                  <div class="text-muted small mb-2" style="font-size:0.75rem;">
                    {{ categories.utility.messages }} msgs @ {{ categories.utility.rate }}/msg
                  </div>
                  <div class="progress mb-2" style="height: 4px;">
                    <div class="progress-bar bg-success" :style="{ width: categories.utility.pct + '%' }"></div>
                  </div>
                  <p class="text-muted mb-0" style="font-size: 0.72rem; line-height: 1.3;">
                    {{ categories.utility.description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Authentication -->
            <div class="col-12 col-md-6 col-xl-3" v-if="categories.auth">
              <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
                <div class="card-body p-3">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="fw-bold small text-info"><i class="bi bi-circle-fill text-info me-1" style="font-size:0.5rem"></i> Authentication</span>
                    <span class="fw-black text-info">{{ categories.auth.cost }}</span>
                  </div>
                  <div class="text-muted small mb-2" style="font-size:0.75rem;">
                    {{ categories.auth.messages }} msgs @ {{ categories.auth.rate }}/msg
                  </div>
                  <div class="progress mb-2" style="height: 4px;">
                    <div class="progress-bar bg-info" :style="{ width: categories.auth.pct + '%' }"></div>
                  </div>
                  <p class="text-muted mb-0" style="font-size: 0.72rem; line-height: 1.3;">
                    {{ categories.auth.description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Service -->
            <div class="col-12 col-md-6 col-xl-3" v-if="categories.service">
              <div class="card border-0 shadow-sm h-100 border-start border-4 border-secondary">
                <div class="card-body p-3">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="fw-bold small text-secondary"><i class="bi bi-circle-fill text-secondary me-1" style="font-size:0.5rem"></i> Service Window</span>
                    <span class="fw-black text-secondary">{{ categories.service.cost }}</span>
                  </div>
                  <div class="text-muted small mb-2" style="font-size:0.75rem;">
                    {{ categories.service.messages }} msgs @ {{ categories.service.rate }}/msg
                  </div>
                  <div class="progress mb-2" style="height: 4px;">
                    <div class="progress-bar bg-secondary" :style="{ width: categories.service.pct + '%' }"></div>
                  </div>
                  <p class="text-muted mb-0" style="font-size: 0.72rem; line-height: 1.3;">
                    {{ categories.service.description }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- DAILY TRANSMISSION & SPEND TRAJECTORY CHART -->
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h6 class="fw-bold mb-1">Daily Transmission Volume & Meta Cost Trajectory</h6>
                  <p class="text-muted small mb-0">Daily outbound dispatches, delivered receipt confirmations and incurred Meta cost</p>
                </div>
                <span class="badge bg-light text-muted border">Daily Trend</span>
              </div>
              <div style="height: 260px; position: relative;">
                <canvas ref="trajectoryChart"></canvas>
              </div>
            </div>
          </div>

          <!-- ITEMIZED TEMPLATE BILLING BREAKDOWN TABLE -->
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold mb-1">
                  Itemized WhatsApp Template Billing Ledger
                  <span class="badge bg-light text-dark border ms-2">{{ filteredTemplates.length }} Templates</span>
                </h5>
                <p class="text-muted small mb-0">Per-template message volume, category classification, and incurred Meta charges</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 220px;">
                  <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                  <input
                    type="text"
                    class="form-control border-start-0 shadow-none"
                    placeholder="Search templates..."
                    v-model="templateSearch"
                  />
                  <button v-if="templateSearch" class="btn btn-outline-secondary border-start-0" type="button" @click="templateSearch = ''">
                    <i class="bi bi-x"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover align-middle custom-table">
                  <thead class="text-muted small">
                    <tr>
                      <th>TEMPLATE NAME</th>
                      <th>CATEGORY</th>
                      <th>CAMPAIGNS USED IN</th>
                      <th class="text-end">TRANSMISSIONS</th>
                      <th class="text-end">DELIVERED</th>
                      <th class="text-end">DELIVERY RATE</th>
                      <th class="text-end">REPLY RATE</th>
                      <th class="text-end">META RATE / MSG</th>
                      <th class="text-end">INCURRED CHARGES</th>
                      <th class="text-center">STATUS</th>
                    </tr>
                  </thead>
                  <tbody class="border-top-0">
                    <tr v-if="filteredTemplates.length === 0">
                      <td colspan="10" class="text-center py-4 text-muted">
                        No templates found matching the current criteria.
                      </td>
                    </tr>
                    <tr v-for="(t, idx) in filteredTemplates" :key="idx">
                      <td>
                        <div class="fw-bold text-dark">{{ t.name }}</div>
                        <div class="text-muted font-monospace" style="font-size: 0.72rem;">ID: {{ t.meta_id }}</div>
                      </td>
                      <td>
                        <span
                          class="badge border fw-semibold"
                          :class="t.category === 'Marketing' ? 'bg-dark text-white' : (t.category === 'Utility' ? 'bg-success-subtle text-success border-success-subtle' : 'bg-light text-dark')"
                        >
                          {{ t.category }}
                        </span>
                      </td>
                      <td>
                        <div class="fw-semibold text-dark small">{{ t.campaign }}</div>
                      </td>
                      <td class="text-end fw-semibold">{{ t.sent }}</td>
                      <td class="text-end fw-semibold text-success">{{ t.delivered }}</td>
                      <td class="text-end fw-bold text-success">{{ t.delivery_rate }}</td>
                      <td class="text-end">{{ t.reply_rate }}</td>
                      <td class="text-end text-muted font-monospace small">{{ t.rate }}</td>
                      <td class="text-end fw-black text-dark">{{ t.cost }}</td>
                      <td class="text-center">
                        <span class="badge bg-success-subtle text-success">
                          <i class="bi bi-check-circle-fill me-1"></i> {{ t.status }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: META AD ACCOUNT MARKETING SPEND -->
        <div class="tab-pane fade" id="pills-adspend" role="tabpanel">
          <!-- AD ACCOUNT SETTING / SELECTOR -->
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                  <h6 class="fw-bold mb-1">Meta Ad Account Connection</h6>
                  <p class="text-muted small mb-0">
                    Connect your Facebook / Meta Ad Account (ID: <code>act_...</code>) to pull marketing spend, impressions, clicks, CPC, and campaign ROI
                  </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <input
                    type="text"
                    class="form-control form-control-sm font-monospace"
                    style="width: 220px;"
                    placeholder="act_123456789"
                    v-model="adAccountInput"
                  />
                  <button class="btn btn-sm btn-primary shadow-sm" @click="saveAdAccountId" :disabled="savingAdAccount">
                    <span v-if="savingAdAccount" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="bi bi-check-lg me-1"></i>
                    <span>Save ID</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- PERMISSION GUIDE IF MISSING ADS_READ -->
          <div v-if="!adInsights.has_permission" class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
            <div class="card-body p-4">
              <div class="d-flex align-items-start gap-3">
                <div class="bg-warning-subtle text-warning-emphasis p-2 rounded-circle fs-4">
                  <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div class="flex-grow-1">
                  <h5 class="fw-bold text-dark mb-1">Meta Marketing API Permission Required (<code>ads_read</code>)</h5>
                  <p class="text-muted small mb-3">
                    {{ adInsights.message }}
                  </p>

                  <div class="card bg-light border-0 mb-3">
                    <div class="card-body p-3">
                      <h6 class="fw-bold small mb-2">How to Enable Live Ad Spend in Meta Business Suite:</h6>
                      <ol class="small text-muted mb-0 ps-3">
                        <li class="mb-1">Log in to <a :href="quickLinks.system_users" target="_blank" class="fw-bold text-decoration-none">Meta Business Settings <i class="bi bi-box-arrow-up-right"></i></a> for Business ID <code>{{ config.owner_business?.id || '1323375205187636' }}</code>.</li>
                        <li class="mb-1">Under <strong>Users -> System Users</strong>, select your API system user or admin user.</li>
                        <li class="mb-1">Click <strong>Generate New Token</strong> (or Edit Permissions) and check the <strong><code>ads_read</code></strong> and <strong><code>read_insights</code></strong> scopes.</li>
                        <li>Update the system token in <router-link to="/settings" class="fw-bold text-decoration-none">NexusCRM Settings -> Meta WhatsApp</router-link>, and enter your Ad Account ID above.</li>
                      </ol>
                    </div>
                  </div>

                  <div class="d-flex gap-2">
                    <a :href="quickLinks.billing_hub" target="_blank" class="btn btn-sm btn-outline-dark">
                      <i class="bi bi-receipt me-1"></i> Open Meta Business Billing Hub
                    </a>
                    <a :href="quickLinks.system_users" target="_blank" class="btn btn-sm btn-primary">
                      <i class="bi bi-person-gear me-1"></i> Open System Users in Meta
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ACTIVE AD ACCOUNT PERFORMANCE INSIGHTS -->
          <div v-else>
            <!-- Account Overview Banner -->
            <div class="card border-0 shadow-sm mb-4" v-if="adInsights.account_info">
              <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                  <span class="badge bg-primary-subtle text-primary mb-1">Connected Ad Account</span>
                  <h4 class="fw-bold mb-0 text-dark">{{ adInsights.account_info.name || 'Meta Ad Account' }}</h4>
                  <div class="text-muted font-monospace small">ID: {{ adInsights.configured_ad_account_id }} · Currency: {{ adInsights.account_info.currency || 'USD' }}</div>
                </div>
                <div class="d-flex gap-3 text-end">
                  <div>
                    <div class="text-muted small">Total Lifetime Spent</div>
                    <div class="fw-bold fs-5 text-dark">${{ adInsights.account_info.amount_spent ? (adInsights.account_info.amount_spent / 100).toFixed(2) : '0.00' }}</div>
                  </div>
                  <div class="border-start ps-3" v-if="adInsights.account_info.balance">
                    <div class="text-muted small">Current Balance</div>
                    <div class="fw-bold fs-5 text-dark">${{ (adInsights.account_info.balance / 100).toFixed(2) }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Ad KPI Cards -->
            <div class="row g-3 mb-4" v-if="adInsights.insights">
              <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 text-center">
                  <div class="text-muted small fw-bold">TOTAL SPEND</div>
                  <h3 class="fw-black mb-0 text-primary">{{ adInsights.insights.spend }}</h3>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 text-center">
                  <div class="text-muted small fw-bold">IMPRESSIONS</div>
                  <h3 class="fw-black mb-0 text-dark">{{ adInsights.insights.impressions }}</h3>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 text-center">
                  <div class="text-muted small fw-bold">CLICKS</div>
                  <h3 class="fw-black mb-0 text-dark">{{ adInsights.insights.clicks }}</h3>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 text-center">
                  <div class="text-muted small fw-bold">CTR / CPC</div>
                  <h3 class="fw-black mb-0 text-success">{{ adInsights.insights.ctr }}</h3>
                  <div class="text-muted small">{{ adInsights.insights.cpc }} / click</div>
                </div>
              </div>
            </div>

            <!-- Campaigns Breakdown Table -->
            <div class="card border-0 shadow-sm" v-if="adInsights.campaigns && adInsights.campaigns.length">
              <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="fw-bold mb-1">Ad Campaigns Performance & Spend</h5>
                <p class="text-muted small mb-0">Granular ad campaign insights pulled directly from Meta Marketing API</p>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover align-middle custom-table">
                    <thead class="text-muted small">
                      <tr>
                        <th>CAMPAIGN NAME</th>
                        <th class="text-end">SPEND</th>
                        <th class="text-end">IMPRESSIONS</th>
                        <th class="text-end">CLICKS</th>
                        <th class="text-end">CTR</th>
                        <th class="text-end">CPC</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(ac, idx) in adInsights.campaigns" :key="idx">
                        <td>
                          <div class="fw-bold text-dark">{{ ac.campaign_name }}</div>
                          <div class="text-muted font-monospace small">ID: {{ ac.campaign_id }}</div>
                        </td>
                        <td class="text-end fw-bold text-dark">{{ ac.spend }}</td>
                        <td class="text-end fw-semibold">{{ ac.impressions }}</td>
                        <td class="text-end fw-semibold">{{ ac.clicks }}</td>
                        <td class="text-end fw-bold text-success">{{ ac.ctr }}</td>
                        <td class="text-end text-muted">{{ ac.cpc }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 3: META ACCOUNT TELEMETRY & PORTALS -->
        <div class="tab-pane fade" id="pills-telemetry" role="tabpanel">
          <div class="row g-4 mb-4">
            <!-- App & WABA Info Card -->
            <div class="col-12 col-lg-6">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                  <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-cpu-fill text-primary"></i> Meta Application & WABA Telemetry
                  </h6>
                  <div class="table-responsive">
                    <table class="table table-sm table-borderless align-middle mb-0 small">
                      <tbody>
                        <tr>
                          <td class="text-muted" style="width: 170px;">Application Name:</td>
                          <td class="fw-bold text-dark">{{ config.app_name }}</td>
                        </tr>
                        <tr>
                          <td class="text-muted">App ID:</td>
                          <td class="font-monospace text-dark">{{ config.app_id }}</td>
                        </tr>
                        <tr>
                          <td class="text-muted">App Category:</td>
                          <td><span class="badge bg-light text-dark border">{{ config.app_category }}</span></td>
                        </tr>
                        <tr>
                          <td class="text-muted">WABA Account Name:</td>
                          <td class="fw-bold text-dark">{{ config.waba_name }}</td>
                        </tr>
                        <tr>
                          <td class="text-muted">WABA Account ID:</td>
                          <td class="font-monospace text-dark">{{ config.waba_id }}</td>
                        </tr>
                        <tr>
                          <td class="text-muted">Currency & Timezone:</td>
                          <td class="fw-semibold text-dark">{{ config.currency }} (Timezone ID: {{ config.timezone }})</td>
                        </tr>
                        <tr>
                          <td class="text-muted">Account Status:</td>
                          <td><span class="badge bg-success-subtle text-success">{{ config.status }}</span></td>
                        </tr>
                        <tr>
                          <td class="text-muted">Account Review:</td>
                          <td><span class="badge bg-success-subtle text-success">{{ config.review_status }}</span></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Direct Meta Portal Action Hub -->
            <div class="col-12 col-lg-6">
              <div class="card border-0 shadow-sm h-100 bg-dark text-white">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                  <div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <h6 class="fw-bold text-white mb-0">Official Meta Business Portals</h6>
                      <i class="bi bi-box-arrow-up-right text-white-50"></i>
                    </div>
                    <p class="text-white-50 small mb-4">
                      Direct single-click access to official Facebook Meta Business Suite billing, payment settings, and WhatsApp management.
                    </p>

                    <div class="d-grid gap-2">
                      <a :href="quickLinks.billing_hub" target="_blank" class="btn btn-outline-light btn-sm text-start d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-credit-card me-2 text-info"></i> Meta Business Suite Payment Settings</span>
                        <i class="bi bi-chevron-right text-white-50"></i>
                      </a>
                      <a :href="quickLinks.invoices" target="_blank" class="btn btn-outline-light btn-sm text-start d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-receipt me-2 text-warning"></i> Official Meta Invoices & Accounts</span>
                        <i class="bi bi-chevron-right text-white-50"></i>
                      </a>
                      <a :href="quickLinks.whatsapp_manager" target="_blank" class="btn btn-outline-light btn-sm text-start d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-whatsapp me-2 text-success"></i> WhatsApp Manager Dashboard</span>
                        <i class="bi bi-chevron-right text-white-50"></i>
                      </a>
                      <a :href="quickLinks.app_dashboard" target="_blank" class="btn btn-outline-light btn-sm text-start d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-meta me-2 text-primary"></i> Meta Developers App Dashboard</span>
                        <i class="bi bi-chevron-right text-white-50"></i>
                      </a>
                    </div>
                  </div>

                  <div class="mt-4 pt-3 border-top border-secondary small text-white-50">
                    Business ID: <strong class="text-white">{{ config.owner_business?.id || '1323375205187636' }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- REGISTERED PHONE NUMBERS UNDER WABA -->
          <div class="card border-0 shadow-sm mb-4" v-if="config.phone_numbers && config.phone_numbers.length">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
              <h5 class="fw-bold mb-1">Registered WhatsApp Phone Numbers</h5>
              <p class="text-muted small mb-0">Active telephone numbers verified under this WhatsApp Business Account</p>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover align-middle custom-table">
                  <thead class="text-muted small">
                    <tr>
                      <th>DISPLAY NUMBER</th>
                      <th>VERIFIED SENDER NAME</th>
                      <th>PHONE NUMBER ID</th>
                      <th>QUALITY RATING</th>
                      <th>PLATFORM</th>
                      <th>THROUGHPUT</th>
                      <th class="text-center">STATUS</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="num in config.phone_numbers" :key="num.id">
                      <td>
                        <div class="fw-bold text-dark font-monospace">{{ num.display_phone_number }}</div>
                      </td>
                      <td>
                        <div class="fw-semibold text-dark">{{ num.verified_name || '—' }}</div>
                      </td>
                      <td class="font-monospace small text-muted">{{ num.id }}</td>
                      <td>
                        <span
                          class="badge"
                          :class="num.quality_rating === 'GREEN' ? 'bg-success-subtle text-success' : 'bg-light text-muted border'"
                        >
                          {{ num.quality_rating }}
                        </span>
                      </td>
                      <td><span class="badge bg-light text-dark border">{{ num.platform_type }}</span></td>
                      <td><span class="badge bg-light text-dark border">{{ num.throughput?.level || 'STANDARD' }}</span></td>
                      <td class="text-center">
                        <span class="badge bg-success-subtle text-success">
                          <i class="bi bi-check-circle-fill me-1"></i> {{ num.code_verification_status }}
                        </span>
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
  </div>
</template>

<script>
import Chart from 'chart.js/auto';
import axios from '../axios';

export default {
  name: 'MetaBilling',
  data() {
    return {
      loading: true,
      syncing: false,
      savingAdAccount: false,
      dateRange: 'last_30_days',
      selectedBankId: 'all',
      templateSearch: '',
      adAccountInput: '',
      banks: [],
      config: {},
      whatsappSummary: {},
      categories: {},
      templates: [],
      dailyTrajectory: {
        labels: [],
        dispatched: [],
        delivered: [],
        cost: [],
      },
      adInsights: {
        has_permission: false,
        insights: null,
        campaigns: [],
      },
      quickLinks: {},
      lastSyncedAt: null,
      chartInstance: null,
    };
  },
  computed: {
    formattedLastSync() {
      if (!this.lastSyncedAt) return 'Just now';
      try {
        const d = new Date(this.lastSyncedAt);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      } catch {
        return 'Recently';
      }
    },
    filteredTemplates() {
      let list = this.templates || [];
      if (this.templateSearch && this.templateSearch.trim()) {
        const q = this.templateSearch.trim().toLowerCase();
        list = list.filter(t =>
          (t.name && t.name.toLowerCase().includes(q)) ||
          (t.category && t.category.toLowerCase().includes(q)) ||
          (t.campaign && t.campaign.toLowerCase().includes(q)) ||
          (t.meta_id && String(t.meta_id).toLowerCase().includes(q))
        );
      }
      return list;
    },
  },
  mounted() {
    this.fetchBanks();
    this.fetchBilling();
  },
  beforeUnmount() {
    if (this.chartInstance) {
      this.chartInstance.destroy();
    }
  },
  methods: {
    async fetchBanks() {
      try {
        const res = await axios.get('/api/banks', { params: { per_page: 200 } });
        this.banks = res.data.data || res.data || [];
      } catch (e) {
        console.error('Failed to load banks for billing filter', e);
      }
    },
    async fetchBilling(isRefresh = false) {
      if (!isRefresh) {
        this.loading = true;
      }
      try {
        const res = await axios.get('/api/meta-billing', {
          params: {
            date_range: this.dateRange,
            bank_id: this.selectedBankId,
            refresh: isRefresh,
          },
        });
        const data = res.data;
        this.config = data.meta_config || {};
        this.adAccountInput = this.config.ad_account_id || '';
        this.whatsappSummary = data.whatsapp_billing?.summary || {};
        this.categories = data.whatsapp_billing?.categories || {};
        this.templates = data.whatsapp_billing?.templates || [];
        this.dailyTrajectory = data.whatsapp_billing?.daily_trajectory || { labels: [], dispatched: [], delivered: [], cost: [] };
        this.adInsights = data.ad_account_insights || {};
        this.quickLinks = data.quick_links || {};
        this.lastSyncedAt = data.synced_at || new Date().toISOString();
      } catch (err) {
        console.error('Failed to load Meta billing', err);
      } finally {
        this.loading = false;
        this.syncing = false;
        this.$nextTick(() => {
          this.initTrajectoryChart();
        });
      }
    },
    async syncFromMeta() {
      this.syncing = true;
      await this.fetchBilling(true);
    },
    async saveAdAccountId() {
      this.savingAdAccount = true;
      try {
        await axios.post('/api/meta-billing/ad-account', {
          ad_account_id: this.adAccountInput,
        });
        await this.syncFromMeta();
      } catch (err) {
        console.error('Failed to save Ad Account ID', err);
      } finally {
        this.savingAdAccount = false;
      }
    },
    initTrajectoryChart() {
      const canvas = this.$refs.trajectoryChart;
      if (!canvas) return;

      if (this.chartInstance) {
        this.chartInstance.destroy();
      }

      const ctx = canvas.getContext('2d');
      this.chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
          labels: this.dailyTrajectory.labels,
          datasets: [
            {
              label: 'Dispatched',
              data: this.dailyTrajectory.dispatched,
              borderColor: '#212529',
              backgroundColor: 'rgba(33, 37, 41, 0.05)',
              borderWidth: 2,
              tension: 0.3,
              fill: true,
              yAxisID: 'y',
            },
            {
              label: 'Delivered',
              data: this.dailyTrajectory.delivered,
              borderColor: '#198754',
              backgroundColor: 'rgba(25, 135, 84, 0.05)',
              borderWidth: 2,
              tension: 0.3,
              fill: true,
              yAxisID: 'y',
            },
            {
              label: 'Estimated Cost ($)',
              data: this.dailyTrajectory.cost,
              borderColor: '#0d6efd',
              borderDash: [4, 4],
              borderWidth: 2,
              tension: 0.3,
              fill: false,
              yAxisID: 'y1',
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false,
          },
          plugins: {
            legend: {
              position: 'top',
              labels: {
                boxWidth: 12,
                font: { size: 11 },
              },
            },
            tooltip: {
              padding: 10,
              boxPadding: 4,
            },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { font: { size: 10 } },
            },
            y: {
              type: 'linear',
              display: true,
              position: 'left',
              grid: { color: 'rgba(0, 0, 0, 0.04)' },
              ticks: { font: { size: 10 } },
            },
            y1: {
              type: 'linear',
              display: true,
              position: 'right',
              grid: { drawOnChartArea: false },
              ticks: {
                font: { size: 10 },
                callback: (val) => '$' + val,
              },
            },
          },
        },
      });
    },
    exportStatement() {
      const escapeCsv = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
      const now = new Date();
      const generatedAt = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;

      const fileRows = [];
      fileRows.push(['NEXUS CRM - FACEBOOK META BILLING & CHARGES STATEMENT']);
      fileRows.push(['Generated At', generatedAt]);
      fileRows.push(['Meta App ID', this.config.app_id || '347591848299284']);
      fileRows.push(['Meta App Name', this.config.app_name || 'CRM System API']);
      fileRows.push(['WhatsApp Business Account ID', this.config.waba_id || '406811385845304']);
      fileRows.push(['WABA Name', this.config.waba_name || 'Iconis CRM']);
      fileRows.push(['Owner Business', this.config.owner_business?.name || 'ICON INFORMATION SYSTEMS']);
      fileRows.push(['Reporting Period', this.dateRange]);
      fileRows.push([]);

      fileRows.push(['EXECUTIVE BILLING SUMMARY']);
      fileRows.push(['Total WhatsApp Cloud Spend', this.whatsappSummary.total_spend || '$0.00']);
      fileRows.push(['Total Messages Dispatched', this.whatsappSummary.dispatched || '0']);
      fileRows.push(['Total Messages Delivered', this.whatsappSummary.delivered || '0']);
      fileRows.push(['Delivery Success Rate', this.whatsappSummary.delivery_rate || '0%']);
      fileRows.push(['Read Confirmation Rate', this.whatsappSummary.read_rate || '0%']);
      fileRows.push(['Average Cost Per Delivered Message', this.whatsappSummary.avg_cost_per_delivered || '$0.00']);
      fileRows.push([]);

      fileRows.push(['CATEGORY CHARGE BREAKDOWN']);
      fileRows.push(['Category', 'Messages Dispatched', 'Rate / Msg', 'Incurred Cost', 'Percentage']);
      if (this.categories.marketing) {
        fileRows.push(['Marketing Tier', this.categories.marketing.messages, this.categories.marketing.rate, this.categories.marketing.cost, this.categories.marketing.pct + '%']);
      }
      if (this.categories.utility) {
        fileRows.push(['Utility Tier', this.categories.utility.messages, this.categories.utility.rate, this.categories.utility.cost, this.categories.utility.pct + '%']);
      }
      if (this.categories.auth) {
        fileRows.push(['Authentication Tier', this.categories.auth.messages, this.categories.auth.rate, this.categories.auth.cost, this.categories.auth.pct + '%']);
      }
      if (this.categories.service) {
        fileRows.push(['Service Window', this.categories.service.messages, this.categories.service.rate, this.categories.service.cost, this.categories.service.pct + '%']);
      }
      fileRows.push([]);

      fileRows.push(['ITEMIZED WHATSAPP TEMPLATE LEDGER']);
      fileRows.push([
        'Template Name',
        'Meta Template ID',
        'Category',
        'Campaigns',
        'Transmissions',
        'Delivered',
        'Delivery %',
        'Reply %',
        'Rate / Msg',
        'Incurred Cost',
        'Status',
      ]);

      const templateList = this.filteredTemplates.length > 0 ? this.filteredTemplates : this.templates;
      templateList.forEach((t) => {
        fileRows.push([
          t.name,
          t.meta_id,
          t.category,
          t.campaign,
          t.sent,
          t.delivered,
          t.delivery_rate,
          t.reply_rate,
          t.rate,
          t.cost,
          t.status,
        ]);
      });

      const csvContent = '\uFEFF' + fileRows.map((r) => r.map(escapeCsv).join(',')).join('\r\n');
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `Meta_Billing_Statement_${this.dateRange}_${now.toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
  },
};
</script>

<style scoped>
.custom-nav-pills .nav-link {
  color: #64748b;
  border-radius: 6px;
  transition: all 0.2s ease;
}

.custom-nav-pills .nav-link:hover {
  color: #0f172a;
  background-color: #f8fafc;
}

.custom-nav-pills .nav-link.active {
  color: #0f172a;
  background-color: #e2e8f0;
}

.custom-table th {
  font-weight: 600;
  font-size: 0.72rem;
  letter-spacing: 0.05em;
  border-top: none;
  padding: 0.75rem 1rem;
}

.custom-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #f1f5f9;
}
</style>
