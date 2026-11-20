<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Confirmation - {{ $order->order_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1c1917; background-color: #fafaf9; margin: 0; padding: 20px; }
        .card { background: #ffffff; max-width: 600px; margin: 0 auto; border-radius: 16px; border: 1px solid #e7e5e4; padding: 32px; }
        .header { text-align: center; border-bottom: 1px solid #f5f5f4; padding-bottom: 24px; margin-bottom: 24px; }
        .order-number { font-family: monospace; font-size: 18px; font-weight: bold; color: #0284c7; }
        .item-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .item-table th { text-align: left; border-bottom: 2px solid #e7e5e4; padding: 8px 0; color: #78716c; font-size: 12px; text-transform: uppercase; }
        .item-table td { padding: 12px 0; border-bottom: 1px solid #f5f5f4; }
        .summary-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .total-row { display: flex; justify-content: space-between; padding: 12px 0; font-size: 16px; font-weight: bold; border-top: 2px solid #1c1917; margin-top: 12px; }
        .address-box { background: #f5f5f4; border-radius: 12px; padding: 16px; font-size: 13px; color: #44403c; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 style="margin: 0 0 8px 0; font-size: 24px;">Thank you for your order!</h1>
            <p style="margin: 0; color: #78716c; font-size: 14px;">Your payment was successful and your order has been received.</p>
            <p class="order-number" style="margin-top: 12px;">{{ $order->order_number }}</p>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product_name }}</strong><br>
                            <span style="color: #78716c; font-size: 12px;">{{ $item->color_name }} • Size {{ $item->size_name }} ({{ $item->sku }})</span>
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td style="text-align: right;">{{ $order->currency }} {{ number_format($item->line_subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 24px;">
            <div class="summary-row">
                <span>Subtotal</span>
                <span>{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</span>
            </div>
            @if($order->product_discount > 0)
                <div class="summary-row" style="color: #059669;">
                    <span>Product Discounts</span>
                    <span>-{{ $order->currency }} {{ number_format($order->product_discount, 2) }}</span>
                </div>
            @endif
            @if($order->coupon_discount > 0)
                <div class="summary-row" style="color: #059669;">
                    <span>Coupon Discount</span>
                    <span>-{{ $order->currency }} {{ number_format($order->coupon_discount, 2) }}</span>
                </div>
            @endif
            <div class="summary-row">
                <span>Shipping</span>
                <span>{{ $order->shipping_charge > 0 ? $order->currency.' '.number_format($order->shipping_charge, 2) : 'Complimentary' }}</span>
            </div>
            <div class="total-row">
                <span>Total Paid</span>
                <span>{{ $order->currency }} {{ number_format($order->final_total, 2) }}</span>
            </div>
        </div>

        <div class="address-box">
            <strong>Shipping To:</strong><br>
            {{ $order->ship_name }}<br>
            {{ $order->ship_address }}<br>
            {{ $order->ship_city }}, {{ $order->ship_state }} {{ $order->ship_postal_code }}<br>
            {{ $order->ship_country }}<br>
            Phone: {{ $order->ship_phone }}
        </div>
    </div>
</body>
</html>
