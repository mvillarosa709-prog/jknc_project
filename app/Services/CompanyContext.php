<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Contracts\Session\Session;
use LogicException;

class CompanyContext
{
    public const SESSION_KEY = 'ordo.company_id';

    /**
     * The company resolved for this request.
     *
     * Resolving the company is a database read, and every scoped query in the
     * app asks this class for the tenant and company ids. Without a memo a
     * single page load re-reads the same row dozens of times, so the value is
     * held for the life of the request and dropped whenever the context changes.
     */
    private ?Company $resolved = null;

    public function __construct(private readonly Session $session) {}

    public function current(): Company
    {
        if ($this->resolved instanceof Company) {
            return $this->resolved;
        }

        $companyId = $this->session->get(self::SESSION_KEY);

        if (! is_string($companyId) || $companyId === '') {
            throw new LogicException('No company context is active for this request.');
        }

        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            $this->forget();

            throw new LogicException("The company context [{$companyId}] no longer exists.");
        }

        return $this->resolved = $company;
    }

    public function id(): string
    {
        return (string) $this->current()->id;
    }

    public function tenantId(): string
    {
        return (string) $this->current()->tenant_id;
    }

    public function set(Company $company): void
    {
        $this->session->put(self::SESSION_KEY, (string) $company->id);

        $this->resolved = $company;
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);

        $this->resolved = null;
    }

    public function has(): bool
    {
        return is_string($this->session->get(self::SESSION_KEY));
    }
}
