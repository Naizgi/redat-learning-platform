@php
    $rName    = $name ?? 'there';
    $rEmail   = $email ?? '';
    $rOtp     = $otp ?? '------';
    $rExpires = $expiresInMinutes ?? 10;
    $rSite    = $siteName ?? 'Redat Learning Hub';
    $rSupport = $supportEmail ?? 'support@redatlearninghub.com';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code - {{ $rSite }}</title>

    <meta name="format-detection" content="telephone=no">
    <meta name="format-detection" content="date=no">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="x-mailer" content="Redat Learning Platform">

    <span style="display: none; font-size: 0; line-height: 0; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; visibility: hidden; mso-hide: all;">
        Transactional password reset OTP email from {{ $rSite }}. Code: {{ $rOtp }}.
        This email is not promotional content. Category: Transactional.
        Expires in {{ $rExpires }} minutes.
    </span>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f9fc;
            width: 100% !important;
        }

        a { color: #667eea !important; text-decoration: none; }
        a:hover { text-decoration: underline !important; }

        @media screen and (min-width: 600px) {
            .email-container { width: 600px !important; margin: 0 auto !important; }
        }

        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; border-radius: 0 !important; }
            .header { padding: 20px !important; }
            .content { padding: 20px !important; }
            .otp-code { font-size: 32px !important; letter-spacing: 6px !important; }
        }

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
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .header p { margin: 10px 0 0; opacity: 0.9; font-size: 16px; }

        .content { padding: 40px; }

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
            display: inline-block;
            padding: 10px 20px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }

        .expiry-notice {
            color: #e74c3c;
            font-weight: 600;
            background-color: #ffebee;
            padding: 12px;
            border-radius: 5px;
            text-align: center;
            margin: 20px 0;
        }

        .instructions {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 25px 0;
            border-radius: 5px;
        }

        .instructions h3 { color: #d35400; margin-top: 0; font-size: 16px; }
        .instructions ol { padding-left: 20px; margin: 10px 0; }
        .instructions li { margin-bottom: 8px; }

        .footer {
            background-color: #f1f3f4;
            padding: 25px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 14px;
        }

        .transactional-notice {
            font-size: 12px;
            color: #666;
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f7f9fc;">
    <!--[if mso]>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr><td style="padding: 20px;">
    <![endif]-->

    <div class="email-container" role="article" aria-label="{{ $rSite }} Password Reset Code">
        <div class="header" role="banner">
            <h1>🔐 Password Reset</h1>
            <p>Your one-time verification code</p>
        </div>

        <div class="content" role="main">
            <h2>Hello{{ $rName !== 'there' ? ', ' . $rName : '' }}!</h2>
            <p>We received a request to reset the password for your <strong>{{ $rSite }}</strong> account.</p>

            <div class="otp-container" role="region" aria-label="Password Reset Code">
                <p style="margin-top: 0; color: #666;">Your password reset code is:</p>
                <div class="otp-code" role="text" aria-label="Your OTP Code is {{ implode(' ', str_split($rOtp)) }}">
                    {{ $rOtp }}
                </div>
                <p style="margin-bottom: 0; color: #666;">Enter this code on the reset page</p>
            </div>

            <div class="expiry-notice" role="alert">
                ⚠️ This code will expire in <strong>{{ $rExpires }} minutes</strong>
            </div>

            <div class="instructions" role="region" aria-label="Instructions">
                <h3>📋 How to reset your password:</h3>
                <ol>
                    <li>Return to the password reset page on our platform</li>
                    <li>Enter the 6-digit code shown above</li>
                    <li>Create a new password</li>
                    <li>Sign in with your new password</li>
                </ol>
                <p><strong>Note:</strong> If you did not request this password reset, please ignore this email. Your password will not change.</p>
            </div>

            <div class="transactional-notice">
                This is a transactional email related to your account security.
                You are receiving this email because a password reset was requested for {{ $rEmail }}.
            </div>
        </div>

        <div class="footer">
            <p><strong>{{ $rSite }}</strong><br>Empowering education through technology</p>
            <p>
                📧 <a href="mailto:{{ $rSupport }}">{{ $rSupport }}</a> |
                🌐 <a href="https://redatlearninghub.com">redatlearninghub.com</a>
            </p>
            <p style="font-size: 12px; margin-top: 20px; color: #999;">
                This is an automated transactional message. Please do not reply to this email.<br>
                © {{ date('Y') }} {{ $rSite }}. All rights reserved.
            </p>
        </div>
    </div>

    <!--[if mso]>
        </td></tr></table>
    <![endif]-->
</body>
</html>