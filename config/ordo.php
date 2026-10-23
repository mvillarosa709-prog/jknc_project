<?php

return [
    'office_name' => env('ORDO_OFFICE_NAME', 'John Kelly & Company'),

'ocr' => [
    'binary' => env(
        'ORDO_TESSERACT_BINARY',
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'
    ),
    'pdf_binary' => env(
        'ORDO_PDFTOPPM_BINARY',
        'C:\\Users\\Admin\\Downloads\\poppler-26.09.0\\Library\\bin\\pdftoppm.exe'
    ),
    'timeout' => (int) env('ORDO_OCR_TIMEOUT', 30),
    'max_files' => (int) env('ORDO_OCR_MAX_FILES', 10),
],

    'upload' => [
        'max_bytes' => (int) env('ORDO_UPLOAD_MAX_BYTES', 1073741824),
        'chunk_bytes' => (int) env('ORDO_UPLOAD_CHUNK_BYTES', 5242880),
        'expiry_hours' => (int) env('ORDO_UPLOAD_EXPIRY_HOURS', 24),
        'completion_lock_seconds' => (int) env('ORDO_UPLOAD_COMPLETION_LOCK_SECONDS', 3600),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'sender_email' => env('BREVO_SENDER_EMAIL'),
        'sender_name' => env('BREVO_SENDER_NAME', env('APP_NAME', 'ORDO Transmittal')),
        'acknowledgment_days' => (int) env('ORDO_ACKNOWLEDGMENT_DAYS', 30),
    ],
];
