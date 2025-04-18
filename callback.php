<?php
require 'config.php';

// Get the callback data
$callbackJSON = file_get_contents('php://input');
$callbackData = json_decode($callbackJSON, true);

// Log the callback for debugging
file_put_contents('mpesa_callback.log', $callbackJSON . "\n", FILE_APPEND);

// Check if this is a valid callback
if (isset($callbackData['Body']['stkCallback']['ResultCode'])) {
    $resultCode = $callbackData['Body']['stkCallback']['ResultCode'];
    $checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'];
    $merchantRequestID = $callbackData['Body']['stkCallback']['MerchantRequestID'];
    
    if ($resultCode == 0) {
        // Payment was successful
        $amount = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
        $mpesaReceiptNumber = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
        $phoneNumber = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
        
        // Update transaction in database
        try {
            $stmt = $pdo->prepare("UPDATE mpesa_transactions 
                                  SET status = 'completed', transaction_id = ?
                                  WHERE checkout_request_id = ? AND merchant_request_id = ?");
            $stmt->execute([$mpesaReceiptNumber, $checkoutRequestID, $merchantRequestID]);
            
            // Send email to all users
            $subject = "New M-Pesa Payment Received";
            $message = "A new payment of KSh $amount has been received from $phoneNumber.\n\n";
            $message .= "M-Pesa Transaction ID: $mpesaReceiptNumber";
            
            sendEmailToAllUsers($subject, $message);
            
            // Respond to M-Pesa
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Failed']);
        }
    } else {
        // Payment failed
        try {
            $stmt = $pdo->prepare("UPDATE mpesa_transactions 
                                  SET status = 'failed'
                                  WHERE checkout_request_id = ? AND merchant_request_id = ?");
            $stmt->execute([$checkoutRequestID, $merchantRequestID]);
            
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Failed']);
        }
    }
} else {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback']);
}
?>