-- ============================================================
--  NexaBank India — Complete Database
--  6 Users: Sakshi Jaswal, Gurpreet Jassal, Rohit Kumar,
--           Priya Sharma, Arjun Mehta, Sneha Iyer
--  Import via phpMyAdmin or: mysql -u root -p < nexabank_db.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS nexabank_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nexabank_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_sessions;
DROP TABLE IF EXISTS smart_offers;
DROP TABLE IF EXISTS spend_analytics;
DROP TABLE IF EXISTS fraud_alerts;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS ai_credit_scores;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS loan_applications;
DROP TABLE IF EXISTS insurance_policies;
DROP TABLE IF EXISTS investments;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    first_name     VARCHAR(60)  NOT NULL,
    last_name      VARCHAR(60)  NOT NULL,
    email          VARCHAR(120) NOT NULL UNIQUE,
    mobile         VARCHAR(15)  NOT NULL UNIQUE,
    pan_number     VARCHAR(10)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    customer_id    VARCHAR(12)  NOT NULL UNIQUE,
    city           VARCHAR(60)  DEFAULT 'India',
    branch         VARCHAR(80)  DEFAULT 'Main Branch',
    ifsc_code      VARCHAR(15)  DEFAULT 'NEXA0000001',
    customer_type  VARCHAR(30)  DEFAULT 'Standard Member',
    joined_date    DATE         DEFAULT (CURRENT_DATE),
    is_verified    TINYINT(1)   DEFAULT 1,
    is_active      TINYINT(1)   DEFAULT 1,
    otp_code       VARCHAR(6)   DEFAULT NULL,
    otp_expires_at DATETIME     DEFAULT NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    last_login     DATETIME     DEFAULT NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: accounts
