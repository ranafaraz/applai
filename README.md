# Personal Outreach CRM

A Laravel-based personal operating system for professional outreach and visibility. The application centralizes contacts, opportunities, email outreach, follow-ups, documents, and reporting, and now includes **Social Studio** for LinkedIn content planning, approval, publishing, media handling, and analytics.

The project is designed so that AI tools such as ChatGPT, GPT Actions, MCP clients, or workflow automation can create structured drafts and recommendations while the CRM remains the system of record and the user remains in control of high-impact actions such as publishing.

## Product Scope

### Outreach CRM

- Manage contacts for networking, applications, research, grants, and scholarship outreach.
- Track opportunities, statuses, priorities, deadlines, notes, and linked contacts.
- Compose email drafts, use signatures/templates, attach documents, schedule messages, and monitor sending activity.
- Sync replies through IMAP, process follow-ups, maintain suppression lists, and review outreach reports.
- Import contacts and opportunities and maintain a timeline/audit trail of activity.

### Social Studio — LinkedIn First

- Connect LinkedIn accounts through OAuth and retain account capability/status metadata.
- Create LinkedIn drafts for text, article-link, image, and multi-image publishing flows.
- Maintain a media library, attach featured assets, and store alt text for image posts.
- Review, approve, schedule, publish, update, and delete LinkedIn content through the CRM workflow.
- Store LinkedIn post URNs/URLs, publishing activity, provider status, and analytics snapshots.
- Provide a foundation for additional social providers in future iterations.

### AI and Automation Integration

- Expose scoped API-key protected endpoints for CRM and Social Studio automation.
- Publish public OpenAPI documents for GPT Actions import.
- Support external clients such as ChatGPT, MCP, and n8n-style ingestion workflows.
- Keep publication gated behind approval controls rather than permitting unrestricted AI posting.

---

## Technical Stack

| Layer | Technology |
|---|---|
| Backend | PHP `^8.3`, Laravel `^13.8` |
| UI | Blade, Livewire `^4.3`, Tailwind CSS, Alpine.js |
| Database | MariaDB / MySQL |
| Queues and scheduling | Laravel database queues and scheduler |
| Email / inbox | Symfony Mailer through Laravel, `webklex/php-imap` |
| Imports | `league/csv` |
| Activity logging | `spatie/laravel-activitylog` plus domain activity/audit records |
| Social provider | LinkedIn REST API with OAuth tokens |
| API integration | `X-Api-Key` clients with scopes, OpenAPI schemas, MCP client support |

---

## Core Architecture

The application is a Laravel monolith with clear domain boundaries:

```text
app/
  Console/Commands/
    CRM scheduled tasks and social publication/analytics commands
  Events/ and Listeners/
    Email and reply-driven workflow activity
  Http/Controllers/
    Web UI, admin, integration management, OpenAPI generation
    Api/Gpt/V1/             Scoped automation endpoints
    SocialStudio/           LinkedIn-focused web workflow
  Http/Middleware/
    API client authentication, scope enforcement, request logging
  Jobs/
    Email, sync/import, and LinkedIn publication jobs
  Models/
    CRM, integration, tenant, and social studio persistence models
  Services/
    Email, IMAP, follow-up, import, dashboard, timeline services
    Social/                 LinkedIn API, media, publishing services

routes/
  web.php                   CRM UI, Social Studio UI, integrations, OpenAPI URLs
  api.php                   GPT/automation CRM and Social Studio APIs
  console.php               Scheduler configuration

mcp/
  src/crm-client.ts         X-Api-Key based external CRM client foundation
```

### Domain Workflow Model

```text
Research / AI drafting
        ↓
CRM draft + media asset storage
        ↓
Human review and approval
        ↓
Schedule or publish action
        ↓
Queue / Laravel scheduler
        ↓
LinkedIn REST API
        ↓
Published-post tracking and analytics snapshots
```

---

## Main Web Areas

