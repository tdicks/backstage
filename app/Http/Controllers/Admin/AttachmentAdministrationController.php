<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AttachmentAdministrationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $search = trim($request->string('q')->toString());
        $sort = $request->string('sort')->toString() ?: 'created_at';
        $direction = strtolower($request->string('direction')->toString()) === 'asc' ? 'asc' : 'desc';

        $sortableColumns = ['created_at', 'type', 'size_bytes'];
        if (! in_array($sort, $sortableColumns, true)) {
            $sort = 'created_at';
        }

        $attachments = Attachment::query()
            ->with([
                'uploader:id,name',
                'attachable' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Set::class => ['session'],
                        Song::class => ['set.session'],
                        Slot::class => ['song.set.session'],
                    ]);
                },
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('label', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%")
                        ->orWhereHas('uploader', function ($uploaderQuery) use ($search): void {
                            $uploaderQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.attachments.index', [
            'attachments' => $attachments,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($attachment->type === Attachment::TYPE_FILE && $attachment->disk && $attachment->path) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('status', 'Attachment deleted.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }
}
