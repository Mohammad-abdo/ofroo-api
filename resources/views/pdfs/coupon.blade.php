<!DOCTYPE html>
<html dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ $language }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $language === 'ar' ? 'كوبون' : 'Coupon' }} - {{ $coupon->coupon_code }}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .coupon { border: 2px dashed #000; padding: 20px; text-align: center; }
        .code { font-size: 24px; font-weight: bold; margin: 20px 0; }
        .barcode { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="coupon">
        <h1>{{ $merchant->company_name_ar ?? $merchant->company_name_en ?? $merchant->company_name }}</h1>
        <h2>{{ $offer->title_ar ?? $offer->title_en }}</h2>
        <div class="code">{{ $coupon->coupon_code }}</div>
        @if($coupon->barcode_value)
        <div class="barcode">
            <img src="data:image/png;base64,{{ base64_encode(\Milon\Barcode\Facades\DNS1DFacade::getBarcodePNG($coupon->barcode_value, 'C128', 2, 50)) }}" alt="Barcode">
        </div>
        @endif
        <p>{{ $language === 'ar' ? 'السعر' : 'Price' }}: {{ number_format($offer->price, 2) }} EGP</p>
        <p>{{ $language === 'ar' ? 'صالح حتى' : 'Valid until' }}: {{ $offer->end_at->format('Y-m-d') }}</p>
    </div>
</body>
</html>


