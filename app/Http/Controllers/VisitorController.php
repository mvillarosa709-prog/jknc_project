<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $visitors = Visitor::query()
            ->with('contact')
            ->when(
                filled($validated['search'] ?? null),
                fn ($query) => $query->search(trim($validated['search']))
            )
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (Visitor $visitor): array => $this->serializeVisitor($visitor))
            ->values();

        return response()->json(['visitors' => $visitors]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_reference' => ['prohibited'],
            'visitor_id' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ]);

        $visitor = Visitor::query()->create($validated)->load('contact');

        return response()->json($this->serializeVisitor($visitor), 201);
    }

    public function serializeVisitor(Visitor $visitor): array
    {
        $contact = $visitor->relationLoaded('contact') ? $visitor->contact : null;

        return [
            'id' => $visitor->id,
            'visitor_reference' => $visitor->visitor_reference,
            'visitor_id' => $visitor->visitor_reference,
            'name' => $visitor->name,
            'mobile' => $visitor->mobile,
            'email' => $visitor->email,
            'company' => $visitor->company_name,
            'company_name' => $visitor->company_name,
            'contact_id' => $visitor->contact_id,
            'contact' => $contact ? [
                'id' => $contact->id,
                'name' => $contact->full_name ?: $contact->company_name,
                'company_name' => $contact->company_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ] : null,
        ];
    }
}
