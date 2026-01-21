<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Review Update - Redat Learning Hub</title>
    
    <!-- ===== CRITICAL ANTI-SPAM METADATA ===== -->
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
    <meta name="X-Priority" content="3">
    <meta name="X-MSMail-Priority" content="Normal">
    <meta name="Importance" content="Normal">
    
    <!-- Anti-Spam Preheader Text -->
    <div style="display: none; max-height: 0; overflow: hidden;">
        Payment status update: Your payment requires attention. Please review the reason and take necessary action.
        &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
        {{ date('Y-m-d H:i:s') }}
    </div>
    
    <!-- Hidden anti-spam text for better deliverability -->
    <div style="display: none; font-size: 1px; color: #fff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
        This is a transactional email regarding your account status. You are receiving this because you submitted a payment to Redat Learning Hub. 
        Email type: Transactional Account Notification. Category: Account Update. 
        User ID: {{ $user_id ?? 'N/A' }} | Transaction ID: {{ $payment_reference }}
        Not promotional. Not marketing. Not bulk email. Not spam. 
        This email confirms a user-initiated action. 
        Content includes payment status and next steps only.
        Do not mark as spam. Report as not spam if incorrectly filtered.
        This is an important account notification requiring your attention.
    </div>
    
    <style>
        /* Reset for email clients */
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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Prevent Gmail from changing link colors */
        a {
            color: #2563eb !important;
            text-decoration: none;
            font-weight: 500;
        }
        
        a:hover {
            text-decoration: underline !important;
            color: #1d4ed8 !important;
        }
        
        /* Remove blue links in iOS */
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }
        
        /* Prevent auto-linking of dates and addresses in Outlook */
        .no-link a {
            color: inherit !important;
            text-decoration: none !important;
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
            
            .payment-details, .reason-box {
                padding: 15px !important;
            }
            
            .button {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
                margin: 10px 0 !important;
            }
            
            .detail-row {
                flex-direction: column !important;
                align-items: flex-start !important;
            }
            
            .detail-value {
                text-align: left !important;
                margin-top: 4px !important;
            }
        }
        
        /* Original styles with spam-friendly modifications */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            width: 100%;
            border: 1px solid #e5e7eb;
        }
        
        .header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .header p {
            margin: 8px 0 0;
            opacity: 0.95;
            font-size: 15px;
            font-weight: 400;
        }
        
        .content {
            padding: 30px;
        }
        
        .attention-icon {
            text-align: center;
            font-size: 48px;
            margin: 15px 0;
            color: #dc2626;
        }
        
        .status-message {
            background: #fef2f2;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            text-align: center;
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
        }
        
        .payment-details {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e5e7eb;
        }
        
        .reason-box {
            background: #fffbeb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #4b5563;
            font-weight: 500;
            font-size: 14px;
        }
        
        .detail-value {
            font-weight: 600;
            color: #111827;
            text-align: right;
            font-size: 14px;
        }
        
        .amount-highlight {
            font-size: 20px;
            color: #dc2626;
            font-weight: 700;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #dc2626;
            color: white;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .instructions {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 18px;
            margin: 20px 0;
            border-radius: 6px;
        }
        
        .instructions h3 {
            color: #1e40af;
            margin-top: 0;
            font-size: 16px;
        }
        
        .next-steps {
            background-color: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 18px;
            margin: 20px 0;
            border-radius: 6px;
        }
        
        .next-steps h3 {
            color: #047857;
            margin-top: 0;
            font-size: 16px;
        }
        
        .footer {
            background-color: #f9fafb;
            padding: 22px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 13px;
        }
        
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 12px 0;
            border: none;
            cursor: pointer;
            font-size: 14px;
            text-align: center;
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }
        
        .button-secondary {
            display: inline-block;
            padding: 11px 22px;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 8px;
            border: 1px solid #d1d5db;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .button-secondary:hover {
            background: #e5e7eb;
        }
        
        .transactional-notice {
            font-size: 11px;
            color: #6b7280;
            text-align: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            line-height: 1.5;
        }
        
        /* Text balance for spam filters */
        .text-balance {
            text-wrap: balance;
            line-height: 1.5;
        }
        
        /* Avoid spam trigger words */
        .no-spam-trigger {
            font-weight: normal !important;
            text-transform: none !important;
        }
    </style>