Authenticated application areas include:

- Dashboard, contacts, opportunities, documents, imports, email accounts, email templates, signatures, outbox, inbox, follow-ups, reports, tags, audit logs, notifications, and settings.
- Social Studio dashboard, OAuth apps, connected accounts, posts, calendar, published content, insights, and media library.
- Integration/API client management under Settings → Integrations.
- Tenant administration and super-admin screens where authorized.

Public endpoints include landing/privacy/terms pages and OpenAPI schema documents for external AI integration.

---

## API and GPT Actions Integration

### Authentication Model

Automation APIs use an `X-Api-Key` header generated through **Settings → Integrations**. The API authentication middleware validates:

- Token presence, validity, activation and expiry.
- API client activation and expiry.
- Optional IP allowlisting.
- Client/token last-used timestamps.
- User binding for downstream tenant/user-scoped queries.

Example request header:

```http
X-Api-Key: pocrm_live_<token>
Accept: application/json
Content-Type: application/json
```

### OpenAPI Documents

The application exposes separate public schemas so an AI client does not need one oversized action definition:

| Schema | Purpose |
|---|---|
| `/openapi/gpt-actions.json` | CRM opportunities, contacts, drafts, attachments, signatures, follow-ups, replies and ingestion capabilities |
| `/openapi/social-gpt-actions.json` | Social Studio / LinkedIn drafting, media, confirmation, publishing-status and analytics capabilities |

### CRM Automation API

Base path: `/api/gpt/v1`

Major capabilities:

- Health and authenticated identity checks.
- Dashboard summary.
- Opportunity and contact search/creation/note linking.
- Signatures and attachment management.
- Email draft creation, preview and attachment handling.
- Follow-up and recent reply retrieval.
- Bulk contact/opportunity ingestion.
- Confirmation records for controlled AI actions.

### Social Studio Automation API

Base path: `/api/social/v1`

Major capabilities:

- Read/verify LinkedIn connected accounts.
- Create, retrieve, update and delete LinkedIn post drafts.
- Upload CRM media assets and attach/detach media from posts.
- Upload approved media to LinkedIn during publication workflows.
- Request publication confirmations and inspect their state.
- Read provider status for published posts.
- Retrieve or synchronize LinkedIn analytics where the connected account has the necessary permissions.

### Safety Boundary for AI Clients

Draft creation and content management may be automated. Publishing is intentionally gated: a LinkedIn post must be approved before `LinkedInPublishService` will publish it. Content edits after approval should reset approval before further publication.

---

## Social Studio Technical Details

### LinkedIn OAuth Setup

Create a LinkedIn developer application and enable the **Share on LinkedIn** product. Configure production values in `.env`:

```env
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI="${APP_URL}/social-studio/connections/callback"
LINKEDIN_SCOPES="w_member_social openid profile email"
```

The application stores connected-account access and refresh tokens using Laravel encrypted casts. Account records track status, expiration, granted/missing scopes, capability metadata, and whether an account is the default posting target.

### LinkedIn Publishing Support

The publishing service builds LinkedIn REST payloads for:

- Plain text posts.
- Article-link posts.
- Single-image posts.
- Multi-image posts through approved attached media assets.

For image publishing, media must be approved and uploaded to LinkedIn before the post payload is sent. LinkedIn URNs and derived public post URLs are retained after publication.

### Analytics Permissions

Publishing through `w_member_social` does not necessarily provide analytics access. The analytics client code expects additional LinkedIn permissions for member post and follower statistics. Analytics endpoints should therefore be treated as capability-dependent and may return permission errors until LinkedIn approves the required access for the application/account.

### Publication States and Controls

The social implementation stores draft content, approval state, schedule state, LinkedIn target/account association, media associations, publishing jobs, confirmation tokens, audit events and analytics snapshots. Publishing guards prevent sending when:

- The target is already published.
- The LinkedIn account is disconnected or its token has expired.
- A LinkedIn member/person URN has not been resolved.
- The post has not been approved.

