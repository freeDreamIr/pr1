<?php
// این اسکریپت باید توسط cronjob اجرا شود.
// مثال: */5 * * * * /usr/bin/php /var/www/traffic_manager/cron.php > /dev/null 2>&1

require_once __DIR__ . '/lib/IptablesManager.php';

$dbPath = __DIR__ . '/db/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$iptables = new IptablesManager();

$stmt = $db->query("SELECT * FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Starting traffic sync for " . count($users) . " users at " . date('Y-m-d H:i:s') . "\n";

foreach ($users as $user) {
    // 1. خواندن ترافیک جدید از iptables
    $newTraffic = $iptables->getTraffic($user['username']);

    if ($newTraffic > 0) {
        // 2. افزودن ترافیک جدید به ترافیک کل ذخیره شده در دیتابیس
        $totalTraffic = $user['traffic_total'] + $newTraffic;

        // 3. بروزرسانی دیتابیس با مقدار جدید
        $updateStmt = $db->prepare("UPDATE users SET traffic_total = ?, last_update = ? WHERE id = ?");
        $updateStmt->execute([$totalTraffic, time(), $user['id']]);

        // 4. صفر کردن شمارنده در iptables برای جلوگیری از شمارش مجدد در اجرای بعدی
        $iptables->resetCounter($user['username']);

        echo " - User: {$user['username']}, New Traffic: $newTraffic bytes, Total: $totalTraffic bytes\n";
    }
}

echo "Sync finished.\n";
