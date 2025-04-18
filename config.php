<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'newsletter_subscriptions');

// SMTP Email configuration
define('MAIL_MAILER', 'smtp');
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', '');
define('MAIL_TO_ADDRESS', '');
define('EMAIL_SUBJECT', '');

// Website URL
define('BASE_URL', 'https://89d3-105-160-84-48.ngrok-free.app');

// Create database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Include PHPMailer
require 'vendor/autoload.php';

function sendConfirmationEmail($email, $token) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM_ADDRESS, 'Newsletter Subscription');
        $mail->addAddress($email);
        $mail->addReplyTo(MAIL_FROM_ADDRESS, 'No Reply');

        // Content
        $confirm_link = BASE_URL . '/confirm.php?email=' . urlencode($email) . '&token=' . $token;
        
        $mail->isHTML(true);
        $mail->Subject = EMAIL_SUBJECT;
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #333;'>Confirm Your Subscription</h2>
                <p style='color: #555;'>Please click the button below to confirm your subscription:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='$confirm_link' style='background: #0066cc; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;'>
                        Confirm Subscription
                    </a>
                </p>
                <p style='color: #555;'>Or copy and paste this link into your browser:<br>
                <a href='$confirm_link' style='color: #0066cc; word-break: break-all;'>$confirm_link</a></p>
                <p style='color: #999; font-size: 12px;'>If you didn't request this subscription, please ignore this email.</p>
            </div>
        ";
        
        $mail->AltBody = "Please click the following link to confirm your subscription:\n\n$confirm_link\n\nIf you didn't request this subscription, please ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Handle subscription confirmation (confirm.php functionality)
if (basename($_SERVER['SCRIPT_NAME']) === 'confirm.php') {
    header('Content-Type: text/html; charset=UTF-8');
    
    $email = isset($_GET['email']) ? urldecode($_GET['email']) : '';
    $token = isset($_GET['token']) ? $_GET['token'] : '';

    if (empty($email) || empty($token)) {
        die("Invalid confirmation link - missing parameters");
    }

    try {
        // Check if email and token match
        $stmt = $pdo->prepare("SELECT * FROM subscribers WHERE email = ? AND token = ? AND is_confirmed = FALSE");
        $stmt->execute([$email, $token]);
        $subscriber = $stmt->fetch();
        
        if ($subscriber) {
            // Update subscriber as confirmed
            $update = $pdo->prepare("UPDATE subscribers SET is_confirmed = TRUE, confirmed_at = CURRENT_TIMESTAMP WHERE email = ?");
            $update->execute([$email]);
            
            // Send welcome email
            $welcome_mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $welcome_mail->isSMTP();
            $welcome_mail->Host       = MAIL_HOST;
            $welcome_mail->SMTPAuth   = true;
            $welcome_mail->Username   = MAIL_USERNAME;
            $welcome_mail->Password   = MAIL_PASSWORD;
            $welcome_mail->SMTPSecure = MAIL_ENCRYPTION;
            $welcome_mail->Port       = MAIL_PORT;
            $welcome_mail->setFrom(MAIL_FROM_ADDRESS, 'Newsletter Team');
            $welcome_mail->addAddress($email);
            $welcome_mail->Subject = 'Welcome to Our Newsletter';
            $welcome_mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                    <h2 style='color: #333;'>Thank you for confirming your subscription!</h2>
                    <p style='color: #555;'>You are now subscribed to our newsletter. Here's what you can expect:</p>
                    <ul style='color: #555;'>
                        <li>Weekly updates</li>
                        <li>Exclusive content</li>
                        <li>Special offers</li>
                    </ul>
                    <p style='color: #555;'>If you ever wish to unsubscribe, you'll find a link at the bottom of every email.</p>
                </div>
            ";
            $welcome_mail->send();
            
            // Show confirmation page
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Subscription Confirmed</title>
                <style>
                    body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
                    .success { background-color: #e6ffe6; border: 1px solid #b3ffb3; padding: 20px; border-radius: 5px; margin: 20px 0; }
                    .btn { display: inline-block; padding: 10px 15px; background: #0066cc; color: white; text-decoration: none; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class="success">
                    <h2>Subscription Confirmed!</h2>
                    <p>Thank you for confirming your email address. You are now subscribed to our newsletter.</p>
                    <p>We\'ve sent a welcome email to '.htmlspecialchars($email).'.</p>
                </div>
                <a href="'.BASE_URL.'" class="btn">Return to Homepage</a>
            </body>
            </html>';
            exit;
        } else {
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Invalid Confirmation Link</title>
                <style>
                    body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
                    .error { background-color: #ffe6e6; border: 1px solid #ffb3b3; padding: 20px; border-radius: 5px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h2>Invalid Confirmation Link</h2>
                    <p>The confirmation link is invalid or has expired.</p>
                    <p>Please try subscribing again.</p>
                </div>
            </body>
            </html>';
            exit;
        }
    } catch(PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}
?>