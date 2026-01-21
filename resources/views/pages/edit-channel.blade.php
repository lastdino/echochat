<div class="p-4 bg-white dark:bg-zinc-800 rounded-lg shadow">
    <h3 class="text-lg font-bold mb-4 dark:text-white">チャンネルを編集</h3>
    <form wire:submit.prevent="updateChannel">
        <div class="space-y-4">
            <flux:field>
                <flux:label>チャンネル名</flux:label>
                <flux:input wire:model="name" placeholder="例: プロジェクトA" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>説明（任意）</flux:label>
                <flux:textarea wire:model="description" placeholder="チャンネルの目的などを入力してください" />
                <flux:error name="description" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">保存</flux:button>
            </div>
        </div>
    </form>
</div>
