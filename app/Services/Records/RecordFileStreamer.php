<?php

namespace App\Services\Records;

use App\Models\RecordFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serve a stored file to the browser.
 *
 * One place for the headers, because they are what make a preview work and they
 * are easy to get subtly wrong per-route: Content-Disposition decides whether the
 * browser renders the file or saves it, and Content-Type decides what it is
 * allowed to render it as.
 *
 * The disk path never leaves this class. Callers hand over a model and get bytes;
 * nothing addresses storage directly, so a preview URL cannot be turned into a
 * path traversal or a raw storage key.
 *
 * Rendering is decided by RecordFile::renderMode(), never here, so that what the
 * drawer draws and what the browser receives cannot drift apart.
 */
class RecordFileStreamer
{
    public function __construct(private readonly Request $request) {}

    /**
     * @throws NotFoundHttpException
     *                               when the bytes are not there
     */
    public function stream(RecordFile $file): StreamedResponse
    {
        return $this->serve($file, false);
    }

    /**
     * Download the original bytes, regardless of whether the browser can preview
     * the file inline.
     */
    public function download(RecordFile $file): StreamedResponse
    {
        return $this->serve($file, true);
    }

    private function serve(RecordFile $file, bool $download): StreamedResponse
    {
        $disk = Storage::disk($file->disk);

        abort_unless($disk->exists($file->path), 404);

        $size = (int) $disk->size($file->path);

        $headers = $this->headers($file, $size);

        // A renderable type is shown in place; anything else is downloaded.
        //
        // This is the security boundary, not a nicety. A file whose bytes sniff
        // as text/html, renamed to .doc and stored on the record, would
        // otherwise be served inline as HTML from this domain — a stored XSS
        // reachable by anyone with the preview URL. Only the renderable set ever
        // goes inline.
        if ($download || ! $file->isStreamableInline()) {
            $headers['Content-Disposition'] = sprintf(
                'attachment; filename="%s"',
                $this->safeName($file->original_name),
            );
        }

        $range = $this->range($size);

        $stream = $disk->readStream($file->path);

        abort_if($stream === false, 404);

        if ($range !== null && fseek($stream, $range[0]) === 0) {
            return $this->partial($stream, $range, $size, $headers);
        }

        // No range asked for, or a stream that cannot seek (an object store
        // read is not seekable). Serve the whole thing rather than a promise we
        // cannot keep.
        $headers['Content-Length'] = (string) $size;

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
        }, 200, $headers);
    }

    /**
     * The headers shared by every response from here.
     *
     * @param  array<string, string>  $headers
     */
    private function headers(RecordFile $file, int $size): array
    {
        return [
            'Content-Type' => $file->renderMimeType(),
            // Sent from the stored size rather than left to chunking: a browser
            // handed a truncated inline document renders a blank pane with no
            // error, and without this header it waits for bytes that never come.
            'Content-Length' => (string) $size,
            'Content-Disposition' => sprintf('inline; filename="%s"', $this->safeName($file->original_name)),
            'X-Content-Type-Options' => 'nosniff',
            // Defence in depth behind nosniff and the Content-Type above: even a
            // file that somehow arrives as markup is sandboxed with no origin and
            // no scripts, so it cannot reach this session or the database.
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            // Advertised so the browser's PDF viewer asks for what it needs rather
            // than buffering a large document end to end before drawing it.
            'Accept-Ranges' => 'bytes',
        ];
    }

    /**
     * The inclusive byte range the browser asked for, or null.
     *
     * Only the single-range form is honoured, which is all a viewer sends. A
     * multi-range request is ignored and served whole, because answering half of
     * it would be worse than answering all of it.
     *
     * @return array{0: int, 1: int}|null
     */
    private function range(int $size): ?array
    {
        $header = $this->request->headers->get('Range');

        if (! is_string($header) || $header === '' || $size <= 0) {
            return null;
        }

        if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches) !== 1) {
            return null;
        }

        [$start, $end] = [$matches[1], $matches[2]];

        if ($start === '' && $end === '') {
            return null;
        }

        if ($start === '') {
            // A suffix range: the final N bytes.
            $length = min((int) $end, $size);

            return $length > 0 ? [$size - $length, $size - 1] : null;
        }

        $from = (int) $start;

        if ($from >= $size) {
            return null;
        }

        $to = $end === '' ? $size - 1 : min((int) $end, $size - 1);

        return $to >= $from ? [$from, $to] : null;
    }

    /**
     * @param  resource  $stream
     * @param  array{0: int, 1: int}  $range
     * @param  array<string, string>  $headers
     */
    private function partial($stream, array $range, int $size, array $headers): StreamedResponse
    {
        [$from, $to] = $range;
        $length = $to - $from + 1;

        $headers['Content-Length'] = (string) $length;
        $headers['Content-Range'] = sprintf('bytes %d-%d/%d', $from, $to, $size);

        return response()->stream(function () use ($stream, $length): void {
            $remaining = $length;

            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(65536, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                echo $chunk;

                $remaining -= strlen($chunk);
            }

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 206, $headers);
    }

    /**
     * A filename safe to put in a quoted Content-Disposition.
     *
     * The stored name is reader-supplied, and a quote or a newline in it would
     * otherwise let a caller inject header content of its own.
     */
    private function safeName(?string $name): string
    {
        return str_replace(['"', "\r", "\n"], '', (string) $name);
    }
}
