<?php

namespace EchoChat\Services;

use EchoChat\Models\Channel;
use Illuminate\Support\Facades\Http;

class AIModelService
{
    public function summarizeChannel(Channel $channel, ?int $limit = null): string
    {
        $limit = $limit ?? config('echochat.ai.message_limit', 50);

        $messages = $channel->messages()
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return 'メッセージがありません。';
        }

        $formattedMessages = $messages->map(fn ($m) => "{$m->user->name}: ".trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $m->content)))))->join("\n");

        $promptTemplate = $channel->workspace->ai_prompt ?? config('echochat.ai.summarize_prompt', "以下のチャット履歴を簡潔に日本語で要約してください。\n\n:messages");
        $prompt = str_replace(':messages', $formattedMessages, $promptTemplate);

        return $this->callAI($prompt);
    }

    public function extractImportantInfo(Channel $channel, string $userName, ?int $limit = null): string
    {
        $limit = $limit ?? config('echochat.ai.message_limit', 50);

        $messages = $channel->messages()
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return 'メッセージがありません。';
        }

        $formattedMessages = $messages->map(fn ($m) => "{$m->user->name}: ".trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $m->content)))))->join("\n");

        $defaultPrompt = "以下のチャット履歴から、:userName 宛ての重要な依頼、質問、または :userName が確認すべき重要情報を抽出し、簡潔な箇条書きの日本語でまとめてください。関連する情報がない場合は「特に関連する重要な情報はありません」と回答してください。\n\nチャット履歴:\n:messages";
        $promptTemplate = $channel->workspace->extract_ai_prompt ?? config('echochat.ai.extract_prompt', $defaultPrompt);

        $prompt = str_replace([':userName', ':messages'], [$userName, $formattedMessages], $promptTemplate);

        return $this->callAI($prompt);
    }

    protected function callAI(string $prompt): string
    {
        $driver = config('echochat.ai.driver', 'gemini');

        return match ($driver) {
            'gemini' => $this->callGemini($prompt),
            'ollama' => $this->callOllama($prompt),
            default => 'AIドライバーが設定されていません。',
        };
    }

    protected function callGemini(string $prompt): string
    {
        $apiKey = config('echochat.ai.gemini_api_key');
        $timeout = config('echochat.ai.timeout', 60);

        if (! $apiKey) {
            return 'Gemini APIキーが設定されていません。';
        }

        $response = Http::timeout($timeout)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            return 'Gemini APIへのリクエストに失敗しました。';
        }

        return $response->json('candidates.0.content.parts.0.text') ?? '要約に失敗しました。';
    }

    protected function callOllama(string $prompt): string
    {
        $endpoint = config('echochat.ai.ollama_endpoint', 'http://localhost:11434/api/generate');
        $model = config('echochat.ai.ollama_model', 'llama3');
        $timeout = config('echochat.ai.timeout', 60);

        $response = Http::timeout($timeout)->post($endpoint, [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
        ]);

        if ($response->failed()) {
            return 'Ollamaへのリクエストに失敗しました。ローカルサーバーが起動しているか確認してください。';
        }

        return $response->json('response') ?? '要約に失敗しました。';
    }
}
