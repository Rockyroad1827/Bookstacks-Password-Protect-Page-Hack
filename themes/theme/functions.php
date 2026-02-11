<?php
use BookStack\Entities\Models\Page;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Event;
use Illuminate\Routing\Events\RouteMatched;

// --------------------------------------------------------------------------------
// 1. SEARCH BLOCKER (Raw Request Intercept)
// --------------------------------------------------------------------------------
// Immediately blocks search requests for "Protected" tags to prevent leaks.
if (isset($_SERVER['REQUEST_URI'])) {
    $rawUri = $_SERVER['REQUEST_URI'];
    $decodedUri = rawurldecode($rawUri);
    if (strpos($rawUri, '/search') !== false && stripos($decodedUri, 'Protected') !== false) {
        header("Location: /");
        exit();
    }
}

///////////////////////////////////////////////////////////////////////////////////
// Page Lock Logic & Helpers
///////////////////////////////////////////////////////////////////////////////////

// Check if a specific page is currently unlocked in the user's session
if (!function_exists('isPageUnlocked')) {
    function isPageUnlocked($pageId) {
        $unlockedPages = Session::get('secure_unlocked_pages', []);
        if (isset($unlockedPages[$pageId]) && $unlockedPages[$pageId] > time()) {
            return true;
        }
        return false;
    }
}

// Get the effective PIN for a page (Custom or Master)
if (!function_exists('getSecurePagePin')) {
    function getSecurePagePin($page) {
        $tag = $page->tags()->where('name', 'Protected')->first();
        if (!$tag) return null;
        return !empty($tag->value) ? $tag->value : env('SECURE_PAGE_PIN');
    }
}

// --------------------------------------------------------------------------------
// ROUTE INTERCEPTORS (API & Exports)
// --------------------------------------------------------------------------------
Event::listen(RouteMatched::class, function (RouteMatched $event) {
    $request = request();
    $path = $request->path();

    // --- API PROTECTION ---
    // Intercepts calls to /api/pages/{id}
    // We use a regex check or simple string check to catch the ID
    if (preg_match('#^api/pages/(\d+)$#', $path, $matches)) {
        $pageId = $matches[1];
        
        if ($pageId) {
            $page = Page::find($pageId);
            
            // If page exists and is Protected
            if ($page && $page->tags()->where('name', 'Protected')->exists()) {
                
                // Check if "AllowAPI" tag is present (Bypass PIN)
                if ($page->tags()->where('name', 'AllowAPI')->exists()) {
                    return; // Allow access
                }

                // Check for PIN in Header (X-PIN-Code) or Query Param (pin_code)
                $providedPin = $request->header('X-PIN-Code') ?? $request->query('pin_code');
                $realPin = getSecurePagePin($page);

                if (!$providedPin || (string)$providedPin !== (string)$realPin) {
                    // Fail immediately with 403 JSON
                    response()->json([
                        'error' => [
                            'code' => 403,
                            'message' => 'Access denied. Provide valid PIN or enable API access for this page.'
                        ]
                    ], 403)->send();
                    exit();
                }
            }
        }
    }

    // --- EXPORT PROTECTION (PDF, HTML, etc) ---
    if (strpos($path, '/export/') !== false) {
        $slug = $event->route->parameter('pageSlug');
        if ($slug) {
            $page = Page::where('slug', $slug)->first();
            if ($page && $page->tags()->where('name', 'Protected')->exists()) {
                if (!isPageUnlocked($page->id)) {
                    $currentExportUrl = $request->fullUrl();
                    $lockScreenUrl = $page->getUrl() . '?redirect_after_unlock=' . urlencode($currentExportUrl);
                    header("Location: " . $lockScreenUrl);
                    exit();
                }
            }
        }
    }
});

