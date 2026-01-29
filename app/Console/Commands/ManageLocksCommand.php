<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use BookStack\Entities\Models\Page;

class ManageLocksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manage-locks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage PIN protection on BookStack pages';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("🔒  BookStack Secure Page Manager");
        $this->info("================================");

        // 1. Fetch Protected Pages
        $protectedPages = Page::whereHas('tags', function ($query) {
            $query->where('name', 'Protected');
        })->get();

        if ($protectedPages->isEmpty()) {
            $this->warn("No protected pages found.");
            return 0;
        }

        // 2. Build Table Data
        $headers = ['ID', 'Page Title', 'Current Password'];
        $rows = [];
        $map = [];

        foreach ($protectedPages as $page) {
            $tag = $page->tags->where('name', 'Protected')->first();
            $currentPass = $tag->value ? $tag->value : "<fg=yellow>[Master PIN]</>";
            
            $rows[] = [
                $page->id,
                $page->name,
                $currentPass
            ];
            
            // Map ID to Page Object for easy lookup
            $map[$page->id] = $page;
        }

        $this->table($headers, $rows);

        // 3. Interactive Selection
        $pageId = $this->ask('Enter the ID of the page to edit (or press Enter to exit)');

        if (!$pageId || !isset($map[$pageId])) {
            $this->info("Exiting.");
            return 0;
        }

        $selectedPage = $map[$pageId];
        $currentTag = $selectedPage->tags->where('name', 'Protected')->first();
        
        $this->line("");
        $this->info("Selected: <fg=cyan>{$selectedPage->name}</>");
        $this->line("Current Password: " . ($currentTag->value ?: "Master PIN"));

        // 4. Update Logic
        $newPass = $this->ask("Enter NEW password (leave empty for Master PIN, or type 'DELETE' to unlock)");

        if ($newPass === 'DELETE') {
            $currentTag->delete();
            $this->info("✅  Protection REMOVED from page.");
        } else {
            $currentTag->value = $newPass;
            $currentTag->save();
            
            $status = empty($newPass) ? "Master PIN" : $newPass;
            $this->info("✅  Password updated to: {$status}");
        }

        return 0;
    }
}