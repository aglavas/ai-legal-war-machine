<?php

namespace App\GraphQL\AutoDiscovery\Introspection;

class TypeUtils
{
    public static function unwrapType(array $type): array
    {
        $t = $type;
        while (isset($t['kind']) && in_array($t['kind'], ['NON_NULL', 'LIST'], true)) {
            $t = $t['ofType'] ?? $t;
        }
        return $t ?? $type;
    }

    public static function isScalarKind(?string $kind, ?string $name): bool
    {
        if ($kind === 'SCALAR') {
            return true;
        }
        // Some servers implement custom scalars with kind=SCALAR and name (e.g., BigInteger, DateTime)
        // This method is here for future overrides if needed.
        return false;
    }

    public static function isScalar(array $type): bool
    {
        $t = self::unwrapType($type);
        return $t['kind'] === 'SCALAR';
    }

    public static function typeToString(array $type): string
    {
        // Build GraphQL type string (e.g., [BigInteger!]!, String!, DateTime)
        if ($type['kind'] === 'NON_NULL') {
            return self::typeToString($type['ofType']) . '!';
        }
        if ($type['kind'] === 'LIST') {
            $inner = self::typeToString($type['ofType']);
            return '[' . $inner . ']';
        }
        return $type['name'] ?? 'Unknown';
    }
}
