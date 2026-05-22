# Personal Outreach CRM

A centralized Laravel monolith for managing job, scholarship, research, grant, and networking outreach in one place.

## Demo Credentials

| Email | Password |
|-------|----------|
| demo@example.com | password |
| admin@example.com | password |

---

## VPS Deployment Guide

### 1. PHP & Extensions

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

### 3. Deploy & Configure

```bash
cd /var/www
git clone https://github.com/ranafaraz/personal-crm.git personal-crm
cd personal-crm

composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Edit `.env`:
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
```

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # loads demo data
php artisan storage:link

# Permissions
sudo chown -R www-data:www-data /var/www/personal-crm
sudo chmod -R 775 /var/www/personal-crm/storage
sudo chmod -R 775 /var/www/personal-crm/bootstrap/cache

# Production optimizations
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

### 6. Cron (Laravel Scheduler)

```bash
sudo crontab -u www-data -e
# Add:
* * * * * cd /var/www/personal-crm && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs:
- **Every 5 min** — send scheduled emails (`crm:send-scheduled`)
- **Every 15 min** — sync IMAP inboxes (`crm:sync-inboxes`)
- **Every 30 min** — process due follow-ups (`crm:process-follow-ups`)
- **Midnight** — reset daily send counters (`crm:reset-daily-counters`)

### 7. Supervisor (Queue Worker)

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

1. Enable 2-FA on Google → Settings → App Passwords → generate 16-char password
2. Add account in CRM:
   - **SMTP**: `smtp.gmail.com:587`, encryption `tls`
   - **IMAP**: `imap.gmail.com:993`, encryption `ssl`
   - Password: the App Password (not your Gmail login)

Credentials are stored **encrypted at rest** using Laravel's `encrypted` cast. They are never shown after saving.

---

## Architecture

```
app/
  Console/Commands/     crm:sync-inboxes, crm:send-scheduled, crm:process-follow-ups
  Events/               EmailSent, EmailFailed, ReplyReceived
  Http/Controllers/     16 controllers (Auth, Dashboard, Contacts, Opportunities...)
  Http/Requests/        Form validation
  Jobs/                 SendEmailJob, SyncInboxJob, ProcessContactImportJob...
  Listeners/            Timeline logging, follow-up cancellation on reply
  Models/               16 models with full relationships
  Policies/             Owner-only access control
  Services/
    EmailSendingService   Symfony Mailer, per-account limits, suppression check
    ImapSyncService       webklex/php-imap, reply-to-outbound matching
    FollowUpService       Schedule, process, and cancel follow-ups
    CsvImportService      Auto-detect columns, upsert contacts
    DashboardService      All dashboard stats and funnel data
    TimelineService       Log events on contacts and opportunities

database/migrations/    18 migrations
database/seeders/       Demo users, contacts, opportunities, templates, tags
resources/views/        Blade + Tailwind CSS + Alpine.js
routes/web.php          97 named routes
routes/console.php      Scheduler definitions
```

---

## Running Tests

```bash
php artisan test
```

---

## Security Notes

- SMTP/IMAP passwords encrypted via Laravel `encrypted` cast
- Passwords never shown after saving (edit forms leave password blank)
- User data scoped by `user_id` on every query
- Authorization policies on all CRUD actions
- Suppression list prevents accidental email to opted-out contacts
- Per-account daily/hourly send limits prevent abuse
