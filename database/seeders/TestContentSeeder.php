<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Modules\Pages\Models\Page;
use Modules\Blog\Models\Post;

class TestContentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (!$user) {
            $this->command->error('No user found. Please create a user first.');
            return;
        }

        // Create test page
        $page = Page::updateOrCreate(
            ['slug' => 'page-00'],
            [
                'title' => 'Test Page 00',
                'content' => '<h1>Welcome to Test Page 00</h1><p>This is a test page with some content.</p>',
                'status' => 'published',
                'author_id' => $user->id,
                'published_at' => now(),
            ]
        );
        $this->command->info("Created page: {$page->slug}");

        // Create test blog post
        $post = Post::updateOrCreate(
            ['slug' => 'post-01'],
            [
                'title' => 'Test Post 01',
                'content' => '<h1>Welcome to Test Post 01</h1><p>This is a test blog post with some content.</p>',
                'excerpt' => 'This is a test blog post excerpt.',
                'status' => 'published',
                'author_id' => $user->id,
                'published_at' => now(),
            ]
        );
        $this->command->info("Created post: {$post->slug}");

        $this->command->newLine();
        $this->command->info('Test URLs:');
        $this->command->line('- Page: http://127.0.0.1:8001/page-00');
        $this->command->line('- Blog: http://127.0.0.1:8001/blog/post-01');
    }
}
