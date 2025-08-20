<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation to CheckRight</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
        }
        .footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        .company-info {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to CheckRight</h1>
            <p>You've been invited to join {{ $invitation->company?->name ?? 'CheckRight' }}</p>
        </div>

        <div class="content">
            <h2>Hello!</h2>

            <p>You have been invited to join <strong>{{ $invitation->company?->name ?? 'CheckRight' }}</strong> on CheckRight as a <strong>{{ ucfirst($invitation->role) }}</strong>.</p>

            @if($invitation->company)
            <div class="company-info">
                <h3>Company Details:</h3>
                <p><strong>Name:</strong> {{ $invitation->company->name }}</p>
                @if($invitation->company->subdomain)
                <p><strong>Domain:</strong> {{ $invitation->company->subdomain }}{{ config('tenant.domain.suffix') }}</p>
                @endif
                <p><strong>Your Role:</strong> {{ ucfirst($invitation->role) }}</p>
            </div>
            @else
            <div class="company-info">
                <h3>Invitation Details:</h3>
                <p><strong>Your Role:</strong> {{ ucfirst($invitation->role) }}</p>
            </div>
            @endif

            <p>To accept this invitation and set up your account, click the button below:</p>

            <div style="text-align: center;">
                <a href="{{ $acceptUrl }}" class="button">Accept Invitation</a>
            </div>

            <p><strong>Important:</strong> This invitation will expire on {{ $invitation->expires_at->format('F j, Y \a\t g:i A') }}.</p>

            <p>If you have any questions, please contact your administrator.</p>

            <p>Welcome aboard!</p>
        </div>

        <div class="footer">
            <p>This invitation was sent to {{ $invitation->email }}.</p>
            <p>If you did not expect this invitation, you can safely ignore this email.</p>
            <p>&copy; {{ date('Y') }} CheckRight. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
