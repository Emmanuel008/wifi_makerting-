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
        $auth = $this->resolveAuth($request);
        if (!$auth) return $this->unauthorizedResponse();

        $query = DB::table('wifi_clients')->where('id', $id);
        if ($auth->role === 'client') {
            $query->where('tenant_id', $auth->tenantId);
        }
        $wifiClient = $query->first();

        if (!$wifiClient) {
            return response()->json(['success' => false, 'error' => 'Client not found.'], 404);
        }

        $body    = $this->decodeJsonBody($request);
        $updates = ['updated_at' => now()];
        $kickResult = null;

        if (array_key_exists('is_active', $body)) {
            $isActive             = (bool) $body['is_active'];
            $updates['is_active'] = $isActive ? 1 : 0;

            if (!$isActive && !empty($wifiClient->mac_address)) {
                try {
                    $mikrotik   = new MikroTikService();
                    $kickResult = $mikrotik->kickHotspotSession($wifiClient->mac_address);
                    // Also remove the per-user hotspot account so they must re-auth
                    if (!empty($wifiClient->phone)) {
                        $mikrotik->removeHotspotUser($wifiClient->phone);
                    }
                } catch (\Throwable $e) {
                    $kickResult = ['kicked' => 0, 'error' => $e->getMessage(), 'host' => '?'];
                }
            }
        }

        if (array_key_exists('session_minutes', $body)) {
            $minutes = max(1, (int) $body['session_minutes']);
            $updates['session_minutes'] = $minutes;
        }

        if (count($updates) === 1) { // only updated_at
            return response()->json(['success' => false, 'error' => 'Nothing to update.'], 422);
        }

        DB::table('wifi_clients')->where('id', $id)->update($updates);

        return response()->json([
            'success' => true,
            'kick'    => $kickResult,
        ]);
    }

    public function destroy(int $id, Request $request)
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) return $this->unauthorizedResponse();

        $query = DB::table('wifi_clients')->where('id', $id);
        if ($auth->role === 'client') {
            $query->where('tenant_id', $auth->tenantId);
        }
        $client = $query->first();

        if (!$client) {
            return response()->json(['success' => false, 'error' => 'Client not found.'], 404);
        }

        if (!empty($client->mac_address)) {
            try {
                $mikrotik = new MikroTikService();
                $mikrotik->kickHotspotSession($client->mac_address);
                if (!empty($client->phone)) {
                    $mikrotik->removeHotspotUser($client->phone);
                }
            } catch (\Throwable) {
                // Router unreachable — still delete the DB record.
            }
        }

        DB::table('wifi_clients')->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function index(Request $request)
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) return $this->unauthorizedResponse();

        $export   = in_array($request->query('export'), ['1', 'true', 'yes'], true);
        $page     = max(1, (int) ($request->query('page', 1)));
        $pageSize = 20;
        $offset   = ($page - 1) * $pageSize;

        $query = DB::table('wifi_clients as wc')
            ->select([
                'wc.id', 'wc.phone', 'wc.mac_address', 'wc.ip_address',
                'wc.is_active', 'wc.session_minutes', 'wc.session_started_at',
                'wc.created_at', 'wc.updated_at', 'wc.tenant_id',
                DB::raw('(SELECT COUNT(*) FROM wifi_clients WHERE phone = wc.phone) as registration_count'),
            ]);

        // Clients only see their own users
        if ($auth->role === 'client') {
            $query->where('wc.tenant_id', $auth->tenantId);
        }

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

        $mapper = fn ($row) => $this->mapRow($row);

        if ($export) {
            $rows = $query->orderByDesc('wc.updated_at')->get()->map($mapper);
            return response()->json(['success' => true, 'data' => $rows, 'total' => $rows->count()]);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('wc.updated_at')
            ->limit($pageSize)
            ->offset($offset)
            ->get()
            ->map($mapper);

        return response()->json([
            'success'  => true,
            'data'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $pageSize,
        ]);
    }

    /**
     * POST /api/wifi-clients/sync-mikrotik  (admin only)
     *
     * Fetches live sessions from MikroTik. Any DB client marked active
     * whose MAC address is NOT in MikroTik's active list gets deactivated.
     */
    public function syncWithMikrotik(Request $request)
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) return $this->unauthorizedResponse();
        if ($auth->role !== 'admin') return $this->forbiddenResponse();

        $mtResult = (new MikroTikService())->getActiveSessions();

        $activeMacs = [];
        foreach (($mtResult['sessions'] ?? []) as $session) {
            $mac = strtoupper(trim($session['mac-address'] ?? ''));
            if ($mac !== '') {
                $activeMacs[] = $mac;
            }
        }

        // Find DB records marked active but whose MAC isn't in MikroTik
        $dbActive = DB::table('wifi_clients')
            ->where('is_active', 1)
            ->whereNotNull('mac_address')
            ->select(['id', 'phone', 'mac_address'])
            ->get();

        $deactivated = [];
        foreach ($dbActive as $client) {
            $mac = strtoupper(trim($client->mac_address ?? ''));
            if ($mac !== '' && !in_array($mac, $activeMacs, true)) {
                DB::table('wifi_clients')
                    ->where('id', $client->id)
                    ->update(['is_active' => 0, 'updated_at' => now()]);
                $deactivated[] = [
                    'id'    => $client->id,
                    'phone' => $client->phone,
                    'mac'   => $client->mac_address,
                ];
            }
        }

        return response()->json([
            'success'               => true,
            'mikrotik_error'        => $mtResult['error'],
            'mikrotik_active_count' => count($activeMacs),
            'mikrotik_sessions'     => $mtResult['sessions'] ?? [],
            'deactivated_count'     => count($deactivated),
            'deactivated'           => $deactivated,
        ]);
    }

    private function mapRow(object $row): object
    {
        $row->is_active          = (bool) $row->is_active;
        $row->registration_count = (int) $row->registration_count;
        return $row;
    }
}