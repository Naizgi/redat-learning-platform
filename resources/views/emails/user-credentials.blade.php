<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Credentials</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8fafc;
            padding: 30px;
            border: 1px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 10px 10px;
        }
        .credentials-box {
            background: white;
            border: 2px dashed #4361ee;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info-box {
            background: #e6f7ff;
            border-left: 4px solid #1890ff;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff7e6;
            border-left: 4px solid #faad14;
            padding: 15px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #06d6a0 0%, #4895ef 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #64748b;
            font-size: 12px;
        }
        .password {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            color: #4361ee;
            background: #f1f5f9;
            padding: 10px;
            border-radius: 4px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to {{ $data['siteName'] }}!</h1>
        <p>Your account has been {{ $data['type'] === 'new' ? 'created' : ($data['type'] === 'reset' ? 'password reset' : 'updated') }}</p>
    </div>
    
    <div class="content">
        <p>Hello <strong>{{ $data['name'] }}</strong>,</p>
        
        @if($data['type'] === 'new')
            <p>Welcome to {{ $data['siteName'] }}! Your account has been successfully created.</p>
        @elseif($data['type'] === 'reset')
            <p>Your password has been reset by the administrator.</p>
        @elseif($data['type'] === 'updated')
            <p>Your account information has been updated.</p>
        @else
            <p>Here are your account credentials:</p>
        @endif
        
        <div class="credentials-box">
            <h3>Your Login Credentials:</h3>
            <p><strong>Email:</strong> {{ $data['email'] }}</p>
            <p><strong>Password:</strong> <span class="password">{{ $data['password'] }}</span></p>
            <p><strong>Role:</strong> {{ ucfirst($data['role']) }}</p>
        </div>
        
        <div class="info-box">
            <p><strong>Important:</strong> Please login and change your password immediately after your first login for security.</p>
        </div>
        
        <div style="text-align: center;">
            <a href="{{ $data['loginUrl'] }}" class="button">Login to Your Account</a>
        </div>
        
        <div class="warning-box">
            <p><strong>Security Notice:</strong></p>
            <ul>
                <li>Keep your credentials confidential</li>
                <li>Do not share your password with anyone</li>
                <li>Always logout after your session</li>
                <li>Use a strong, unique password when you change it</li>
            </ul>
        </div>
        
        <p>If you have any questions or need assistance, please contact our support team at <a href="mailto:{{ $data['supportEmail'] }}">{{ $data['supportEmail'] }}</a>.</p>
        
        <p>Best regards,<br>
        The {{ $data['siteName'] }} Team</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} {{ $data['siteName'] }}. All rights reserved.</p>
    </div>
</body>
</html>