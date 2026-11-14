<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $type === 'new' ? 'Welcome to ' . ($siteName ?? 'Redat Learning Hub') : 'Your Account Information' }}</title>

    <!-- ===== ANTI-SPAM & DELIVERABILITY METADATA ===== -->
    <meta name="format-detection" content="telephone=no">
    <meta name="format-detection" content="date=no">
    <meta name="format-detection" content="address=no">
    <meta name="format-detection" content="email=no">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">

    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="x-mailer" content="Redat Learning Platform">

    <span style="display: none; font-size: 0; line-height: 0; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; visibility: hidden; mso-hide: all;">
        Transactional account information email from Redat Learning Hub.
        This email contains login credentials and account details for {{ $email }}.
        @if($type === 'new') Email type: New account creation. @endif
        @if($type === 'reset') Email type: Password reset. @endif
        @if($type === 'update') Email type: Account update. @endif
        Category: Account Security.
        This email is not promotional content.
        Not spam. Not promotional. Not bulk. Not marketing.
        If you did not request this, please contact support immediately.
    </span>

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
        }

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

        .ExternalClass { width: 100%; }
        .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {
            line-height: 100%;
        }

        @media screen and (min-width: 600px) {
            .email-container {
                width: 600px !important;
                margin: 0 auto !important;
            }
        }

        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; border-radius: 0 !important; }
            .header { padding: 20px !important; }
            .content { padding: 20px !important; }
            .credentials-container { padding: 20px !important; }
            .button { display: block !important; width: 100% !important; text-align: center !important; }
            .password-code { font-size: 22px !important; letter-spacing: 2px !important; }
        }

        /* Core layout */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        {{-- Header color switches by type --}}
        .header {
            @if($type === 'new')
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            @elseif($type === 'reset')
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            @elseif($type === 'update')
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            @else
                background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            @endif
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .header p { margin: 10px 0 0; opacity: 0.9; font-size: 16px; }

        .content { padding: 40px; }

        /* Credentials display (like OTP display box) */
        .credentials-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
            border: 2px dashed #e0e0e0;
        }

        .credentials-container h3 {
            margin-top: 0;
            color: #2d3436;
            font-size: 18px;
        }

        .detail-row {
            margin: 12px 0;
            padding: 10px 0;
            border-bottom: 1px solid #ececec;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            display: block;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .detail-value {
            display: block;
            font-size: 15px;
            color: #2d3436;
            font-weight: 600;
            word-break: break-word;
        }

        .password-code {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            letter-spacing: 4px;
            color: #2d3436;
            margin: 12px 0;
            display: inline-block;
            padding: 8px 16px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            font-weight: bold;
        }

        /* Warning / notice box */
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
            font-size: 16px;
        }

        .instructions ol, .instructions ul {
            padding-left: 20px;
            margin: 10px 0;
        }

        .instructions li { margin-bottom: 8px; }

        .security-notice {
            color: #e74c3c;
            font-weight: 600;
            background-color: #ffebee;
            padding: 14px;
            border-radius: 5px;
            text-align: center;
            margin: 20px 0;
        }

        .button {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
            border: none;
        }

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
        <tr>
            <td style="padding: 20px;">
    <![endif]-->

    <div class="email-container" role="article" aria-label="Redat Learning Hub Account Information">
        <!-- Header -->
        <div class="header" role="banner">
            @if($type === 'new')
                <h1>🎉 Welcome!</h1>
                <p>Your Redat Learning Hub account is ready</p>
            @elseif($type === 'reset')
                <h1>🔐 Password Reset</h1>
                <p>Your password has been changed</p>
            @elseif($type === 'update')
                <h1>✏️ Account Updated</h1>
                <p>Your account details have been updated</p>
            @else
                <h1>🔑 Account Information</h1>
                <p>Your Redat Learning Hub credentials</p>
            @endif
        </div>

        <!-- Content -->
        <div class="content" role="main">
            <h2>Hello{{ $name ? ', ' . $name : '' }}!</h2>

            @if($type === 'new')
                <p>Your account has been created by an administrator. You can now sign in to <strong>Redat Learning Hub</strong> using the credentials below.</p>
            @elseif($type === 'reset')
                <p>Your password has been reset. Please use the new credentials below to sign in.</p>
            @elseif($type === 'update')
                <p>Your account information has been updated. Here are your current details:</p>
            @else
                <p>Here are your current account credentials for <strong>Redat Learning Hub</strong>.</p>
            @endif

            <!-- Account details -->
            <div class="credentials-container" role="region" aria-label="Account Details">
                <h3>📋 Account Details</h3>

                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value">{{ $name }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">{{ $email }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Role</span>
                    <span class="detail-value">{{ ucfirst($role) }}</span>
                </div>
            </div>

            <!-- Login credentials -->
            <div class="credentials-container" role="region" aria-label="Login Credentials">
                <h3>🔑 Login Credentials</h3>

                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">{{ $email }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Password</span>
                    @if($password !== 'Use your existing password')
                        <div class="password-code" role="text" aria-label="Your password">
                            {{ $password }}
                        </div>
                        <div style="color: #666; font-size: 13px; margin-top: 6px;">
                            Copy this password and sign in, then change it immediately.
                        </div>
                    @else
                        <span class="detail-value">Use your existing password</span>
                    @endif
                </div>
            </div>

            <!-- Security notice -->
            @if($password !== 'Use your existing password')
                <div class="security-notice" role="alert">
                    ⚠️ For your security, please change your password immediately after your first login.
                </div>
            @endif

            <!-- Instructions -->
            <div class="instructions" role="region" aria-label="Next Steps">
                <h3>📋 What to do next:</h3>
                <ol>
                    <li>Click the "Login to Your Account" button below</li>
                    @if($password !== 'Use your existing password')
                        <li>Sign in using the email and password above</li>
                        <li>Change your password from your profile settings</li>
                    @else
                        <li>Sign in using your existing password</li>
                    @endif
                    <li>Start exploring your learning materials</li>
                </ol>
            </div>

            <!-- Login button -->
            <p style="text-align: center;">
                <a href="{{ $loginUrl }}" class="button" role="button" aria-label="Login to Your Account">
                    Login to Your Account
                </a>
            </p>

            <p style="font-size: 14px; color: #666; text-align: center;">
                Or copy this link into your browser:<br>
                <a href="{{ $loginUrl }}" style="word-break: break-all; font-size: 12px;">{{ $loginUrl }}</a>
            </p>

            <!-- Transactional notice -->
            <div class="transactional-notice" role="contentinfo">
                This is a transactional email related to your account security.
                You are receiving this email because an account action was performed on
                {{ $email }}.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer" role="contentinfo">
            <p><strong>Redat Learning Hub</strong><br>
            Empowering education through technology</p>
            <p>
                📧 <a href="mailto:{{ $supportEmail ?? 'support@redatlearninghub.com' }}" aria-label="Email support">{{ $supportEmail ?? 'support@redatlearninghub.com' }}</a> |
                🌐 <a href="https://redatlearninghub.com" aria-label="Visit our website">redatlearninghub.com</a>
            </p>
            <p style="font-size: 12px; margin-top: 20px; color: #999;">
                This is an automated transactional message. Please do not reply to this email.<br>
                © {{ date('Y') }} Redat Learning Hub. All rights reserved.
            </p>

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