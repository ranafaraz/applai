<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Demo user (user_id = 1)
        $demo = User::create([
            'name'     => 'Demo User',
            'email'    => 'demo@example.com',
            'password' => Hash::make('password'),
        ]);

        UserSetting::create([
            'user_id'                  => $demo->id,
            'timezone'                 => 'UTC',
            'date_format'              => 'Y-m-d',
            'default_follow_up_days'   => 5,
            'default_email_account_id' => null,
            'notify_on_reply'          => true,
            'notify_on_bounce'         => true,
        ]);

        // Admin user
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }
}
