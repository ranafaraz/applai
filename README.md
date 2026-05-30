# Personal Outreach CRM

A Laravel-based personal operating system for professional outreach and visibility. It centralizes contacts, opportunities, email outreach, replies, follow-ups, documents, reporting, and **Social Studio** for LinkedIn content planning, approval, publishing, media, and analytics.

The CRM is designed to remain the system of record while AI tools such as ChatGPT, GPT Actions, MCP clients, or workflow automation create structured drafts and recommendations. High-impact actions such as social publishing remain approval-controlled.

## Scope

### Outreach CRM

- Contacts for networking, applications, research, grant, and scholarship outreach.
- Opportunities with status, priority, deadline, notes, URLs, and linked contacts.
- Email accounts, templates, signatures, attachments, drafts, scheduled sending, inbox sync, follow-ups, suppression controls, reporting, and audit activity.
- CSV-based contact and opportunity ingestion.
- Tenant/team administration and API client integration management.

### Social Studio — LinkedIn First

- LinkedIn OAuth app and connected-account management.
- Text, article-link, image, and multi-image post workflows.
- Media library, featured assets, approval metadata, and image alt text.
- Drafting, review, approval, scheduling, publication, published-post status, and analytics surfaces.
- Architecture intended to support additional social providers later.

### AI and Automation Integration

- Scoped API-key protected endpoints for CRM and Social Studio automation.
- Public OpenAPI definitions for GPT Actions import.
- MCP client foundation and API patterns suitable for workflow automation.
- Human approval boundary for social publishing.

---

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP `^8.3`, Laravel `^13.8` |
| UI | Blade, Livewire `^4.3`, Tailwind CSS, Alpine.js |
| Database | MariaDB / MySQL |
| Queues and scheduling | Laravel database queues and scheduler |
| Email / inbox | Laravel mail stack and `webklex/php-imap` |
| Imports | `league/csv` |
| Activity logging | `spatie/laravel-activitylog` plus domain audit/activity records |
| Social provider | LinkedIn REST API with OAuth tokens |
| External automation | Scoped `X-Api-Key` APIs, OpenAPI schemas, MCP client support |

---

## Architecture

```text
app/
  Console/Commands/       CRM jobs, social publishing, analytics synchronization
  Events/ and Listeners/  Email and reply-driven workflow activity
  Http/Controllers/
    Api/Gpt/V1/           Scoped external automation endpoints
    SocialStudio/         LinkedIn-focused web workflow
    Admin/                Tenant and administration surfaces
  Http/Middleware/        API client authentication, scope and logging middleware
  Jobs/                   Email, import/sync, and LinkedIn publishing jobs
  Models/                 CRM, API integration, tenant, and social persistence
  Services/
    Email/IMAP/follow-up/dashboard/timeline services
    Social/               LinkedIn client, media and publishing services

routes/
  web.php                 Browser UI, Social Studio, integrations, OpenAPI URLs
  api.php                 GPT/automation and Social Studio APIs
  console.php             Scheduler definitions

mcp/
  src/crm-client.ts       External CRM client using X-Api-Key authentication
```

### Workflow Model

```text
Research / AI drafting
        ↓
CRM draft and media asset storage
        ↓
Human review and approval
        ↓
Schedule or publish action
        ↓
Laravel scheduler / queue
        ↓
LinkedIn REST API
        ↓
Post tracking and analytics snapshots
```

---

## Major Web Areas

Authenticated screens include dashboard, contacts, opportunities, documents, imports, email accounts, email templates, signatures, outbox, inbox, follow-ups, reports, tags, audit logs, notifications, settings, integrations, and administration screens where authorized.

Social Studio includes dashboard, OAuth apps, connected accounts, posts, calendar, published content, insights, and media library routes.

Public application endpoints include landing/privacy/terms pages and the OpenAPI schema documents described below.

---

## API and GPT Actions Integration

### Authentication

