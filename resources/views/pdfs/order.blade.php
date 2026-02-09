<!DOCTYPE html>
<html dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ $language }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $language === 'ar' ? 'الطلب' : 'Order' }} #{{ $order->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .coupon { border: 1px solid #ccc; padding: 15px; margin: 10px 0; page-break-inside: avoid; }
        .code { font-size: 18px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $language === 'ar' ? 'الطلب رقم' : 'Order #' }}{{ $order->id }}</h1>
    <p>{{ $language === 'ar' ? 'المبلغ الإجمالي' : 'Total Amount' }}: {{ number_format($order->total_amount, 2) }} EGP</p>
    
    <h2>{{ $language === 'ar' ? 'الكوبونات' : 'Coupons' }}:</h2>
    @foreach($coupons as $coupon)
    <div class="coupon">
        <div class="code">{{ $coupon->coupon_code }}</div>
        <p>{{ $coupon->offer->title_ar ?? $coupon->offer->title_en }}</p>
        @if($coupon->barcode_value)
        <img src="data:image/png;base64,{{ base64_encode(\Milon\Barcode\Facades\DNS1DFacade::getBarcodePNG($coupon->barcode_value, 'C128', 2, 50)) }}" alt="Barcode">
        @endif
    </div>
    @endforeach
</body>
</html>


