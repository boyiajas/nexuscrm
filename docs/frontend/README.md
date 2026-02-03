# Frontend Overview

- Framework: Vue 3 SPA built with Vite (`resources/js/app.js`). Global styles from Bootstrap 5 + Bootstrap Icons (`bootstrap/dist/css/bootstrap.min.css`, `bootstrap-icons`).
- Root component: `resources/js/components/layout/App.vue` renders `<router-view />`. Authenticated pages nest inside `MainLayout.vue` (sidebar + navbar + router outlet).
- Routing: `resources/js/router.js` defines auth/public routes; navigation guard checks `localStorage.nexus_user` and redirects unauthenticated users to `/login`.
- HTTP: axios wrapper in `resources/js/axios.js` sets base URL and attaches Sanctum token if present. Components import it directly.
- Charts: Dashboard uses Chart.js line chart; other tables/charts are Bootstrap-based.
- State: no centralized store; each view fetches its own data from the API.
