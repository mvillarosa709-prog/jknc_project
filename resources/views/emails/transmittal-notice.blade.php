<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Transmittal Notice - {{ $transmittal->tracking_number }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f1f5f9;padding:30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,0.06);border:1px solid #e2e8f0;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding:28px 32px;background-color:#1e3a8a;color:#ffffff;border-bottom:1px solid #1e40af;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <div style="font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#93c5fd;margin-bottom:6px;">Official Transmittal Notice</div>
                                        <div style="font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-0.02em;">John Kelly &amp; Company</div>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block;padding:4px 10px;background-color:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:6px;font-size:11px;font-weight:600;color:#ffffff;">
                                            ORDO Secure
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:32px;color:#334155;font-size:14px;line-height:1.6;">
                            <p style="margin:0 0 16px 0;font-size:16px;color:#0f172a;font-weight:600;">
                                Dear {{ $recipient->name ?: 'Recipient' }},
                            </p>

                            <p style="margin:0 0 20px 0;color:#475569;">
                                You have been sent an official transmittal document package from <strong>John Kelly &amp; Company</strong>. Please review the details below and confirm receipt via the secure portal.
                            </p>

                            <!-- Document Meta Box -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:24px;border-collapse:separate;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;">Transmittal Reference</div>
                                        <div style="font-size:16px;font-weight:800;color:#1e3a8a;font-family:monospace;margin-bottom:8px;">{{ $transmittal->tracking_number }}</div>

                                        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;">Subject / Purpose</div>
                                        <div style="font-size:13px;font-weight:600;color:#1e293b;margin-bottom:8px;">{{ $transmittal->subject }}</div>

                                        <div style="font-size:12px;color:#64748b;">
                                            <strong>Mode:</strong> {{ strtoupper($transmittal->direction ?? 'SEND') }} &bull; 
                                            <strong>Date:</strong> {{ $transmittal->transmittal_date?->format('M j, Y') ?? date('M j, Y') }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:28px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $acknowledgmentUrl }}" target="_blank" style="display:inline-block;padding:14px 28px;background-color:#1e3a8a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;border-radius:8px;box-shadow:0 2px 6px rgba(30,58,138,0.25);">
                                            Review &amp; Acknowledge Transmittal &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 10px 0;font-size:12px;color:#64748b;text-align:center;">
                                This confidential link is personal to you and will expire in <strong>72 hours (3 days)</strong>. Please review and download your documents promptly.
                            </p>

                            <div style="border-top:1px solid #e2e8f0;margin-top:28px;padding-top:18px;">
                                <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.5;">
                                    If the button above does not work, copy and paste this link into your browser:<br>
                                    <a href="{{ $acknowledgmentUrl }}" style="color:#2563eb;word-break:break-all;font-size:11px;">{{ $acknowledgmentUrl }}</a>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;font-size:11px;color:#94a3b8;line-height:1.5;">
                            &copy; {{ date('Y') }} John Kelly &amp; Company. All rights reserved.<br>
                            This is an automated system notification from the ORDO Transmittal Management System.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
