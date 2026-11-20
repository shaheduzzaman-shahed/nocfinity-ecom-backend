<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Status Update - {{ $order->order_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1c1917; background-color: #fafaf9; margin: 0; padding: 20px; }
        .card { background: #ffffff; max-width: 600px; margin: 0 auto; border-radius: 16px; border: 1px solid #e7e5e4; padding: 32px; }
        .header { text-align: center; border-bottom: 1px solid #f5f5f4; padding-bottom: 24px; margin-bottom: 24px; }
        .order-number { font-family: monospace; font-size: 18px; font-weight: bold; color: #0284c7; }
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 9999px; font-size: 14px; font-weight: 600; text-transform: capitalize; margin-top: 12px; }
        .status-processing { background: #e0e7ff; color: #3730a3; }
        .status-shipped { background: #e0f2fe; color: #0369a1; }
        .status-delivered { background: #dcfce7; color: #15803d; }
        .status-default { background: #f5f5f4; color: #44403c; }
        .item-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .item-table th { text-align: left; border-bottom: 2px solid #e7e5e4; padding: 8px 0; color: #78716c; font-size: 12px; text-transform: uppercase; }
        .item-table td { padding: 12px 0; border-bottom: 1px solid #f5f5f4; }
        .summary-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .total-row { display: flex; justify-content: space-between; padding: 12px 0; font-size: 16px; font-weight: bold; border-top: 2px solid #1c1917; margin-top: 12px; }
        .note-box { background: #f0f9ff; border: 1px solid #e0f2fe; border-radius: 12px; padding: 16px; font-size: 13px; color: #0369a1; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 style="margin: 0 0 8px 0; font-size: 24px;">Order Status Update</h1>
            <p style="margin: 0; color: #78716c; font-size: 14px;">Your order has progressed to a new status:</p>
            <div class="status-badge status-{{ $newStatus }}">
                {{ ucfirst($newStatus) }}
            </div>
            <p class="order-number" style="margin-top: 16px;">{{ $order->order_number }}</p>
        </div>

        @if($newStatus === 'processing')
            <p style="font-size: 14px; color: #44403c;">Our warehouse fulfillment team has acknowledged your order and items are now being carefully picked and packed.</p>
        @elseif($newStatus === 'shipped')
            <p style="font-size: 14px; color: #44403c;">Great news! Your package has been handed over to the courier and is on its way to your destination.</p>
        @elseif($newStatus === 'delivered')
            <p style="font-size: 14px; color: #44403c;">Your package has arrived! We hope you love your new purchase.</p>
        @endif

        @if($note)
            <div class="note-box">
                <strong>Fulfillment Note:</strong><br>
                {{ $note }}
            </div>
        @endif

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
                <span>Total Amount</span>
                <span style="font-weight: bold;">{{ $order->currency }} {{ number_format($order->final_total, 2) }}</span>
            </div>
            <div class="summary-row" style="color: #78716c;">
                <span>Shipping Address</span>
                <span>{{ $order->ship_address }}, {{ $order->ship_city }}</span>
            </div>
        </div>
    </div>
</body>
</html>
