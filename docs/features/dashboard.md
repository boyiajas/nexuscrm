# Dashboard

## Purpose
Home view showing operational KPIs, activity, and delivery rollups.

## Backend
- `DashboardController@index` (`GET /api/dashboard`): returns summary counts (clients, campaign counts, open chats), delivery stats aggregated from `campaign_clients`, channel breakdown (JSON `channels` on campaigns), recent audit activity (scoped to user for non-super-admin), and 7-day campaign creation data.
- `DashboardController@campaignActivity` (`GET /api/dashboard/campaign-activity`): 30-day campaign activity series (labels/data) if you want a longer chart.

## Frontend
- View: `resources/js/views/Dashboard.vue`.
- Renders summary cards, channel breakdown progress bars, recent activity list, delivery stats, and a Chart.js line chart. Refresh button calls `/api/dashboard`.
- Data cleanliness: chart rendering waits for `$nextTick` after data changes; limits recent activity to 10 items.

## Data & Permissions
- Department-scoped for non-`SUPER_ADMIN` users (clients and campaigns filtered by department).
- Delivery stats computed from `campaign_clients` statuses across WhatsApp/Email/SMS.