---

## Important Implementation Note: GPT-Scheduled Social Posts

The web Social Studio workflow updates post targets into the `scheduled` state consumed by the `social:publish-due-posts` scheduler.

The current GPT/API confirmation workflow supports requesting a `schedule` confirmation, but its approval handler does not yet visibly mirror the approved schedule onto a `SocialPostTarget` record in the same way the web workflow does. Because the scheduled publisher only processes targets already marked `scheduled` and due, a schedule created exclusively through the GPT/API flow should be verified in the CRM UI until that handoff is completed and covered by integration tests.

This is a known workflow-hardening item, not a recommendation to bypass approval.

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

Edit `.env` for production:

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
php artisan db:seed --force        # loads demo data when desired
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

### 5. Apache Alternative

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/personal-crm/public
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem
    <Directory /var/www/personal-crm/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 6. Laravel Scheduler

```bash
sudo crontab -u www-data -e
# Add:
* * * * * cd /var/www/personal-crm && php artisan schedule:run >> /dev/null 2>&1
```

Configured tasks include:

| Frequency | Task | Command |
|---|---|---|
| Every 5 minutes | Queue scheduled emails | `crm:send-scheduled` |
| Every 15 minutes | Sync active IMAP inboxes | `crm:sync-inboxes` |
| Every 30 minutes | Process due email follow-ups | `crm:process-follow-ups` |
| Midnight | Reset daily email counters | `crm:reset-daily-counters` |
| Every 5 minutes | Publish due approved social targets | `social:publish-due-posts` |
| Hourly | Sync recent LinkedIn analytics | `social:sync-linkedin-analytics --hours=72` |
| Daily at 03:00 | Sync broader LinkedIn analytics window | `social:sync-linkedin-analytics --hours=720` |

### 7. Queue Worker with Supervisor

```bash
sudo apt install -y supervisor
```

`/etc/supervisor/conf.d/personal-crm.conf`:

```ini
[program:crm-worker]
process_name=%(program_name)02d
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
3. Add the mailbox account in the CRM with:
   - SMTP: `smtp.gmail.com:587`, encryption `tls`
   - IMAP: `imap.gmail.com:993`, encryption `ssl`
   - Password: the App Password, not the main Google password.

SMTP/IMAP credentials are stored encrypted at rest using Laravel encrypted casts and should not be displayed again after saving.

---

## Development and Testing

```bash
composer install
npm install
php artisan migrate
npm run build
php artisan test
```

For local concurrent Laravel, queue, log and Vite processes, the Composer development script is available:

```bash
composer run dev
```

### Testing Priorities for Continued Development

The highest-value integration coverage areas are:

- GPT/API draft → approval → scheduled LinkedIn target → scheduler → publish lifecycle.
- Approval invalidation after content edits.
- Token expiration, missing permissions and account reconnection behavior.
- Image/media approval and upload before publication.
- API-scope enforcement and tenant/user isolation.
- Analytics permission failures and snapshot synchronization.

---

## Security and Operational Notes

- SMTP/IMAP credentials and LinkedIn access/refresh tokens are encrypted using Laravel encrypted casts.
- API clients use scoped `X-Api-Key` credentials with activation, expiry and optional IP allowlists.
- User-owned social posts and connected accounts are queried by `user_id`; maintain the same ownership boundary for new features.
- Publication is approval-gated; do not weaken this guard for AI integrations.
- Suppression checks and per-account email limits protect outbound email workflows.
- Provider failures are sanitized before recording/logging where implemented; retain that pattern for future integrations.
- Use HTTPS in production and protect OAuth application secrets and API keys through environment configuration.

---

## Demo Credentials

Seeded demo environments may expose:

| Email | Password |
|---|---|
| `demo@example.com` | `password` |
| `admin@example.com` | `password` |

Do not use seeded demo credentials in a production deployment.
