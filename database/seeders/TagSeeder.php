<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates sample tags for user_id = 1.
     */
    public function run(): void
    {
        $tags = [
            [
                'user_id' => 1,
                'name'    => 'urgent',
                'color'   => '#ef4444',
                'slug'    => 'urgent',
            ],
            [
                'user_id' => 1,
                'name'    => 'top-priority',
                'color'   => '#f97316',
                'slug'    => 'top-priority',
            ],
            [
                'user_id' => 1,
                'name'    => 'FAANG',
                'color'   => '#3b82f6',
                'slug'    => 'faang',
            ],
            [
                'user_id' => 1,
                'name'    => 'academic',
                'color'   => '#8b5cf6',
                'slug'    => 'academic',
            ],
            [
                'user_id' => 1,
                'name'    => 'fellowship',
                'color'   => '#10b981',
                'slug'    => 'fellowship',
            ],
            [
                'user_id' => 1,
                'name'    => 'PhD',
                'color'   => '#f59e0b',
                'slug'    => 'phd',
            ],
            [
                'user_id' => 1,
                'name'    => 'follow-up-sent',
                'color'   => '#6366f1',
                'slug'    => 'follow-up-sent',
            ],
        ];

        foreach ($tags as $tag) {
            Tag::create($tag);
        }
    }
}
