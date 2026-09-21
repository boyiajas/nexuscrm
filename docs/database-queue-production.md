# Database queue workers

The database queue has three workloads with different runtime needs. Keep
`QUEUE_CONNECTION=database` and `CACHE_STORE=database` in production. Set:

```dotenv
DB_QUEUE_RETRY_AFTER=180
DB_LONG_QUEUE_RETRY_AFTER=3900
```

Run one worker per queue to reduce concurrent `FOR UPDATE` reservations on the
same queue. All three connections use the existing `jobs` table.

| Supervisor program | Command | Processes |
| --- | --- | ---: |
| `nexuscrm-whatsapp` | `php -d max_execution_time=0 /var/www/public_html/artisan queue:work database --queue=whatsapp --sleep=1 --tries=3 --timeout=120` | 1 |
| `nexuscrm-default` | `php -d max_execution_time=0 /var/www/public_html/artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=60` | 1 |
| `nexuscrm-imports` | `php -d max_execution_time=0 /var/www/public_html/artisan queue:work database_long --queue=imports --sleep=1 --tries=3 --timeout=3600` | 1 |

Add `nexuscrm-imports.conf` with the same `directory`, `user`, `autostart`,
`autorestart`, `stopasgroup`, and `killasgroup` settings as the current programs.
Set `stopwaitsecs=150` for WhatsApp, `stopwaitsecs=90` for default, and
`stopwaitsecs=3700` for imports so a Supervisor restart allows running jobs to
finish. WhatsApp sends have a 120-second timeout and a 180-second reservation.
Import jobs have a 3600-second timeout and a 3900-second reservation.

Before switching the workers, let existing imports and deletions in the
`default` queue finish. Jobs already stored in `default` do not move to
`imports` when new code is deployed. Check for waiting long jobs with:

```sql
SELECT COUNT(*) FROM jobs
WHERE queue = 'default'
  AND (payload LIKE '%ImportClientsJob%' OR payload LIKE '%DeleteBatchJob%');
```

After deploying the code and updating Supervisor files, run `php artisan
config:cache`, then `supervisorctl reread` and `supervisorctl update`. Supervisor
will start the new imports group and replace the changed worker groups.
