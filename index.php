<?php
require 'vendor/autoload.php';
require 'config.php'; // Your existing config file with DB and email settings

// Initialize M-Pesa API credentials (replace with your actual credentials)
define('MPESA_CONSUMER_KEY', '4zXL5EWLLwlgXgyDNUyL3nAGfjorAEu44E1IzZwAw7TENL6m');
define('MPESA_CONSUMER_SECRET', 'Whg6vSG0NeGt5BGv764z3RgwWO74a7a3OVJDqoayln2TfGcrfB5XYAnmd5XZJBCf');
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
define('MPESA_SHORTCODE', '174379');
define('MPESA_CALLBACK_URL', BASE_URL . 'https://89d3-105-160-84-48.ngrok-free.app/callback.php');

// Function to generate access token
function getAccessToken() {
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    
    $ch = curl_init('https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response)->access_token;
}

// Function to initiate STK push
function initiateSTKPush($phone, $amount, $reference) {
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    $curl_post_data = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $reference,
        'TransactionDesc' => 'Payment for services'
    ];
    
    $access_token = getAccessToken();
    $ch = curl_init('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone'], $_POST['amount'])) {
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    $amount = (float)$_POST['amount'];
    $reference = 'INV-' . time();
    
    // Validate input
    if (strlen($phone) !== 12 || !preg_match('/^254/', $phone)) {
        die("Invalid phone number format. Use 2547XXXXXXXX");
    }
    
    if ($amount <= 0) {
        die("Amount must be greater than 0");
    }
    
    // Initiate STK push
    $response = initiateSTKPush($phone, $amount, $reference);
    
    if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
        // Save transaction to database
        try {
            $stmt = $pdo->prepare("INSERT INTO mpesa_transactions 
                                  (phone_number, amount, merchant_request_id, checkout_request_id) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $phone,
                $amount,
                $response['MerchantRequestID'],
                $response['CheckoutRequestID']
            ]);
            
            echo "<p>STK push initiated successfully! Please check your phone to complete the payment.</p>";
        } catch(PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    } else {
        die("Failed to initiate STK push: " . ($response['errorMessage'] ?? 'Unknown error'));
    }
}

// Function to send email to all users
function sendEmailToAllUsers($subject, $message) {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT email FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Configuration (from your config.php)
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->setFrom(MAIL_FROM_ADDRESS, 'M-Pesa Payment System');
        
        foreach ($users as $email) {
            try {
                $mail->clearAddresses();
                $mail->addAddress($email);
                $mail->Subject = $subject;
                $mail->Body    = $message;
                $mail->send();
            } catch (Exception $e) {
                error_log("Failed to send email to $email: " . $mail->ErrorInfo);
            }
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa STK Push</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        form { display: flex; flex-direction: column; gap: 15px; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 5px; font-weight: bold; }
        input { padding: 10px; font-size: 16px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 12px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0055aa; }
    </style>
</head>
<body>
    <h1>M-Pesa STK Push Payment</h1>
    <form method="POST">
        <div class="form-group">
            <label for="phone">Phone Number (2547XXXXXXXX):</label>
            <input type="text" id="phone" name="phone" placeholder="254712345678" required>
        </div>
        <div class="form-group">
            <label for="amount">Amount (KSh):</label>
            <input type="number" id="amount" name="amount" min="1" step="1" required>
        </div>
        <button type="submit">Initiate Payment</button>
    </form>
</body>
</html>