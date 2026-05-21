<?php
// ============================================================
//  NexaBank India — Database Configuration
//  File: backend/config.php
// ============================================================

define('DB_HOST',     'localhost');
define('DB_USER',     'root');            // XAMPP default username
define('DB_PASS',     '');                // XAMPP default (empty)
define('DB_NAME',     'nexabank_db');
define('DB_PORT',     3306);

// JWT / Session
define('SESSION_SECRET', 'nexabank_secret_key_change_in_production_2024');
define('SESSION_HOURS',  24);

// App
define('APP_NAME',  'NexaBank India');
define('APP_ENV',   'development');       // change to 'production' when live
define('APP_URL',   'http://localhost/NexaBank_Project/backend');
// ── NOTE FOR XAMPP SETUP ──────────────────────────────────────
// Place this folder at: C:\xampp\htdocs\NexaBank_Project\
// Open website at:      http://localhost/NexaBank_Project/frontend/index.html
// Import database from: database/nexabank_db.sql  via phpMyAdmin

// ── Database Connection (Singleton) ──────────────────────────
function get_db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// ── JSON Response Helper ──────────────────────────────────────
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data);
    exit;
}

// ── Auth: get current user from session token ─────────────────
function get_auth_user(): ?array {
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    if (empty($token)) return null;

    $db  = get_db();
    $stmt = $db->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.mobile, u.customer_id
        FROM user_sessions s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.session_token = ? AND s.is_valid = 1 AND s.expires_at > NOW()
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// ── Generate unique reference number ─────────────────────────
function gen_reference(): string {
    return 'NXB' . date('YmdHis') . rand(10, 99);
}

// ── Generate OTP ─────────────────────────────────────────────
function gen_otp(): string {
    return str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}
