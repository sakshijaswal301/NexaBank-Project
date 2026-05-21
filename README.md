# NexaBank-Project
AI-Powered Banking Web Application
╔══════════════════════════════════════════════════════════════════╗
║          NexaBank India — Windows XAMPP Setup Guide              ║
║          Project by: Sakshi Jaswal                               ║
╚══════════════════════════════════════════════════════════════════╝

QUICK SUMMARY
─────────────
• Apache  → MUST be ON
• MySQL   → MUST be ON
• URL     → http://localhost/NexaBank_Project/frontend/index.html
• No Node.js, no npm, no extra installations needed

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 1 — INSTALL XAMPP (skip if already installed)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Download from  https://www.apachefriends.org/
2. Run installer → keep all defaults → Finish
3. Open XAMPP Control Panel (from Start menu or Desktop icon)
4. Click START next to Apache  — wait for green "Running"
5. Click START next to MySQL   — wait for green "Running"

Test: Open Chrome → go to http://localhost → XAMPP dashboard appears ✅

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 2 — COPY PROJECT TO XAMPP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Open File Explorer
2. Go to:  C:\xampp\htdocs\
3. Copy the folder "NexaBank_Project" into htdocs
4. Final structure must be:
   C:\xampp\htdocs\NexaBank_Project\
   C:\xampp\htdocs\NexaBank_Project\frontend\index.html   ← main file
   C:\xampp\htdocs\NexaBank_Project\backend\config.php
   C:\xampp\htdocs\NexaBank_Project\database\nexabank_db.sql

⚠️  IMPORTANT: folder must be named exactly  NexaBank_Project
    (not NexaBank_Project-main or NexaBank_Final etc.)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 3 — IMPORT DATABASE INTO phpMyAdmin
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Open Chrome → go to:  http://localhost/phpmyadmin
2. Click "New" in the left sidebar
3. In "Database name" box type:  nexabank_db
4. Click "Create"
5. Click on "nexabank_db" in the left sidebar (it opens)
6. Click the "Import" tab at the top of the page
7. Click "Choose File"
8. Navigate to:  C:\xampp\htdocs\NexaBank_Project\database\nexabank_db.sql
9. Select the file → click "Go" at the bottom of the page
10. Wait a few seconds…

✅  Success message: "Import has been successfully finished"
✅  You should now see 14 tables in the left panel under nexabank_db

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 4 — OPEN THE WEBSITE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Open Chrome and go to:

  http://localhost/NexaBank_Project/frontend/index.html

The NexaBank homepage will load. Click "Login" to proceed.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 5 — LOGIN CREDENTIALS (Only these 6 accounts work)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

┌─────────────────┬──────────────┬───────────────┬───────────────────────────┐
│ Name            │ Customer ID  │ Password      │ Profile                   │
├─────────────────┼──────────────┼───────────────┼───────────────────────────┤
│ Sakshi Jaswal   │ NXB20240001  │ sakshi@123    │ Premium · Score 847 ⭐    │
│ Gurpreet Jassal │ NXB20240002  │ gurpreet@456  │ Gold · Score 761          │
│ Rohit Kumar     │ NXB20240003  │ rohit@789     │ Standard · Score 712      │
│ Priya Sharma    │ NXB20240004  │ priya@321     │ Premium · Score 892 ⭐    │
│ Arjun Mehta     │ NXB20240005  │ arjun@654     │ Business Elite · Score 834│
│ Sneha Iyer      │ NXB20240006  │ sneha@987     │ Standard · Score 642      │
└─────────────────┴──────────────┴───────────────┴───────────────────────────┘

OTP Screen: Type any 6 digits e.g. 1 1 1 1 1 1 → click Verify

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
HOW TO USE EVERY FEATURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🤖 NEXA AI CHAT     → Sidebar: "Nexa AI"
   Ask: "What is my balance?" / "Show fraud alerts" / "Loan eligibility"
   Quick chips appear below the chat for one-click queries

