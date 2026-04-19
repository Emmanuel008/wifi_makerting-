<?php

/**
 * Copy to db-credentials.php (gitignored) and adjust values.
 *
 * Run php/sql/managed_users.sql for users, login (login.php), and User Management (managed-users.php).
 * Run php/sql/wifi_captive.sql for guest leads (/wifi + captive-register.php).
 * Run php/sql/wifi_sessions.sql for phone/device/SSID/online time (wifi-session.php, wifi-connected-list.php).
 * Optional: set captive_wifi_password (or env CAPTIVE_WIFI_PASSWORD) to the portal password guests must enter.
 * If you already have managed_users without passwords, run php/sql/migrate_managed_users_password.sql once.
 */
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'wifimarketing',
    'username' => 'root',
    'password' => 'CHANGE_ME',
    'captive_wifi_password' => 'CHANGE_ME_GUEST_WIFI',
];
