<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;

class DashboardStatsController extends BaseApiController
{
    public function index()
    {
        $totalClients    = DB::table('wifi_clients')->count();
        $totalSms        = DB::table('sms_outbox')->count();
        $totalRecipients = DB::table('sms_outbox_recipients')->count();
        $deliveredSms    = DB::table('sms_outbox_recipients')
            ->where('status', 'delivered')
            ->count();

        $deliverability = $totalRecipients > 0
            ? round(($deliveredSms / $totalRecipients) * 100, 1)
            : null;

        return response()->json([
            'success'          => true,
            'total_clients'    => $totalClients,
            'total_sms'        => $totalSms,
            'total_recipients' => $totalRecipients,
            'delivered_sms'    => $deliveredSms,
            'deliverability'   => $deliverability,
        ]);
    }
}
