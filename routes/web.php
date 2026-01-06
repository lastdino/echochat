<?php

use Illuminate\Support\Facades\Route;
use EchoChat\Models\Workspace;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/echochat/{workspace:slug}/{channel?}', function (Workspace $workspace, $channel = null) {
        return view('echochat::chat-page', [
            'workspace' => $workspace,
            'channel' => $channel ? $workspace->channels()->where('name', $channel)->first() : null,
        ]);
    })->name('echochat.chat');
});
