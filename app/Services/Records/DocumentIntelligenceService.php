<?php

namespace App\Services\Records;

use App\Models\RecordClassification;
use App\Models\RecordIndex;
use App\Models\RecordIntakeItem;

class DocumentIntelligenceService
{
    /**
     * Analyze an intake item and detect the best matching Classification, Index, Source, and Document Date.
     *
     * @return array{classification_id: string|null, index_id: string|null, source: string|null, document_date: string|null}
     */
    public function detect(RecordIntakeItem $item): array
    {
        $tenantId = $item->tenant_id;
        $companyId = $item->company_id;

        $master = $item->files()->where('file_role', 'master')->first()
            ?? $item->files()->first();

        $textToAnalyze = trim(implode(' ', array_filter([
            $item->title,
            $master?->original_name,
            $master?->ocr_text,
        ])));

        if ($textToAnalyze === '') {
            return [
                'classification_id' => $item->suggested_classification_id,
                'index_id' => $item->suggested_index_id,
                'source' => $item->source,
                'document_date' => $item->document_date?->format('Y-m-d'),
            ];
        }

        $classifications = RecordClassification::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['indexes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get();

        $detectedClassificationId = $item->suggested_classification_id;
        $detectedIndexId = $item->suggested_index_id;
        $detectedSource = $item->source;
        $detectedDate = $item->document_date?->format('Y-m-d');

        $lowerText = strtolower($textToAnalyze);

        // 1. Search for specific index matches first
        $bestIndex = null;
        $bestClassification = null;

        // Common high-confidence keyword patterns
        $indexPatterns = [
            '2303' => ['2303', 'bir form 2303', 'certificate of registration'],
            '2307' => ['2307', 'bir form 2307', 'creditable tax', 'withholding tax'],
            '1601c' => ['1601c', '1601-c'],
            'board resolution' => ['board resolution', 'trustee resolution', 'trustees resolution'],
            'minutes' => ['minutes of', 'board minutes', 'meeting minutes'],
            'general information sheet' => ['general information sheet', 'gis'],
            'secretary\'s certificate' => ['secretary certificate', 'secretary\'s certificate', 'sec cert'],
            'articles of incorporation' => ['articles of incorporation', 'by-laws', 'incorporation'],
            'bank' => ['bank statement', 'bank maintenance', 'deposit confirmation', 'bank account'],
            'operation' => ['operation', 'operations', 'delivery receipt', 'work order', 'job order'],
        ];

        foreach ($classifications as $classification) {
            foreach ($classification->indexes as $index) {
                $indexNameLower = strtolower($index->name);
                $indexCodeLower = strtolower($index->code);

                // Exact phrase or whole-word code check in text
                if (preg_match('/\b' . preg_quote($indexCodeLower, '/') . '\b/i', $lowerText)) {
                    $bestIndex = $index;
                    $bestClassification = $classification;
                    break 2;
                }

                // Pattern checks
                foreach ($indexPatterns as $category => $terms) {
                    if (str_contains($indexNameLower, $category)) {
                        foreach ($terms as $term) {
                            $matched = strlen($term) <= 4
                                ? (bool) preg_match('/\b' . preg_quote($term, '/') . '\b/i', $lowerText)
                                : str_contains($lowerText, $term);

                            if ($matched) {
                                $bestIndex = $index;
                                $bestClassification = $classification;
                                break 3;
                            }
                        }
                    }
                }
            }

            // Check classification code / name if index hasn't matched yet
            $classNameLower = strtolower($classification->name);
            $classCodeLower = strtolower($classification->code);
            if ($bestClassification === null) {
                if (preg_match('/\b' . preg_quote($classCodeLower, '/') . '\b/i', $lowerText) ||
                    (strlen($classNameLower) > 4 && str_contains($lowerText, $classNameLower))) {
                    $bestClassification = $classification;
                }
            }
        }

        // Apply detected classification and index
        if ($bestClassification !== null) {
            $detectedClassificationId = (string) $bestClassification->id;
            if ($bestIndex !== null) {
                $detectedIndexId = (string) $bestIndex->id;
            } elseif ($bestClassification->indexes->isNotEmpty()) {
                $detectedIndexId = (string) $bestClassification->indexes->first()->id;
            }
        }

        // 2. Detect source if empty
        if (blank($detectedSource)) {
            if (preg_match('/\b(bir|tax|revenue|2303|2307|1601c)\b/i', $lowerText)) {
                $detectedSource = 'Bureau of Internal Revenue';
            } elseif (preg_match('/\b(sec|gis|incorporation)\b/i', $lowerText) || str_contains($lowerText, 'securities and exchange') || str_contains($lowerText, 'general information sheet')) {
                $detectedSource = 'Securities and Exchange Commission';
            } elseif (str_contains($lowerText, 'board resolution') || str_contains($lowerText, 'board of directors') || str_contains($lowerText, 'directors')) {
                $detectedSource = 'Board of Directors';
            } elseif (str_contains($lowerText, 'corporate secretary') || str_contains($lowerText, 'sec cert')) {
                $detectedSource = 'Corporate Secretary';
            } elseif (preg_match('/\brcbc\b/i', $lowerText)) {
                $detectedSource = 'RCBC';
            } elseif (preg_match('/\bbdo\b/i', $lowerText)) {
                $detectedSource = 'BDO';
            } elseif (str_contains($lowerText, 'operation') || str_contains($lowerText, 'delivery') || str_contains($lowerText, 'job order')) {
                $detectedSource = 'Management';
            } elseif (str_contains($lowerText, 'client') || str_contains($lowerText, 'customer')) {
                $detectedSource = 'Supplier';
            }
        }

        // 3. Detect document date if empty
        if (blank($detectedDate)) {
            // Check for MM/DD/YYYY or MM-DD-YYYY
            if (preg_match('/\b(0?[1-9]|1[0-2])[\/\-](0?[1-9]|[12]\d|3[01])[\/\-](\d{4})\b/', $lowerText, $dateMatches)) {
                $detectedDate = sprintf('%04d-%02d-%02d', (int) $dateMatches[3], (int) $dateMatches[1], (int) $dateMatches[2]);
            } elseif (preg_match('/\b(\d{4})[\/\-](0?[1-9]|1[0-2])[\/\-](0?[1-9]|[12]\d|3[01])\b/', $lowerText, $dateMatches)) {
                $detectedDate = sprintf('%04d-%02d-%02d', (int) $dateMatches[1], (int) $dateMatches[2], (int) $dateMatches[3]);
            } elseif (preg_match('#(0?[1-9]|1[0-2])[i/\-.](0?[1-9]|[12]\d|3[01])[i/\-.~]([~2\d][\d.]{2,4})#', $lowerText, $dateMatches)) {
                $yearDigits = preg_replace('/[^\d]/', '', str_replace('~', '2', $dateMatches[3]));
                if (strlen($yearDigits) === 4) {
                    $detectedDate = sprintf('%04d-%02d-%02d', (int) $yearDigits, (int) $dateMatches[1], (int) $dateMatches[2]);
                }
            }
        }

        return [
            'classification_id' => $detectedClassificationId,
            'index_id' => $detectedIndexId,
            'source' => $detectedSource,
            'document_date' => $detectedDate,
        ];
    }
}
