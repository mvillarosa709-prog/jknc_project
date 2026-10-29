<div class="workspace-bottom-meta" style="display: flex; flex-direction: column; gap: 12px; margin-top: 0; width: 100%;">
    {{-- Receipt Information --}}
    <article class="detail-card meta-card" style="margin: 0; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px;">
        <h2 style="font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">Receipt Information</h2>
        <dl style="display: grid; grid-template-columns: 100px 1fr; gap: 6px 10px; font-size: 12.5px; margin: 0;">
            <dt style="font-weight: 600; color: #64748b;">Receipt No</dt>
            <dd style="margin: 0; color: #0f172a; font-weight: 500;">{{ $latestReceipt?->receipt_code ?? 'Not yet generated' }}</dd>

            <dt style="font-weight: 600; color: #64748b;">Receipt File</dt>
            <dd style="margin: 0; color: #0f172a;">
                @if ($latestReceipt && ($canViewPrivateFiles ?? false))
                    <a href="{{ route('transmittals.receipts.pdf', [$transmittal, $latestReceipt->id]) }}" style="color: #0037a6; font-weight: 600; text-decoration: underline;">
                        Download TRR
                    </a>
                @else
                    {{ $latestReceipt ? 'Available to authorized users' : 'Not available' }}
                @endif
            </dd>
        </dl>
    </article>

    {{-- Attachment Summary --}}
    <article class="detail-card meta-card" style="margin: 0; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px;">
        <h2 style="font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">Attachment Summary</h2>
        <dl style="display: grid; grid-template-columns: 100px 1fr; gap: 6px 10px; font-size: 12.5px; margin: 0;">
            <dt style="font-weight: 600; color: #64748b;">With Files</dt>
            <dd style="margin: 0; color: #0f172a; font-weight: 600;">{{ $transmittal->documents->count() }}</dd>

            <dt style="font-weight: 600; color: #64748b;">Total Items</dt>
            <dd style="margin: 0; color: #0f172a; font-weight: 600;">{{ $transmittal->items->count() }}</dd>
        </dl>
    </article>
</div>
