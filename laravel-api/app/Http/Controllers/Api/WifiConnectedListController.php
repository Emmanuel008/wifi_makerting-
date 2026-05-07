<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class WifiConnectedListController extends BaseApiController
{
    public function list(Request $request)
    {
        $body = $this->decodeJsonBody($request->getContent());
        if ($body === null) {
            return response()->json(['sucess' => false, 'error' => 'Invalid JSON body'], 400);
        }

        $page = isset($body['page']) ? (int) $body['page'] : 1;
        $pageSize = isset($body['pageSize']) ? (int) $body['pageSize'] : 10;
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;

        try {
            $total = (int) DB::table('wifi_sessions')->count();
            $rows = DB::table('wifi_sessions')
                ->selectRaw("id, phone, COALESCE(NULLIF(TRIM(device), ''), LEFT(COALESCE(user_agent, ''), 80)) AS device, ssid, TIMESTAMPDIFF(SECOND, connected_at, IFNULL(disconnected_at, UTC_TIMESTAMP())) AS online_seconds, (disconnected_at IS NULL AND last_seen_at >= (UTC_TIMESTAMP() - INTERVAL 5 MINUTE)) AS is_live")
                ->orderByDesc('last_seen_at')
                ->offset($offset)
                ->limit($pageSize)
                ->get();
        } catch (Throwable) {
            return response()->json(['sucess' => false, 'error' => 'Query failed. Did you run php/sql/wifi_sessions.sql?'], 500);
        }

        $output = $rows->map(static fn ($row) => [
            'id' => (int) $row->id,
            'phone' => $row->phone !== null ? (string) $row->phone : '',
            'device' => (string) ($row->device ?? ''),
            'ssid' => $row->ssid !== null ? (string) $row->ssid : '',
            'onlineSeconds' => (int) ($row->online_seconds ?? 0),
            'isLive' => ((int) ($row->is_live ?? 0)) === 1,
        ])->all();

        return response()->json([
            'sucess' => true,
            'rows' => $output,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