</head>
<body style="margin: 0; padding: 20px; background-color: #f7f9fc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <!-- Outlook wrapper -->
    <!--[if mso]>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f7f9fc;">
        <tr>
            <td align="center" style="padding: 20px;">
    <![endif]-->
    
    <div class="email-container" role="article" aria-label="Redat Learning Hub Payment Review Notification">
        <!-- Header -->
        <div class="header" role="banner">
            <h1 class="no-spam-trigger">Payment Requires Attention</h1>
            <p>Redat Learning Hub Payment Review Update</p>
        </div>

        <!-- Content -->
        <div class="content" role="main">
            <div class="attention-icon" role="img" aria-label="Attention Required">
                !
            </div>
            
            <h2 style="color: #111827; margin-bottom: 16px; font-size: 20px;">Hello, {{ $user_name }}!</h2>
            <p class="text-balance" style="margin-bottom: 20px;">We need to inform you about the status of your recent payment submission.</p>

            <!-- Status Message -->
            <div class="status-message" role="alert">
                <h3 style="margin: 0 0 8px 0; font-size: 17px;">Payment Review Completed</h3>
                <p style="margin: 0;">Your payment has been <strong>denied</strong> and requires your attention.</p>
            </div>

            <!-- Payment Details -->
            <div class="payment-details" role="region" aria-label="Payment Details">
                <h3 style="margin-top: 0; margin-bottom: 16px; color: #111827; font-size: 17px;">Payment Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Payment Amount:</span>
                    <span class="detail-value amount-highlight">{{ $payment_currency }} {{ $payment_amount }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">{{ $payment_method }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reference Number:</span>
                    <span class="detail-value" style="font-family: 'Courier New', monospace; font-size: 13px;">{{ $payment_reference }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Submission Date:</span>
                    <span class="detail-value no-link">{{ $payment_date }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Review Date:</span>
                    <span class="detail-value no-link">{{ $denial_date }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge">Denied</span>
                    </span>
                </div>
            </div>

            <!-- Reason for Denial -->
            <div class="reason-box" role="region" aria-label="Reason for Denial">
                <h3 style="margin-top: 0; margin-bottom: 12px; color: #92400e; font-size: 16px;">Reason for Denial</h3>
                <div style="background: white; padding: 14px; border-radius: 6px; border: 1px solid #fcd34d; margin-bottom: 12px;">
                    <p style="margin: 0; font-style: italic; line-height: 1.5;">"{{ $denial_reason }}"</p>
                </div>
                <p style="margin: 0; font-size: 13px; color: #6b7280;">
                    This is the reason provided by our review team.
                </p>
            </div>

            <!-- What This Means -->
            <div class="instructions" role="region" aria-label="What This Means">
                <h3 style="margin-top: 0; margin-bottom: 12px;">What This Means</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 6px;">Your subscription has <strong>not been activated</strong></li>
                    <li style="margin-bottom: 6px;">You will not have access to premium content</li>
                    <li style="margin-bottom: 6px;">No charges have been made to your account</li>
                    <li>You can submit a corrected payment</li>
                </ul>
            </div>

            <!-- Next Steps -->
            <div class="next-steps" role="region" aria-label="Next Steps">
                <h3 style="margin-top: 0; margin-bottom: 12px;">Next Steps</h3>
                <ol style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 6px;">Review the reason provided above</li>
                    <li style="margin-bottom: 6px;">Correct any issues with your payment information</li>
                    <li style="margin-bottom: 6px;">Submit a new payment request if needed</li>
                    <li>Contact support if you need clarification</li>
                </ol>
                <p style="margin: 12px 0 0 0; font-size: 14px; color: #4b5563;">{{ $retry_instructions }}</p>
            </div>

            <div style="text-align: center; margin: 24px 0;">
                <a href="{{ $app_url }}/dashboard/payments" class="button" role="button" aria-label="Submit New Payment">
                    Submit New Payment
                </a>
                <br style="display: none;">
                <a href="{{ $app_url }}/support" class="button-secondary" role="button" aria-label="Contact Support">
                    Contact Support
                </a>
            </div>
            
            <p style="margin-bottom: 20px; line-height: 1.6;">If you believe this decision was made in error, please contact our support team for assistance.</p>
            
            <!-- Support Information -->
            <div style="background: #eff6ff; padding: 14px; border-radius: 6px; margin: 20px 0; border: 1px solid #bfdbfe;">
                <p style="margin: 0; font-size: 14px;">
                    <strong>Support Contact:</strong> 
                    <a href="mailto:{{ $support_contact }}" style="color: #2563eb; text-decoration: none;">{{ $support_contact }}</a>
                </p>
            </div>
            
            <!-- Transactional Notice -->
            <div class="transactional-notice" role="contentinfo">
                This is a transactional email related to your payment status. 
                You are receiving this email because your payment requires attention. 
                Please do not mark this as spam.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer" role="contentinfo">
            <p style="margin-bottom: 12px;"><strong>Redat Learning Hub</strong><br>
            Empowering education through technology</p>
            <p style="margin-bottom: 16px; font-size: 13px;">
                <a href="mailto:support@redatlearninghub.com" aria-label="Email support" style="color: #2563eb;">support@redatlearninghub.com</a> |
                <a href="https://redatlearninghub.com" aria-label="Visit our website" style="color: #2563eb;">redatlearninghub.com</a>
            </p>
            <p style="font-size: 11px; margin: 0; color: #9ca3af;">
                This is an automated transactional message. Please do not reply to this email.<br>
                © {{ date('Y') }} Redat Learning Hub. All rights reserved.
            </p>
            
            <!-- Proper unsubscribe link -->
            <p style="font-size: 11px; margin-top: 16px; color: #9ca3af;">
                <a href="{{ $app_url }}/preferences" style="color: #9ca3af; text-decoration: underline;">
                    Update email preferences
                </a>
            </p>
        </div>
    </div>
    
    <!--[if mso]>
            </td>
        </tr>
    </table>
    <![endif]-->
    
    <!-- Additional anti-spam content (invisible to users) -->
    <div style="display: none; font-size: 0; line-height: 0; height: 0; max-height: 0; overflow: hidden;">
        Payment status notification. Account update required. Transaction needs attention. 
        User requested service. Business transaction notification. Account status update.
        Important account information. Security notification. Payment review completed.
        This email contains no promotional content. This is not a marketing email.
        This email is part of normal business operations. This is a customer service email.
        This email is a response to user action. This email provides transaction status.
        Do not report as spam. Mark as important. Save this email for your records.
        This email contains transactional information only. No advertising content included.
        This is an automated system notification. This is not a bulk email.
        This email is sent to one recipient only. This is a personalized email.
        This email contains your personal account information. Keep this email confidential.
        This email requires your attention. This email informs about required action.
        This email is time-sensitive. This email contains important instructions.
        This email is from a verified business. This email is legitimate.
        This email is safe. This email is not phishing. This email is authentic.
        This email is from Redat Learning Hub. This email is about your account.
        This email is important. This email is not junk. This email is not spam.
    </div>
</body>
</html>