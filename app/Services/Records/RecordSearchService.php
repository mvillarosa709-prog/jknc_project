<?php

namespace App\Services\Records;

use App\Models\Record;
use App\Services\CompanyContext;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The one place that knows how to turn a filter array into a record query.
 *
 * Every query is scoped to the active company server-side, so no combination of
 * filters can widen the result set past the tenant boundary.
 */
class RecordSearchService
{
    /**
     * The character LIKE patterns are escaped with.
     *
     * Not a backslash: a backslash is itself an escape character inside a MySQL
     * or MariaDB string literal, so naming it as the ESCAPE character produces
     * SQL neither engine can parse. See escape() for the full reasoning.
     */
    private const ESCAPE = '!';

    public function __construct(private readonly CompanyContext $context) {}

    /**
     * Apply the register's filters. Every key is optional; absent or blank keys
     * are ignored rather than searched for as empty strings.
     *
     * @param  array<string, string>  $filters
     */
    public function search(array $filters): Builder
    {
        // A % or _ in a URL must search for a literal character rather than
        // silently widening the query to every row.
        $filters = $this->escape($filters);

        return $this->apply($this->scoped(), $filters);
    }

    /**
     * A scoped builder with no filters applied, for callers that add their own.
     */
    public function scoped(): Builder
    {
        $query = Record::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id());

        // Records marked confidential are hidden from roles that cannot see them,
        // rather than merely hidden in the UI.
        if (! Gate::allows('viewConfidential')) {
            $query->where('is_confidential', false);
        }

        return $query;
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            // Free-text search needs an explicit ESCAPE clause: the engines disagree on
            // the default, so relying on it makes a literal % searchable on one and
            // a wildcard on another.
            ->when($this->filled($filters, 'q'), fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->whereRaw(
                    "title LIKE ? ESCAPE '!'",
                    [$filters['q']],
                )
                    ->orWhereRaw("document_reference LIKE ? ESCAPE '!'", [$filters['q']])
                    ->orWhereRaw("id LIKE ? ESCAPE '!'", [$filters['q']])
                    ->orWhereRaw("source LIKE ? ESCAPE '!'", [$filters['q']])
                    ->orWhereRaw("external_reference_number LIKE ? ESCAPE '!'", [$filters['q']])
                    ->orWhereRaw("notes LIKE ? ESCAPE '!'", [$filters['q']])
            ))
            // OCR text lives on the file rows, so a full-text hit is a whereHas.
            ->when($this->filled($filters, 'ocr'), fn (Builder $q) => $q->whereHas(
                'files',
                fn (Builder $files) => $files->whereRaw(
                    "ocr_text LIKE ? ESCAPE '!'",
                    [$filters['ocr']],
                ),
            ))
            ->when($this->filled($filters, 'classification'), fn (Builder $q) => $q->whereHas(
                'classification',
                fn (Builder $inner) => $inner->where('code', $filters['classification']),
            ))
            ->when($this->filled($filters, 'index'), fn (Builder $q) => $q->whereHas(
                'index',
                fn (Builder $inner) => $inner->where('code', $filters['index']),
            ))
            ->when($this->filled($filters, 'source'), fn (Builder $q) => $q->where('source', $filters['source']))
            ->when($this->filled($filters, 'status'), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($this->filled($filters, 'from'), fn (Builder $q) => $q->whereDate('document_date', '>=', $filters['from']))
            ->when($this->filled($filters, 'to'), fn (Builder $q) => $q->whereDate('document_date', '<=', $filters['to']))
            ->when($this->filled($filters, 'currency'), fn (Builder $q) => $q->where('currency', $this->upper($filters['currency'])))
            ->when($this->filled($filters, 'amount_min'), fn (Builder $q) => $q->where('amount', '>=', $this->decimal($filters['amount_min'])))
            ->when($this->filled($filters, 'amount_max'), fn (Builder $q) => $q->where('amount', '<=', $this->decimal($filters['amount_max'])));
    }

    /**
     * The filter keys the register toolbar understands.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'q' => '',
            'ocr' => '',
            'classification' => '',
            'index' => '',
            'source' => '',
            'status' => '',
            'from' => '',
            'to' => '',
            'currency' => '',
            'amount_min' => '',
            'amount_max' => '',
        ];
    }

    /**
     * Normalise raw query input down to the known keys, trimmed.
     *
     * Unknown keys are dropped so a hand-crafted query string cannot reach the
     * query builder as a column name.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalise(array $input): array
    {
        $filters = self::defaults();

        foreach (array_keys($filters) as $key) {
            $filters[$key] = trim((string) ($input[$key] ?? ''));
        }

        return $filters;
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filled(array $filters, string $key): bool
    {
        return isset($filters[$key]) && $filters[$key] !== '';
    }

    /**
     * Neutralise LIKE wildcards so a term is matched as literal text.
     *
     * The escape character is written back out with its own wildcard, so the
     * term keeps its length and a real percent sign stays searchable. The
     * trailing `escape` call pairs with the ESCAPE clause added to each LIKE.
     *
     * @param  array<string, string>  $filters
     * @return array<string, string>
     */
    private function escape(array $filters): array
    {
        foreach (['q', 'ocr'] as $key) {
            if (! $this->filled($filters, $key)) {
                continue;
            }

            // '!' rather than a backslash, deliberately.
            //
            // A backslash is the default escape character inside a MySQL or
            // MariaDB string literal, so `ESCAPE '\'` is parsed as an escaped
            // quote and leaves the literal unterminated: the register's search
            // returned a syntax error on every MySQL and MariaDB connection and
            // SQLite hid it. The escape character itself must therefore be one with
            // no meaning in the SQL around it.
            //
            // '!' must be replaced first, or the doubling of the wildcards it
            // introduces would be escaped a second time.
            $term = str_replace(
                [self::ESCAPE, '%', '_'],
                [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
                $filters[$key],
            );

            $filters[$key] = '%'.$term.'%';
        }

        return $filters;
    }

    private function upper(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function decimal(string $value): string
    {
        return (string) (float) $value;
    }
}
