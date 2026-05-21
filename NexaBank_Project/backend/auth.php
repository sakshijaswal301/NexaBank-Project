<?php
// ============================================================
//  NexaBank India — Authentication API
//  File: backend/auth.php
//  Endpoints: register | login | verify-otp | logout
// ============================================================

require_once 'config.php';

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    // ----------------------------------------------------------
    // POST /auth.php?action=register
    // ----------------------------------------------------------
    case 'register':
        $required = ['first_name','last_name','email','mobile','pan_number','password'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                json_response(['success'=>false, 'message'=>"Field '$field' is required."], 400);
            }
        }

        $db         = get_db();
        $first      = trim($body['first_name']);
        $last       = trim($body['last_name']);
        $email      = strtolower(trim($body['email']));
        $mobile     = preg_replace('/\D/', '', $body['mobile']);
        $pan        = strtoupper(trim($body['pan_number']));
        $password   = $body['password'];

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success'=>false,'message'=>'Invalid email address.'], 400);
        }
        // Validate PAN (Indian format: AAAAA9999A)
        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
            json_response(['success'=>false,'message'=>'Invalid PAN number format.'], 400);
        }
        // Check password strength
        if (strlen($password) < 8) {
            json_response(['success'=>false,'message'=>'Password must be at least 8 characters.'], 400);
        }

        // Check duplicates
        $check = $db->prepare("SELECT user_id FROM users WHERE email=? OR mobile=? OR pan_number=?");
        $check->bind_param('sss', $email, $mobile, $pan);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            json_response(['success'=>false,'message'=>'Account already exists with this email, mobile, or PAN.'], 409);
        }

        // Hash password (bcrypt)
        $hash        = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
        $customer_id = 'NXB' . date('Y') . str_pad((string)rand(1,9999), 4, '0', STR_PAD_LEFT);
        $otp         = gen_otp();
        $otp_expiry  = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmt = $db->prepare("
            INSERT INTO users(first_name,last_name,email,mobile,pan_number,password_hash,customer_id,otp_code,otp_expires_at)
            VALUES(?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param('sssssssss', $first,$last,$email,$mobile,$pan,$hash,$customer_id,$otp,$otp_expiry);

        if (!$stmt->execute()) {
            json_response(['success'=>false,'message'=>'Registration failed. Please try again.'], 500);
        }

        $new_user_id = $db->insert_id;

        // Create default savings account
        $acct_number = '4001' . str_pad((string)rand(1,999999999999), 12, '0', STR_PAD_LEFT);
        $acct_stmt = $db->prepare("INSERT INTO accounts(user_id,account_number,account_type,balance) VALUES(?,?,'savings',0.00)");
        $acct_stmt->bind_param('is', $new_user_id, $acct_number);
        $acct_stmt->execute();

        // In production: send OTP via SMS (Twilio/MSG91)
        // For demo: return OTP in response
        json_response([
            'success'     => true,
            'message'     => 'Registration successful! Verify your mobile with OTP.',
            'customer_id' => $customer_id,
            'otp'         => $otp,  // REMOVE in production — send via SMS
            'mobile'      => substr($mobile, 0, 3) . 'XXXXX' . substr($mobile, -2)
        ]);
        break;

    // ----------------------------------------------------------
    // POST /auth.php?action=login
    // ----------------------------------------------------------
    case 'login':
        $identifier = trim($body['identifier'] ?? '');  // customer_id or mobile
        $password   = $body['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            json_response(['success'=>false,'message'=>'Customer ID and password are required.'], 400);
        }

        $db   = get_db();
        $stmt = $db->prepare("
            SELECT user_id,first_name,last_name,email,mobile,customer_id,password_hash,is_verified,is_active
            FROM users WHERE customer_id=? OR mobile=? LIMIT 1
        ");
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            json_response(['success'=>false,'message'=>'Invalid credentials. Please try again.'], 401);
        }
        if (!$user['is_active']) {
            json_response(['success'=>false,'message'=>'Your account has been suspended. Contact support.'], 403);
        }

        // Generate OTP for 2FA
        $otp        = gen_otp();
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $upd = $db->prepare("UPDATE users SET otp_code=?,otp_expires_at=?,last_login=NOW() WHERE user_id=?");
        $upd->bind_param('ssi', $otp, $otp_expiry, $user['user_id']);
        $upd->execute();

        // In production: send OTP via SMS
        json_response([
            'success'   => true,
            'message'   => 'OTP sent to your registered mobile.',
            'user_id'   => $user['user_id'],
            'name'      => $user['first_name'] . ' ' . $user['last_name'],
            'mobile'    => substr($user['mobile'],0,3).'XXXXX'.substr($user['mobile'],-2),
            'otp'       => $otp   // REMOVE in production
        ]);
        break;

    // ----------------------------------------------------------
    // POST /auth.php?action=verify-otp
    // ----------------------------------------------------------
    case 'verify-otp':
        $user_id = (int)($body['user_id'] ?? 0);
        $otp     = trim($body['otp'] ?? '');

        if (!$user_id || empty($otp)) {
            json_response(['success'=>false,'message'=>'User ID and OTP are required.'], 400);
        }

        $db   = get_db();
        $stmt = $db->prepare("
            SELECT user_id,first_name,last_name,email,customer_id,otp_code,otp_expires_at
            FROM users WHERE user_id=? LIMIT 1
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || $user['otp_code'] !== $otp) {
            json_response(['success'=>false,'message'=>'Invalid OTP. Please try again.'], 401);
        }
        if (strtotime($user['otp_expires_at']) < time()) {
            json_response(['success'=>false,'message'=>'OTP expired. Please request a new one.'], 401);
        }

        // Mark verified
        $vrf = $db->prepare("UPDATE users SET is_verified=1,otp_code=NULL,otp_expires_at=NULL WHERE user_id=?");
        $vrf->bind_param('i', $user_id);
        $vrf->execute();

        // Create session token
        $token      = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . SESSION_HOURS . ' hours'));
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $sess = $db->prepare("INSERT INTO user_sessions(session_token,user_id,ip_address,user_agent,expires_at) VALUES(?,?,?,?,?)");
        $sess->bind_param('sisss', $token, $user_id, $ip, $ua, $expires_at);
        $sess->execute();

        json_response([
            'success'     => true,
            'message'     => 'Login successful. Welcome to NexaBank!',
            'token'       => $token,
            'expires_at'  => $expires_at,
            'user' => [
                'user_id'     => $user['user_id'],
                'name'        => $user['first_name'] . ' ' . $user['last_name'],
                'email'       => $user['email'],
                'customer_id' => $user['customer_id']
            ]
        ]);
        break;

    // ----------------------------------------------------------
    // POST /auth.php?action=logout
    // ----------------------------------------------------------
    case 'logout':
        $user = get_auth_user();
        if (!$user) { json_response(['success'=>false,'message'=>'Not authenticated.'], 401); }

        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $db    = get_db();
        $stmt  = $db->prepare("UPDATE user_sessions SET is_valid=0 WHERE session_token=?");
        $stmt->bind_param('s', $token);
        $stmt->execute();

        json_response(['success'=>true,'message'=>'Logged out successfully.']);
        break;

    default:
        json_response(['success'=>false,'message'=>'Invalid action.'], 404);
}
