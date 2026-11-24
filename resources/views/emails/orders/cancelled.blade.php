<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Cancelled - {{ $order->order_number }}</title>
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
            color: #dc2626;
            background-color: #fef2f2;
            border: 1px solid #fee2e2;
            padding: 4px 12px;
            border-radius: 6px;
            display: inline-block;
        }
        .info-box {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            border-radius: 12px;
            padding: 16px;
            font-size: 13px;
            color: #991b1b;
            margin: 20px 0;
            line-height: 1.6;
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
        .button-wrapper {
            text-align: center;
            margin: 32px 0 16px 0;
        }
        .btn-secondary {
            display: inline-block;
            background-color: #f4f4f5;
            color: #18181b !important;
            border: 1px solid #e4e4e7;
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
            <h1 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700; color: #dc2626;">Order Cancelled</h1>
            <p style="margin: 0; color: #78716c; font-size: 14px;">Your order has been cancelled.</p>
            <div style="margin-top: 14px;">
                <span class="order-number">{{ $order->order_number }}</span>
            </div>
        </div>

        <div class="info-box">
            <strong>Cancellation Details:</strong><br>
            {{ $reason }}<br>
            <span style="font-size: 12px; color: #b91c1c; display: inline-block; margin-top: 6px;">
                If payment was previously captured, any applicable refund will be processed in accordance with store policy.
            </span>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th>Cancelled Item</th>
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
            <div class="total-row">
                <span>Total Amount</span>
                <span>{{ $order->currency }} {{ number_format($order->final_total, 2) }}</span>
            </div>
        </div>

        <div class="button-wrapper">
            <a href="{{ config('app.frontend_url') }}" class="btn-secondary" target="_blank" rel="noopener noreferrer">
                Continue Shopping
            </a>
        </div>

        <div class="footer">
            <p style="margin: 0 0 4px 0;">&copy; {{ date('Y') }} Nocfinity. All rights reserved.</p>
            <p style="margin: 0;">If you have any questions regarding your cancellation, please contact our support team.</p>
        </div>
    </div>
</body>
</html>
