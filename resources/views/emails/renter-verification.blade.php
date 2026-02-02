<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #3C50E0 0%, #2E40C0 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #fff; margin: 0;">Verify Your Email</h1>
    </div>
    
    <div style="background: #fff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
        <p>Hello {{ $renter->name }},</p>
        
        <p>Thank you for creating an account with {{ $appName }}. Please verify your email address to continue with your rental request.</p>
        
        <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
            <p style="margin: 0; font-size: 14px; color: #666;">Your verification PIN:</p>
            <p style="font-size: 32px; font-weight: bold; color: #3C50E0; letter-spacing: 8px; margin: 10px 0;">{{ $verificationPin }}</p>
            <p style="margin: 0; font-size: 12px; color: #999;">This PIN expires in 60 minutes</p>
        </div>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ $verificationUrl }}" style="background: #3C50E0; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Verify Email Address</a>
        </p>
        
        <p style="font-size: 14px; color: #666;">Or copy and paste this link into your browser:</p>
        <p style="font-size: 12px; color: #999; word-break: break-all;">{{ $verificationUrl }}</p>
        
        <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">
        
        <p style="font-size: 12px; color: #999;">If you did not create an account, please ignore this email.</p>
    </div>
</body>
</html>
