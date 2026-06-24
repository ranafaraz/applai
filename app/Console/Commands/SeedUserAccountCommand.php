<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmailSendingService;
use App\Services\ImapSyncService;
use Illuminate\Console\Command;

/**
 * Idempotent setup of a user's CRM account:
 *  - removes mistakenly-created dexdevs@gmail.com email account (wrong address)
 *  - ensures Personal Gmail + DEXDevs Gmail (dexdevs007@gmail.com) + three
 *    self-hosted domain mailboxes (info@dexdevs.com, info@alwakeelinstitute.com,
 *    info@universalphysioclinic.com) exist
 *  - marks the user's preferred default account
 *  - seeds 12 production-quality email templates
 *  - runs SMTP + IMAP connection tests on every account and persists the result
 */
class SeedUserAccountCommand extends Command
{
    protected $signature = 'crm:seed-user
        {email : The user email to seed (e.g. ranafarazahmed@gmail.com)}
        {--default= : Email address of the account to mark as default}';

    protected $description = 'Set up a user account with templates, email accounts, and connection tests';

    public function handle(EmailSendingService $emailService, ImapSyncService $imapService): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User not found: {$email}");
            return self::FAILURE;
        }

        $tenantId = $user->tenant_id ?? Tenant::where('slug', 'default')->value('id');
        $this->info("Seeding for user #{$user->id} ({$user->email}), tenant_id={$tenantId}");

        $this->cleanupLegacy($user);
        $this->ensureEmailAccounts($user, $tenantId);
        $this->seedTemplates($user, $tenantId);
        $this->applyDefault($user);

        $this->testConnections($user, $emailService, $imapService);

        $this->newLine();
        $this->info('Seeding complete.');
        return self::SUCCESS;
    }

    /**
     * Remove email accounts created by previous (incorrect) seed runs.
     * dexdevs@gmail.com was a typo — the real account is dexdevs007@gmail.com.
     */
    private function cleanupLegacy(User $user): void
    {
        $this->line('— Cleanup ———————————————————————');

        foreach (['dexdevs@gmail.com', 'info@awakeelinstitute.com'] as $wrong) {
            $row = EmailAccount::where('user_id', $user->id)->where('email', $wrong)->first();
            if ($row) {
                $row->forceDelete();
                $this->info("  removed mistaken email account {$wrong} (id #{$row->id})");
            } else {
                $this->line("  no mistaken {$wrong} account");
            }
        }

        // Also remove a stray legacy contact created by older seeds
        $stray = Contact::where('user_id', $user->id)->where('email', 'dexdevs007@gmail.com')->first();
        if ($stray) {
            $stray->forceDelete();
            $this->info("  removed legacy contact dexdevs007@gmail.com (id #{$stray->id})");
        }
    }

    /**
     * Ensure all the user's mailboxes exist (Gmail + self-hosted domains).
     * Never overwrites an existing account's stored credentials.
     */
    private function ensureEmailAccounts(User $user, ?int $tenantId): void
    {
        $this->newLine();
        $this->line('— Email accounts ————————————————');

        // --- Gmail mailboxes via smtp.gmail.com / imap.gmail.com ----------
        $this->ensureGmailAccount($user, $tenantId, [
            'name'       => 'Personal Gmail',
            'email'      => 'ranafarazahmed@gmail.com',
            'from_name'  => $user->name ?? 'Rana Faraz',
            'is_default' => true,
            'password'   => env('MY_PERSONAL_GMAIL_APP_PASSWORD'),
        ]);

        $this->ensureGmailAccount($user, $tenantId, [
            'name'       => 'DEXDevs Gmail',
            'email'      => 'dexdevs007@gmail.com',
            'from_name'  => 'DEXDevs',
            'is_default' => false,
            'password'   => env('DEXDEVS_GMAIL_APP_PASSWORD'),
        ]);

        // --- Self-hosted domain mailboxes via mail.<domain> ----------------
        // These rely on a Postfix/Dovecot setup on the VPS with virtual
        // mailboxes for info@<domain>. Mailbox passwords come from env;
        // if they aren't set, account is created with an empty password
        // and the user must update it via the web UI before SMTP works.
        foreach (
            [
                ['domain' => 'dexdevs.com',               'env' => 'INFO_DEXDEVS_PASSWORD'],
                ['domain' => 'alwakeelinstitute.com',      'env' => 'INFO_AWAKEEL_PASSWORD'],
                ['domain' => 'universalphysioclinic.com', 'env' => 'INFO_UPC_PASSWORD'],
            ] as $spec
        ) {
            $this->ensureDomainAccount($user, $tenantId, $spec['domain'], env($spec['env']), $spec['env']);
        }
    }

    private function ensureGmailAccount(User $user, ?int $tenantId, array $spec): void
    {
        $existing = EmailAccount::where('user_id', $user->id)
            ->where('email', $spec['email'])
            ->first();

        if ($existing) {
            $this->line("  exists: {$spec['email']} (id #{$existing->id}) — left unchanged");
            return;
        }

        if (empty($spec['password'])) {
            $this->warn("  skip:   {$spec['email']} — no app password in env");
            return;
        }

        $account = EmailAccount::create([
            'tenant_id'         => $tenantId,
            'user_id'           => $user->id,
            'name'              => $spec['name'],
            'email'             => $spec['email'],
            'from_name'         => $spec['from_name'],
            'smtp_host'         => 'smtp.gmail.com',
            'smtp_port'         => 587,
            'smtp_encryption'   => 'tls',
            'smtp_username'     => $spec['email'],
            'smtp_password'     => $spec['password'],
            'imap_host'         => 'imap.gmail.com',
            'imap_port'         => 993,
            'imap_encryption'   => 'ssl',
            'imap_username'     => $spec['email'],
            'imap_password'     => $spec['password'],
            'daily_limit'       => 300,
            'hourly_limit'      => 30,
            'min_delay_seconds' => 10,
            'is_active'         => true,
            'is_default'        => $spec['is_default'],
            'notes'             => 'Gmail via app password (auto-seeded).',
        ]);

        $this->info("  created: {$account->email} (id #{$account->id})");
    }

    private function ensureDomainAccount(User $user, ?int $tenantId, string $domain, ?string $password, string $envName): void
    {
        $email = "info@{$domain}";

        $existing = EmailAccount::where('user_id', $user->id)->where('email', $email)->first();
        if ($existing) {
            // If env now provides a password and the existing account has none
            // (or a wrong placeholder), sync it. Otherwise leave alone.
            if ($password) {
                $currentSmtp = $existing->getRawOriginal('smtp_password');
                if (empty($existing->smtp_password) || $existing->smtp_password === '' || empty($currentSmtp)) {
                    $existing->update([
                        'smtp_password' => $password,
                        'imap_password' => $password,
                        'is_active'     => true,
                        'notes'         => "Self-hosted mailbox on mail.{$domain}. Password synced from {$envName}.",
                    ]);
                    $this->info("  updated password: {$email} (id #{$existing->id})");
                    return;
                }
            }
            $this->line("  exists: {$email} (id #{$existing->id}) — left unchanged");
            return;
        }

        // Even without a password we still want the EmailAccount row to exist so
        // the user can fill in the password via Edit instead of building the whole
        // account from scratch. Mark it inactive so we don't try to send from it.
        $hasPassword = ! empty($password);

        $account = EmailAccount::create([
            'tenant_id'         => $tenantId,
            'user_id'           => $user->id,
            'name'              => ucfirst(strstr($domain, '.', true) ?: $domain) . ' Info',
            'email'             => $email,
            'from_name'         => ucfirst(strstr($domain, '.', true) ?: $domain),
            'smtp_host'         => "mail.{$domain}",
            'smtp_port'         => 587,
            'smtp_encryption'   => 'tls',
            'smtp_username'     => $email,
            'smtp_password'     => $password ?? '',
            'imap_host'         => "mail.{$domain}",
            'imap_port'         => 993,
            'imap_encryption'   => 'ssl',
            'imap_username'     => $email,
            'imap_password'     => $password ?? '',
            'daily_limit'       => 200,
            'hourly_limit'      => 30,
            'min_delay_seconds' => 15,
            'is_active'         => $hasPassword,
            'is_default'        => false,
            'notes'             => $hasPassword
                ? "Self-hosted mailbox on mail.{$domain}."
                : "Self-hosted mailbox on mail.{$domain}. SET PASSWORD VIA EDIT (or set {$envName} env var).",
        ]);

        if ($hasPassword) {
            $this->info("  created: {$email} (id #{$account->id})");
        } else {
            $this->warn("  created: {$email} (id #{$account->id}) WITHOUT password (set {$envName} or update via Edit)");
        }
    }

    /**
     * Apply the --default option or default to the user's Personal Gmail.
     * Ensures exactly one account has is_default=true.
     */
    private function applyDefault(User $user): void
    {
        $defaultEmail = $this->option('default') ?: 'ranafarazahmed@gmail.com';

        $target = EmailAccount::where('user_id', $user->id)->where('email', $defaultEmail)->first();
        if (! $target) {
            $this->warn("  default account {$defaultEmail} not found, leaving defaults as-is");
            return;
        }

        EmailAccount::where('user_id', $user->id)->update(['is_default' => false]);
        $target->update(['is_default' => true]);
        $this->info("  default account: {$target->email} (id #{$target->id})");
    }

    /**
     * Seed a comprehensive set of useful email templates for the user.
     * Idempotent by (user_id, name).
     */
    private function seedTemplates(User $user, ?int $tenantId): void
    {
        $this->newLine();
        $this->line('— Email templates ———————————————');

        $templates = $this->templateDefinitions();
        $created = 0;
        $existed = 0;

        foreach ($templates as $tpl) {
            $existing = EmailTemplate::where('user_id', $user->id)->where('name', $tpl['name'])->first();

            if ($existing) {
                $existed++;
                continue;
            }

            EmailTemplate::create([
                'tenant_id'  => $tenantId,
                'user_id'    => $user->id,
                'name'       => $tpl['name'],
                'subject'    => $tpl['subject'],
                'body'       => $tpl['body'],
                'type'       => $tpl['type'],
                'is_active'  => true,
                'times_used' => 0,
            ]);
            $created++;
            $this->line("  + {$tpl['name']}");
        }

        $this->info("  templates: {$created} created, {$existed} already existed");
    }

    /** @return array<int, array{name:string,subject:string,body:string,type:string}> */
    private function templateDefinitions(): array
    {
        return [
            ['name' => 'Cold Outreach — Job Application',   'type' => 'initial_outreach', 'subject' => 'Application for {{position}} at {{company}}', 'body' => "Hi {{first_name}},\n\nI came across the {{position}} role at {{company}} and wanted to reach out directly. I've spent the last several years building software in this space and I think my background lines up well with what you're looking for.\n\nA few highlights:\n- [credential 1]\n- [credential 2]\n- [credential 3]\n\nI'd love a short call to explore whether there's a fit. Are you open to a 15-minute conversation next week?\n\nBest,\nRana Faraz"],
            ['name' => 'Cold Outreach — Startup CTO Services','type' => 'initial_outreach','subject' => 'Engineering support for {{company}}','body' => "Hi {{first_name}},\n\nCongrats on the recent traction at {{company}}. I help early-stage founders move faster on the technical side — typically as a fractional CTO or hands-on engineering lead through the first hires.\n\nIf you're juggling product, hiring, and architecture decisions, I'd be glad to share how we've helped similar teams ship faster while keeping the codebase clean.\n\nWould a quick call this or next week make sense?\n\nBest,\nRana Faraz\nDEXDevs"],
            ['name' => 'Cold Outreach — Networking Intro',  'type' => 'networking',       'subject' => 'Quick intro — {{company}} and DEXDevs',    'body' => "Hi {{first_name}},\n\nI've been following what {{company}} is building and wanted to introduce myself. I run engineering at DEXDevs and we work on similar problems.\n\nI'd love to swap notes sometime — no pitch, just a genuine conversation about what you're seeing in the market.\n\nLet me know if you're open to a 15-minute call in the next couple of weeks.\n\nBest,\nRana Faraz"],
            ['name' => 'Follow-up — 1st Reminder (5 days)', 'type' => 'follow_up',        'subject' => 'Re: Application for {{position}} at {{company}}','body' => "Hi {{first_name}},\n\nJust following up on my note from last week about the {{position}} role at {{company}}. I know inboxes get busy — wanted to bump this in case it was useful.\n\nHappy to share more about my background or jump on a quick call whenever it works for you.\n\nThanks,\nRana"],
            ['name' => 'Follow-up — 2nd Reminder (10 days)','type' => 'follow_up',        'subject' => 'Following up on {{position}}',             'body' => "Hi {{first_name}},\n\nCircling back one more time on {{position}} at {{company}}. If timing isn't right, no problem at all — I'd appreciate even a quick \"not now\" so I know where things stand.\n\nIf it would help, I can send a 60-second video walkthrough of a relevant project I've shipped.\n\nThanks for your time,\nRana"],
            ['name' => 'Follow-up — Final Check-in',        'type' => 'follow_up',        'subject' => 'Last note re: {{position}}',               'body' => "Hi {{first_name}},\n\nI'll keep this short — this is my last note unless I hear back. I'm genuinely interested in {{company}} and the {{position}} role, but I don't want to clutter your inbox.\n\nIf the timing isn't right or the role has been filled, I completely understand. Wishing {{company}} the best either way.\n\nBest,\nRana"],
            ['name' => 'Thank You — After Interview',       'type' => 'thank_you',        'subject' => 'Thank you — {{position}} conversation',    'body' => "Hi {{first_name}},\n\nThanks for taking the time to talk today about the {{position}} role at {{company}}. I really enjoyed our conversation — particularly the part about [specific topic from the call].\n\nA few things you mentioned got me thinking, and I'll follow up separately with a short note on [topic].\n\nLooking forward to next steps.\n\nBest,\nRana"],
            ['name' => 'Thank You — After Intro Call',      'type' => 'thank_you',        'subject' => 'Great chatting today',                     'body' => "Hi {{first_name}},\n\nReally enjoyed the call today — thanks for making time. I'll follow up on the things we discussed:\n\n- [Action item 1]\n- [Action item 2]\n\nIf there's anything else I can share in the meantime, just let me know.\n\nBest,\nRana"],
            ['name' => 'Networking — Referral Request',     'type' => 'networking',       'subject' => 'Quick favor — intro at {{company}}?',      'body' => "Hi {{first_name}},\n\nHope you're doing well. I'm exploring an opportunity at {{company}} and noticed you're connected to a few people there. Would you be open to making a quick intro to {{position}} if you think we'd be a good match?\n\nNo pressure at all — happy to send a short blurb you can forward if it helps.\n\nThanks,\nRana"],
            ['name' => 'Networking — Reconnect',            'type' => 'networking',       'subject' => "Long time — let's catch up",               'body' => "Hi {{first_name}},\n\nIt's been a while. I was just thinking about the work you were doing at {{company}} and wanted to reach out.\n\nWould love to hear what you're up to these days. Free for a quick call sometime in the next couple of weeks?\n\nBest,\nRana"],
            ['name' => 'Scholarship Application Intro',     'type' => 'initial_outreach', 'subject' => 'Application for {{position}} scholarship', 'body' => "Dear {{first_name}},\n\nI'm writing to express interest in the {{position}} scholarship offered by {{company}}. I'm currently [your background/program] and the focus areas of the scholarship align closely with my work in [topic].\n\nI'd be glad to provide any additional materials needed beyond the standard application. Could you confirm what's required and the deadline?\n\nThank you,\nRana Faraz"],
            ['name' => 'Grant Inquiry',                     'type' => 'initial_outreach', 'subject' => 'Inquiry regarding {{position}} grant',     'body' => "Hello {{first_name}},\n\nI'm researching funding for [project description] and the {{position}} grant from {{company}} looks like a strong fit. Before submitting a full proposal, I wanted to confirm a couple of things:\n\n1. Is the program currently accepting applications?\n2. Is there a preferred format or contact for initial inquiries?\n\nHappy to share a one-page summary of the project on request.\n\nBest regards,\nRana Faraz"],
        ];
    }

    /**
     * Test SMTP + IMAP for every account on the user. Test methods now persist
     * smtp_status / imap_status on the EmailAccount row.
     *
     * @return array<string, EmailAccount> keyed by lowercased email
     */
    private function testConnections(User $user, EmailSendingService $emailService, ImapSyncService $imapService): array
    {
        $this->newLine();
        $this->line('— Connection tests ———————————————');

        $accounts = EmailAccount::where('user_id', $user->id)->orderBy('id')->get();
        $working  = [];

        foreach ($accounts as $account) {
            $smtp = $emailService->testSmtpConnection($account);
            $smtp['success']
                ? $this->info("  ✓ SMTP {$account->email} — {$smtp['message']}")
                : $this->error("  ✗ SMTP {$account->email} — " . mb_substr($smtp['message'], 0, 200));

            $imap = $imapService->testImapConnection($account);
            $imap['success']
                ? $this->info("  ✓ IMAP {$account->email} — {$imap['message']}")
                : $this->error("  ✗ IMAP {$account->email} — " . mb_substr($imap['message'], 0, 200));

            if ($smtp['success']) {
                $working[strtolower($account->email)] = $account;
            }
        }

        return $working;
    }

}
