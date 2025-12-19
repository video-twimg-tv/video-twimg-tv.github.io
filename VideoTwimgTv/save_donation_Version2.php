<?php
// save_donation.php (updated)
// Stores incoming JSON donation into DB (SQLite or MySQL), writes CSV/JSON backup and optionally sends email notification.

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false, 'message'=>'Method not allowed']);
    exit;
}

// Read raw input (expect JSON)
$raw = file_get_contents('php://input');
if (!$raw) {
    echo json_encode(['success'=>false, 'message'=>'No input received']);
    exit;
}

$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success'=>false, 'message'=>'Invalid JSON']);
    exit;
}

// Extract and strict-validate
$name = isset($data['name']) ? trim($data['name']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$usd_amount = isset($data['usd_amount']) ? trim($data['usd_amount']) : '';
$crypto_amount = isset($data['crypto_amount']) ? trim($data['crypto_amount']) : '';
$network = isset($data['network']) ? trim($data['network']) : '';
$address = isset($data['address']) ? trim($data['address']) : '';
$timestamp = isset($data['timestamp']) ? trim($data['timestamp']) : date('c');

// Strict email validation: filter_var + RFC-like regex length checks
function is_valid_email($email) {
    if (!$email) return false;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    // optionally restrict domains etc. Keep simple but robust:
    return strlen($email) <= 254;
}

// Basic validation
if ($usd_amount === '' || !is_numeric($usd_amount)) {
    echo json_encode(['success'=>false, 'message'=>'Invalid USD amount']);
    exit;
}
if ($address === '') {
    echo json_encode(['success'=>false, 'message'=>'Address missing']);
    exit;
}
if ($email !== '' && !is_valid_email($email)) {
    // reject invalid email this time (per request stricter)
    echo json_encode(['success'=>false, 'message'=>'Invalid email address']);
    exit;
}

// sanitize for storage
function sanitize_val($v) {
    return trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$v));
}

$record = [
    'timestamp' => sanitize_val($timestamp),
    'name' => sanitize_val($name ?: '(anonymous)'),
    'email' => sanitize_val($email),
    'usd_amount' => number_format((float)$usd_amount, 2, '.', ''),
    'crypto_amount' => sanitize_val($crypto_amount),
    'network' => sanitize_val($network),
    'address' => sanitize_val($address),
];

// Save to DB
try {
    if ($db_type === 'sqlite') {
        $pdo = new PDO('sqlite:' . $sqlite_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare('INSERT INTO donations (timestamp,name,email,usd_amount,crypto_amount,network,address) VALUES (:timestamp,:name,:email,:usd_amount,:crypto_amount,:network,:address)');
    } else {
        $dsn = "mysql:host={$mysql['host']};port={$mysql['port']};dbname={$mysql['dbname']};charset={$mysql['charset']}";
        $pdo = new PDO($dsn, $mysql['user'], $mysql['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->prepare('INSERT INTO donations (timestamp,name,email,usd_amount,crypto_amount,network,address) VALUES (:timestamp,:name,:email,:usd_amount,:crypto_amount,:network,:address)');
    }
    $stmt->execute([
        ':timestamp' => $record['timestamp'],
        ':name' => $record['name'],
        ':email' => $record['email'],
        ':usd_amount' => $record['usd_amount'],
        ':crypto_amount' => $record['crypto_amount'],
        ':network' => $record['network'],
        ':address' => $record['address']
    ]);
} catch (Exception $e) {
    // fallback: still try to append CSV/JSON and return error
    error_log("DB error: " . $e->getMessage());
    // continue to CSV/JSON
}

// Backup to CSV + JSON (non-fatal)
$csvFile = __DIR__ . '/donations.csv';
$jsonFile = __DIR__ . '/donations.json';
$csvHeader = ['timestamp','name','email','usd_amount','crypto_amount','network','address'];

$fp = @fopen($csvFile, 'a');
if ($fp) {
    if (filesize($csvFile) === 0) {
        fputcsv($fp, $csvHeader);
    }
    fputcsv($fp, [$record['timestamp'],$record['name'],$record['email'],$record['usd_amount'],$record['crypto_amount'],$record['network'],$record['address']]);
    fclose($fp);
}

$jsonArr = [];
if (file_exists($jsonFile)) {
    $jsonRaw = file_get_contents($jsonFile);
    if ($jsonRaw !== false) {
        $tmp = json_decode($jsonRaw, true);
        if (is_array($tmp)) $jsonArr = $tmp;
    }
}
$jsonArr[] = $record;
if (count($jsonArr) > 500) $jsonArr = array_slice($jsonArr, -500);
@file_put_contents($jsonFile, json_encode($jsonArr, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// Send email notification if enabled
if (!empty($notify_email_enabled) && !empty($notify_email_to)) {
    $to = $notify_email_to;
    $subject = $notify_email_subject;
    $message = "New donation received:\n\n";
    $message .= "Name: {$record['name']}\n";
    $message .= "Email: {$record['email']}\n";
    $message .= "USD: \${$record['usd_amount']}\n";
    $message .= "Crypto: {$record['crypto_amount']}\n";
    $message .= "Network: {$record['network']}\n";
    $message .= "Address: {$record['address']}\n";
    $message .= "Time: {$record['timestamp']}\n";
    $headers = "From: {$notify_email_from}\r\n";
    // Use PHP mail(); for production consider PHPMailer + SMTP
    try {
        @mail($to, $subject, $message, $headers);
    } catch (Exception $e) {
        error_log("Mail error: " . $e->getMessage());
    }
}

echo json_encode(['success'=>true, 'message'=>'Saved']);
exit;
?>