<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            color: #1c1917;
            background-color: #fafaf9;
            margin: 0;
            padding: 24px 12px;
        }
        .card {
            background: #ffffff;
            max-width: 600px;
            margin: 0 auto;
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
        .order-number {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 16px;
            font-weight: 700;
            color: #0284c7;
            background-color: #f0f9ff;
            border: 1px solid #e0f2fe;
            padding: 4px 12px;
            border-radius: 6px;
            display: inline-block;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        .item-table th {
            text-align: left;
            border-bottom: 2px solid #e7e5e4;
            padding: 10px 0;
            color: #78716c;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .item-table td {
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f4;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #57534e;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 16px;
            font-weight: 700;
            border-top: 2px solid #1c1917;
            margin-top: 10px;
            color: #1c1917;
        }
        .address-box {
            background: #f5f5f4;
            border-radius: 12px;
            padding: 18px;
            font-size: 13px;
            color: #44403c;
            margin-top: 24px;
            line-height: 1.6;
        }
        .button-wrapper {
            text-align: center;
            margin: 32px 0 16px 0;
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
    <div class="card">
        <div class="header">
            <a href="{{ config('app.frontend_url') }}" class="brand">NOC<span>FINITY</span></a>
            <h1 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700;">Thank you for your order!</h1>
            <p style="margin: 0; color: #78716c; font-size: 14px;">Your payment was confirmed and your order has been received.</p>
            <div style="margin-top: 14px;">
                <span class="order-number">{{ $order->order_number }}</span>
            </div>
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
                        <td style="text-align: center; vertical-align: middle;">{{ $item->quantity }}</td>
                        <td style="text-align: right; vertical-align: middle;">{{ $order->currency }} {{ number_format($item->line_subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px;">
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
            <strong style="color: #1c1917; font-size: 14px;">Shipping Address:</strong><br>
            {{ $order->ship_name }}<br>
            {{ $order->ship_address }}<br>
            {{ $order->ship_city }}, {{ $order->ship_state }} {{ $order->ship_postal_code }}<br>
            {{ $order->ship_country }}<br>
            Phone: {{ $order->ship_phone }}
        </div>

        <div class="button-wrapper">
            <a href="{{ rtrim(config('app.frontend_url', 'http://localhost:3000'), '/') }}/orders/{{ $order->order_number }}" class="btn-primary" target="_blank" rel="noopener noreferrer">
                View Order Details
            </a>
        </div>

        <div class="footer">
            <p style="margin: 0 0 4px 0;">&copy; {{ date('Y') }} Nocfinity. All rights reserved.</p>
            <p style="margin: 0;">If you have any questions, please contact customer support.</p>
        </div>
    </div>
</body>
</html>
