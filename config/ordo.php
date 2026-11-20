<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Default Role
    |---------------------------------------------------------------------------
    |
    | The prototype has no sign-in, so requests arrive without an authenticated
    | user. Policies fall back to this role rather than denying everything, which
    | keeps the app usable before authentication lands.
    |
    | Set it to the most restrictive value that still lets you work; tighten it
    | to 'standard_user' once you are testing role enforcement by hand.
    |
    */

    'default_role' => env('ORDO_DEFAULT_ROLE', 'records_manager'),

    /*
    |---------------------------------------------------------------------------
    | Rate Limits
    |---------------------------------------------------------------------------
    |
    | Per-minute ceilings keyed to named limiters registered in AppServiceProvider.
    | Exports and the record picker are the expensive reads: both scan beyond a
    | single page and can be pointed at a large register.
    |
    */

    'rate_limits' => [
        'exports' => (int) env('ORDO_RATE_LIMIT_EXPORTS', 30),
        'picker' => (int) env('ORDO_RATE_LIMIT_PICKER', 60),
    ],

    'uploads' => [
        'intake_max_file_size_mb' => (int) env('ORDO_INTAKE_MAX_FILE_SIZE_MB', 20),
        'record_file_max_size_mb' => (int) env('ORDO_RECORD_FILE_MAX_SIZE_MB', 50),
    ],

    'ocr' => [
        'tesseract_binary' => env('ORDO_TESSERACT_BINARY', 'tesseract'),
        'pdftoppm_binary' => env('ORDO_PDFTOPPM_BINARY', 'pdftoppm'),
        'languages' => env('ORDO_OCR_LANGUAGES', 'eng'),
        'timeout_seconds' => (int) env('ORDO_OCR_TIMEOUT_SECONDS', 30),
        'max_pdf_pages' => (int) env('ORDO_OCR_MAX_PDF_PAGES', 20),
        'pdf_dpi' => (int) env('ORDO_OCR_PDF_DPI', 250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lookup Cache
    |--------------------------------------------------------------------------
    |
    | Seconds a company's classification, index and source lookups stay cached
    | for, in App\Services\Records\RecordLookups. The scheme is seeded rather
    | than edited from the interface, so a short window is enough to keep the
    | lookups off every page load. Anything that changes the scheme forgets the
    | affected company's entries explicitly, so this window is only a backstop
    | for a change that forgot to.
    |
    */

    'lookup_cache_ttl' => (int) env('ORDO_LOOKUP_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Accepted Document Extensions
    |--------------------------------------------------------------------------
    |
    | The one list of file extensions ORDO accepts as a document. It is read by
    | both the server-side validation rule and the file picker's accept attribute,
    | so the dialog cannot offer something the server will refuse. It lived only
    | in the validation trait, and the pickers were hand-written copies of it that
    | had already begun to drift.
    |
    | The list is checked by extension rather than by sniffed content type, on
    | purpose. App\Http\Requests\Concerns\ValidatesUploadedDocuments explains why,
    | and what protects a stored document whose bytes are not what its name says.
    |
    */

    'document_extensions' => [
        'pdf',
        'txt',
        'csv',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'jpg',
        'jpeg',
        'png',
        'webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Defaults
    |--------------------------------------------------------------------------
    |
    | The limits a company is given when it is first metered, applied by the
    | migration that introduces storage_limit_bytes. They live here rather than
    | in the migration so they can be changed and re-seeded without editing
    | migration history.
    |
    | storage_bytes is raw bytes to match record_files.size_bytes, so the figure
    | the Repository Usage card compares against is the same unit it sums.
    | A value of 0 is treated as "do not seed", leaving the company unlimited.
    |
    */

    'plan_defaults' => [
        'record_units' => (int) env('ORDO_DEFAULT_RECORD_UNITS', 1000),
        'storage_bytes' => (int) env('ORDO_DEFAULT_STORAGE_BYTES', 5368709120),
    ],

];
