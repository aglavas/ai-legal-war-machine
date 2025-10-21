<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class JsonUnescaped implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') return [];
        // Value might already be array when hydrated by DB drivers; ensure array
        if (is_array($value)) return $value;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) return null;
        // Ensure arrays/objects are encoded preserving Unicode and slashes
        if (is_string($value)) {
            // Attempt to decode strings first (e.g., if user passed JSON)
            $try = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $try;
            } else {
                // keep as string
                return $value;
            }
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

