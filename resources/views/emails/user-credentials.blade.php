@php
    // ── Resolve raw inputs (any of these forms can arrive) ────────────
    $rawType = $type ?? $data['type'] ?? 'new';
    $name_   = $name    ?? $user_name    ?? $data['name']         ?? 'there';
    $email_  = $email   ?? $user_email   ?? $data['email']        ?? '';
    $role_   = $role    ?? $user_role    ?? $data['role']         ?? 'student';
    $pw_     = $password ?? $temp_password ?? $data['password']   ?? null;
    $otp_    = $otp     ?? $data['otp']  ?? null;
    $exp_    = $expiresInMinutes ?? $expires_in_minutes ?? $data['expires_in_minutes'] ?? 10;
    $login_  = $loginUrl ?? $login_url   ?? $data['login_url']    ?? (config('app.url') . '/login');
    $reset_  = $resetUrl ?? $reset_url   ?? $data['reset_url']    ?? (config('app.url') . '/reset-password');
    $supp_   = $supportEmail ?? $support_email ?? $data['support_email'] ?? 'support@redatlearninghub.com';
    $site_   = $siteName ?? $site_name   ?? $data['site_name']    ?? 'Redat Learning Hub';

    // ── Map controller vocabulary → blade vocabulary ───────────────────
    // AdminUserController sends: admin_created, password_reset, email_update,
    // account_update, admin_updated, credentials_reminder.
    // PasswordResetController sends: password_reset_otp.
    // All are normalized to the four canonical types below.
    $rType = match ($rawType) {
        'admin_created'                                   => 'new',
        'password_reset'                                  => 'reset',
        'email_update', 'account_update', 'admin_updated' => 'update',
        'credentials_reminder'                            => 'reminder',
        'password_reset_otp'                              => 'password_reset_otp',
        'new', 'update', 'reset', 'reminder'              => $rawType,
        default                                           => 'update',
    };

    // ── Expose the canonical variables the template uses ─────────────
    $rName     = $name_;
    $rEmail    = $email_;
    $rRole     = $role_;
    $rPassword = $pw_;
    $rOtp      = $otp_;
    $rExpires  = $exp_;
    $rLoginUrl = $login_;
    $rResetUrl = $reset_;
    $rSupport  = $supp_;
    $rSiteName = $site_;

    $isOtpReset  = $rType === 'password_reset_otp';
    $hasPassword = !$isOtpReset && !empty($rPassword) && $rPassword !== 'Use your existing password';

    // ── Header style per canonical type ──────────────────────────────
    $headerGradient = match ($rType) {
        'new'                          => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'reset', 'password_reset_otp'  => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'update'                       => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        default                        => 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
    };

    $headerIcon = match ($rType) {
        'new'                => '🎉 Welcome!',
        'reset'              => '🔐 Password Reset',
        'password_reset_otp' => '🔐 Password Reset',
        'update'             => '✏️ Account Updated',
        default              => '🔑 Account Information',
    };

    $headerSubtitle = match ($rType) {
        'new'                => "Your {$rSiteName} account is ready",
        'reset'              => 'Your password has been changed',
        'password_reset_otp' => 'Your one-time verification code',
        'update'             => 'Your account details have been updated',
        default              => "Your {$rSiteName} credentials",
    };

    $pageTitle = match ($rType) {
        'password_reset_otp' => 'Password Reset Code - ' . $rSiteName,
        'new'                => 'Welcome to ' . $rSiteName,
        default              => 'Your Account Information',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>

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
        Transactional account email from {{ $rSiteName }}.
        @if($rType === 'new') Account creation confirmation. @endif
        @if($rType === 'update') Account update notification. @endif
        @if($rType === 'reset') Password reset confirmation. @endif
        @if($rType === 'password_reset_otp') Password reset OTP code: {{ $rOtp }}. @endif
        Category: Account Security. Not promotional. Not bulk. Not marketing.
    </span>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6; color: #333333;
            background-color: #f7f9fc;
            -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;
            width: 100% !important;
        }
        a { color: #667eea !important; text-decoration: none; }
        a:hover { text-decoration: underline !important; }
        a[x-apple-data-detectors] {
            color: inherit !important; text-decoration: none !important;
            font-size: inherit !important; font-family: inherit !important;
            font-weight: inherit !important; line-height: inherit !important;
        }
        .ExternalClass { width: 100%; }
        .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div { line-height: 100%; }

        @media screen and (min-width: 600px) {
            .email-container { width: 600px !important; margin: 0 auto !important; }
        }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; border-radius: 0 !important; }
            .header { padding: 20px !important; }
            .content { padding: 20px !important; }
            .credentials-container, .otp-container { padding: 20px !important; }
            .button { display: block !important; width: 100% !important; text-align: center !important; }
            .password-code { font-size: 22px !important; letter-spacing: 2px !important; }
            .otp-code { font-size: 32px !important; letter-spacing: 6px !important; }
        }

        .email-container {
            max-width: 600px; margin: 0 auto;
            background-color: #ffffff; border-radius: 12px;
            overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        .header {
            background: {{ $headerGradient }};
            color: white; padding: 30px; text-align: center;
        }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .header p { margin: 10px 0 0; opacity: 0.9; font-size: 16px; }
        .content { padding: 40px; }

        .credentials-container {
            background: #f8f9fa; border-radius: 10px; padding: 30px;
            margin: 30px 0; border: 2px dashed #e0e0e0;
        }
        .credentials-container h3 { margin-top: 0; color: #2d3436; font-size: 18px; }
        .detail-row { margin: 12px 0; padding: 10px 0; border-bottom: 1px solid #ececec; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            display: block; font-size: 12px; color: #666;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 4px; font-weight: 600;
        }
        .detail-value { display: block; font-size: 15px; color: #2d3436; font-weight: 600; word-break: break-word; }
        .password-code {
            font-family: 'Courier New', monospace; font-size: 24px;
            letter-spacing: 4px; color: #2d3436; margin: 12px 0;
            display: inline-block; padding: 8px 16px; background: white;
            border-radius: 8px; border: 2px solid #e0e0e0; font-weight: bold;
        }

        .otp-container {
            background: #f8f9fa; border-radius: 10px; padding: 30px;
            margin: 30px 0; text-align: center; border: 2px dashed #e0e0e0;
        }
        .otp-code {
            font-size: 42px; font-weight: bold; letter-spacing: 8px;
            color: #2d3436; margin: 20px 0;
            font-family: 'Courier New', monospace;
            display: inline-block; padding: 10px 20px;
            background: white; border-radius: 8px; border: 2px solid #e0e0e0;
        }

        .expiry-notice {
            color: #e74c3c; font-weight: 600; background-color: #ffebee;
            padding: 12px; border-radius: 5px; text-align: center; margin: 20px 0;
        }
        .security-notice {
            color: #e74c3c; font-weight: 600; background-color: #ffebee;
            padding: 14px; border-radius: 5px; text-align: center; margin: 20px 0;
        }

        .instructions {
            background-color: #fff8e1; border-left: 4px solid #ffc107;
            padding: 20px; margin: 25px 0; border-radius: 5px;
        }
        .instructions h3 { color: #d35400; margin-top: 0; font-size: 16px; }
        .instructions ol, .instructions ul { padding-left: 20px; margin: 10px 0; }
        .instructions li { margin-bottom: 8px; }

        .button {
            display: inline-block; padding: 14px 28px;
            background: {{ $headerGradient }};
            color: white !important; border-radius: 8px;
            font-weight: 600; margin: 20px 0;
        }

        .footer {
            background-color: #f1f3f4; padding: 25px; text-align: center;
            border-top: 1px solid #e0e0e0; color: #666; font-size: 14px;
        }
        .transactional-notice {
            font-size: 12px; color: #666; text-align: center;
            margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f7f9fc;">
    <!--[if mso]>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr><td style="padding: 20px;">
    <![endif]-->

    <div class="email-container" role="article" aria-label="{{ $rSiteName }} account email">
        {{-- =================== HEADER (varies by type) =================== --}}
        <div class="header" role="banner">
            <h1>{{ $headerIcon }}</h1>
            <p>{{ $headerSubtitle }}</p>
        </div>

        {{-- =================== BODY =================== --}}
        <div class="content" role="main">
            <h2>Hello{{ $rName !== 'there' ? ', ' . $rName : '' }}!</h2>

            @if($rType === 'password_reset_otp')
                {{-- ============== PASSWORD RESET OTP DESIGN ============== --}}
                <p>We received a request to reset the password for your <strong>{{ $rSiteName }}</strong> account.</p>

                <div class="otp-container" role="region" aria-label="Password Reset Code">
                    <p style="margin-top: 0; color: #666;">Your password reset code is:</p>
                    <div class="otp-code" role="text" aria-label="Your OTP Code is {{ implode(' ', str_split($rOtp ?? '------')) }}">
                        {{ $rOtp ?? '------' }}
                    </div>
                    <p style="margin-bottom: 0; color: #666;">Enter this code on the reset page</p>
                </div>

                <div class="expiry-notice" role="alert">
                    ⚠️ This code will expire in <strong>{{ $rExpires }} minutes</strong>
                </div>

                <div class="instructions">
                    <h3>📋 How to reset your password:</h3>
                    <ol>
                        <li>Return to the password reset page on our platform</li>
                        <li>Enter the 6-digit code shown above</li>
                        <li>Create a new password</li>
                        <li>Sign in with your new password</li>
                    </ol>
                    <p><strong>Note:</strong> If you did not request this password reset, please ignore this email. Your password will not change.</p>
                </div>

                <p style="text-align: center;">
                    <a href="{{ $rResetUrl }}" class="button">Go to Reset Page</a>
                </p>
            @else
                {{-- ============== CREDENTIALS DESIGN (new/update/reset) ============== --}}
                @if($rType === 'new')
                    <p>Your account has been created by an administrator. You can now sign in to <strong>{{ $rSiteName }}</strong> using the credentials below.</p>
                @elseif($rType === 'reset')
                    <p>Your password has been reset. Please use the new credentials below to sign in.</p>
                @elseif($rType === 'update')
                    <p>Your account information has been updated. Here are your current details:</p>
                @else
                    <p>Here are your current account credentials for <strong>{{ $rSiteName }}</strong>.</p>
                @endif

                <div class="credentials-container">
                    <h3>📋 Account Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Name</span>
                        <span class="detail-value">{{ $rName }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value">{{ $rEmail }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Role</span>
                        <span class="detail-value">{{ ucfirst($rRole) }}</span>
                    </div>
                </div>

                <div class="credentials-container">
                    <h3>🔑 Login Credentials</h3>
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value">{{ $rEmail }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Password</span>
                        @if($hasPassword)
                            <div class="password-code">{{ $rPassword }}</div>
                            <div style="color: #666; font-size: 13px; margin-top: 6px;">
                                Copy this password and sign in, then change it immediately.
                            </div>
                        @else
                            <span class="detail-value">Use your existing password</span>
                        @endif
                    </div>
                </div>

                @if($hasPassword)
                    <div class="security-notice" role="alert">
                        ⚠️ For your security, please change your password immediately after your first login.
                    </div>
                @endif

                <div class="instructions">
                    <h3>📋 What to do next:</h3>
                    <ol>
                        <li>Click the "Login to Your Account" button below</li>
                        @if($hasPassword)
                            <li>Sign in using the email and password above</li>
                            <li>Change your password from your profile settings</li>
                        @else
                            <li>Sign in using your existing password</li>
                        @endif
                        <li>Start exploring your learning materials</li>
                    </ol>
                </div>

                <p style="text-align: center;">
                    <a href="{{ $rLoginUrl }}" class="button">Login to Your Account</a>
                </p>

                <p style="font-size: 14px; color: #666; text-align: center;">
                    Or copy this link into your browser:<br>
                    <a href="{{ $rLoginUrl }}" style="word-break: break-all; font-size: 12px;">{{ $rLoginUrl }}</a>
                </p>
            @endif

            <div class="transactional-notice">
                This is a transactional email related to your account security.
                You are receiving this email because an account action was performed on {{ $rEmail }}.
            </div>
        </div>

        {{-- =================== FOOTER =================== --}}
        <div class="footer" role="contentinfo">
            <p><strong>{{ $rSiteName }}</strong><br>Empowering education through technology</p>
            <p>
                📧 <a href="mailto:{{ $rSupport }}">{{ $rSupport }}</a> |
                🌐 <a href="https://redatlearninghub.com">redatlearninghub.com</a>
            </p>
            <p style="font-size: 12px; margin-top: 20px; color: #999;">
                This is an automated transactional message. Please do not reply to this email.<br>
                © {{ date('Y') }} {{ $rSiteName }}. All rights reserved.
            </p>
            <p style="font-size: 11px; color: #999; margin-top: 15px;">
                <a href="https://redatlearninghub.com/unsubscribe" style="color: #999; text-decoration: underline;">
                    Unsubscribe from transactional emails
                </a>
            </p>
        </div>
    </div>

    <!--[if mso]>
        </td></tr></table>
    <![endif]-->
</body>
</html>