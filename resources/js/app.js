//import './bootstrap';

import { createApp } from 'vue';
import router from './router';
import App from './components/layout/App.vue';

import 'bootstrap/dist/css/bootstrap.min.css';
// Icon CSS
import 'bootstrap-icons/font/bootstrap-icons.css';   // for Bootstrap Icons

import 'bootstrap';

import '../css/app.css';

import axios from './axios';

const app = createApp(App);

app.config.globalProperties.$axios = axios;

app.use(router);
app.mount('#app');

