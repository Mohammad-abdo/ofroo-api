<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #333;">Thank you for your order!</h1>
        <p>Your order #<strong>{{ $order->id }}</strong> has been confirmed.</p>
        <p>Total Amount: <strong>{{ number_format($order->total_amount, 2) }} EGP</strong></p>
        <p>Payment Method: <strong>{{ ucfirst($order->payment_method) }}</strong></p>
        
        <h2 style="color: #333; margin-top: 30px;">Your Coupons:</h2>
        <ul>
            @foreach($coupons as $coupon)
            <li style="margin: 10px 0;">
                <strong>{{ $coupon->coupon_code }}</strong> - 
                {{ $coupon->offer->title_en ?? $coupon->offer->title_ar }}
            </li>
            @endforeach
        </ul>
        
        <p>A PDF file containing all your coupons has been attached.</p>
        <hr>
        <p style="color: #666; font-size: 12px;">OFROO - Local Coupons & Offers</p>
    </div>
</body>
</html>


