<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function setIndex(Request $request, Set $set): JsonResponse
    {
        $this->authorize('view', $set);

        return response()->json([
            'attachments' => $this->attachmentPayload($set),
            'can_manage' => $request->user()->can('createForSet', [Attachment::class, $set]),
        ]);
    }

    public function songIndex(Request $request, Song $song): JsonResponse
    {
        $song->loadMissing('set');
        $this->authorize('view', $song->set);

        return response()->json([
            'attachments' => $this->attachmentPayload($song),
            'can_manage' => $request->user()->can('createForSong', [Attachment::class, $song]),
        ]);
    }

    public function slotIndex(Request $request, Slot $slot): JsonResponse
    {
        $slot->loadMissing('song.set');
        $this->authorize('view', $slot->song->set);

        return response()->json([
            'attachments' => $this->attachmentPayload($slot),
            'can_manage' => $request->user()->can('createForSlot', [Attachment::class, $slot]),
        ]);
    }

    public function setStore(Request $request, Set $set): JsonResponse
    {
        $this->authorize('createForSet', [Attachment::class, $set]);

        $this->createAttachment($request, $set);

        return response()->json([
            'message' => 'Attachment added.',
            'attachments' => $this->attachmentPayload($set->fresh()),
            'can_manage' => true,
        ], 201);
    }

    public function songStore(Request $request, Song $song): JsonResponse
    {
        $this->authorize('createForSong', [Attachment::class, $song]);

        $this->createAttachment($request, $song);

        return response()->json([
            'message' => 'Attachment added.',
            'attachments' => $this->attachmentPayload($song->fresh()),
            'can_manage' => true,
        ], 201);
    }

    public function slotStore(Request $request, Slot $slot): JsonResponse
    {
        $this->authorize('createForSlot', [Attachment::class, $slot]);

        $this->createAttachment($request, $slot);

        return response()->json([
            'message' => 'Attachment added.',
            'attachments' => $this->attachmentPayload($slot->fresh()),
            'can_manage' => true,
        ], 201);
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        if ($attachment->type === Attachment::TYPE_FILE && $attachment->disk && $attachment->path) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachable = $attachment->attachable;
        $attachment->delete();

        return response()->json([
            'message' => 'Attachment removed.',
            'attachments' => $attachable ? $this->attachmentPayload($attachable) : [],
            'can_manage' => $attachable ? $this->canManageAttachable($request, $attachable) : false,
        ]);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        abort_unless($attachment->type === Attachment::TYPE_FILE, 404);
        abort_unless($attachment->disk && $attachment->path, 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_filename ?: basename($attachment->path),
            array_filter([
                'Content-Type' => $attachment->mime_type,
            ])
        );
    }

    private function createAttachment(Request $request, Set|Song|Slot $attachable): Attachment
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([Attachment::TYPE_FILE, Attachment::TYPE_LINK])],
            'label' => ['nullable', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'max:2048', 'required_if:type,'.Attachment::TYPE_LINK],
            'file' => ['nullable', 'file', 'max:10240', 'required_if:type,'.Attachment::TYPE_FILE],
        ], [
            'file.max' => 'Attachments must be 10MB or smaller.',
        ]);

        $payload = [
            'uploader_user_id' => $request->user()->id,
            'type' => $validated['type'],
            'label' => $validated['label'] ?? null,
            'original_filename' => null,
            'disk' => null,
            'path' => null,
            'url' => null,
            'mime_type' => null,
            'size_bytes' => null,
        ];

        if ($validated['type'] === Attachment::TYPE_LINK) {
            $payload['url'] = $validated['url'];
        } else {
            $uploadedFile = $request->file('file');
            $disk = config('filesystems.default');
            $path = $uploadedFile->store('attachments/'.now()->format('Y/m'), ['disk' => $disk]);

            $payload['disk'] = $disk;
            $payload['path'] = $path;
            $payload['original_filename'] = $uploadedFile->getClientOriginalName();
            $payload['mime_type'] = $uploadedFile->getClientMimeType();
            $payload['size_bytes'] = $uploadedFile->getSize();
        }

        return $attachable->attachments()->create($payload);
    }

    private function canManageAttachable(Request $request, Set|Song|Slot $attachable): bool
    {
        if ($attachable instanceof Set) {
            return $request->user()->can('createForSet', [Attachment::class, $attachable]);
        }

        if ($attachable instanceof Song) {
            return $request->user()->can('createForSong', [Attachment::class, $attachable]);
        }

        return $request->user()->can('createForSlot', [Attachment::class, $attachable]);
    }

    private function attachmentPayload(Set|Song|Slot $attachable): array
    {
        $attachable->loadMissing('attachments.uploader');

        return $attachable->attachments
            ->map(fn (Attachment $attachment) => [
                'id' => $attachment->id,
                'type' => $attachment->type,
                'label' => $attachment->label,
                'original_filename' => $attachment->original_filename,
                'url' => $attachment->url,
                'download_url' => $attachment->type === Attachment::TYPE_FILE
                    ? route('attachments.download', $attachment)
                    : null,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'uploader_name' => $attachment->uploader?->name,
                'created_at' => optional($attachment->created_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
