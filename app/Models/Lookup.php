<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lookup extends Model
{
    protected $fillable = ['tenant_id', 'type', 'value', 'slug', 'meta', 'is_system', 'usage_count'];

    protected function casts(): array
    {
        return [
            'is_system'   => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    /**
     * Get all values of a given type (system + tenant) ordered by value.
     */
    public static function listFor(string $type, ?int $tenantId = null): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('type', $type)
            ->where(function ($q) use ($tenantId) {
                $q->where('is_system', true)
                  ->orWhere('tenant_id', $tenantId);
            })
            ->orderBy('value')
            ->get();
    }

    /**
     * Record a free-form value into the lookup table so future autocompletes
     * include it. No-op if value is blank or already exists for the tenant.
     */
    public static function record(string $type, ?string $value, ?int $tenantId): void
    {
        $value = trim((string) $value);
        if ($value === '') return;

        $existing = static::where('type', $type)
            ->where('value', $value)
            ->where(function ($q) use ($tenantId) {
                $q->where('is_system', true)->orWhere('tenant_id', $tenantId);
            })
            ->first();

        if ($existing) {
            $existing->increment('usage_count');
            return;
        }

        static::create([
            'tenant_id'   => $tenantId,
            'type'        => $type,
            'value'       => $value,
            'slug'        => \Illuminate\Support\Str::slug($value),
            'is_system'   => false,
            'usage_count' => 1,
        ]);
    }
}