-- ============================================================
CREATE TABLE accounts (
    account_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    account_number VARCHAR(20)   NOT NULL UNIQUE,
    account_type   ENUM('savings','current','fd','credit') NOT NULL DEFAULT 'savings',
    balance        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    credit_limit   DECIMAL(15,2) DEFAULT NULL,
    interest_rate  DECIMAL(5,2)  DEFAULT 7.00,
    is_active      TINYINT(1)    DEFAULT 1,
    opened_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    maturity_date  DATE          DEFAULT NULL,
    fd_rate        VARCHAR(10)   DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: transactions
-- ============================================================
CREATE TABLE transactions (
    txn_id        INT AUTO_INCREMENT PRIMARY KEY,
    account_id    INT           NOT NULL,
    txn_type      ENUM('debit','credit') NOT NULL,
    amount        DECIMAL(15,2) NOT NULL,
    merchant_name VARCHAR(120)  DEFAULT NULL,
    category      VARCHAR(60)   DEFAULT 'uncategorised',
    description   TEXT          DEFAULT NULL,
    location      VARCHAR(120)  DEFAULT NULL,
    status        ENUM('pending','success','failed','flagged') DEFAULT 'success',
    balance_after DECIMAL(15,2) NOT NULL,
    txn_date      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    reference_no  VARCHAR(30)   NOT NULL UNIQUE,
    FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
    INDEX idx_account(account_id), INDEX idx_date(txn_date)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: fraud_alerts
-- ============================================================
CREATE TABLE fraud_alerts (
    alert_id      INT AUTO_INCREMENT PRIMARY KEY,
    txn_id        INT           NOT NULL,
    user_id       INT           NOT NULL,
    risk_level    ENUM('low','medium','high') NOT NULL,
    alert_type    VARCHAR(100)  NOT NULL,
    description   TEXT          NOT NULL,
    ai_confidence DECIMAL(5,2)  DEFAULT NULL,
    is_resolved   TINYINT(1)    DEFAULT 0,
    resolved_at   DATETIME      DEFAULT NULL,
    action_taken  VARCHAR(100)  DEFAULT NULL,
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (txn_id)  REFERENCES transactions(txn_id)  ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: ai_credit_scores
-- ============================================================
CREATE TABLE ai_credit_scores (
    score_id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT           NOT NULL UNIQUE,
    overall_score       SMALLINT      NOT NULL DEFAULT 700,
    payment_history_pct DECIMAL(5,2)  DEFAULT 98.00,
    credit_utilisation  DECIMAL(5,2)  DEFAULT 24.00,
    account_age_score   DECIMAL(5,2)  DEFAULT 82.00,
    credit_mix          DECIMAL(5,2)  DEFAULT 75.00,
    fraud_penalty       DECIMAL(5,2)  DEFAULT 0.00,
    score_label         ENUM('poor','fair','good','very_good','excellent') DEFAULT 'good',
    computed_at         DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: spend_analytics
-- ============================================================
CREATE TABLE spend_analytics (
    analytics_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    month          TINYINT       NOT NULL,
    year           SMALLINT      NOT NULL,
    category       VARCHAR(60)   NOT NULL,
    total_spent    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    txn_count      INT           NOT NULL DEFAULT 0,
    avg_txn_amount DECIMAL(15,2) DEFAULT NULL,
    pct_of_total   DECIMAL(5,2)  DEFAULT NULL,
    ai_insight     TEXT          DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_month_cat(user_id, month, year, category)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: loans
-- ============================================================
CREATE TABLE loans (
    loan_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    loan_type      VARCHAR(60)   NOT NULL,
    loan_amount    DECIMAL(15,2) NOT NULL,
    outstanding    DECIMAL(15,2) NOT NULL,
    emi_amount     DECIMAL(15,2) NOT NULL,
    interest_rate  DECIMAL(5,2)  NOT NULL,
    tenure_months  INT           NOT NULL,
    remaining_months INT         NOT NULL,
    status         ENUM('active','closed','overdue') DEFAULT 'active',
    start_date     DATE          NOT NULL,
    next_emi_date  DATE          DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: loan_applications
-- ============================================================
CREATE TABLE loan_applications (
    app_id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    loan_type      VARCHAR(60)   NOT NULL,
    amount         DECIMAL(15,2) NOT NULL,
    purpose        TEXT          DEFAULT NULL,
    status         ENUM('submitted','documents_verified','credit_check','approved','disbursed','rejected') DEFAULT 'submitted',
    app_date       DATE          DEFAULT (CURRENT_DATE),
    reference_no   VARCHAR(20)   NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: insurance_policies
-- ============================================================
CREATE TABLE insurance_policies (
    policy_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    policy_type    VARCHAR(60)   NOT NULL,
    provider       VARCHAR(80)   NOT NULL,
    policy_number  VARCHAR(30)   NOT NULL UNIQUE,
    coverage       VARCHAR(80)   NOT NULL,
    premium        VARCHAR(40)   NOT NULL,
    due_date       DATE          DEFAULT NULL,
    status         ENUM('active','renewal_due','expired','claimed') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: investments
-- ============================================================
CREATE TABLE investments (
    inv_id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    inv_type       VARCHAR(60)   NOT NULL,
    inv_name       VARCHAR(120)  NOT NULL,
    invested_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    current_value  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    returns_pct    DECIMAL(5,2)  DEFAULT 0.00,
    detail_text    VARCHAR(200)  DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: smart_offers
-- ============================================================
CREATE TABLE smart_offers (
    offer_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL,
    offer_type   VARCHAR(60)  NOT NULL,
    offer_title  VARCHAR(150) NOT NULL,
    offer_desc   TEXT         NOT NULL,
    offer_value  VARCHAR(80)  NOT NULL,
    ai_match_pct DECIMAL(5,2) DEFAULT NULL,
    is_accepted  TINYINT(1)   DEFAULT 0,
    is_dismissed TINYINT(1)   DEFAULT 0,
    valid_until  DATE         DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: user_sessions
-- ============================================================
CREATE TABLE user_sessions (
    session_token VARCHAR(128) PRIMARY KEY,
    user_id       INT          NOT NULL,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    user_agent    TEXT         DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    expires_at    DATETIME     NOT NULL,
    is_valid      TINYINT(1)   DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: chat_sessions + chat_messages
-- ============================================================
CREATE TABLE chat_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT      NOT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active  TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE chat_messages (
    message_id   INT AUTO_INCREMENT PRIMARY KEY,
    session_id   INT  NOT NULL,
    sender       ENUM('user','ai') NOT NULL,
    message_text TEXT NOT NULL,
    intent       VARCHAR(80) DEFAULT NULL,
    sent_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(session_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STORED PROCEDURE: compute_ai_credit_score
-- ============================================================
DELIMITER $$
CREATE PROCEDURE compute_ai_credit_score(IN p_user_id INT)
BEGIN
    DECLARE v_total_txns   INT DEFAULT 0;
    DECLARE v_flagged_txns INT DEFAULT 0;
    DECLARE v_credit_used  DECIMAL(15,2) DEFAULT 0;
    DECLARE v_credit_limit DECIMAL(15,2) DEFAULT 1;
    DECLARE v_utilisation  DECIMAL(5,2)  DEFAULT 0;
    DECLARE v_pay_score    DECIMAL(5,2)  DEFAULT 95;
    DECLARE v_fraud_pen    DECIMAL(5,2)  DEFAULT 0;
    DECLARE v_final_score  SMALLINT      DEFAULT 700;
    DECLARE v_label        VARCHAR(20)   DEFAULT 'good';

    SELECT COUNT(*), SUM(IF(status='flagged',1,0))
    INTO v_total_txns, v_flagged_txns
    FROM transactions t JOIN accounts a ON t.account_id=a.account_id
    WHERE a.user_id=p_user_id;

    IF v_total_txns > 0 THEN
        SET v_pay_score = ((v_total_txns - IFNULL(v_flagged_txns,0)) / v_total_txns) * 100;
    END IF;

    SELECT IFNULL(ABS(SUM(balance)),0), IFNULL(SUM(credit_limit),1)
    INTO v_credit_used, v_credit_limit
    FROM accounts WHERE user_id=p_user_id AND account_type='credit';

    SET v_utilisation = LEAST((v_credit_used / v_credit_limit)*100, 100);
    SELECT IFNULL(COUNT(*)*15,0) INTO v_fraud_pen
    FROM fraud_alerts WHERE user_id=p_user_id AND risk_level='high' AND is_resolved=0;

    SET v_final_score = LEAST(900, GREATEST(300,
        300 + ROUND(v_pay_score * 3.5) + ROUND((100 - v_utilisation) * 1.5) - v_fraud_pen
    ));

    SET v_label = CASE
        WHEN v_final_score >= 800 THEN 'excellent'
        WHEN v_final_score >= 750 THEN 'very_good'
        WHEN v_final_score >= 700 THEN 'good'
        WHEN v_final_score >= 600 THEN 'fair'
        ELSE 'poor' END;

    INSERT INTO ai_credit_scores(user_id,overall_score,payment_history_pct,credit_utilisation,fraud_penalty,score_label,computed_at)
    VALUES (p_user_id,v_final_score,v_pay_score,v_utilisation,v_fraud_pen,v_label,NOW())
    ON DUPLICATE KEY UPDATE
        overall_score=v_final_score, payment_history_pct=v_pay_score,
        credit_utilisation=v_utilisation, fraud_penalty=v_fraud_pen,
        score_label=v_label, computed_at=NOW();
END$$

-- ============================================================
-- STORED PROCEDURE: compute_spend_analytics
-- ============================================================
CREATE PROCEDURE compute_spend_analytics(IN p_user_id INT, IN p_month INT, IN p_year INT)
BEGIN
    DECLARE v_total DECIMAL(15,2) DEFAULT 0;
    SELECT IFNULL(SUM(amount),0) INTO v_total
    FROM transactions t JOIN accounts a ON t.account_id=a.account_id
    WHERE a.user_id=p_user_id AND txn_type='debit'
      AND MONTH(txn_date)=p_month AND YEAR(txn_date)=p_year;

    INSERT INTO spend_analytics(user_id,month,year,category,total_spent,txn_count,avg_txn_amount,pct_of_total)
    SELECT a.user_id, MONTH(t.txn_date), YEAR(t.txn_date), t.category,
           SUM(t.amount), COUNT(*), AVG(t.amount),
           IF(v_total>0, ROUND(SUM(t.amount)/v_total*100,2),0)
    FROM transactions t JOIN accounts a ON t.account_id=a.account_id
    WHERE a.user_id=p_user_id AND t.txn_type='debit'
      AND MONTH(t.txn_date)=p_month AND YEAR(t.txn_date)=p_year
    GROUP BY t.category
    ON DUPLICATE KEY UPDATE
        total_spent=VALUES(total_spent), txn_count=VALUES(txn_count),
        avg_txn_amount=VALUES(avg_txn_amount), pct_of_total=VALUES(pct_of_total);
END$$
DELIMITER ;

-- ============================================================
-- USERS (passwords are bcrypt hashed — plaintext shown in comments)
-- ============================================================
-- User 1: Sakshi Jaswal         | ID: NXB20240001 | Pass: sakshi@123
-- User 2: Gurpreet Jassal       | ID: NXB20240002 | Pass: gurpreet@456
-- User 3: Rohit Kumar           | ID: NXB20240003 | Pass: rohit@789
-- User 4: Priya Sharma          | ID: NXB20240004 | Pass: priya@321
-- User 5: Arjun Mehta           | ID: NXB20240005 | Pass: arjun@654
-- User 6: Sneha Iyer            | ID: NXB20240006 | Pass: sneha@987
-- NOTE: password_hash values below are bcrypt of the above passwords
INSERT INTO users (first_name,last_name,email,mobile,pan_number,password_hash,customer_id,city,branch,ifsc_code,customer_type,joined_date,is_verified) VALUES
('Sakshi','Jaswal','sakshi@nexabank.in','9876500001','ABCDE1111F','$2y$12$fdihJg38sFKJCEF3tp6F/OhrAgSCexGAJhaoApRzqNe6hfBVaOaee','NXB20240001','Bengaluru','Bengaluru Main','NEXA0001234','Premium Member','2022-01-15',1),
('Gurpreet','Jassal','gurpreet@nexabank.in','9876500002','BCDEF2222G','$2y$12$XvE5AxjV..1Wl6IiwtY99ecrNhJuqwuyKqi/sg0s8GtbpdZBfj1vm','NXB20240002','Ludhiana','Ludhiana Central','NEXA0003456','Gold Member','2021-03-20',1),
('Rohit','Kumar','rohit@nexabank.in','9876500003','CDEFG3333H','$2y$12$I8O3vhWWjeSWuuPfVkb8COWmhGB4Efw/b0YaAXNj02ysulO17kCNK','NXB20240003','Bengaluru','Whitefield Branch','NEXA0002345','Standard Member','2023-08-10',1),
('Priya','Sharma','priya@nexabank.in','9876500004','DEFGH4444I','$2y$12$bB8tPCBM1iMEzy1I24QzSOzHC7f4xVLRUP3jH6WncyynnsvzWeDAO','NXB20240004','Delhi','Connaught Place','NEXA0004567','Premium Member','2020-11-05',1),
('Arjun','Mehta','arjun@nexabank.in','9876500005','EFGHI5555J','$2y$12$djau7n3OuqMwlvTFBbxUoOhN4iS16xjE9mMOxSTQX0Ck/AEUmyB1K','NXB20240005','Mumbai','Bandra Kurla Complex','NEXA0005678','Business Elite','2019-06-12',1),
('Sneha','Iyer','sneha@nexabank.in','9876500006','FGHIJ6666K','$2y$12$T05WZJVq3nUKAQFG6wgif.M.p9A87WLJ.eFddiqPBSi8ES6W2.GIS','NXB20240006','Chennai','Anna Nagar','NEXA0006789','Standard Member','2024-02-28',1);

-- ============================================================
-- ACCOUNTS
-- ============================================================
-- Sakshi Jaswal (user_id=1)
INSERT INTO accounts (user_id,account_number,account_type,balance,credit_limit,interest_rate) VALUES
(1,'4001200034567291','savings',482310.00,NULL,7.00),
(1,'4001200034514512','credit',-12840.00,200000.00,0.00),
(1,'FD2024008472345','fd',108100.00,NULL,8.10);
UPDATE accounts SET maturity_date='2026-06-15', fd_rate='8.1%' WHERE account_number='FD2024008472345';

-- Gurpreet Jassal (user_id=2)
INSERT INTO accounts (user_id,account_number,account_type,balance,credit_limit,interest_rate) VALUES
(2,'4001200088214433','savings',127580.00,NULL,7.00),
(2,'4001200088219900','credit',-45200.00,150000.00,0.00),
(2,'FD2023004123456','fd',205000.00,NULL,7.25);
UPDATE accounts SET maturity_date='2026-09-20', fd_rate='7.25%' WHERE account_number='FD2023004123456';

-- Rohit Kumar (user_id=3)
INSERT INTO accounts (user_id,account_number,account_type,balance,credit_limit,interest_rate) VALUES
(3,'4001200055123310','savings',89200.00,NULL,7.00),
(3,'4001200055127781','credit',-28400.00,100000.00,0.00),
(3,'FD2024011023456','fd',50000.00,NULL,7.50);
UPDATE accounts SET maturity_date='2026-08-10', fd_rate='7.5%' WHERE account_number='FD2024011023456';

-- Priya Sharma (user_id=4)
INSERT INTO accounts (user_id,account_number,account_type,balance,credit_limit,interest_rate) VALUES
(4,'4001200077345621','savings',312450.00,NULL,7.00),
(4,'4001200077340012','credit',0.00,300000.00,0.00),
(4,'FD2022002312345','fd',500000.00,NULL,8.10);
UPDATE accounts SET maturity_date='2027-11-05', fd_rate='8.1%' WHERE account_number='FD2022002312345';

-- Arjun Mehta (user_id=5)
INSERT INTO accounts (user_id,account_number,account_type,balance,credit_limit,interest_rate) VALUES
(5,'4001200099117823','savings',884200.00,NULL,7.00),
(5,'4001200099113344','credit',-185000.00,500000.00,0.00),
(5,'FD2021000882345','fd',1200000.00,NULL,8.10);
UPDATE accounts SET maturity_date='2027-06-12', fd_rate='8.1%' WHERE account_number='FD2021000882345';

-- Sneha Iyer (user_id=6)
INSERT INTO accounts (user_id,account_number,account_type,balance,credit_limit,interest_rate) VALUES
(6,'4001200033029988','savings',45800.00,NULL,7.00),
(6,'4001200033025566','credit',-8200.00,75000.00,0.00);

-- ============================================================
-- TRANSACTIONS — Sakshi Jaswal (account_id=1 savings, 2 credit)
-- ============================================================
INSERT INTO transactions (account_id,txn_type,amount,merchant_name,category,location,status,balance_after,txn_date,reference_no) VALUES
(1,'credit',68000.00,'Employer NEFT','income','Bengaluru, IN','success',550310.00,'2025-05-01 09:00:00','NXB2025050101'),
(1,'debit',2340.00,'Amazon India','shopping','Bengaluru, IN','success',547970.00,'2025-05-03 11:05:00','NXB2025050301'),
(1,'debit',850.00,'Swiggy','food','Bengaluru, IN','success',547120.00,'2025-05-03 13:22:00','NXB2025050302'),
(1,'debit',12400.00,'Unknown Merchant','unknown','Singapore, SG','flagged',534720.00,'2025-05-02 02:47:00','NXB2025050203'),
(1,'debit',1840.00,'BESCOM Electricity','utilities','Bengaluru, IN','success',532880.00,'2025-04-30 10:15:00','NXB2025043001'),
(1,'debit',3200.00,'Apollo Pharmacy','healthcare','Bengaluru, IN','success',529680.00,'2025-04-29 16:30:00','NXB2025042901'),
(1,'debit',640.00,'BookMyShow','entertainment','Bengaluru, IN','success',529040.00,'2025-04-28 19:12:00','NXB2025042801'),
(1,'debit',2800.00,'HP Petrol Pump','fuel','Bengaluru, IN','success',526240.00,'2025-04-27 08:45:00','NXB2025042701');

INSERT INTO fraud_alerts (txn_id,user_id,risk_level,alert_type,description,ai_confidence) VALUES
(4,1,'high','geo_anomaly','Transaction from Singapore at 02:47 AM — outside your regular geographic zone. Unknown merchant. AI confidence: 85%.',85.00),
(3,1,'medium','night_pattern','Multiple login attempts from unrecognised Chrome browser. Possible credential stuffing attempt.',35.00);

-- ============================================================
-- TRANSACTIONS — Gurpreet Jassal (account_id=4 savings, 5 credit)
-- ============================================================
INSERT INTO transactions (account_id,txn_type,amount,merchant_name,category,location,status,balance_after,txn_date,reference_no) VALUES
(4,'credit',95000.00,'Employer NEFT','income','Ludhiana, IN','success',222580.00,'2025-05-01 09:00:00','NXB2025050104'),
(4,'debit',41000.00,'Croma Electronics','electronics','Ludhiana, IN','flagged',181580.00,'2025-05-02 15:00:00','NXB2025050204'),
(4,'debit',3200.00,'HPCL Fuel','fuel','Ludhiana, IN','success',178380.00,'2025-05-03 08:00:00','NXB2025050304'),
(4,'debit',1400.00,'Zomato','food','Ludhiana, IN','success',176980.00,'2025-05-03 19:45:00','NXB2025050305'),
(4,'debit',2800.00,'Punjab Electricity','utilities','Ludhiana, IN','success',174180.00,'2025-04-28 10:00:00','NXB2025042804'),
(4,'debit',5000.00,'Education SIP','investment','Ludhiana, IN','success',169180.00,'2025-04-25 12:00:00','NXB2025042504');

INSERT INTO fraud_alerts (txn_id,user_id,risk_level,alert_type,description,ai_confidence) VALUES
(10,2,'medium','high_value','High-value electronics purchase of ₹41,000 at Croma — amount is 8× your average transaction. Please verify.',42.00);

-- ============================================================
-- TRANSACTIONS — Rohit Kumar (account_id=7 savings)
-- ============================================================
INSERT INTO transactions (account_id,txn_type,amount,merchant_name,category,location,status,balance_after,txn_date,reference_no) VALUES
(7,'credit',120000.00,'Employer NEFT','income','Bengaluru, IN','success',209200.00,'2025-05-01 09:00:00','NXB2025050107'),
(7,'debit',35000.00,'Rent Transfer','housing','Bengaluru, IN','success',174200.00,'2025-05-01 10:00:00','NXB2025050107A'),
(7,'debit',4200.00,'Flipkart','shopping','Bengaluru, IN','success',170000.00,'2025-05-02 14:30:00','NXB2025050207'),
(7,'debit',1200.00,'Swiggy','food','Bengaluru, IN','success',168800.00,'2025-05-03 20:00:00','NXB2025050307'),
(7,'debit',3000.00,'Indian Oil','fuel','Bengaluru, IN','success',165800.00,'2025-05-04 09:00:00','NXB2025050407');

-- ============================================================
-- TRANSACTIONS — Priya Sharma (account_id=10 savings)
-- ============================================================
INSERT INTO transactions (account_id,txn_type,amount,merchant_name,category,location,status,balance_after,txn_date,reference_no) VALUES
(10,'credit',85000.00,'Employer NEFT','income','Delhi, IN','success',397450.00,'2025-05-01 09:00:00','NXB2025050110'),
(10,'debit',8500.00,'Myntra','shopping','Delhi, IN','success',388950.00,'2025-05-02 11:00:00','NXB2025050210'),
(10,'debit',22000.00,'MakeMyTrip','travel','Delhi, IN','success',366950.00,'2025-05-02 15:00:00','NXB2025050210A'),
(10,'debit',2100.00,'Zomato Gold','food','Delhi, IN','success',364850.00,'2025-05-03 19:30:00','NXB2025050310'),
(10,'debit',3499.00,'Udemy Course','education','Delhi, IN','success',361351.00,'2025-05-04 14:00:00','NXB2025050410');

-- ============================================================
-- TRANSACTIONS — Arjun Mehta (account_id=13 savings)
-- ============================================================
INSERT INTO transactions (account_id,txn_type,amount,merchant_name,category,location,status,balance_after,txn_date,reference_no) VALUES
(13,'credit',280000.00,'Business Dividend','income','Mumbai, IN','success',1164200.00,'2025-05-01 10:00:00','NXB2025050113'),
(13,'debit',85000.00,'Office Rent','business','Mumbai, IN','success',1079200.00,'2025-05-01 11:00:00','NXB2025050113A'),
(13,'debit',95000.00,'Dubai Business Trip','travel','Dubai, AE','flagged',984200.00,'2025-04-30 08:00:00','NXB2025043013'),
(13,'debit',12500.00,'Client Dinner','food','Mumbai, IN','success',971700.00,'2025-04-29 21:00:00','NXB2025042913'),
(13,'debit',8400.00,'Team Mobile Bills','utilities','Mumbai, IN','success',963300.00,'2025-04-28 12:00:00','NXB2025042813');

INSERT INTO fraud_alerts (txn_id,user_id,risk_level,alert_type,description,ai_confidence) VALUES
(21,5,'medium','geo_anomaly','Large international transfer of ₹95,000 to Dubai — please verify if this was authorized.',38.00);

-- ============================================================
-- TRANSACTIONS — Sneha Iyer (account_id=16 savings)
-- ============================================================
INSERT INTO transactions (account_id,txn_type,amount,merchant_name,category,location,status,balance_after,txn_date,reference_no) VALUES
(16,'credit',42000.00,'Employer NEFT','income','Chennai, IN','success',87800.00,'2025-05-01 09:00:00','NXB2025050116'),
(16,'debit',12000.00,'House Rent','housing','Chennai, IN','success',75800.00,'2025-05-01 11:00:00','NXB2025050116A'),
(16,'debit',3200.00,'Big Bazaar','grocery','Chennai, IN','success',72600.00,'2025-05-02 17:30:00','NXB2025050216'),
(16,'debit',420.00,'Ola Cab','transport','Chennai, IN','success',72180.00,'2025-05-03 09:15:00','NXB2025050316'),
(16,'debit',599.00,'BSNL Recharge','utility','Chennai, IN','success',71581.00,'2025-05-05 12:00:00','NXB2025050516');

-- ============================================================
-- AI CREDIT SCORES
-- ============================================================
INSERT INTO ai_credit_scores (user_id,overall_score,payment_history_pct,credit_utilisation,account_age_score,credit_mix,fraud_penalty,score_label) VALUES
(1, 847, 98.0, 6.4,  88.0, 80.0, 15.0, 'excellent'),
(2, 761, 95.0, 30.1, 85.0, 72.0, 0.0,  'very_good'),
(3, 712, 94.0, 28.4, 72.0, 68.0, 0.0,  'good'),
(4, 892, 99.0, 0.0,  92.0, 90.0, 0.0,  'excellent'),
(5, 834, 96.0, 37.0, 95.0, 85.0, 0.0,  'excellent'),
(6, 642, 90.0, 10.9, 52.0, 45.0, 0.0,  'fair');

-- ============================================================
-- SPEND ANALYTICS
-- ============================================================
INSERT INTO spend_analytics (user_id,month,year,category,total_spent,txn_count,pct_of_total) VALUES
(1,5,2025,'shopping',15380,8,40),(1,5,2025,'food',8459,12,22),(1,5,2025,'travel',5768,3,15),(1,5,2025,'utilities',8843,4,23),
(2,5,2025,'housing',21735,2,35),(2,5,2025,'food',15500,14,25),(2,5,2025,'education',12480,2,20),(2,5,2025,'fuel',12480,8,20),
(3,5,2025,'housing',38500,1,45),(3,5,2025,'food',11000,10,20),(3,5,2025,'shopping',21450,6,25),(3,5,2025,'fuel',5500,4,10),
(4,5,2025,'shopping',9300,5,30),(4,5,2025,'travel',10850,2,35),(4,5,2025,'food',6200,8,20),(4,5,2025,'education',4650,2,15),
(5,5,2025,'business',48800,3,40),(5,5,2025,'travel',36600,2,30),(5,5,2025,'food',18300,8,15),(5,5,2025,'utilities',18300,4,15),
(6,5,2025,'housing',11970,1,42),(6,5,2025,'grocery',7980,6,28),(6,5,2025,'transport',4275,12,15),(6,5,2025,'utility',4275,3,15);

-- ============================================================
-- LOANS
-- ============================================================
INSERT INTO loans (user_id,loan_type,loan_amount,outstanding,emi_amount,interest_rate,tenure_months,remaining_months,status,start_date,next_emi_date) VALUES
(1,'Home Loan',3500000.00,2800000.00,28450.00,8.35,240,216,'active','2022-01-15','2025-06-05'),
(1,'Car Loan',420000.00,168000.00,8200.00,9.00,60,36,'active','2023-02-10','2025-06-05'),
(2,'Home Loan',5500000.00,4950000.00,42000.00,8.50,264,216,'active','2021-03-20','2025-06-07'),
(2,'Education Loan',800000.00,480000.00,9500.00,8.50,96,60,'active','2021-03-20','2025-06-07'),
(3,'Car Loan',1200000.00,960000.00,22500.00,9.20,60,48,'active','2024-01-15','2025-06-05'),
(5,'Business Loan',20000000.00,12000000.00,380000.00,10.50,120,96,'active','2022-06-01','2025-06-05');

-- ============================================================
-- LOAN APPLICATIONS
-- ============================================================
INSERT INTO loan_applications (user_id,loan_type,amount,purpose,status,app_date,reference_no) VALUES
(1,'Home Loan',3500000.00,'Purchase home in Bengaluru','disbursed','2022-01-10','LN2022-00123'),
(2,'Home Loan',5500000.00,'Purchase house in Ludhiana','disbursed','2021-03-15','LN2021-00456'),
(2,'Car Loan',800000.00,'Purchase Maruti Swift','credit_check','2025-05-10','LN2025-00789'),
(3,'Personal Loan',300000.00,'Home renovation','approved','2025-03-01','LN2025-00321'),
(4,'Home Loan',8000000.00,'Property in Delhi','disbursed','2023-11-01','LN2023-00891'),
(5,'Business Loan',20000000.00,'Business expansion Mumbai','disbursed','2022-06-01','LN2022-00055');

-- ============================================================
-- INSURANCE POLICIES
-- ============================================================
INSERT INTO insurance_policies (user_id,policy_type,provider,policy_number,coverage,premium,due_date,status) VALUES
(1,'Life Insurance','NexaLife','NL-2024-78451','₹50,00,000','₹12,500/year','2025-12-15','active'),
(1,'Health Insurance','NexaHealth','NH-2024-33892','₹10,00,000','₹8,200/year','2026-03-10','active'),
(1,'Car Insurance','NexaMotor','NM-2025-11204','Comprehensive','₹18,500/year','2026-01-20','active'),
(2,'Life Insurance','NexaLife','NL-2023-44210','₹1,00,00,000','₹22,000/year','2026-03-20','active'),
(2,'Health Insurance','NexaHealth','NH-2023-88901','₹20,00,000','₹14,500/year','2025-06-30','renewal_due'),
(3,'Health Insurance','NexaHealth','NH-2023-55432','₹5,00,000','₹6,500/year','2025-08-10','active'),
(4,'Life Insurance','NexaLife','NL-2020-12300','₹1,50,00,000','₹28,000/year','2025-11-05','active'),
(4,'Health Insurance','NexaHealth','NH-2020-44121','₹25,00,000','₹18,000/year','2025-11-05','active'),
(4,'Travel Insurance','NexaTravel','NT-2025-00421','Global Coverage','₹2,500/trip','2025-12-31','active'),
(5,'Life Insurance','NexaLife','NL-2019-00341','₹5,00,00,000','₹85,000/year','2025-06-30','renewal_due'),
(5,'Business Insurance','NexaBiz','NB-2022-00890','₹2,00,00,000','₹42,000/year','2025-06-30','active'),
(5,'Health Insurance','NexaHealth','NH-2019-00234','₹50,00,000 Family','₹36,000/year','2025-09-12','active'),
(6,'Health Insurance','NexaHealth','NH-2024-77001','₹3,00,000','₹4,200/year','2026-02-28','active');

-- ============================================================
-- INVESTMENTS
-- ============================================================
INSERT INTO investments (user_id,inv_type,inv_name,invested_amount,current_value,returns_pct,detail_text) VALUES
(1,'fd','FD-2024-00847',100000.00,108100.00,8.10,'₹1,00,000 · 8.1% p.a. · Matures Jun 2026'),
(1,'mf','HDFC Midcap Fund — SIP',36000.00,40300.00,14.20,'₹3,000/month SIP · +14.2% CAGR'),
(2,'fd','FD-2023-00412',200000.00,205000.00,7.25,'₹2,00,000 · 7.25% p.a. · Matures Sep 2026'),
(2,'mf','Axis Bluechip Fund',60000.00,67600.00,12.40,'₹5,000/month SIP · +12.4% CAGR'),
(3,'fd','FD-2024-01102',50000.00,53750.00,7.50,'₹50,000 · 7.5% p.a. · Matures Aug 2026'),
(3,'mf','SBI Nifty Index Fund',24000.00,25450.00,11.80,'₹2,000/month SIP · +11.8% CAGR'),
(4,'fd','FD-2022-00231',500000.00,580500.00,8.10,'₹5,00,000 · 8.1% p.a. · Matures Nov 2027'),
(4,'mf','Mirae Asset Emerging Fund',96000.00,111792.00,16.20,'₹8,000/month SIP · +16.2% CAGR'),
(5,'fd','FD-2021-00088',1200000.00,1297200.00,8.10,'₹12,00,000 · 8.1% p.a. · Matures Jun 2027'),
(5,'mf','DSP Midcap Fund',240000.00,284160.00,18.40,'₹20,000/month SIP · +18.4% CAGR'),
(6,'mf','Nippon India Small Cap Fund',12000.00,13080.00,13.20,'₹1,000/month SIP · +13.2% CAGR');

-- ============================================================
-- SMART OFFERS
-- ============================================================
INSERT INTO smart_offers (user_id,offer_type,offer_title,offer_desc,offer_value,ai_match_pct,valid_until) VALUES
(1,'personal_loan','Pre-Approved Personal Loan','Based on credit score 847 — instant 4-hour disbursal, zero processing fee.','₹5,00,000 @ 10.2%',96.00,DATE_ADD(CURDATE(),INTERVAL 30 DAY)),
(1,'fd','Exclusive FD — 8.1% p.a.','Premium member rate locked for 12 months. Limited availability.','8.1% p.a.',88.00,DATE_ADD(CURDATE(),INTERVAL 15 DAY)),
(1,'credit_card','AI Cashback Credit Card','5% cashback on UPI — matched from your spend analytics.','5% on UPI',91.00,DATE_ADD(CURDATE(),INTERVAL 20 DAY)),
(1,'credit_card','Zero Forex Travel Card','AI detected travel spending. Save on every international trip.','Zero Forex',92.00,DATE_ADD(CURDATE(),INTERVAL 25 DAY)),
(2,'car_loan','Pre-Approved Car Loan','Based on your profile — instant disbursal within 48 hours.','₹8,00,000 @ 9.0%',91.00,DATE_ADD(CURDATE(),INTERVAL 30 DAY)),
(2,'credit_card','Upgrade to Platinum Card','Higher credit limit, lounge access, zero joining fee.','₹2,50,000 limit',85.00,DATE_ADD(CURDATE(),INTERVAL 20 DAY)),
(3,'personal_loan','Personal Loan — Instant','Quick approval, no branch visit needed.','₹3,00,000 @ 12%',82.00,DATE_ADD(CURDATE(),INTERVAL 30 DAY)),
(3,'fd','Tax Saver FD','Save tax under 80C. 5-year lock-in.','7.5% p.a.',78.00,DATE_ADD(CURDATE(),INTERVAL 20 DAY)),
(4,'credit_card','Nexa Platinum Card','Worldwide lounge access, 10% cashback, zero annual fee.','10% Cashback',98.00,DATE_ADD(CURDATE(),INTERVAL 30 DAY)),
(4,'wealth','Wealth Management Service','Dedicated RM assigned. Free portfolio review.','Premium Service',95.00,DATE_ADD(CURDATE(),INTERVAL 20 DAY)),
(5,'business_loan','Working Capital Loan','Instant approval, collateral-free, 48-hr disbursal.','₹50,00,000 @ 11%',94.00,DATE_ADD(CURDATE(),INTERVAL 30 DAY)),
(5,'credit_card','Business Platinum Card','₹10L limit, 2% cashback on all spends, airport lounge.','₹10,00,000 limit',97.00,DATE_ADD(CURDATE(),INTERVAL 20 DAY)),
(6,'credit_card','Starter Credit Card','Build your credit history. ₹500 joining cashback.','₹75,000 limit',88.00,DATE_ADD(CURDATE(),INTERVAL 30 DAY)),
(6,'rd','Recurring Deposit','Start from ₹500/month. 6.8% interest per annum.','6.8% p.a.',92.00,DATE_ADD(CURDATE(),INTERVAL 20 DAY));

-- Run credit score computation for all users
CALL compute_ai_credit_score(1);
CALL compute_ai_credit_score(2);
CALL compute_ai_credit_score(3);
CALL compute_ai_credit_score(4);
CALL compute_ai_credit_score(5);
CALL compute_ai_credit_score(6);

SELECT 'NexaBank DB setup complete! All 6 users created.' AS Status;
