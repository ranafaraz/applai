<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedType('country', $this->countries());
        $this->seedType('industry', $this->industries());
        // 'source' and 'city' are user-extendable; not pre-seeded.
    }

    /**
     * @param  array<int, array{value:string, meta?:string}|string>  $items
     */
    private function seedType(string $type, array $items): void
    {
        foreach ($items as $item) {
            $value = is_string($item) ? $item : ($item['value'] ?? null);
            $meta  = is_array($item) ? ($item['meta'] ?? null) : null;
            if (! $value) continue;

            Lookup::firstOrCreate(
                ['tenant_id' => null, 'type' => $type, 'value' => $value],
                [
                    'slug'        => Str::slug($value),
                    'meta'        => $meta,
                    'is_system'   => true,
                    'usage_count' => 0,
                ]
            );
        }
    }

    /**
     * Curated, alphabetised list of countries with ISO alpha-2 codes in meta.
     * Trimmed to widely-used countries; users can add more via record().
     * @return array<int, array{value:string, meta:string}>
     */
    private function countries(): array
    {
        return [
            ['value' => 'Afghanistan', 'meta' => 'AF'], ['value' => 'Albania', 'meta' => 'AL'],
            ['value' => 'Algeria', 'meta' => 'DZ'], ['value' => 'Argentina', 'meta' => 'AR'],
            ['value' => 'Armenia', 'meta' => 'AM'], ['value' => 'Australia', 'meta' => 'AU'],
            ['value' => 'Austria', 'meta' => 'AT'], ['value' => 'Azerbaijan', 'meta' => 'AZ'],
            ['value' => 'Bahrain', 'meta' => 'BH'], ['value' => 'Bangladesh', 'meta' => 'BD'],
            ['value' => 'Belarus', 'meta' => 'BY'], ['value' => 'Belgium', 'meta' => 'BE'],
            ['value' => 'Bhutan', 'meta' => 'BT'], ['value' => 'Bolivia', 'meta' => 'BO'],
            ['value' => 'Brazil', 'meta' => 'BR'], ['value' => 'Bulgaria', 'meta' => 'BG'],
            ['value' => 'Cambodia', 'meta' => 'KH'], ['value' => 'Canada', 'meta' => 'CA'],
            ['value' => 'Chile', 'meta' => 'CL'], ['value' => 'China', 'meta' => 'CN'],
            ['value' => 'Colombia', 'meta' => 'CO'], ['value' => 'Costa Rica', 'meta' => 'CR'],
            ['value' => 'Croatia', 'meta' => 'HR'], ['value' => 'Cyprus', 'meta' => 'CY'],
            ['value' => 'Czech Republic', 'meta' => 'CZ'], ['value' => 'Denmark', 'meta' => 'DK'],
            ['value' => 'Dominican Republic', 'meta' => 'DO'], ['value' => 'Ecuador', 'meta' => 'EC'],
            ['value' => 'Egypt', 'meta' => 'EG'], ['value' => 'Estonia', 'meta' => 'EE'],
            ['value' => 'Ethiopia', 'meta' => 'ET'], ['value' => 'Finland', 'meta' => 'FI'],
            ['value' => 'France', 'meta' => 'FR'], ['value' => 'Georgia', 'meta' => 'GE'],
            ['value' => 'Germany', 'meta' => 'DE'], ['value' => 'Ghana', 'meta' => 'GH'],
            ['value' => 'Greece', 'meta' => 'GR'], ['value' => 'Guatemala', 'meta' => 'GT'],
            ['value' => 'Hong Kong', 'meta' => 'HK'], ['value' => 'Hungary', 'meta' => 'HU'],
            ['value' => 'Iceland', 'meta' => 'IS'], ['value' => 'India', 'meta' => 'IN'],
            ['value' => 'Indonesia', 'meta' => 'ID'], ['value' => 'Iran', 'meta' => 'IR'],
            ['value' => 'Iraq', 'meta' => 'IQ'], ['value' => 'Ireland', 'meta' => 'IE'],
            ['value' => 'Israel', 'meta' => 'IL'], ['value' => 'Italy', 'meta' => 'IT'],
            ['value' => 'Jamaica', 'meta' => 'JM'], ['value' => 'Japan', 'meta' => 'JP'],
            ['value' => 'Jordan', 'meta' => 'JO'], ['value' => 'Kazakhstan', 'meta' => 'KZ'],
            ['value' => 'Kenya', 'meta' => 'KE'], ['value' => 'Kuwait', 'meta' => 'KW'],
            ['value' => 'Latvia', 'meta' => 'LV'], ['value' => 'Lebanon', 'meta' => 'LB'],
            ['value' => 'Lithuania', 'meta' => 'LT'], ['value' => 'Luxembourg', 'meta' => 'LU'],
            ['value' => 'Malaysia', 'meta' => 'MY'], ['value' => 'Maldives', 'meta' => 'MV'],
            ['value' => 'Malta', 'meta' => 'MT'], ['value' => 'Mexico', 'meta' => 'MX'],
            ['value' => 'Moldova', 'meta' => 'MD'], ['value' => 'Mongolia', 'meta' => 'MN'],
            ['value' => 'Morocco', 'meta' => 'MA'], ['value' => 'Myanmar', 'meta' => 'MM'],
            ['value' => 'Nepal', 'meta' => 'NP'], ['value' => 'Netherlands', 'meta' => 'NL'],
            ['value' => 'New Zealand', 'meta' => 'NZ'], ['value' => 'Nigeria', 'meta' => 'NG'],
            ['value' => 'North Macedonia', 'meta' => 'MK'], ['value' => 'Norway', 'meta' => 'NO'],
            ['value' => 'Oman', 'meta' => 'OM'], ['value' => 'Pakistan', 'meta' => 'PK'],
            ['value' => 'Palestine', 'meta' => 'PS'], ['value' => 'Panama', 'meta' => 'PA'],
            ['value' => 'Paraguay', 'meta' => 'PY'], ['value' => 'Peru', 'meta' => 'PE'],
            ['value' => 'Philippines', 'meta' => 'PH'], ['value' => 'Poland', 'meta' => 'PL'],
            ['value' => 'Portugal', 'meta' => 'PT'], ['value' => 'Qatar', 'meta' => 'QA'],
            ['value' => 'Romania', 'meta' => 'RO'], ['value' => 'Russia', 'meta' => 'RU'],
            ['value' => 'Saudi Arabia', 'meta' => 'SA'], ['value' => 'Serbia', 'meta' => 'RS'],
            ['value' => 'Singapore', 'meta' => 'SG'], ['value' => 'Slovakia', 'meta' => 'SK'],
            ['value' => 'Slovenia', 'meta' => 'SI'], ['value' => 'South Africa', 'meta' => 'ZA'],
            ['value' => 'South Korea', 'meta' => 'KR'], ['value' => 'Spain', 'meta' => 'ES'],
            ['value' => 'Sri Lanka', 'meta' => 'LK'], ['value' => 'Sweden', 'meta' => 'SE'],
            ['value' => 'Switzerland', 'meta' => 'CH'], ['value' => 'Syria', 'meta' => 'SY'],
            ['value' => 'Taiwan', 'meta' => 'TW'], ['value' => 'Tajikistan', 'meta' => 'TJ'],
            ['value' => 'Tanzania', 'meta' => 'TZ'], ['value' => 'Thailand', 'meta' => 'TH'],
            ['value' => 'Tunisia', 'meta' => 'TN'], ['value' => 'Turkey', 'meta' => 'TR'],
            ['value' => 'Turkmenistan', 'meta' => 'TM'], ['value' => 'Uganda', 'meta' => 'UG'],
            ['value' => 'Ukraine', 'meta' => 'UA'], ['value' => 'United Arab Emirates', 'meta' => 'AE'],
            ['value' => 'United Kingdom', 'meta' => 'GB'], ['value' => 'United States', 'meta' => 'US'],
            ['value' => 'Uruguay', 'meta' => 'UY'], ['value' => 'Uzbekistan', 'meta' => 'UZ'],
            ['value' => 'Venezuela', 'meta' => 'VE'], ['value' => 'Vietnam', 'meta' => 'VN'],
            ['value' => 'Yemen', 'meta' => 'YE'], ['value' => 'Zambia', 'meta' => 'ZM'],
            ['value' => 'Zimbabwe', 'meta' => 'ZW'],
        ];
    }

    /** @return array<int, string> */
    private function industries(): array
    {
        return [
            'Aerospace & Defense', 'Agriculture & Farming', 'Automotive',
            'Banking & Financial Services', 'Biotechnology', 'Construction & Real Estate',
            'Consulting', 'Consumer Goods', 'E-commerce', 'Education & EdTech',
            'Energy & Utilities', 'Entertainment & Media', 'Fashion & Apparel',
            'Food & Beverage', 'Government & Public Sector', 'Healthcare & Medical',
            'Hospitality & Tourism', 'Insurance', 'Legal Services',
            'Logistics & Supply Chain', 'Manufacturing', 'Marketing & Advertising',
            'Mining & Metals', 'Non-Profit & NGO', 'Pharmaceuticals',
            'Professional Services', 'Retail', 'SaaS / Software',
            'Sports & Fitness', 'Technology / IT Services',
            'Telecommunications', 'Transportation',
        ];
    }
}
