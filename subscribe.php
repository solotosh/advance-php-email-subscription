<?php
require_once 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT * FROM subscribers WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                if ($existing['is_confirmed']) {
                    $message = "This email is already subscribed and confirmed.";
                } else {
                    // Regenerate token for security
                    $new_token = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare("UPDATE subscribers SET token = ? WHERE email = ?");
                    $stmt->execute([$new_token, $email]);
                    
                    if (sendConfirmationEmail($email, $new_token)) {
                        $message = "This email is already subscribed but not confirmed. We've sent a new confirmation email.";
                    } else {
                        $error = "Failed to send confirmation email. Please try again later.";
                    }
                }
            } else {
                // Generate unique token
                $token = bin2hex(random_bytes(32));
                
                // Insert new subscriber
                $stmt = $pdo->prepare("INSERT INTO subscribers (email, token) VALUES (?, ?)");
                $stmt->execute([$email, $token]);
                
                // Send confirmation email
                if (sendConfirmationEmail($email, $token)) {
                    $message = "Thank you for subscribing! Please check your email to confirm your subscription.";
                    
                    // Optional: Send notification to admin
                    $admin_mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $admin_mail->isSMTP();
                    $admin_mail->Host       = MAIL_HOST;
                    $admin_mail->SMTPAuth   = true;
                    $admin_mail->Username   = MAIL_USERNAME;
                    $admin_mail->Password   = MAIL_PASSWORD;
                    $admin_mail->SMTPSecure = MAIL_ENCRYPTION;
                    $admin_mail->Port       = MAIL_PORT;
                    $admin_mail->setFrom(MAIL_FROM_ADDRESS, 'Newsletter System');
                    $admin_mail->addAddress(MAIL_TO_ADDRESS);
                    $admin_mail->Subject = 'New Subscription: ' . $email;
                    $admin_mail->Body    = "A new user has subscribed to your newsletter:\n\nEmail: $email";
                    $admin_mail->send();
                } else {
                    $error = "Failed to send confirmation email. Please try again later.";
                }
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        } catch(Exception $e) {
            $error = "Mailer error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to Our Newsletter</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .success { color: green; margin-bottom: 20px; padding: 10px; background: #e6ffe6; border: 1px solid #b3ffb3; }
        .error { color: red; margin-bottom: 20px; padding: 10px; background: #ffe6e6; border: 1px solid #ffb3b3; }
        form { display: flex; flex-direction: column; gap: 15px; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 5px; font-weight: bold; }
        input[type="email"] { padding: 10px; font-size: 16px; border: 1px solid #ddd; border-radius: 4px; }
        input[type="submit"] { padding: 12px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        input[type="submit"]:hover { background: #0055aa; }
    </style>
</head>
<body>
    <h1>Subscribe to Our Newsletter</h1>
    
    <?php if ($message): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="subscribe.php">
        <div class="form-group">
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required placeholder="your@email.com">
        </div>
        <input type="submit" value="Subscribe">
    </form>
</body>
</html>