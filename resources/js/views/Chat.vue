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
          <button v-if="canManageChat" class="btn btn-sm btn-outline-primary shadow-none py-1 px-2 text-nowrap" @click="openAddClientModal()" title="Add Client To Chat">
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
          <option v-for="waba in availableWabas" :key="waba.phone_number_id" :value="waba.phone_number_id">
            {{ waba.number }}{{ waba.bank_name ? ` (${waba.bank_name})` : (waba.label ? ` (${waba.label})` : '') }}
          </option>
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
        <template v-for="(session, index) in sessions" :key="session.id">
          <!-- Section divider when searching -->
          <div
            v-if="sidebarSearch && showSectionHeader(session, index)"
            class="px-3 py-1 bg-light text-muted small fw-bold text-uppercase border-bottom"
            :class="{ 'border-top': index > 0 }"
            style="font-size: 0.72rem; letter-spacing: 0.5px;"
          >
            <i :class="session.is_client_only ? 'bi bi-people-fill me-1 text-primary' : 'bi bi-chat-dots-fill me-1 text-secondary'"></i>
            {{ session.is_client_only ? 'Clients Directory' : 'Conversations' }}
          </div>

          <div
            class="chat-list-item d-flex p-2 border-bottom position-relative"
            :class="{ 'active-chat': activeSession && activeSession.id === session.id }"
            @click="openSession(session, $event)"
          >
            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
              <i :class="session.is_client_only ? 'bi bi-person' : 'bi bi-person-fill'"></i>
            </div>
            <div class="flex-grow-1 overflow-hidden">
              <div class="d-flex justify-content-between align-items-baseline mb-1">
                <div class="d-flex align-items-center gap-1 overflow-hidden me-1">
                  <span class="fw-semibold text-truncate">{{ session.client_name }}</span>
                  <span v-if="session.is_client_only" class="badge bg-light text-primary border flex-shrink-0" style="font-size: 0.65rem;">Client</span>
                  <div v-if="loadingSessionId === session.id" class="spinner-border spinner-border-sm text-primary flex-shrink-0 ms-1" style="width: 0.85rem; height: 0.85rem; border-width: 0.15em;" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>
                <small v-if="session.updated_at && !session.is_client_only" class="text-muted timestamp flex-shrink-0">{{ session.updated_at ? session.updated_at.split('T')[0] : '' }}</small>
                <small v-else-if="session.is_client_only" class="badge bg-light text-muted border flex-shrink-0" style="font-size: 0.65rem;">Start chat</small>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted text-truncate w-100 pe-2 d-flex align-items-center">
                  <i v-if="isLastMessageFromAgent(session)" class="bi bi-reply-fill text-muted me-1 flex-shrink-0" title="Agent reply"></i>
                  <i v-else-if="session.last_message === 'quick reply'" class="bi bi-reply-fill text-muted me-1 flex-shrink-0"></i>
                  <i v-else-if="session.is_client_only" class="bi bi-chat-plus text-primary me-1 flex-shrink-0"></i>
                  <span class="text-truncate">
                    <template v-if="session.is_client_only">
                      {{ session.phone ? `${session.phone} • Click to start chat` : 'Click to start chat' }}
                    </template>
                    <template v-else>
                      {{ session.last_message || 'No messages yet' }}
                    </template>
                  </span>
                </small>
                <div class="d-flex align-items-center gap-2">
                  <span v-if="session.unread_count > 0" class="badge rounded-pill bg-success unread-badge">{{ session.unread_count }}</span>
                  <div class="dropdown chat-list-dropdown">
                    <i class="bi bi-chevron-down text-muted" style="cursor: pointer; font-size: 1.1rem; transform: translateY(2px); display: inline-block;" data-bs-toggle="dropdown" aria-expanded="false"></i>
                    <ul class="dropdown-menu shadow border-0" style="min-width: 220px;">
                      <li><a class="dropdown-item py-2" href="#" @click.prevent="showContactInfo(session)"><i class="bi bi-person-vcard text-primary me-3"></i>Client Info</a></li>
                      <li v-if="canManageChat && !session.is_client_only && !session.unread_count && loadingSessionId !== session.id">
                        <a class="dropdown-item py-2" href="#" @click.prevent.stop="markSessionUnread(session)">
                          <i class="bi bi-envelope text-primary me-3"></i>Mark as unread
                        </a>
                      </li>
                      <li v-if="canManageChat && !sessionHasClient(session)">
                        <a class="dropdown-item py-2" href="#" @click.prevent="openAddClientModal(session)">
                          <i class="bi bi-person-plus text-success me-3"></i>Add Client
                        </a>
                      </li>
                      <li v-if="session.is_client_only && canManageChat">
                        <a class="dropdown-item py-2 text-primary" href="#" @click.prevent="openSession(session)">
                          <i class="bi bi-chat-dots me-3"></i>Start Chat
                        </a>
                      </li>
                      <li><a class="dropdown-item py-2" href="#" @click.prevent="toggleSearch()"><i class="bi bi-search text-muted me-3"></i>Search</a></li>
                      <template v-if="!session.is_client_only">
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
                      </template>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
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
              <span v-if="activeWaba"> • WABA: <strong class="text-dark">{{ activeWaba.bank_name ? `${activeWaba.bank_name} • ` : '' }}{{ activeWaba.label }} ({{ activeWaba.number }})</strong></span>
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
                <li v-if="canManageChat && !loadingMessages">
                  <a class="dropdown-item py-2" href="#" @click.prevent="markSessionUnread(activeSession)">
                    <i class="bi bi-envelope me-2 text-primary"></i>Mark as unread
                  </a>
                </li>
                <li v-if="canManageChat && activeSession.platform?.toLowerCase() === 'whatsapp'">
                  <a
                    class="dropdown-item py-2"
                    :class="{ disabled: liveChatLocked }"
                    href="#"
                    :aria-disabled="liveChatLocked"
                    @click.prevent="openTemplateModal(activeSession)"
                  >
                    <i class="bi bi-file-earmark-text me-2 text-success"></i>Send Template
                  </a>
                </li>
                <li v-if="canManageChat && !sessionHasClient(activeSession)">
                  <a class="dropdown-item py-2" href="#" @click.prevent="openAddClientModal(activeSession)">
                    <i class="bi bi-person-plus text-success me-2"></i>Add Client
                  </a>
                </li>
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
                  <i v-if="msg.sender === 'agent' && activeSession?.platform === 'whatsapp'"
                    :class="['bi', 'ms-1', deliveryStatusIcon(msg.delivery_status), deliveryStatusClass(msg.delivery_status)]"
                    :title="deliveryStatusTitle(msg.delivery_status, msg.delivery_status_at, msg)"
                    style="font-size: 1.1em;"></i>
                </div>
                <div
                  v-if="msg.sender === 'agent' && ['failed', 'unknown'].includes(msg.delivery_status)"
                  class="delivery-error-message mt-1"
                  :class="msg.delivery_status === 'failed' ? 'text-danger' : 'text-warning-emphasis'"
                >
                  <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ deliveryErrorText(msg) }}
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
    
    <!-- Send WhatsApp Template Modal -->
    <div class="modal fade" id="sendTemplateModal" tabindex="-1" ref="templateModal">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow border-0">
          <div class="modal-header border-bottom py-3">
            <div>
              <h5 class="modal-title h6 mb-1 text-dark fw-bold">
                <i class="bi bi-file-earmark-text text-success me-2"></i>Send WhatsApp Template
              </h5>
              <small class="text-muted" v-if="templateSession">
                To {{ templateSession.client_name }} · {{ templateSession.client?.phone || templateSession.phone }}
              </small>
            </div>
            <button type="button" class="btn-close" @click="closeTemplateModal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-0">
            <div class="row g-0 template-picker-body">
              <div class="col-md-5 border-end d-flex flex-column">
                <div class="p-3 border-bottom bg-light">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input v-model="templateSearch" type="search" class="form-control" placeholder="Search approved templates..." />
                  </div>
                </div>

                <div v-if="templatesLoading" class="flex-grow-1 d-flex align-items-center justify-content-center text-muted py-5">
                  <span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading templates...
                </div>
                <div v-else-if="filteredTemplates.length === 0" class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-muted text-center p-4">
                  <i class="bi bi-file-earmark-x fs-2 mb-2"></i>
                  <div class="fw-semibold">No approved templates found</div>
                  <small>Sync approved templates in Settings, then reopen this window.</small>
                </div>
                <div v-else class="list-group list-group-flush overflow-auto template-list">
                  <button
                    v-for="template in filteredTemplates"
                    :key="template.sid"
                    type="button"
                    class="list-group-item list-group-item-action p-3"
                    :class="{ active: selectedTemplateId === template.sid }"
                    @click="selectTemplate(template)"
                  >
                    <div class="fw-semibold text-break">{{ template.name || template.sid }}</div>
                    <div class="d-flex gap-1 mt-2 flex-wrap">
                      <span class="badge" :class="selectedTemplateId === template.sid ? 'bg-white text-primary' : 'bg-light text-dark border'">{{ template.language || 'Unknown language' }}</span>
                      <span class="badge" :class="selectedTemplateId === template.sid ? 'bg-white text-primary' : 'bg-light text-dark border'">{{ template.category || 'Template' }}</span>
                    </div>
                  </button>
                </div>
              </div>

              <div class="col-md-7 bg-light p-4 overflow-auto template-preview-column">
                <div v-if="selectedTemplate" class="mx-auto" style="max-width: 520px;">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                      <h6 class="fw-bold mb-1">{{ selectedTemplate.name || selectedTemplate.sid }}</h6>
                      <small class="text-muted">Preview of the message the client will receive</small>
                    </div>
                    <span class="badge bg-success">Approved</span>
                  </div>

                  <div class="whatsapp-phone-preview mb-4 shadow">
                    <div class="whatsapp-phone-statusbar d-flex align-items-center justify-content-between px-3">
                      <span class="fw-semibold">{{ templatePreviewTime }}</span>
                      <span class="d-flex align-items-center gap-1">
                        <i class="bi bi-reception-4"></i>
                        <i class="bi bi-wifi"></i>
                        <i class="bi bi-battery-full"></i>
                      </span>
                    </div>

                    <div class="whatsapp-phone-header d-flex align-items-center px-2 py-2">
                      <i class="bi bi-arrow-left text-white fs-5 me-2"></i>
                      <div class="whatsapp-contact-avatar rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="bi bi-building text-white"></i>
                      </div>
                      <div class="min-w-0 ms-2 flex-grow-1">
                        <div class="text-white fw-semibold text-truncate whatsapp-contact-name">
                          {{ templatePreviewBusinessName }}
                          <i class="bi bi-patch-check-fill ms-1 whatsapp-verified-icon" title="Official business account"></i>
                        </div>
                        <div class="text-white-50 text-truncate whatsapp-contact-number">{{ templatePreviewBusinessNumber }}</div>
                      </div>
                      <div class="d-flex align-items-center gap-3 text-white ms-2 fs-5">
                        <i class="bi bi-camera-video"></i>
                        <i class="bi bi-telephone"></i>
                        <i class="bi bi-three-dots-vertical"></i>
                      </div>
                    </div>

                    <div class="whatsapp-chat-wallpaper position-relative p-3">
                      <div class="whatsapp-encryption-note mx-auto mb-3 px-3 py-2 text-center">
                        <i class="bi bi-lock-fill me-1"></i>Messages are end-to-end encrypted.
                      </div>
                      <div class="whatsapp-date-chip mx-auto mb-3 px-3 py-1 text-center">TODAY</div>

                      <div class="whatsapp-template-message position-relative">
                        <svg viewBox="0 0 8 13" width="8" height="13" class="whatsapp-bubble-tail" aria-hidden="true">
                          <path fill="currentColor" d="M1.533 3.568 8 12.193V1H2.812C1.042 1 .474 2.156 1.533 3.568z"></path>
                        </svg>

                        <img
                          v-if="selectedTemplate.header_format === 'IMAGE' && selectedTemplate.media_urls?.[0]"
                          :src="selectedTemplate.media_urls[0]"
                          class="w-100 rounded mb-2 template-header-image"
                          alt="Template header"
                        />
                        <video
                          v-else-if="selectedTemplate.header_format === 'VIDEO' && selectedTemplate.media_urls?.[0]"
                          :src="selectedTemplate.media_urls[0]"
                          class="w-100 rounded mb-2 template-header-image"
                          controls
                          preload="metadata"
                        ></video>
                        <div v-else-if="selectedTemplate.header_format === 'IMAGE'" class="whatsapp-document-preview rounded p-3 mb-2 text-center">
                          <i class="bi bi-image fs-2 d-block text-secondary"></i>
                          <small class="text-muted">Image header</small>
                        </div>
                        <div v-else-if="selectedTemplate.header_format === 'DOCUMENT'" class="whatsapp-document-preview rounded p-3 mb-2 d-flex align-items-center gap-2">
                          <i class="bi bi-file-earmark-text-fill fs-2 text-secondary"></i>
                          <div class="min-w-0">
                            <div class="fw-semibold text-truncate">Template document</div>
                            <small class="text-muted">Document attachment</small>
                          </div>
                        </div>
                        <div v-else-if="selectedTemplate.header_format && !['TEXT', 'IMAGE'].includes(selectedTemplate.header_format)" class="whatsapp-document-preview rounded p-2 mb-2 small text-muted">
                          <i class="bi bi-paperclip me-1"></i>{{ selectedTemplate.header_format }} header
                        </div>

                        <div v-if="renderedTemplateHeader" class="fw-bold mb-1 template-message-text whatsapp-message-header">{{ renderedTemplateHeader }}</div>
                        <div class="template-message-text whatsapp-message-body">{{ renderedTemplateBody || 'No message preview is available.' }}</div>
                        <div class="d-flex align-items-end justify-content-between gap-2 mt-1">
                          <div v-if="selectedTemplate.footer_text" class="text-muted whatsapp-message-footer">{{ selectedTemplate.footer_text }}</div>
                          <span v-else></span>
                          <div class="text-muted whatsapp-message-time text-nowrap">{{ templatePreviewTime }}</div>
                        </div>

                        <div v-if="selectedTemplate.buttons?.length" class="whatsapp-template-buttons mt-2">
                          <div v-for="(button, index) in selectedTemplate.buttons" :key="index" class="whatsapp-template-button text-center py-2">
                            <i v-if="String(button.type || '').toUpperCase() === 'QUICK_REPLY'" class="bi bi-reply-fill me-1"></i>
                            <i v-else-if="String(button.type || '').toUpperCase() === 'PHONE_NUMBER'" class="bi bi-telephone-fill me-1"></i>
                            <i v-else class="bi bi-box-arrow-up-right me-1"></i>
                            {{ button.text || button.type || 'Action' }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-if="templateVariableEntries.length" class="card border-0 shadow-sm">
                    <div class="card-body">
                      <h6 class="fw-bold mb-1">Template values</h6>
                      <p class="text-muted small mb-3">Enter the values required for this client. The preview updates as you type.</p>
                      <div v-for="entry in templateVariableEntries" :key="entry.key" class="mb-3 last-variable-field">
                        <label class="form-label small fw-semibold">
                          {{ entry.label }} <span class="text-danger">*</span>
                        </label>
                        <input
                          v-model="templateVariableValues[entry.key]"
                          type="text"
                          class="form-control form-control-sm"
                          :placeholder="`Value for ${entry.key}`"
                          maxlength="1024"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                <div v-else-if="!templatesLoading" class="h-100 d-flex flex-column align-items-center justify-content-center text-muted text-center">
                  <i class="bi bi-chat-square-text fs-1 mb-3"></i>
                  <h6>Select a template</h6>
                  <small>Choose an approved template from the list to preview it.</small>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-white py-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="closeTemplateModal" :disabled="sendingTemplate">Cancel</button>
            <button type="button" class="btn btn-success btn-sm px-3" @click="sendTemplate" :disabled="!canSendSelectedTemplate">
              <span v-if="sendingTemplate" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-send-fill me-1"></i>Send Template
            </button>
          </div>
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
                  <div class="form-text" style="font-size: 0.75rem;">
                    <span v-if="sourceChatSessionId">Prefilled from the selected inbound chat.</span>
                    <span v-else>Include country code (e.g., +27).</span>
                  </div>
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
                    <option v-for="waba in availableWabas" :key="waba.phone_number_id" :value="waba.phone_number_id">{{ waba.number }} - {{ waba.bank_name || waba.label }}</option>
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
      templateModalInstance: null,
      templateSession: null,
      templates: [],
      templatesLoading: false,
      templateSearch: '',
      selectedTemplateId: null,
      templateVariableValues: {},
      sendingTemplate: false,
      showClientInfoModal: false,
      loadingSessionId: null,
      loadingMessages: false,
      loadingSessions: false,
      markingUnreadSessionId: null,
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
      sourceChatSessionId: null,
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
    },
    templatePreviewWaba() {
      if (!this.templateSession?.waba_phone_number_id) return this.activeWaba;
      return this.availableWabas.find((waba) =>
        String(waba.phone_number_id) === String(this.templateSession.waba_phone_number_id)
      ) || this.activeWaba;
    },
    templatePreviewBusinessName() {
      return this.templatePreviewWaba?.label
        || this.templatePreviewWaba?.bank_name
        || 'WhatsApp Business';
    },
    templatePreviewBusinessNumber() {
      return this.templatePreviewWaba?.number || 'Business account';
    },
    templatePreviewTime() {
      return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
    filteredTemplates() {
      const search = this.templateSearch.trim().toLowerCase();
      if (!search) return this.templates;

      return this.templates.filter((template) => [
        template.name,
        template.sid,
        template.language,
        template.category,
        template.body_preview,
      ].some((value) => String(value || '').toLowerCase().includes(search)));
    },
    selectedTemplate() {
      return this.templates.find((template) => template.sid === this.selectedTemplateId) || null;
    },
    templateVariableEntries() {
      return Object.entries(this.selectedTemplate?.variables || {}).map(([key, label]) => ({ key, label }));
    },
    renderedTemplateHeader() {
      return this.renderTemplatePart(this.selectedTemplate?.header_text, 'header');
    },
    renderedTemplateBody() {
      return this.renderTemplatePart(this.selectedTemplate?.body_preview, 'body');
    },
    canSendSelectedTemplate() {
      if (!this.selectedTemplate || this.sendingTemplate || this.liveChatLocked) return false;
      return this.templateVariableEntries.every(({ key }) => String(this.templateVariableValues[key] || '').trim() !== '');
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
    disposeManagedModal(this.templateModalInstance);
    this.templateModalInstance = null;
  },
  methods: {
    hasPermission(permCode) {
      if (!this.currentUser) return false;
      if (this.currentRoleCodes.includes('SUPER_ADMIN')) {
        return true;
      }

      if (Array.isArray(this.currentUser.permission_codes)) {
        return this.currentUser.permission_codes.includes(permCode);
      }

      return false;
    },
    isLastMessageFromAgent(session) {
      if (!session || !session.last_message) return false;

      // 1. Active session with locally loaded messages
      if (this.activeSession && this.activeSession.id === session.id && this.messages && this.messages.length > 0) {
        const lastMsg = this.messages[this.messages.length - 1];
        if (lastMsg) {
          return lastMsg.sender === 'agent';
        }
      }

      // 2. Eager loaded latest_message object
      if (session.latest_message && session.latest_message.sender) {
        return session.latest_message.sender === 'agent';
      }

      // 3. Fallback direct last_sender check
      if (session.last_sender) {
        return session.last_sender === 'agent';
      }

      return false;
    },
    deliveryStatusIcon(status) {
      if (status === 'read' || status === 'delivered') return 'bi-check-all';
      if (status === 'sent') return 'bi-check';
      if (status === 'failed') return 'bi-exclamation-circle';
      if (status === 'pending' || status === 'accepted') return 'bi-clock';
      return 'bi-question-circle';
    },
    deliveryStatusClass(status) {
      if (status === 'read') return 'text-primary';
      if (status === 'failed') return 'text-danger';
      return 'text-muted';
    },
    deliveryErrorText(message) {
      const code = message?.delivery_error_code ? ` (${message.delivery_error_code})` : '';
      const fallback = message?.delivery_status === 'unknown'
        ? 'The delivery result could not be confirmed.'
        : 'Meta did not provide an error description for this message.';

      return `WhatsApp ${message?.delivery_status || 'delivery'}${code}: ${message?.delivery_error_message || fallback}`;
    },
    deliveryStatusTitle(status, statusAt, message = null) {
      if (status === 'read' && statusAt) {
        return `Customer read receipt received at ${new Date(statusAt).toLocaleString()}`;
      }
      if (status === 'failed' || status === 'unknown') {
        return this.deliveryErrorText(message || { delivery_status: status });
      }
      return {
        pending: 'Waiting for WhatsApp',
        accepted: 'Accepted by WhatsApp; delivery not yet confirmed',
        sent: 'Sent; delivery not yet confirmed',
        delivered: 'Delivered; reading not confirmed',
        read: 'Customer read receipt received',
      }[status] || 'Delivery status unavailable';
    },
    sessionHasClient(session) {
      return !!(session?.client_id || session?.client?.id);
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
    showSectionHeader(session, index) {
      if (!this.sidebarSearch) return false;
      if (index === 0) return true;
      const prevSession = this.sessions[index - 1];
      if (!prevSession) return true;
      return !!session.is_client_only !== !!prevSession.is_client_only;
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

      if (session.is_client_only || String(session.id).startsWith('client_')) {
        axios
          .post('/api/chat/session-for-client', {
            client_id: session.client_id,
            platform: 'whatsapp',
            waba_number: this.filterWaba !== 'all' ? this.filterWaba : undefined,
          })
          .then((res) => {
            this.activeSession = res.data;
            this.messages = res.data.messages || [];
            const idx = this.sessions.findIndex((s) => s.id === session.id);
            if (idx !== -1) {
              this.sessions.splice(idx, 1, res.data);
            }
            this.$nextTick(this.scrollToBottom);
          })
          .catch((err) => {
            console.error('Failed to start chat for client', err);
            notify.error('Failed to start chat for client.', 'Chat');
          })
          .finally(() => {
            this.loadingSessionId = null;
            this.loadingMessages = false;
          });
        return;
      }

      axios.get(`/api/chat/sessions/${session.id}`).then((res) => {
        this.activeSession = res.data;
        this.messages = res.data.messages || [];
        const listSession = this.sessions.find((item) => item.id === session.id);
        if (listSession) listSession.unread_count = res.data.unread_count;
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

      // Soft refresh active session messages (only when not loading a new session and real session exists)
      if (this.activeSession && !this.loadingMessages && !this.activeSession.is_client_only && !String(this.activeSession.id).startsWith('client_')) {
        const activeSessionId = this.activeSession.id;
        axios.get(`/api/chat/sessions/${activeSessionId}`, { params: { peek: 1 } }).then((res) => {
          if (this.activeSession?.id !== activeSessionId) return;
          const fetchedMessages = res.data.messages || [];
          const hasNewMessages = fetchedMessages.length > this.messages.length;
          const statusChanged = fetchedMessages.some((message, index) =>
            message.id === this.messages[index]?.id &&
            (message.delivery_status !== this.messages[index]?.delivery_status ||
              message.delivery_status_at !== this.messages[index]?.delivery_status_at ||
              message.delivery_error_code !== this.messages[index]?.delivery_error_code ||
              message.delivery_error_message !== this.messages[index]?.delivery_error_message)
          );
          if (fetchedMessages.length !== this.messages.length || statusChanged) {
            this.messages = fetchedMessages;
            if (hasNewMessages) this.$nextTick(this.scrollToBottom);
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
          if (res.data.delivery_status === 'failed') {
            notify.error(this.deliveryErrorText(res.data), 'Chat');
          } else if (res.data.delivery_status === 'unknown') {
            notify.error(this.deliveryErrorText(res.data), 'Chat');
          }
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
    markSessionUnread(session) {
      if (!this.canManageChat || !session || session.is_client_only || this.markingUnreadSessionId) return;
      this.markingUnreadSessionId = session.id;
      axios.post(`/api/chat/sessions/${session.id}/mark-unread`).then((res) => {
        if (this.activeSession?.id === session.id) {
          this.activeSession = null;
          this.messages = [];
        }
        session.unread_count = res.data.unread_count;
        this.fetchSessions();
        notify.success('Chat marked as unread.', 'Chat');
      }).catch((err) => {
        console.error('Failed to mark chat as unread', err);
        notify.error('Failed to mark chat as unread.', 'Chat');
      }).finally(() => {
        this.markingUnreadSessionId = null;
      });
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
    openTemplateModal(session) {
      if (!this.canManageChat || !session || this.liveChatLocked) return;
      if (String(session.platform || '').toLowerCase() !== 'whatsapp') {
        notify.error('Templates can only be sent to WhatsApp chats.', 'Chat');
        return;
      }

      this.templateSession = session;
      this.templateSearch = '';
      this.selectedTemplateId = null;
      this.templateVariableValues = {};
      this.loadTemplates();

      this.$nextTick(() => {
        if (!this.templateModalInstance && this.$refs.templateModal) {
          this.templateModalInstance = createManagedModal(this.$refs.templateModal);
        }
        this.templateModalInstance?.show();
      });
    },
    closeTemplateModal() {
      if (!this.sendingTemplate) {
        this.templateModalInstance?.hide();
      }
    },
    loadTemplates() {
      this.templatesLoading = true;
      axios.get('/api/whatsapp-templates', { params: { approved: true } })
        .then((res) => {
          this.templates = Array.isArray(res.data) ? res.data : [];
          if (this.templates.length) {
            this.selectTemplate(this.templates[0]);
          }
        })
        .catch((err) => {
          console.error('Failed to load WhatsApp templates', err);
          this.templates = [];
          notify.error(err.response?.data?.message || 'Failed to load approved WhatsApp templates.', 'Chat');
        })
        .finally(() => {
          this.templatesLoading = false;
        });
    },
    selectTemplate(template) {
      this.selectedTemplateId = template.sid;
      this.templateVariableValues = Object.keys(template.variables || {}).reduce((values, key) => {
        values[key] = '';
        return values;
      }, {});
    },
    renderTemplatePart(text, prefix) {
      return String(text || '').replace(/{{(\d+)}}/g, (placeholder, index) => {
        return String(this.templateVariableValues[`${prefix}_${index}`] || '').trim() || placeholder;
      });
    },
    sendTemplate() {
      if (!this.canSendSelectedTemplate || !this.templateSession) return;

      const sessionId = this.templateSession.id;
      this.sendingTemplate = true;
      axios.post(`/api/chat/sessions/${sessionId}/templates`, {
        template_id: this.selectedTemplate.sid,
        variables: this.templateVariableValues,
      }).then((res) => {
        if (this.activeSession?.id === sessionId && !this.messages.some((message) => message.id === res.data.id)) {
          this.messages.push(res.data);
          this.$nextTick(this.scrollToBottom);
        }

        this.templateModalInstance?.hide();
        this.fetchSessions();

        if (res.data.delivery_status === 'failed') {
          notify.error(this.deliveryErrorText(res.data), 'Chat');
        } else if (res.data.delivery_status === 'unknown') {
          notify.error(this.deliveryErrorText(res.data), 'Chat');
        } else {
          notify.success('WhatsApp template sent.', 'Chat');
        }
      }).catch((err) => {
        console.error('Failed to send WhatsApp template', err);
        const validationMessage = Object.values(err.response?.data?.errors || {})[0]?.[0];
        notify.error(validationMessage || err.response?.data?.message || 'Failed to send WhatsApp template.', 'Chat');
      }).finally(() => {
        this.sendingTemplate = false;
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
    openAddClientModal(session = null) {
      const sourceSession = session && session.id && !this.sessionHasClient(session) ? session : null;
      const sourceWaba = sourceSession?.waba_phone_number_id
        ? this.availableWabas.find(w => String(w.phone_number_id) === String(sourceSession.waba_phone_number_id))
        : null;
      let initialDepartments = [];
      if (this.filterDepartment !== 'all') {
        const found = this.availableDepartments.find(d => String(d.id) === String(this.filterDepartment));
        if (found) {
          initialDepartments.push(found);
        }
      }

      this.sourceChatSessionId = sourceSession?.id || null;
      this.newClientForm = {
        first_name: '',
        surname: '',
        phone: sourceSession?.phone || '',
        departments: initialDepartments,
        bank_id: sourceSession?.bank_id || sourceWaba?.bank_id || (this.filterBank !== 'all' ? this.filterBank : (this.availableBanks[0]?.id || '')),
        waba_number: sourceSession?.waba_phone_number_id || (this.filterWaba !== 'all' ? this.filterWaba : (this.availableWabas[0]?.phone_number_id || '')),
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
          source_chat_session_id: this.sourceChatSessionId,
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

      if (session.id && !session.is_client_only && !String(session.id).startsWith('client_')) {
        axios.get(`/api/chat/sessions/${session.id}`, { params: { peek: 1 } }).then((res) => {
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

.delivery-error-message {
  max-width: 34rem;
  padding-top: 0.25rem;
  border-top: 1px solid rgba(220, 53, 69, 0.2);
  font-size: 0.72rem;
  line-height: 1.25;
  overflow-wrap: anywhere;
}

.template-picker-body {
  min-height: 540px;
  max-height: 68vh;
}

.template-list,
.template-preview-column {
  max-height: 68vh;
}

.template-message-text {
  line-height: 1.45;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.template-header-image {
  max-height: 220px;
  object-fit: cover;
}

.min-w-0 {
  min-width: 0;
}

.whatsapp-phone-preview {
  max-width: 430px;
  margin: 0 auto;
  overflow: hidden;
  border: 6px solid #263238;
  border-radius: 22px;
  background: #efeae2;
}

.whatsapp-phone-statusbar {
  height: 26px;
  color: #ffffff;
  background: #075e54;
  font-size: 0.68rem;
}

.whatsapp-phone-header {
  min-height: 58px;
  background: #008069;
}

.whatsapp-contact-avatar {
  width: 40px;
  height: 40px;
  background: #607d8b;
}

.whatsapp-contact-name {
  font-size: 0.9rem;
  line-height: 1.15;
}

.whatsapp-contact-number {
  margin-top: 3px;
  font-size: 0.69rem;
}

.whatsapp-verified-icon {
  color: #8edfd2;
  font-size: 0.78rem;
}

.whatsapp-chat-wallpaper {
  min-height: 350px;
  background-color: #efeae2;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120'%3E%3Cg fill='none' stroke='%238b9b93' stroke-opacity='.11' stroke-width='1.3'%3E%3Cpath d='M14 18c7-5 15 4 10 11s-15 3-14-5m71-13 8 8-8 8-8-8zm-40 39c4-7 15-5 16 3s-10 13-15 7m45 13c8-2 13 8 7 14s-15 0-12-8M9 88c8-6 18 3 12 11S5 102 6 94m50-3 9 9m-9 0 9-9m37 2c4-7 14-3 12 5s-13 8-14 0'/%3E%3Cpath d='M36 7c3 7 11 8 16 3m-20 63c8 0 12 7 8 13m34-48c5 6 13 5 17-1m13 39c-7 2-9 10-4 15'/%3E%3C/g%3E%3C/svg%3E");
}

.whatsapp-encryption-note {
  width: fit-content;
  max-width: 88%;
  border-radius: 7px;
  color: #6b6252;
  background: #ffeecd;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.08);
  font-size: 0.64rem;
}

.whatsapp-date-chip {
  width: fit-content;
  border-radius: 7px;
  color: #54656f;
  background: #ffffffd9;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.08);
  font-size: 0.64rem;
}

.whatsapp-template-message {
  width: 88%;
  padding: 8px 8px 5px;
  margin-left: 7px;
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
  font-size: 0.9rem;
  line-height: 1.3;
}

.whatsapp-message-body {
  font-size: 0.82rem;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

.whatsapp-message-footer {
  font-size: 0.68rem;
}

.whatsapp-message-time {
  font-size: 0.62rem;
}

.whatsapp-document-preview {
  background: #f0f2f5;
  border: 1px solid #e1e5e7;
}

.whatsapp-template-buttons {
  margin-right: -8px;
  margin-left: -8px;
  margin-bottom: -5px;
}

.whatsapp-template-button {
  color: #00a884;
  border-top: 1px solid #e9edef;
  font-size: 0.78rem;
  font-weight: 600;
}

.last-variable-field:last-child {
  margin-bottom: 0 !important;
}

@media (max-width: 767.98px) {
  .template-picker-body {
    min-height: auto;
    max-height: none;
  }

  .template-list {
    max-height: 260px;
  }

  .template-preview-column {
    max-height: none;
  }
}

/* Preserve existing multiselect tag color if used elsewhere in the view */
:deep(.multiselect__tag) {
  background: #0d6efd;
}
</style>
