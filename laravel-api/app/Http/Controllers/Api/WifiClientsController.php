<?php

namespace App\Http\Controllers\Api;

use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WifiClientsController extends BaseApiController
{
    /**
     * PATCH /api/wifi-clients/{id}
     * Toggle active/inactive and/or update session_minutes.
     */
    public function update(int $id, Request $request)
    {
        $wifiClient = DB::table('wifi_clients')->where('id', $id)->first();

        if (!$wifiClient) {
            return response()->json(['success' => false, 'error' => 'Client not found.'], 404);
        }

        $body    = $this->decodeJsonBody($request);
        $updates = [];

        if (array_key_exists('is_active', $body)) {
            $isActive             = (bool) $body['is_active'];
            $updates['is_active'] = $isActive ? 1 : 0;

            // Kick MikroTik session when deactivating
            $kickResult = null;
            if (!$isActive && !empty($wifiClient->mac_address)) {
                try {
                    $kickResult = (new MikroTikService())->kickHotspotSession($wifiClient->mac_address);
                } catch (\Throwable $e) {
                    $kickResult = ['kicked' => 0, 'error' => $e->getMessage(), 'host' => '?'];
                }
            }
        }

        if (array_key_exists('session_minutes', $body)) {
            $minutes = max(1, (int) $body['session_minutes']);
            $updates['session_minutes'] = $minutes;
        }

        if (empty($updates)) {
            return response()->json(['success' => false, 'error' => 'Nothing to update.'], 422);
        }

        DB::table('wifi_clients')->where('id', $id)->update($updates);

        return response()->json([
            'success' => true,
            'kick'    => $kickResult,  // null if not a deactivation, array with debug info if it was
        ]);
    }

    public function destroy(int $id)
    {
        $client = DB::table('wifi_clients')->where('id', $id)->first();

        if (!$client) {
            return response()->json(['success' => false, 'error' => 'Client not found.'], 404);
        }

        // Kick active MikroTik session BEFORE deleting from DB so the MAC is still available.
        if (!empty($client->mac_address)) {
            try {
                (new MikroTikService())->kickHotspotSession($client->mac_address);
            } catch (\Throwable) {
                // Router unreachable — still delete the DB record.
            }
        }

        DB::table('wifi_clients')->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function index(Request $request)
    {
        $page     = max(1, (int) ($request->query('page', 1)));
        $pageSize = 20;
        $offset   = ($page - 1) * $pageSize;

        $total = DB::table('wifi_clients')->count();

        $rows = DB::table('wifi_clients')
            ->select(['id', 'phone', 'mac_address', 'ip_address', 'is_active', 'session_minutes', 'session_started_at', 'created_at', 'updated_at'])
            ->orderByDesc('updated_at')
            ->limit($pageSize)
            ->offset($offset)
            ->get()
            ->map(function ($row) {
                // SQLite stores booleans as 0/1 integers — cast to real bool for JSON
                $row->is_active = (bool) $row->is_active;
                return $row;
            });

        return response()->json([
            'success'   => true,
            'data'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $pageSize,
        ]);
    }
}
