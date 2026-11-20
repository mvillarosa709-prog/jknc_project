<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Record;
use LogicException;

/**
 * Issues the human-readable Record ID shown as REC-2026-001285.
 *
 * This is a second identifier, not a replacement for the Document Reference.
 * The 5-character reference is the system's own, generated randomly and checked
 * for uniqueness, and nothing here touches it. This one is readable, ordered and
 * configurable, which is what a reader can quote from a letter or find in a
 * folder name.
 *
 * The counter lives on the company row and is allocated under a row lock, in the
 * same transaction that files the record. Locking the *company* rather than the
 * intake item is the whole point: two different items filed at the same moment
 * would otherwise both read the same counter and take the same number. The
 * unique index on (tenant_id, company_id, record_number) is still the
 * authoritative guarantee — the lock only keeps the happy path from reaching it.
 */
class RecordNumberGenerator
{
    /**
     * How far past the configured sequence to look for a free number.
     *
     * Needed because an operator can set Next Sequence below what has already
     * been issued. Rather than failing that save, the generator walks forward to
     * the first free number. Bounded, because an unbounded walk would be a hang.
     */
    private const MAX_ATTEMPTS = 5000;

    /**
     * Take the next number for this company and leave the counter after it.
     *
     * Must be called inside the filing transaction, with the company row locked.
     */
    public function allocate(Company $company, ?\DateTimeInterface $now = null): string
    {
        $sequence = max(1, (int) ($company->record_next_sequence ?? 1));
        $taken = $this->issued($company);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = $this->format($company, $sequence, $now);

            if (! in_array($candidate, $taken, true)) {
                $company->forceFill([
                    // Left after the number just issued, so the tab's example
                    // always reads as the *next* one rather than the last.
                    'record_next_sequence' => $sequence + 1,
                ])->save();

                return $candidate;
            }

            $sequence++;
        }

        throw new LogicException(
            'Could not allocate a unique Record ID. Check the Next Sequence in Records Management Setup.',
        );
    }

    /**
     * The number the next filing would receive, without taking it.
     *
     * Used by the setup tab's example, which must not advance the counter just
     * because somebody opened the modal.
     */
    public function preview(Company $company, ?\DateTimeInterface $now = null): string
    {
        return $this->format($company, max(1, (int) ($company->record_next_sequence ?? 1)), $now);
    }

    /**
     * Assemble the number, exactly as the reference mock does:
     * prefix, an optional year, then the sequence padded to the chosen width.
     */
    public function format(Company $company, int $sequence, ?\DateTimeInterface $now = null): string
    {
        $prefix = trim((string) ($company->record_prefix ?? ''));

        if ($prefix === '') {
            $prefix = 'REC';
        }

        $digits = max(3, min(10, (int) ($company->record_sequence_digits ?? 6)));
        $year = $company->record_include_year ?? true
            ? '-'.($now ?? now())->format('Y')
            : '';

        return sprintf('%s%s-%s', $prefix, $year, str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT));
    }

    /**
     * The numbers this company has already issued.
     *
     * Read once per allocation and compared in memory. A Record ID is at most
     * forty characters, so a few thousand of them is nothing next with a query
     * per candidate.
     *
     * @return list<string>
     */
    private function issued(Company $company): array
    {
        return Record::query()
            ->where('tenant_id', $company->tenant_id)
            ->where('company_id', $company->id)
            ->whereNotNull('record_number')
            ->pluck('record_number')
            ->map(static fn ($number): string => (string) $number)
            ->all();
    }
}
