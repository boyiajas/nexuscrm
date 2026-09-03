<template>
  <div class="row g-0 rounded overflow-hidden shadow-sm chat-wrapper" style="height: calc(100vh - 120px); border: 1px solid #dee2e6;">
    <!-- Sessions list -->
    <div class="col-md-4 border-end d-flex flex-column bg-white h-100">
      <!-- Sidebar Header -->
      <div class="sidebar-header d-flex justify-content-between align-items-center p-3">
        <div class="d-flex align-items-center">
          <div class="avatar bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
            <i class="bi bi-chat-text-fill"></i>
          </div>
          <span class="fw-semibold">Live Chats</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button v-if="canManageChat" class="btn btn-sm btn-outline-primary shadow-none py-1 px-2 text-nowrap" @click="openAddClientModal" title="Add Client To Chat">
            <i class="bi bi-plus-lg"></i> Add Client
          </button>
          <select v-model="filterStatus" class="form-select form-select-sm w-auto shadow-none border-0 bg-transparent fw-semibold text-muted" @change="fetchSessions">
            <option value="all">All</option>
            <option value="active">Active</option>
            <option value="closed">Closed</option>
            <option value="unread">Unread</option>
            <option value="read">Read</option>
          </select>
        </div>
      </div>

      <!-- Search Bar & WABA Filter -->
      <div class="p-2 border-bottom sidebar-search d-flex gap-2">
        <div class="input-group input-group-sm" style="width: 60%;">
          <span class="input-group-text bg-white border-end-0 text-muted">
            <i class="bi bi-search"></i>
          </span>
          <input
            v-model="sidebarSearch"
            type="text"
            class="form-control border-start-0 shadow-none bg-white"
            placeholder="Search name, phone, account..."
            @input="onSidebarSearchInput"
          />
        </div>
        <select v-model="filterWaba" class="form-select form-select-sm shadow-none text-truncate" @change="fetchSessions" style="width: 40%; font-size: 0.825rem;" title="Filter by WhatsApp Number">
          <option value="all">All Numbers</option>
          <option v-for="waba in availableWabas" :key="waba.phone_number_id" :value="waba.phone_number_id">{{ waba.number }}</option>
        </select>
      </div>

      <!-- Segmentation Filters -->
      <div class="p-2 border-bottom sidebar-filters bg-light">
        <div class="d-flex flex-column gap-2">
          <div class="d-flex gap-2">
            <select v-model="filterDepartment" class="form-select form-select-sm shadow-none w-50" @change="fetchSessions">
              <option value="all">All Departments</option>
              <option v-for="dept in availableDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
            </select>
            <select v-model="filterBank" class="form-select form-select-sm shadow-none w-50" @change="fetchSessions">
              <option value="all">All Branches</option>
              <option v-for="bank in availableBanks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Chat List -->
      <div class="chat-list flex-grow-1 overflow-auto position-relative">
        <div v-if="loadingSessions" class="position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-flex justify-content-center pt-5 z-index-1" style="z-index: 10;">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
        <div
          v-for="session in sessions"
          :key="session.id"
          class="chat-list-item d-flex p-2 border-bottom position-relative"
          :class="{ 'active-chat': activeSession && activeSession.id === session.id }"
          @click="openSession(session, $event)"
        >
          <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
            <i class="bi bi-person-fill"></i>
          </div>
          <div class="flex-grow-1 overflow-hidden">
            <div class="d-flex justify-content-between align-items-baseline mb-1">
              <div class="d-flex align-items-center gap-1 overflow-hidden me-1">
                <span class="fw-semibold text-truncate">{{ session.client_name }}</span>
                <div v-if="loadingSessionId === session.id" class="spinner-border spinner-border-sm text-primary flex-shrink-0 ms-1" style="width: 0.85rem; height: 0.85rem; border-width: 0.15em;" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>
              <small class="text-muted timestamp flex-shrink-0">{{ session.updated_at ? session.updated_at.split('T')[0] : '' }}</small>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted text-truncate w-100 pe-2 d-flex align-items-center">
                <i v-if="isLastMessageFromUser(session)" class="bi bi-check2-all text-primary me-1 flex-shrink-0" style="font-size: 1.05rem;" title="Replied"></i>
                <i v-else-if="session.last_message === 'quick reply'" class="bi bi-reply-fill text-muted me-1 flex-shrink-0"></i>
                <span class="text-truncate">{{ session.last_message || 'No messages yet' }}</span>
              </small>
              <div class="d-flex align-items-center gap-2">
                <span v-if="session.unread_count > 0" class="badge rounded-pill bg-success unread-badge">{{ session.unread_count }}</span>
                <div class="dropdown chat-list-dropdown">
                  <i class="bi bi-chevron-down text-muted" style="cursor: pointer; font-size: 1.1rem; transform: translateY(2px); display: inline-block;" data-bs-toggle="dropdown" aria-expanded="false"></i>
                  <ul class="dropdown-menu shadow border-0" style="min-width: 220px;">
                    <li><a class="dropdown-item py-2" href="#" @click.prevent="showContactInfo(session)"><i class="bi bi-person-vcard text-primary me-3"></i>Client Info</a></li>
                    <li><a class="dropdown-item py-2" href="#" @click.prevent="toggleSearch()"><i class="bi bi-search text-muted me-3"></i>Search</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li class="dropdown-header text-uppercase small fw-bold text-muted">Opt-In Status</li>
                    <li>
                      <a class="dropdown-item py-1 text-success d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(session, 'yes')">
                        <span><i class="bi bi-check-circle-fill me-2"></i>Opt-In: Yes</span>
                        <i v-if="(session.client?.opt_in || session.opt_in) === 'yes'" class="bi bi-check2"></i>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item py-1 text-danger d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(session, 'no')">
                        <span><i class="bi bi-x-circle-fill me-2"></i>Opt-In: No</span>
                        <i v-if="(session.client?.opt_in || session.opt_in) === 'no'" class="bi bi-check2"></i>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item py-1 text-secondary d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(session, 'none')">
                        <span><i class="bi bi-dash-circle me-2"></i>Opt-In: None</span>
                        <i v-if="!session.client?.opt_in || session.client?.opt_in === 'none' || session.opt_in === 'none'" class="bi bi-check2"></i>
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="#" @click.prevent="clearChat(session)"><i class="bi bi-eraser text-muted me-3"></i>Clear chat</a></li>
                    <li><a class="dropdown-item py-2 text-danger" href="#" @click.prevent="deleteSession(session)"><i class="bi bi-trash text-danger me-3"></i>Delete chat</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="#" @click.prevent="blockClient(session)"><i class="bi bi-slash-circle text-danger me-3"></i>Block</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="sessions.length === 0 && !loadingSessions" class="p-4 text-center text-muted small">
          No chat sessions found.
        </div>
        <div v-if="sessions.length > 0 && hasMoreSessions" class="p-3 text-center border-top">
          <button class="btn btn-sm btn-outline-primary rounded-pill px-4" @click="fetchSessions(true)" :disabled="loadingSessions">
            <span v-if="loadingSessions" class="spinner-border spinner-border-sm me-2" role="status"></span>
            Load More Chats
          </button>
        </div>
      </div>
    </div>

    <!-- Chat window -->
    <div class="col-md-8 d-flex flex-column bg-chat h-100">
      <div v-if="activeSession" class="d-flex flex-column h-100">
        <!-- Chat Header -->
        <div class="chat-header p-3 d-flex align-items-center border-bottom shadow-sm z-index-1">
          <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
            <i class="bi bi-person-fill"></i>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">{{ activeSession.client_name }}</div>
              <span v-if="activeSession.client?.easy_pay_number" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1" title="Easy Pay Number">
                <i class="bi bi-credit-card me-1"></i>EasyPay: {{ activeSession.client.easy_pay_number }}
              </span>
              <span v-if="(activeSession.client?.opt_in || activeSession.opt_in) === 'yes'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" title="Opted In">
                <i class="bi bi-check-circle-fill me-1"></i>Opt-In: Yes
              </span>
              <span v-else-if="(activeSession.client?.opt_in || activeSession.opt_in) === 'no'" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1" title="Opted Out">
                <i class="bi bi-x-circle-fill me-1"></i>Opt-In: No
              </span>
              <span v-else class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-1" title="Unset">
                <i class="bi bi-dash-circle me-1"></i>Opt-In: None
              </span>
            </div>
            <small class="text-muted">
              {{ activeSession.platform }}
              <span v-if="activeWaba"> • WABA: <strong class="text-dark">{{ activeWaba.label }} ({{ activeWaba.number }})</strong></span>
              <span v-if="activeSession.client?.easy_pay_number"> • EasyPay: <strong class="text-dark">{{ activeSession.client.easy_pay_number }}</strong></span>
              <span v-if="activeSession.agent"> • Assigned to {{ activeSession.agent.name }}</span>
            </small>
          </div>
          <div class="ms-auto text-muted d-flex gap-3 fs-5 align-items-center">
            <i class="bi bi-search" style="cursor: pointer;" title="Search"></i>
            <div class="dropdown">
              <i class="bi bi-three-dots-vertical" style="cursor: pointer;" data-bs-toggle="dropdown" aria-expanded="false" title="Menu"></i>
              <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 220px;">
                <li><a class="dropdown-item py-2" href="#" @click.prevent="showContactInfo(activeSession)"><i class="bi bi-person-vcard me-2 text-primary"></i>Client Info</a></li>
                <li><a class="dropdown-item py-2" href="#" @click.prevent="toggleSearch()"><i class="bi bi-search me-2 text-muted"></i>Search</a></li>
                <li><hr class="dropdown-divider"></li>
                <li class="dropdown-header text-uppercase small fw-bold text-muted">Set Opt-In Status</li>
                <li>
                  <a class="dropdown-item py-1 text-success d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(activeSession, 'yes')">
                    <span><i class="bi bi-check-circle-fill me-2"></i>Opt-In: Yes</span>
                    <i v-if="(activeSession.client?.opt_in || activeSession.opt_in) === 'yes'" class="bi bi-check2"></i>
                  </a>
                </li>
                <li>
                  <a class="dropdown-item py-1 text-danger d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(activeSession, 'no')">
                    <span><i class="bi bi-x-circle-fill me-2"></i>Opt-In: No</span>
                    <i v-if="(activeSession.client?.opt_in || activeSession.opt_in) === 'no'" class="bi bi-check2"></i>
                  </a>
                </li>
                <li>
                  <a class="dropdown-item py-1 text-secondary d-flex align-items-center justify-content-between" href="#" @click.prevent="setOptIn(activeSession, 'none')">
                    <span><i class="bi bi-dash-circle me-2"></i>Opt-In: None</span>
                    <i v-if="!activeSession.client?.opt_in || activeSession.client?.opt_in === 'none' || activeSession.opt_in === 'none'" class="bi bi-check2"></i>
                  </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2" href="#" @click.prevent="clearChat(activeSession)"><i class="bi bi-eraser me-2 text-muted"></i>Clear chat</a></li>
                <li><a class="dropdown-item py-2 text-danger" href="#" @click.prevent="deleteSession(activeSession)"><i class="bi bi-trash text-danger me-2"></i>Delete chat</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="#" @click.prevent="blockClient(activeSession)"><i class="bi bi-slash-circle text-danger me-2"></i>Block</a></li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Search Bar Overlay -->
        <div v-if="isSearching" class="p-2 border-bottom bg-white d-flex align-items-center" style="z-index: 2;">
          <i class="bi bi-search text-muted ms-2"></i>
          <input type="text" class="form-control border-0 shadow-none ms-2" placeholder="Search messages..." v-model="searchQuery" ref="searchInput">
          <i class="bi bi-x-lg text-muted ms-2" style="cursor:pointer;" @click="closeSearch()"></i>
        </div>

        <!-- Messages Area -->
        <div class="chat-messages flex-grow-1 overflow-auto p-4" ref="messagesContainer">
          <!-- Loading State -->
          <div v-if="loadingMessages" class="h-100 d-flex flex-column align-items-center justify-content-center py-5">
            <div class="spinner-border text-primary mb-3" style="width: 2.5rem; height: 2.5rem;" role="status">
              <span class="visually-hidden">Loading conversation history...</span>
            </div>
            <div class="fw-semibold text-secondary mb-1">Loading conversation history...</div>
            <small class="text-muted">Fetching latest WhatsApp messages for {{ activeSession?.client_name }}</small>
          </div>

          <!-- Empty State -->
          <div v-else-if="displayedMessages.length === 0" class="h-100 d-flex flex-column align-items-center justify-content-center text-muted">
            <i class="bi bi-chat-left-dots fs-1 mb-2 opacity-50"></i>
            <span>No messages found in this chat history.</span>
          </div>

          <!-- Messages -->
          <template v-else>
            <!-- Load Previous / View More Button -->
            <div v-if="hasMoreMessages && !searchQuery" class="text-center mb-3">
              <button
                type="button"
                class="btn btn-sm btn-light border rounded-pill px-3 py-1 shadow-sm text-secondary"
                style="font-size: 0.825rem; background-color: #ffffff;"
                @click="loadMoreMessages"
              >
                <i class="bi bi-clock-history me-1 text-primary"></i>
                View More Messages ({{ totalRemainingMessages }} older message{{ totalRemainingMessages === 1 ? '' : 's' }})
              </button>
            </div>

            <div
              v-for="msg in displayedMessages"
              :key="msg.id"
              class="message-wrapper d-flex mb-1"
              :class="msg.sender === 'agent' ? 'justify-content-end' : 'justify-content-start'"
            >
              <div class="chat-bubble position-relative shadow-sm" :class="msg.sender === 'agent' ? 'bubble-out' : 'bubble-in'">
                <!-- Media Attachment Preview -->
                <div v-if="msg.media_url" class="media-preview mb-2">
                  <template v-if="msg.media_type === 'image'">
                    <a :href="msg.media_url" target="_blank" rel="noopener">
                      <img :src="msg.media_url" class="img-fluid rounded border" style="max-height: 250px; object-fit: cover;" alt="Attachment" />
                    </a>
                  </template>
                  <template v-else-if="msg.media_type === 'video'">
                    <video :src="msg.media_url" controls class="w-100 rounded border" style="max-height: 250px;"></video>
                  </template>
                  <template v-else-if="msg.media_type === 'audio'">
                    <audio :src="msg.media_url" controls class="w-100 mb-1"></audio>
                  </template>
                  <template v-else>
                    <a :href="msg.media_url" target="_blank" rel="noopener" class="btn btn-sm btn-light border text-start text-dark d-inline-flex align-items-center gap-2">
                      <i class="bi bi-file-earmark-arrow-down-fill text-primary fs-5"></i>
                      <span class="text-truncate" style="max-width: 200px;">Download Attachment</span>
                    </a>
                  </template>
                </div>

                <div class="message-content">
                  {{ msg.content }}
                </div>
                <div class="message-meta d-flex justify-content-end align-items-center mt-1">
                  <small class="timestamp text-muted ms-3">
                    {{ formatTime(msg.sent_at || msg.created_at) }}
                  </small>
                  <i v-if="msg.sender === 'agent'" class="bi bi-check-all ms-1 text-primary" style="font-size: 1.1em;"></i>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Attachment Preview Banner -->
        <div v-if="selectedFile" class="px-3 py-2 bg-light border-top border-bottom d-flex align-items-center justify-content-between text-muted small">
          <span class="text-truncate me-2">
            <i class="bi bi-paperclip me-1 text-primary"></i>
            <strong>Attachment:</strong> {{ selectedFile.name }} ({{ formatFileSize(selectedFile.size) }})
          </span>
          <button type="button" class="btn-close btn-close-sm" @click="clearSelectedFile" aria-label="Remove file"></button>
        </div>

        <!-- Composer -->
        <div class="chat-composer p-3 border-top">
          <form @submit.prevent="sendMessage" class="d-flex align-items-center">
            <input type="file" ref="fileInput" class="d-none" @change="onFileSelected" />
            <button type="button" class="btn btn-link text-muted fs-4 p-0 me-3 shadow-none" @click="triggerFileInput" title="Attach file" :disabled="!activeSession || loadingMessages || !canManageChat || liveChatLocked">
              <i class="bi bi-paperclip" :class="{'text-primary': selectedFile}"></i>
            </button>
            <textarea
              v-model="newMessage"
              class="form-control rounded-4 border-0 shadow-none py-2 px-3 flex-grow-1 me-3"
              :class="{'locked-input': liveChatLocked}"
              style="background-color: #ffffff; resize: none; overflow-y: auto; line-height: 1.5;"
              rows="1"
              :placeholder="liveChatLocked ? liveChatLockedMessage : 'Type a message (Shift+Enter for new line)'"
              :disabled="!activeSession || loadingMessages || !canManageChat || uploadingFile || liveChatLocked"
              @keydown.enter.exact.prevent="sendMessage"
              @input="adjustTextareaHeight"
              ref="messageInput"
            ></textarea>
            <button type="submit" class="btn text-muted fs-4 p-0 shadow-none" :disabled="!activeSession || loadingMessages || (!newMessage.trim() && !selectedFile) || !canManageChat || uploadingFile || liveChatLocked">
              <span v-if="uploadingFile" class="spinner-border spinner-border-sm text-primary" role="status"></span>
              <i v-else class="bi bi-send-fill" :class="{'text-primary': newMessage.trim() || selectedFile}"></i>
            </button>
          </form>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="h-100 d-flex flex-column align-items-center justify-content-center bg-chat text-muted">
        <div class="text-center">
          <i class="bi bi-whatsapp" style="font-size: 5rem; color: #d1d7db;"></i>
          <h4 class="mt-4 fw-light text-secondary">WhatsApp Web</h4>
          <p class="small text-muted mt-2">Select a chat session to start messaging.</p>
        </div>
      </div>
    </div>
    
    <!-- Client Info Modal -->
    <div class="modal fade" id="contactInfoModal" tabindex="-1" ref="contactInfoModal">
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow border-0">
          <div class="modal-header border-bottom py-3">
            <h5 class="modal-title h6 mb-0 text-dark fw-bold">
              <i class="bi bi-person-vcard text-primary me-2"></i>Client Information
            </h5>
            <button type="button" class="btn-close" @click="closeContactInfoModal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4" v-if="contactInfoSession">
            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
              <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 52px; height: 52px; font-size: 1.8rem;">
                <i class="bi bi-person-fill"></i>
              </div>
              <div>
                <h5 class="mb-1 text-dark fw-bold">{{ contactInfoSession.client_name }}</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <span v-if="contactInfoSession.client?.status" class="badge bg-secondary">
                    {{ contactInfoSession.client.status }}
                  </span>
                  <span v-if="(contactInfoSession.client?.opt_in || contactInfoSession.opt_in) === 'yes'" class="badge bg-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Opt-In: Yes
                  </span>
                  <span v-else-if="(contactInfoSession.client?.opt_in || contactInfoSession.opt_in) === 'no'" class="badge bg-danger">
                    <i class="bi bi-x-circle-fill me-1"></i>Opt-In: No
                  </span>
                  <span v-else class="badge bg-secondary">
                    <i class="bi bi-dash-circle me-1"></i>Opt-In: None
                  </span>
                </div>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Easy Pay Number</label>
                <div class="fw-bold text-primary font-monospace bg-light p-2 rounded border">
                  {{ contactInfoSession.client?.easy_pay_number || '-' }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Account Number</label>
                <div class="fw-semibold text-dark bg-light p-2 rounded border font-monospace">
                  {{ contactInfoSession.client?.account_number || '-' }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">ID Number</label>
                <div class="text-dark bg-light p-2 rounded border small">
                  {{ contactInfoSession.client?.id_number || '-' }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Phone Number</label>
                <div class="text-dark bg-light p-2 rounded border small">
                  {{ contactInfoSession.client?.phone || contactInfoSession.phone || '-' }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Outstanding Balance</label>
                <div class="fw-semibold text-danger bg-light p-2 rounded border">
                  {{ formatCurrency(contactInfoSession.client?.outstanding_balance) }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Arrears Amount</label>
                <div class="fw-semibold text-dark bg-light p-2 rounded border">
                  {{ formatCurrency(contactInfoSession.client?.arrears_amount) }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Installment Amount</label>
                <div class="text-dark bg-light p-2 rounded border small">
                  {{ formatCurrency(contactInfoSession.client?.installment_amount) }}
                </div>
              </div>

              <div class="col-6">
                <label class="small text-muted fw-medium d-block mb-1">Bank / Institution</label>
                <div class="text-dark bg-light p-2 rounded border small text-truncate">
                  {{ contactInfoSession.client?.bank?.name || contactInfoSession.client?.bank_name || '-' }}
                </div>
              </div>

              <div class="col-12">
                <label class="small text-muted fw-medium d-block mb-1">Email Address</label>
                <div class="text-dark bg-light p-2 rounded border small">
                  {{ contactInfoSession.client?.email || '-' }}
                </div>
              </div>

              <div class="col-12" v-if="contactInfoSession.client?.opt_in_updated_at || contactInfoSession.client?.whatsapp_opted_in_at">
                <label class="small text-muted fw-medium d-block mb-1">Opt-In Updated Timestamp</label>
                <div class="text-muted bg-light p-2 rounded border small">
                  <i class="bi bi-clock me-1"></i>
                  {{ contactInfoSession.client?.opt_in_updated_at || contactInfoSession.client?.whatsapp_opted_in_at }}
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light py-2">
            <button type="button" class="btn btn-secondary btn-sm" @click="closeContactInfoModal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Client to Chat Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1" ref="addClientModal">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
          <div class="modal-header border-bottom py-3">
            <h5 class="modal-title h6 mb-0 text-dark fw-bold">
              <i class="bi bi-person-plus text-primary me-2"></i>Add Client to Chat
            </h5>
            <button type="button" class="btn-close" @click="closeAddClientModal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <form @submit.prevent="submitNewClient" id="addClientForm">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold text-dark">First Name <span class="text-danger">*</span></label>
                  <input v-model="newClientForm.first_name" type="text" class="form-control form-control-sm shadow-none" required placeholder="John">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold text-dark">Surname <span class="text-danger">*</span></label>
                  <input v-model="newClientForm.surname" type="text" class="form-control form-control-sm shadow-none" required placeholder="Doe">
                </div>
                <div class="col-12">
                  <label class="form-label small fw-semibold text-dark">WhatsApp Number <span class="text-danger">*</span></label>
                  <input v-model="newClientForm.phone" type="text" class="form-control form-control-sm shadow-none" required placeholder="e.g. +27821234567">
                  <div class="form-text" style="font-size: 0.75rem;">Include country code (e.g., +27).</div>
                </div>
                <div class="col-12">
                  <label class="form-label small fw-semibold text-dark">Department(s) <span class="text-danger">*</span></label>
                  <VueMultiselect
                    v-model="newClientForm.departments"
                    :options="availableDepartments"
                    :multiple="true"
                    :close-on-select="false"
                    :clear-on-select="false"
                    :preserve-search="true"
                    placeholder="Select Department(s)"
                    label="name"
                    track-by="id"
                    :preselect-first="false"
                  />
                  <div class="form-text" style="font-size: 0.75rem;">Select one or more departments.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold text-dark">Bank / Branch</label>
                  <select v-model="newClientForm.bank_id" class="form-select form-select-sm shadow-none">
                    <option value="">Default (Your Bank)</option>
                    <option v-for="bank in availableBanks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold text-dark">Opt-In Status</label>
                  <select v-model="newClientForm.opt_in" class="form-select form-select-sm shadow-none">
                    <option value="yes">Opt-In: Yes</option>
                    <option value="no">Opt-In: No</option>
                    <option value="none">Opt-In: None</option>
                  </select>
                </div>
                <div class="col-12 mt-4 pt-3 border-top">
                  <label class="form-label small fw-semibold text-primary"><i class="bi bi-whatsapp me-1"></i> Chat From WABA Number <span class="text-danger">*</span></label>
                  <select v-model="newClientForm.waba_number" class="form-select form-select-sm shadow-none border-primary" required>
                    <option value="" disabled>Select WABA Number...</option>
                    <option v-for="waba in availableWabas" :key="waba.phone_number_id" :value="waba.phone_number_id">{{ waba.number }} - {{ waba.label }}</option>
                  </select>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer bg-light py-2">
            <button type="button" class="btn btn-secondary btn-sm shadow-none" @click="closeAddClientModal" :disabled="isSubmittingClient">Cancel</button>
            <button type="submit" form="addClientForm" class="btn btn-primary btn-sm shadow-none" :disabled="isSubmittingClient">
              <span v-if="isSubmittingClient" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
              Create & Chat
            </button>
          </div>
        </div>
      </div>
    </div>
    
  </div>
</template>

<script>
import axios from '../axios';
import { notify } from '../utils/notify';
import { createManagedModal, disposeManagedModal } from '../utils/modal';
import VueMultiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import './Chat.css';

export default {
  name: 'ChatView',
  components: {
    VueMultiselect
  },
  data() {
    return {
      sessions: [],
      activeSession: null,
      messages: [],
      visibleCount: 20,
      newMessage: '',
      selectedFile: null,
      uploadingFile: false,
      filterStatus: 'all',
      sidebarSearch: '',
      searchTimeout: null,
      pollingInterval: null,
      isSearching: false,
      searchQuery: '',
      contactInfoSession: null,
      modalInstance: null,
      showClientInfoModal: false,
      loadingSessionId: null,
      loadingMessages: false,
      loadingSessions: false,
      currentSessionPage: 1,
      hasMoreSessions: false,
      filterDepartment: 'all',
      filterBank: 'all',
      filterWaba: 'all',
      availableDepartments: [],
      availableBanks: [],
      availableWabas: [],
      liveChatLocked: false,
      liveChatLockedMessage: '',
      addClientModalInstance: null,
      isSubmittingClient: false,
      newClientForm: {
        first_name: '',
        surname: '',
        phone: '',
        departments: [],
        bank_id: '',
        waba_number: '',
        opt_in: 'yes',
      },
    };
  },
  computed: {
    currentUser() {
      try {
        return JSON.parse(localStorage.getItem('nexus_user') || '{}');
      } catch {
        return {};
      }
    },
    currentRoleCodes() {
      if (Array.isArray(this.currentUser?.role_codes) && this.currentUser.role_codes.length) {
        return this.currentUser.role_codes;
      }

      return this.currentUser?.role ? [this.currentUser.role] : [];
    },
    canManageChat() {
      return this.hasPermission('send_whatsapp');
    },
    filteredMessages() {
      if (!this.searchQuery) return this.messages;
      const query = this.searchQuery.toLowerCase();
      return this.messages.filter(msg => msg.content && msg.content.toLowerCase().includes(query));
    },
    displayedMessages() {
      if (this.searchQuery) return this.filteredMessages;
      if (this.messages.length <= this.visibleCount) return this.messages;
      return this.messages.slice(this.messages.length - this.visibleCount);
    },
    hasMoreMessages() {
      if (this.searchQuery) return false;
      return this.messages.length > this.visibleCount;
    },
    totalRemainingMessages() {
      if (this.messages.length <= this.visibleCount) return 0;
      return this.messages.length - this.visibleCount;
    },
    activeWaba() {
      if (!this.activeSession || !this.activeSession.waba_phone_number_id) return null;
      return this.availableWabas.find(w => String(w.phone_number_id) === String(this.activeSession.waba_phone_number_id)) || null;
    }
  },
  mounted() {
    this.loadFilters();
    this.fetchSessions().then(() => {
      this.handleQueryClient();
    });
    this.startPolling();
  },
  beforeUnmount() {
    this.stopPolling();
    if (this.modalInstance) {
      try {
        disposeManagedModal(this.modalInstance);
      } catch (e) {
        // ignore
      }
      this.modalInstance = null;
    }
  },
  methods: {
    hasPermission(permCode) {
      if (!this.currentUser) return false;
      if (this.currentRoleCodes.includes('SUPER_ADMIN') || this.currentRoleCodes.includes('ADMIN')) {
        return true;
      }

      if (Array.isArray(this.currentUser.permission_codes)) {
        return this.currentUser.permission_codes.includes(permCode);
      }

      return false;
    },
    isLastMessageFromUser(session) {
      if (!session || !session.last_message) return false;

      // 1. Active session with locally loaded messages
      if (this.activeSession && this.activeSession.id === session.id && this.messages && this.messages.length > 0) {
        const lastMsg = this.messages[this.messages.length - 1];
        if (lastMsg) {
          return lastMsg.sender !== 'client';
        }
      }

      // 2. Eager loaded latest_message object
      if (session.latest_message && session.latest_message.sender) {
        return session.latest_message.sender !== 'client';
      }

      // 3. Fallback direct last_sender check
      if (session.last_sender) {
        return session.last_sender !== 'client';
      }

      return false;
    },
    fetchSessions(loadMore = false) {
      if (loadMore === true) {
        this.currentSessionPage++;
      } else {
        this.currentSessionPage = 1;
      }
      this.loadingSessions = true;
      return axios
        .get('/api/chat/sessions', {
          params: {
            status: this.filterStatus,
            search: this.sidebarSearch,
            department_id: this.filterDepartment,
            bank_id: this.filterBank,
            waba_number: this.filterWaba,
            per_page: 100,
            page: this.currentSessionPage,
          },
        })
        .then((res) => {
          const fetchedData = res.data.data || res.data;
          if (loadMore === true) {
            this.sessions = [...this.sessions, ...fetchedData];
          } else {
            this.sessions = fetchedData;
          }

          if (res.data.meta && res.data.meta.current_page < res.data.meta.last_page) {
            this.hasMoreSessions = true;
          } else if (res.data.last_page && res.data.current_page < res.data.last_page) {
            this.hasMoreSessions = true;
          } else {
            this.hasMoreSessions = false;
          }
        })
        .finally(() => {
          this.loadingSessions = false;
        });
    },
    onSidebarSearchInput() {
      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout);
      }
      this.searchTimeout = setTimeout(() => {
        this.fetchSessions();
      }, 300);
    },
    openSession(session, event = null) {
      if (event && event.target.closest('.chat-list-dropdown')) {
        return;
      }
      this.activeSession = session;
      this.loadingSessionId = session.id;
      this.loadingMessages = true;
      this.messages = [];
      this.visibleCount = 20;

      axios.get(`/api/chat/sessions/${session.id}`).then((res) => {
        this.activeSession = res.data;
        this.messages = res.data.messages || [];
        this.$nextTick(this.scrollToBottom);
      }).catch((err) => {
        console.error('Failed to load chat history', err);
        notify.error('Failed to load chat history.', 'Chat');
      }).finally(() => {
        this.loadingSessionId = null;
        this.loadingMessages = false;
      });
    },
    loadMoreMessages() {
      const container = this.$refs.messagesContainer;
      const oldScrollHeight = container ? container.scrollHeight : 0;
      const oldScrollTop = container ? container.scrollTop : 0;

      this.visibleCount += 20;

      this.$nextTick(() => {
        if (container) {
          container.scrollTop = container.scrollHeight - oldScrollHeight + oldScrollTop;
        }
      });
    },
    startPolling() {
      this.stopPolling();
      this.pollingInterval = setInterval(() => {
        this.pollData();
      }, 5000);
    },
    stopPolling() {
      if (this.pollingInterval) {
        clearInterval(this.pollingInterval);
        this.pollingInterval = null;
      }
    },
    pollData() {
      // Soft refresh sidebar (only when not actively typing search)
      if (!this.sidebarSearch) {
        axios
          .get('/api/chat/sessions', {
            params: {
              status: this.filterStatus,
              search: this.sidebarSearch,
              department_id: this.filterDepartment,
              bank_id: this.filterBank,
              waba_number: this.filterWaba,
              per_page: this.currentSessionPage * 100,
              page: 1,
            },
          })
          .then((res) => {
            this.sessions = res.data.data || res.data;
          });
      }

      // Soft refresh active session messages (only when not loading a new session)
      if (this.activeSession && !this.loadingMessages) {
        axios.get(`/api/chat/sessions/${this.activeSession.id}`).then((res) => {
          const fetchedMessages = res.data.messages || [];
          if (fetchedMessages.length > this.messages.length) {
            this.messages = fetchedMessages;
            this.$nextTick(this.scrollToBottom);
          }
        });
      }
    },
    handleQueryClient() {
      const clientId = this.$route.query.client_id;
      const sessionId = this.$route.query.session_id;

      if (sessionId) {
        axios.get(`/api/chat/sessions/${sessionId}`).then((res) => {
          this.activeSession = res.data;
          this.messages = res.data.messages || [];
          this.visibleCount = 20;
          this.fetchSessions();
          this.$nextTick(this.scrollToBottom);
        }).catch((err) => {
          console.error('Unable to open chat session by ID', err);
        });
        return;
      }

      if (!clientId || !this.canManageChat) return;

      axios
        .post('/api/chat/session-for-client', {
          client_id: clientId,
          platform: 'whatsapp',
        })
        .then((res) => {
          this.activeSession = res.data;
          this.messages = res.data.messages || [];
          this.visibleCount = 20;
          this.fetchSessions();
          this.$nextTick(this.scrollToBottom);
        })
        .catch((err) => {
          console.error('Unable to open chat for client', err);
        });
    },
    adjustTextareaHeight(e) {
      const el = e.target;
      el.style.height = 'auto';
      el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    },
    triggerFileInput() {
      if (this.$refs.fileInput) {
        this.$refs.fileInput.click();
      }
    },
    onFileSelected(event) {
      const files = event.target.files;
      if (files && files.length > 0) {
        this.selectedFile = files[0];
      }
    },
    clearSelectedFile() {
      this.selectedFile = null;
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = '';
      }
    },
    formatTime(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
    formatFileSize(bytes) {
      if (!bytes) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },
    sendMessage() {
      if (!this.canManageChat || !this.activeSession || this.uploadingFile) return;

      const content = this.newMessage.trim();
      if (!content && !this.selectedFile) return;

      this.uploadingFile = true;

      let requestPromise;
      if (this.selectedFile) {
        const formData = new FormData();
        if (content) {
          formData.append('content', content);
        }
        formData.append('file', this.selectedFile);
        formData.append('is_template', '0');

        requestPromise = axios.post(`/api/chat/sessions/${this.activeSession.id}/messages`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
      } else {
        requestPromise = axios.post(`/api/chat/sessions/${this.activeSession.id}/messages`, {
          content,
          is_template: false,
        });
      }

      requestPromise
        .then((res) => {
          const exists = this.messages.some(m => m.id === res.data.id);
          if (!exists) {
            this.messages.push(res.data);
          }
          this.newMessage = '';
          if (this.$refs.messageInput) {
            this.$refs.messageInput.style.height = 'auto';
          }
          this.clearSelectedFile();
          this.$nextTick(this.scrollToBottom);
          this.fetchSessions();
        })
        .catch((err) => {
          console.error('Failed to send message', err);
          notify.error(err.response?.data?.message || 'Failed to send message.');
        })
        .finally(() => {
          this.uploadingFile = false;
        });
    },
    scrollToBottom() {
      const el = this.$refs.messagesContainer;
      if (el) {
        el.scrollTop = el.scrollHeight;
      }
    },
    deleteSession(session) {
      if (!this.canManageChat || !session) return;
      if (confirm(`Are you sure you want to delete the chat session with ${session.client_name}? This will permanently remove the chat history.`)) {
        axios.delete(`/api/chat/sessions/${session.id}`).then(() => {
          if (this.activeSession && this.activeSession.id === session.id) {
            this.activeSession = null;
            this.messages = [];
          }
          this.fetchSessions();
        }).catch((err) => {
          console.error('Failed to delete session', err);
          alert('Failed to delete chat session.');
        });
      }
    },
    clearChat(session) {
      if (!this.canManageChat || !session) return;
      if (confirm(`Are you sure you want to clear the chat history for ${session.client_name}?`)) {
        axios.post(`/api/chat/sessions/${session.id}/clear`).then(() => {
          if (this.activeSession && this.activeSession.id === session.id) {
            this.messages = [];
          }
          this.fetchSessions();
        }).catch((err) => {
          console.error('Failed to clear chat', err);
          alert('Failed to clear chat session.');
        });
      }
    },
    blockClient(session) {
      if (!this.canManageChat || !session) return;
      if (confirm(`Are you sure you want to block ${session.client_name}? They will be opted out of WhatsApp communications.`)) {
        axios.post(`/api/chat/sessions/${session.id}/block`).then(() => {
          if (this.activeSession && this.activeSession.id === session.id) {
            this.activeSession = null;
            this.messages = [];
          }
          this.fetchSessions();
        }).catch((err) => {
          console.error('Failed to block client', err);
          alert('Failed to block client.');
        });
      }
    },
    setOptIn(session, status) {
      if (!this.canManageChat || !session) return;
      axios.post(`/api/chat/sessions/${session.id}/opt-in`, { opt_in: status }).then((res) => {
        notify.success(res.data.message || 'Opt-in status updated successfully.', 'Opt-In');
        if (session.client) {
          session.client.opt_in = res.data.opt_in;
          session.client.opt_in_updated_at = res.data.opt_in_updated_at;
        } else {
          session.opt_in = res.data.opt_in;
        }
        if (this.activeSession && this.activeSession.id === session.id) {
          if (this.activeSession.client) {
            this.activeSession.client.opt_in = res.data.opt_in;
            this.activeSession.client.opt_in_updated_at = res.data.opt_in_updated_at;
          } else {
            this.activeSession.opt_in = res.data.opt_in;
          }
        }
      }).catch((err) => {
        console.error('Failed to update opt-in status', err);
        notify.error('Failed to update opt-in status.');
      });
    },
    loadFilters() {
      axios.get('/api/chat/filters').then((res) => {
        this.availableBanks = res.data.banks || [];
        this.availableDepartments = res.data.departments || [];
        this.availableWabas = res.data.wabas || [];
        this.liveChatLocked = res.data.liveChatLocked || false;
        this.liveChatLockedMessage = res.data.liveChatLockedMessage || 'Live chat is temporarily disabled.';
      }).catch((err) => {
        console.error('Failed to load chat filters', err);
      });
    },
    openAddClientModal() {
      let initialDepartments = [];
      if (this.filterDepartment !== 'all') {
        const found = this.availableDepartments.find(d => String(d.id) === String(this.filterDepartment));
        if (found) {
          initialDepartments.push(found);
        }
      }
      this.newClientForm = {
        first_name: '',
        surname: '',
        phone: '',
        departments: initialDepartments,
        bank_id: this.filterBank !== 'all' ? this.filterBank : (this.availableBanks[0]?.id || ''),
        waba_number: this.filterWaba !== 'all' ? this.filterWaba : (this.availableWabas[0]?.phone_number_id || ''),
        opt_in: 'yes',
      };
      
      this.$nextTick(() => {
        if (this.$refs.addClientModal) {
          if (!this.addClientModalInstance) {
            this.addClientModalInstance = createManagedModal(this.$refs.addClientModal);
          }
          this.addClientModalInstance.show();
        }
      });
    },
    closeAddClientModal() {
      if (this.addClientModalInstance) {
        this.addClientModalInstance.hide();
      }
    },
    submitNewClient() {
      if (!this.newClientForm.first_name || !this.newClientForm.surname || !this.newClientForm.phone || this.newClientForm.departments.length === 0 || !this.newClientForm.waba_number) {
        notify.error('Please fill in all mandatory fields.');
        return;
      }
      
      this.isSubmittingClient = true;
      
      const payload = {
        ...this.newClientForm,
        department_ids: this.newClientForm.departments.map(d => d.id)
      };
      delete payload.departments;

      axios.post('/api/clients', payload)
      .then((res) => {
        const newClient = res.data;
        notify.success('Client added successfully.');
        this.closeAddClientModal();
        
        // Open the chat session
        axios.post('/api/chat/session-for-client', {
          client_id: newClient.id,
          platform: 'whatsapp',
          waba_number: this.newClientForm.waba_number,
        }).then((sessionRes) => {
          this.activeSession = sessionRes.data;
          this.messages = sessionRes.data.messages || [];
          this.visibleCount = 20;
          this.fetchSessions();
          this.$nextTick(this.scrollToBottom);
        }).catch((err) => {
          console.error('Unable to open chat for new client', err);
          notify.error('Client created, but failed to open chat session automatically.');
          this.fetchSessions();
        });
      })
      .catch((err) => {
        console.error('Failed to create client', err);
        notify.error(err.response?.data?.message || 'Failed to create client.');
      })
      .finally(() => {
        this.isSubmittingClient = false;
      });
    },
    showContactInfo(session) {
      if (!session) return;
      const openModal = (data) => {
        this.contactInfoSession = data;
        this.$nextTick(() => {
          if (this.$refs.contactInfoModal) {
            if (!this.modalInstance) {
              this.modalInstance = createManagedModal(this.$refs.contactInfoModal);
            }
            if (this.modalInstance) {
              this.modalInstance.show();
            }
          }
        });
      };

      if (session.id) {
        axios.get(`/api/chat/sessions/${session.id}`).then((res) => {
          openModal(res.data);
        }).catch(() => {
          openModal(session);
        });
      } else {
        openModal(session);
      }
    },
    closeContactInfoModal() {
      if (this.modalInstance) {
        this.modalInstance.hide();
      }
    },
    formatCurrency(val) {
      if (val === null || val === undefined || val === '') return '-';
      const num = Number(val);
      if (isNaN(num)) return '-';
      return 'R ' + num.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    toggleSearch() {
      this.isSearching = true;
      this.$nextTick(() => {
        if (this.$refs.searchInput) {
          this.$refs.searchInput.focus();
        }
      });
    },
    closeSearch() {
      this.isSearching = false;
      this.searchQuery = '';
    }
  },
};
</script>

<style scoped>
.locked-input::placeholder {
  color: #dc3545 !important;
  opacity: 1 !important;
}

/* Preserve existing multiselect tag color if used elsewhere in the view */
:deep(.multiselect__tag) {
  background: #0d6efd;
}
</style>
