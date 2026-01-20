<?php

use EchoChat\Models\Workspace;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;
use Flux\Flux;

new class extends Component {
    public Workspace $workspace;
    public string $name;
    public bool $allow_member_channel_creation;
    public bool $allow_member_channel_deletion;
    public ?string $ai_prompt= null;

    public function mount(Workspace $workspace)
    {
        Gate::authorize('update', $workspace);

        $this->workspace = $workspace;
        $this->name = $workspace->name;
        $this->allow_member_channel_creation = $workspace->allow_member_channel_creation;
        $this->allow_member_channel_deletion = $workspace->allow_member_channel_deletion;
        $this->ai_prompt = $workspace->ai_prompt;
    }

    public function save()
    {
        Gate::authorize('update', $this->workspace);

        $this->validate([
            'name' => 'required|string|max:255',
            'ai_prompt' => 'nullable|string',
        ]);

        $this->workspace->update([
            'name' => $this->name,
            'allow_member_channel_creation' => $this->allow_member_channel_creation,
            'allow_member_channel_deletion' => $this->allow_member_channel_deletion,
            'ai_prompt' => $this->ai_prompt,
        ]);

        Flux::toast('設定を保存しました。');
    }
}; ?>

<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl">ワークスペース設定</flux:heading>
        <flux:button variant="ghost" icon="chevron-left" href="{{ route('echochat.chat', ['workspace' => $workspace->slug]) }}">
            チャットに戻る
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <form wire:submit="save" class="p-6 space-y-6">
            <flux:field>
                <flux:label>ワークスペース名</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading level="3" class="mb-2">権限設定</flux:heading>
                <flux:text class="mb-4">メンバーができる操作を制限できます。</flux:text>

                <flux:switch wire:model="allow_member_channel_creation" label="メンバーによるチャンネル作成を許可する" description="オフにするとオーナーのみがチャンネルを作成できます。" />

                <div class="mt-4">
                    <flux:switch wire:model="allow_member_channel_deletion" label="メンバーによるチャンネル削除を許可する" description="オフにするとオーナーと作成者のみが削除できます。" />
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading level="3" class="mb-2">AI要約設定</flux:heading>
                <flux:text class="mb-4">チャンネルのメッセージを要約する際のプロンプトをカスタマイズできます。</flux:text>

                <flux:field>
                    <flux:label>要約プロンプト</flux:label>
                    <flux:textarea
                        wire:model="ai_prompt"
                        placeholder="以下のチャット履歴を簡潔に日本語で要約してください。&#10;&#10;:messages"
                        rows="5"
                    />
                    <flux:description>
                        <code>:messages</code> は実際のチャット履歴に置き換えられます。未入力の場合はデフォルトのプロンプトが使用されます。
                    </flux:description>
                    <flux:error name="ai_prompt" />
                </flux:field>
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">保存する</flux:button>
            </div>
        </form>
    </div>
</div>
