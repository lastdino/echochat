<?php

namespace EchoChat\Support;

use EchoChat\Models\Channel;
use EchoChat\Models\Message;
use EchoChat\Notifications\MentionedInMessage;
use EchoChat\Notifications\ReplyInThread;

class MessageSupport
{
    public static function notifyMentions(Message $message): void
    {
        if (empty($message->content)) {
            return;
        }

        $content = $message->content;
        $channel = $message->channel;

        // Check for @channel
        $hasChannelMention = str_contains($content, '@channel');
        if ($hasChannelMention) {
            $content = str_replace('@channel', '___MENTION_DONE___', $content);
            $membersToNotify = $channel->members()
                ->where('user_id', '!=', auth()->id())
                ->with('user')
                ->get()
                ->map(fn ($m) => $m->user);

            foreach ($membersToNotify as $user) {
                $user->notify(new MentionedInMessage($message, true));
            }
        }

        // チャンネルメンバーの名前リストを取得（長い順にソートして部分一致を防ぐ）
        $members = $channel->members()
            ->with('user')
            ->get()
            ->map(fn ($m) => [
                'user' => $m->user,
                'name' => UserSupport::getName($m->user),
            ])
            ->filter(fn ($m) => ! empty($m['name']))
            ->sortByDesc(fn ($m) => strlen($m['name']));

        $mentionedUserIds = [];

        foreach ($members as $member) {
            $name = $member['name'];
            $mention = '@'.$name;

            // メッセージ内に @名前 が含まれているか確認（単語境界を考慮）
            if (str_contains($content, $mention)) {
                if ($member['user']->id !== auth()->id()) {
                    $mentionedUserIds[] = $member['user']->id;
                    // 他の名前と部分一致しないように、マッチした部分を置換して除外する
                    $content = str_replace($mention, '___MENTION_DONE___', $content);
                }
            }
        }

        if (! empty($mentionedUserIds)) {
            $userModel = config('echochat.models.user', \App\Models\User::class);
            $mentionedUsers = $userModel::whereIn('id', array_unique($mentionedUserIds))->get();

            foreach ($mentionedUsers as $user) {
                $user->notify(new MentionedInMessage($message));
            }
        }
    }

    public static function notifyThreadParticipants(Message $message): void
    {
        if (! $message->parent_id) {
            return;
        }

        $parent = $message->parent()->with('user')->first();
        if (! $parent) {
            return;
        }

        // スレッド参加者（親メッセージの投稿者 + 返信の投稿者たち）
        $participantIds = Message::where('parent_id', $message->parent_id)
            ->where('user_id', '!=', auth()->id())
            ->pluck('user_id')
            ->push($parent->user_id)
            ->unique()
            ->filter(fn ($id) => $id !== auth()->id());

        if ($participantIds->isEmpty()) {
            return;
        }

        $userModel = config('echochat.models.user', \App\Models\User::class);
        $participants = $userModel::whereIn('id', $participantIds)->get();

        foreach ($participants as $user) {
            $user->notify(new ReplyInThread($message));
        }
    }

    public static function formatContent(string $content, ?Channel $channel = null): string
    {
        // HTMLタグが含まれているかチェック
        if ($content === strip_tags($content)) {
            // タグが含まれていない場合は通常通りエスケープと改行処理
            $escaped = e($content);
            $withBreaks = $escaped;
        } else {
            // HTMLタグが含まれている場合は、許可されたタグ以外を除去（XSS対策）
            // 許可するタグ: <b>, <i>, <u>, <s>, <a>, <ul>, <ol>, <li>, <code>, <pre>, <br>, <p>, <h1>, <h2>, <h3>
            $allowedTags = '<b><i><u><s><a><ul><ol><li><code><pre><br><p><h1><h2><h3>';
            $sanitized = strip_tags($content, $allowedTags);

            // 属性の除去（javascript: 等の除去のため、簡易的な処理）
            // より厳格な対策が必要な場合は HTML Purifier などの導入を推奨
            $sanitized = preg_replace('/on\w+="[^"]*"/i', '', $sanitized);
            $sanitized = preg_replace('/href="javascript:[^"]*"/i', 'href="#"', $sanitized);

            $withBreaks = $sanitized;
        }

        if (! $channel) {
            return $withBreaks;
        }

        // @channel のハイライト
        $replacements = [];
        if (str_contains($withBreaks, '@channel')) {
            $placeholder = '___MENTION_CHANNEL___';
            $withBreaks = str_replace('@channel', $placeholder, $withBreaks);
            $replacements[$placeholder] = '<span class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-1 rounded font-medium">@channel</span>';
        }

        // メンションをハイライト
        // チャンネルメンバーの名前リストを取得（長い順にソートして部分一致を防ぐ）
        $names = $channel->members()
            ->with('user')
            ->get()
            ->map(fn ($m) => UserSupport::getName($m->user))
            ->filter()
            ->unique()
            ->sortByDesc(fn ($name) => strlen($name));

        foreach ($names as $name) {
            $mention = '@'.e($name);
            $replacement = '___MENTION_'.md5($name).'___';
            $withBreaks = str_replace($mention, $replacement, $withBreaks);
            $replacements[$replacement] = '<span class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-1 rounded font-medium">'.$mention.'</span>';
        }

        if (count($replacements) > 0) {
            foreach ($replacements as $placeholder => $html) {
                $withBreaks = str_replace($placeholder, $html, $withBreaks);
            }
        }

        return $withBreaks;
    }
}
