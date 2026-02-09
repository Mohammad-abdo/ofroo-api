<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #333;">Hello {{ $user->name }}</h1>
        <p>Your verification code is:</p>
        <div style="background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;">
            {{ $otp }}
        </div>
        <p>This code is valid for 10 minutes only.</p>
        <p>If you did not request this code, please ignore this message.</p>
        <hr>
        <p style="color: #666; font-size: 12px;">OFROO - Local Coupons & Offers</p>
    </div>
</body>
</html>


