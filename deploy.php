<?php
session_start();
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CONFIG
$SECRET_CODE = "151124";
$TIMEOUT_SECONDS = 10;

// SMTP Configuration - Zoho Mail
$SMTP_HOST = 'smtp.zoho.com';
$SMTP_PORT = 587;
$SMTP_USERNAME = 'noreply@aspensoftwarefoundation.com';
$SMTP_PASSWORD = 'Atlanta__59'; // Update this
$SMTP_FROM_EMAIL = 'noreply@aspensoftwarefoundation.com';
$SMTP_FROM_NAME = 'Aspen Hosting Services (AHS)';
$ADMIN_EMAIL = "noreply@aspensoftwarefoundation.com";

$step = $_POST['step'] ?? '';

// TIMEOUT
if (isset($_SESSION['last_deploy']) && (time() - $_SESSION['last_deploy']) < $TIMEOUT_SECONDS) {
    die("Please wait before deploying again.");
}

// DEPLOYMENT STEP
if ($step === "deploy") {
    $code  = $_POST['code'] ?? '';
    $plan  = $_POST['plan'] ?? '';
    $os    = $_POST['os'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if ($code !== $SECRET_CODE) {
        die("Invalid code.");
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email address.");
    }
    
    $_SESSION['last_deploy'] = time();
    
    $username = "user" . rand(100,99999);
    $password = bin2hex(random_bytes(6));
    $vmid     = rand(200,99999);
    
    $plan_names = [
        'personal' => 'Personal - 2GB RAM / 1 Core / 10GB SSD',
        'basic' => 'Basic - 4GB RAM / 2 Cores / 25GB SSD',
        'pro' => 'Pro - 8GB RAM / 4 Cores / 45GB SSD',
        'smallbusiness' => 'Small Business - 18GB RAM / 8 Cores / 95GB NVMe'
    ];
    
    $os_names = [
        'debian' => 'Debian 13 netinstall',
        'fedora' => 'Fedora Server',
        'ubuntu-server' => 'Ubuntu Server 24.04 LTS',
        'centos' => 'CentOS Stream 9',
        'alpine' => 'Alpine Linux'
    ];
    
    // Start deployment in background
    $log_file = "/tmp/deploy-{$vmid}.log";
    $command = "nohup sudo /mnt/aspen-website/deploy-vm.sh {$username} {$password} {$vmid} {$plan} {$os} > {$log_file} 2>&1 & echo $!";
    $pid = shell_exec($command);
    
    echo "<div style='max-width:600px; margin:50px auto; padding:40px; background:rgba(0,0,0,0.9); border-radius:16px; color:white; font-family:Arial,sans-serif;'>";
    echo "<h1 style='color:#4ade80; margin-bottom:20px;'>✓ Deployment Initiated!</h1>";
    echo "<div style='background:#1a1a1a; padding:20px; border-radius:8px; margin-bottom:20px;'>";
    echo "<p style='margin:10px 0;'><strong>VM ID:</strong> {$vmid}</p>";
    echo "<p style='margin:10px 0;'><strong>Username:</strong> {$username}</p>";
    echo "<p style='margin:10px 0;'><strong>Password:</strong> {$password}</p>";
    echo "<p style='margin:10px 0;'><strong>Plan:</strong> " . ($plan_names[$plan] ?? $plan) . "</p>";
    echo "<p style='margin:10px 0;'><strong>OS:</strong> " . ($os_names[$os] ?? $os) . "</p>";
    echo "</div>";
    echo "<div style='background:#fef3c7; color:#92400e; padding:15px; border-radius:8px; margin-bottom:20px;'>";
    echo "<p style='margin:0;'><strong>⚠ Important:</strong> Save these credentials now! Your VPS is being deployed in the background.</p>";
    echo "</div>";
    echo "<p style='color:#aaa;'>⏱ Deployment typically takes 2-3 minutes.</p>";
    echo "<p style='color:#aaa;'>📧 You will receive an email at <strong>{$email}</strong> when deployment is complete.</p>";
    echo "<p style='margin-top:30px;'><a href='/' style='display:inline-block; padding:12px 24px; background:#dc2626; color:white; text-decoration:none; border-radius:6px;'>Return to Home</a></p>";
    echo "</div>";
    
    function sendEmail($to, $subject, $body, $isHTML = false) {
        global $SMTP_HOST, $SMTP_PORT, $SMTP_USERNAME, $SMTP_PASSWORD, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;
        
        $mail = new PHPMailer(true);
        
        try {
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host       = $SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = $SMTP_USERNAME;
            $mail->Password   = $SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $SMTP_PORT;
            
            $mail->setFrom($SMTP_FROM_EMAIL, $SMTP_FROM_NAME);
            $mail->addAddress($to);
            
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email failed: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    // Send immediate "deployment started" email
    $customer_subject = "VPS Deployment Started - VM ID: {$vmid}";
    
    $customer_message = "Hello,\n\n";
    $customer_message .= "Your VPS deployment has been initiated!\n\n";
    $customer_message .= "=== VPS Details ===\n";
    $customer_message .= "VM ID: {$vmid}\n";
    $customer_message .= "Username: {$username}\n";
    $customer_message .= "Password: {$password}\n\n";
    $customer_message .= "=== Plan Details ===\n";
    $customer_message .= "Plan: " . ($plan_names[$plan] ?? $plan) . "\n";
    $customer_message .= "OS: " . ($os_names[$os] ?? $os) . "\n\n";
    $customer_message .= "You can access your VPS through: https://server.aspensoftwarefoundation.com\n";
    $customer_message .= "PLEASE CHANGE YOUR VPS AND ACCOUNT PASSWORD WHEN LOGGED IN TO AVOID SECURITY VULNERABILITIES!\n";
    $customer_message .= "NOTE: If you forget your account or VPS password, you will need to contact us at contact@aspensoftwarefoundation.com to allow us to change it for you.\n\n";
    $customer_message .= "Thank you for choosing Aspen Hosting Services!\n";
    $customer_message .= "- The Aspen Team";
    
    sendEmail($email, $customer_subject, $customer_message);
    
    // Create completion check script (optional - for sending "deployment complete" email)
    $check_script = "/tmp/check-deploy-{$vmid}.sh";
    $check_content = "#!/bin/bash
while [ ! -f {$log_file}.done ]; do
    sleep 5
done

# Send completion email via PHP
php -r \"
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

\\\$mail = new PHPMailer(true);
\\\$mail->isSMTP();
\\\$mail->Host = '{$SMTP_HOST}';
\\\$mail->SMTPAuth = true;
\\\$mail->Username = '{$SMTP_USERNAME}';
\\\$mail->Password = '{$SMTP_PASSWORD}';
\\\$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
\\\$mail->Port = {$SMTP_PORT};
\\\$mail->setFrom('{$SMTP_FROM_EMAIL}', '{$SMTP_FROM_NAME}');
\\\$mail->addAddress('{$email}');
\\\$mail->Subject = 'VPS Deployment Complete - VM ID: {$vmid}';
\\\$mail->Body = 'Your VPS is now ready!

Access your VPS through the Proxmox web interface using the credentials provided in your previous email.

VM ID: {$vmid}
Username: {$username}

Thank you for choosing Aspen Hosting!';
\\\$mail->send();
\"

rm {$log_file} {$log_file}.done {$check_script}
";
    
    file_put_contents($check_script, $check_content);
    chmod($check_script, 0755);
    shell_exec("nohup {$check_script} > /dev/null 2>&1 &");
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aspen Hosting Services</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { height:100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: linear-gradient(-45deg, #ff5a36, #ff8c42, #ff5a36, #ff8c42);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: #fff;
            padding-top: 120px;
        }
        @keyframes gradientBG {
            0% { background-position:0% 50%; }
            50% { background-position:100% 50%; }
            100% { background-position:0% 50%; }
        }
        .container {
            background: rgba(0,0,0,0.8);
            padding: 40px;
            border-radius: 16px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.7);
        }
        h1 { font-size: 2.5rem; margin-bottom: 20px; color: #dc2626; }
        p { color: #ccc; margin-bottom: 20px; }
        select, input[type="text"], input[type="email"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            border:none;
            font-size: 1rem;
        }
        select:focus, input:focus { outline:none; box-shadow:0 0 8px #dc2626; }
        .btn-primary {
            padding: 15px 30px;
            background: #dc2626;
            color: #fff;
            border:none;
            border-radius:6px;
            font-weight:bold;
            font-size:1.2rem;
            cursor:pointer;
            transition:0.3s;
            display:inline-block;
            text-decoration:none;
        }
        .btn-primary:hover { background:#b91c1c; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Aspen VPS Deployment</h1>
        <p>Deploy your VPS instantly. Choose your plan and OS below.</p>
        <form method="POST">
            <input type="hidden" name="step" value="deploy">
            <input type="email" name="email" placeholder="Your Email Address" required>
            <input type="text" name="code" placeholder="Enter Deployment Code" required>
            <select name="plan" required>
                <option value="personal">Personal - 2GB RAM / 1 Core / 10GB SSD - $4/mo</option>
                <option value="basic">Basic - 4GB RAM / 2 Cores / 25GB SSD - $6/mo</option>
                <option value="pro">Pro - 8GB RAM / 4 Cores / 45GB SSD - $10/mo</option>
                <option value="smallbusiness">Small Business - 18GB RAM / 8 Cores / 95GB NVMe - $18.95/mo</option>
            </select>
            <select name="os" required>
                <option value="debian">Debian 13 netinstall</option>
                <option value="fedora">Fedora Server</option>
                <option value="ubuntu-server">Ubuntu Server 24.04 LTS</option>
                <option value="centos">CentOS Stream 9</option>
                <option value="alpine">Alpine Linux</option>
            </select>
            <p style="color:#ccc; font-size:0.9rem; margin-bottom:15px;">
                Deploy your VPS and have it running instantly!
            </p>
            <button type="submit" class="btn-primary">Deploy Now</button>
        </form>
    </div>
</body>
</html>
