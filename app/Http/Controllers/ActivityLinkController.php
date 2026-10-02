<?php

namespace App\Http\Controllers;

use App\Models\ActivityLink;
use App\Services\ActivityAuditService;
use App\Services\ActivityLinkService;
use App\Services\ActivityTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ActivityLinkController extends Controller
{
    public function __construct(
        private readonly ActivityTypeRegistry $types,
        private readonly ActivityLinkService $links,
        private readonly ActivityAuditService $audit
    ) {}

    public function index(Request $request, string $type, int $id)
    {
        $sourceType = $this->types->normalize($type);
        $this->types->resolve($sourceType, $id, $request->user());

        $links = ActivityLink::query()
            ->with('createdBy:id,name,email')
            ->where(function ($query) use ($sourceType, $id): void {
                $query->where([
                    'source_type' => $sourceType,
                    'source_id' => $id,
                ])->orWhere([
                    'related_type' => $sourceType,
                    'related_id' => $id,
                ]);
            })
            ->orderBy('id')
            ->get()
            ->map(function (ActivityLink $link) use ($request, $sourceType, $id): ?array {
                $sourceIsFirst = $link->source_type === $sourceType && $link->source_id === $id;
                $relatedType = $sourceIsFirst ? $link->related_type : $link->source_type;
                $relatedId = $sourceIsFirst ? $link->related_id : $link->source_id;

                try {
                    $related = $this->types->resolve($relatedType, $relatedId, $request->user());
                } catch (ModelNotFoundException|ValidationException|HttpExceptionInterface) {
                    return null;
                }

                return [
                    'link_id' => $link->id,
                    'type' => $relatedType,
                    'id' => $relatedId,
                    'key' => $this->types->key($relatedType, $relatedId),
                    'title' => $this->types->title($relatedType, $related),
                    'created_by' => $link->createdBy,
                    'created_at' => $link->created_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['related_activities' => $links]);
    }

    public function store(Request $request, string $type, int $id)
    {
        $validated = $request->validate([
            'related_type' => ['required', 'string', 'max:60'],
            'related_id' => ['required', 'integer', 'min:1'],
        ]);

        $sourceType = $this->types->normalize($type);
        $relatedType = $this->types->normalize($validated['related_type']);
        $relatedId = (int) $validated['related_id'];

        $this->types->resolve($sourceType, $id, $request->user());
        $related = $this->types->resolve($relatedType, $relatedId, $request->user());

        if ($sourceType === $relatedType && $id === $relatedId) {
            return response()->json([
                'message' => 'An activity cannot be related to itself.',
                'errors' => ['related_id' => ['Self-links are not allowed.']],
            ], 422);
        }

        [$link, $created] = $this->links->create(
            $sourceType,
            $id,
            $relatedType,
            $relatedId,
            $request->user()->id
        );

        if ($created) {
            $this->audit->record($sourceType, $id, 'related_activity_linked', [], [
                'related_type' => $relatedType,
                'related_id' => $relatedId,
            ]);
        }

        return response()->json([
            'created' => $created,
            'link_id' => $link->id,
            'related_activity' => [
                'type' => $relatedType,
                'id' => $relatedId,
                'key' => $this->types->key($relatedType, $relatedId),
                'title' => $this->types->title($relatedType, $related),
            ],
        ], $created ? 201 : 200);
    }

    public function destroy(
        Request $request,
        string $type,
        int $id,
        string $relatedType,
        int $relatedId
    ) {
        $sourceType = $this->types->normalize($type);
        $relatedType = $this->types->normalize($relatedType);

        $this->types->resolve($sourceType, $id, $request->user());
        $this->types->resolve($relatedType, $relatedId, $request->user());

        $link = $this->links->find($sourceType, $id, $relatedType, $relatedId);
        abort_unless($link, 404);
        $link->delete();

        $this->audit->record($sourceType, $id, 'related_activity_unlinked', [], [
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        return response()->json(null, 204);
    }
}
