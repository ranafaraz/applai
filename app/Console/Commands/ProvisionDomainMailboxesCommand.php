<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Throwable;

/**
 * Provision mailboxes in PostfixAdmin's MySQL backend for the three CRM
 * domain accounts (info@dexdevs.com, info@alwakeelinstitute.com,
 * info@universalphysioclinic.com).
 *
 * Reads PostfixAdmin database credentials from /etc/postfixadmin/config.local.php
 * (Debian default) or /var/www/postfixadmin/config.local.php and inserts the
 * mailbox + alias rows the same way the PostfixAdmin web UI would.
 *
 * Passwords are hashed with bcrypt (PostfixAdmin's default
 * `BLF-CRYPT` / `$encrypt = 'php_crypt:BLOWFISH'` scheme).
 */
class ProvisionDomainMailboxesCommand extends Command
{
    protected $signature = 'crm:provision-mailboxes
        {--dry-run : Show what would happen without writing anything}';

    protected $description = 'Create missing info@{domain} mailboxes in PostfixAdmin and print credentials';

    private const DOMAINS = [
        'dexdevs.com',
        'alwakeelinstitute.com',
        'universalphysioclinic.com',
    ];

    public function handle(): int
    {
        $configCreds = $this->resolvePostfixAdminCreds();
        if (! $configCreds) {
            $this->error('Could not locate PostfixAdmin database config. Tried:');
            foreach ($this->candidateConfigs() as $p) {
                $this->line("  - {$p}");
            }
            return self::FAILURE;
        }

        $this->info("PostfixAdmin DB: host={$configCreds['host']} db={$configCreds['db']} (config: {$configCreds['_config']})");

        try {
            $dsn = "mysql:host={$configCreds['host']};dbname={$configCreds['db']};charset=utf8mb4";
            $pdo = new PDO($dsn, $configCreds['user'], $configCreds['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $this->error('Cannot connect to PostfixAdmin DB: ' . $e->getMessage());
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $created = [];

        foreach (self::DOMAINS as $domain) {
            $email = "info@{$domain}";

            $exists = (int) $pdo->query("SELECT COUNT(*) FROM mailbox WHERE username = " . $pdo->quote($email))->fetchColumn();
            if ($exists > 0) {
                $this->line("  exists: {$email} — leaving unchanged");
                continue;
            }

            // Ensure the domain row exists (PostfixAdmin requires it)
            $domainExists = (int) $pdo->query("SELECT COUNT(*) FROM domain WHERE domain = " . $pdo->quote($domain))->fetchColumn();
            if ($domainExists === 0) {
                $this->warn("  domain row missing in postfixadmin.domain for {$domain} — skipping (add via PostfixAdmin UI first)");
                continue;
            }

            $password = $this->generatePassword();
            $hash     = password_hash($password, PASSWORD_BCRYPT);

            // Match PostfixAdmin's `BLF-CRYPT` format prefix.
            // PostfixAdmin reads `{BLF-CRYPT}$2y$...` or raw `$2y$...` depending on version.
            $stored = '{BLF-CRYPT}' . $hash;

            $maildir = "{$domain}/info/";

            if ($dry) {
                $this->line("  DRY: would create {$email} (password printed at end)");
                $created[$email] = $password;
                continue;
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO mailbox
                    (username, password, name, maildir, quota, domain, created, modified, active)
                    VALUES (:u, :p, :n, :m, 0, :d, NOW(), NOW(), 1)");
                $stmt->execute([
                    ':u' => $email,
                    ':p' => $stored,
                    ':n' => 'Info ' . ucfirst(strstr($domain, '.', true) ?: $domain),
                    ':m' => $maildir,
                    ':d' => $domain,
                ]);

                // PostfixAdmin also creates a self-alias so the address resolves to itself
                $aliasExists = (int) $pdo->query("SELECT COUNT(*) FROM alias WHERE address = " . $pdo->quote($email))->fetchColumn();
                if ($aliasExists === 0) {
                    $aliasStmt = $pdo->prepare("INSERT INTO alias
                        (address, goto, domain, created, modified, active)
                        VALUES (:a, :g, :d, NOW(), NOW(), 1)");
                    $aliasStmt->execute([
                        ':a' => $email,
                        ':g' => $email,
                        ':d' => $domain,
                    ]);
                }

                // Bump mailbox count on domain row
                $pdo->prepare("UPDATE domain SET mailboxes = mailboxes + 1, modified = NOW() WHERE domain = :d")
                    ->execute([':d' => $domain]);

                $pdo->commit();
                $this->info("  created: {$email}");
                $created[$email] = $password;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $this->error("  failed to create {$email}: " . $e->getMessage());
            }
        }

        if (! empty($created)) {
            $this->newLine();
            $this->info('────── New mailbox credentials ──────');
            foreach ($created as $email => $password) {
                $this->line("  {$email}  {$password}");
            }
            $this->info('Save these in the CRM (Email Accounts → Edit) or set them as env vars:');
            $envs = [
                'info@dexdevs.com'               => 'INFO_DEXDEVS_PASSWORD',
                'info@alwakeelinstitute.com'     => 'INFO_AWAKEEL_PASSWORD',
                'info@universalphysioclinic.com' => 'INFO_UPC_PASSWORD',
            ];
            foreach ($created as $email => $password) {
                $env = $envs[$email] ?? 'INFO_PASSWORD';
                $this->line("  {$env}={$password}");
            }
        }

        return self::SUCCESS;
    }

    private function candidateConfigs(): array
    {
        return [
            '/etc/postfixadmin/config.local.php',
            '/var/www/postfixadmin/config.local.php',
            '/usr/share/postfixadmin/config.local.php',
            '/var/www/html/postfixadmin/config.local.php',
            '/var/www/mail.dexdevs.com/postfixadmin/config.local.php',
            '/etc/postfixadmin/config.inc.php',
            '/var/www/postfixadmin/config.inc.php',
        ];
    }

    /**
     * Parse a PostfixAdmin config file to extract MySQL credentials.
     * Looks for $CONF['database_host'] / database_user / database_password / database_name.
     */
    private function resolvePostfixAdminCreds(): ?array
    {
        foreach ($this->candidateConfigs() as $path) {
            if (! is_readable($path)) continue;

            $contents = @file_get_contents($path);
            if (! $contents) continue;

            $get = function (string $key) use ($contents): ?string {
                if (preg_match("/\\\$CONF\\[\\s*['\"]" . preg_quote($key, '/') . "['\"]\\s*\\]\\s*=\\s*['\"]([^'\"]*)['\"]/", $contents, $m)) {
                    return $m[1];
                }
                return null;
            };

            $host = $get('database_host') ?? 'localhost';
            $db   = $get('database_name');
            $user = $get('database_user');
            $pass = $get('database_password');

            if ($db && $user) {
                return [
                    'host'    => $host,
                    'db'      => $db,
                    'user'    => $user,
                    'pass'    => (string) $pass,
                    '_config' => $path,
                ];
            }
        }
        return null;
    }

    private function generatePassword(int $length = 18): string
    {
        // Strong, URL/shell-safe, no ambiguous chars
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%^&*-_=+';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
