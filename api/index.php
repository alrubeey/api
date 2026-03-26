<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

// File settings
$accounts_file = 'accounts.json';

// Helper functions
function load_accounts() {
    global $accounts_file;
    if (file_exists($accounts_file)) {
        $data = json_decode(file_get_contents($accounts_file), true);
        return $data['accounts'] ?? [];
    }
    return [];
}

function save_accounts($accounts) {
    global $accounts_file;
    file_put_contents($accounts_file, json_encode(['accounts' => $accounts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function save_account() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_account') {
        $accounts = load_accounts();
        
        $account = [
            'id' => uniqid(),
            'name' => $_POST['account_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'use_ssl' => isset($_POST['use_ssl']),
            'from_name' => $_POST['from_name'] ?? ''
        ];
        
        // Validation
        if (empty($account['name']) || empty($account['email']) || empty($account['smtp_host']) || 
            empty($account['username']) || empty($account['password'])) {
            $_SESSION['error'] = 'All fields are required';
            return;
        }
        
        if (!filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Please enter a valid email address';
            return;
        }
        
        $accounts[] = $account;
        save_accounts($accounts);
        $_SESSION['success'] = 'Account saved successfully! ✓';
    }
}

function delete_account() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
        $account_id = $_POST['account_id'] ?? null;
        if ($account_id) {
            $accounts = load_accounts();
            $accounts = array_filter($accounts, function($acc) use ($account_id) {
                return $acc['id'] !== $account_id;
            });
            save_accounts(array_values($accounts));
            $_SESSION['success'] = 'Account deleted successfully! ✓';
        }
    }
}

function send_email() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') {
        require_once 'PHPMailer.php';
        require_once 'SMTP.php';
        require_once 'Exception.php';
        
        $accounts = load_accounts();
        $account_id = $_POST['account_id'] ?? null;
        
        $selected_account = null;
        foreach ($accounts as $acc) {
            if ($acc['id'] === $account_id) {
                $selected_account = $acc;
                break;
            }
        }
        
        if (!$selected_account) {
            $_SESSION['error'] = 'Account not found';
            return;
        }
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $selected_account['smtp_host'];
            $mail->Port = $selected_account['smtp_port'];
            $mail->Username = $selected_account['username'];
            $mail->Password = $selected_account['password'];
            
            if ($selected_account['use_ssl']) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->SMTPAuth = true;
            $mail->CharSet = 'UTF-8';
            
            // Message Data
            $mail->setFrom($selected_account['email'], $selected_account['from_name']);
            $mail->Subject = $_POST['subject'] ?? '';
            $mail->Body = $_POST['message'] ?? '';
            $mail->isHTML(true);
            
            // Email List
            $emails = array_filter(array_map('trim', explode("\n", $_POST['email_list'] ?? '')));
            
            if (empty($emails)) {
                $_SESSION['error'] = 'Please enter at least one email address';
                return;
            }
            
            $sent_count = 0;
            $failed = [];
            
            foreach ($emails as $email) {
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail->addAddress($email);
                        if ($mail->send()) {
                            $sent_count++;
                        }
                        $mail->clearAddresses();
                    } catch (Exception $e) {
                        $failed[] = $email;
                    }
                } else {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $failed[] = $email . ' (invalid format)';
                    }
                }
            }
            
            if ($sent_count > 0) {
                $_SESSION['success'] = "Successfully sent $sent_count email(s)! ✓";
            }
            if (!empty($failed)) {
                $_SESSION['warning'] = "Failed to send to: " . implode(", ", $failed);
            }
            if ($sent_count == 0 && !empty($failed)) {
                $_SESSION['error'] = "No emails were sent. Please check the email list.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Sending Error: ' . $e->getMessage();
        }
    }
}

// Process requests
save_account();
delete_account();
send_email();

