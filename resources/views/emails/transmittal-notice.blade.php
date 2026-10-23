<div style="font-family:Arial,sans-serif;color:#17251f;max-width:640px;margin:0 auto;line-height:1.6">
    <p style="letter-spacing:.14em;color:#62806f;font-size:12px;font-weight:bold">ORDO · SECURE TRANSMITTAL</p>
    <h1 style="font-size:24px">A transmittal is ready for your acknowledgment</h1>
    <p>Hello {{ $recipient->name ?: 'there' }},</p>
    <p><strong>{{ $transmittal->tracking_number }}</strong> — {{ $transmittal->subject }}</p>
    <p>Use the secure link below to confirm receipt. This link is private, expires in {{ config('ordo.brevo.acknowledgment_days') }} days, and should not be forwarded.</p>
    <p style="margin:28px 0"><a href="{{ $acknowledgmentUrl }}" style="background:#17644e;color:white;text-decoration:none;padding:13px 20px;border-radius:5px">Review and acknowledge receipt</a></p>
    <p style="font-size:13px;color:#64756c">The link provides access only to the receipt form; it does not expose transmittal files. Contact the sender using your established secure channel to access the document package.</p>
</div>
