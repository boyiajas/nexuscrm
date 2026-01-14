<template>
  <div class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="card shadow-sm" style="max-width: 460px; width: 100%;">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <div
            class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2"
            style="width: 48px; height: 48px;"
          >
            <i class="bi bi-person-plus"></i>
          </div>
          <h4 class="mb-0">Create Account</h4>
          <small class="text-muted">Register to access NexusCRM</small>
        </div>

        <div v-if="error" class="alert alert-danger py-2">
          {{ error }}
        </div>

        <form @submit.prevent="submit">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input
              v-model="form.name"
              type="text"
              class="form-control"
              required
            />
          </div>

          <div class="mb-3">
            <label class="form-label">Email address</label>
            <input
              v-model="form.email"
              type="email"
              class="form-control"
              required
            />
          </div>

          <div class="mb-3">
            <label class="form-label">Department (optional)</label>
            <input
              v-model="form.department"
              type="text"
              class="form-control"
              placeholder="Collections, Sales, Legal..."
            />
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="form-control"
              required
              minlength="6"
            />
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input
              v-model="form.password_confirmation"
              :type="showPassword ? 'text' : 'password'"
              class="form-control"
              required
              minlength="6"
            />
          </div>

          <div class="mb-3 form-check">
            <input
              v-model="showPassword"
              type="checkbox"
              class="form-check-input"
              id="showPwdCheck"
            />
            <label class="form-check-label" for="showPwdCheck">
              Show password
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
            Register
          </button>
        </form>

        <p class="text-center mt-3 mb-0 small">
          Already have an account?
          <router-link :to="{ name: 'login' }">Sign in</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '../../axios';

export default {
  name: 'RegisterView',
  data() {
    return {
      form: {
        name: '',
        email: '',
        department: '',
        password: '',
        password_confirmation: '',
      },
      loading: false,
      error: null,
      showPassword: false,
    };
  },
  methods: {
    async submit() {
      this.loading = true;
      this.error = null;

      try{
        // Sanctum CSRF
        await axios.get('/sanctum/csrf-cookie');

        const res = await axios.post('/api/register', this.form);

        // Store user in localStorage like login
        localStorage.setItem('nexus_user', JSON.stringify(res.data));

        // redirect to dashboard
        this.$router.push({ name: 'dashboard' });
      } catch (e) {
        if (e.response && e.response.status === 422) {
          // user-friendly message
          this.error =
            e.response.data.message ||
            'Please check the form and try again.';
        } else {
          this.error = 'Registration failed. Please try again.';
        }
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
