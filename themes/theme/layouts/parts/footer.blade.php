{{-- DELETION BLOCKED MODAL --}}
@if(session()->has('protected_deletion_blocked'))
    <div id="lock-block-modal" style="display: flex; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(2px);">
        <div class="card p-xl" style="max-width: 450px; width: 90%; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div class="text-center">
                <div class="text-neg mb-l" style="display: inline-flex; padding: 16px; background-color: #fff2f2; border-radius: 50%;">
                    <svg fill="currentColor" width="48" height="48" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                </div>
                <h3 class="list-heading text-xl bold mb-s">Action Blocked</h3>
                <p class="text-muted mb-l" style="font-size: 1.1em; line-height: 1.5;">
                    This item contains <strong>{{ session('protected_deletion_blocked') }}</strong> PIN-protected page(s).<br>
                    <span class="small">You must unlock or move these pages before this item can be deleted.</span>
                </p>
                {{-- REMOVED inline onclick, ADDED ID --}}
                <button type="button" id="close-lock-modal" class="button primary">Understood</button>
            </div>
        </div>
    </div>
@endif

<div class="print-hidden">
    <footer class="px-xl py-m mt-xl border-top text-muted text-small">
        <div class="container">
            <p>&copy; BookStack</p>
        </div>
    </footer>

    <script nonce="{{ $cspNonce }}">
    (function() {
        console.log("Authorized Footer Script Running");

        // SETUP MODAL CLOSING LOGIC
        const closeBtn = document.getElementById('close-lock-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                const modal = document.getElementById('lock-block-modal');
                if (modal) modal.remove();
            });
        }

        // HIDE TAGS LOGIC
        function hideProtectedTags() {
            const tags = document.querySelectorAll('.tag-item');
            
            tags.forEach(tag => {
                if (tag.dataset.protectedChecked) return;

                const tagText = tag.innerText.trim();
                if (tagText.startsWith('Protected')) {
                    tag.style.display = 'none';
                    tag.dataset.protectedHidden = "true";
                }
                tag.dataset.protectedChecked = "true";
            });
        }

        // HIDE CONTENT LOGIC
        function hideProtectedContent() {
            const trigger = "🔒"; 
            const items = document.querySelectorAll('.entity-list-item');

            items.forEach(item => {
                const titleElement = item.querySelector('.entity-list-item-name');
                
                if (titleElement && titleElement.textContent.includes(trigger)) {
                    
                    const desc = item.querySelector('.entity-list-item-desc');
                    if (desc) desc.style.display = 'none';

                    const snippet = item.querySelector('.entity-list-item-snippet'); 
                    if (snippet) {
                        snippet.style.display = 'none';
                    } else {
                        const mutedItems = item.querySelectorAll('.text-muted');
                        mutedItems.forEach(el => {
                            if (el.querySelector('a') === null) { 
                                el.style.display = 'none';
                            }
                        });
                    }
                }
            });
        }

        // EXECUTION
        hideProtectedTags();
        hideProtectedContent();

        document.addEventListener("DOMContentLoaded", function() {
            hideProtectedTags();
            hideProtectedContent();
        });

        const observer = new MutationObserver(function(mutations) {
            hideProtectedTags();
            hideProtectedContent();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    })();
    </script>
</div>
