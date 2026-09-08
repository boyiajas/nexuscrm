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
          <select class="form-select border-0 shadow-none fw-semibold" style="font-size: 0.8rem; width: auto;">
            <option>Last 30 Days</option>
            <option>This Month</option>
            <option>Year to Date</option>
          </select>
        </div>
        
        <div class="input-group input-group-sm bg-white shadow-sm border rounded">
          <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-briefcase"></i></span>
          <select class="form-select border-0 shadow-none fw-semibold" style="font-size: 0.8rem; width: auto;">
            <option>All Institutions (12)</option>
            <option>Standard Bank</option>
            <option>FNB</option>
          </select>
        </div>

        <button class="btn btn-sm btn-light border shadow-sm fw-semibold d-flex align-items-center gap-1" @click="fetchData">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
        <button class="btn btn-sm btn-dark shadow-sm fw-semibold d-flex align-items-center gap-1">
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
                      Yielding $206,733 in total debt settlement initiated through direct WhatsApp automated CTA links.
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
                <h5 class="fw-bold mb-1">WhatsApp Template Performance & Cost Breakdown <span class="badge bg-light text-dark border ms-2">{{ tables.templates.length }} Templates</span></h5>
                <p class="text-muted small mb-0">Granular analytics per pre-approved Meta Business template</p>
              </div>
              <div class="d-flex gap-2">
                <input type="text" class="form-control form-control-sm" placeholder="Filter template..." style="width: 200px;" />
                <button class="btn btn-sm btn-light border"><i class="bi bi-file-earmark-excel me-1"></i> CSV</button>
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
                    <tr v-for="(tpl, idx) in tables.templates" :key="idx">
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
                      <td class="text-center"><span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i> {{ tpl.status }}</span></td>
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
                <h5 class="fw-bold mb-1">Campaign Performance Matrix <span class="badge bg-success-subtle text-success ms-2">{{ tables.campaigns.length }} Active Dispatches</span></h5>
                <p class="text-muted small mb-0">Real-time cohort execution, debtor interaction & financial recovery rules</p>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-light border"><i class="bi bi-layout-three-columns me-1"></i> Columns</button>
                <button class="btn btn-sm btn-light border"><i class="bi bi-cloud-download me-1"></i> Export</button>
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
                      <th class="text-end">DELIVERY %</th>
                      <th class="text-end">REPLIES RECEIVED</th>
                      <th class="text-end">META COST</th>
                      <th class="text-end">RECOVERY RATE</th>
                    </tr>
                  </thead>
                  <tbody class="border-top-0">
                    <tr v-for="(cmp, idx) in tables.campaigns" :key="idx">
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i>
                          <div>
                            <div class="fw-bold text-dark">{{ cmp.name }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Batch: {{ cmp.batch }}</div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="fw-semibold text-dark" style="font-size: 0.85rem;">{{ cmp.bank }}</div>
                      </td>
                      <td>
                        <div class="d-flex position-relative">
                          <div v-for="(agent, aIdx) in cmp.agents" :key="aIdx" class="avatar-sm rounded-circle border border-2 border-white d-flex align-items-center justify-content-center bg-dark text-white shadow-sm" :style="{ marginLeft: aIdx === 0 ? '0' : '-10px', width: '28px', height: '28px', fontSize: '0.65rem' }">
                            {{ agent }}
                          </div>
                        </div>
                      </td>
                      <td class="text-end fw-semibold">{{ cmp.sent }}</td>
                      <td class="text-end fw-bold text-success">{{ cmp.delivery }}</td>
                      <td class="text-end">{{ cmp.replies }}</td>
                      <td class="text-end">{{ cmp.cost }}</td>
                      <td class="text-end">
                        <div class="fw-bold text-success">{{ cmp.recoveryPct }}</div>
                        <div class="text-success" style="font-size: 0.75rem;">({{ cmp.recoveryAmt }})</div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- AGENTS TAB -->
        <div class="tab-pane fade" id="agents">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold mb-1">Agent & User Statistics <span class="badge bg-light text-dark border ms-2">{{ tables.agents.length }} Active Specialists</span></h5>
                <p class="text-muted small mb-0">Productivity indicators, SLA adherence, and outbound-to-inbound conversion speed</p>
              </div>
              <button class="btn btn-sm btn-light border"><i class="bi bi-journal-text me-1"></i> Audit Logs</button>
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
                    <tr v-for="(agent, idx) in tables.agents" :key="idx">
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
                      <td class="text-end">{{ agent.dispatched }}</td>
                      <td class="text-end fw-bold text-success">{{ agent.replyRate }}</td>
                      <td class="text-end">{{ agent.inbound }}</td>
                      <td class="text-end"><span class="badge bg-success-subtle text-success fw-bold" style="font-size: 0.8rem;">{{ agent.responseTime }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top text-muted small">
                <div>Mean Team SLA: <strong>4.8 minutes</strong> (target: < 10.0m)</div>
                <a href="#" class="text-decoration-none fw-semibold">View Team Workload Heatmap <i class="bi bi-arrow-right"></i></a>
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
      },
      chartInstance: null
    };
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    setTimeframe(tf) {
      if (this.timeframe === tf) return;
      this.timeframe = tf;
      // In a real app we'd fetch different data based on tf, but for now we just toggle UI state
      // We can also re-init chart if we had weekly data
    },
    async fetchData() {
      this.loading = true;
      try {
        const response = await axios.get('/api/analytics');
        const data = response.data;
        
        this.summary = data.summary;
        this.spend = data.spend;
        this.assets = data.assets;
        this.funnelData = data.funnel;
        this.tables = data.tables;

        this.$nextTick(() => {
          this.initChart();
        });
      } catch (error) {
        console.error('Failed to load analytics', error);
      } finally {
        this.loading = false;
      }
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
}
.custom-table td {
  padding: 1rem 0.5rem;
  vertical-align: middle;
}

.bg-success-subtle {
  background-color: #d1e7dd !important;
}
.text-success {
  color: #198754 !important;
}
</style>
