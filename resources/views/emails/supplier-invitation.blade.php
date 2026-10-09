<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete your HIMS supplier registration</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-shell { padding: 16px 8px !important; }
            .email-header, .email-content, .email-footer { padding-left: 22px !important; padding-right: 22px !important; }
            .email-content { padding-top: 28px !important; padding-bottom: 28px !important; }
            .email-heading { font-size: 24px !important; line-height: 31px !important; }
            .email-button { display: block !important; }
        }
    </style>
</head>
<body style="margin:0;background:#f4f7fb;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#172033;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f4f7fb;">
    <tr>
        <td class="email-shell" align="center" style="padding:36px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:580px;background:#ffffff;border:1px solid #dbe4ef;border-radius:14px;overflow:hidden;">
                <tr>
                    <td class="email-header" style="padding:26px 32px;background:#174c86;color:#ffffff;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="font-size:21px;font-weight:700;letter-spacing:-0.3px;">HIMS</td>
                                <td align="right" style="color:#dbeafe;font-size:11px;font-weight:700;letter-spacing:1.1px;text-transform:uppercase;">Supplier Portal</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="email-content" style="padding:36px 32px 32px;">
                        <p style="margin:0;color:#174c86;font-size:14px;font-weight:700;line-height:22px;">Hello {{ $name }},</p>
                        <h1 class="email-heading" style="margin:10px 0 0;color:#172033;font-size:28px;font-weight:700;line-height:36px;letter-spacing:-0.6px;">Complete your supplier registration</h1>
                        <p style="margin:16px 0 0;color:#4b5565;font-size:15px;line-height:24px;"><strong style="color:#172033;">{{ $supplierName }}</strong> has invited you to serve as its Vendor Administrator in the HIMS Supplier Portal.</p>
                        <p style="margin:10px 0 0;color:#4b5565;font-size:15px;line-height:24px;">Accept the invitation to activate your account and begin the hospital review process.</p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:26px 0;">
                            <tr>
                                <td align="center" style="border-radius:8px;background:#174c86;">
                                    <a class="email-button" href="{{ $invitationUrl }}" style="display:inline-block;padding:14px 24px;color:#ffffff;font-size:14px;font-weight:700;line-height:20px;text-align:center;text-decoration:none;">Accept Supplier Invitation</a>
                                </td>
                            </tr>
                        </table>

                        <div style="padding:18px;background:#f8fafc;border:1px solid #dbe4ef;border-radius:10px;">
                            <div style="color:#172033;font-size:14px;font-weight:700;line-height:22px;">What happens next</div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:12px;">
                                <tr>
                                    <td valign="top" style="width:24px;color:#174c86;font-size:13px;font-weight:700;line-height:21px;">1.</td>
                                    <td style="padding-bottom:8px;color:#4b5565;font-size:13px;line-height:21px;">Activate your Vendor Administrator account.</td>
                                </tr>
                                <tr>
                                    <td valign="top" style="width:24px;color:#174c86;font-size:13px;font-weight:700;line-height:21px;">2.</td>
                                    <td style="padding-bottom:8px;color:#4b5565;font-size:13px;line-height:21px;">Complete the company profile and upload supporting documents.</td>
                                </tr>
                                <tr>
                                    <td valign="top" style="width:24px;color:#174c86;font-size:13px;font-weight:700;line-height:21px;">3.</td>
                                    <td style="color:#4b5565;font-size:13px;line-height:21px;">Submit the profile for hospital review.</td>
                                </tr>
                            </table>
                        </div>

                        <div style="margin-top:18px;padding:14px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;color:#78350f;font-size:13px;line-height:21px;">
                            <strong>This invitation expires {{ $expiresIn }}.</strong><br>
                            Accepting it starts registration but does not approve {{ $supplierName }} for procurement. Hospital review is still required.
                        </div>

                        <p style="margin:22px 0 0;color:#647083;font-size:13px;line-height:21px;">If you did not expect this invitation, no action is needed.</p>

                        <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5eaf0;color:#6b7280;font-size:12px;line-height:19px;">
                            Button not working? Copy and paste this address into your browser:<br>
                            <a href="{{ $invitationUrl }}" style="color:#174c86;text-decoration:underline;text-underline-offset:2px;word-break:break-all;">{{ $invitationUrl }}</a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="email-footer" style="padding:20px 32px;background:#f8fafc;border-top:1px solid #e5eaf0;color:#647083;font-size:12px;line-height:19px;">
                        <strong style="color:#374151;">{{ $appName }}</strong><br>
                        This is an automated supplier onboarding notification.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
