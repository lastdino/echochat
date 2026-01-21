<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

Route::middleware(['web', 'auth'])->group(function () {
    $path = config('echochat.path', 'echochat');

    Route::get($path.'/attachments/{media:uuid}', function (Media $media) {
        $message = $media->model;

        if (! $message instanceof \EchoChat\Models\Message) {
            abort(404);
        }

        Gate::authorize('view', $message->channel->workspace);

        // チャンネルのメンバーであるかどうかのチェックも追加
        if (! $message->channel->isMember(auth()->id())) {
            // パブリックチャンネルであれば閲覧可能か？
            if ($message->channel->is_private) {
                abort(403);
            }
        }

        if (! \Illuminate\Support\Facades\Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            abort(404, 'File not found on disk: '.$media->getPathRelativeToRoot());
        }

        return response()->download(
            \Illuminate\Support\Facades\Storage::disk($media->disk)->path($media->getPathRelativeToRoot()),
            $media->file_name
        );
    })->name('echochat.attachments.show');

    Route::get($path.'/workspaces', \EchoChat\Livewire\WorkspaceList::class)
        ->name('echochat.workspaces');

    Route::get($path.'/{workspace:slug}/settings', \EchoChat\Livewire\WorkspaceSettings::class)
        ->name('echochat.workspaces.settings');

    Route::get($path.'/{workspace:slug}/{channel?}/{message?}', \EchoChat\Livewire\Chat::class)
        ->name('echochat.chat');

});
