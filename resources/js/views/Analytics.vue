<template>
  <div class="analytics-container p-3 p-md-4">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
      <div>
        <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-circle-fill text-success" style="font-size: 0.6rem;"></i> 
          Statistics & Reports
        </h3>
        <p class="text-muted small mb-0">In-depth performance analytics, delivery tracking, Meta messaging spend, and agent efficiency metrics.</p>
      </div>
      
      <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm bg-white shadow-sm border rounded">
          <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-calendar3"></i></span>
          <select class="form-select border-0 shadow-none fw-semibold" style="font-size: 0.8rem; width: auto;" v-model="dateRange" @change="onDateFilterChange">
            <option value="last_30_days">Last 30 Days</option>
            <option value="this_month">This Month</option>
            <option value="3_months">3 Months</option>
            <option value="6_months">6 Months</option>
            <option value="1_year">1 Year</option>
            <option value="2_years">2 Years</option>
            <option value="year_to_date">Year to Date</option>
            <option value="all_time">All Time (All Campaigns)</option>
          </select>
        </div>
        
        <div class="input-group input-group-sm bg-white shadow-sm border rounded">
          <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-briefcase"></i></span>
          <select class="form-select border-0 shadow-none fw-semibold" style="font-size: 0.8rem; width: auto;" v-model="selectedBankId" @change="onBankFilterChange">
            <option value="all">All Institutions ({{ banks.length }})</option>
            <option v-for="b in banks" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>

        <button class="btn btn-sm btn-light border shadow-sm fw-semibold d-flex align-items-center gap-1" @click="fetchData">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
        <button class="btn btn-sm btn-dark shadow-sm fw-semibold d-flex align-items-center gap-1" @click="exportCampaignReport">
          <i class="bi bi-cloud-download"></i> Export Report
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <div class="mt-2 text-muted">Compiling analytics...</div>
    </div>

    <div v-else>
      <!-- Custom Tabs -->
      <div class="nav nav-pills custom-tabs mb-4 p-1 bg-white border rounded shadow-sm d-inline-flex">
        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#overall">
          <i class="bi bi-graph-up me-1"></i> Overall Statistics
        </button>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#campaigns">
          <i class="bi bi-megaphone me-1"></i> Campaign Performance
        </button>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#whatsapp">
          <i class="bi bi-whatsapp me-1"></i> WhatsApp Templates & Cost
        </button>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#agents">
          <i class="bi bi-people me-1"></i> Agent & User Statistics
        </button>
      </div>

      <!-- Tab Content -->
      <div class="tab-content">
        <!-- OVERALL STATISTICS TAB -->
        <div class="tab-pane fade show active" id="overall">
          <!-- KPI Cards -->
          <div class="row g-3 mb-4">
            <!-- Dispatched -->
            <div class="col-12 col-md-6 col-lg-3">
              <div class="card h-100 border-0 shadow-sm border-start border-4 border-dark">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Total Dispatched</div>
                    <div class="bg-light rounded p-1 text-secondary"><i class="bi bi-send-fill"></i></div>
                  </div>
                  <h2 class="fw-black mb-3">{{ summary.dispatched }}</h2>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success fw-bold"><i class="bi bi-arrow-up-short"></i> 15.2%</span>
                    <span class="text-muted" style="font-size: 0.75rem;">vs. previous 30 days</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Delivery -->
            <div class="col-12 col-md-6 col-lg-3">
              <div class="card h-100 border-0 shadow-sm border-start border-4 border-success">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Delivery & Engagement</div>
                    <div class="bg-success-subtle rounded p-1 text-success"><i class="bi bi-check-all"></i></div>
                  </div>
                  <h2 class="fw-black mb-3">{{ summary.delivery_rate }} <span class="fs-6 text-muted fw-normal">Delivered</span></h2>
                  <div class="d-flex flex-wrap gap-2">
                    <div class="bg-light rounded px-2 py-1 text-dark" style="font-size: 0.75rem;">Delivered <strong>{{ summary.delivered }}</strong></div>
                    <div class="bg-success-subtle rounded px-2 py-1 text-success fw-semibold" style="font-size: 0.75rem;">{{ summary.read_rate }} Read ({{ summary.read }})</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Spend -->
            <div class="col-12 col-md-6 col-lg-3">
              <div class="card h-100 border-0 shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Meta Cloud Spend</div>
                    <div class="bg-light rounded p-1 text-secondary"><i class="bi bi-receipt"></i></div>
                  </div>
                  <h2 class="fw-black mb-3">{{ spend.total }}</h2>
                  <div class="d-flex flex-wrap gap-2">
                    <div class="text-muted" style="font-size: 0.75rem;">Avg <strong>{{ spend.avg_per_delivered }}</strong> / delivered</div>
                    <div class="bg-light border rounded px-2 py-0 text-muted" style="font-size: 0.65rem; align-self: center;">TIER 1 CLOUD API</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Assets -->
            <div class="col-12 col-md-6 col-lg-3">
              <div class="card h-100 border-0 shadow-sm border-start border-4 border-secondary position-relative overflow-hidden">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Live Assets Orchestrated</div>
                    <div class="bg-light rounded p-1 text-secondary"><i class="bi bi-diagram-3"></i></div>
                  </div>
                  <h2 class="fw-black mb-3">{{ assets.campaigns }} <span class="fs-6 text-muted fw-normal">Campaigns</span></h2>
                  <div class="d-flex flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                      <i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i> {{ assets.templates }} Approved Templates
                    </div>
                    <div class="d-flex align-items-center gap-1 text-success fw-bold" style="font-size: 0.75rem;">
                      100% Compliant
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Chart Section -->
          <div class="row g-3 mb-4">
            <div class="col-12 col-lg-8 col-xl-9">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                      <h5 class="fw-bold mb-1">Message Volume & Delivery Funnel</h5>
                      <p class="text-muted small mb-0">Daily transmission attrition & inbound consumer interaction trajectory</p>
                    </div>
                    <div class="btn-group btn-group-sm border rounded">
                      <button class="btn btn-light bg-white border-0 fw-semibold" :class="{'text-dark active': timeframe === 'daily', 'text-muted': timeframe !== 'daily'}" @click="setTimeframe('daily')">Daily</button>
                      <button class="btn btn-light bg-white border-0" :class="{'text-dark active': timeframe === 'weekly', 'text-muted': timeframe !== 'weekly'}" @click="setTimeframe('weekly')">Weekly</button>
                      <button class="btn btn-light bg-white border-0" :class="{'text-dark active': timeframe === 'monthly', 'text-muted': timeframe !== 'monthly'}" @click="setTimeframe('monthly')">Monthly</button>
                      <button class="btn btn-light bg-white border-0" :class="{'text-dark active': timeframe === '3month', 'text-muted': timeframe !== '3month'}" @click="setTimeframe('3month')">3 Months</button>
                    </div>
                  </div>

                  <!-- Funnel Stats -->
                  <div class="row g-2 mb-4 text-center">
                    <div class="col-3">
                      <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">1. Dispatched</div>
                      <div class="fw-bold fs-5">{{ summary.dispatched }}</div>
                      <div class="text-muted" style="font-size: 0.7rem;">100% Base</div>
                    </div>
                    <div class="col-3 position-relative">
                      <div class="position-absolute top-50 start-0 translate-middle text-muted"><i class="bi bi-chevron-right"></i></div>
                      <div class="text-success text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">2. Delivered</div>
                      <div class="fw-bold fs-5 text-success">{{ summary.delivered }}</div>
                      <div class="text-success" style="font-size: 0.7rem;">{{ summary.delivery_rate }} success</div>
                    </div>
                    <div class="col-3 position-relative">
                      <div class="position-absolute top-50 start-0 translate-middle text-muted"><i class="bi bi-chevron-right"></i></div>
                      <div class="text-dark text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">3. Read Confirmation</div>
                      <div class="fw-bold fs-5">{{ summary.read }}</div>
                      <div class="text-muted" style="font-size: 0.7rem;">{{ summary.read_rate }} seen</div>
                    </div>
                    <div class="col-3 position-relative">
                      <div class="position-absolute top-50 start-0 translate-middle text-muted"><i class="bi bi-chevron-right"></i></div>
                      <div class="text-primary text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">4. Debtor Inbound</div>
                      <div class="fw-bold fs-5">{{ summary.inbound }}</div>
                      <div class="text-muted" style="font-size: 0.7rem;">{{ summary.engagement_rate }} engaged</div>
                    </div>
                  </div>

                  <!-- Chart Canvas -->
                  <div style="height: 300px; position: relative;">
                    <canvas ref="funnelChart"></canvas>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Right Sidebar -->
            <div class="col-12 col-lg-4 col-xl-3">
              <div class="d-flex flex-column h-100 gap-3">
                <!-- Spend Breakdown -->
                <div class="card border-0 shadow-sm flex-grow-1">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                      <h6 class="fw-bold mb-0">Spend Breakdown</h6>
                      <i class="bi bi-receipt text-muted"></i>
                    </div>
                    <p class="text-muted small mb-4">Meta Tier Billing & Yield Rate</p>

                    <div class="mb-3" v-if="spend.marketing">
                      <div class="d-flex justify-content-between mb-1">
                        <div class="small fw-semibold"><i class="bi bi-circle-fill text-dark me-1" style="font-size:0.5rem"></i> Marketing</div>
                        <div class="small fw-bold">{{ spend.marketing.cost }}</div>
                      </div>
                      <div class="d-flex justify-content-between text-muted" style="font-size: 0.7rem;">
                        <div>{{ spend.marketing.msgs }} msgs sent</div>
                        <div>{{ spend.marketing.rate }} / msg rate</div>
                      </div>
                      <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-dark" role="progressbar" :style="{ width: spend.marketing.pct + '%' }"></div>
                      </div>
                    </div>

                    <div class="mb-3" v-if="spend.utility">
                      <div class="d-flex justify-content-between mb-1">
                        <div class="small fw-semibold"><i class="bi bi-circle-fill text-success me-1" style="font-size:0.5rem"></i> Utility / Payment Reminders</div>
                        <div class="small fw-bold">{{ spend.utility.cost }}</div>
                      </div>
                      <div class="d-flex justify-content-between text-muted" style="font-size: 0.7rem;">
                        <div>{{ spend.utility.msgs }} msgs sent</div>
                        <div>{{ spend.utility.rate }} / msg rate</div>
                      </div>
                      <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-success" role="progressbar" :style="{ width: spend.utility.pct + '%' }"></div>
                      </div>
                    </div>

                    <div class="mb-3" v-if="spend.auth">
                      <div class="d-flex justify-content-between mb-1">
                        <div class="small fw-semibold"><i class="bi bi-circle-fill text-info me-1" style="font-size:0.5rem"></i> Authentication / Service</div>
                        <div class="small fw-bold">{{ spend.auth.cost }}</div>
                      </div>
                      <div class="d-flex justify-content-between text-muted" style="font-size: 0.7rem;">
                        <div>{{ spend.auth.msgs }} msgs sent</div>
                        <div>{{ spend.auth.rate }} / msg rate</div>
                      </div>
                      <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-info" role="progressbar" :style="{ width: spend.auth.pct + '%' }"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Recovery Multiplier -->
                <div class="card border-0 shadow-sm bg-dark text-white">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 text-success">
                      <div class="text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">RECOVERY MULTIPLIER</div>
                      <i class="bi bi-arrow-repeat fs-5"></i>
                    </div>
                    <h3 class="fw-black mb-1">{{ spend.recovery_multiplier }} <span class="fs-6 text-white-50 fw-normal">recovered / $1.00 spent</span></h3>
                    <p class="text-white-50 mb-0 mt-3" style="font-size: 0.75rem; line-height: 1.5;">
                      Yielding {{ spend.recovery_amount || '$0.00' }} in total debt settlement initiated through direct WhatsApp automated CTA links.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TEMPLATES TAB -->
        <div class="tab-pane fade" id="whatsapp">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold mb-1">
                  WhatsApp Template Performance & Cost Breakdown 
                  <span class="badge bg-light text-dark border ms-2">{{ filteredTemplates.length }} Templates</span>
                  <span v-if="filteredTemplates.length !== tables.templates.length" class="badge bg-light text-muted border ms-1">
                    Filtered of {{ tables.templates.length }}
                  </span>
                </h5>
                <p class="text-muted small mb-0">Granular analytics per pre-approved Meta Business template</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 220px;">
                  <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                  <input
                    type="text"
                    class="form-control border-start-0 shadow-none"
                    placeholder="Filter template..."
                    v-model="templateSearch"
                  >
                  <button v-if="templateSearch" class="btn btn-outline-secondary border-start-0" type="button" @click="templateSearch = ''">
                    <i class="bi bi-x"></i>
                  </button>
                </div>
                <button class="btn btn-sm btn-light border" @click="exportTemplateReport">
                  <i class="bi bi-cloud-download me-1"></i> Export
                </button>
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
                      <th class="text-end">TOTAL SENT</th>
                      <th class="text-end">DELIVERY RATE</th>
                      <th class="text-end">OPT IN / REPLY</th>
                      <th class="text-end">RATE / MSG</th>
                      <th class="text-end">INCURRED COST</th>
                      <th class="text-center">STATUS</th>
                    </tr>
                  </thead>
                  <tbody class="border-top-0">
                    <tr v-if="filteredTemplates.length === 0">
                      <td colspan="9" class="text-center py-4 text-muted">
                        No templates found matching the current criteria.
                      </td>
                    </tr>
                    <tr v-for="(tpl, idx) in filteredTemplates" :key="tpl.id || idx">
                      <td>
                        <div class="fw-bold text-dark">{{ tpl.name }}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">{{ tpl.id }}</div>
                      </td>
                      <td><span class="badge bg-light text-dark border">{{ tpl.category }}</span></td>
                      <td>
                        <div class="fw-semibold text-dark" style="font-size: 0.85rem;">{{ tpl.campaign }}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">{{ tpl.sub_campaign }}</div>
                      </td>
                      <td class="text-end fw-semibold">{{ tpl.sent }}</td>
                      <td class="text-end fw-bold text-success">{{ tpl.delivery }}</td>
                      <td class="text-end">{{ tpl.reply }}</td>
                      <td class="text-end text-muted">{{ tpl.rate }}</td>
                      <td class="text-end fw-bold">{{ tpl.cost }}</td>
                      <td class="text-center">
                        <span class="badge bg-success-subtle text-success">
                          <i class="bi bi-check-circle-fill me-1"></i> {{ tpl.status }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- CAMPAIGNS TAB -->
        <div class="tab-pane fade" id="campaigns">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold mb-1">
                  Campaign Performance Matrix 
                  <span class="badge bg-success-subtle text-success ms-2">{{ filteredCampaigns.length }} Campaigns</span>
                  <span v-if="filteredCampaigns.length !== tables.campaigns.length" class="badge bg-light text-muted border ms-1">
                    Filtered of {{ tables.campaigns.length }}
                  </span>
                </h5>
                <p class="text-muted small mb-0">Unique WhatsApp client responses and payment options per campaign</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 220px;">
                  <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                  <input
                    type="text"
                    class="form-control border-start-0 shadow-none"
                    placeholder="Search campaigns..."
                    v-model="campaignSearch"
                  >
                  <button v-if="campaignSearch" class="btn btn-outline-secondary border-start-0" type="button" @click="campaignSearch = ''">
                    <i class="bi bi-x"></i>
                  </button>
                </div>
                <button class="btn btn-sm btn-light border" @click="exportCampaignReport">
                  <i class="bi bi-cloud-download me-1"></i> Export
                </button>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                 <table class="table table-hover align-middle custom-table">
                  <thead class="text-muted small">
                    <tr>
                      <th>CAMPAIGN NAME</th>
                      <th>BANK / DEPARTMENT</th>
                      <th>ASSIGNED AGENTS</th>
                      <th class="text-end">MESSAGES SENT</th>
                      <th class="text-end">DELIVERED(READ)</th>
                      <th class="text-end">DELIVERED(UNREAD)</th>
                      <th class="text-end">FAILED</th>
                      <th class="text-end">DELIVERY %</th>
                      <th class="text-end">UNIQUE REPLIES</th>
                      <th class="text-end">QUICK REPLIES</th>
                      <th class="text-end">OPT-OUTS</th>
                      <th class="text-end">PTP</th>
                      <th class="text-end">DEBIT ORDER</th>
                      <th class="text-end">EST. META COST</th>
                    </tr>
                  </thead>
                  <tbody class="border-top-0">
                    <tr v-if="filteredCampaigns.length === 0">
                      <td colspan="14" class="text-center py-4 text-muted">
                        No campaigns found matching the current criteria.
                      </td>
                    </tr>
                    <tr v-for="(cmp, idx) in paginatedCampaigns" :key="cmp.id || idx">
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <i
                            class="bi bi-circle-fill"
                            :class="cmp.status === 'Active' ? 'text-success' : (cmp.status === 'Draft' ? 'text-secondary' : 'text-warning')"
                            style="font-size: 0.5rem;"
                          ></i>
                          <div>
                            <div class="fw-bold text-dark">{{ cmp.name }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                              Batch: {{ cmp.batch }} · {{ cmp.status }}
                              <span v-if="cmp.created_at"> · {{ cmp.created_at }}</span>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="fw-semibold text-dark" style="font-size: 0.85rem;">{{ cmp.bank }}</div>
                      </td>
                      <td>
                        <div v-if="cmp.agents.length" class="d-flex position-relative">
                          <div v-for="(agent, aIdx) in cmp.agents" :key="aIdx" class="avatar-sm rounded-circle border border-2 border-white d-flex align-items-center justify-content-center bg-dark text-white shadow-sm" :style="{ marginLeft: aIdx === 0 ? '0' : '-10px', width: '28px', height: '28px', fontSize: '0.65rem' }">
                            {{ agent }}
                          </div>
                        </div>
                        <span v-else class="text-muted">—</span>
                      </td>
                      <td class="text-end fw-semibold">{{ cmp.sent }}</td>
                      <td class="text-end text-success fw-semibold">{{ cmp.delivered_read || '0' }}</td>
                      <td class="text-end text-primary fw-semibold">{{ cmp.delivered_unread || '0' }}</td>
                      <td class="text-end text-danger fw-semibold">{{ cmp.failed || '0' }}</td>
                      <td class="text-end fw-bold" :class="parseFloat(cmp.delivery) >= 90 ? 'text-success' : (parseFloat(cmp.delivery) > 0 ? 'text-warning' : 'text-muted')">
                        <div>{{ cmp.delivery }}</div>
                        <div class="text-muted fw-normal" style="font-size: 0.7rem;">{{ cmp.delivered || '0' }} total</div>
                      </td>
                      <td class="text-end fw-semibold">{{ cmp.replies }}</td>
                      <td class="text-end text-primary fw-semibold">{{ cmp.quick_replies }}</td>
                      <td class="text-end text-danger fw-semibold">{{ cmp.opt_outs }}</td>
                      <td class="text-end text-warning-emphasis fw-semibold">{{ cmp.ptp }}</td>
                      <td class="text-end">
                        <div class="text-success fw-semibold">{{ cmp.debit_order }}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Not set: {{ cmp.payment_not_set }}</div>
                      </td>
                      <td class="text-end">
                        <div class="fw-semibold text-dark">{{ cmp.cost }}</div>
                        <div class="text-muted" style="font-size: 0.7rem;" v-if="cmp.rate && cmp.template_category">
                          {{ cmp.rate }} ({{ cmp.template_category }})
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Pagination Footer -->
            <div class="card-footer bg-white border-top py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
              <div class="d-flex align-items-center gap-3 text-muted small">
                <span>
                  Showing <strong class="text-dark">{{ paginationStartIndex }}</strong> to <strong class="text-dark">{{ paginationEndIndex }}</strong> of <strong class="text-dark">{{ filteredCampaigns.length }}</strong> campaigns
                </span>
                <div class="d-flex align-items-center gap-1">
                  <span>Show</span>
                  <select class="form-select form-select-sm shadow-none" style="width: auto; font-size: 0.8rem;" v-model="perPage">
                    <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                  </select>
                  <span>per page</span>
                </div>
              </div>

              <nav v-if="totalPages > 1" aria-label="Campaign pagination">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button class="page-link shadow-none" type="button" @click="setPage(currentPage - 1)" :disabled="currentPage === 1">
                      <i class="bi bi-chevron-left"></i>
                    </button>
                  </li>
                  <li
                    v-for="(page, pIdx) in displayedPages"
                    :key="pIdx"
                    class="page-item"
                    :class="{ active: page === currentPage, disabled: page === '...' }"
                  >
                    <button v-if="page !== '...'" class="page-link shadow-none" type="button" @click="setPage(page)">
                      {{ page }}
                    </button>
                    <span v-else class="page-link shadow-none border-0 bg-transparent text-muted">...</span>
                  </li>
                  <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                    <button class="page-link shadow-none" type="button" @click="setPage(currentPage + 1)" :disabled="currentPage === totalPages">
                      <i class="bi bi-chevron-right"></i>
                    </button>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
        </div>

        <!-- AGENTS TAB -->
        <div class="tab-pane fade" id="agents">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold mb-1">
                  Agent & User Statistics 
                  <span class="badge bg-light text-dark border ms-2">{{ filteredAgents.length }} Active Specialists</span>
                  <span v-if="filteredAgents.length !== tables.agents.length" class="badge bg-light text-muted border ms-1">
                    Filtered of {{ tables.agents.length }}
                  </span>
                </h5>
                <p class="text-muted small mb-0">Productivity indicators, SLA adherence, and outbound-to-inbound conversion speed</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 220px;">
                  <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                  <input
                    type="text"
                    class="form-control border-start-0 shadow-none"
                    placeholder="Filter specialist..."
                    v-model="agentSearch"
                  >
                  <button v-if="agentSearch" class="btn btn-outline-secondary border-start-0" type="button" @click="agentSearch = ''">
                    <i class="bi bi-x"></i>
                  </button>
                </div>
                <button class="btn btn-sm btn-light border" @click="exportAgentReport">
                  <i class="bi bi-cloud-download me-1"></i> Export
                </button>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover align-middle custom-table">
                  <thead class="text-muted small">
                    <tr>
                      <th>AGENT / USER NAME</th>
                      <th>ROLE</th>
                      <th class="text-center">CAMPAIGNS MANAGED</th>
                      <th class="text-end">MESSAGES DISPATCHED</th>
                      <th class="text-end">FOLLOW-UP REPLY RATE</th>
                      <th class="text-end">INBOUND HANDLED</th>
                      <th class="text-end">AVG RESPONSE TIME</th>
                    </tr>
                  </thead>
                  <tbody class="border-top-0">
                    <tr v-if="filteredAgents.length === 0">
                      <td colspan="7" class="text-center py-4 text-muted">
                        No agent or user statistics found matching the current criteria.
                      </td>
                    </tr>
                    <tr v-for="(agent, idx) in filteredAgents" :key="idx">
                      <td>
                        <div class="d-flex align-items-center gap-3">
                          <div class="avatar-sm rounded-circle d-flex align-items-center justify-content-center bg-dark text-white fw-bold shadow-sm" style="width: 36px; height: 36px; font-size: 0.8rem;">
                            {{ agent.initials }}
                          </div>
                          <div>
                            <div class="fw-bold text-dark">{{ agent.name }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">{{ agent.email }}</div>
                          </div>
                        </div>
                      </td>
                      <td><span class="badge bg-light text-dark border fw-normal text-capitalize">{{ agent.role }}</span></td>
                      <td class="text-center fw-semibold">{{ agent.campaigns }}</td>
                      <td class="text-end fw-semibold">{{ agent.dispatched }}</td>
                      <td class="text-end fw-bold text-success">{{ agent.replyRate }}</td>
                      <td class="text-end">{{ agent.inbound }}</td>
                      <td class="text-end"><span class="badge bg-success-subtle text-success fw-bold" style="font-size: 0.8rem;">{{ agent.responseTime }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top text-muted small">
                <div>Team Response Target: <strong>&lt; 5 mins</strong> (SLA adherence: 100%)</div>
                <router-link to="/system-logs" class="text-decoration-none fw-semibold">View System Audit Logs <i class="bi bi-arrow-right"></i></router-link>
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
  name: 'Analytics',
  data() {
    return {
      loading: true,
      timeframe: 'daily',
      dateRange: 'last_30_days',
      selectedBankId: 'all',
      banks: [],
      campaignSearch: '',
      templateSearch: '',
      agentSearch: '',
      perPage: 25,
      currentPage: 1,
      perPageOptions: [25, 50, 100, 250, 500, 1000, 'All'],
      summary: {
        dispatched: '0',
        delivered: '0',
        read: '0',
        inbound: '0',
        delivery_rate: '0%',
        read_rate: '0%',
        engagement_rate: '0%',
      },
      spend: {
        total: '$0.00',
        avg_per_delivered: '$0.000',
        marketing: { cost: '$0.00', msgs: '0', rate: '$0.00', pct: 0 },
        utility: { cost: '$0.00', msgs: '0', rate: '$0.00', pct: 0 },
        auth: { cost: '$0.00', msgs: '0', rate: '$0.00', pct: 0 },
        recovery_multiplier: '$0.00',
      },
      assets: {
        campaigns: 0,
        templates: 0,
      },
      funnelData: {
        labels: [],
        dispatched: [],
        delivered: [],
        read: [],
        replied: [],
      },
      tables: {
        templates: [],
        campaigns: [],
        agents: [],
      }
    };
  },
  computed: {
    filteredCampaigns() {
      let list = this.tables.campaigns || [];
      if (this.campaignSearch && this.campaignSearch.trim()) {
        const q = this.campaignSearch.trim().toLowerCase();
        list = list.filter(c => 
          (c.name && c.name.toLowerCase().includes(q)) ||
          (c.batch && c.batch.toLowerCase().includes(q)) ||
          (c.bank && c.bank.toLowerCase().includes(q)) ||
          (c.status && c.status.toLowerCase().includes(q)) ||
          (c.template_name && c.template_name.toLowerCase().includes(q)) ||
          (c.template_category && c.template_category.toLowerCase().includes(q))
        );
      }
      return list;
    },
    filteredTemplates() {
      let list = this.tables.templates || [];
      if (this.templateSearch && this.templateSearch.trim()) {
        const q = this.templateSearch.trim().toLowerCase();
        list = list.filter(t => 
          (t.name && t.name.toLowerCase().includes(q)) ||
          (t.category && t.category.toLowerCase().includes(q)) ||
          (t.campaign && t.campaign.toLowerCase().includes(q)) ||
          (t.sub_campaign && t.sub_campaign.toLowerCase().includes(q)) ||
          (t.id && String(t.id).toLowerCase().includes(q))
        );
      }
      return list;
    },
    filteredAgents() {
      let list = this.tables.agents || [];
      if (this.agentSearch && this.agentSearch.trim()) {
        const q = this.agentSearch.trim().toLowerCase();
        list = list.filter(a => 
          (a.name && a.name.toLowerCase().includes(q)) ||
          (a.email && a.email.toLowerCase().includes(q)) ||
          (a.role && a.role.toLowerCase().includes(q))
        );
      }
      return list;
    },
    totalPages() {
      if (this.perPage === 'All' || this.perPage === 'all' || !this.perPage) {
        return 1;
      }
      const count = this.filteredCampaigns.length;
      return Math.max(1, Math.ceil(count / Number(this.perPage)));
    },
    paginatedCampaigns() {
      if (this.perPage === 'All' || this.perPage === 'all') {
        return this.filteredCampaigns;
      }
      const pageSize = Number(this.perPage);
      const start = (this.currentPage - 1) * pageSize;
      return this.filteredCampaigns.slice(start, start + pageSize);
    },
    paginationStartIndex() {
      if (this.filteredCampaigns.length === 0) return 0;
      if (this.perPage === 'All' || this.perPage === 'all') return 1;
      return (this.currentPage - 1) * Number(this.perPage) + 1;
    },
    paginationEndIndex() {
      if (this.filteredCampaigns.length === 0) return 0;
      if (this.perPage === 'All' || this.perPage === 'all') return this.filteredCampaigns.length;
      return Math.min(this.currentPage * Number(this.perPage), this.filteredCampaigns.length);
    },
    displayedPages() {
      const total = this.totalPages;
      const current = this.currentPage;
      if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
      }
      const pages = [];
      pages.push(1);
      if (current > 3) {
        pages.push('...');
      }
      const start = Math.max(2, current - 1);
      const end = Math.min(total - 1, current + 1);
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      if (current < total - 2) {
        pages.push('...');
      }
      pages.push(total);
      return pages;
    }
  },
  watch: {
    campaignSearch() {
      this.currentPage = 1;
    },
    perPage() {
      this.currentPage = 1;
    }
  },
  mounted() {
    this.chartInstance = null; // Store non-reactively
    this.fetchBanks();
    this.fetchData();
  },
  methods: {
    async fetchBanks() {
      try {
        const res = await axios.get('/api/banks', { params: { per_page: 200 } });
        this.banks = res.data.data || res.data || [];
      } catch (e) {
        console.error('Failed to load banks for analytics filter', e);
      }
    },
    onDateFilterChange() {
      this.currentPage = 1;
      this.fetchData();
    },
    onBankFilterChange() {
      this.currentPage = 1;
      this.fetchData();
    },
    setPage(p) {
      if (p === '...' || p < 1 || p > this.totalPages) return;
      this.currentPage = p;
    },
    getDateRangeLabel(key) {
      const map = {
        last_30_days: 'Last 30 Days',
        this_month: 'This Month',
        '3_months': '3 Months',
        '6_months': '6 Months',
        '1_year': '1 Year',
        '2_years': '2 Years',
        year_to_date: 'Year to Date',
        all_time: 'All Time (All Campaigns)',
      };
      return map[key] || key;
    },
    getSelectedBankLabel() {
      if (this.selectedBankId === 'all' || !this.selectedBankId) {
        return `All Institutions (${this.banks.length})`;
      }
      const bank = this.banks.find(b => String(b.id) === String(this.selectedBankId));
      return bank ? bank.name : `Institution ID: ${this.selectedBankId}`;
    },
    setTimeframe(tf) {
      if (this.timeframe === tf) return;
      this.timeframe = tf;
      this.fetchData();
    },
    async fetchData() {
      this.loading = true;
      try {
        const response = await axios.get('/api/analytics', {
          params: {
            timeframe: this.timeframe,
            date_range: this.dateRange,
            bank_id: this.selectedBankId,
            campaign_date_scope: 'filter',
          }
        });
        const data = response.data;
        
        this.summary = data.summary;
        this.spend = data.spend;
        this.assets = data.assets;
        this.funnelData = data.funnel;
        this.tables = data.tables;
      } catch (error) {
        console.error('Failed to load analytics', error);
      } finally {
        this.loading = false;
        this.$nextTick(() => {
          this.initChart();
        });
      }
    },
    exportCampaignReport() {
      const exportList = this.filteredCampaigns.length > 0 ? this.filteredCampaigns : (this.tables.campaigns || []);
      const escapeCsv = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;

      const now = new Date();
      const generatedAt = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;

      const dateRangeLabel = this.getDateRangeLabel(this.dateRange);
      const bankLabel = this.getSelectedBankLabel();

      const cleanInt = (v) => parseInt(String(v ?? '0').replace(/[^0-9]/g, ''), 10) || 0;
      const cleanFloat = (v) => parseFloat(String(v ?? '0').replace(/[^0-9.]/g, '')) || 0;

      // Executive KPI Aggregations
      const totalSent = exportList.reduce((acc, c) => acc + cleanInt(c.sent), 0);
      const totalDeliveredRead = exportList.reduce((acc, c) => acc + cleanInt(c.delivered_read), 0);
      const totalDeliveredUnread = exportList.reduce((acc, c) => acc + cleanInt(c.delivered_unread), 0);
      const totalDeliveredCombined = exportList.reduce((acc, c) => acc + cleanInt(c.delivered), 0);
      const totalFailed = exportList.reduce((acc, c) => acc + cleanInt(c.failed), 0);
      const totalReplies = exportList.reduce((acc, c) => acc + cleanInt(c.replies), 0);
      const totalQuickReplies = exportList.reduce((acc, c) => acc + cleanInt(c.quick_replies), 0);
      const totalOptOuts = exportList.reduce((acc, c) => acc + cleanInt(c.opt_outs), 0);
      const totalPtp = exportList.reduce((acc, c) => acc + cleanInt(c.ptp), 0);
      const totalDebitOrder = exportList.reduce((acc, c) => acc + cleanInt(c.debit_order), 0);
      const totalPaymentNotSet = exportList.reduce((acc, c) => acc + cleanInt(c.payment_not_set), 0);
      const totalCost = exportList.reduce((acc, c) => acc + cleanFloat(c.cost), 0);
      const overallDeliveryRate = totalSent > 0 ? ((totalDeliveredCombined / totalSent) * 100).toFixed(1) + '%' : '0.0%';

      const fileRows = [];

      // SECTION 1: HEADER & METADATA
      fileRows.push(['NEXUS CRM - CAMPAIGN PERFORMANCE & FINANCIAL REPORT']);
      fileRows.push(['Report Generated At', generatedAt]);
      fileRows.push(['Filter Period', dateRangeLabel]);
      fileRows.push(['Institution / Department', bankLabel]);
      if (this.campaignSearch && this.campaignSearch.trim()) {
        fileRows.push(['Search Filter Applied', this.campaignSearch.trim()]);
      }
      fileRows.push([]); // Blank line

      // SECTION 2: EXECUTIVE SUMMARY & TOTALS
      fileRows.push(['EXECUTIVE SUMMARY & TOTALS']);
      fileRows.push(['Total Campaigns Analyzed', exportList.length]);
      fileRows.push(['Total Messages Sent (Dispatched)', totalSent]);
      fileRows.push(['Delivered (Read / Seen)', totalDeliveredRead]);
      fileRows.push(['Delivered (Unread / Received)', totalDeliveredUnread]);
      fileRows.push(['Combined Total Delivered', totalDeliveredCombined]);
      fileRows.push(['Delivery Success Rate', overallDeliveryRate]);
      fileRows.push(['Failed / Undeliverable Messages', totalFailed]);
      fileRows.push(['Unique Inbound Replies', totalReplies]);
      fileRows.push(['Quick Button Replies', totalQuickReplies]);
      fileRows.push(['Opt-Outs / Stop Requests', totalOptOuts]);
      fileRows.push(['Promises to Pay (PTP)', totalPtp]);
      fileRows.push(['Debit Orders Captured', totalDebitOrder]);
      fileRows.push(['Payment Options Not Set', totalPaymentNotSet]);
      fileRows.push(['Total Estimated Meta Cost', '$' + totalCost.toFixed(2)]);
      fileRows.push([]); // Blank line

      // SECTION 3: CAMPAIGN BREAKDOWN MATRIX
      fileRows.push(['CAMPAIGN PERFORMANCE MATRIX BREAKDOWN']);
      const matrixHeaders = [
        'Campaign Name',
        'Campaign ID',
        'Bank / Department',
        'Batch Reference',
        'Status',
        'Created Date',
        'Messages Sent',
        'Delivered (Read)',
        'Delivered (Unread)',
        'Total Delivered',
        'Failed',
        'Delivery %',
        'Unique Replies',
        'Quick Replies',
        'Opt-Outs',
        'PTP',
        'Debit Order',
        'Payment Not Set',
        'Template Category',
        'Rate / Msg',
        'Estimated Meta Cost',
      ];
      fileRows.push(matrixHeaders);

      exportList.forEach((c) => {
        fileRows.push([
          c.name,
          c.id,
          c.bank,
          c.batch,
          c.status,
          c.created_at || 'N/A',
          c.sent,
          c.delivered_read || '0',
          c.delivered_unread || '0',
          c.delivered || '0',
          c.failed || '0',
          c.delivery,
          c.replies,
          c.quick_replies,
          c.opt_outs,
          c.ptp,
          c.debit_order,
          c.payment_not_set,
          c.template_category || 'N/A',
          c.rate || 'N/A',
          c.cost,
        ]);
      });

      // SECTION 4: GRAND TOTAL ROW
      fileRows.push([
        'GRAND TOTAL',
        '',
        '',
        '',
        '',
        '',
        totalSent,
        totalDeliveredRead,
        totalDeliveredUnread,
        totalDeliveredCombined,
        totalFailed,
        overallDeliveryRate,
        totalReplies,
        totalQuickReplies,
        totalOptOuts,
        totalPtp,
        totalDebitOrder,
        totalPaymentNotSet,
        '',
        '',
        '$' + totalCost.toFixed(2),
      ]);

      const csvContent = '\uFEFF' + fileRows
        .map((row) => row.map(escapeCsv).join(','))
        .join('\r\n');

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      const fileDate = now.toISOString().slice(0, 10);
      link.download = `NexusCRM_Campaign_Performance_Report_${this.dateRange}_${fileDate}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    exportTemplateReport() {
      const exportList = this.filteredTemplates.length > 0 ? this.filteredTemplates : (this.tables.templates || []);
      const escapeCsv = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
      const now = new Date();
      const generatedAt = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;

      const cleanInt = (v) => parseInt(String(v ?? '0').replace(/[^0-9]/g, ''), 10) || 0;
      const cleanFloat = (v) => parseFloat(String(v ?? '0').replace(/[^0-9.]/g, '')) || 0;

      const totalSent = exportList.reduce((acc, t) => acc + cleanInt(t.sent), 0);
      const totalCost = exportList.reduce((acc, t) => acc + cleanFloat(t.cost), 0);

      const fileRows = [];
      fileRows.push(['NEXUS CRM - WHATSAPP TEMPLATE PERFORMANCE & COST BREAKDOWN']);
      fileRows.push(['Report Generated At', generatedAt]);
      fileRows.push(['Filter Period', this.getDateRangeLabel(this.dateRange)]);
      fileRows.push(['Institution / Department', this.getSelectedBankLabel()]);
      if (this.templateSearch && this.templateSearch.trim()) {
        fileRows.push(['Search Filter Applied', this.templateSearch.trim()]);
      }
      fileRows.push([]);

      fileRows.push([
        'Template Name',
        'Template ID',
        'Category',
        'Campaigns Used In',
        'Sub Campaign',
        'Total Sent',
        'Delivery Rate',
        'Opt In / Reply Rate',
        'Rate / Msg',
        'Incurred Cost',
        'Status'
      ]);

      exportList.forEach((t) => {
        fileRows.push([
          t.name,
          t.id,
          t.category,
          t.campaign,
          t.sub_campaign || '',
          t.sent,
          t.delivery,
          t.reply,
          t.rate,
          t.cost,
          t.status
        ]);
      });

      fileRows.push([
        'GRAND TOTAL',
        '',
        '',
        '',
        '',
        totalSent,
        '',
        '',
        '',
        '$' + totalCost.toFixed(2),
        ''
      ]);

      const csvContent = '\uFEFF' + fileRows
        .map((row) => row.map(escapeCsv).join(','))
        .join('\r\n');

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      const fileDate = now.toISOString().slice(0, 10);
      link.download = `NexusCRM_WhatsApp_Template_Report_${this.dateRange}_${fileDate}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    exportAgentReport() {
      const exportList = this.filteredAgents.length > 0 ? this.filteredAgents : (this.tables.agents || []);
      const escapeCsv = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
      const now = new Date();
      const generatedAt = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;

      const cleanInt = (v) => parseInt(String(v ?? '0').replace(/[^0-9]/g, ''), 10) || 0;

      const totalCampaigns = exportList.reduce((acc, a) => acc + cleanInt(a.campaigns), 0);
      const totalDispatched = exportList.reduce((acc, a) => acc + cleanInt(a.dispatched), 0);
      const totalInbound = exportList.reduce((acc, a) => acc + cleanInt(a.inbound), 0);

      const fileRows = [];
      fileRows.push(['NEXUS CRM - AGENT & USER PRODUCTIVITY REPORT']);
      fileRows.push(['Report Generated At', generatedAt]);
      fileRows.push(['Filter Period', this.getDateRangeLabel(this.dateRange)]);
      fileRows.push(['Institution / Department', this.getSelectedBankLabel()]);
      if (this.agentSearch && this.agentSearch.trim()) {
        fileRows.push(['Search Filter Applied', this.agentSearch.trim()]);
      }
      fileRows.push([]);

      fileRows.push([
        'Agent / User Name',
        'Email Address',
        'Role',
        'Campaigns Managed',
        'Messages Dispatched',
        'Follow-up Reply Rate',
        'Inbound Handled',
        'Average Response Time'
      ]);

      exportList.forEach((a) => {
        fileRows.push([
          a.name,
          a.email,
          a.role,
          a.campaigns,
          a.dispatched,
          a.replyRate,
          a.inbound,
          a.responseTime
        ]);
      });

      fileRows.push([
        'GRAND TOTAL',
        '',
        '',
        totalCampaigns,
        totalDispatched,
        '',
        totalInbound,
        ''
      ]);

      const csvContent = '\uFEFF' + fileRows
        .map((row) => row.map(escapeCsv).join(','))
        .join('\r\n');

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      const fileDate = now.toISOString().slice(0, 10);
      link.download = `NexusCRM_Agent_Statistics_Report_${this.dateRange}_${fileDate}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    initChart() {
      const canvas = this.$refs.funnelChart;
      if (!canvas) return;

      const ctx = canvas.getContext('2d');
      
      if (this.chartInstance) {
        this.chartInstance.destroy();
      }

      const gradientBase = ctx.createLinearGradient(0, 0, 0, 300);
      gradientBase.addColorStop(0, 'rgba(33, 37, 41, 0.1)');
      gradientBase.addColorStop(1, 'rgba(33, 37, 41, 0)');

      const gradientSuccess = ctx.createLinearGradient(0, 0, 0, 300);
      gradientSuccess.addColorStop(0, 'rgba(25, 135, 84, 0.2)');
      gradientSuccess.addColorStop(1, 'rgba(25, 135, 84, 0)');

      this.chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
          labels: this.funnelData.labels,
          datasets: [
            {
              label: 'Total Sent',
              data: this.funnelData.dispatched,
              borderColor: '#212529',
              backgroundColor: gradientBase,
              borderWidth: 2,
              fill: true,
              tension: 0.4,
              pointRadius: 3,
            },
            {
              label: 'Delivered',
              data: this.funnelData.delivered,
              borderColor: '#198754',
              backgroundColor: gradientSuccess,
              borderWidth: 2,
              fill: true,
              tension: 0.4,
              pointRadius: 3,
            },
            {
              label: 'Read (Seen)',
              data: this.funnelData.read,
              borderColor: '#0dcaf0',
              borderWidth: 2,
              borderDash: [5, 5],
              fill: false,
              tension: 0.4,
              pointRadius: 0,
            },
            {
              label: 'Debtor Inbound Reply',
              data: this.funnelData.replied,
              borderColor: '#20c997',
              borderWidth: 2,
              fill: false,
              tension: 0.4,
              pointRadius: 4,
              pointBackgroundColor: '#fff',
            }
          ]
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
              position: 'bottom',
              labels: {
                usePointStyle: true,
                boxWidth: 8,
                font: { size: 11 }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#000',
              bodyColor: '#333',
              borderColor: '#e9ecef',
              borderWidth: 1,
              padding: 10,
              boxPadding: 4,
              usePointStyle: true,
            }
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { font: { size: 11 }, color: '#6c757d' }
            },
            y: {
              beginAtZero: true,
              grid: { color: '#f8f9fa' },
              ticks: { display: false } // Hide Y axis values to match design
            }
          }
        }
      });
    }
  }
};
</script>

<style scoped>
.custom-tabs {
  padding: 4px;
}
.custom-tabs .nav-link {
  color: #6c757d;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border-radius: 6px;
}
.custom-tabs .nav-link:hover {
  color: #495057;
}
.custom-tabs .nav-link.active {
  background-color: #f8f9fa;
  color: #212529;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.custom-table th {
  text-transform: uppercase;
  font-weight: 700;
  font-size: 0.65rem;
  letter-spacing: 0.05em;
  border-bottom-width: 1px;
  padding: 0.5rem 0.5rem;
}
.custom-table td {
  padding: 0.38rem 0.5rem;
  vertical-align: middle;
}

.bg-success-subtle {
  background-color: #d1e7dd !important;
}
.text-success {
  color: #198754 !important;
}
</style>
