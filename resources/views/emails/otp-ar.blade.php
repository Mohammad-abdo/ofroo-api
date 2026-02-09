<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رمز التحقق</title>
</head>
<body style="font-family: Arial, sans-serif; direction: rtl; text-align: right;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #333;">مرحباً {{ $user->name }}</h1>
        <p>رمز التحقق الخاص بك هو:</p>
        <div style="background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;">
            {{ $otp }}
        </div>
        <p>هذا الرمز صالح لمدة 10 دقائق فقط.</p>
        <p>إذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة.</p>
        <hr>
        <p style="color: #666; font-size: 12px;">OFROO - كوبونات وعروض محلية</p>
    </div>
</body>
</html>