🛡 FRAUD DETECTION  → Sidebar: "Fraud Center"
   See HIGH/MED/SAFE alerts · Click security buttons to freeze/unblock card

📊 SPEND ANALYTICS  → Sidebar: "Spend Analytics"
   Animated bar chart + donut breakdown + AI savings insights

⭐ AI CREDIT SCORE  → Sidebar: "AI Credit Score"
   Score ring, history trend, improvement tips

🎯 SMART OFFERS     → Sidebar: "Smart Offers"
   AI-matched loan / card / FD offers with match % — click Apply

📤 FUND TRANSFER    → Sidebar: "Fund Transfer"
   Select UPI/NEFT/RTGS/IMPS · Enter recipient + amount · Transfer
   Balance deducts live · Success modal with reference number

💳 CARD DETAILS     → Sidebar: "Card Details"
   Full card display · 6 control buttons (freeze/unfreeze/block etc.)

🏦 APPLY LOAN       → Sidebar: "Apply Loan"
   6 loan types · Live EMI calculator · Submit → modal with App ID

📍 TRACK LOAN       → Sidebar: "Track Loan"
   5-stage progress tracker (Submitted → Verified → Check → Approved → Disbursed)

🛡 INSURANCE        → Sidebar: "Insurance" / "Track Insurance"
   View policies · Pay premium · Download policy · Raise claim

📈 INVESTMENTS      → Sidebar: "Investments"
   FD + Mutual Fund portfolio with returns

⚡ PAY BILLS        → Sidebar: "Pay Bills"
   8 bill categories + upcoming bills with Pay Now buttons

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FILE STRUCTURE (what each file does)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

NexaBank_Project/
│
├── frontend/
│   └── index.html          ← THE COMPLETE WEBSITE
│                             (HTML + CSS + JavaScript — all in one)
│                             Open this file via localhost to run
│
├── backend/
│   ├── config.php          ← Database connection (root / empty password)
│   ├── auth.php            ← Login, Register, OTP, Logout API
│   ├── ai_engine.php       ← Fraud Detection, Credit Score,
│   │                          Spend Analytics, Nexa AI Chat, Offers API
│   └── transactions.php    ← View transactions, Fund Transfer API
│
├── database/
│   └── nexabank_db.sql     ← IMPORT THIS in phpMyAdmin
│                             Contains: 14 tables, 6 users, all demo data,
│                             transactions, fraud alerts, loans, insurance,
│                             investments, AI credit scores, smart offers
│
└── docs/
    └── Sakshi_Jaswal_MBA_Report.docx

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TROUBLESHOOTING
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PROBLEM: Apache won't start / port error
SOLUTION: Another app is using port 80.
  → In XAMPP → Config (Apache) → httpd.conf
  → Change "Listen 80" to "Listen 8080"
  → Then open: http://localhost:8080/NexaBank_Project/frontend/index.html

PROBLEM: Page shows "404 Not Found"
SOLUTION: Folder is not named correctly or not in the right place
  → Must be at: C:\xampp\htdocs\NexaBank_Project\
  → Folder name must be exactly: NexaBank_Project

PROBLEM: Login says "Invalid Customer ID"
SOLUTION: Use the exact IDs from the table above (NXB20240001 etc.)
  → Passwords are case-sensitive: sakshi@123 (all lowercase)

PROBLEM: Database import fails
SOLUTION: Make sure you created "nexabank_db" first before importing
  → phpMyAdmin → New → nexabank_db → Create → THEN import

PROBLEM: Page looks unstyled / broken
SOLUTION: Must open via http://localhost/... — NOT by double-clicking the file
  → Double-clicking opens as file:// which blocks fonts and styles

PROBLEM: White screen after login
SOLUTION: Clear browser cache: Ctrl+Shift+Delete → Clear All → Refresh

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
