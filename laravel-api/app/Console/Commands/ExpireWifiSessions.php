<?php

namespace App\Console\Commands;

use App\Services\MikroTikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs every minute via scheduler.
 * Finds sessions that have exceeded their time limit and kicks them from MikroTik.
 *
 * Register in app/Console/Kernel.php:
 *   $schedule->command('wifi:expire-sessions')->everyMinute();
 */
class ExpireWifiSessions extends Command
{
    protected $signature   = 'wifi:expire-sessions';
    protected $description = 'Kick and deactivate WiFi clients whose session time limit has expired';

    public function handle(): int
    {
        // Find all active clients with a started session that has expired.
        // Uses SQLite-compatible datetime arithmetic.
        $expired = DB::table('wifi_clients')
            ->where('is_active', true)
            ->whereNotNull('session_started_at')
            ->whereRaw("datetime(session_started_at, '+' || session_minutes || ' minutes') <= datetime('now')")
            ->select(['id', 'phone', 'mac_address'])
            ->get();

        if ($expired->isEmpty()) {
            return self::SUCCESS;
        }

        $mikrotik = new MikroTikService();

        foreach ($expired as $client) {
            // Kick active MikroTik session
            if (!empty($client->mac_address)) {
                try {
                    $mikrotik->kickHotspotSession($client->mac_address);
                } catch (\Throwable) {}
            }

            // Mark inactive
            DB::table('wifi_clients')->where('id', $client->id)->update(['is_active' => false]);

            $this->line("Expired: {$client->phone} ({$client->mac_address})");
        }

        $this->info("Expired {$expired->count()} session(s).");

        return self::SUCCESS;
    }
}
