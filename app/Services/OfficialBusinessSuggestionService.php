<?php

namespace App\Services;

use App\Models\OfficialBusinessSuggestion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficialBusinessSuggestionService
{
    public function normalize(?string $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return filled($value) ? mb_strtolower($value) : null;
    }

    /**
     * Remember a reusable value. Blank values are intentionally ignored so drafts
     * never create unusable suggestion rows.
     *
     * @param array{company_id?: int|string|null, client_id?: int|string|null, deal_id?: int|string|null, project_id?: int|string|null} $context
     */
    public function remember(string $fieldType, ?string $value, array $context = []): ?OfficialBusinessSuggestion
    {
        $this->assertFieldType($fieldType);

        $normalized = $this->normalize($value);
        if ($normalized === null) {
            return null;
        }

        $display = preg_replace('/\s+/u', ' ', trim((string) $value));
        $context = $this->context($context);
        $identity = [
            'field_type' => $fieldType,
            'normalized_value' => $normalized,
            'context_key' => $this->contextKey($context),
        ];

        return DB::transaction(function () use ($identity, $display, $context): OfficialBusinessSuggestion {
            $suggestion = OfficialBusinessSuggestion::query()
                ->where($identity)
                ->lockForUpdate()
                ->first();

            if ($suggestion) {
                $suggestion->fill([
                    ...$context,
                    'display_value' => $display,
                    'frequency_count' => $suggestion->frequency_count + 1,
                    'last_used_at' => now(),
                ])->save();

                return $suggestion->fresh();
            }

            try {
                return OfficialBusinessSuggestion::create([
                    ...$identity,
                    ...$context,
                    'display_value' => $display,
                    'frequency_count' => 1,
                    'last_used_at' => now(),
                ]);
            } catch (QueryException $exception) {
                // A concurrent request won the unique-key race; reuse that row.
                $suggestion = OfficialBusinessSuggestion::query()
                    ->where($identity)
                    ->lockForUpdate()
                    ->firstOrFail();
                $suggestion->fill([
                    ...$context,
                    'display_value' => $display,
                    'frequency_count' => $suggestion->frequency_count + 1,
                    'last_used_at' => now(),
                ])->save();

                return $suggestion->fresh();
            }
        });
    }

    /**
     * @param array{company_id?: int|string|null, client_id?: int|string|null, deal_id?: int|string|null, project_id?: int|string|null} $context
     * @return Collection<int, OfficialBusinessSuggestion>
     */
    public function suggest(string $fieldType, ?string $query = null, array $context = [], int $limit = 10): Collection
    {
        $this->assertFieldType($fieldType);
        $context = $this->context($context);
        $needle = $this->normalize($query);

        return OfficialBusinessSuggestion::query()
            ->where('field_type', $fieldType)
            ->when($needle, fn ($builder) => $builder->where('normalized_value', 'like', '%'.$needle.'%'))
            ->when(array_key_exists('company_id', $context), fn ($builder) => $builder->where('company_id', $context['company_id']))
            ->when(array_key_exists('deal_id', $context), fn ($builder) => $builder->where('deal_id', $context['deal_id']))
            ->when(array_key_exists('project_id', $context), fn ($builder) => $builder->where('project_id', $context['project_id']))
            ->orderByDesc('frequency_count')
            ->orderByDesc('last_used_at')
            ->orderBy('display_value')
            ->limit(max(1, min($limit, 50)))
            ->get();
    }

    private function assertFieldType(string $fieldType): void
    {
        if (! in_array($fieldType, config('official_business.suggestion_field_types', []), true)) {
            throw ValidationException::withMessages([
                'field_type' => 'Unsupported Official Business suggestion field type.',
            ]);
        }
    }

    /** @return array{company_id?: int, deal_id?: int, project_id?: int} */
    private function context(array $context): array
    {
        $companyId = $context['company_id'] ?? $context['client_id'] ?? null;

        return collect([
            'company_id' => filled($companyId) ? (int) $companyId : null,
            'deal_id' => filled($context['deal_id'] ?? null) ? (int) $context['deal_id'] : null,
            'project_id' => filled($context['project_id'] ?? null) ? (int) $context['project_id'] : null,
        ])->filter(fn ($value): bool => $value !== null)->all();
    }

    private function contextKey(array $context): string
    {
        return implode('|', [
            'company:'.($context['company_id'] ?? ''),
            'deal:'.($context['deal_id'] ?? ''),
            'project:'.($context['project_id'] ?? ''),
        ]);
    }
}
