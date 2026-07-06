import axios from 'axios';

axios.defaults.baseURL = '/';
// The SPA uses Sanctum personal access tokens for authenticated API requests.
// Avoid mixing bearer-token auth with a stale cookie-backed session state.
axios.defaults.withCredentials = false;

// Re-hydrate auth header on page refresh so protected API calls keep working
const storedToken = localStorage.getItem('nexus_token');
if (storedToken) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
}

export async function syncAuthenticatedUser() {
  const storedToken = localStorage.getItem('nexus_token');
  if (!storedToken) {
    return null;
  }

  if (!axios.defaults.headers.common['Authorization']) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
  }

  const response = await axios.get('/api/me');
  const user = response.data || null;

  if (user) {
    localStorage.setItem('nexus_user', JSON.stringify(user));
    window.dispatchEvent(new CustomEvent('auth-user-updated', { detail: user }));
  }

  return user;
}

axios.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;
    if (status === 401) {
      localStorage.removeItem('nexus_token');
      localStorage.removeItem('nexus_user');
      delete axios.defaults.headers.common['Authorization'];

      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

    return Promise.reject(error);
  }
);

export default axios;
