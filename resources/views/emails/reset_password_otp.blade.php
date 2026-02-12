<!DOCTYPE html>
<html>
<head>
    <title>Password Reset OTP</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #e11d48;">Password Reset Request</h2>
        <p>Hello,</p>
        <p>You are receiving this email because we received a password reset request for your account.</p>
        <p>Your One-Time Password (OTP) is:</p>
        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #e11d48; background: #fff1f2; padding: 10px 20px; border-radius: 8px;">{{ $code }}</span>
        </div>
        <p>This code will expire in 15 minutes.</p>
        <p>If you did not request a password reset, no further action is required.</p>
        <p>Thanks,<br>
        {{ config('app.name') }}</p>
    </div>
</body>
</html>
