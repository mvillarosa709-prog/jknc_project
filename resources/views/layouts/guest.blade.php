<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Recipient Portal') · JK&C ORDO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/css/transmittal.css', 'resources/js/app.js'])
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-hover: #1e40af;
            --primary-light: #eff6ff;
            --surface: #ffffff;
            --bg-page: #f8fafc;
            --border: #e2e8f0;
            --border-focus: #3b82f6;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            --success: #059669;
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
        }

        * {
            box-sizing: border-box;
        }

        body.recipient-portal-body {
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        .portal-header {
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .portal-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .portal-brand img {
            height: 34px;
            width: auto;
            object-fit: contain;
        }

        .portal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .portal-badge .dot {
            width: 7px;
            height: 7px;
            background: #10b981;
            border-radius: 50%;
        }

        .portal-main-wrapper {
            flex: 1;
            width: 100%;
            max-width: 1680px;
            margin: 0 auto;
            padding: 24px 28px 48px;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: minmax(360px, 420px) minmax(0, 1.9fr);
            gap: 28px;
            align-items: start;
        }

        @media (max-width: 1200px) {
            .portal-grid {
                grid-template-columns: 1fr;
            }
        }

        .portal-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
        }

        .portal-card-header {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .portal-card-header h1, .portal-card-header h2 {
            margin: 0 0 6px 0;
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .portal-card-header p {
            margin: 0;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .transmittal-meta-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        .transmittal-meta-pill .eyebrow {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
            display: block;
        }

        .transmittal-meta-pill .tracking-no {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 700;
            color: var(--primary);
            display: block;
            margin-bottom: 4px;
        }

        .transmittal-meta-pill .subject {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 4px 0;
        }

        .transmittal-meta-pill .recipient-email {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-group label .hint {
            font-size: 11px;
            font-weight: 500;
            color: #94a3b8;
            margin-left: 4px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            font-size: 13px;
            font-family: inherit;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            color: #0f172a;
            outline: none;
            transition: all 0.15s ease;
        }

        .form-control:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin: 18px 0;
            cursor: pointer;
        }

        .checkbox-container input[type="checkbox"] {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .checkbox-container span {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            line-height: 1.4;
        }

        .btn-acknowledge {
            width: 100%;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #1e3a8a;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(30, 58, 138, 0.2);
            transition: all 0.15s ease;
        }

        .btn-acknowledge:hover {
            background: #1e40af;
            box-shadow: 0 4px 8px rgba(30, 58, 138, 0.25);
            transform: translateY(-1px);
        }

        .btn-acknowledge:active {
            transform: translateY(0);
        }

        .audit-footer-note {
            margin-top: 14px;
            font-size: 11px;
            color: #94a3b8;
            text-align: center;
            line-height: 1.5;
        }

        /* Success Card State */
        .success-box {
            text-align: center;
            padding: 24px 16px;
        }

        .success-icon-badge {
            width: 56px;
            height: 56px;
            background: #dcfce7;
            color: #16a34a;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .receipt-code-badge {
            display: inline-block;
            background: #f1f5f9;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 8px 16px;
            margin: 14px 0;
        }

        .receipt-code-badge span {
            display: block;
            font-size: 10px;
            font-weight: 800;
            color: #64748b;
            letter-spacing: 0.08em;
        }

        .receipt-code-badge code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .btn-download-trr {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 42px;
            background: #ffffff;
            color: var(--primary);
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-download-trr:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* Document Preview Pane (Matching Draft of Transmittal) */
        .portal-preview-pane {
            width: 100%;
            background: #e9eef6;
            border: 1px solid #dce5f2;
            border-radius: 14px;
            padding: 18px 16px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
            box-sizing: border-box;
        }

        .portal-preview-pane .preview-pane-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0 4px 14px;
            color: #536a8b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
        }

        .portal-preview-pane .workspace-document-pages {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 10px 0;
            overflow-x: auto;
            background: transparent;
        }

        .portal-preview-pane .transmittal-doc-page {
            width: 210mm !important;
            min-width: 210mm !important;
            max-width: 210mm !important;
            min-height: 297mm !important;
            box-sizing: border-box !important;
            margin: 0 auto 28px auto !important;
            background: #ffffff !important;
            box-shadow: 0 8px 30px rgba(16, 27, 42, 0.12) !important;
            border-radius: 2px !important;
        }

        .portal-preview-pane #transmittal-cover-page {
            padding: 22mm 22mm 20mm 22mm !important;
        }

        .portal-preview-pane #transmittal-preview-pdf {
            padding: 18mm 16mm 22mm 16mm !important;
            font-family: Arial, sans-serif !important;
            color: #111827 !important;
        }

        .portal-action-card {
            position: sticky;
            top: 86px;
        }

        /* Portal Evidentiary Attachments Box */
        .portal-attachments-card {
            margin-top: 24px;
            padding: 18px 20px;
            background: #ffffff;
            border: 1px solid #dce5f2;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .portal-attachments-header h3 {
            margin: 0 0 12px 0;
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }

        .portal-attachments-eyebrow {
            display: block;
            font-size: 10px;
            font-weight: 800;
            color: #3b82f6;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 2px;
        }

        .portal-attachments-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .portal-attachment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.15s ease;
        }

        .portal-attachment-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .portal-attachment-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .portal-file-icon {
            font-size: 22px;
            color: #ef4444;
            flex-shrink: 0;
        }

        .portal-attachment-info strong {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .portal-attachment-info small {
            display: block;
            font-size: 11px;
            color: #64748b;
        }

        .btn-portal-download {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #1e3a8a;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .btn-portal-download:hover {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
        }

        .portal-footer {
            text-align: center;
            padding: 24px 20px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: #94a3b8;
            background: #ffffff;
            margin-top: 40px;
        }
    </style>
</head>
<body class="recipient-portal-body">
    <header class="portal-header">
        <a class="portal-brand" href="#">
            <img src="/images/imaglogo.png" alt="John Kelly & Company" onerror="this.style.display='none'">
            <div style="display: flex; flex-direction: column;">
                <span style="font-size: 14px; font-weight: 800; color: #0f172a; letter-spacing: -0.01em;">John Kelly & Company</span>
                <span style="font-size: 10px; font-weight: 700; color: #64748b; letter-spacing: 0.05em; text-transform: uppercase;">Official Transmittal System</span>
            </div>
        </a>
        <div class="portal-badge">
            <span class="dot"></span>
            <span>Secure External Portal</span>
        </div>
    </header>

    <main class="portal-main-wrapper">
        @if (session('acknowledged'))
            <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check text-green-600"></i>
                <span>{{ session('acknowledged') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 14px; font-weight: 600;">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="portal-footer">
        <p style="margin: 0;">Protected by End-to-End Audit Hash · John Kelly & Company · All rights reserved.</p>
    </footer>
</body>
</html>
