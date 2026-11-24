<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - Nocfinity</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #1c1917;
            background-color: #fafaf9;
            margin: 0;
            padding: 24px 12px;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e7e5e4;
            padding: 36px 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #f5f5f4;
            padding-bottom: 24px;
            margin-bottom: 24px;
        }
        .brand {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: #09090b;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 12px;
        }
        .brand span {
            color: #0284c7;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            color: #18181b;
            margin: 0 0 8px 0;
        }
        .lead {
            font-size: 14px;
            color: #71717a;
            margin: 0;
        }
        .content {
            font-size: 14px;
            color: #3f3f46;
            line-height: 1.6;
        }
        .button-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .btn-primary {
            display: inline-block;
            background-color: #09090b;
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            letter-spacing: 0.01em;
        }
        .alert-box {
            background-color: #f4f4f5;
            border: 1px solid #e4e4e7;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 12px;
            color: #52525b;
            margin: 24px 0 16px 0;
        }
        .fallback-url {
            word-break: break-all;
            font-size: 12px;
            color: #0284c7;
            text-decoration: underline;
        }
        .footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #f4f4f5;
            text-align: center;
            font-size: 12px;
            color: #a1a1aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="{{ $frontendUrl }}" class="brand">NOC<span>FINITY</span></a>
            <h1 class="title">Reset Your Password</h1>
            <p class="lead">We received a request to reset the password for your account.</p>
        </div>

        <div class="content">
            <p>Hello{{ $user->name ? ' ' . e($user->name) : '' }},</p>
            <p>You recently asked to reset your password for your Nocfinity account associated with <strong>{{ e($user->email) }}</strong>. Click the button below to choose a new password:</p>

            <div class="button-wrapper">
                <a href="{{ $resetUrl }}" class="btn-primary" target="_blank" rel="noopener noreferrer">Reset Password</a>
            </div>

            <div class="alert-box">
                <strong>Important Notice:</strong> This password reset link will expire in <strong>{{ $expireMinutes }} minutes</strong>. If you did not make this request, you can safely ignore this email — your password will remain unchanged.
            </div>

            <p style="font-size: 12px; color: #71717a; margin-top: 20px;">
                If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:
            </p>
            <p class="fallback-url">
                <a href="{{ $resetUrl }}" style="color: #0284c7;">{{ $resetUrl }}</a>
            </p>
        </div>

        <div class="footer">
            <p style="margin: 0 0 4px 0;">&copy; {{ date('Y') }} Nocfinity. All rights reserved.</p>
            <p style="margin: 0;">This is an automated transactional message sent by the store.</p>
        </div>
    </div>
</body>
</html>
