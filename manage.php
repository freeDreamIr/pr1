<?php
// --- تنظیمات اولیه و اتصال به دیتابیس SQLite ---
$dbPath = __DIR__ . '/db/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    port INTEGER NOT NULL UNIQUE,
    traffic_total BIGINT DEFAULT 0,
    last_update INTEGER
)");

require_once 'lib/IptablesManager.php';
$iptables = new IptablesManager();
$message = '';

// --- پردازش درخواست‌های POST (افزودن/حذف کاربر) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // افزودن کاربر جدید
    if (isset($_POST['add_user']) && !empty($_POST['username'])) {
        $username = trim($_POST['username']);
        // پیدا کردن یک پورت آزاد (مثلا در بازه 10000 تا 20000)
        $stmt = $db->query("SELECT port FROM users");
        $used_ports = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $port = 10000;
        while (in_array($port, $used_ports)) {
            $port++;
        }

        try {
            $stmt = $db->prepare("INSERT INTO users (username, port) VALUES (?, ?)");
            $stmt->execute([$username, $port]);
            $iptables->addUser($username, $port);
            $message = "کاربر $username با پورت $port با موفقیت اضافه شد.";
        } catch (PDOException $e) {
            $message = "خطا: نام کاربری یا پورت تکراری است.";
        }
    }

    // حذف کاربر
    if (isset($_POST['remove_user'])) {
        $userId = $_POST['user_id'];
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $iptables->removeUser($user['username'], $user['port']);
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $message = "کاربر {$user['username']} با موفقیت حذف شد.";
        }
    }
}

// --- دریافت لیست کاربران برای نمایش ---
$users = $db->query("SELECT * FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// تابع کمکی برای نمایش زیبای حجم ترافیک
function formatBytes($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت بهینه ترافیک کاربران</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f8f9fa; color: #343a40; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 20px auto; background: #ffffff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1, h2 { color: #212529; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 14px; border: 1px solid #e9ecef; text-align: right; vertical-align: middle; }
        th { background-color: #f8f9fa; font-weight: 600; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        form { margin-bottom: 25px; display: flex; gap: 10px; align-items: center; }
        input[type="text"] { flex-grow: 1; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 16px; transition: border-color 0.2s; }
        input[type="text"]:focus { border-color: #80bdff; outline: none; }
        button { padding: 10px 20px; border: none; font-size: 16px; background-color: #007bff; color: white; border-radius: 6px; cursor: pointer; transition: background-color 0.2s; }
        button:hover { background-color: #0056b3; }
        button.danger { background-color: #dc3545; }
        button.danger:hover { background-color: #c82333; }
        .message { padding: 15px; background-color: #e6f7ff; border: 1px solid #b3e0ff; border-radius: 6px; margin-bottom: 20px; color: #004085; }
        .message.error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>مدیریت ترافیک کاربران</h1>
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <h2>افزودن کاربر جدید</h2>
        <form method="post">
            <input type="text" name="username" placeholder="نام کاربری جدید..." required>
            <button type="submit" name="add_user">افزودن کاربر</button>
        </form>

        <h2>لیست کاربران</h2>
        <table>
            <thead>
                <tr>
                    <th>نام کاربری</th>
                    <th>پورت</th>
                    <th>ترافیک کل</th>
                    <th>آخرین بروزرسانی</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" style="text-align: center;">هیچ کاربری یافت نشد.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= $user['port'] ?></td>
                            <td><?= formatBytes($user['traffic_total']) ?></td>
                            <td><?= $user['last_update'] ? date('Y-m-d H:i:s', $user['last_update']) : 'هرگز' ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('آیا از حذف کاربر <?= htmlspecialchars($user['username']) ?> مطمئن هستید؟');" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="remove_user" class="danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
