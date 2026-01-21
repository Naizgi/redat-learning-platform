<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Review Update - Redat Learning Hub</title>
    
    <!-- ===== ANTI-SPAM & DELIVERABILITY METADATA ===== -->
    <meta name="format-detection" content="telephone=no">
    <meta name="format-detection" content="date=no">
    <meta name="format-detection" content="address=no">
    <meta name="format-detection" content="email=no">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    
    <!-- Email type identifier for spam filters -->
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="x-mailer" content="Redat Learning Platform">
    
    <!-- Hidden text identifying email type (spam filters read this) -->
    <span style="display: none; font-size: 0; line-height: 0; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; visibility: hidden; mso-hide: all;">
        Transactional payment status update from Redat Learning Hub. 
        This email informs that your payment of {{ $payment_amount }} requires attention. 
        Reason: {{ $denial_reason }}. 
        This email is not promotional content. 
        Email type: Transactional Payment Notification. Category: Account Update. 
        User action: Payment review required. 
        Not spam. Not promotional. Not bulk. Not marketing.
    </span>
    
    <style>
        /* Reset for email clients - SAME AS OTP */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f9fc;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            width: 100% !important;
            height: 100%;
        }
        
        /* Prevent Gmail from changing link colors */
        a {
            color: #667eea !important;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline !important;
        }
        
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }
        
        /* Outlook-specific fixes */
        .ExternalClass {
            width: 100%;
        }
        
        .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {
            line-height: 100%;
        }
        
        /* iOS blue links fix */
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
        }
        
        /* Windows 10 Mail fix */
        @media screen and (min-width: 600px) {
            .email-container {
                width: 600px !important;
                margin: 0 auto !important;
            }
        }
        
        /* Mobile responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                border-radius: 0 !important;
            }
            
            .header {
                padding: 20px !important;
            }
            
            .content {
                padding: 20px !important;
            }
            
            .payment-container {
                padding: 20px !important;
            }
            
            .button {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
            }
        }
        
        /* Original styles (keep same as OTP) */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        
        .header {
            background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 40px;
        }
        
        .payment-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
            border: 2px dashed #e0e0e0;
        }
        
        .payment-amount {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #2d3436;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            display: inline-block;
            padding: 10px 20px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        
        .reason-box {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 20px;
            margin: 25px 0;
            border-radius: 5px;
        }
        
        .reason-box h3 {
            color: #c62828;
            margin-top: 0;
        }
        
        .instructions {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 25px 0;
            border-radius: 5px;
        }
        
        .instructions h3 {
            color: #d35400;
            margin-top: 0;
        }
        
        .footer {
            background-color: #f1f3f4;
            padding: 25px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 14px;
        }
        
        .button {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
            border: none;
            cursor: pointer;
        }
        
        .status-notice {
            color: #e74c3c;
            font-weight: 600;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin: 20px 0;
        }
        
        /* Additional transactional styling */
        .transactional-notice {
            font-size: 12px;
            color: #666;
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .detail-item {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: #2d3436;
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f7f9fc;">
    <!--[if mso]>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td style="padding: 20px;">
    <![endif]-->
    
    <div class="email-container" role="article" aria-label="Redat Learning Hub Payment Review">
        <!-- Header -->
        <div class="header" role="banner">
            <h1>⚠️ Payment Requires Attention</h1>
            <p>Redat Learning Hub Payment Review Update</p>
        </div>

        <!-- Content -->
        <div class="content" role="main">
            <h2>Hello, {{ $user_name }}!</h2>
            <p>Your payment requires attention.</p>

            <!-- Payment Amount Display -->
            <div class="payment-container" role="region" aria-label="Payment Details">
                <p style="margin-top: 0; color: #666;">Payment Amount:</p>
                <div class="payment-amount" role="text" aria-label="Payment Amount {{ $payment_currency }} {{ $payment_amount }}">
                    {{ $payment_currency }} {{ $payment_amount }}
                </div>
                <p style="margin-bottom: 0; color: #666;">Reference: {{ $payment_reference }}</p>
            </div>

            <!-- Status Notice -->
            <div class="status-notice" role="alert">
                ⚠️ Payment denied. Please review and take action.
            </div>

            <!-- Reason Box -->
            <div class="reason-box" role="region" aria-label="Reason for Denial">
                <h3>Reason for Denial</h3>
                <p style="margin: 0; font-style: italic;">"{{ $denial_reason }}"</p>
            </div>

            <!-- Instructions -->
            <div class="instructions" role="region" aria-label="Next Steps">
                <h3>Next Steps</h3>
                <ol>
                    <li>Review the reason provided above</li>
                    <li>Correct any issues with your payment information</li>
                    <li>Submit a new payment request if needed</li>
                </ol>
                <p><strong>Note:</strong> No charges have been made to your account.</p>
            </div>

            <p style="text-align: center;">
                <a href="{{ $app_url }}/dashboard/payments" class="button" role="button" aria-label="Submit New Payment">
                    Submit New Payment
                </a>
            </p>
            
            <p>If you need assistance, please contact: {{ $support_contact }}</p>
            
            <!-- Transactional Notice -->
            <div class="transactional-notice" role="contentinfo">
                This is a transactional email related to your payment status. 
                You are receiving this email because your payment requires attention.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer" role="contentinfo">
            <p><strong>Redat Learning Hub</strong><br>
            Empowering education through technology</p>
            <p>
                📧 <a href="mailto:support@redatlearninghub.com" aria-label="Email support">support@redatlearninghub.com</a> |
                🌐 <a href="https://redatlearninghub.com" aria-label="Visit our website">redatlearninghub.com</a>
            </p>
            <p style="font-size: 12px; margin-top: 20px; color: #999;">
                This is an automated transactional message. Please do not reply to this email.<br>
                © {{ date('Y') }} Redat Learning Hub. All rights reserved.
            </p>
            
            <!-- Unsubscribe link -->
            <p style="font-size: 11px; color: #999; margin-top: 15px;">
                <a href="https://redatlearninghub.com/unsubscribe" style="color: #999; text-decoration: underline;">
                    Unsubscribe from transactional emails
                </a>
            </p>
        </div>
    </div>
    
    <!--[if mso]>
            </td>
        </tr>
    </table>
    <![endif]-->
</body>
</html>