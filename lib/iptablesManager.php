<?php

/**
 * Class IptablesManager
 *
 * این کلاس تمام عملیات مربوط به افزودن، حذف و خواندن ترافیک کاربران
 * از iptables را مدیریت می‌کند.
 */
class IptablesManager
{
    /**
     * یک کاربر جدید به سیستم اضافه کرده و قوانین iptables را برای او تنظیم می‌کند.
     * ما ترافیک کاربر را بر اساس یک پورت منحصر به فرد شناسایی می‌کنیم.
     *
     * @param string $username نام کاربری
     * @param int $port پورت اختصاصی کاربر
     */
    public function addUser(string $username, int $port): void
    {
        $chainName = "traffic_" . $username;
        // 1. ایجاد یک chain جدید برای شمارش ترافیک کاربر
        shell_exec("sudo iptables -N $chainName");

        // 2. هدایت تمام ترافیک (ورودی و خروجی) از پورت کاربر به chain اختصاصی او
        shell_exec("sudo iptables -I FORWARD 1 -p tcp --dport $port -j $chainName");
        shell_exec("sudo iptables -I FORWARD 1 -p tcp --sport $port -j $chainName");
    }

    /**
     * کاربری را از سیستم حذف کرده و قوانین iptables مربوط به او را پاک می‌کند.
     *
     * @param string $username نام کاربری
     * @param int $port پورت اختصاصی کاربر
     */
    public function removeUser(string $username, int $port): void
    {
        $chainName = "traffic_" . $username;
        // 1. حذف قوانین از chain اصلی (FORWARD)
        shell_exec("sudo iptables -D FORWARD -p tcp --dport $port -j $chainName");
        shell_exec("sudo iptables -D FORWARD -p tcp --sport $port -j $chainName");

        // 2. پاک کردن (Flush) تمام قوانین داخل chain اختصاصی کاربر
        shell_exec("sudo iptables -F $chainName");

        // 3. حذف کامل chain اختصاصی کاربر
        shell_exec("sudo iptables -X $chainName");
    }

    /**
     * میزان ترافیک مصرفی یک کاربر را از iptables می‌خواند.
     *
     * @param string $username نام کاربری
     * @return int میزان ترافیک به بایت
     */
    public function getTraffic(string $username): int
    {
        $chainName = "traffic_" . $username;
        // اجرای دستور برای خواندن اطلاعات با جزئیات کامل (-v -n -x)
        $output = shell_exec("sudo iptables -L $chainName -v -n -x");

        if (empty($output)) {
            return 0;
        }

        // با استفاده از Regular Expression، فقط ستون دوم (بایت‌ها) را استخراج و جمع می‌زنیم
        preg_match_all('/^\s*\d+\s+(\d+)/m', $output, $matches);
        return array_sum($matches[1]);
    }

    /**
     * شمارنده ترافیک یک کاربر را در iptables صفر می‌کند.
     * این کار بعد از خواندن ترافیک انجام می‌شود تا در دفعه بعد، ترافیک تکراری محاسبه نشود.
     *
     * @param string $username نام کاربری
     */
    public function resetCounter(string $username): void
    {
        $chainName = "traffic_" . $username;
        shell_exec("sudo iptables -Z $chainName");
    }
}
