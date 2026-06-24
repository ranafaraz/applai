<?php

namespace App\Support;

/**
 * Single source of truth for the mobile app's opportunity stages (§4.2/§8).
 *
 * The DB `opportunities.status` column historically used a looser enum
 * (active, waiting_reply, rejected, withdrawn). New writes store the canonical
 * mobile stage directly; legacy values are normalized on read so the app only
 * ever sees one of the eight canonical stages.
 */
class OpportunityStage
{
    /** Canonical, ordered stages exposed to the mobile app. */
    public const STAGES = [
        'draft', 'applied', 'replied', 'interview',
        'offer', 'won', 'closed', 'archived',
    ];

    /** Legacy DB status → canonical mobile stage. */
    private const LEGACY = [
        'active'        => 'applied',
        'waiting_reply' => 'applied',
        'rejected'      => 'closed',
        'withdrawn'     => 'closed',
    ];

    public static function isValid(string $stage): bool
    {
        return in_array($stage, self::STAGES, true);
    }

    /** Map any stored status value to a canonical mobile stage. */
    public static function normalize(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return 'draft';
        }
        if (in_array($stored, self::STAGES, true)) {
            return $stored;
        }

        return self::LEGACY[$stored] ?? 'draft';
    }

    /**
     * All stored `status` values that should match a canonical stage filter,
     * so filtering by `applied` also surfaces legacy `active`/`waiting_reply` rows.
     */
    public static function storedValuesFor(string $stage): array
    {
        $aliases = [
            'applied' => ['active', 'waiting_reply'],
            'closed'  => ['rejected', 'withdrawn'],
        ];

        return array_values(array_unique(array_merge([$stage], $aliases[$stage] ?? [])));
    }

    /** Ordered stage metadata for GET /meta/stages (labels, emoji, hex colors). */
    public static function meta(): array
    {
        $meta = [
            'draft'     => ['Draft', '📝', '#94A3B8'],
            'applied'   => ['Applied', '📤', '#3B82F6'],
            'replied'   => ['Replied', '💬', '#8B5CF6'],
            'interview' => ['Interview', '🎙️', '#F59E0B'],
            'offer'     => ['Offer', '🎯', '#10B981'],
            'won'       => ['Won', '🏆', '#22C55E'],
            'closed'    => ['Closed', '📁', '#6B7280'],
            'archived'  => ['Archived', '🗄️', '#4B5563'],
        ];

        $out = [];
        foreach (self::STAGES as $i => $value) {
            [$label, $emoji, $color] = $meta[$value];
            $out[] = [
                'value' => $value,
                'label' => $label,
                'emoji' => $emoji,
                'color' => $color,
                'order' => $i,
            ];
        }

        return $out;
    }
}
