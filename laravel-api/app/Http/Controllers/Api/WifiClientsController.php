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

    /**
     * GET /api/wifi-clients
     * Optional query params: page, from, to, export=1
     */
    public function index(Request $request)
    {
        $export = in_array($request->query('export'), ['1', 'true', 'yes'], true);
        $query  = $this->baseQuery($request);

        if ($export) {
            $rows = $query
                ->orderByDesc('wc.updated_at')
                ->get()
                ->map(fn ($row) => $this->mapWifiClientRow($row));

            return response()->json([
                'success' => true,
                'data'    => $rows,
                'total'   => $rows->count(),
            ]);
        }

        $page     = max(1, (int) ($request->query('page', 1)));
        $pageSize = 20;
        $offset   = ($page - 1) * $pageSize;
        $total    = (clone $query)->count();

        $rows = $query
            ->orderByDesc('wc.updated_at')
            ->limit($pageSize)
            ->offset($offset)
            ->get()
            ->map(fn ($row) => $this->mapWifiClientRow($row));

        return response()->json([
            'success'   => true,
            'data'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $pageSize,
        ]);
    }

    private function baseQuery(Request $request)
    {
        $query = DB::table('wifi_clients as wc')
            ->select([
                'wc.id', 'wc.phone', 'wc.mac_address', 'wc.ip_address',
                'wc.is_active', 'wc.session_minutes', 'wc.session_started_at',
                'wc.created_at', 'wc.updated_at',
                DB::raw('(SELECT COUNT(*) FROM wifi_clients WHERE phone = wc.phone) as registration_count'),
            ]);

        $from = $request->query('from');
        if (is_string($from) && trim($from) !== '') {
            $fromTs = strtotime($from);
            if ($fromTs !== false) {
                $query->where('wc.created_at', '>=', gmdate('Y-m-d H:i:s', $fromTs));
            }
        }

        $to = $request->query('to');
        if (is_string($to) && trim($to) !== '') {
            $toTs = strtotime($to);
            if ($toTs !== false) {
                $query->where('wc.created_at', '<=', gmdate('Y-m-d H:i:s', $toTs));
            }
        }

        return $query;
    }

    private function mapWifiClientRow(object $row): object
    {
        // SQLite stores booleans as 0/1 integers — cast to real bool for JSON
        $row->is_active = (bool) $row->is_active;
        $row->registration_count = (int) $row->registration_count;

        return $row;
    }
}
