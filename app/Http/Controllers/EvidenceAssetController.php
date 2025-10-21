<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvidenceAssetController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $request->validate([]); // keep middleware-driven validation

        // middleware \'signed\' validates the signature; decrypt path
        $path = Crypt::decryptString($request->query('p'));

        $real = realpath($path);
        if (!$real || !is_file($real) || !$this->isAllowed($real)) {
            abort(404);
        }

        return response()->file($real);
    }

    private function isAllowed(string $real): bool
    {
        $allowed = [
            public_path(''),
            storage_path('app/public'),
            storage_path('app/private'),
        ];

        foreach ($allowed as $base) {
            $baseReal = realpath($base);
            if ($baseReal && str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }
}
