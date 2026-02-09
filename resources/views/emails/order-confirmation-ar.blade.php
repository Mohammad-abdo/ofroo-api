<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد الطلب</title>
</head>
<body style="font-family: Arial, sans-serif; direction: rtl; text-align: right;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #333;">شكراً لك على طلبك!</h1>
        <p>تم تأكيد طلبك رقم: <strong>#{{ $order->id }}</strong></p>
        <p>المبلغ الإجمالي: <strong>{{ number_format($order->total_amount, 2) }} د.ك</strong></p>
        <p>طريقة الدفع: <strong>{{ $order->payment_method === 'cash' ? 'نقدي' : 'بطاقة' }}</strong></p>
        
        <h2 style="color: #333; margin-top: 30px;">كوبوناتك:</h2>
        <ul>
            @foreach($coupons as $coupon)
            <li style="margin: 10px 0;">
                <strong>{{ $coupon->coupon_code }}</strong> - 
                {{ $coupon->offer->title_ar ?? $coupon->offer->title_en }}
            </li>
            @endforeach
        </ul>
        
        <p>تم إرسال ملف PDF يحتوي على جميع الكوبونات كمرفق.</p>
        <hr>
        <p style="color: #666; font-size: 12px;">OFROO - كوبونات وعروض محلية</p>
    </div>
</body>
</html>


