<?php
// manage-locks.php
// A terminal tool to manage "Protected" tags in BookStack

// 1. Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use BookStack\Entities\Models\Page;

echo "\n🔒 BookStack Secure Page Manager\n";
echo "================================\n";

// 2. Fetch all pages with the 'Protected' tag
$protectedPages = Page::whereHas('tags', function ($query) {
    $query->where('name', 'Protected');
})->get();

if ($protectedPages->isEmpty()) {
    echo "No protected pages found.\n\n";
    exit;
}

// 3. Display Menu
$map = []; // Map selection index to Page Object
$i = 1;

echo sprintf("%-5s | %-40s | %-20s\n", "ID", "Page Title", "Current Password");
echo str_repeat("-", 70) . "\n";

foreach ($protectedPages as $page) {
    // Get the specific tag to show the current password
    $tag = $page->tags->where('name', 'Protected')->first();
    $currentPass = $tag->value ? $tag->value : "[Master PIN]";
    
    echo sprintf("%-5s | %-40s | %-20s\n", "[$i]", substr($page->name, 0, 38), $currentPass);
    $map[$i] = $page;
    $i++;
}
echo str_repeat("-", 70) . "\n";

// 4. Interactive Selection
echo "\nSelect a page number to edit (or press Enter to exit): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));

if (empty($line) || !isset($map[$line])) {
    echo "Exiting.\n";
    exit;
}

$selectedPage = $map[$line];
$currentTag = $selectedPage->tags->where('name', 'Protected')->first();

echo "\nSelected: " . $selectedPage->name . "\n";
echo "Current Password: " . ($currentTag->value ? $currentTag->value : "Master PIN") . "\n";
echo "Enter NEW password (leave empty to use Master PIN, or type 'DELETE' to unlock): ";

$newPass = trim(fgets($handle));

// 5. Update Logic
if ($newPass === 'DELETE') {
    // Remove the protection tag entirely
    $currentTag->delete();
    echo "\n✅ Protection REMOVED from page.\n";
} else {
    // Update the tag value
    $currentTag->value = $newPass;
    $currentTag->save();
    
    $status = empty($newPass) ? "Master PIN" : $newPass;
    echo "\n✅ Password updated to: $status\n";
}

echo "\n";