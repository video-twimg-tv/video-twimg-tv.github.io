<?php
// config.php
// Edit values below to fit your environment

// DB type: 'sqlite' or 'mysql'
$db_type = 'sqlite';

// If sqlite:
$sqlite_path = __DIR__ . '/donations.sqlite';

// If mysql:
$mysql = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'donations_db',
    'user' => 'dbuser',
    'pass' => 'dbpass',
    'charset' => 'utf8mb4'
];

// Admin credentials:
// Generate password hash with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
$admin_user = 'admin';
$admin_password_hash = password_hash('change_this_password', PASSWORD_DEFAULT); // CHANGE immediately

// Email notification settings
$notify_email_enabled = false; // set true to enable mail notifications
$notify_email_to = 'akunmonja1@gmail.com';
$notify_email_from = 'akunmonja1@gmail.com';
$notify_email_subject = 'New crypto donation received';

// If you prefer SMTP / PHPMailer, you can integrate it in save_donation.php
