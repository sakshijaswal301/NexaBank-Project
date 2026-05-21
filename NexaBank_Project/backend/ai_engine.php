<?php
// ============================================================
//  NexaBank India — AI Engine API
//  File: backend/ai_engine.php
//  All 5 AI Features:
//    1. Fraud Detection
//    2. Spend Analytics
//    3. AI Credit Score
//    4. Nexa AI Chat
//    5. Smart Offers
// ============================================================

require_once 'config.php';

// All AI endpoints require authentication
$user = get_auth_user();
if (!$user) {
    json_response(['success'=>false,'message'=>'Authentication required.'], 401);
}

$action = $_GET['action'] ?? '';
$db     = get_db();
$uid    = (int)$user['user_id'];

switch ($action) {

    // ==========================================================
    // 1. FRAUD DETECTION
    //    GET /ai_engine.php?action=fraud-alerts
    //    POST /ai_engine.php?action=analyze-transaction
    //    POST /ai_engine.php?action=resolve-alert
    // ==========================================================

    case 'fraud-alerts':
        $stmt = $db->prepare("
            SELECT fa.*, t.merchant_name, t.amount, t.location, t.txn_date, t.reference_no
            FROM fraud_alerts fa
            JOIN transactions t ON fa.txn_id = t.txn_id
            WHERE fa.user_id = ?
            ORDER BY fa.created_at DESC
            LIMIT 20
        ");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $alerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        json_response(['success'=>true, 'alerts'=>$alerts, 'count'=>count($alerts)]);
        break;

    case 'analyze-transaction':
        // Called after every new transaction to run AI fraud checks
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $txn_id = (int)($body['txn_id'] ?? 0);
        if (!$txn_id) { json_response(['success'=>false,'message'=>'txn_id required'],400); }

        // Fetch transaction
        $stmt = $db->prepare("
            SELECT t.*, a.user_id FROM transactions t
            JOIN accounts a ON t.account_id=a.account_id
            WHERE t.txn_id=? AND a.user_id=?
        ");
        $stmt->bind_param('ii', $txn_id, $uid);
        $stmt->execute();
        $txn = $stmt->get_result()->fetch_assoc();
        if (!$txn) { json_response(['success'=>false,'message'=>'Transaction not found'],404); }

        $flags   = [];
        $risk    = 'low';
        $conf    = 0;

        // ── Rule 1: Geographic anomaly (non-Indian location) ──
        if (!empty($txn['location']) && !str_contains(strtolower($txn['location']), 'india')
            && !str_contains(strtolower($txn['location']), 'in')) {
            $flags[] = ['type'=>'geo_anomaly','desc'=>"Transaction from {$txn['location']} — outside your usual geographic zone.",'weight'=>40];
            $conf   += 40;
        }

        // ── Rule 2: Night-time large transaction ──
        $hour = (int)date('H', strtotime($txn['txn_date']));
        if ($txn['amount'] > 5000 && ($hour < 5 || $hour > 23)) {
            $flags[] = ['type'=>'night_large','desc'=>"High-value transaction of ₹{$txn['amount']} at {$hour}:00 (unusual hours).",'weight'=>25];
            $conf   += 25;
        }

        // ── Rule 3: Unknown category ──
        if ($txn['category'] === 'unknown') {
            $flags[] = ['type'=>'unknown_merchant','desc'=>"Merchant category unrecognised — could not verify {$txn['merchant_name']}.",'weight'=>20];
            $conf   += 20;
        }

        // ── Rule 4: Velocity check — >3 transactions in 1 hour ──
        $vel_stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM transactions t
            JOIN accounts a ON t.account_id=a.account_id
            WHERE a.user_id=? AND t.txn_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $vel_stmt->bind_param('i', $uid);
        $vel_stmt->execute();
        $vel = $vel_stmt->get_result()->fetch_assoc();
        if ($vel['cnt'] > 3) {
            $flags[] = ['type'=>'velocity','desc'=>"{$vel['cnt']} transactions detected in the last hour — possible card skimming.",'weight'=>15];
            $conf   += 15;
        }

        // ── Rule 5: Amount > 10× average spend ──
        $avg_stmt = $db->prepare("
            SELECT AVG(amount) as avg_amt FROM transactions t
            JOIN accounts a ON t.account_id=a.account_id
            WHERE a.user_id=? AND t.txn_type='debit' AND t.txn_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $avg_stmt->bind_param('i', $uid);
        $avg_stmt->execute();
        $avg = $avg_stmt->get_result()->fetch_assoc();
        if ($avg['avg_amt'] && $txn['amount'] > $avg['avg_amt'] * 10) {
            $flags[] = ['type'=>'high_value','desc'=>"Amount ₹{$txn['amount']} is " . round($txn['amount']/$avg['avg_amt'],1) . "× your average transaction.",'weight'=>20];
            $conf   += 20;
        }

        // ── Determine risk level ──
        if ($conf >= 60) $risk = 'high';
        elseif ($conf >= 30) $risk = 'medium';

        // ── Save alert if risk is medium or high ──
        if (!empty($flags) && $risk !== 'low') {
            $desc_all = implode(' | ', array_column($flags, 'desc'));
            $alert_type = $flags[0]['type'];

            $ins = $db->prepare("
                INSERT INTO fraud_alerts(txn_id,user_id,risk_level,alert_type,description,ai_confidence)
                VALUES(?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE risk_level=VALUES(risk_level), description=VALUES(description)
            ");
            $ins->bind_param('iisssd', $txn_id,$uid,$risk,$alert_type,$desc_all,$conf);
            $ins->execute();

            // Flag the transaction
            $flg = $db->prepare("UPDATE transactions SET status='flagged' WHERE txn_id=?");
            $flg->bind_param('i', $txn_id); $flg->execute();
        }

        json_response([
            'success'    => true,
            'risk_level' => $risk,
            'confidence' => $conf,
            'flags'      => $flags,
            'flagged'    => !empty($flags) && $risk !== 'low'
        ]);
        break;

    case 'resolve-alert':
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $alert_id = (int)($body['alert_id'] ?? 0);
        $action_t = $body['action'] ?? 'verified';   // 'verified','blocked','ignored'

        $stmt = $db->prepare("
            UPDATE fraud_alerts
            SET is_resolved=1, resolved_at=NOW(), action_taken=?
            WHERE alert_id=? AND user_id=?
        ");
        $stmt->bind_param('sii', $action_t, $alert_id, $uid);
        $stmt->execute();

        json_response(['success'=>true,'message'=>'Alert resolved.']);
        break;

    // ==========================================================
    // 2. SPEND ANALYTICS
    //    GET /ai_engine.php?action=spend-analytics&month=5&year=2024
    //    GET /ai_engine.php?action=spend-trend
    // ==========================================================

    case 'spend-analytics':
        $month = (int)($_GET['month'] ?? date('n'));
        $year  = (int)($_GET['year']  ?? date('Y'));

        // Trigger stored procedure to recompute
        $proc = $db->prepare("CALL compute_spend_analytics(?,?,?)");
        $proc->bind_param('iii', $uid, $month, $year);
        $proc->execute();
        $db->next_result();

        $stmt = $db->prepare("
            SELECT category, total_spent, txn_count, avg_txn_amount, pct_of_total, ai_insight
            FROM spend_analytics
            WHERE user_id=? AND month=? AND year=?
            ORDER BY total_spent DESC
        ");
        $stmt->bind_param('iii', $uid, $month, $year);
        $stmt->execute();
        $analytics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Generate AI insights per category
        $insights_map = [
            'food'      => 'Your food spend is high. Cooking at home 3x per week could save ₹2,000/month.',
            'shopping'  => 'You shop most on weekends. Consider a weekly budget cap to avoid impulse buys.',
            'travel'    => 'Your travel pattern suggests 2+ trips/year. A travel credit card could save 15% on forex.',
            'utilities' => 'Utility costs are stable — great! Consider auto-pay to avoid late fees.',
            'unknown'   => 'Some transactions are unrecognised. Review and report if fraudulent.'
        ];
        foreach ($analytics as &$row) {
            $row['ai_insight'] = $insights_map[$row['category']] ?? 'Spending in this category looks normal.';
        }

        // Total for month
        $tot_stmt = $db->prepare("SELECT SUM(total_spent) as total FROM spend_analytics WHERE user_id=? AND month=? AND year=?");
        $tot_stmt->bind_param('iii', $uid, $month, $year);
        $tot_stmt->execute();
        $total = $tot_stmt->get_result()->fetch_assoc()['total'] ?? 0;

        json_response(['success'=>true,'month'=>$month,'year'=>$year,'total_spent'=>$total,'categories'=>$analytics]);
        break;

    case 'spend-trend':
        // Last 6 months aggregated spend
        $stmt = $db->prepare("
            SELECT month, year, SUM(total_spent) as monthly_total, SUM(txn_count) as txn_count
            FROM spend_analytics WHERE user_id=?
            GROUP BY year, month
            ORDER BY year DESC, month DESC
            LIMIT 6
        ");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $trend = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

        json_response(['success'=>true,'trend'=>$trend]);
        break;

    // ==========================================================
    // 3. AI CREDIT SCORE
    //    GET /ai_engine.php?action=credit-score
    // ==========================================================

    case 'credit-score':
        // Recompute via stored procedure
        $proc = $db->prepare("CALL compute_ai_credit_score(?)");
        $proc->bind_param('i', $uid);
        $proc->execute();
        $proc->get_result(); // consume result set
        $db->next_result();

        $stmt = $db->prepare("
            SELECT overall_score, payment_history_pct, credit_utilisation,
                   account_age_score, fraud_penalty, score_label, computed_at
            FROM ai_credit_scores WHERE user_id=?
        ");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $score = $stmt->get_result()->fetch_assoc();

        if (!$score) {
            json_response(['success'=>false,'message'=>'Score not computed yet.'], 404);
        }

        // AI narrative
        $narratives = [
            'excellent' => 'Excellent! You are in the top 5% of NexaBank customers. You qualify for our lowest loan rates.',
            'very_good' => 'Very good score! You qualify for most premium products at preferential rates.',
            'good'      => 'Good score. Small improvements in payment regularity could push you to Very Good.',
            'fair'      => 'Fair score. Reduce credit utilisation and avoid missed payments to improve.',
            'poor'      => 'Your score needs attention. Contact our financial advisor for a recovery plan.'
        ];
        $score['ai_narrative'] = $narratives[$score['score_label']] ?? '';

        json_response(['success'=>true,'score'=>$score]);
        break;

    // ==========================================================
    // 4. NEXA AI CHAT
    //    POST /ai_engine.php?action=chat
    //    GET  /ai_engine.php?action=chat-history&session_id=X
    // ==========================================================

    case 'chat':
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $message = trim($body['message'] ?? '');
        $sess_id = (int)($body['session_id'] ?? 0);

        if (empty($message)) { json_response(['success'=>false,'message'=>'Message cannot be empty.'],400); }

        // Get or create chat session
        if (!$sess_id) {
            $ns = $db->prepare("INSERT INTO chat_sessions(user_id) VALUES(?)");
            $ns->bind_param('i',$uid); $ns->execute();
            $sess_id = $db->insert_id;
        }

        // Save user message
        $ins = $db->prepare("INSERT INTO chat_messages(session_id,sender,message_text,intent) VALUES(?,'user',?,?)");

        // ── Nexa AI: Rule-based NLP Intent Engine ──
        $msg_lower = strtolower($message);
        $intent    = 'general';
        $reply     = '';

        // Fetch user context
        $ctx = $db->prepare("
            SELECT a.balance, a.account_type, a.account_number,
                   cs.overall_score, cs.score_label
            FROM accounts a
            LEFT JOIN ai_credit_scores cs ON cs.user_id=a.user_id
            WHERE a.user_id=? AND a.account_type='savings' LIMIT 1
        ");
        $ctx->bind_param('i',$uid); $ctx->execute();
        $ctx_data = $ctx->get_result()->fetch_assoc();
        $balance  = number_format((float)($ctx_data['balance'] ?? 0), 2);
        $score    = $ctx_data['overall_score'] ?? 'N/A';

        // Intent matching
        if (str_contains($msg_lower,'balance') || str_contains($msg_lower,'account')) {
            $intent = 'balance_inquiry';
            $reply  = "Your current savings account balance is **₹{$balance}**. Your account is active and in good standing. Is there anything else you'd like to check?";

        } elseif (str_contains($msg_lower,'fraud') || str_contains($msg_lower,'suspicious') || str_contains($msg_lower,'alert')) {
            $intent = 'fraud_inquiry';
            $fa_count = $db->prepare("SELECT COUNT(*) as c FROM fraud_alerts WHERE user_id=? AND is_resolved=0");
            $fa_count->bind_param('i',$uid); $fa_count->execute();
            $cnt = $fa_count->get_result()->fetch_assoc()['c'] ?? 0;
            $reply = $cnt > 0
                ? "🚨 You have **{$cnt} unresolved fraud alert(s)**. The most recent involves an international transaction. I recommend reviewing and resolving these immediately from the Fraud Center."
                : "✅ No active fraud alerts on your account. Our AI is monitoring all transactions 24/7.";

        } elseif (str_contains($msg_lower,'credit score') || str_contains($msg_lower,'cibil')) {
            $intent = 'credit_score';
            $lbl    = ucfirst(str_replace('_',' ', $ctx_data['score_label'] ?? 'good'));
            $reply  = "Your NexaBank AI Credit Score is **{$score}/900** — rated **{$lbl}**. This is computed from your payment history, credit utilisation, account age, and transaction behaviour. Would you like tips to improve it?";

        } elseif (str_contains($msg_lower,'loan') || str_contains($msg_lower,'emi')) {
            $intent = 'loan_inquiry';
            $reply  = "Based on your credit score of **{$score}**, here are your pre-approved loan options:\n\n• **Personal Loan**: Up to ₹5,00,000 @ 10.2% p.a.\n• **Home Loan Top-Up**: Up to ₹15,00,000 @ 8.5% p.a.\n• **Car Loan**: Up to ₹8,00,000 @ 9.0% p.a.\n\nAll with instant approval. Would you like me to calculate your EMI?";

        } elseif (str_contains($msg_lower,'emi') || str_contains($msg_lower,'calculat')) {
            $intent = 'emi_calculator';
            $reply  = "I can help with EMI calculation! A ₹5,00,000 personal loan at 10.2% for 36 months = **₹16,128/month**. For 60 months = **₹10,668/month**. Tell me your desired loan amount and tenure for a precise calculation.";

        } elseif (str_contains($msg_lower,'spend') || str_contains($msg_lower,'spending') || str_contains($msg_lower,'analytics')) {
            $intent = 'spend_inquiry';
            $reply  = "Your top spending categories this month:\n1. 🛒 Shopping — 40% of total spend\n2. 🍔 Food & Dining — 22%\n3. ⚡ Utilities — 23%\n4. ✈️ Travel — 15%\n\nAI Tip: Your food delivery spend is 34% above your average. Weekly grocery orders could save ~₹2,100/month.";

        } elseif (str_contains($msg_lower,'fd') || str_contains($msg_lower,'fixed deposit')) {
            $intent = 'fd_inquiry';
            $reply  = "NexaBank FD rates as of today:\n• 7 days – 3 months: **4.5%** p.a.\n• 3–6 months: **5.75%** p.a.\n• 6–12 months: **6.5%** p.a.\n• 1–2 years: **7.25%** p.a.\n• 2–5 years: **7.5%** p.a.\n\n⭐ **Premium Member Exclusive: 8.1%** for 12-month FD. Want me to book one for you?";

        } elseif (str_contains($msg_lower,'transfer') || str_contains($msg_lower,'send money') || str_contains($msg_lower,'upi')) {
            $intent = 'transfer';
            $reply  = "To transfer funds, go to **Quick Actions → Send Money** on your dashboard. You can transfer via:\n• UPI (instant, free)\n• NEFT (2–4 hours)\n• RTGS (same day, above ₹2 lakh)\n\nAll transfers are AI-monitored for security in real time.";

        } elseif (str_contains($msg_lower,'hi') || str_contains($msg_lower,'hello') || str_contains($msg_lower,'hey')) {
            $intent = 'greeting';
            $name   = $user['first_name'];
            $reply  = "Hello {$name}! 👋 I'm Nexa, your AI banking assistant. I can help you with:\n• Account balance & transactions\n• Fraud alerts & security\n• Loan eligibility & EMI\n• Spend analytics & insights\n• FD rates & investments\n\nWhat would you like to know today?";

        } elseif (str_contains($msg_lower,'thank')) {
            $intent = 'thanks';
            $reply  = "You're very welcome, {$user['first_name']}! 😊 Is there anything else I can help you with? I'm here 24/7.";

        } else {
            $intent = 'general';
            $reply  = "I understand you're asking about \"{$message}\". Let me check your account data... For detailed assistance on this topic, I recommend visiting the relevant section in your dashboard, or I can connect you with a human banking advisor. Would you like me to do that?";
        }

        // Save user message
        $ins->bind_param('iss', $sess_id, $message, $intent);
        $ins->execute();

        // Save AI reply
        $ai_ins = $db->prepare("INSERT INTO chat_messages(session_id,sender,message_text,intent) VALUES(?,'ai',?,?)");
        $ai_ins->bind_param('iss', $sess_id, $reply, $intent);
        $ai_ins->execute();

        json_response([
            'success'    => true,
            'session_id' => $sess_id,
            'intent'     => $intent,
            'reply'      => $reply
        ]);
        break;

    case 'chat-history':
        $sess_id = (int)($_GET['session_id'] ?? 0);
        if (!$sess_id) { json_response(['success'=>false,'message'=>'session_id required'],400); }

        $stmt = $db->prepare("
            SELECT sender, message_text, intent, sent_at
            FROM chat_messages WHERE session_id=?
            ORDER BY sent_at ASC
        ");
        $stmt->bind_param('i',$sess_id); $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        json_response(['success'=>true,'messages'=>$messages]);
        break;

    // ==========================================================
    // 5. SMART OFFERS
    //    GET /ai_engine.php?action=offers
    //    POST /ai_engine.php?action=generate-offers  (AI engine)
    //    POST /ai_engine.php?action=accept-offer
    // ==========================================================

    case 'offers':
        $stmt = $db->prepare("
            SELECT offer_id,offer_type,offer_title,offer_desc,offer_value,ai_match_pct,valid_until,created_at
            FROM smart_offers
            WHERE user_id=? AND is_dismissed=0 AND (valid_until IS NULL OR valid_until >= CURDATE())
            ORDER BY ai_match_pct DESC
        ");
        $stmt->bind_param('i',$uid); $stmt->execute();
        $offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        json_response(['success'=>true,'offers'=>$offers,'count'=>count($offers)]);
        break;

    case 'generate-offers':
        // AI: Analyse user profile and generate personalised offers
        // Fetch user context
        $profile = $db->prepare("
            SELECT cs.overall_score, cs.score_label, cs.credit_utilisation,
                   SUM(sa.total_spent) as monthly_spend,
                   COUNT(fa.alert_id) as fraud_alerts
            FROM users u
            LEFT JOIN ai_credit_scores cs ON cs.user_id=u.user_id
            LEFT JOIN spend_analytics sa   ON sa.user_id=u.user_id AND sa.month=MONTH(NOW()) AND sa.year=YEAR(NOW())
            LEFT JOIN fraud_alerts fa      ON fa.user_id=u.user_id AND fa.is_resolved=0
            WHERE u.user_id=?
            GROUP BY cs.overall_score,cs.score_label,cs.credit_utilisation
        ");
        $profile->bind_param('i',$uid); $profile->execute();
        $p = $profile->get_result()->fetch_assoc();

        $score  = (int)($p['overall_score'] ?? 700);
        $spend  = (float)($p['monthly_spend'] ?? 0);
        $alerts = (int)($p['fraud_alerts'] ?? 0);

        // Clear old undismissed offers
        $del = $db->prepare("DELETE FROM smart_offers WHERE user_id=? AND is_accepted=0 AND is_dismissed=0");
        $del->bind_param('i',$uid); $del->execute();

        $new_offers = [];

        // Offer 1: Personal loan if score >= 700
        if ($score >= 700) {
            $rate  = $score >= 800 ? '10.2' : ($score >= 750 ? '11.5' : '13.0');
            $match = min(99, round(($score - 700) / 2 + 70));
            $new_offers[] = [$uid,'personal_loan','Pre-Approved Personal Loan',
                "Based on your credit score of {$score}, you qualify for instant disbursal. Zero processing fee.",
                "Rs.5L @ {$rate}% p.a.", $match, date('Y-m-d', strtotime('+30 days'))];
        }

        // Offer 2: FD if balance is high
        $bal_stmt = $db->prepare("SELECT SUM(balance) as total FROM accounts WHERE user_id=? AND account_type='savings'");
        $bal_stmt->bind_param('i',$uid); $bal_stmt->execute();
        $bal = (float)($bal_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        if ($bal > 50000) {
            $new_offers[] = [$uid,'fd','Exclusive FD Rate — Premium Member',
                'Lock in our highest rate for 12 months. Limited availability for Premium customers only.',
                '8.1% p.a.', 88, date('Y-m-d', strtotime('+15 days'))];
        }

        // Offer 3: Travel card if travel spend exists
        $travel = $db->prepare("SELECT total_spent FROM spend_analytics WHERE user_id=? AND category='travel' AND year=YEAR(NOW()) ORDER BY month DESC LIMIT 1");
        $travel->bind_param('i',$uid); $travel->execute();
        $trav_spend = (float)($travel->get_result()->fetch_assoc()['total_spent'] ?? 0);
        if ($trav_spend > 2000) {
            $new_offers[] = [$uid,'credit_card','Zero Forex Travel Card',
                "AI detected travel spending of ₹" . number_format($trav_spend) . " recently. Save on every international trip.",
                'Zero Forex + 3% Cashback', 92, date('Y-m-d', strtotime('+20 days'))];
        }

        // Offer 4: Cashback card always (based on spend)
        if ($spend > 10000) {
            $new_offers[] = [$uid,'credit_card','AI Cashback Credit Card',
                "5% cashback on UPI payments — matched from your spend analytics showing ₹" . number_format($spend) . " monthly spend.",
                '5% on UPI', 91, date('Y-m-d', strtotime('+20 days'))];
        }

        // Insert new offers
        $ins = $db->prepare("INSERT INTO smart_offers(user_id,offer_type,offer_title,offer_desc,offer_value,ai_match_pct,valid_until) VALUES(?,?,?,?,?,?,?)");
        foreach ($new_offers as $o) {
            $ins->bind_param('issssds', ...$o);
            $ins->execute();
        }

        json_response(['success'=>true,'message'=>count($new_offers).' personalised offers generated.','count'=>count($new_offers)]);
        break;

    case 'accept-offer':
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $offer_id = (int)($body['offer_id'] ?? 0);

        $stmt = $db->prepare("UPDATE smart_offers SET is_accepted=1 WHERE offer_id=? AND user_id=?");
        $stmt->bind_param('ii',$offer_id,$uid); $stmt->execute();

        json_response(['success'=>true,'message'=>'Offer accepted! Our team will contact you within 24 hours.']);
        break;

    case 'dismiss-offer':
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $offer_id = (int)($body['offer_id'] ?? 0);

        $stmt = $db->prepare("UPDATE smart_offers SET is_dismissed=1 WHERE offer_id=? AND user_id=?");
        $stmt->bind_param('ii',$offer_id,$uid); $stmt->execute();

        json_response(['success'=>true,'message'=>'Offer dismissed.']);
        break;

    // ==========================================================
    // DASHBOARD DATA (combined endpoint for frontend)
    // ==========================================================
    case 'dashboard':
        // Accounts
        $acc = $db->prepare("SELECT account_id,account_number,account_type,balance,credit_limit,interest_rate FROM accounts WHERE user_id=? AND is_active=1");
        $acc->bind_param('i',$uid); $acc->execute();
        $accounts = $acc->get_result()->fetch_all(MYSQLI_ASSOC);

        // Recent transactions
        $txns_stmt = $db->prepare("
            SELECT t.txn_id,t.txn_type,t.amount,t.merchant_name,t.category,t.status,t.balance_after,t.txn_date,t.reference_no
            FROM transactions t JOIN accounts a ON t.account_id=a.account_id
            WHERE a.user_id=? ORDER BY t.txn_date DESC LIMIT 10
        ");
        $txns_stmt->bind_param('i',$uid); $txns_stmt->execute();
        $transactions = $txns_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Credit score
        $sc = $db->prepare("SELECT overall_score,score_label,payment_history_pct,credit_utilisation FROM ai_credit_scores WHERE user_id=?");
        $sc->bind_param('i',$uid); $sc->execute();
        $credit = $sc->get_result()->fetch_assoc();

        // Fraud alerts count
        $fa = $db->prepare("SELECT COUNT(*) as c FROM fraud_alerts WHERE user_id=? AND is_resolved=0");
        $fa->bind_param('i',$uid); $fa->execute();
        $fraud_count = $fa->get_result()->fetch_assoc()['c'] ?? 0;

        // Offers count
        $of = $db->prepare("SELECT COUNT(*) as c FROM smart_offers WHERE user_id=? AND is_dismissed=0 AND is_accepted=0");
        $of->bind_param('i',$uid); $of->execute();
        $offers_count = $of->get_result()->fetch_assoc()['c'] ?? 0;

        json_response([
            'success'      => true,
            'user'         => $user,
            'accounts'     => $accounts,
            'transactions' => $transactions,
            'credit_score' => $credit,
            'fraud_alerts' => $fraud_count,
            'offers'       => $offers_count
        ]);
        break;

    default:
        json_response(['success'=>false,'message'=>'Invalid AI action.'],404);
}
