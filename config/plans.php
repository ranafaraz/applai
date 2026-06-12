<?php

/*
|--------------------------------------------------------------------------
| Subscription plans — single source of truth for tier limits & features
|--------------------------------------------------------------------------
|
| Keys match the tenants.plan enum (free|pro|enterprise). "enterprise" is
| marketed as the "Team" plan; the column value is kept for backwards
| compatibility with existing rows.
|
| A limit of null means unlimited. Limits are tenant-wide, not per-user.
| Paddle price ids are configured per environment in .env (sandbox vs live).
|
*/

return [

    'default' => 'free',

    'plans' => [

        'free' => [
            'label'            => 'Free',
            'monthly_price'    => 0,
            'paddle_price_ids' => [],
            'limits'           => [
                'users'           => 1,
                'contacts'        => 100,
                'opportunities'   => 25,
                'email_accounts'  => 1,
                'emails_per_day'  => 25,
                'social_accounts' => 1,
                'api_clients'     => 0,
            ],
            'features' => [
                'follow_up_automation' => false,
                'email_tracking'       => false,
                'api_access'           => false,
                'approval_workflows'   => false,
            ],
        ],

        'pro' => [
            'label'            => 'Pro',
            'monthly_price'    => 19,
            'paddle_price_ids' => [
                'monthly' => env('PADDLE_PRICE_PRO_MONTHLY'),
                'annual'  => env('PADDLE_PRICE_PRO_ANNUAL'),
            ],
            'limits' => [
                'users'           => 1,
                'contacts'        => null,
                'opportunities'   => null,
                'email_accounts'  => 3,
                'emails_per_day'  => 200,
                'social_accounts' => 5,
                'api_clients'     => 5,
            ],
            'features' => [
                'follow_up_automation' => true,
                'email_tracking'       => true,
                'api_access'           => true,
                'approval_workflows'   => false,
            ],
        ],

        // Marketed as "Team"; tenants.plan stores 'enterprise' for
        // backwards compatibility with existing rows.
        'enterprise' => [
            'label'            => 'Team',
            'monthly_price'    => 29, // per seat
            'paddle_price_ids' => [
                'monthly' => env('PADDLE_PRICE_TEAM_MONTHLY'),
                'annual'  => env('PADDLE_PRICE_TEAM_ANNUAL'),
            ],
            'limits' => [
                'users'           => 25,
                'contacts'        => null,
                'opportunities'   => null,
                'email_accounts'  => 10,
                'emails_per_day'  => 1000,
                'social_accounts' => 15,
                'api_clients'     => 25,
            ],
            'features' => [
                'follow_up_automation' => true,
                'email_tracking'       => true,
                'api_access'           => true,
                'approval_workflows'   => true,
            ],
        ],
    ],
];