// --------------------------------------------------------------------------------
// BACKEND ROUTES (PIN Logic & Toggles)
// --------------------------------------------------------------------------------
if (!app()->routesAreCached()) {
    
    // Validate PIN
    Route::post('/secure-pin-check', function () {
        $input = request()->input('pin_code');
        $pageId = request()->input('page_id');
        $redirect = request()->input('redirect_to', '/');
        
        $targetPin = env('SECURE_PAGE_PIN');
        if ($pageId) {
            $page = Page::find($pageId);
            if ($page) {
                $targetPin = getSecurePagePin($page);
            }
        }

        if ($input && $targetPin && (string)$input === (string)$targetPin) {
            $unlockedPages = Session::get('secure_unlocked_pages', []);
            $unlockedPages[$pageId] = time() + 5; // Valid for 5 seconds (enough to load page)
            Session::put('secure_unlocked_pages', $unlockedPages);
            Session::save();
            return redirect($redirect);
        }
        return redirect($redirect)->with('pin_error', 'Invalid Access Code');
    })->middleware('web');

    // Enable Lock & Add Emoji
    Route::post('/secure-lock-page', function () {
        $pageId = request()->input('page_id');
        $customPass = request()->input('custom_password');
        $redirectUrl = request()->input('redirect_to', '/');
        $page = Page::find($pageId);

        if ($page && userCan('page-update', $page)) {
            if (!$page->tags()->where('name', 'Protected')->exists()) {
                $page->tags()->create(['name' => 'Protected', 'value' => $customPass]);
                
                if (strpos($page->name, '🔒') === false) {
                    $page->name = trim($page->name) . ' 🔒';
                    $page->save();
                }
                Session::flash('success', 'PIN protection enabled.');
            }
        }
        return redirect($redirectUrl);
    })->middleware('web');

    // 3. Disable Lock & Clean up Tags
    Route::post('/secure-unlock-page', function () {
        $pageId = request()->input('page_id');
        $redirectUrl = request()->input('redirect_to', '/');
        $page = Page::find($pageId);

        if ($page && userCan('page-update', $page)) {
            // Remove Protected Tag
            $page->tags()->where('name', 'Protected')->delete();
            
            // Remove AllowAPI Tag (Cleanup)
            $page->tags()->where('name', 'AllowAPI')->delete();

            // Remove Emoji
            if (strpos($page->name, '🔒') !== false) {
                $page->name = trim(str_replace('🔒', '', $page->name));
                $page->save();
            }
            Session::flash('success', 'PIN protection removed.');
        }
        return redirect($redirectUrl);
    })->middleware('web');

    // 4. Toggle API Access
    Route::post('/secure-toggle-api', function () {
        $pageId = request()->input('page_id');
        $redirectUrl = request()->input('redirect_to', '/');
        $page = Page::find($pageId);

        if ($page && userCan('page-update', $page)) {
            $apiTag = $page->tags()->where('name', 'AllowAPI')->first();
            
            if ($apiTag) {
                $apiTag->delete();
                Session::flash('success', 'API access disabled for this page.');
            } else {
                $page->tags()->create(['name' => 'AllowAPI', 'value' => 'true']);
                Session::flash('success', 'API access enabled for this page.');
            }
        }
        return redirect($redirectUrl);
    })->middleware('web');
}

// --------------------------------------------------------------------------------
// LOCK SCREEN HTML GENERATOR
// --------------------------------------------------------------------------------
if (!function_exists('renderSecureLockScreen')) {
    function renderSecureLockScreen($title = 'Protected Content', $pageId = null) {
        $errorHtml = Session::has('pin_error') ? 
            '<div class="text-neg bold mb-m" style="background: #ffebeb; border: 1px solid #cb2431; padding: 10px; border-radius: 4px;">' . Session::get('pin_error') . '</div>' : '';
        $pageIdInput = $pageId ? '<input type="hidden" name="page_id" value="' . $pageId . '">' : '';
        $targetRedirect = request()->get('redirect_after_unlock') ?: request()->fullUrl();

        return '
        <div class="flex-fill flex-container-column justify-center items-center" style="min-height: 60vh;">
            <div class="card content-wrap auto-height" style="max-width: 500px; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div class="text-center mb-l">
                    <div style="width: 80px; height: 80px; background-color: var(--color-primary); color: #FFF; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                         <svg fill="currentColor" width="40" height="40" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    </div>
                    <h1 class="list-heading text-xl mb-s">' . $title . '</h1>
                    <p class="text-muted">This page is password protected.</p>
                </div>
                ' . $errorHtml . '
                <form method="POST" action="/secure-pin-check" class="stretch-inputs">
                    <input type="hidden" name="_token" value="' . csrf_token() . '">
                    ' . $pageIdInput . '
                    <input type="hidden" name="redirect_to" value="' . htmlspecialchars($targetRedirect) . '">
                    <div class="form-group mb-l">
                        <label for="pin_code" class="text-muted mb-xs">Access Code</label>
                        <input type="password" id="pin_code" name="pin_code" placeholder="Enter PIN..." class="input-base" style="font-size: 1.1em; padding: 10px;" autofocus>
                    </div>
                    <div class="form-group text-right">
                         <a href="/" class="button outline mr-m">Cancel</a>
                        <button type="submit" class="button primary">Unlock Page</button>
                    </div>
                </form>
            </div>
        </div>
        <style>.tri-layout-right, .tri-layout-left, .toolbar { display: none !important; } #main-content { width: 100% !important; margin: 0 !important; max-width: 100% !important; }</style>';
    }
}

