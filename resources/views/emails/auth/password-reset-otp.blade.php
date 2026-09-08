<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HIMS Password Reset Code</title>
</head>
<body style="margin:0;background:#f5f5f5;font-family:Arial,sans-serif;color:#171717;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e5e5;">
                <tr>
                    <td style="padding:26px 32px;background:#0a0a0a;color:#ffffff;">
                        <div style="font-size:20px;font-weight:700;">HIMS</div>
                        <div style="margin-top:3px;color:#a3a3a3;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;">Supply Chain and Inventory</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <h1 style="margin:0;font-size:24px;line-height:32px;">Password reset verification</h1>
                        <p style="margin:16px 0 0;color:#525252;font-size:15px;line-height:24px;">Use this one-time code to continue resetting your HIMS password:</p>
                        <div style="margin:26px 0;padding:20px;text-align:center;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;color:#174cb6;font-size:32px;font-weight:700;letter-spacing:8px;">{{ $otp }}</div>
                        <p style="margin:0;color:#525252;font-size:14px;line-height:22px;">This code expires in {{ $expiresInMinutes }} {{ Str::plural('minute', $expiresInMinutes) }} and can be used only once. A newly requested code replaces every earlier code.</p>
                        <p style="margin:18px 0 0;color:#737373;font-size:13px;line-height:20px;">If you did not request a password reset, you can safely ignore this email. HIMS will never ask you to reply with this code or disclose your password.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
