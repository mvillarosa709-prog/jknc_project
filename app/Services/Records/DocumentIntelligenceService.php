<?php

namespace App\Services\Records;

use App\Models\RecordClassification;
use App\Models\RecordIndex;
use App\Models\RecordIntakeItem;

class DocumentIntelligenceService
{
    /**
     * Analyze an intake item and detect the best matching Classification, Index, and Source.
     *
     * @return array{classification_id: string|null, index_id: string|null, source: string|null}
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

        $lowerText = strtolower($textToAnalyze);

        // 1. Search for specific index matches first
        $bestIndex = null;
        $bestClassification = null;

        // Common high-confidence keyword patterns
        $indexPatterns = [
            'board resolution' => ['resolution', 'board resolution', 'trustee resolution'],
            'minutes' => ['minutes of', 'board minutes', 'meeting minutes'],
            'general information sheet' => ['general information sheet', 'gis'],
            'secretary\'s certificate' => ['secretary certificate', 'secretary\'s certificate', 'sec cert'],
            'articles of incorporation' => ['articles of incorporation', 'by-laws', 'incorporation'],
            '2303' => ['2303', 'certificate of registration', 'bir form 2303'],
            '2307' => ['2307', 'creditable tax', 'withholding tax', 'bir form 2307'],
            'bank' => ['bank statement', 'bank maintenance', 'deposit confirmation', 'bank account'],
            'operation' => ['operation', 'operations', 'delivery receipt', 'work order', 'job order'],
        ];

        foreach ($classifications as $classification) {
            foreach ($classification->indexes as $index) {
                $indexNameLower = strtolower($index->name);
                $indexCodeLower = strtolower($index->code);

                // Exact phrase check in text
                if (str_contains($lowerText, $indexNameLower) || str_contains($lowerText, $indexCodeLower)) {
                    $bestIndex = $index;
                    $bestClassification = $classification;
                    break 2;
                }

                // Pattern checks
                foreach ($indexPatterns as $category => $terms) {
                    if (str_contains($indexNameLower, $category)) {
                        foreach ($terms as $term) {
                            if (str_contains($lowerText, $term)) {
                                $bestIndex = $index;
                                $bestClassification = $classification;
                                break 3;
                            }
                        }
                    }
                }
            }

            // Check classification name if index hasn't matched yet
            $classNameLower = strtolower($classification->name);
            $classCodeLower = strtolower($classification->code);
            if ($bestClassification === null && (str_contains($lowerText, $classNameLower) || str_contains($lowerText, $classCodeLower))) {
                $bestClassification = $classification;
            }
        }

        // Apply detected classification and index
        if ($bestClassification !== null) {
            $detectedClassificationId = (string) $bestClassification->id;
            if ($bestIndex !== null) {
                $detectedIndexId = (string) $bestIndex->id;
            } elseif ($bestClassification->indexes->isNotEmpty()) {
                // If classification matched and has indexes, pick the first index
                $detectedIndexId = (string) $bestClassification->indexes->first()->id;
            }
        }

        // 2. Detect source if empty
        if (blank($detectedSource)) {
            if (str_contains($lowerText, 'board resolution') || str_contains($lowerText, 'resolution') || str_contains($lowerText, 'directors')) {
                $detectedSource = 'Corporate Secretary';
            } elseif (str_contains($lowerText, 'sec') || str_contains($lowerText, 'general information sheet') || str_contains($lowerText, 'incorporation')) {
                $detectedSource = 'SEC';
            } elseif (str_contains($lowerText, 'bir') || str_contains($lowerText, 'tax') || str_contains($lowerText, '2303') || str_contains($lowerText, '2307')) {
                $detectedSource = 'BIR';
            } elseif (str_contains($lowerText, 'bank')) {
                $detectedSource = 'Bank';
            } elseif (str_contains($lowerText, 'operation') || str_contains($lowerText, 'delivery') || str_contains($lowerText, 'job order')) {
                $detectedSource = 'Internal Operations';
            } elseif (str_contains($lowerText, 'client') || str_contains($lowerText, 'customer')) {
                $detectedSource = 'Client';
            }
        }

        return [
            'classification_id' => $detectedClassificationId,
            'index_id' => $detectedIndexId,
            'source' => $detectedSource,
        ];
    }
}
