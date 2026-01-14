import axios from 'axios';

axios.defaults.baseURL = '/';
axios.defaults.withCredentials = true; // important for Sanctum cookies

// Re-hydrate auth header on page refresh so protected API calls keep working
const storedToken = localStorage.getItem('nexus_token');
if (storedToken) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
}

export default axios;
