<?php

namespace App\Support;

use App\Models\Record;
use LogicException;
use Random\RandomException;

class DocumentReferenceGenerator
{
    /**
     * Ambiguous glyphs (0/O, 1/I) are excluded so a reference read aloud or
     * copied off a printed label stays unambiguous.
     */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LENGTH = 5;

    private const MAX_ATTEMPTS = 12;

    /**
     * Generate a Document Reference that is unique within the given tenant and company.
     *
     * The records table carries a unique index on (tenant_id, company_id, document_reference).
     * That index is the authoritative guarantee; this loop simply keeps the happy path from
     * ever reaching it.
     */
    public function generate(string $tenantId, string $companyId): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = $this->attempt();

            $exists = Record::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('document_reference', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw new LogicException('Could not allocate a unique Document Reference. Try filing again.');
    }

    /**
     * @throws RandomException
     */
    private function attempt(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $reference = '';

        for ($position = 0; $position < self::LENGTH; $position++) {
            $reference .= $alphabet[random_int(0, $max)];
        }

        return $reference;
    }
}
