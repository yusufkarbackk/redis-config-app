<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class DecryptController extends Controller
{
    public function decrypt(Request $request)
    {
        // 1. Tambahkan token keamanan sederhana
        // Pastikan token ini sama dengan yang ada di config.yml Go
        $secretToken = env('INTERNAL_DECRYPT_TOKEN');

        if ($request->bearerToken() !== $secretToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $encryptedValue = $request->input('secret');
        if (empty($encryptedValue)) {
            return response()->json(['error' => 'No secret provided'], 400);
        }

        try {
            // 2. Lakukan dekripsi
            $decryptedValue = decrypt($encryptedValue);

            // 3. Kembalikan plain text
            return response()->json(['plain' => $decryptedValue]);
        } catch (DecryptException $e) {
            return response()->json(['error' => 'Failed to decrypt'], 500);
        }
    }
}
