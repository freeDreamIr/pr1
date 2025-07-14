```bash
#!/bin/bash

# =================================================================
# اسکریپت نصب خودکار پنل مدیریت ترافیک Iptables
# =================================================================

# --- متغیرها ---
# آدرس ریپازیتوری گیت‌هاب خود را اینجا وارد کنید
REPO_URL="https://github.com/freeDreamIr/pr1.git"
# مسیر نصب پنل
INSTALL_PATH="/var/www/traffic_manager"

# --- رنگ‌ها برای خروجی بهتر ---
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# --- توابع ---

# تابع برای نمایش پیام‌های اطلاعاتی
info() {
    echo -e "${GREEN}[INFO] $1${NC}"
}

# تابع برای نمایش پیام‌های خطا
error() {
    echo -e "${RED}[ERROR] $1${NC}"
    exit 1
}

# تابع برای بررسی اجرای اسکریپت با دسترسی روت
check_root() {
    if [ "$(id -u)" -ne 0 ]; then
        error "این اسکریپت باید با دسترسی root اجرا شود. لطفا از sudo استفاده کنید."
    fi
}

# تابع برای نصب پیش‌نیازها
install_dependencies() {
    info "در حال بررسی و نصب پیش‌نیازها..."
    apt-get update
    apt-get install -y git curl php php-sqlite3 &> /dev/null
    if ! command -v php &> /dev/null; then
        error "نصب PHP با مشکل مواجه شد. لطفا به صورت دستی نصب کنید."
    fi
    info "پیش‌نیازها با موفقیت نصب شدند."
}

# تابع برای کلون کردن پروژه از گیت‌هاب
clone_repo() {
    info "در حال دریافت فایل‌های پروژه از گیت‌هاب..."
    if [ -d "$INSTALL_PATH" ]; then
        info "پوشه پروژه از قبل وجود دارد. در حال به‌روزرسانی..."
        cd "$INSTALL_PATH"
        git pull origin main
    else
        git clone "$REPO_URL" "$INSTALL_PATH"
    fi
    if [ ! -d "$INSTALL_PATH" ]; then
        error "دریافت پروژه از گیت‌هاب با شکست مواجه شد."
    fi
    info "پروژه با موفقیت در مسیر $INSTALL_PATH قرار گرفت."
}

# تابع برای تنظیم دسترسی‌ها
set_permissions() {
    info "در حال تنظیم دسترسی‌های فایل..."
    # ایجاد پوشه دیتابیس در صورت عدم وجود
    mkdir -p "$INSTALL_PATH/db"
    # تنظیم مالکیت پوشه پروژه برای کاربر وب‌سرور
    chown -R www-data:www-data "$INSTALL_PATH"
    # تنظیم دسترسی صحیح برای پوشه دیتابیس
    chmod -R 755 "$INSTALL_PATH"
    info "دسترسی‌ها تنظیم شدند."
}

# تابع برای تنظیم Sudoers
configure_sudo() {
    info "در حال تنظیم دسترسی sudo برای اجرای دستور iptables..."
    SUDOERS_FILE="/etc/sudoers.d/traffic_manager_sudo"
    echo "www-data ALL=(ALL) NOPASSWD: /usr/sbin/iptables" > "$SUDOERS_FILE"
    echo "www-data ALL=(ALL) NOPASSWD: /sbin/iptables" >> "$SUDOERS_FILE"
    chmod 0440 "$SUDOERS_FILE"
    info "دسترسی Sudo برای وب‌سرور تنظیم شد."
}

# تابع برای تنظیم Cronjob
configure_cron() {
    info "در حال تنظیم Cronjob برای به‌روزرسانی خودکار ترافیک..."
    CRON_COMMAND="*/5 * * * * /usr/bin/php $INSTALL_PATH/cron.php > /dev/null 2>&1"
    # حذف کرانجاب قبلی برای جلوگیری از تکرار
    (crontab -l 2>/dev/null | grep -v "$INSTALL_PATH/cron.php" ; echo "$CRON_COMMAND") | crontab -
    info "Cronjob برای اجرا در هر 5 دقیقه تنظیم شد."
}

# --- اجرای اصلی اسکریپت ---
main() {
    check_root
    install_dependencies
    clone_repo
    set_permissions
    configure_sudo
    configure_cron

    echo -e "\n${GREEN}====================================================="
    echo -e "         نصب با موفقیت به پایان رسید! 🎉"
    echo -e "=====================================================${NC}\n"
    echo -e "پنل شما در مسیر زیر نصب شده است:"
    echo -e "${YELLOW}$INSTALL_PATH${NC}"
    echo -e "\nبرای دسترسی به پنل، فایل ${YELLOW}manage.php${NC} را در مرورگر خود باز کنید."
    echo -e "مثال: http://YOUR_SERVER_IP/traffic_manager/manage.php\n"
}


