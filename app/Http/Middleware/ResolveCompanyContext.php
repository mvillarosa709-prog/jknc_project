<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyContext
{
    public function __construct(private readonly CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $wasSet = $this->context->has();

        try {
            $this->context->current();
        } catch (LogicException $e) {
            if ($wasSet) {
                abort(403, $e->getMessage());
            }

            $this->bootstrapDefaultCompany();
        }

        return $next($request);
    }

    /**
     * The prototype has no authentication yet, so a session without a company falls
     * back to the first available company. Scoping stays server-side: every query
     * still filters on the resolved tenant and company.
     */
    private function bootstrapDefaultCompany(): void
    {
        $company = Company::query()->orderBy('created_at')->orderBy('id')->first();

        if (! $company instanceof Company) {
            throw new LogicException('No company exists. Run `php artisan db:seed` before using the intake engine.');
        }

        $this->context->set($company);
    }
}
