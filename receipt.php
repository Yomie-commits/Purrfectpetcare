<?php
/**
 * Receipt Generation for Pet Care Management System
 */

// Database connection
require_once 'db_connect.php';

// Get transaction reference
$transaction_ref = $_GET['ref'] ?? '';

if (empty($transaction_ref)) {
    die('Transaction reference is required');
}

// Get payment details
$stmt = $pdo->prepare("SELECT p.*, u.name as user_name, u.email as user_email FROM payments p JOIN users u ON p.user_id = u.id WHERE p.transaction_id = ?");
$stmt->execute([$transaction_ref]);
$payment = $stmt->fetch();

if (!$payment) {
    die('Payment not found');
}

// Generate receipt number
$receipt_number = 'RCP' . $payment['transaction_id'];

// Format amount
$amount_formatted = 'KSh ' . number_format($payment['amount'], 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Purrfect Pet Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --orange: #FF7300;
            --cream: #FFF5E1;
            --teal: #3AAFA9;
            --charcoal: #111318;
            --grey: #C0C0C0;
            --white: #FFFFFF;
        }
        
        body {
            background-color: var(--cream);
            font-family: 'Arial', sans-serif;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .receipt-header {
            background: linear-gradient(135deg, var(--orange), var(--teal));
            color: var(--white);
            padding: 30px;
            text-align: center;
        }
        
        .receipt-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .receipt-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .receipt-body {
            padding: 30px;
        }
        
        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            color: var(--teal);
            font-size: 1.2rem;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--orange);
            padding-bottom: 5px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--charcoal);
        }
        
        .info-value {
            color: var(--teal);
        }
        
        .amount-section {
            background: var(--cream);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .amount-label {
            font-size: 1.1rem;
            color: var(--charcoal);
            margin-bottom: 10px;
        }
        
        .amount-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--orange);
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .receipt-footer {
            background: var(--charcoal);
            color: var(--white);
            padding: 20px;
            text-align: center;
        }
        
        .receipt-footer p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--orange);
            color: var(--white);
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .print-button:hover {
            background: var(--teal);
            transform: translateY(-2px);
        }
        
        @media print {
            .print-button {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .receipt-container {
                box-shadow: none;
                margin: 0;
            }
        }
        
        @media (max-width: 768px) {
            .receipt-info {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .receipt-header h1 {
                font-size: 1.5rem;
            }
            
            .amount-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>
    
    <div class="receipt-container">
        <div class="receipt-header">
            <h1><i class="fas fa-paw"></i> Purrfect Pet Care</h1>
            <p>Payment Receipt</p>
        </div>
        
        <div class="receipt-body">
            <div class="receipt-info">
                <div class="info-section">
                    <h3><i class="fas fa-receipt"></i> Receipt Details</h3>
                    <div class="info-item">
                        <span class="info-label">Receipt Number:</span>
                        <span class="info-value"><?php echo $receipt_number; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Transaction ID:</span>
                        <span class="info-value"><?php echo $payment['transaction_id']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date:</span>
                        <span class="info-value"><?php echo date('F j, Y', strtotime($payment['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Time:</span>
                        <span class="info-value"><?php echo date('g:i A', strtotime($payment['created_at'])); ?></span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-user"></i> Customer Details</h3>
                    <div class="info-item">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($payment['user_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($payment['user_email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value"><?php echo $payment['method']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-badge status-<?php echo strtolower($payment['status']); ?>">
                                <?php echo $payment['status']; ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="amount-section">
                <div class="amount-label">Total Amount Paid</div>
                <div class="amount-value"><?php echo $amount_formatted; ?></div>
            </div>
            
            <?php if (!empty($payment['description'])): ?>
            <div class="info-section">
                <h3><i class="fas fa-info-circle"></i> Payment Description</h3>
                <div class="info-item">
                    <span class="info-label">Description:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['description']); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 text-center">
                <p style="color: var(--grey); font-size: 0.9rem;">
                    <i class="fas fa-shield-alt"></i> This receipt serves as proof of payment for the services rendered.
                </p>
                <p style="color: var(--grey); font-size: 0.9rem;">
                    Thank you for choosing Purrfect Pet Care!
                </p>
            </div>
        </div>
        
        <div class="receipt-footer">
            <p><strong>Purrfect Pet Care</strong></p>
            <p>Moi Avenue, Nairobi, Kenya</p>
            <p>Phone: +254 792 002 230 | Email: info@purrfectpetcare.com</p>
            <p>Website: www.purrfectpetcare.com</p>
        </div>
    </div>
    
    <script>
        // Auto-print on load (optional)
        // window.onload = function() { window.print(); }
        
        // Add download functionality
        function downloadReceipt() {
            window.print();
        }
    </script>
</body>
</html> 