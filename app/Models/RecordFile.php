<?php

namespace App\Models;

use App\Enums\FileRole;
use App\Enums\OcrStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

class RecordFile extends Model
{
    use HasUlids;

    public const RENDER_PDF = 'pdf';

    public const RENDER_IMAGE = 'image';

    public const RENDER_TEXT = 'text';

    public const RENDER_DOWNLOAD = 'download';

    /**
     * Contents we generated from an Office archive, shown in a sandboxed frame.
     */
    public const RENDER_HTML = 'html';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'record_id',
        'record_intake_item_id',
        'file_role',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'page_count',
        'checksum_sha256',
        'version',
        'ocr_status',
        'ocr_text',
        'ocr_error',
        'ocr_completed_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_role' => FileRole::class,
            'ocr_status' => OcrStatus::class,
            'ocr_completed_at' => 'datetime',
            'size_bytes' => 'integer',
            'page_count' => 'integer',
            'version' => 'integer',
        ];
    }

    public function intakeItem(): BelongsTo
    {
        return $this->belongsTo(RecordIntakeItem::class, 'record_intake_item_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(RecordActivityLog::class, 'record_file_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'record_id');
    }

    public function isFiled(): bool
    {
        return $this->record_id !== null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf' || $this->extension === 'pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Whether the browser will show this file as text on its own.
     *
     * Plain text and CSV are the only non-document types a browser renders
     * without help. That is what makes a .txt preview possible rather than a
     * fallback card — no converter, no library, just the iframe doing what it
     * already does.
     */
    public function isText(): bool
    {
        return in_array(strtolower((string) $this->mime_type), ['text/plain', 'text/csv'], true);
    }

    /**
     * How this file should be shown: pdf, image, text, html, or download.
     *
     * The drawer and the streaming endpoint both ask this, and they have to get
     * the same answer. They used to decide separately — the drawer rendered an
     * iframe because the extension said PDF, while the endpoint sent whatever the
     * sniffer had guessed — so a PDF the sniffer could not identify was handed
     * to an iframe as application/octet-stream, and the browser saved it instead
     * of showing it.
     */
    public function renderMode(): string
    {
        return match (true) {
            $this->canRenderAsHtml() => self::RENDER_HTML,
            $this->isPdf() => self::RENDER_PDF,
            $this->isImage() => self::RENDER_IMAGE,
            $this->isText() => self::RENDER_TEXT,
            default => self::RENDER_DOWNLOAD,
        };
    }

    /**
     * Whether the file's contents can be converted to something a browser can
     * show, and so are worth an iframe of generated HTML.
     *
     * Decided on the extension rather than the sniffed MIME, because that is what
     * the upload allowlist admits and because the two disagree often enough to
     * matter. Whether the conversion actually succeeds is not decided here — the
     * renderer finds out, and says so in the page rather than in this model.
     */
    public function canRenderAsHtml(): bool
    {
        return in_array(strtolower((string) $this->extension), ['docx', 'xlsx'], true);
    }

    /**
     * Whether the *stored bytes* may be displayed in place rather than downloaded.
     *
     * Deliberately narrower than renderMode(). An .xlsx is shown to the reader as
     * HTML we generated, but the bytes themselves are a ZIP archive and must never
     * go to the browser as something it will interpret — so it still downloads
     * from the preview route. This is the boundary that keeps a document from
     * being executed, and the fact that we can now show Office files does not
     * widen it by one byte.
     */
    public function isStreamableInline(): bool
    {
        return in_array($this->renderMode(), [self::RENDER_PDF, self::RENDER_IMAGE, self::RENDER_TEXT], true);
    }

    /**
     * The Content-Type this file must be served with.
     *
     * Always safe to put in front of a browser. A render mode decides whether the
     * response is inline or a download; this decides what the browser is told the
     * bytes are, which is what stops a file full of markup from being handed to
     * the HTML engine.
     */
    public function renderMimeType(): string
    {
        return match ($this->renderMode()) {
            // Stated rather than echoed: a .pdf the sniffer could not identify is
            // still served as a PDF, which is what makes it render at all.
            self::RENDER_PDF => 'application/pdf',
            self::RENDER_IMAGE, self::RENDER_TEXT => (string) $this->mime_type,
            default => 'application/octet-stream',
        };
    }

    /**
     * Order a record's files so the one a preview should show comes first.
     *
     * One rule, deliberately, because the browser used to pick the master itself
     * while the server picked something else — the pane rendered an iframe
     * labelled with the master's name, pointed at a URL streaming a supporting
     * file, and showed nothing. The two sides have to be able to disagree about
     * nothing.
     *
     * Ordering by the role rather than filtering is what makes it usable as a
     * fallback: `ORDER BY file_role` alone is not enough, because file_role is a
     * string and 'supporting' sorts before 'master'. That is the bug this replaces.
     *
     * Master role first, then the highest version, then the oldest id. Intake
     * files are created as the master at version 1, so this reads the same for an
     * item still sitting in the queue and for a filed record carrying supporting
     * files and versions.
     *
     * @param  Builder<RecordFile>  $query
     */
    public function scopeCurrentMasterFirst(Builder $query): void
    {
        $query->orderByRaw('CASE WHEN file_role = ? THEN 0 ELSE 1 END', [FileRole::Master->value])
            ->orderByDesc('version')
            ->orderBy('id');
    }

    /**
     * The file a preview of this file's owner should show.
     *
     * Falls back to the first file when nothing is marked as the master, so a
     * record whose roles were never set still previews something rather than
     * returning a 404 for a file that plainly exists.
     */
    public static function currentMasterFor(Builder|Relation $query): ?self
    {
        // A relation arrives as HasMany, not Builder. getQuery() hands back the
        // underlying builder with the relation's own constraints already applied
        // — record_id, or the intake item's — so the scope narrows within them
        // rather than escaping to every file on file.
        return $query->getQuery()->currentMasterFirst()->first();
    }

    /**
     * A short human label for the file type, matching the ORDO mock's friendlyFileType().
     */
    public function documentType(): string
    {
        $name = strtolower((string) $this->original_name);
        $mime = strtolower((string) $this->mime_type);

        return match (true) {
            $mime === 'application/pdf', str_ends_with($name, '.pdf') => 'PDF Document',
            $mime === 'image/jpeg', (bool) preg_match('/\.jpe?g$/', $name) => 'JPEG Image',
            $mime === 'image/png', str_ends_with($name, '.png') => 'PNG Image',
            $mime === 'image/webp', str_ends_with($name, '.webp') => 'WEBP Image',
            (bool) preg_match('/wordprocessingml|msword/', $mime), (bool) preg_match('/\.docx?$/', $name) => 'Word Document',
            (bool) preg_match('/spreadsheetml|excel/', $mime), (bool) preg_match('/\.xlsx?$/', $name) => 'Excel Spreadsheet',
            default => $mime !== '' ? strtoupper(explode('/', $mime)[1] ?? 'Document').' File' : 'Document',
        };
    }
}
