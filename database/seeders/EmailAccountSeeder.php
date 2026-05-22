<?php

namespace Database\Seeders;

use App\Models\EmailAccount;
use Illuminate\Database\Seeder;

class EmailAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a placeholder outreach account for the demo user (user_id = 1).
     * Passwords are stored via Laravel's 'encrypted' cast.
     */
    public function run(): void
    {
        EmailAccount::create([
            'user_id'           => 1,
            'name'              => 'Demo Outreach Account',
            'email'             => 'demo@example.com',
            'from_name'         => 'Demo User',
            'smtp_host'         => 'smtp.example.com',
            'smtp_port'         => 587,
            'smtp_encryption'   => 'tls',
            'smtp_username'     => 'demo@example.com',
            'smtp_password'     => 'smtp-password-placeholder',
            'imap_host'         => 'imap.example.com',
            'imap_port'         => 993,
            'imap_encryption'   => 'ssl',
            'imap_username'     => 'demo@example.com',
            'imap_password'     => 'imap-password-placeholder',
            'daily_limit'       => 50,
            'hourly_limit'      => 10,
            'min_delay_seconds' => 30,
            'emails_sent_today' => 0,
            'last_reset_at'     => now(),
            'sync_status'       => 'idle',
            'is_active'         => true,
            'is_default'        => true,
            'notes'             => 'Default outreach account for the demo user.',
        ]);
    }
}
