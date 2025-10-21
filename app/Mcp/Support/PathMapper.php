<?php

namespace App\Mcp\Support;

class PathMapper
{
    public static function toProxyUrl(string $absPath): string
    {
        $b64 = rtrim(strtr(base64_encode($absPath), '+/', '-_'), '=');
        return url('/api/mcp/file') . '?p=' . $b64;
    }

    public static function fileRef(?string $absPath): ?array
    {
        if (!$absPath || !is_file($absPath)) return null;
        return [
            'path' => $absPath,
            'url'  => self::toProxyUrl($absPath),
            'size' => filesize($absPath),
        ];
    }
}