// --------------------------------------------------------------------------------
// PARENT DELETION BLOCKER (Safeguard)
// --------------------------------------------------------------------------------
// Prevents deleting shelves/books/chapters if they contain protected pages
View::composer(['shelves.delete', 'books.delete', 'chapters.delete'], function ($view) {
    $data = $view->getData();
    $entity = null;
    $protectedCount = 0;

    if (isset($data['shelf'])) {
        $entity = $data['shelf'];
        foreach ($entity->books as $book) {
            $protectedCount += $book->pages()->whereHas('tags', function($q){ $q->where('name', 'Protected'); })->count();
        }
    } elseif (isset($data['book'])) {
        $entity = $data['book'];
        $protectedCount = $entity->pages()->whereHas('tags', function($q){ $q->where('name', 'Protected'); })->count();
    } elseif (isset($data['chapter'])) {
        $entity = $data['chapter'];
        $protectedCount = $entity->pages()->whereHas('tags', function($q){ $q->where('name', 'Protected'); })->count();
    }

    if ($entity && $protectedCount > 0) {
        Session::flash('protected_deletion_blocked', $protectedCount);
        Session::save(); // Force save to ensure popup appears
        header("Location: " . $entity->getUrl());
        exit();
    }
});

// --------------------------------------------------------------------------------
// VIEW INTERCEPTORS (Display Logic)
// --------------------------------------------------------------------------------

// SHOW PAGE: Replace content with lock screen
View::composer(['pages.show'], function ($view) {
    $data = $view->getData();
    if (!isset($data['page'])) return;
    $page = $data['page'];
    
    if ($page->tags()->where('name', 'Protected')->exists() && !isPageUnlocked($page->id)) {
        $page->html = renderSecureLockScreen("Protected Content", $page->id);
    }
});

// PAGE ACTIONS: Redirect to lock screen if trying to edit/move/delete
View::composer(['pages.edit', 'pages.move', 'pages.revisions', 'pages.delete', 'pages.copy', 'form.entity-permissions'], function ($view) {
    $data = $view->getData();
    $page = $data['page'] ?? $data['model'] ?? null;

    if ($page instanceof \BookStack\Entities\Models\Page) {
         if ($page->tags()->where('name', 'Protected')->exists() && !isPageUnlocked($page->id)) {
             $currentActionUrl = request()->fullUrl();
             $redirectUrl = $page->getUrl() . '?redirect_after_unlock=' . urlencode($currentActionUrl);
             header("Location: " . $redirectUrl);
             exit();
         }
    }
});

// LIST SCRUBBER: Remove preview text from search results/lists
View::composer(['partials.entity-list-item', 'partials.page-list-item', 'partials.book-content-list-item'], function ($view) {
    $data = $view->getData();
    $entity = $data['entity'] ?? $data['page'] ?? null;

    if ($entity && isset($entity->tags)) {
        $hasProtectedTag = $entity->tags->contains(function($tag) {
            return strtolower($tag->name) === 'protected';
        });

        if ($hasProtectedTag) {
            $entity->text = '';
            $entity->html = '';
            $entity->preview_html = '';
        }

        // Hide the 'Protected' tag from the visual list
        $entity->tags = $entity->tags->filter(function($tag) {
            return strtolower($tag->name) !== 'protected';
        });
    }
});

// --------------------------------------------------------------------------------
// REGISTER ARTISAN COMMAND
// --------------------------------------------------------------------------------
if (app()->runningInConsole()) {
    $commandFile = __DIR__ . '/app/Console/Commands/ManageLocksCommand.php';
    if (file_exists($commandFile)) {
        require_once $commandFile;
        // Fix: Use Application::starting instead of Artisan::starting
        \Illuminate\Console\Application::starting(function ($artisan) {
            $artisan->resolveCommands([\BookStack\Console\Commands\ManageLocksCommand::class]);
        });
    }
}
