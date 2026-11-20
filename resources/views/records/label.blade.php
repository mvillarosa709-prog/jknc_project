{{--
    The printable Record Label.

    Printer-agnostic: no fixed page size, no margins assumed. The sheet is styled for
    A4 and the label itself is a fixed physical size, because that is what a label
    printer cuts to. Everything outside the label is chrome that @media print hides.

    The QR is inline SVG rather than an <img>, so it scales with the label instead of
    rasterising at one density — a 203dpi label printer and a laser printer get the
    same crisp result from one asset.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Record Label — {{ $record->document_reference }}</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #cbd5e1;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #f8fafc;
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        .sheet {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: center;
            max-width: 900px;
            margin: 0 auto;
        }

        /*
            90mm x 60mm is the common A4 sheet layout for record folders. The label
            holds one record's whole identity, so nothing here is allowed to wrap
            onto a second physical label.
        */
        .label {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            width: 90mm;
            height: 60mm;
            padding: 7mm;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 4px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .label__meta { min-width: 0; display: flex; flex-direction: column; }

        .label__org {
            margin: 0 0 6px;
            font-size: 8pt;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .label__title {
            margin: 0 0 8px;
            font-size: 13pt;
            line-height: 1.25;
            font-weight: 700;
            /* Long titles clip rather than push the reference off the label. */
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .label__refs {
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin: 0;
            font-size: 9pt;
        }

        .label__refs dt {
            color: var(--muted);
            font-size: 7pt;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .label__refs dd { margin: 0; font-weight: 600; }

        .label__scheme {
            margin: auto 0 0;
            padding-top: 6px;
            border-top: 1px solid var(--line);
            font-size: 8pt;
            color: var(--muted);
        }

        .label__qr {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            width: 40mm;
        }

        .label__qr svg { width: 38mm; height: 38mm; display: block; }

        .label__qr span {
            font-size: 6.5pt;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
            text-align: center;
        }

        .actions { max-width: 900px; margin: 0 auto 20px; display: flex; gap: 8px; }

        .actions a, .actions button {
            font: inherit;
            font-size: 13px;
            padding: 8px 14px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
            text-decoration: none;
        }

        .actions .primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }

        @media print {
            body { padding: 0; background: #fff; }
            .actions { display: none; }
            .sheet { display: block; max-width: none; }
            .label { border: 1px dashed #000; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button class="primary" type="button" onclick="window.print()">Print label</button>
        <a href="{{ route('records.index') }}">Back to register</a>
    </div>

    <div class="sheet">
        <article class="label">
            <div class="label__meta">
                <p class="label__org">{{ $record->company?->name ?? 'ORDO' }}</p>

                <h1 class="label__title">{{ $record->title }}</h1>

                <dl class="label__refs">
                    <dt>Document Reference</dt>
                    <dd>{{ $record->document_reference }}</dd>

                    @if ($record->record_number)
                        <dt>Record ID</dt>
                        <dd>{{ $record->record_number }}</dd>
                    @endif
                </dl>

                <p class="label__scheme">
                    @if ($record->classification?->code)
                        {{ $record->classification->code }}
                        @if ($record->index?->code) &middot; {{ $record->index->code }} @endif
                    @else
                        Unclassified
                    @endif
                </p>
            </div>

            <div class="label__qr">
                {{-- Inline SVG from Bacon, so it scales with the label instead of rasterising at
                     one density. Emitted unescaped because {{ }} would turn the tags
                     into visible text. Safe to do so: the only variable in the SVG is a
                     ULID from the record key, and Bacon escapes its own path data. --}}
                <div role="img" aria-label="QR code linking to this record">{!! $qr !!}</div>
                <span>Scan to open</span>
            </div>
        </article>
    </div>
</body>
</html>