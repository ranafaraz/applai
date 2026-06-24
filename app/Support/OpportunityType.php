<?php

namespace App\Support;

/**
 * Opportunity types for the mobile app (§4.2). The app's primary vocabulary is
 * job, phd, scholarship, grant, freelance (matching the onboarding tracking
 * types). Legacy DB values (research, networking) remain accepted so existing
 * records and the web app keep working.
 */
class OpportunityType
{
    /** Types surfaced in GET /meta/types and the Add/Edit form. */
    public const TYPES = ['job', 'phd', 'scholarship', 'grant', 'freelance'];

    /** Additional legacy values still accepted on write. */
    private const LEGACY = ['research', 'networking'];

    public static function allowed(): array
    {
        return array_merge(self::TYPES, self::LEGACY);
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::allowed(), true);
    }

    /** Ordered type metadata for GET /meta/types. */
    public static function meta(): array
    {
        $labels = [
            'job'         => ['Job', '💼'],
            'phd'         => ['PhD', '🎓'],
            'scholarship' => ['Scholarship', '📚'],
            'grant'       => ['Grant', '💰'],
            'freelance'   => ['Freelance', '🧑‍💻'],
        ];

        $out = [];
        foreach (self::TYPES as $i => $value) {
            [$label, $emoji] = $labels[$value];
            $out[] = ['value' => $value, 'label' => $label, 'emoji' => $emoji, 'order' => $i];
        }

        return $out;
    }
}