Automation APIs use an `X-Api-Key` header generated in **Settings → Integrations**. Middleware validates token status and expiry, client status and expiry, optional IP allowlisting, and binds the corresponding application user for scoped access.

```http
X-Api-Key: pocrm_live_<token>
Accept: application/json
Content-Type: application/json
```

### OpenAPI Documents

| URL | Purpose |
|---|---|
| `/openapi/gpt-actions.json` | CRM opportunities, contacts, drafts, attachments, signatures, follow-ups, replies, and ingestion |
| `/openapi/social-gpt-actions.json` | LinkedIn drafting, media, confirmations, publication status, and analytics |

Keeping CRM and Social Studio in separate schemas also keeps action definitions manageable for GPT integrations.

### CRM Automation API

Base path: `/api/gpt/v1`

Capabilities include health and identity checks, dashboard summaries, opportunity/contact management, notes and links, signatures, attachments, email drafts and rendered previews, follow-ups, recent replies, ingestion endpoints, and confirmation records.

### Social Studio Automation API

Base path: `/api/social/v1`

Capabilities include connected-account inspection and verification, LinkedIn draft CRUD, media asset creation and association, provider upload flow, publication confirmation requests, provider-status reads, and analytics retrieval/synchronization where provider permissions allow it.

### AI Safety Boundary

AI integrations may create and revise drafts. Publication is approval-gated: `LinkedInPublishService` refuses to publish unapproved posts. Changes made after approval should invalidate or reset approval before publication proceeds.

---

## Social Studio Technical Details

### LinkedIn OAuth Configuration

Create a LinkedIn developer application and enable **Share on LinkedIn**. Configure production values in `.env`:

```env
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI="${APP_URL}/social-studio/connections/callback"
LINKEDIN_SCOPES="w_member_social openid profile email"
```

Access and refresh tokens are persisted through Laravel encrypted casts. Connected-account records track account URN, status, token expiry, granted/missing scopes, capabilities, and default-account selection.

### LinkedIn Publishing

The publishing services support:

- Text posts.
- Article-link posts.
- Single-image posts.
- Multi-image posts using approved attached media assets.

For image publication, assets must be approved and uploaded to LinkedIn before post creation. Published posts retain the returned LinkedIn URN and an application-derived LinkedIn post URL when the URN pattern is recognized.

### Analytics Permissions

The implemented analytics client calls member post and follower statistics endpoints and explicitly handles missing analytics permissions. Publishing permission alone does not guarantee analytics access; analytics behavior is capability-dependent on LinkedIn access granted to the app/account.

### Publish Guards

Publishing is rejected when:

- A target has already been published.
- The selected LinkedIn account is disconnected or the token is expired.
- No LinkedIn member/person URN is available.
- The post approval state is not `approved`.

---

## Known Workflow Hardening Item: GPT-Scheduled Social Posts

The browser-based Social Studio scheduling workflow sets post targets to `scheduled`, which is the state consumed by the `social:publish-due-posts` scheduler.

The current GPT/API confirmation workflow accepts a `schedule` confirmation request, but its approval handler does not visibly copy the approved schedule into a scheduled `SocialPostTarget` in the same way as the web workflow. Since the scheduler only processes due targets already marked `scheduled`, schedules created exclusively through the GPT/API flow should be verified in the CRM UI until this handoff is completed and protected by integration tests.

This limitation should be fixed without weakening the approval requirement.

---

## VPS Deployment Guide

### 1. PHP and Extensions

```bash
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mysql \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
  php8.3-intl php8.3-gd php8.3-tokenizer php8.3-ctype php8.3-fileinfo \
  php8.3-imap php8.3-openssl
```

### 2. MariaDB Setup

```bash
sudo apt install -y mariadb-server
sudo mysql_secure_installation
sudo mariadb -u root -p -e "
  CREATE DATABASE personal_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'crm_user'@'localhost' IDENTIFIED BY 'strong_password';
  GRANT ALL PRIVILEGES ON personal_crm.* TO 'crm_user'@'localhost';
  FLUSH PRIVILEGES;"
```

