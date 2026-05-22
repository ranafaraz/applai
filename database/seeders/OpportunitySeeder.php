<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use Illuminate\Database\Seeder;

class OpportunitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 10 sample opportunities for user_id = 1.
     */
    public function run(): void
    {
        $opportunities = [
            // ── Job Opportunities ─────────────────────────────────────────
            [
                'user_id'          => 1,
                'title'            => 'Software Engineer – ML Infrastructure',
                'type'             => 'job',
                'organization'     => 'Google',
                'description'      => 'Senior SWE role on the ML infrastructure team in Mountain View. Requires 5+ years Python/C++ and distributed-systems experience. Referral from John Doe (Google I/O).',
                'url'              => 'https://careers.google.com/jobs/results/12345',
                'status'           => 'waiting_reply',
                'priority'         => 'high',
                'deadline'         => now()->addDays(14)->toDateString(),
                'notes'            => 'Applied via referral. Follow-up due soon.',
                'last_activity_at' => now()->subDays(7),
            ],
            [
                'user_id'          => 1,
                'title'            => 'Product Manager – Azure AI',
                'type'             => 'job',
                'organization'     => 'Microsoft',
                'description'      => 'PM role focused on Azure Cognitive Services product line. Sarah Chen (HR) is the point of contact for the Redmond team.',
                'url'              => 'https://careers.microsoft.com/jobs/67890',
                'status'           => 'replied',
                'priority'         => 'high',
                'deadline'         => now()->addDays(21)->toDateString(),
                'notes'            => 'Phone screen scheduled for next week.',
                'last_activity_at' => now()->subDays(2),
            ],
            [
                'user_id'          => 1,
                'title'            => 'Research Scientist – Alignment',
                'type'             => 'job',
                'organization'     => 'Anthropic',
                'description'      => 'Research Scientist position on the interpretability team. Contact: James Thompson (Research Director).',
                'url'              => 'https://www.anthropic.com/careers',
                'status'           => 'active',
                'priority'         => 'medium',
                'deadline'         => null,
                'notes'            => 'Drafting cover letter. Rolling admissions.',
                'last_activity_at' => now()->subDays(5),
            ],

            // ── Scholarship / Fellowship Opportunities ─────────────────────
            [
                'user_id'          => 1,
                'title'            => 'NSF Graduate Research Fellowship (GRFP)',
                'type'             => 'scholarship',
                'organization'     => 'National Science Foundation',
                'description'      => 'Three-year fellowship providing a $37,000 annual stipend. Program Director: Marcus Johnson. Focused on computer science and AI proposals.',
                'url'              => 'https://www.nsfgrfp.org/',
                'status'           => 'waiting_reply',
                'priority'         => 'high',
                'deadline'         => now()->addDays(60)->toDateString(),
                'notes'            => 'Application submitted. Awaiting review results.',
                'last_activity_at' => now()->subDays(30),
            ],
            [
                'user_id'          => 1,
                'title'            => 'Gates Cambridge Scholarship',
                'type'             => 'scholarship',
                'organization'     => 'Gates Foundation',
                'description'      => 'Full-cost scholarship for postgraduate study at Cambridge. Contact: Amanda Foster (Program Officer) for guidance on the application.',
                'url'              => 'https://www.gatescambridge.org/',
                'status'           => 'draft',
                'priority'         => 'high',
                'deadline'         => now()->addDays(45)->toDateString(),
                'notes'            => 'Personal statement in progress. Need two more reference letters.',
                'last_activity_at' => now()->subDays(3),
            ],

            // ── Research Collaboration Opportunities ───────────────────────
            [
                'user_id'          => 1,
                'title'            => 'NLP Research Collaboration – MIT CSAIL',
                'type'             => 'research',
                'organization'     => 'MIT',
                'description'      => 'Potential collaboration with Prof. David Kim on large-language-model interpretability. Initial outreach sent; awaiting response.',
                'url'              => 'https://people.csail.mit.edu/dkim',
                'status'           => 'waiting_reply',
                'priority'         => 'medium',
                'deadline'         => null,
                'notes'            => 'Sent collaboration inquiry on ' . now()->subDays(20)->toDateString() . '.',
                'last_activity_at' => now()->subDays(20),
            ],
            [
                'user_id'          => 1,
                'title'            => 'AI Safety Research Partnership – Stanford HAI',
                'type'             => 'research',
                'organization'     => 'Stanford University',
                'description'      => 'Joint research project on AI safety evaluations with Emily Rodriguez (Research Director, Stanford HAI). Discussed at NeurIPS 2024.',
                'url'              => 'https://hai.stanford.edu/',
                'status'           => 'replied',
                'priority'         => 'high',
                'deadline'         => now()->addDays(90)->toDateString(),
                'notes'            => 'Emily replied positively. Setting up kick-off call.',
                'last_activity_at' => now()->subDays(8),
            ],

            // ── Grant Opportunity ──────────────────────────────────────────
            [
                'user_id'          => 1,
                'title'            => 'NIH R01 Grant – Biomedical AI Diagnostics',
                'type'             => 'grant',
                'organization'     => 'NIH',
                'description'      => 'R01 application for an AI-driven diagnostic tool for rare diseases. Program Director: Sofia Martinez. Budget: $500k over 3 years.',
                'url'              => 'https://grants.nih.gov/grants/guide/pa-files/PA-20-185.html',
                'status'           => 'active',
                'priority'         => 'medium',
                'deadline'         => now()->addDays(120)->toDateString(),
                'notes'            => 'Preliminary data collection underway. Specific Aims drafted.',
                'last_activity_at' => now()->subDays(10),
            ],

            // ── Additional varied entries ──────────────────────────────────
            [
                'user_id'          => 1,
                'title'            => 'Research Engineer – AlphaFold Next',
                'type'             => 'job',
                'organization'     => 'DeepMind',
                'description'      => 'Research Engineering role on the protein-structure prediction team. Contact: Priya Sharma (Research Engineer) for an internal referral.',
                'url'              => 'https://deepmind.google/careers/',
                'status'           => 'active',
                'priority'         => 'medium',
                'deadline'         => null,
                'notes'            => 'Reaching out to Priya for a warm intro before applying.',
                'last_activity_at' => now()->subDays(1),
            ],
            [
                'user_id'          => 1,
                'title'            => 'AWS Applied Scientist (L6)',
                'type'             => 'job',
                'organization'     => 'Amazon',
                'description'      => 'Applied Scientist for Alexa AI team. Thomas Nguyen (HR) covers this opening. Strong preference for candidates with RL and NLP background.',
                'url'              => 'https://www.amazon.jobs/en/jobs/applied-scientist-l6',
                'status'           => 'draft',
                'priority'         => 'low',
                'deadline'         => now()->addDays(30)->toDateString(),
                'notes'            => 'Have not applied yet. Tailoring resume.',
                'last_activity_at' => now()->subDays(14),
            ],
        ];

        foreach ($opportunities as $opportunity) {
            Opportunity::create($opportunity);
        }
    }
}
