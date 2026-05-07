<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StoreWifiPasswordController extends BaseApiController
{
    public function storePassword(Request $request)
    {
        $wifiPassword = trim((string) ($request->wifiPassword ?? ''));
        if ($wifiPassword === '') {
            return response()->json(['sucess' => false, 'error' => 'wifiPassword is required'], 400);
        }

        try {
            DB::table('wifi_passwords')->updateOrInsert(
                ['id' => 1],
                ['password_hash' => $wifiPassword, 'updated_at' => now()]
            );
        } catch (Throwable) {
            return response()->json(['sucess' => false, 'error' => 'Could not save password. Run migrations first.'], 500);
        }

        return response()->json(['sucess' => true], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
