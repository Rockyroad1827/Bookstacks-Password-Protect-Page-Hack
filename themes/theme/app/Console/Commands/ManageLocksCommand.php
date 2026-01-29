<?php

namespace BookStack\Console\Commands;

use BookStack\Entities\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class ManageLocksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookstack:manage-locks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage PIN protection on BookStack pages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("🔒  BookStack Secure Page Manager");
        $this->info("================================");

        // Fetch Protected Pages
        $protectedPages = Page::whereHas('tags', function ($query) {
            $query->where('name', 'Protected');
        })->get();

        if ($protectedPages->isEmpty()) {
            $this->warn("No protected pages found.");
        }

        // Build Table Data
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
        $this->line("Current .env Master PIN: <fg=green>" . env('SECURE_PAGE_PIN', '(Not Set)') . "</>");
        $this->line("");

        // Interactive Selection
        $choice = $this->ask('Enter Page ID to edit, "M" to Change Master PIN, or press Enter to exit');

        // --- OPTION M: CHANGE MASTER PIN ---
        if (strtoupper($choice) === 'M') {
            return $this->changeMasterPin($protectedPages);
        }

        // --- OPTION: EXIT ---
        if (!$choice || !isset($map[$choice])) {
            $this->info("Exiting.");
            return 0;
        }

        // --- OPTION: EDIT SINGLE PAGE ---
        $selectedPage = $map[$choice];
        $currentTag = $selectedPage->tags->where('name', 'Protected')->first();
        
        $this->line("");
        $this->info("Selected: <fg=cyan>{$selectedPage->name}</>");
        $this->line("Current Password: " . ($currentTag->value ?: "Master PIN"));

        // Update Logic
        $newPass = $this->ask("Enter NEW password (leave empty for Master PIN, or type 'DELETE' to unlock)");

        if ($newPass === 'DELETE') {
            // Remove the protection tag
            $currentTag->delete();

            // Remove the Lock Symbol from the Page Title
            $lockSymbol = '🔒';
            if (strpos($selectedPage->name, $lockSymbol) !== false) {
                $selectedPage->name = trim(str_replace($lockSymbol, '', $selectedPage->name));
                $selectedPage->save();
                $this->info("✅  Lock symbol removed from page title.");
            }

            $this->info("✅  Protection REMOVED from page.");
        } else {
            $currentTag->value = $newPass;
            $currentTag->save();
            
            $status = empty($newPass) ? "Master PIN" : $newPass;
            $this->info("✅  Password updated to: {$status}");
        }

        return 0;
    }

    /**
     * Handle the Master PIN update logic
     */
    protected function changeMasterPin($allProtectedPages)
    {
        $this->info("\n--- UPDATING MASTER PIN ---");
        
        // Capture the OLD Master PIN (from loaded env) before we change it
        $oldMasterPin = env('SECURE_PAGE_PIN');
        
        $newPin = $this->ask("Enter the NEW Master PIN");

        if (empty($newPin)) {
            $this->error("PIN cannot be empty.");
            return 1;
        }

        if (!$this->confirm("Are you sure you want to change the Master PIN to '{$newPin}'?")) {
            $this->info("Operation cancelled.");
            return 0;
        }

        // Update .env File
        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $content = File::get($envPath);
            $key = 'SECURE_PAGE_PIN';
            
            if (strpos($content, "$key=") !== false) {
                // Replace existing key
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$newPin}", $content);
            } else {
                // Append new key
                $content .= "\n{$key}={$newPin}";
            }
            
            File::put($envPath, $content);
            $this->info("✅  Updated .env file.");
        } else {
            $this->error("Could not find .env file at: $envPath");
            return 1;
        }

        // Update Relevant Pages (Explicit + Implicit)
        $explicitUpdates = 0;
        $totalUpdated = 0;
        
        foreach ($allProtectedPages as $page) {
            $tag = $page->tags()->where('name', 'Protected')->first();
            if ($tag) {
                $val = $tag->value;

                // Explicitly using the OLD Master PIN -> Update DB to New PIN
                if (!empty($val) && $val == $oldMasterPin) {
                    $tag->value = $newPin; 
                    $tag->save();
                    $explicitUpdates++;
                    $totalUpdated++;
                }
                // Implicitly using Master PIN (empty value) -> Inherits New PIN automatically
                elseif (empty($val)) {
                    $totalUpdated++;
                }
                // Custom Password -> Ignored
            }
        }
        
        if ($explicitUpdates > 0) {
            $this->comment("ℹ️  Database updated for {$explicitUpdates} pages that explicitly used the old PIN.");
        }

        // Clear Config Cache
        $this->call('config:clear');
        
        // Final Summary
        $this->info("✅  Updated {$totalUpdated} total pages.");

        return 0;
    }
}
