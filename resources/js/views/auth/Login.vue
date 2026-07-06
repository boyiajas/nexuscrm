<template>
  <div class="login-page">
    <header class="login-topbar">
      <div class="login-topbar__inner">
        <a href="/" class="login-topbar__brand">
          <span class="login-topbar__mark">
            <img
              v-if="branding.app_logo_url"
              :src="branding.app_logo_url"
              :alt="branding.app_name"
              class="login-brand-logo"
            />
            <i v-else class="bi bi-shield-lock"></i>
          </span>
          <span class="login-topbar__brand-copy">
            <span class="login-topbar__name">{{ branding.app_name }}</span>
            <span class="login-topbar__sub">Strauss Recovery Solutions</span>
          </span>
        </a>

        <nav class="login-topbar__nav">
          <a href="/#features">Features</a>
          <a href="/#security">Security</a>
          <a href="/compliance">Compliance</a>
          <a href="/privacy-policy">Privacy Policy</a>
          <a href="/terms-of-service">Terms</a>
        </nav>

        <a href="/login" class="login-topbar__cta">Login</a>
      </div>
    </header>

    <div class="login-stage">
      <section class="login-shell">
        <div class="login-panel">
          <span class="login-panel__eyebrow">
            <i class="bi bi-patch-check-fill"></i>
            Secure Access
          </span>
          <h1>Continue into the governed collections workspace.</h1>
          <p>
            Use the same secure operating platform for debtor follow-up, Meta WhatsApp engagement,
            audit visibility, and compliance-led workflow management.
          </p>

          <div class="login-panel__trust">
            <span><i class="bi bi-shield-check"></i> Role-based access</span>
            <span><i class="bi bi-journal-check"></i> Audit-ready logs</span>
            <span><i class="bi bi-whatsapp"></i> Meta direct messaging</span>
          </div>
        </div>

        <div class="login-card">
          <div class="login-card__header">
            <span class="login-card__eyebrow">SRS DailyCRM Access</span>
            <h2 v-if="step === 'credentials'">Sign in to {{ branding.app_name }}</h2>
            <h2 v-else-if="step === 'mfa'">Verify your sign in</h2>
            <h2 v-else>Reset your password</h2>
            <p v-if="step === 'credentials'">{{ branding.app_tagline || 'Secure access to your operating dashboard.' }}</p>
            <p v-else-if="step === 'mfa'">Enter the verification code sent to your email address.</p>
            <p v-else>Complete the password reset challenge to continue.</p>
          </div>

          <div v-if="error" class="alert alert-danger py-2">
            {{ error }}
          </div>

          <form @submit.prevent="submit">
            <div class="mb-3" v-if="step === 'credentials'">
              <label class="form-label">Email address</label>
              <div class="login-input-wrap">
                <span class="login-input-icon"><i class="bi bi-at"></i></span>
                <input
                  v-model="form.email"
                  type="email"
                  class="form-control login-control login-control--with-left"
                  required
                  autocomplete="email"
                  placeholder="name@nexuscorp.com"
                />
              </div>
            </div>

            <div class="mb-3" v-if="step === 'credentials'">
              <label class="form-label d-flex justify-content-between">
                <span>Password</span>
                <span class="login-link login-link--static">Forgot password?</span>
              </label>
              <div class="login-input-wrap">
                <span class="login-input-icon"><i class="bi bi-lock"></i></span>
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  class="form-control login-control login-control--with-left login-control--with-right"
                  required
                  autocomplete="current-password"
                />
                <button type="button" class="login-eye" @click="togglePassword" aria-label="Toggle password visibility">
                  <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                </button>
              </div>
            </div>

            <div class="mb-3 form-check login-check" v-if="step === 'credentials'">
              <input
                v-model="form.remember"
                type="checkbox"
                class="form-check-input"
                id="rememberCheck"
              />
              <label class="form-check-label" for="rememberCheck">
                Remember this device for 30 days
              </label>
            </div>

            <div class="login-inline-note" v-if="step === 'credentials'">
              Protected by role-based access, MFA, and audit logging.
            </div>

            <div v-if="step === 'mfa'">
              <div class="alert alert-info py-2">
                {{ mfa.message }}
                <div class="small mt-1" v-if="mfa.maskedEmail">Code destination: {{ mfa.maskedEmail }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Verification code</label>
                <input
                  v-model="mfa.code"
                  type="text"
                  class="form-control login-control"
                  maxlength="6"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  required
                />
              </div>
              <button type="button" class="btn btn-link btn-sm p-0 mb-3 login-link" @click="resetToCredentials" :disabled="loading">
                Use different credentials
              </button>
            </div>

            <div v-if="step === 'password_reset'">
              <div class="alert alert-warning py-2">
                {{ passwordReset.message }}
              </div>
              <div class="mb-3">
                <label class="form-label">New password</label>
                <input
                  v-model="passwordReset.password"
                  type="password"
                  class="form-control login-control"
                  autocomplete="new-password"
                  required
                />
                <div class="form-text">
                  Use at least 12 characters with upper/lowercase letters, a number, and a symbol.
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Confirm new password</label>
                <input
                  v-model="passwordReset.password_confirmation"
                  type="password"
                  class="form-control login-control"
                  autocomplete="new-password"
                  required
                />
              </div>
              <button type="button" class="btn btn-link btn-sm p-0 mb-3 login-link" @click="resetToCredentials" :disabled="loading">
                Use different credentials
              </button>
            </div>

            <button
              type="submit"
              class="btn btn-primary w-100 login-submit"
              :disabled="loading"
            >
              <span
                v-if="loading"
                class="spinner-border spinner-border-sm me-2"
              ></span>
              {{ submitLabel }}
              <i v-if="!loading && step === 'credentials'" class="bi bi-arrow-right ms-2"></i>
            </button>
          </form>

            <template v-if="step === 'credentials'">
              <div class="login-divider"><span>OR</span></div>

              <button type="button" class="btn w-100 login-sso" disabled>
                <i class="bi bi-diagram-3 me-2"></i>
                Sign in with SSO
              </button>

              <div class="login-help">
                Don't have an account?
                <span>Contact Administrator</span>
              </div>
            </template>
        </div>
      </section>
    </div>

    <footer class="login-footer">
      <div class="login-footer__inner">
        <div class="login-footer__brand-block">
          <div class="login-footer__brand">
            <span class="login-footer__mark">
              <i class="bi bi-shield-lock"></i>
            </span>
            <span class="login-footer__name">{{ branding.app_name }}</span>
          </div>
          <div class="login-footer__copy">
            Collections workflow, WhatsApp engagement, audit, and compliance tooling for Strauss Recovery Solutions.
          </div>
          <div class="login-footer__copy login-footer__copy--fine">© 2024 {{ branding.app_name }} - Strauss Recovery Solutions. All rights reserved.</div>
        </div>
        <div class="login-footer__links">
          <a href="/privacy-policy">Privacy Policy</a>
          <a href="/compliance">Compliance</a>
          <a href="/data-deletion">Data Deletion</a>
          <a href="/terms-of-service">Terms of Service</a>
          <a href="/login">Login</a>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
import axios, { syncAuthenticatedUser } from '../../axios';

export default {
  name: 'LoginView',
  data() {
    return {
      branding: {
        app_name: 'SRS DailyCRM',
        app_short_name: 'NC',
        app_tagline: 'Sign in to your dashboard',
        app_logo_url: '',
      },
      form: {
        email: '',
        password: '',
        remember: false,
      },
      loading: false,
      error: null,
      showPassword: false,
      step: 'credentials',
      mfa: {
        challengeId: '',
        code: '',
        message: '',
        maskedEmail: '',
      },
      passwordReset: {
        challengeId: '',
        message: '',
        password: '',
        password_confirmation: '',
      },
    };
  },
  computed: {
    submitLabel() {
      if (this.step === 'credentials') return 'Sign in';
      if (this.step === 'mfa') return 'Verify and continue';
      return 'Reset password and continue';
    },
  },
  created() {
    this.loadStoredBranding();
    this.loadBranding();
  },
  methods: {
    applyBranding(branding = {}) {
      const resolvedName = !branding.app_name || branding.app_name === 'NexusCRM'
        ? 'SRS DailyCRM'
        : branding.app_name;
      this.branding = {
        app_name: resolvedName,
        app_short_name: branding.app_short_name || 'NC',
        app_tagline: branding.app_tagline || 'Sign in to your dashboard',
        app_logo_url: branding.app_logo_url || '',
      };
      document.title = this.branding.app_name;
      localStorage.setItem('nexus_branding', JSON.stringify(this.branding));
    },
    loadStoredBranding() {
      const stored = localStorage.getItem('nexus_branding');
      if (!stored) return;
      try {
        this.applyBranding(JSON.parse(stored));
      } catch (e) {
        // ignore malformed cache
      }
    },
    async loadBranding() {
      try {
        const res = await axios.get('/api/settings/branding');
        this.applyBranding(res.data || {});
      } catch (e) {
        // keep fallback branding
      }
    },
    togglePassword() {
      this.showPassword = !this.showPassword;
    },
    resetToCredentials() {
      this.step = 'credentials';
      this.mfa = {
        challengeId: '',
        code: '',
        message: '',
        maskedEmail: '',
      };
      this.passwordReset = {
        challengeId: '',
        message: '',
        password: '',
        password_confirmation: '',
      };
      this.error = null;
    },
    async submit() {
      this.loading = true;
      this.error = null;

      try {
        if (this.step === 'credentials') {
          await axios.get('/sanctum/csrf-cookie');

          const response = await axios.post('/api/login', {
            email: this.form.email,
            password: this.form.password,
            remember: this.form.remember,
          });

          if (response.data?.mfa_required) {
            this.step = 'mfa';
            this.mfa.challengeId = response.data.challenge_id;
            this.mfa.message = response.data.message || 'Enter the verification code sent to your email.';
            this.mfa.maskedEmail = response.data.masked_email || '';
            return;
          }

          if (response.data?.password_reset_required) {
            this.step = 'password_reset';
            this.passwordReset.challengeId = response.data.challenge_id;
            this.passwordReset.message = response.data.message || 'Your password must be reset before you can continue.';
            return;
          }

          this.completeLogin(response.data);
          return;
        }

        if (this.step === 'password_reset') {
          const response = await axios.post('/api/login/password/reset', {
            challenge_id: this.passwordReset.challengeId,
            password: this.passwordReset.password,
            password_confirmation: this.passwordReset.password_confirmation,
          });

          if (response.data?.mfa_required) {
            this.step = 'mfa';
            this.mfa.challengeId = response.data.challenge_id;
            this.mfa.message = response.data.message || 'Enter the verification code sent to your email.';
            this.mfa.maskedEmail = response.data.masked_email || '';
            return;
          }

          this.completeLogin(response.data);
          return;
        }

        const response = await axios.post('/api/login/mfa/verify', {
          challenge_id: this.mfa.challengeId,
          code: this.mfa.code,
        });

        this.completeLogin(response.data);
      } catch (e) {
        if (e.response && e.response.status === 422) {
          this.error = e.response.data.message || 'Invalid credentials.';
        } else if (e.response && e.response.status === 423) {
          this.error = e.response.data.message || 'Your account is temporarily locked.';
        } else if (e.response && e.response.status === 428 && e.response.data?.password_reset_required) {
          this.step = 'password_reset';
          this.passwordReset.challengeId = e.response.data.challenge_id;
          this.passwordReset.message = e.response.data.message || 'Your password must be reset before you can continue.';
        } else if (e.response && e.response.status === 202 && e.response.data?.mfa_required) {
          this.step = 'mfa';
          this.mfa.challengeId = e.response.data.challenge_id;
          this.mfa.message = e.response.data.message || 'Enter the verification code sent to your email.';
          this.mfa.maskedEmail = e.response.data.masked_email || '';
        } else {
          this.error = 'Unable to login. Please try again.';
        }
      } finally {
        this.loading = false;
      }
    },
    async completeLogin(payload) {
      const { token, user } = payload;

      localStorage.setItem('nexus_token', token);
      localStorage.setItem('nexus_user', JSON.stringify(user));
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
      try {
        await syncAuthenticatedUser();
      } catch (e) {
        // Keep the login flow usable even if the follow-up profile sync fails.
      }
      this.$router.push({ name: 'dashboard' });
    },
  },
};
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
  background: radial-gradient(circle at top right, #eaedff 0%, #faf8ff 100%);
  padding: 0;
}

.login-page__backdrop {
  display: none;
}

.login-page::before {
  content: "";
  position: absolute;
  inset: 64px 0 0;
  background:
    radial-gradient(circle at 78% 24%, rgba(30, 64, 175, 0.08), transparent 0 24%, transparent 24.5%),
    radial-gradient(circle at 14% 72%, rgba(134, 242, 228, 0.18), transparent 0 18%, transparent 18.5%),
    radial-gradient(circle at 56% 48%, rgba(222, 225, 255, 0.9), transparent 0 26%, transparent 26.5%);
  opacity: 0.95;
  pointer-events: none;
}

.login-page::after {
  content: "";
  position: absolute;
  inset: 120px auto auto 50%;
  width: 560px;
  height: 560px;
  transform: translateX(-8%);
  background:
    linear-gradient(30deg, rgba(136, 181, 255, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(136, 181, 255, 0.08) 87.5%, rgba(136, 181, 255, 0.08)),
    linear-gradient(150deg, rgba(136, 181, 255, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(136, 181, 255, 0.08) 87.5%, rgba(136, 181, 255, 0.08)),
    linear-gradient(90deg, rgba(136, 181, 255, 0.05) 2%, transparent 2.5%, transparent 97%, rgba(136, 181, 255, 0.05) 97.5%, rgba(136, 181, 255, 0.05));
  background-size: 180px 104px;
  opacity: 0.6;
  pointer-events: none;
  filter: blur(0.2px);
}

.login-topbar {
  position: relative;
  z-index: 1;
  height: 64px;
  background: rgba(250, 248, 255, 0.88);
  border-bottom: 1px solid rgba(196, 197, 213, 0.3);
  backdrop-filter: blur(10px);
}

.login-topbar__inner {
  max-width: 1280px;
  height: 100%;
  margin: 0 auto;
  padding: 0 40px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.login-topbar__brand {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.login-topbar__mark {
  width: 32px;
  height: 32px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #00288e;
  color: #fff;
}

.login-topbar__brand-copy {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.login-topbar__name {
  font-size: 1.5rem;
  font-weight: 700;
  color: #131b2e;
  line-height: 1.05;
}

.login-topbar__sub {
  font-size: 0.76rem;
  color: #6b7280;
  line-height: 1.1;
}

.login-topbar__nav {
  display: flex;
  align-items: center;
  gap: 22px;
}

.login-topbar__nav a {
  color: #444653;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
}

.login-topbar__nav a:hover {
  color: #00288e;
}

.login-topbar__cta {
  border-radius: 4px;
  background: #1e40af;
  padding: 8px 18px;
  color: #fff;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 600;
  transition: opacity 0.2s ease;
}

.login-topbar__cta:hover {
  opacity: 0.92;
}

.login-stage {
  position: relative;
  z-index: 1;
  flex: 1;
  min-height: calc(100vh - 64px - 176px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 56px 16px 48px;
}

.login-shell {
  width: min(1280px, 100%);
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(420px, 520px);
  gap: 48px;
  align-items: center;
  padding: 0 40px;
}

.login-panel {
  max-width: 520px;
}

.login-panel__eyebrow,
.login-card__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 5px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(196, 197, 213, 0.45);
  color: #3755c3;
  font-size: 0.7rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 700;
}

.login-panel h1 {
  margin: 18px 0 16px;
  font-family: Manrope, Inter, sans-serif;
  font-size: clamp(2.25rem, 4vw, 3.55rem);
  line-height: 1.05;
  letter-spacing: -0.03em;
  color: #131b2e;
}

.login-panel p {
  margin: 0 0 28px;
  max-width: 480px;
  color: #444653;
  font-size: 1.03rem;
  line-height: 1.7;
}

.login-panel__trust {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.login-panel__trust span {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border-radius: 999px;
  padding: 11px 16px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(196, 197, 213, 0.42);
  color: #283044;
  font-size: 0.88rem;
  box-shadow: 0 10px 24px rgba(55, 85, 195, 0.07);
}

.login-card {
  width: 100%;
  border-radius: 24px;
  padding: 36px 34px 30px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(196, 197, 213, 0.44);
  box-shadow: 0 24px 60px rgba(55, 85, 195, 0.12);
  backdrop-filter: blur(18px);
}

.login-card__header {
  margin-bottom: 28px;
}

.login-brand-mark {
  overflow: hidden;
  width: 100%;
  height: 100%;
}

.login-brand-logo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.login-card__header h2 {
  margin: 0 0 8px;
  font-family: Manrope, Inter, sans-serif;
  font-size: 2rem;
  font-weight: 800;
  color: #131b2e;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.login-card__header p {
  margin: 0;
  color: #444653;
  font-size: 1rem;
  line-height: 1.45;
}

.login-input-wrap {
  position: relative;
}

.login-input-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  font-size: 1.15rem;
  z-index: 2;
}

.login-eye {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  border: 0;
  background: transparent;
  color: #6b7280;
  padding: 0;
  font-size: 1.2rem;
}

.login-control {
  min-height: 62px;
  border-radius: 12px;
  border-color: #c4c5d5;
  background: #f2f3ff;
  box-shadow: none;
  font-size: 1rem;
}

.login-control:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12);
}

.login-control--with-left {
  padding-left: 46px;
}

.login-control--with-right {
  padding-right: 46px;
}

.login-link {
  color: #2563eb;
  text-decoration: none;
  font-weight: 600;
}

.login-link--static {
  cursor: default;
}

.login-link:hover {
  color: #173bab;
  text-decoration: underline;
}

.login-link--static:hover {
  color: #2563eb;
  text-decoration: none;
}

.login-submit {
  min-height: 64px;
  border-radius: 12px;
  background: linear-gradient(135deg, #00288e, #1e40af);
  border: 0;
  box-shadow: 0 12px 24px rgba(30, 64, 175, 0.2);
  font-size: 1.05rem;
  font-weight: 700;
}

.login-submit:hover,
.login-submit:focus {
  background: linear-gradient(135deg, #173bab, #1238a2);
}

.login-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 22px 0;
  color: #6b7280;
  font-size: 0.86rem;
  font-weight: 700;
  text-transform: uppercase;
}

.login-divider::before,
.login-divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: #d7dfef;
}

.login-sso {
  min-height: 58px;
  border-radius: 12px;
  border: 1px solid #c4c5d5;
  background: #fff;
  color: #172033;
  font-weight: 600;
}

.login-sso:disabled {
  color: #172033;
  opacity: 1;
}

.login-help {
  margin-top: 20px;
  padding-top: 22px;
  border-top: 1px solid #dfe6f3;
  text-align: center;
  color: #2f3b53;
  font-size: 0.98rem;
}

.login-help span {
  color: #153fb8;
  font-weight: 700;
}

.login-inline-note {
  margin: 2px 0 18px;
  color: #667085;
  font-size: 0.88rem;
}

.login-footnote {
  display: none;
}

.login-check {
  color: #44516b;
}

.login-footer {
  position: relative;
  z-index: 1;
  background: #f2f3ff;
  border-top: 1px solid rgba(196, 197, 213, 0.45);
  padding: 28px 0;
}

.login-footer__inner {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 40px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 32px;
}

.login-footer__brand-block {
  max-width: 420px;
}

.login-footer__brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}

.login-footer__mark {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  background: #00288e;
  color: #fff;
}

.login-footer__name {
  font-family: Manrope, Inter, sans-serif;
  font-weight: 800;
  color: #131b2e;
}

.login-footer__copy {
  font-size: 0.9rem;
  color: #444653;
  line-height: 1.65;
}

.login-footer__copy--fine {
  margin-top: 12px;
  color: #6b7280;
  font-size: 0.82rem;
}

.login-footer__links {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 18px 28px;
  padding-top: 8px;
}

.login-footer__links a {
  color: #444653;
  text-decoration: none;
  font-size: 0.9rem;
}

.login-footer__links a:hover {
  text-decoration: underline;
}

@media (max-width: 991px) {
  .login-topbar__inner,
  .login-shell,
  .login-footer__inner {
    padding-left: 24px;
    padding-right: 24px;
  }

  .login-shell {
    grid-template-columns: 1fr;
    gap: 28px;
  }

  .login-panel {
    max-width: none;
  }

  .login-panel h1 {
    max-width: 660px;
  }

  .login-card {
    max-width: 620px;
  }

  .login-page::after {
    width: 420px;
    height: 420px;
    inset: 180px auto auto 52%;
  }
}

@media (max-width: 575px) {
  .login-topbar {
    height: auto;
  }

  .login-topbar__inner {
    flex-wrap: wrap;
    padding: 14px 18px;
  }

  .login-topbar__nav {
    order: 3;
    width: 100%;
    overflow-x: auto;
    gap: 16px;
    padding-bottom: 2px;
  }

  .login-topbar__name {
    font-size: 1.15rem;
  }

  .login-topbar__sub {
    font-size: 0.72rem;
  }

  .login-stage {
    padding: 28px 0 34px;
    min-height: auto;
  }

  .login-shell {
    padding-left: 18px;
    padding-right: 18px;
  }

  .login-panel h1 {
    font-size: 2.15rem;
  }

  .login-panel p {
    font-size: 0.96rem;
  }

  .login-card {
    padding: 26px 20px 24px;
    border-radius: 18px;
  }

  .login-card__header h2 {
    font-size: 1.65rem;
  }

  .login-footer {
    padding: 22px 0;
  }

  .login-footer__inner {
    align-items: flex-start;
    flex-direction: column;
    padding-left: 18px;
    padding-right: 18px;
  }

  .login-footer__links {
    justify-content: flex-start;
  }
}
</style>
