<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $type === 'new' ? 'Welcome to ' . ($siteName ?? 'Our Platform') : 'Your Account Information' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4f46e5;
        }
        .header h1 {
            color: #4f46e5;
            margin: 0;
        }
        .content {
            margin-bottom: 30px;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .credentials {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            background: #4f46e5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $siteName ?? 'Learning Platform' }}</h1>
            <p>{{ $type === 'new' ? 'Welcome to Our Platform' : 'Account Information' }}</p>
        </div>
        
        <div class="content">
            @if($type === 'new')
                <h2>Welcome, {{ $name }}!</h2>
                <p>Your account has been created by the administrator. Here are your login credentials:</p>
            @elseif($type === 'reset')
                <h2>Password Reset</h2>
                <p>Your password has been reset. Please use the new credentials below:</p>
            @elseif($type === 'update')
                <h2>Account Updated</h2>
                <p>Your account information has been updated:</p>
            @else
                <h2>Account Credentials</h2>
                <p>Here are your account credentials:</p>
            @endif
            
            <div class="info-box">
                <h3>Account Details</h3>
                <p><strong>Name:</strong> {{ $name }}</p>
                <p><strong>Email:</strong> {{ $email }}</p>
                <p><strong>Role:</strong> {{ ucfirst($role) }}</p>
            </div>
            
            <div class="credentials">
                <h3>Login Credentials</h3>
                <p><strong>Email:</strong> {{ $email }}</p>
                @if($password !== 'Use your existing password')
                    <p><strong>Password:</strong> <strong style="color: #0ea5e9;">{{ $password }}</strong></p>
                    <p><em>Please change this password after your first login.</em></p>
                @else
                    <p><strong>Password:</strong> Use your existing password</p>
                @endif
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $loginUrl }}" class="btn">Login to Your Account</a>
            </div>
            
            @if($password !== 'Use your existing password')
                <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <strong>Security Notice:</strong>
                    <p>For security reasons, please change your password immediately after logging in.</p>
                </div>
            @endif
        </div>
        
        <div class="footer">
            <p>If you have any questions, please contact our support team:</p>
            <p><a href="mailto:{{ $supportEmail ?? 'support@example.com' }}">{{ $supportEmail ?? 'support@example.com' }}</a></p>
            <p style="margin-top: 20px; font-size: 12px;">
                This is an automated message. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>