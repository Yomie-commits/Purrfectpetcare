<?php
/**
 * Payment Integration for Pet Care Management System
 * Supports M-Pesa and Card Payments
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
require_once 'db_connect.php';

// Get the action from POST data
$action = $_POST['action'] ?? '';

// Initialize response array
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    switch ($action) {
        // --- M-PESA PAYMENT ---
        case 'mpesa_payment':
            $amount = $_POST['amount'] ?? 0;
            $phone = $_POST['phone'] ?? '';
            $description = $_POST['description'] ?? 'Pet Care Payment';
            $user_id = $_POST['user_id'] ?? 0;
            
            if (empty($phone) || $amount <= 0) {
                throw new Exception('Invalid phone number or amount');
            }
            
            // Format phone number for M-Pesa (254XXXXXXXXX)
            $phone = formatPhoneForMpesa($phone);
            
            // Generate unique transaction reference
            $transaction_ref = 'PET' . date('YmdHis') . rand(1000, 9999);
            
            // Simulate M-Pesa STK Push (in production, integrate with actual M-Pesa API)
            $mpesa_response = simulateMpesaSTKPush($phone, $amount, $transaction_ref, $description);
            
            if ($mpesa_response['success']) {
                // Save payment record
                $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, method, status, transaction_id, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $amount, 'M-Pesa', 'Pending', $transaction_ref, $description]);
                
                $response = [
                    'success' => true,
                    'message' => 'M-Pesa payment initiated successfully',
                    'data' => [
                        'transaction_ref' => $transaction_ref,
                        'checkout_request_id' => $mpesa_response['checkout_request_id'],
                        'instructions' => 'Please check your phone for M-Pesa prompt and enter your PIN to complete payment'
                    ]
                ];
            } else {
                throw new Exception($mpesa_response['message']);
            }
            break;
            
        // --- CARD PAYMENT ---
        case 'card_payment':
            $amount = $_POST['amount'] ?? 0;
            $card_number = $_POST['card_number'] ?? '';
            $expiry_month = $_POST['expiry_month'] ?? '';
            $expiry_year = $_POST['expiry_year'] ?? '';
            $cvv = $_POST['cvv'] ?? '';
            $card_holder = $_POST['card_holder'] ?? '';
            $description = $_POST['description'] ?? 'Pet Care Payment';
            $user_id = $_POST['user_id'] ?? 0;
            
            if (empty($card_number) || empty($cvv) || $amount <= 0) {
                throw new Exception('Invalid card details or amount');
            }
            
            // Validate card details
            if (!validateCardDetails($card_number, $expiry_month, $expiry_year, $cvv)) {
                throw new Exception('Invalid card details');
            }
            
            // Generate unique transaction reference
            $transaction_ref = 'PET' . date('YmdHis') . rand(1000, 9999);
            
            // Simulate card payment processing (in production, integrate with actual payment gateway)
            $card_response = simulateCardPayment($card_number, $amount, $transaction_ref, $description);
            
            if ($card_response['success']) {
                // Save payment record
                $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, method, status, transaction_id, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $amount, 'Card', 'Completed', $transaction_ref, $description]);
                
                $response = [
                    'success' => true,
                    'message' => 'Card payment processed successfully',
                    'data' => [
                        'transaction_ref' => $transaction_ref,
                        'receipt_url' => generateReceiptUrl($transaction_ref),
                        'amount' => $amount
                    ]
                ];
            } else {
                throw new Exception($card_response['message']);
            }
            break;
            
        // --- CHECK PAYMENT STATUS ---
        case 'check_payment_status':
            $transaction_ref = $_POST['transaction_ref'] ?? '';
            
            if (empty($transaction_ref)) {
                throw new Exception('Transaction reference is required');
            }
            
            // Check payment status in database
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_id = ?");
            $stmt->execute([$transaction_ref]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                $response = [
                    'success' => true,
                    'message' => 'Payment status retrieved',
                    'data' => [
                        'status' => $payment['status'],
                        'amount' => $payment['amount'],
                        'method' => $payment['method'],
                        'transaction_id' => $payment['transaction_id'],
                        'created_at' => $payment['created_at']
                    ]
                ];
            } else {
                throw new Exception('Payment not found');
            }
            break;
            
        // --- GET PAYMENT METHODS ---
        case 'get_payment_methods':
            $response = [
                'success' => true,
                'message' => 'Payment methods retrieved',
                'data' => [
                    'mpesa' => [
                        'name' => 'M-Pesa',
                        'description' => 'Pay using M-Pesa mobile money',
                        'icon' => 'fas fa-mobile-alt',
                        'enabled' => true
                    ],
                    'card' => [
                        'name' => 'Credit/Debit Card',
                        'description' => 'Pay using Visa, Mastercard, or other cards',
                        'icon' => 'fas fa-credit-card',
                        'enabled' => true
                    ],
                    'cash' => [
                        'name' => 'Cash',
                        'description' => 'Pay in cash at the clinic',
                        'icon' => 'fas fa-money-bill-wave',
                        'enabled' => true
                    ]
                ]
            ];
            break;
            
        // --- GENERATE RECEIPT ---
        case 'generate_receipt':
            $transaction_ref = $_POST['transaction_ref'] ?? '';
            
            if (empty($transaction_ref)) {
                throw new Exception('Transaction reference is required');
            }
            
            // Get payment details
            $stmt = $pdo->prepare("SELECT p.*, u.name as user_name FROM payments p JOIN users u ON p.user_id = u.id WHERE p.transaction_id = ?");
            $stmt->execute([$transaction_ref]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                $receipt_data = generateReceiptData($payment);
                $response = [
                    'success' => true,
                    'message' => 'Receipt generated successfully',
                    'data' => $receipt_data
                ];
            } else {
                throw new Exception('Payment not found');
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ];
}

echo json_encode($response);

// --- HELPER FUNCTIONS ---

/**
 * Format phone number for M-Pesa (254XXXXXXXXX)
 */
