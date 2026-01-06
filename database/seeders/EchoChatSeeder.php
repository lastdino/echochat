<?php

namespace EchoChat\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use EchoChat\Models\Workspace;
use EchoChat\Models\Channel;

class EchoChatSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $workspace = Workspace::create([
            'name' => 'Default Workspace',
            'slug' => 'default',
            'owner_id' => $user->id,
        ]);

        $channels = ['general', 'random', 'development'];

        foreach ($channels as $name) {
            $workspace->channels()->create([
                'name' => $name,
                'creator_id' => $user->id,
            ]);
        }
    }
}
