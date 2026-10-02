<?php

namespace App\Http\Controllers;

use App\Models\ActivityAttachment;
use App\Services\ActivityAttachmentService;
use App\Services\ActivityAuditService;
use App\Services\ActivityTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActivityAttachmentController extends Controller
{
    public function __construct(
        private readonly ActivityTypeRegistry $types,
        private readonly ActivityAttachmentService $attachments,
        private readonly ActivityAuditService $audit
    ) {}

    public function index(Request $request, string $type, int $id)
    {
        $type = $this->types->normalize($type);
        $record = $this->types->resolve($type, $id, $request->user());

        return response()->json([
            'attachments' => $this->attachments->forActivity($type, $record),
        ]);
    }

    public function store(Request $request, string $type, int $id)
    {
        $type = $this->types->normalize($type);
        $this->types->resolve($type, $id, $request->user());

        $validated = $request->validate([
            'attachment' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,mp3,wav,m4a,mp4,mov',
                'max:20480',
            ],
            'category' => ['nullable', 'string', 'max:60'],
            'metadata' => ['nullable', 'array'],
        ]);

        $file = $validated['attachment'];
        $path = $file->store('activities/'.strtolower($type).'/'.$id, $type === ActivityTypeRegistry::EMAIL ? 'local' : 'public');

        $attachment = $this->attachments->createFromStoredFile(
            $type,
            $id,
            $path,
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $file->getSize(),
            $validated['category'] ?? null,
            $request->user()->id,
            $validated['metadata'] ?? []
        );

        $this->audit->record($type, $id, 'attachment_uploaded', [], [
            'attachment_id' => $attachment->id,
            'category' => $attachment->category,
        ]);

        return response()->json(
            $this->attachments->serialize($attachment->load('uploadedBy:id,name,email')),
            201
        );
    }

    public function destroy(Request $request, string $type, int $id, int $attachment)
    {
        $type = $this->types->normalize($type);
        $this->types->resolve($type, $id, $request->user());
        $attachment = ActivityAttachment::query()->findOrFail($attachment);

        abort_unless(
            $attachment->activity_type === $type && (int) $attachment->activity_id === $id,
            404
        );

        if ($attachment->storage_disk && $attachment->storage_path) {
            Storage::disk($attachment->storage_disk)->delete($attachment->storage_path);
        }

        $attachmentId = $attachment->id;
        $attachment->delete();

        $this->audit->record($type, $id, 'attachment_removed', [], [
            'attachment_id' => $attachmentId,
        ]);

        return response()->json(null, 204);
    }
}