### 3. Deploy and Configure

```bash
cd /var/www
git clone https://github.com/ranafaraz/personal-crm.git personal-crm
cd personal-crm
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Set production `.env` values:

```env
APP_NAME="Personal Outreach CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=personal_crm
DB_USERNAME=crm_user
DB_PASSWORD=strong_password

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI="${APP_URL}/social-studio/connections/callback"
LINKEDIN_SCOPES="w_member_social openid profile email"
```

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # only when seeded/demo data is desired
php artisan storage:link

sudo chown -R www-data:www-data /var/www/personal-crm
sudo chmod -R 775 /var/www/personal-crm/storage
sudo chmod -R 775 /var/www/personal-crm/bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 4. Nginx Virtual Host

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/personal-crm/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    client_max_body_size 25M;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/personal-crm /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d your-domain.com
```

### 5. Laravel Scheduler

```bash
sudo crontab -u www-data -e
# Add:
* * * * * cd /var/www/personal-crm && php artisan schedule:run >> /dev/null 2>&1
```

| Frequency | Task | Command |
|---|---|---|
| Every 5 minutes | Queue scheduled emails | `crm:send-scheduled` |
| Every 15 minutes | Sync active IMAP inboxes | `crm:sync-inboxes` |
| Every 30 minutes | Process due email follow-ups | `crm:process-follow-ups` |
| Midnight | Reset daily email counters | `crm:reset-daily-counters` |
| Every 5 minutes | Publish due approved social targets | `social:publish-due-posts` |
| Hourly | Sync recent LinkedIn analytics | `social:sync-linkedin-analytics --hours=72` |
| Daily at 03:00 | Sync broader LinkedIn analytics window | `social:sync-linkedin-analytics --hours=720` |

### 6. Queue Worker with Supervisor

```bash
sudo apt install -y supervisor
```

`/etc/supervisor/conf.d/personal-crm.conf`:

```ini
[program:crm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/personal-crm/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/personal-crm/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start crm-worker:*
```

---

## Gmail / IMAP Setup

1. Enable two-factor authentication for the Google account.
2. Generate a Google App Password.
3. Add the mailbox in the CRM:
   - SMTP: `smtp.gmail.com:587`, encryption `tls`
   - IMAP: `imap.gmail.com:993`, encryption `ssl`
   - Password: the App Password, not the primary Google password.

SMTP/IMAP credentials are stored encrypted at rest using Laravel encrypted casts and should not be shown again after saving.

---

## Development and Testing

```bash
composer install
npm install
php artisan migrate
npm run build
php artisan test
```

For local Laravel, queue, log and Vite processes:

```bash
composer run dev
```

### Priority Integration Tests

- GPT/API draft → approval → scheduled target → scheduler → LinkedIn publish lifecycle.
- Approval invalidation after content edits.
- Token expiration, permission errors, and account reconnection.
- Image/media approval and upload before publication.
- API scope enforcement and tenant/user isolation.
- Analytics permission failures and snapshot synchronization.

---

## Security and Operational Notes

- SMTP/IMAP credentials and LinkedIn access/refresh tokens use Laravel encrypted casts.
- API clients use scoped `X-Api-Key` credentials with activation, expiry, and optional IP allowlists.
- User-owned CRM and social records must remain scoped to the authenticated user/tenant.
- Publication is approval-gated; do not weaken this control for AI integrations.
- Suppression checks and per-account email limits protect outbound email workflows.
- Provider errors should be sanitized before storage or logging.
- Use HTTPS in production and store OAuth secrets and API keys only through protected configuration.

---

## Demo Credentials

Seeded demo environments may expose:

| Email | Password |
|---|---|
| `demo@example.com` | `password` |
| `admin@example.com` | `password` |

Do not use seeded demo credentials in a production deployment.
