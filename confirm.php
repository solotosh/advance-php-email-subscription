<?php
header('Content-Type: application/json'); // Set JSON header for API responses
require_once 'config.php';

$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

$response = [
    'success' => false,
    'message' => '',
    'email' => $email
];

if (empty($email) || empty($token)) {
    $response['message'] = "Invalid confirmation link - missing parameters";
    echo json_encode($response);
    exit;
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
        
        $response['success'] = true;
        $response['message'] = "Subscription confirmed successfully!";
        
        // Send welcome email (in background)
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port       = MAIL_PORT;
            $mail->setFrom(MAIL_FROM_ADDRESS, 'Newsletter Team');
            $mail->addAddress($email);
            $mail->Subject = 'Welcome to Our Newsletter';
            $mail->Body    = "Thank you for confirming your subscription!";
            $mail->send();
        } catch (Exception $e) {
            error_log("Welcome email failed: " . $e->getMessage());
        }
    } else {
        $response['message'] = "Invalid or expired confirmation link.";
    }
} catch(PDOException $e) {
    $response['message'] = "Database error: " . $e->getMessage();
}

// Output JSON for API calls
if (isset($_GET['api'])) {
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirming Subscription...</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .status { padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { background-color: #e6ffe6; border: 1px solid #b3ffb3; }
        .error { background-color: #ffe6e6; border: 1px solid #ffb3b3; }
        .loading { background-color: #e6f3ff; border: 1px solid #b3d9ff; }
    </style>
</head>
<body>
    <div id="confirmation-status" class="status loading">
        <p>Confirming your subscription, please wait...</p>
    </div>
    
    <script>
    $(document).ready(function() {
        // Automatically process confirmation when page loads
        $.ajax({
            url: window.location.href + '&api=1',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                const statusDiv = $('#confirmation-status');
                statusDiv.removeClass('loading');
                
                if (response.success) {
                    statusDiv.addClass('success');
                    statusDiv.html(`
                        <h2>Subscription Confirmed!</h2>
                        <p>Thank you for confirming your email: ${response.email}</p>
                        <p>You will now receive our newsletter updates.</p>
                    `);
                } else {
                    statusDiv.addClass('error');
                    statusDiv.html(`
                        <h2>Confirmation Failed</h2>
                        <p>${response.message}</p>
                        <p>Please try subscribing again or contact support.</p>
                    `);
                }
            },
            error: function(xhr, status, error) {
                $('#confirmation-status').removeClass('loading').addClass('error')
                    .html(`
                        <h2>Error Occurred</h2>
                        <p>We couldn't process your confirmation at this time.</p>
                        <p>Please try again later or contact support.</p>
                    `);
            }
        });
    });
    </script>
</body>
</html>