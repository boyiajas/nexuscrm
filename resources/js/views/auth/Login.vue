<template>
  <div class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <div
            class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2"
            style="width: 48px; height: 48px;"
          >
            <i class="bi bi-chat-dots"></i>
          </div>
          <h4 class="mb-0">NexusCRM Login</h4>
          <small class="text-muted">Sign in to your dashboard</small>
        </div>

        <div v-if="error" class="alert alert-danger py-2">
          {{ error }}
        </div>

        <form @submit.prevent="submit">
          <div class="mb-3">
            <label class="form-label">Email address</label>
            <input
              v-model="form.email"
              type="email"
              class="form-control"
              required
              autocomplete="email"
            />
          </div>

          <div class="mb-3">
            <label class="form-label d-flex justify-content-between">
              <span>Password</span>
              <button
                type="button"
                class="btn btn-link btn-sm p-0"
                @click="togglePassword"
              >
                {{ showPassword ? 'Hide' : 'Show' }}
              </button>
            </label>
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="form-control"
              required
              autocomplete="current-password"
            />
          </div>

          <div class="mb-3 form-check">
            <input
              v-model="form.remember"
              type="checkbox"
              class="form-check-input"
              id="rememberCheck"
            />
            <label class="form-check-label" for="rememberCheck">
              Remember me
            </label>
          </div>

          <button
            type="submit"
            class="btn btn-primary w-100"
            :disabled="loading"
          >
            <span
              v-if="loading"
              class="spinner-border spinner-border-sm me-2"
            ></span>
            Sign in
          </button>
        </form>

        <p class="text-muted small text-center mt-3 mb-0">
          Protected area. Authorized staff only.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../../axios';

export default {
  name: 'LoginView',
  data() {
    return {
      form: {
        email: '',
        password: '',
        remember: false,
      },
      loading: false,
      error: null,
      showPassword: false,
    };
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },
    async submit() {
      this.loading = true;
      this.error = null;

      try {
        // 1) Get CSRF cookie for Sanctum
        await axios.get('/sanctum/csrf-cookie');

        // 2) Post login
        const response = await axios.post('/api/login', {
          email: this.form.email,
          password: this.form.password,
          remember: this.form.remember,
        });

        // 3) Store user in localStorage (simple approach)
        const { token, user } = response.data;
        
        // Store token and user data
        localStorage.setItem('nexus_token', token);
        localStorage.setItem('nexus_user', JSON.stringify(user));
        
        // Set default axios header
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        // 4) Go to dashboard
        this.$router.push({ name: 'dashboard' });
      } catch (e) {
        if (e.response && e.response.status === 422) {
          this.error = e.response.data.message || 'Invalid credentials.';
        } else {
          this.error = 'Unable to login. Please try again.';
        }
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
