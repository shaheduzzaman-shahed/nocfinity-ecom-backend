<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Update - {{ $order->order_number }}</title>
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
        .status-badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 14px;
        }
        .status-processing {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }
        .status-shipped {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #dcfce7;
        }
        .status-delivered {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #d1fae5;
        }
        .status-default {
            background: #f5f5f4;
            color: #44403c;
            border: 1px solid #e7e5e4;
        }
        .status-message {
            font-size: 14px;
            color: #44403c;
            margin: 20px 0;
            line-height: 1.6;
            text-align: center;
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
        .note-box {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 12px;
            padding: 16px;
            font-size: 13px;
            color: #0369a1;
            margin: 20px 0;
            line-height: 1.5;
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
            <h1 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700;">Order Status Update</h1>
            <p style="margin: 0; color: #78716c; font-size: 14px;">Your order has progressed to a new status:</p>
            <div>
                <span class="status-badge status-{{ $newStatus }}">
                    {{ ucfirst($newStatus) }}
                </span>
            </div>
            <div style="margin-top: 14px;">
                <span class="order-number">{{ $order->order_number }}</span>
            </div>
        </div>

        <div class="status-message">
            @if($newStatus === 'processing')
                Our fulfillment team is currently picking, preparing, and packaging your items with care.
            @elseif($newStatus === 'shipped')
                Great news! Your package has been handed over to the carrier and is on its way to you.
            @elseif($newStatus === 'delivered')
                Your package has been successfully delivered. We hope you enjoy your purchase!
            @else
                Your order status has been updated from <strong>{{ ucfirst($previousStatus) }}</strong> to <strong>{{ ucfirst($newStatus) }}</strong>.
            @endif
        </div>

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
                        <td style="text-align: center; vertical-align: middle;">{{ $item->quantity }}</td>
                        <td style="text-align: right; vertical-align: middle;">{{ $order->currency }} {{ number_format($item->line_subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <div class="summary-row">
                <span>Total Amount</span>
                <span style="font-weight: 700; color: #1c1917;">{{ $order->currency }} {{ number_format($order->final_total, 2) }}</span>
            </div>
            <div class="summary-row" style="color: #78716c;">
                <span>Delivery Address</span>
                <span>{{ $order->ship_address }}, {{ $order->ship_city }}</span>
            </div>
        </div>

        <div class="button-wrapper">
            <a href="{{ rtrim(config('app.frontend_url', 'http://localhost:3000'), '/') }}/orders/{{ $order->order_number }}" class="btn-primary" target="_blank" rel="noopener noreferrer">
                Track Order
            </a>
        </div>

        <div class="footer">
            <p style="margin: 0 0 4px 0;">&copy; {{ date('Y') }} Nocfinity. All rights reserved.</p>
            <p style="margin: 0;">If you have any questions, please contact customer support.</p>
        </div>
    </div>
</body>
</html>
