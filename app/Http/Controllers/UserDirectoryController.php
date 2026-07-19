<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDirectoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim($request->string('q')->toString());

        $users = User::query()
            ->select(['id', 'name', 'bio', 'is_admin', 'slot_coverage'])
            ->where('hide_from_directory', false)
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($nested) use ($query): void {
                    $nested->where('name', 'like', "%{$query}%")
                        ->orWhere('bio', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('directory.index', [
            'users' => $users,
            'query' => $query,
            'slotOptions' => Slot::options(),
        ]);
    }
}
