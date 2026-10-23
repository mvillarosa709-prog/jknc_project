@extends('layouts.app')

@section('title', 'Smart Upload')

@section('content')

<style>
    /* ==============================
       SMART UPLOAD ROOT & STYLES
    ============================== */
    .smart-upload-container {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        padding-bottom: 60px;
    }

    .smart-upload-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        width: 100%;
        margin-bottom: 22px;
        padding: 20px 24px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }

    .smart-upload-title .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
        padding: 3px 9px;
        background: #eff6ff;
        border: 1px solid #dbeafe;
        border-radius: 20px;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .6px;
        text-transform: uppercase;
    }

    .smart-upload-title h1 {
        margin: 0 0 6px;
        color: #0f172a;
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.5px;
        line-height: 1.25;
    }

    .smart-upload-title .muted {
        margin: 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }

    .smart-upload-heading-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 38px;
        padding: 8px 14px;
        border: 1px solid #dbe3ef;
        border-radius: 9px;
        background: #ffffff;
        color: #334155;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all .15s ease;
    }

    .btn-action:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    .smart-upload-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        background: #ffffff;
        color: #475569;
        font-size: 20px;
        line-height: 1;
        text-decoration: none;
        transition: all .15s ease;
    }

    .smart-upload-close:hover {
        background: #fee2e2;
        border-color: #fecaca;
        color: #dc2626;
    }

    /* ==============================
       PANEL & TOOLBAR
    ============================== */
    .smart-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 22px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }

    .panel-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
    }

    .panel-section-title h2 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        color: #0f172a;
        font-size: 16px;
        font-weight: 700;
    }

    .panel-section-title .section-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        background: #eff6ff;
        color: #2563eb;
        border-radius: 8px;
        font-size: 14px;
    }

    .panel-section-title p {
        margin: 3px 0 0;
        color: #64748b;
        font-size: 12px;
    }

    /* Actions toolbar */
    .smart-toolbar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }

    .btn-toolbar {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 36px;
        padding: 7px 14px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all .15s ease;
        border: 1px solid transparent;
    }

    .btn-toolbar-primary {
        background: #2563eb;
        color: #ffffff;
    }
    .btn-toolbar-primary:hover {
        background: #1d4ed8;
    }

    .btn-toolbar-outline {
        background: #ffffff;
        border-color: #cbd5e1;
        color: #334155;
    }
    .btn-toolbar-outline:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
    }

    .btn-toolbar-purple {
        background: #f5f3ff;
        border-color: #ddd6fe;
        color: #6d28d9;
    }
    .btn-toolbar-purple:hover {
        background: #ede9fe;
        border-color: #c4b5fd;
    }

    .btn-toolbar-danger {
        background: #fff;
        border-color: #fecaca;
        color: #dc2626;
        margin-left: auto;
    }
    .btn-toolbar-danger:hover {
        background: #fee2e2;
    }

    /* ==============================
       DRAG & DROP ZONE
    ============================== */
    .smart-drop-zone {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        min-height: 190px;
        padding: 28px 20px;
        border: 2px dashed #93c5fd;
        border-radius: 12px;
        background: #f8fbff;
        text-align: center;
        cursor: pointer;
        transition: all .2s ease;
    }

    .smart-drop-zone:hover,
    .smart-drop-zone.drag-over {
        border-color: #2563eb;
        background: #eff6ff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
    }

    .smart-drop-zone.compact {
        min-height: 100px;
        padding: 16px;
        flex-direction: row;
        gap: 16px;
    }

    .smart-drop-zone .upload-icon-circle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #dbeafe;
        color: #2563eb;
        font-size: 20px;
        font-weight: 700;
        transition: transform .2s ease;
    }

    .smart-drop-zone:hover .upload-icon-circle {
        transform: translateY(-2px);
    }

    .smart-drop-zone strong {
        color: #0f172a;
        font-size: 15px;
        font-weight: 700;
    }

    .smart-drop-zone .drop-hint {
        color: #64748b;
        font-size: 12px;
    }

    .smart-drop-zone .drop-hint u {
        color: #2563eb;
        font-weight: 600;
        text-underline-offset: 3px;
    }

    .smart-drop-zone .drop-limits {
        color: #94a3b8;
        font-size: 11px;
    }

    .smart-drop-zone input[type="file"] {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        clip-path: inset(50%);
    }

    /* ==============================
       UPLOAD & OCR PROGRESS BAR
    ============================== */
    .progress-container {
        margin: 16px 0;
        padding: 14px 18px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }

    .progress-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 12px;
        font-weight: 600;
        color: #334155;
    }

    .progress-track {
        width: 100%;
        height: 7px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #3b82f6, #2563eb);
        border-radius: 10px;
        transition: width .3s ease;
    }

    .progress-status-msg {
        margin-top: 6px;
        font-size: 11px;
        color: #64748b;
    }
    .progress-status-msg.error {
        color: #dc2626;
        font-weight: 600;
    }

    /* ==============================
       UPLOADED FILES QUEUE CARDS
    ============================== */
    .files-queue-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 16px;
    }

    .file-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 12px 16px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        transition: all .15s ease;
    }

    .file-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
    }

    .file-card-main {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        flex: 1;
    }

    .file-badge-type {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        flex-shrink: 0;
        border-radius: 8px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .5px;
        text-transform: uppercase;
        background: #f1f5f9;
        color: #475569;
    }
    .file-badge-type.pdf { background: #fee2e2; color: #dc2626; }
    .file-badge-type.doc, .file-badge-type.docx { background: #dbeafe; color: #2563eb; }
    .file-badge-type.img, .file-badge-type.jpg, .file-badge-type.png { background: #fef3c7; color: #d97706; }

    .file-meta-col {
        min-width: 0;
        flex: 1;
    }

    .file-meta-name {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .file-meta-details {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 3px;
        font-size: 11px;
        color: #64748b;
    }

    .file-card-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .file-category-select {
        min-height: 32px;
        padding: 4px 10px;
        border: 1px solid #dbe3ef;
        border-radius: 7px;
        font-size: 11px;
        font-weight: 600;
        color: #334155;
        background: #ffffff;
        outline: none;
    }
    .file-category-select:focus {
        border-color: #3b82f6;
    }

    .btn-remove-file {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border: 1px solid transparent;
        border-radius: 7px;
        background: transparent;
        color: #94a3b8;
        font-size: 16px;
        cursor: pointer;
        transition: all .15s ease;
    }
    .btn-remove-file:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #fecaca;
    }

    /* ==============================
       CONFIDENCE & SOURCE BADGES
    ============================== */
    .badge-confidence {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .3px;
        text-transform: uppercase;
    }

    .badge-confidence.ocr {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }

    .badge-confidence.matched {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }

    .badge-confidence.autofilled {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }

    .badge-confidence.review {
        background: #fffbeb;
        color: #d97706;
        border: 1px solid #fde68a;
    }

    .badge-confidence.duplicate {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    /* ==============================
       LIST OF ITEMS TABLE
    ============================== */
    .items-table-wrapper {
        width: 100%;
        overflow-x: auto;
        margin-top: 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #ffffff;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        text-align: left;
    }

    .items-table th {
        background: #f8fafc;
        padding: 10px 12px;
        font-weight: 700;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .items-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .items-table tr:hover td {
        background: #f8fafc;
    }

    .items-table input {
        width: 100%;
        min-height: 32px;
        padding: 5px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 12px;
        color: #0f172a;
        background: #ffffff;
        outline: none;
    }
    .items-table input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px #dbeafe;
    }

    .items-table .col-pos { width: 36px; text-align: center; color: #64748b; font-weight: 700; }
    .items-table .col-file { width: 140px; }
    .items-table .col-particular { min-width: 160px; }
    .items-table .col-unique { width: 120px; }
    .items-table .col-qty { width: 65px; }
    .items-table .col-desc { min-width: 160px; }
    .items-table .col-remarks { width: 130px; }
    .items-table .col-source { width: 110px; white-space: nowrap; }
    .items-table .col-action { width: 36px; text-align: center; }

    .file-attachment-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 130px;
        padding: 3px 6px;
        background: #f1f5f9;
        border-radius: 6px;
        font-size: 11px;
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ==============================
       TRANSMITTAL DETAILS FORM
    ============================== */
    .fields-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .field-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .field-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .field-label {
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }

    .field-label .required {
        color: #dc2626;
    }

    .field-input {
        width: 100%;
        min-height: 40px;
        padding: 8px 12px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        font-size: 13px;
        color: #0f172a;
        background: #ffffff;
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .field-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px #dbeafe;
    }

    /* ==============================
       CONFIRMATION FOOTER
    ============================== */
    .confirmation-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 24px;
        padding: 18px 24px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .confirmation-notice {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #475569;
        font-size: 12px;
    }

    .confirmation-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .btn-submit-main {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        padding: 10px 22px;
        background: #2563eb;
        color: #ffffff;
        border: none;
        border-radius: 9px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        transition: all .15s ease;
    }
    .btn-submit-main:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
    }
    .btn-submit-main:disabled {
        opacity: .65;
        cursor: wait;
        transform: none;
        box-shadow: none;
    }

    /* ==============================
       MODALS: CAMERA & DOC MGMT
    ============================== */
    .smart-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(4px);
        padding: 20px;
    }

    .smart-modal {
        background: #ffffff;
        border-radius: 16px;
        width: 100%;
        max-width: 650px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        border: 1px solid #cbd5e1;
    }

    .smart-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .smart-modal-header h3 {
        margin: 0;
        color: #0f172a;
        font-size: 16px;
        font-weight: 700;
    }

    .smart-modal-body {
        padding: 20px;
    }

    .smart-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px 20px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        border-bottom-left-radius: 16px;
        border-bottom-right-radius: 16px;
    }

    .camera-preview-wrapper {
        position: relative;
        width: 100%;
        height: 340px;
        background: #0f172a;
        border-radius: 10px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .camera-video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .camera-canvas {
        display: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .fields-grid-2 {
            grid-template-columns: minmax(0, 1fr);
        }
        .smart-upload-heading {
            flex-direction: column;
            align-items: flex-start;
        }
        .confirmation-banner {
            flex-direction: column;
            align-items: flex-start;
        }
        .confirmation-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<div class="smart-upload-container">

    {{-- HEADER --}}
    <div class="smart-upload-heading">
        <div class="smart-upload-title">
            <span class="eyebrow">✧ High-Speed Creation Path</span>
            <h1>Smart Upload</h1>
            <p class="muted">
                Upload or drag multiple documents, run OCR classification, detect metadata &amp; duplicates, auto-populate the items list, and review before creating your draft.
            </p>
        </div>

        <div class="smart-upload-heading-actions">
            <a class="btn-action" href="{{ route('transmittals.index') }}">
                ← Back to Transmittals
            </a>
            <a class="smart-upload-close" href="{{ route('transmittals.index') }}" aria-label="Close Smart Upload" title="Close">
                &times;
            </a>
        </div>
    </div>

    {{-- FORM --}}
    <form
        method="POST"
        action="{{ route('transmittals.smart-upload.store') }}"
        enctype="multipart/form-data"
        id="smart-upload-form"
        data-upload-start-url="{{ route('transmittals.uploads.start') }}"
        data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}"
        data-chunk-bytes="{{ config('ordo.upload.chunk_bytes') }}"
        data-upload-analyze-url="{{ url('/transmittals/uploads') }}"
        data-analyze-url="{{ route('transmittals.analyze') }}"
        data-max-ocr-files="{{ config('ordo.ocr.max_files', 10) }}"
    >
        @csrf

        {{-- SECTION 1: UPLOAD & DROPZONE --}}
        <section class="smart-panel smart-upload-workspace">
            <div class="panel-section-title">
                <div>
                    <h2><span class="section-icon">↑</span> Upload Documents</h2>
                    <p>Select multiple files, drag and drop documents, capture a photo, or choose from Document Management.</p>
                </div>

                <div class="smart-toolbar">
                    <button type="button" class="btn-toolbar btn-toolbar-primary" id="btn-browse-trigger">
                        📁 Browse Multiple Files
                    </button>
                    <button type="button" class="btn-toolbar btn-toolbar-outline" id="btn-camera-trigger">
                        📷 Take a Photo
                    </button>
                    <button type="button" class="btn-toolbar btn-toolbar-purple" id="btn-docmgmt-trigger">
                        🗂️ Document Management
                    </button>
                    <button type="button" class="btn-toolbar btn-toolbar-outline" id="btn-reanalyze" style="display: none;">
                        ✧ Re-analyze OCR
                    </button>
                    <button type="button" class="btn-toolbar btn-toolbar-danger" id="btn-clear-all" style="display: none;">
                        🗑 Clear All
                    </button>
                </div>
            </div>

            {{-- PERSISTENT MULTI-FILE DROP ZONE --}}
            <label class="smart-drop-zone" id="smart-dropzone" for="smart-document-input">
                <span class="upload-icon-circle">↑</span>
                <strong>Drag &amp; drop your files here (Multiple Files Supported)</strong>
                <span class="drop-hint">or <u>browse from your device</u> · Drop additional files anytime</span>
                <small class="drop-limits">
                    PDF, JPG, PNG, TIFF, DOC/DOCX · Up to {{ number_format(config('ordo.upload.max_bytes') / 1048576) }} MB per file · Up to 20 files total
                </small>

                <input
                    type="file"
                    name="documents[]"
                    id="smart-document-input"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx,.txt"
                >
            </label>

            {{-- PROGRESS TRACKER --}}
            <div class="progress-container" id="upload-progress-container" style="display: none;">
                <div class="progress-header">
                    <span id="progress-title">Uploading &amp; Analyzing files...</span>
                    <span id="progress-percentage">0%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-status-msg" id="progress-status-msg">
                    Reading documents and extracting metadata with OCR...
                </div>
            </div>

            {{-- UPLOADED FILES QUEUE --}}
            <div id="smart-queue-container" style="display: none; margin-top: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 13px; font-weight: 700; color: #1e293b;">
                        Attached Documents (<span id="queue-count">0</span>)
                    </span>
                    <span style="font-size: 11px; color: #64748b;">
                        Categorize each document or verify detected types
                    </span>
                </div>

                <div class="files-queue-list" id="files-queue-list"></div>
            </div>
        </section>

        {{-- SECTION 2: AUTO-CREATED LIST OF ITEMS TABLE --}}
        <section class="smart-panel" id="smart-items-section" style="display: none;">
            <div class="panel-section-title">
                <div>
                    <h2><span class="section-icon">☰</span> List of Items (Auto-created from Documents)</h2>
                    <p>One row auto-created per detected document. You can edit every field, add custom items, or remove rows.</p>
                </div>
                <button type="button" class="btn-toolbar btn-toolbar-outline" id="btn-add-item-row">
                    + Add Item Row
                </button>
            </div>

            <div class="items-table-wrapper">
                <table class="items-table" id="smart-items-table">
                    <thead>
                        <tr>
                            <th class="col-pos">#</th>
                            <th class="col-file">Attached Document</th>
                            <th class="col-particular">Particular / Title <span class="required" style="color: #dc2626;">*</span></th>
                            <th class="col-unique">Unique ID / Ref</th>
                            <th class="col-qty">Qty</th>
                            <th class="col-desc">Description</th>
                            <th class="col-remarks">Remarks</th>
                            <th class="col-source">Source</th>
                            <th class="col-action"></th>
                        </tr>
                    </thead>
                    <tbody id="smart-items-tbody">
                        {{-- Items rows dynamically injected here --}}
                    </tbody>
                </table>
            </div>
        </section>

        {{-- SECTION 3: TRANSMITTAL DETAILS & ROUTING (REVIEW SCREEN) --}}
        <section class="smart-panel" id="smart-details-section" style="display: none;">
            <div class="panel-section-title">
                <div>
                    <h2><span class="section-icon">✎</span> Transmittal Header &amp; Recipient Review</h2>
                    <p>Auto-filled from OCR. Every field is editable and tagged with confidence/source labels.</p>
                </div>
            </div>

            <div class="fields-grid-2">
                {{-- Subject --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="subject-field">
                            Subject <span class="required">*</span>
                        </label>
                        <span class="badge-confidence review" id="badge-subject">Review Required</span>
                    </div>
                    <input
                        class="field-input"
                        name="subject"
                        id="subject-field"
                        value="{{ old('subject', '') }}"
                        required
                        maxlength="200"
                        placeholder="e.g. Architectural Drawing Set for Submittal"
                    >
                </div>

                {{-- External Reference --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="reference-field">
                            Document Reference / Control No.
                        </label>
                        <span class="badge-confidence review" id="badge-reference">Review Required</span>
                    </div>
                    <input
                        class="field-input"
                        name="external_reference"
                        id="reference-field"
                        value="{{ old('external_reference', '') }}"
                        maxlength="120"
                        placeholder="e.g. DWG-2026-001"
                    >
                </div>
            </div>

            <div class="fields-grid-2">
                {{-- Related Company --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="company-field">
                            Company / Client / Organization
                        </label>
                        <span class="badge-confidence review" id="badge-company">Review Required</span>
                    </div>
                    <input
                        class="field-input"
                        name="related_company"
                        id="company-field"
                        value="{{ old('related_company', '') }}"
                        maxlength="200"
                        placeholder="e.g. Acme Construction Corp"
                    >
                </div>

                {{-- Delivery Address --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="address-field">
                            Delivery Address / Location
                        </label>
                        <span class="badge-confidence review" id="badge-address">Review Required</span>
                    </div>
                    <input
                        class="field-input"
                        name="recipient_address"
                        id="address-field"
                        value="{{ old('recipient_address', '') }}"
                        maxlength="500"
                        placeholder="e.g. 100 Main Street, Suite 400"
                    >
                </div>
            </div>

            <div class="fields-grid-2">
                {{-- Recipient Name --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="recipient-name-field">
                            Recipient Attention Name
                        </label>
                        <span class="badge-confidence review" id="badge-recipient-name">Review Required</span>
                    </div>
                    <input
                        class="field-input"
                        name="recipient_name"
                        id="recipient-name-field"
                        value="{{ old('recipient_name', '') }}"
                        maxlength="160"
                        placeholder="e.g. Engr. John Miller"
                    >
                </div>

                {{-- Recipient Email --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="recipient-email-field">
                            Recipient Email Address
                        </label>
                        <span class="badge-confidence review" id="badge-recipient-email">Review Required</span>
                    </div>
                    <input
                        class="field-input"
                        name="recipient_email"
                        id="recipient-email-field"
                        type="email"
                        value="{{ old('recipient_email', '') }}"
                        maxlength="254"
                        placeholder="e.g. john.miller@example.com"
                    >
                </div>
            </div>

            <div class="fields-grid-2">
                {{-- Transmittal Date --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="date-field">
                            Transmittal Date
                        </label>
                        <span class="badge-confidence autofilled" id="badge-date">Auto-filled</span>
                    </div>
                    <input
                        class="field-input"
                        type="date"
                        name="transmittal_date"
                        id="date-field"
                        value="{{ old('transmittal_date', now()->toDateString()) }}"
                    >
                </div>

                {{-- Purpose / Remarks --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="purpose-field">
                            Purpose / Transmittal Instructions
                        </label>
                        <span class="badge-confidence autofilled">Auto-filled</span>
                    </div>
                    <input
                        class="field-input"
                        name="purpose"
                        id="purpose-field"
                        value="{{ old('purpose', 'For review, approval, and official record.') }}"
                        maxlength="500"
                        placeholder="e.g. For your review and approval"
                    >
                </div>
            </div>
        </section>

        {{-- SECTION 4: CONFIRMATION & CREATE DRAFT / TRN --}}
        <div class="confirmation-banner" id="smart-confirmation-section" style="display: none;">
            <div class="confirmation-notice">
                <span style="font-size: 18px; color: #2563eb;">✦</span>
                <div>
                    <strong style="color: #0f172a; font-size: 13px;">Ready to create transmittal?</strong>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                        Only after confirmation will the working draft and permanent tracking number (TRN) be generated.
                    </div>
                </div>
            </div>

            <div class="confirmation-actions">
                <a class="btn-action" href="{{ route('transmittals.index') }}">
                    Cancel
                </a>
                <button type="submit" class="btn-submit-main" id="btn-submit-draft">
                    Confirm &amp; Create Working Draft (TRN) →
                </button>
            </div>
        </div>
    </form>
</div>

{{-- MODAL 1: CAMERA CAPTURE --}}
<div class="smart-modal-backdrop" id="camera-modal" style="display: none;">
    <div class="smart-modal">
        <div class="smart-modal-header">
            <h3>📷 Take a Photo / Document Scanner</h3>
            <button type="button" class="btn-remove-file" id="btn-close-camera">&times;</button>
        </div>
        <div class="smart-modal-body">
            <div class="camera-preview-wrapper">
                <video class="camera-video" id="camera-video" autoplay playsinline></video>
                <canvas class="camera-canvas" id="camera-canvas"></canvas>
            </div>
            <p id="camera-status" style="margin: 10px 0 0; font-size: 12px; color: #64748b; text-align: center;">
                Position your document clearly within frame, then click Capture Photo.
            </p>
        </div>
        <div class="smart-modal-footer">
            <button type="button" class="btn-action" id="btn-switch-camera">🔄 Flip Camera</button>
            <button type="button" class="btn-action" id="btn-cancel-camera">Cancel</button>
            <button type="button" class="btn-toolbar btn-toolbar-primary" id="btn-snap-photo">📸 Capture Photo</button>
            <button type="button" class="btn-toolbar btn-toolbar-primary" id="btn-use-photo" style="display: none;">✓ Use Photo</button>
        </div>
    </div>
</div>

{{-- MODAL 2: DOCUMENT MANAGEMENT PICKER --}}
<div class="smart-modal-backdrop" id="docmgmt-modal" style="display: none;">
    <div class="smart-modal" style="max-width: 750px;">
        <div class="smart-modal-header">
            <h3>🗂️ Select from Document Management</h3>
            <button type="button" class="btn-remove-file" id="btn-close-docmgmt">&times;</button>
        </div>
        <div class="smart-modal-body">
            <div style="margin-bottom: 14px;">
                <input
                    type="text"
                    id="docmgmt-search"
                    class="field-input"
                    placeholder="Search documents by name, category, or TRN..."
                >
            </div>

            <div style="max-height: 320px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                <table class="items-table" id="docmgmt-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;"></th>
                            <th>Document Name</th>
                            <th>Category</th>
                            <th>TRN Reference</th>
                            <th>Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentDocuments ?? [] as $doc)
                            <tr class="docmgmt-row" data-name="{{ $doc->original_name }}" data-trn="{{ $doc->transmittal?->tracking_number }}" data-category="{{ $doc->category }}">
                                <td>
                                    <input type="checkbox" class="docmgmt-checkbox" value="{{ $doc->id }}" data-doc-name="{{ $doc->original_name }}" data-doc-category="{{ $doc->category }}" data-doc-trn="{{ $doc->transmittal?->tracking_number }}">
                                </td>
                                <td><strong>{{ $doc->original_name }}</strong></td>
                                <td><span class="badge-confidence ocr">{{ strtoupper($doc->category) }}</span></td>
                                <td><span style="font-family: monospace; font-size: 11px;">{{ $doc->transmittal?->tracking_number ?? '—' }}</span></td>
                                <td>{{ number_format(($doc->size_bytes ?? 0) / 1024, 1) }} KB</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 24px; color: #94a3b8;">
                                    No previously archived documents available in Document Management.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="smart-modal-footer">
            <button type="button" class="btn-action" id="btn-cancel-docmgmt">Close</button>
            <button type="button" class="btn-toolbar btn-toolbar-primary" id="btn-apply-docmgmt">Attach Selected to Transmittal</button>
        </div>
    </div>
</div>

{{-- JAVASCRIPT LOGIC --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('smart-upload-form');
    const fileInput = document.getElementById('smart-document-input');
    const dropZone = document.getElementById('smart-dropzone');
    const browseTrigger = document.getElementById('btn-browse-trigger');
    const cameraTrigger = document.getElementById('btn-camera-trigger');
    const docmgmtTrigger = document.getElementById('btn-docmgmt-trigger');
    const reanalyzeBtn = document.getElementById('btn-reanalyze');
    const clearAllBtn = document.getElementById('btn-clear-all');

    const progressContainer = document.getElementById('upload-progress-container');
    const progressFill = document.getElementById('progress-fill');
    const progressPercentage = document.getElementById('progress-percentage');
    const progressTitle = document.getElementById('progress-title');
    const progressStatusMsg = document.getElementById('progress-status-msg');

    const queueContainer = document.getElementById('smart-queue-container');
    const queueList = document.getElementById('files-queue-list');
    const queueCount = document.getElementById('queue-count');

    const itemsSection = document.getElementById('smart-items-section');
    const itemsTbody = document.getElementById('smart-items-tbody');
    const addItemBtn = document.getElementById('btn-add-item-row');

    const detailsSection = document.getElementById('smart-details-section');
    const confirmationSection = document.getElementById('smart-confirmation-section');
    const submitDraftBtn = document.getElementById('btn-submit-draft');

    // Header inputs
    const subjectInput = document.getElementById('subject-field');
    const referenceInput = document.getElementById('reference-field');
    const companyInput = document.getElementById('company-field');
    const addressInput = document.getElementById('address-field');
    const recipientNameInput = document.getElementById('recipient-name-field');
    const recipientEmailInput = document.getElementById('recipient-email-field');

    // Badges
    const badgeSubject = document.getElementById('badge-subject');
    const badgeReference = document.getElementById('badge-reference');
    const badgeCompany = document.getElementById('badge-company');
    const badgeAddress = document.getElementById('badge-address');
    const badgeRecipientName = document.getElementById('badge-recipient-name');
    const badgeRecipientEmail = document.getElementById('badge-recipient-email');

    // Modals
    const cameraModal = document.getElementById('camera-modal');
    const cameraVideo = document.getElementById('camera-video');
    const cameraCanvas = document.getElementById('camera-canvas');
    const cameraStatus = document.getElementById('camera-status');
    const btnSnapPhoto = document.getElementById('btn-snap-photo');
    const btnUsePhoto = document.getElementById('btn-use-photo');
    const btnSwitchCamera = document.getElementById('btn-switch-camera');
    const btnCloseCamera = document.getElementById('btn-close-camera');
    const btnCancelCamera = document.getElementById('btn-cancel-camera');

    const docmgmtModal = document.getElementById('docmgmt-modal');
    const docmgmtSearch = document.getElementById('docmgmt-search');
    const btnCloseDocmgmt = document.getElementById('btn-close-docmgmt');
    const btnCancelDocmgmt = document.getElementById('btn-cancel-docmgmt');
    const btnApplyDocmgmt = document.getElementById('btn-apply-docmgmt');

    // State
    let selectedFiles = []; // Array of File objects + metadata
    let cameraStream = null;
    let cameraFacingMode = 'environment';
    let capturedPhotoBlob = null;
    let isAnalyzing = false;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const uploadChunkBytes = Number(form.dataset.chunkBytes || 5242880);
    const maxOcrFiles = Number(form.dataset.maxOcrFiles || 10);
    const analyzeUrl = form.dataset.analyzeUrl || '/transmittals/analyze';
    const uploadStartUrl = form.dataset.uploadStartUrl || '/transmittals/uploads';

    /* ============================================================
       1. MULTI-FILE QUEUE & ACCUMULATION
    ============================================================ */
    function syncInputFiles() {
        const transfer = new DataTransfer();
        selectedFiles.forEach(function (item) {
            if (item.file instanceof File) {
                transfer.items.add(item.file);
            }
        });
        fileInput.files = transfer.files;
    }

    function addFiles(files) {
        if (!files || files.length === 0) return;

        let hasNew = false;
        Array.from(files).forEach(function (file) {
            // Check duplicate in current batch
            const isDuplicate = selectedFiles.some(function (existing) {
                return existing.file.name === file.name && existing.file.size === file.size;
            });

            if (!isDuplicate && selectedFiles.length < 20) {
                selectedFiles.push({
                    file: file,
                    category: detectCategoryFromName(file.name),
                    analysis: null,
                    duplicateWarning: false,
                    matchedRecord: null,
                    ocrStatus: 'pending' // pending, running, completed, error
                });
                hasNew = true;
            }
        });

        if (hasNew) {
            syncInputFiles();
            renderQueue();
            updateSectionsVisibility();
            triggerOcrAnalysis();
        }
    }

    function detectCategoryFromName(name) {
        const lower = name.toLowerCase();
        if (lower.includes('receipt') || lower.includes('acknowledgment')) return 'receipt';
        if (lower.includes('proof') || lower.includes('transmission')) return 'proof';
        if (lower.includes('attachment') || lower.includes('appendix')) return 'attachment';
        return 'document';
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        syncInputFiles();
        renderQueue();
        rebuildItemsTable();
        updateSectionsVisibility();
    }

    function clearAllFiles() {
        selectedFiles = [];
        syncInputFiles();
        renderQueue();
        rebuildItemsTable();
        updateSectionsVisibility();
    }

    browseTrigger.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        addFiles(fileInput.files);
    });

    clearAllBtn.addEventListener('click', clearAllFiles);
    reanalyzeBtn.addEventListener('click', triggerOcrAnalysis);

    /* ============================================================
       2. DRAG AND DROP (MULTIPLE FILES)
    ============================================================ */
    ['dragenter', 'dragover'].forEach(function (name) {
        dropZone.addEventListener(name, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('drag-over');
        });
    });

    ['dragleave', 'dragend'].forEach(function (name) {
        dropZone.addEventListener(name, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-over');
        });
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('drag-over');

        const dt = e.dataTransfer;
        if (dt && dt.files && dt.files.length > 0) {
            addFiles(dt.files);
        }
    });

    // Window level drag prevention
    window.addEventListener('dragover', function (e) { e.preventDefault(); });
    window.addEventListener('drop', function (e) { e.preventDefault(); });

    /* ============================================================
       3. QUEUE RENDERING & CARDS
    ============================================================ */
    function renderQueue() {
        queueList.innerHTML = '';
        queueCount.textContent = selectedFiles.length;

        if (selectedFiles.length === 0) {
            queueContainer.style.display = 'none';
            dropZone.classList.remove('compact');
            return;
        }

        queueContainer.style.display = 'block';
        dropZone.classList.add('compact');

        selectedFiles.forEach(function (item, index) {
            const card = document.createElement('div');
            card.className = 'file-card';

            const ext = item.file.name.split('.').pop().toLowerCase();
            let extClass = 'file-badge-type';
            if (ext === 'pdf') extClass += ' pdf';
            else if (['doc', 'docx'].includes(ext)) extClass += ' doc';
            else if (['jpg', 'jpeg', 'png', 'tiff', 'tif'].includes(ext)) extClass += ' img';

            // Status Badge
            let statusBadge = '<span class="badge-confidence review">Pending OCR</span>';
            if (item.ocrStatus === 'running') {
                statusBadge = '<span class="badge-confidence ocr">Analyzing OCR...</span>';
            } else if (item.ocrStatus === 'completed') {
                if (item.matchedRecord) {
                    statusBadge = `<span class="badge-confidence matched">Matched Record: ${item.matchedRecord.tracking_number || 'DB Match'}</span>`;
                } else {
                    statusBadge = '<span class="badge-confidence ocr">✧ OCR Extracted</span>';
                }
            } else if (item.ocrStatus === 'error') {
                statusBadge = '<span class="badge-confidence review">Review Required</span>';
            }

            let dupBadge = '';
            if (item.duplicateWarning) {
                dupBadge = `<span class="badge-confidence duplicate" title="${item.duplicateWarning}">⚠ ${item.duplicateWarning}</span>`;
            }

            card.innerHTML = `
                <div class="file-card-main">
                    <span class="${extClass}">${ext.slice(0, 4)}</span>
                    <div class="file-meta-col">
                        <p class="file-meta-name" title="${item.file.name}">${item.file.name}</p>
                        <div class="file-meta-details">
                            <span>${(item.file.size / 1024).toFixed(1)} KB</span>
                            ${statusBadge}
                            ${dupBadge}
                        </div>
                    </div>
                </div>
                <div class="file-card-actions">
                    <select class="file-category-select" data-file-index="${index}">
                        <option value="document" ${item.category === 'document' ? 'selected' : ''}>Document</option>
                        <option value="receipt" ${item.category === 'receipt' ? 'selected' : ''}>Receipt</option>
                        <option value="proof" ${item.category === 'proof' ? 'selected' : ''}>Proof</option>
                        <option value="attachment" ${item.category === 'attachment' ? 'selected' : ''}>Attachment</option>
                    </select>
                    <button type="button" class="btn-remove-file" data-remove-index="${index}" title="Remove file">&times;</button>
                </div>
            `;

            queueList.appendChild(card);
        });

        // Category change listener
        queueList.querySelectorAll('.file-category-select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                const idx = Number(this.dataset.fileIndex);
                if (selectedFiles[idx]) {
                    selectedFiles[idx].category = this.value;
                }
            });
        });

        // Remove button listener
        queueList.querySelectorAll('[data-remove-index]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const idx = Number(this.dataset.removeIndex);
                removeFile(idx);
            });
        });
    }

    function updateSectionsVisibility() {
        const hasFiles = selectedFiles.length > 0;
        clearAllBtn.style.display = hasFiles ? 'inline-flex' : 'none';
        reanalyzeBtn.style.display = hasFiles ? 'inline-flex' : 'none';
        itemsSection.style.display = hasFiles ? 'block' : 'none';
        detailsSection.style.display = hasFiles ? 'block' : 'none';
        confirmationSection.style.display = hasFiles ? 'flex' : 'none';
    }

    /* ============================================================
       4. OCR / AI EXTRACTION PER FILE & DUPLICATE DETECTION
    ============================================================ */
    async function triggerOcrAnalysis() {
        if (isAnalyzing || selectedFiles.length === 0) return;
        isAnalyzing = true;

        progressContainer.style.display = 'block';
        progressFill.style.width = '10%';
        progressPercentage.textContent = '10%';
        progressTitle.textContent = 'Extracting Document Intelligence...';
        progressStatusMsg.className = 'progress-status-msg';
        progressStatusMsg.textContent = 'Sending documents to OCR engine for classification and metadata detection...';

        const ocrCandidates = selectedFiles.filter(function (item) {
            return /\.(pdf|jpe?g|png|tiff?)$/i.test(item.file.name);
        });

        if (ocrCandidates.length === 0) {
            progressFill.style.width = '100%';
            progressPercentage.textContent = '100%';
            progressStatusMsg.textContent = 'Non-OCR files attached. You can review details manually.';
            isAnalyzing = false;
            rebuildItemsTable();
            return;
        }

        try {
            let processed = 0;
            const total = Math.min(ocrCandidates.length, maxOcrFiles);

            for (let i = 0; i < total; i++) {
                const item = ocrCandidates[i];
                item.ocrStatus = 'running';
                renderQueue();

                let analysisData = null;

                // Support chunked upload if file > 20MB
                if (item.file.size > 20 * 1024 * 1024) {
                    progressStatusMsg.textContent = `Uploading large file chunks for ${item.file.name}...`;
                    const uploadId = await uploadResumableFile(item.file);
                    const analyzeRes = await fetch(`${uploadStartUrl}/${uploadId}/analyze`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }
                    });
                    analysisData = await analyzeRes.json();
                    if (!analyzeRes.ok) throw new Error(analysisData.message || `OCR failed for ${item.file.name}`);
                } else {
                    const formData = new FormData();
                    formData.append('documents[]', item.file);

                    const res = await fetch(analyzeUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                        body: formData
                    });
                    const resJson = await res.json();
                    if (!res.ok) throw new Error(resJson.message || `OCR failed for ${item.file.name}`);
                    analysisData = resJson.documents ? resJson.documents[0] : resJson;
                }

                if (analysisData) {
                    item.analysis = analysisData;
                    item.ocrStatus = 'completed';
                    if (analysisData.category) item.category = analysisData.category;
                    if (analysisData.matched_record) {
                        item.matchedRecord = analysisData.matched_record;
                        item.duplicateWarning = analysisData.matched_record.label + (analysisData.matched_record.tracking_number ? ` (${analysisData.matched_record.tracking_number})` : '');
                    }
                }

                processed++;
                const pct = Math.round((processed / total) * 90) + 10;
                progressFill.style.width = pct + '%';
                progressPercentage.textContent = pct + '%';
                progressStatusMsg.textContent = `Analyzed ${processed} of ${total} documents...`;
            }

            progressFill.style.width = '100%';
            progressPercentage.textContent = '100%';
            progressTitle.textContent = 'Analysis Completed!';
            progressStatusMsg.textContent = `Processed ${processed} document(s). Metadata auto-filled and review screen ready.`;

            // Auto-populate header details from extracted fields
            applyExtractedHeaderFields();

            // Auto-create List of Items rows
            rebuildItemsTable();

        } catch (err) {
            console.error('OCR Error:', err);
            progressStatusMsg.className = 'progress-status-msg error';
            progressStatusMsg.textContent = err.message || 'OCR extraction encountered an issue. You can complete fields manually.';
        } finally {
            isAnalyzing = false;
            renderQueue();
        }
    }

    // Helper for large chunked uploads
    async function uploadResumableFile(file) {
        const startRes = await fetch(uploadStartUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: file.name, size: file.size, type: file.type || 'application/octet-stream' })
        });
        const startData = await startRes.json();
        if (!startRes.ok) throw new Error(startData.message || `Could not start upload for ${file.name}`);

        const uploadId = startData.upload_id;
        const chunkCount = Number(startData.chunk_count);
        const chunkSize = Number(startData.chunk_size || uploadChunkBytes);

        for (let idx = 0; idx < chunkCount; idx++) {
            const chunk = file.slice(idx * chunkSize, Math.min(file.size, (idx + 1) * chunkSize));
            const chunkBody = new FormData();
            chunkBody.append('chunk', chunk, `${file.name}.part`);

            const chunkRes = await fetch(`${uploadStartUrl}/${uploadId}/chunks/${idx}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: chunkBody
            });
            if (!chunkRes.ok) throw new Error(`Upload chunk ${idx + 1} failed for ${file.name}`);
        }

        const completeRes = await fetch(`${uploadStartUrl}/${uploadId}/complete`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }
        });
        const completeData = await completeRes.json();
        if (!completeRes.ok) throw new Error(completeData.message || `Verification failed for ${file.name}`);

        file.resumableUploadId = completeData.upload_id;
        return completeData.upload_id;
    }

    /* ============================================================
       5. APPLY EXTRACTED HEADER DETAILS & CONFIDENCE LABELS
    ============================================================ */
    function applyExtractedHeaderFields() {
        const analyses = selectedFiles.map(function (item) { return item.analysis; }).filter(Boolean);
        if (analyses.length === 0) return;

        function findField(key) {
            for (let i = 0; i < analyses.length; i++) {
                if (analyses[i].fields && analyses[i].fields[key]) {
                    return {
                        value: analyses[i].fields[key],
                        metadata: analyses[i].field_metadata ? analyses[i].field_metadata[key] : null
                    };
                }
            }
            return null;
        }

        // Subject
        const sub = findField('subject');
        if (sub && sub.value) {
            if (!subjectInput.value || subjectInput.value === 'Smart Upload Document') {
                subjectInput.value = sub.value;
                setConfidenceBadge(badgeSubject, 'autofilled', 'Auto-filled · OCR');
            }
        } else if (!subjectInput.value && selectedFiles.length > 0) {
            const cleanName = selectedFiles[0].file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
            subjectInput.value = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
            setConfidenceBadge(badgeSubject, 'autofilled', 'Auto-filled');
        }

        // Reference
        const ref = findField('reference') || findField('unique_id');
        if (ref && ref.value && !referenceInput.value) {
            referenceInput.value = ref.value;
            setConfidenceBadge(badgeReference, 'autofilled', 'Auto-filled · OCR');
        }

        // Company
        const comp = findField('company');
        if (comp && comp.value && !companyInput.value) {
            companyInput.value = comp.value;
            setConfidenceBadge(badgeCompany, 'autofilled', 'Auto-filled · OCR');
        }

        // Address
        const addr = findField('recipient_address');
        if (addr && addr.value && !addressInput.value) {
            addressInput.value = addr.value;
            setConfidenceBadge(badgeAddress, 'autofilled', 'Auto-filled · OCR');
        }

        // Recipient Name
        const recName = findField('recipient_name');
        if (recName && recName.value && !recipientNameInput.value) {
            recipientNameInput.value = recName.value;
            setConfidenceBadge(badgeRecipientName, 'autofilled', 'Auto-filled · OCR');
        }

        // Recipient Email
        const recEmail = findField('recipient_email');
        if (recEmail && recEmail.value && !recipientEmailInput.value) {
            recipientEmailInput.value = recEmail.value;
            setConfidenceBadge(badgeRecipientEmail, 'autofilled', 'Auto-filled · High');
        }
    }

    function setConfidenceBadge(badgeEl, type, text) {
        if (!badgeEl) return;
        badgeEl.className = 'badge-confidence ' + type;
        badgeEl.textContent = text;
    }

    // When user types in header fields, update badge to edited/user-reviewed
    [
        [subjectInput, badgeSubject],
        [referenceInput, badgeReference],
        [companyInput, badgeCompany],
        [addressInput, badgeAddress],
        [recipientNameInput, badgeRecipientName],
        [recipientEmailInput, badgeRecipientEmail]
    ].forEach(function ([input, badge]) {
        if (input && badge) {
            input.addEventListener('input', function () {
                if (input.value.trim().length > 0) {
                    setConfidenceBadge(badge, 'autofilled', 'User Verified');
                } else {
                    setConfidenceBadge(badge, 'review', 'Review Required');
                }
            });
        }
    });

    /* ============================================================
       6. AUTO-CREATE ONE LIST OF ITEMS ROW PER DETECTED DOCUMENT
    ============================================================ */
    function rebuildItemsTable() {
        itemsTbody.innerHTML = '';

        if (selectedFiles.length === 0) {
            return;
        }

        selectedFiles.forEach(function (item, index) {
            const fields = item.analysis && item.analysis.fields ? item.analysis.fields : {};
            const cleanName = item.file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');

            const particular = fields.subject || cleanName;
            const uniqueId = fields.unique_id || fields.reference || '';
            const qty = '1';
            const desc = fields.description || `${item.category.charAt(0).toUpperCase() + item.category.slice(1)} attachment (${item.file.name})`;
            const remarks = item.ocrStatus === 'completed' ? 'Auto-filled from OCR' : 'Attached from Smart Upload';

            let sourceBadge = '<span class="badge-confidence review">Review</span>';
            if (item.ocrStatus === 'completed') {
                sourceBadge = item.matchedRecord
                    ? '<span class="badge-confidence matched">Matched</span>'
                    : '<span class="badge-confidence ocr">OCR · High</span>';
            }

            addItemRowElement({
                index: index,
                docIndex: index,
                fileName: item.file.name,
                particular: particular,
                uniqueId: uniqueId,
                qty: qty,
                desc: desc,
                remarks: remarks,
                sourceBadge: sourceBadge
            });
        });
    }

    function addItemRowElement(data) {
        const row = document.createElement('tr');
        row.className = 'item-row';
        const rowIndex = itemsTbody.children.length;

        row.innerHTML = `
            <td class="col-pos">${rowIndex + 1}</td>
            <td class="col-file">
                <span class="file-attachment-pill" title="${data.fileName || 'Custom Item'}">
                    📎 ${data.fileName ? data.fileName.slice(0, 15) + (data.fileName.length > 15 ? '...' : '') : 'Manual item'}
                </span>
                <input type="hidden" name="items[${rowIndex}][document_index]" value="${data.docIndex !== undefined ? data.docIndex : ''}">
            </td>
            <td class="col-particular">
                <input type="text" name="items[${rowIndex}][particular]" value="${data.particular || ''}" required placeholder="Particular / item title">
            </td>
            <td class="col-unique">
                <input type="text" name="items[${rowIndex}][unique_id]" value="${data.uniqueId || ''}" placeholder="Ref / serial ID">
            </td>
            <td class="col-qty">
                <input type="text" name="items[${rowIndex}][quantity]" value="${data.qty || '1'}" placeholder="1">
            </td>
            <td class="col-desc">
                <input type="text" name="items[${rowIndex}][description]" value="${data.desc || ''}" placeholder="Description / specifications">
            </td>
            <td class="col-remarks">
                <input type="text" name="items[${rowIndex}][remarks]" value="${data.remarks || 'User reviewed'}" placeholder="Remarks">
            </td>
            <td class="col-source">
                ${data.sourceBadge || '<span class="badge-confidence autofilled">Manual</span>'}
            </td>
            <td class="col-action">
                <button type="button" class="btn-remove-file" title="Delete row" style="font-size: 14px;">&times;</button>
            </td>
        `;

        // Row deletion
        row.querySelector('.col-action button').addEventListener('click', function () {
            row.remove();
            renumberItemRows();
        });

        itemsTbody.appendChild(row);
    }

    function renumberItemRows() {
        Array.from(itemsTbody.children).forEach(function (row, idx) {
            row.querySelector('.col-pos').textContent = idx + 1;
            row.querySelectorAll('input').forEach(function (input) {
                input.name = input.name.replace(/items\[\d+\]/, `items[${idx}]`);
            });
        });
    }

    addItemBtn.addEventListener('click', function () {
        addItemRowElement({
            index: itemsTbody.children.length,
            docIndex: '',
            fileName: '',
            particular: 'Additional Item ' + (itemsTbody.children.length + 1),
            uniqueId: '',
            qty: '1',
            desc: '',
            remarks: 'Manually added item',
            sourceBadge: '<span class="badge-confidence autofilled">User Added</span>'
        });
    });

    /* ============================================================
       7. CAMERA CAPTURE (PHOTO SCANNER)
    ============================================================ */
    cameraTrigger.addEventListener('click', async function () {
        cameraModal.style.display = 'flex';
        cameraStatus.textContent = 'Starting camera preview...';
        btnSnapPhoto.style.display = 'inline-flex';
        btnUsePhoto.style.display = 'none';

        try {
            await startCamera();
            cameraStatus.textContent = 'Position your document clearly, then click Capture Photo.';
        } catch (err) {
            console.error('Camera error:', err);
            cameraStatus.textContent = 'Could not access camera. Please allow camera permissions or browse files instead.';
        }
    });

    async function startCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) { track.stop(); });
        }
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: cameraFacingMode } },
            audio: false
        });
        cameraVideo.srcObject = cameraStream;
        await cameraVideo.play();
    }

    btnSwitchCamera.addEventListener('click', async function () {
        cameraFacingMode = cameraFacingMode === 'environment' ? 'user' : 'environment';
        await startCamera();
    });

    btnSnapPhoto.addEventListener('click', function () {
        if (!cameraVideo.videoWidth) return;

        cameraCanvas.width = cameraVideo.videoWidth;
        cameraCanvas.height = cameraVideo.videoHeight;
        const ctx = cameraCanvas.getContext('2d');
        ctx.drawImage(cameraVideo, 0, 0);

        cameraCanvas.toBlob(function (blob) {
            if (blob) {
                capturedPhotoBlob = blob;
                btnSnapPhoto.style.display = 'none';
                btnUsePhoto.style.display = 'inline-flex';
                cameraStatus.textContent = 'Photo captured! Click "Use Photo" to attach it, or close to retake.';
            }
        }, 'image/jpeg', 0.92);
    });

    btnUsePhoto.addEventListener('click', function () {
        if (capturedPhotoBlob) {
            const fileName = `captured-doc-${Date.now()}.jpg`;
            const photoFile = new File([capturedPhotoBlob], fileName, { type: 'image/jpeg' });
            addFiles([photoFile]);
            closeCamera();
        }
    });

    function closeCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) { track.stop(); });
            cameraStream = null;
        }
        cameraVideo.srcObject = null;
        cameraModal.style.display = 'none';
    }

    btnCloseCamera.addEventListener('click', closeCamera);
    btnCancelCamera.addEventListener('click', closeCamera);

    /* ============================================================
       8. DOCUMENT MANAGEMENT PICKER
    ============================================================ */
    docmgmtTrigger.addEventListener('click', function () {
        docmgmtModal.style.display = 'flex';
    });

    function closeDocmgmt() {
        docmgmtModal.style.display = 'none';
    }

    btnCloseDocmgmt.addEventListener('click', closeDocmgmt);
    btnCancelDocmgmt.addEventListener('click', closeDocmgmt);

    // Filter search
    if (docmgmtSearch) {
        docmgmtSearch.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.docmgmt-row').forEach(function (row) {
                const name = row.dataset.name.toLowerCase();
                const trn = (row.dataset.trn || '').toLowerCase();
                const cat = row.dataset.category.toLowerCase();
                const match = name.includes(q) || trn.includes(q) || cat.includes(q);
                row.style.display = match ? '' : 'none';
            });
        });
    }

    btnApplyDocmgmt.addEventListener('click', function () {
        const checked = document.querySelectorAll('.docmgmt-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one document from Document Management.');
            return;
        }

        checked.forEach(function (chk) {
            const name = chk.dataset.docName;
            const category = chk.dataset.docCategory || 'document';
            const trn = chk.dataset.docTrn;

            // Create a virtual file representation or row item
            const mockBlob = new Blob([`Document Management Archive: ${name} (TRN: ${trn})`], { type: 'application/pdf' });
            const virtualFile = new File([mockBlob], name, { type: 'application/pdf' });

            selectedFiles.push({
                file: virtualFile,
                category: category,
                analysis: {
                    fields: { subject: name.replace(/\.[^/.]+$/, ''), reference: trn },
                    field_metadata: { subject: { source: 'doc_management', confidence: 'high' } }
                },
                duplicateWarning: false,
                matchedRecord: { label: 'Doc Mgmt Record', tracking_number: trn, document_name: name },
                ocrStatus: 'completed'
            });
        });

        syncInputFiles();
        renderQueue();
        rebuildItemsTable();
        applyExtractedHeaderFields();
        updateSectionsVisibility();
        closeDocmgmt();
    });

    /* ============================================================
       9. FORM SUBMISSION (CREATE DRAFT & PERMANENT TRN)
    ============================================================ */
    form.addEventListener('submit', async function (e) {
        if (form.dataset.submitting === 'true') {
            e.preventDefault();
            return;
        }

        // Validate at least one file or item
        if (selectedFiles.length === 0) {
            e.preventDefault();
            alert('Please upload or drag at least one document before submitting.');
            return;
        }

        // Validate subject
        if (!subjectInput.value.trim()) {
            e.preventDefault();
            alert('Please provide a Transmittal Subject.');
            subjectInput.focus();
            return;
        }

        form.dataset.submitting = 'true';
        submitDraftBtn.disabled = true;
        submitDraftBtn.textContent = 'Creating Working Draft & TRN...';

        // Check if any large files need resumable chunked upload first
        try {
            for (let i = 0; i < selectedFiles.length; i++) {
                const item = selectedFiles[i];
                if (item.file.size > 20 * 1024 * 1024 && !item.file.resumableUploadId) {
                    submitDraftBtn.textContent = `Uploading ${item.file.name}...`;
                    await uploadResumableFile(item.file);
                }
            }

            // Append OCR tokens and categories as hidden inputs
            selectedFiles.forEach(function (item, idx) {
                // Category
                const catInput = document.createElement('input');
                catInput.type = 'hidden';
                catInput.name = 'categories[]';
                catInput.value = item.category || 'document';
                form.appendChild(catInput);

                // OCR token
                const tokInput = document.createElement('input');
                tokInput.type = 'hidden';
                tokInput.name = 'ocr_tokens[]';
                tokInput.value = item.analysis && item.analysis.analysis_token ? item.analysis.analysis_token : '';
                form.appendChild(tokInput);

                // Upload IDs for resumable files
                if (item.file.resumableUploadId) {
                    const upIdInput = document.createElement('input');
                    upIdInput.type = 'hidden';
                    upIdInput.name = 'upload_ids[]';
                    upIdInput.value = item.file.resumableUploadId;
                    form.appendChild(upIdInput);

                    const upCatInput = document.createElement('input');
                    upCatInput.type = 'hidden';
                    upCatInput.name = 'upload_categories[]';
                    upCatInput.value = item.category || 'document';
                    form.appendChild(upCatInput);

                    const upTokInput = document.createElement('input');
                    upTokInput.type = 'hidden';
                    upTokInput.name = 'upload_ocr_tokens[]';
                    upTokInput.value = item.analysis && item.analysis.analysis_token ? item.analysis.analysis_token : '';
                    form.appendChild(upTokInput);
                }
            });

            // Allow the form to submit natively to create draft and redirect
        } catch (err) {
            e.preventDefault();
            form.dataset.submitting = 'false';
            submitDraftBtn.disabled = false;
            submitDraftBtn.textContent = 'Confirm & Create Working Draft (TRN) →';
            alert('Upload preparation failed: ' + err.message);
        }
    });
});
</script>

@endsection
