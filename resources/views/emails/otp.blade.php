<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Redat Learning Platform</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f7f9fc;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .otp-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
            border: 2px dashed #e0e0e0;
        }
        .otp-code {
            font-size: 42px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #2d3436;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
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
        .footer a {
            color: #667eea;
            text-decoration: none;
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
        }
        .expiry-notice {
            color: #e74c3c;
            font-weight: 600;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>🔐 Redat Learning Platform</h1>
            <p>Email Verification Required</p>
        </div>

        <!-- Content -->
        <div class="content">
            <h2>Hello!</h2>
            <p>Thank you for registering with <strong>Redat Learning Platform</strong>. To complete your registration and verify your email address, please use the One-Time Password (OTP) below:</p>

            <!-- OTP Display -->
            <div class="otp-container">
                <p style="margin-top: 0; color: #666;">Your verification code:</p>
                <div class="otp-code">{{ $otp }}</div>
                <p style="margin-bottom: 0; color: #666;">Enter this code on the verification page</p>
            </div>

            <!-- Expiry Notice -->
            <div class="expiry-notice">
                ⚠️ This OTP will expire in <strong>10 minutes</strong>
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h3>📋 How to verify your email:</h3>
                <ol>
                    <li>Go to the verification page on our platform</li>
                    <li>Enter the 6-digit code shown above</li>
                    <li>Click "Verify Email" to complete the process</li>
                </ol>
                <p><strong>Note:</strong> If you didn't request this verification, please ignore this email.</p>
            </div>

            <!-- Support Info -->
            <p>If you're having trouble with the verification process, please:</p>
            <ul>
                <li>Make sure you're using the code within 10 minutes</li>
                <li>Check that you've entered the code correctly</li>
                <li>Contact our support team if issues persist</li>
            </ul>

            <p style="text-align: center;">
                <a href="https://redatlearninghub.com/verify" class="button">Go to Verification Page</a>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Redat Learning Platform</strong><br>
            Empowering education through technology</p>
            <p>
                📧 <a href="mailto:support@redatlearninghub.com">support@redatlearninghub.com</a> |
                🌐 <a href="https://redatlearninghub.com">redatlearninghub.com</a>
            </p>
            <p style="font-size: 12px; margin-top: 20px; color: #999;">
                This is an automated message. Please do not reply to this email.<br>
                © {{ date('Y') }} Redat Learning Platform. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>