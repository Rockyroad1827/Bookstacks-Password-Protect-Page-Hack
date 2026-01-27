<?php
// --------------------------------------------------------------------------------
//RAW SERVER INTERCEPT
// --------------------------------------------------------------------------------
// Immediately blocks any search request for "Protected"
// If found, redirects to home to prevent leaking hidden pages.
if (isset($_SERVER['REQUEST_URI'])) {
    $rawUri = $_SERVER['REQUEST_URI'];
    $decodedUri = rawurldecode($rawUri);
    if (strpos($rawUri, '/search') !== false && stripos($decodedUri, 'Protected') !== false) {
        header("Location: /");
        exit();
    }
}

use BookStack\Entities\Models\Page;
use BookStack\Facades\Theme;
use BookStack\Facades\ThemeEvents;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;


// --------------------------------------------------------------------------------
// BACKEND ROUTES
// --------------------------------------------------------------------------------
if (!app()->routesAreCached()) {
    // Check PIN
    Route::post('/secure-pin-check', function () {
        $input = request()->input('pin_code');
        $pageId = request()->input('page_id');
        $redirect = request()->input('redirect_to', '/');
        
        $masterPin = env('SECURE_PAGE_PIN');
        $targetPin = $masterPin; 
        
        // If checking a specific page, look for custom password tag
        if ($pageId) {
            $page = Page::find($pageId);
            if ($page) {
                // We use the relation directly to bypass any view filters
                $tag = $page->tags()->where('name', 'Protected')->first();
                if ($tag && !empty($tag->value)) {
                    $targetPin = $tag->value;
                }
            }
        }

        if ($input && $targetPin && (string)$input === (string)$targetPin) {
            Session::put('secure_access_expiry', time() + 5); 
            Session::save();
            return redirect($redirect);
        }
        return redirect($redirect)->with('pin_error', 'Invalid Access Code');
    })->middleware('web');

    // Enable Lock
    Route::post('/secure-lock-page', function () {
        $pageId = request()->input('page_id');
        $customPass = request()->input('custom_password');
        $redirectUrl = request()->input('redirect_to', '/');
        $page = Page::find($pageId);
        if ($page && userCan('page-update', $page)) {
            if (!$page->tags()->where('name', 'Protected')->exists()) {
                $page->tags()->create(['name' => 'Protected', 'value' => $customPass]);
                Session::flash('success', 'PIN protection enabled.');
            }
        }
        return redirect($redirectUrl);
    })->middleware('web');

    // Disable Lock
    Route::post('/secure-unlock-page', function () {
        $pageId = request()->input('page_id');
        $redirectUrl = request()->input('redirect_to', '/');
        $page = Page::find($pageId);
        if ($page && userCan('page-update', $page)) {
            $tag = $page->tags()->where('name', 'Protected')->first();
            if ($tag) {
                $tag->delete();
                Session::flash('success', 'PIN protection removed.');
            }
        }
        return redirect($redirectUrl);
    })->middleware('web');
}

// --------------------------------------------------------------------------------
// LOCK SCREEN GENERATOR
// --------------------------------------------------------------------------------
if (!function_exists('renderSecureLockScreen')) {
    function renderSecureLockScreen($title = 'Protected Content', $pageId = null) {
        $errorHtml = Session::has('pin_error') ? 
            '<div class="text-neg bold mb-m" style="background: #ffebeb; border: 1px solid #cb2431; padding: 10px; border-radius: 4px;">' . Session::get('pin_error') . '</div>' : '';
        
        $pageIdInput = $pageId ? '<input type="hidden" name="page_id" value="' . $pageId . '">' : '';

        return '
        <div class="flex-fill flex-container-column justify-center items-center" style="min-height: 60vh;">
            <div class="card content-wrap auto-height" style="max-width: 500px; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div class="text-center mb-l">
                    <div style="width: 80px; height: 80px; background-color: var(--color-primary); color: #FFF; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <svg fill="currentColor" width="40" height="40" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    </div>
                    <h1 class="list-heading text-xl mb-s">' . $title . '</h1>
                    <p class="text-muted">This page is password protected. Please enter the access code below.</p>
                </div>
                ' . $errorHtml . '
                <form method="POST" action="/secure-pin-check" class="stretch-inputs">
                    <input type="hidden" name="_token" value="' . csrf_token() . '">
                    ' . $pageIdInput . '
                    <input type="hidden" name="redirect_to" value="' . request()->fullUrl() . '">
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
// INTERCEPTOR: SINGLE PAGE VIEW (Render Lock Screen)
// --------------------------------------------------------------------------------
View::composer(['pages.show', 'pages.edit'], function ($view) {
    $data = $view->getData();
    if (!isset($data['page'])) return;
    $page = $data['page'];
    
    // Check Status
    $isProtected = $page->tags()->where('name', 'Protected')->exists();
    $isUnlocked = (Session::get('secure_access_expiry', 0) > time());

    if ($isProtected && !$isUnlocked) {
        // If trying to Edit while locked, bounce back to view (which then shows lock screen)
        if ($view->getName() === 'pages.edit') { 
            header("Location: " . $page->getUrl()); 
            exit(); 
        }
        $page->html = renderSecureLockScreen("Protected Content", $page->id);
    }
});

// --------------------------------------------------------------------------------
// SERVER-SIDE TAG FILTER (Hides it from READ View & SEARCH Results only)
// --------------------------------------------------------------------------------
// We target 'pages.show' (Read Mode) and 'partials.entity-list-item' (Search Results/Lists).
// We do NOT target 'pages.edit', so the tag remains visible in the editor.
View::composer(['pages.show', 'partials.entity-list-item'], function ($view) {
    $data = $view->getData();
    $entity = $data['page'] ?? $data['entity'] ?? null;
    if ($entity && isset($entity->tags)) {
        // Filter the tags collection in memory
        $entity->tags = $entity->tags->filter(function($tag) {
            return strtolower($tag->name) !== 'protected';
        });
    }
});
