<?php

namespace App\Services;

use App\Exceptions\OcrUnavailableException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ExecutableNotFoundException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class TransmittalOcrService
{
    /**
     * @return array{category: string, text: string, fields: array<string, string|null>, field_metadata: array<string, array{source: string, confidence: string}>}
     */
    public function analyze(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path)) {
            throw new OcrUnavailableException('The uploaded file could not be read for OCR.');
        }

        $text = mb_substr(trim(
            $file->getMimeType() === 'application/pdf'
                ? $this->extractPdfText($path)
                : $this->extractImageText($path)
        ), 0, 25000);
        $name = mb_strtolower($file->getClientOriginalName().' '.$text);

        $category = match (true) {
            str_contains($name, 'receipt'), str_contains($name, 'acknowledgment') => 'receipt',
            str_contains($name, 'proof'), str_contains($name, 'transmission') => 'proof',
            str_contains($name, 'attachment'), str_contains($name, 'appendix') => 'attachment',
            default => 'document',
        };

        $fields = [
            'reference' => $this->firstMatch($text, '/(?:reference|ref(?:erence)?\s*(?:no|number)?)[\s:#-]*([A-Z0-9][A-Z0-9\/-]{2,})/i'),
            'unique_id' => $this->firstMatch($text, '/(?:unique\s*id|document\s*id|control\s*(?:no|number)|transmittal\s*(?:no|number))[\s:#-]*([A-Z0-9][A-Z0-9\/-]{2,})/i'),
            'date' => $this->firstMatch($text, '/\b(\d{4}-\d{2}-\d{2}|\d{1,2}[\/. -]\d{1,2}[\/. -]\d{2,4})\b/'),
            'subject' => $this->firstMatch($text, '/(?:subject|title)[\s:#-]*([^\r\n]{3,120})/i'),
            'description' => $this->firstMatch($text, '/(?:description|details)[\s:#-]*([^\r\n]{3,250})/i'),
            'company' => $this->firstMatch($text, '/(?:company|organization|client)[\s:#-]*([^\r\n]{2,120})/i'),
            'recipient_name' => $this->firstMatch($text, '/(?:to|recipient|attention|attn)[\s:#-]*([^\r\n]{2,120})/i'),
            'recipient_email' => $this->firstMatch($text, '/\b([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})\b/i'),
            'recipient_address' => $this->firstMatch($text, '/(?:delivery\s*address|recipient\s*address|address|ship\s*to)[\s:#-]*([^\r\n]{5,200})/i'),
            'acknowledged_by' => $this->firstMatch($text, '/(?:acknowledged\s*by|received\s*by|signed\s*by|receiver\s*name)[\s:#-]*([^\r\n]{2,160})/i'),
            'location' => $this->firstMatch($text, '/(?:location|place\s*received|received\s*at)[\s:#-]*([^\r\n]{2,200})/i'),
            'device' => $this->firstMatch($text, '/(?:device\s*(?:used)?|terminal)[\s:#-]*([^\r\n]{2,160})/i'),
            'method' => $this->firstMatch($text, '/(?:delivery\s*method|sent\s*via|method)[\s:#-]*(email|courier|in\s*person|portal|other)/i'),
            'acknowledged_date' => $this->firstMatch($text, '/(?:date\s*(?:acknowledged|received)|acknowledged\s*on|received\s*on)[\s:#-]*(\d{4}-\d{2}-\d{2}|\d{1,2}[\/. -]\d{1,2}[\/. -]\d{2,4})/i'),
            'proof_type' => match (true) {
                str_contains($name, 'email confirmation') => 'email_confirmation',
                str_contains($name, 'courier receipt') => 'courier_receipt',
                str_contains($name, 'receipt') => 'delivery_receipt',
                default => null,
            },
        ];
        $fieldMetadata = [];
        foreach ($fields as $field => $value) {
            if ($value !== null) {
                $fieldMetadata[$field] = [
                    'source' => 'tesseract_ocr',
                    'confidence' => $field === 'recipient_email' ? 'high' : 'medium',
                ];
            }
        }

        return [
            'category' => $category,
            'text' => $text,
            'fields' => $fields,
            'field_metadata' => $fieldMetadata,
        ];
    }

    private function firstMatch(string $text, string $pattern): ?string
    {
        return preg_match($pattern, $text, $matches) === 1 && isset($matches[1])
            ? trim($matches[1])
            : null;
    }

    private function extractImageText(string $path): string
    {
        return $this->runTesseract($path);
    }

    private function extractPdfText(string $path): string
    {
        $prefix = tempnam(sys_get_temp_dir(), 'ordo-ocr-');
        if ($prefix === false) {
            throw new OcrUnavailableException('A temporary file could not be created for PDF OCR.');
        }

        if (! unlink($prefix)) {
            throw new OcrUnavailableException('The temporary PDF OCR file could not be prepared.');
        }

        try {
            $conversion = Process::timeout(config('ordo.ocr.timeout'))
                ->run([config('ordo.ocr.pdf_binary'), '-f', '1', '-l', '5', '-r', '150', '-png', $path, $prefix]);

            if (! $conversion->successful()) {
                throw new OcrUnavailableException('PDF OCR could not rasterize this document. Verify the PDF and Poppler installation.');
            }

            $pages = glob($prefix.'-*.png') ?: [];
            sort($pages, SORT_NATURAL);
            if ($pages === []) {
                throw new OcrUnavailableException('PDF OCR did not produce readable pages.');
            }

            $text = [];
            foreach ($pages as $page) {
                $text[] = $this->runTesseract($page);
            }

            return implode("\n", $text);
        } catch (ExecutableNotFoundException|ProcessTimedOutException $exception) {
            throw new OcrUnavailableException(
                'PDF OCR requires the configured Poppler (pdftoppm) and Tesseract executables.',
                previous: $exception
            );
        } finally {
            foreach (glob($prefix.'-*.png') ?: [] as $page) {
                if (is_file($page) && ! unlink($page)) {
                    throw new OcrUnavailableException('Temporary PDF OCR pages could not be removed.');
                }
            }
        }
    }

    private function runTesseract(string $path): string
    {
        try {
            $result = Process::timeout(config('ordo.ocr.timeout'))
                ->run([config('ordo.ocr.binary'), $path, 'stdout']);
        } catch (ExecutableNotFoundException|ProcessTimedOutException $exception) {
            throw new OcrUnavailableException(
                'OCR is unavailable. Install Tesseract and configure ORDO_TESSERACT_BINARY.',
                previous: $exception
            );
        }

        if (! $result->successful()) {
            throw new OcrUnavailableException('Tesseract could not read one of the uploaded pages.');
        }

        return $result->output();
    }
}
