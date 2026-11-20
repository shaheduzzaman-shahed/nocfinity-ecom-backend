<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Cancelled - {{ $order->order_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1c1917; background-color: #fafaf9; margin: 0; padding: 20px; }
        .card { background: #ffffff; max-width: 600px; margin: 0 auto; border-radius: 16px; border: 1px solid #e7e5e4; padding: 32px; }
        .header { text-align: center; border-bottom: 1px solid #f5f5f4; padding-bottom: 24px; margin-bottom: 24px; }
        .order-number { font-family: monospace; font-size: 18px; font-weight: bold; color: #dc2626; }
        .item-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .item-table th { text-align: left; border-bottom: 2px solid #e7e5e4; padding: 8px 0; color: #78716c; font-size: 12px; text-transform: uppercase; }
        .item-table td { padding: 12px 0; border-bottom: 1px solid #f5f5f4; }
        .summary-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .total-row { display: flex; justify-content: space-between; padding: 12px 0; font-size: 16px; font-weight: bold; border-top: 2px solid #1c1917; margin-top: 12px; }
        .info-box { background: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 16px; font-size: 13px; color: #991b1b; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 style="margin: 0 0 8px 0; font-size: 24px; color: #dc2626;">Order Cancelled</h1>
            <p style="margin: 0; color: #78716c; font-size: 14px;">Your order has been successfully cancelled.</p>
            <p class="order-number" style="margin-top: 12px;">{{ $order->order_number }}</p>
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
            <div class="total-row">
                <span>Total Amount</span>
                <span>{{ $order->currency }} {{ number_format($order->final_total, 2) }}</span>
            </div>
        </div>

        <div class="info-box">
            <strong>Cancellation Notice:</strong><br>
            {{ $reason }}<br>
            If payment was previously captured, please note that any manual refund will be reviewed by store administration according to policy.
        </div>
    </div>
</body>
</html>
