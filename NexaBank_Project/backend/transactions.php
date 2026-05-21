<?php
// ============================================================
//  NexaBank India — Transactions API
//  File: backend/transactions.php
// ============================================================

require_once 'config.php';

$user = get_auth_user();
if (!$user) { json_response(['success'=>false,'message'=>'Authentication required.'],401); }

$action = $_GET['action'] ?? 'list';
$db     = get_db();
$uid    = (int)$user['user_id'];

switch ($action) {

    case 'list':
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $type  = $_GET['type'] ?? '';      // debit|credit
        $cat   = $_GET['category'] ?? '';

        $where = "WHERE a.user_id=?";
        $params = [$uid];
        $types  = 'i';
        if ($type) { $where .= " AND t.txn_type=?"; $params[]=$type; $types.='s'; }
        if ($cat)  { $where .= " AND t.category=?"; $params[]=$cat;  $types.='s'; }

        $stmt = $db->prepare("
            SELECT t.txn_id,t.txn_type,t.amount,t.merchant_name,t.category,
                   t.location,t.status,t.balance_after,t.txn_date,t.reference_no
            FROM transactions t JOIN accounts a ON t.account_id=a.account_id
            {$where}
            ORDER BY t.txn_date DESC LIMIT ? OFFSET ?
        ");
        $params[] = $limit; $params[] = $offset; $types .= 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $txns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        json_response(['success'=>true,'transactions'=>$txns,'page'=>$page]);
        break;

    case 'transfer':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $to_acct  = trim($body['to_account'] ?? '');
        $amount   = (float)($body['amount'] ?? 0);
        $remark   = trim($body['remark'] ?? 'Fund Transfer');

        if (!$to_acct || $amount <= 0) {
            json_response(['success'=>false,'message'=>'Valid account number and amount required.'],400);
        }
        if ($amount > 200000) {
            json_response(['success'=>false,'message'=>'Transfer limit is ₹2,00,000 per transaction.'],400);
        }

        // Get sender account
        $src = $db->prepare("SELECT account_id,balance FROM accounts WHERE user_id=? AND account_type='savings' AND is_active=1");
        $src->bind_param('i',$uid); $src->execute();
        $sender = $src->get_result()->fetch_assoc();

        if (!$sender || $sender['balance'] < $amount) {
            json_response(['success'=>false,'message'=>'Insufficient balance.'],400);
        }

        // Get receiver account
        $dst = $db->prepare("SELECT account_id,user_id,balance FROM accounts WHERE account_number=? AND is_active=1");
        $dst->bind_param('s',$to_acct); $dst->execute();
        $receiver = $dst->get_result()->fetch_assoc();

        if (!$receiver) {
            json_response(['success'=>false,'message'=>'Destination account not found.'],404);
        }

        // Transaction (atomic)
        $db->begin_transaction();
        try {
            $ref = gen_reference();
            $new_sender_bal   = $sender['balance'] - $amount;
            $new_receiver_bal = $receiver['balance'] + $amount;

            // Debit sender
            $d = $db->prepare("UPDATE accounts SET balance=? WHERE account_id=?");
            $d->bind_param('di',$new_sender_bal,$sender['account_id']); $d->execute();

            // Credit receiver
            $c = $db->prepare("UPDATE accounts SET balance=? WHERE account_id=?");
            $c->bind_param('di',$new_receiver_bal,$receiver['account_id']); $c->execute();

            // Log debit transaction
            $t1 = $db->prepare("INSERT INTO transactions(account_id,txn_type,amount,merchant_name,category,description,status,balance_after,reference_no) VALUES(?,'debit',?,?,?,'Fund Transfer','success',?,?)");
            $t1->bind_param('idssds',$sender['account_id'],$amount,$to_acct,'transfer',$new_sender_bal,$ref); $t1->execute();

            // Log credit transaction
            $ref2 = gen_reference();
            $t2 = $db->prepare("INSERT INTO transactions(account_id,txn_type,amount,merchant_name,category,description,status,balance_after,reference_no) VALUES(?,'credit',?,?,?,'Fund Transfer Received','success',?,?)");
            $t2->bind_param('idssds',$receiver['account_id'],$amount,'Transfer In','transfer',$new_receiver_bal,$ref2); $t2->execute();

            $db->commit();
            json_response(['success'=>true,'message'=>"₹".number_format($amount,2)." transferred successfully.",'reference'=>$ref]);
        } catch (Exception $e) {
            $db->rollback();
            json_response(['success'=>false,'message'=>'Transfer failed. Please try again.'],500);
        }
        break;

    default:
        json_response(['success'=>false,'message'=>'Invalid action.'],404);
}