// Determine current page
$page = $_GET['page'] ?? 'settings';
$accounts = load_accounts();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Mail Sender - Email Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .nav {
            display: flex;
            gap: 0;
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
        }
        
        .nav-btn {
            flex: 1;
            padding: 15px 20px;
            border: none;
            background: #f5f5f5;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            color: #333;
            font-weight: 500;
        }
        
        .nav-btn:hover {
            background: #e0e0e0;
        }
        
        .nav-btn.active {
            background: #667eea;
            color: white;
            border-bottom: 3px solid #764ba2;
        }
        
        .content {
            padding: 30px;
        }
        
        .alerts {
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        button {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #764ba2;
        }
        
        button.delete {
            background: #dc3545;
            padding: 8px 15px;
            font-size: 14px;
        }
        
        button.delete:hover {
            background: #c82333;
        }
        
        .accounts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .account-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            background: #f9f9f9;
            transition: all 0.3s;
        }
        
        .account-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .account-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .account-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            word-break: break-all;
        }
        
        .account-info strong {
            color: #333;
        }
        
        .page-section {
            display: none;
        }
        
        .page-section.active {
            display: block;
        }
        
        .required {
            color: red;
        }
        
        .form-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .form-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 22px;
        }
        
        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
        }
        
        small {
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .accounts-grid {
                grid-template-columns: 1fr;
            }
            
            .nav {
                flex-direction: column;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 SMTP Mail Sender</h1>
            <p>Professional Email Management and Bulk Messaging System</p>
        </div>
        
        <div class="nav">
            <button class="nav-btn <?php echo ($page === 'settings') ? 'active' : ''; ?>" 
                    onclick="location.href='?page=settings'">
                ⚙️ Account Settings
            </button>
            <button class="nav-btn <?php echo ($page === 'send') ? 'active' : ''; ?>" 
                    onclick="location.href='?page=send'">
                ✉️ Send Messages
            </button>
        </div>
        
        <div class="content">
            <!-- Messages Display -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alerts">
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alerts">
                    <div class="alert alert-error">
                        ✕ <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['warning'])): ?>
                <div class="alerts">
                    <div class="alert alert-warning">
                        ⚠ <?php echo htmlspecialchars($_SESSION['warning']); ?>
                    </div>
                </div>
                <?php unset($_SESSION['warning']); ?>
            <?php endif; ?>
            
            <!-- Settings Page -->
            <div class="page-section <?php echo ($page === 'settings') ? 'active' : ''; ?>">
                <div class="form-section">
                    <h2>Add New Email Account</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="save_account">
                        
                        <div class="form-group">
                            <label>Account Name <span class="required">*</span></label>
                            <input type="text" name="account_name" placeholder="Example: My Gmail Account" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" placeholder="your-email@gmail.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Sender Name <span class="required">*</span></label>
                            <input type="text" name="from_name" placeholder="Example: John Smith" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>SMTP Server <span class="required">*</span></label>
                                <input type="text" name="smtp_host" placeholder="smtp.gmail.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Port <span class="required">*</span></label>
                                <input type="number" name="smtp_port" value="587" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username <span class="required">*</span></label>
                                <input type="text" name="username" placeholder="your-email@gmail.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Password <span class="required">*</span></label>
                                <input type="password" name="password" placeholder="••••••••" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="use_ssl" name="use_ssl" checked>
                                <label for="use_ssl" style="margin-bottom: 0;">Use SSL/TLS Encryption</label>
                            </div>
                        </div>
                        
                        <button type="submit">💾 Save Account</button>
                    </form>
                </div>
                
                <h2>Saved Accounts</h2>
                <div class="accounts-grid">
                    <?php if (empty($accounts)): ?>
                        <p style="grid-column: 1/-1; text-align: center; color: #999; padding: 30px;">No saved accounts yet</p>
                    <?php endif; ?>
                    
                    <?php foreach ($accounts as $account): ?>
                        <div class="account-card">
                            <h3><?php echo htmlspecialchars($account['name']); ?></h3>
                            <div class="account-info">
                                <strong>Email:</strong> <?php echo htmlspecialchars($account['email']); ?>
                            </div>
                            <div class="account-info">
                                <strong>Server:</strong> <?php echo htmlspecialchars($account['smtp_host']); ?>
                            </div>
                            <div class="account-info">
                                <strong>Port:</strong> <?php echo htmlspecialchars($account['smtp_port']); ?>
                            </div>
                            <div class="account-info">
                                <strong>Username:</strong> <?php echo htmlspecialchars($account['username']); ?>
                            </div>
                            <div class="account-info">
                                <strong>Security:</strong> <?php echo $account['use_ssl'] ? '✓ SSL/TLS Enabled' : '✕ No Encryption'; ?>
                            </div>
                            
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="action" value="delete_account">
                                <input type="hidden" name="account_id" value="<?php echo htmlspecialchars($account['id']); ?>">
                                <button type="submit" class="delete" onclick="return confirm('Are you sure you want to delete this account?')">
                                    🗑️ Delete
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Send Messages Page -->
            <div class="page-section <?php echo ($page === 'send') ? 'active' : ''; ?>">
                <?php if (empty($accounts)): ?>
                    <div class="alert alert-error">
                        ⚠️ No accounts added yet. Please go to Account Settings first to add an email account.
                    </div>
                <?php else: ?>
                    <div class="form-section">
                        <h2>Send Bulk Messages</h2>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="send_email">
                            
                            <div class="form-group">
                                <label>Select Account <span class="required">*</span></label>
                                <select name="account_id" required>
                                    <option value="">-- Select an account --</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo htmlspecialchars($account['id']); ?>">
                                            <?php echo htmlspecialchars($account['name']); ?> (<?php echo htmlspecialchars($account['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Email List <span class="required">*</span></label>
                                <textarea name="email_list" placeholder="Enter email addresses (one per line):&#10;recipient1@example.com&#10;recipient2@example.com&#10;recipient3@example.com" required></textarea>
                                <small>💡 Enter one email address per line</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Email Subject <span class="required">*</span></label>
                                <input type="text" name="subject" placeholder="Example: Important Announcement" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Message Content <span class="required">*</span></label>
                                <textarea name="message" placeholder="Enter your message here...&#10;You can use HTML for formatting" required style="min-height: 200px;"></textarea>
                                <small>💡 HTML and CSS formatting supported</small>
                            </div>
                            
                            <button type="submit">📤 Send Messages</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