function formatPhoneForMpesa($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If starts with 0, replace with 254
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    }
    
    // If starts with +, remove it
    if (substr($phone, 0, 1) === '+') {
        $phone = substr($phone, 1);
    }
    
    // If doesn't start with 254, add it
    if (substr($phone, 0, 3) !== '254') {
        $phone = '254' . $phone;
    }
    
    return $phone;
}

/**
 * Simulate M-Pesa STK Push (replace with actual M-Pesa API integration)
 */
function simulateMpesaSTKPush($phone, $amount, $transaction_ref, $description) {
    // In production, this would integrate with actual M-Pesa API
    // For demo purposes, we'll simulate a successful response
    
    // Simulate API delay
    usleep(500000); // 0.5 seconds
    
    // Simulate success (90% success rate for demo)
    if (rand(1, 10) <= 9) {
        return [
            'success' => true,
            'checkout_request_id' => 'ws_CO_' . date('YmdHis') . rand(100000, 999999),
            'message' => 'STK Push sent successfully'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to send STK Push. Please try again.'
        ];
    }
}

/**
 * Validate card details
 */
function validateCardDetails($card_number, $expiry_month, $expiry_year, $cvv) {
    // Remove spaces from card number
    $card_number = preg_replace('/\s/', '', $card_number);
    
    // Basic validation
    if (strlen($card_number) < 13 || strlen($card_number) > 19) {
        return false;
    }
    
    if (strlen($cvv) < 3 || strlen($cvv) > 4) {
        return false;
    }
    
    // Check expiry date
    $current_year = date('Y');
    $current_month = date('m');
    
    if ($expiry_year < $current_year || ($expiry_year == $current_year && $expiry_month < $current_month)) {
        return false;
    }
    
    // Luhn algorithm for card number validation
    return validateLuhn($card_number);
}

/**
 * Luhn algorithm for card number validation
 */
function validateLuhn($number) {
    $sum = 0;
    $length = strlen($number);
    $parity = $length % 2;
    
    for ($i = 0; $i < $length; $i++) {
        $digit = $number[$i];
        if ($i % 2 == $parity) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    
    return ($sum % 10) == 0;
}

/**
 * Simulate card payment processing (replace with actual payment gateway integration)
 */
function simulateCardPayment($card_number, $amount, $transaction_ref, $description) {
    // In production, this would integrate with actual payment gateway (Stripe, PayPal, etc.)
    // For demo purposes, we'll simulate a successful response
    
    // Simulate API delay
    usleep(800000); // 0.8 seconds
    
    // Simulate success (95% success rate for demo)
    if (rand(1, 20) <= 19) {
        return [
            'success' => true,
            'transaction_id' => $transaction_ref,
            'message' => 'Payment processed successfully'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Payment failed. Please check your card details and try again.'
        ];
    }
}

/**
 * Generate receipt URL
 */
function generateReceiptUrl($transaction_ref) {
    return 'receipt.php?ref=' . urlencode($transaction_ref);
}

/**
 * Generate receipt data
 */
function generateReceiptData($payment) {
    return [
        'receipt_number' => 'RCP' . $payment['transaction_id'],
        'date' => $payment['created_at'],
        'amount' => $payment['amount'],
        'method' => $payment['method'],
        'description' => $payment['description'],
        'customer_name' => $payment['user_name'],
        'transaction_id' => $payment['transaction_id'],
        'status' => $payment['status']
    ];
}
?> 