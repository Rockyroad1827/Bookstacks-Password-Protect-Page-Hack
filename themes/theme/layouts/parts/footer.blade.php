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
                <button type="button" id="close-lock-modal" class="button primary">Understood</button>
            </div>
        </div>
    </div>
@endif

<div class="print-hidden">
    <footer class="px-xl py-m mt-xl border-top text-muted text-small">
        <div class="container">
        </div>
    </footer>

    <style>
        /* Base Lock Icon Styling (Positioning) */
        .secure-lock-icon {
            margin-left: auto;      
            align-self: center;     
            color: #d9534f;         
            opacity: 0.8;
            padding-left: 10px;
            display: flex;          
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        /* DEFAULT SIZE (Small - for Navigation/Sidebar) */
        .secure-lock-icon svg {
            width: 16px;
            height: 16px;
            transition: all 0.2s ease;
        }

        /* MAIN MENU SIZE (Bigger - inside the Main Content area) */
        #main-content .secure-lock-icon svg, 
        .page-content .secure-lock-icon svg {
            width: 24px;  /* <--- BIGGER IN MAIN MENU */
            height: 24px;
        }

        /* Force Flexbox on list items */
        .entity-list-item, .book-content-item, .chapter-list-item, .entity-list-item-component {
            display: flex !important;
            align-items: center !important; 
            flex-wrap: nowrap !important;   
            width: 100%;
        }
    </style>

    <script nonce="{{ $cspNonce }}">
    (function() {
        console.log("Authorized Footer Script Running");

        // --- MODAL CLOSING LOGIC ---
        const closeBtn = document.getElementById('close-lock-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                const modal = document.getElementById('lock-block-modal');
                if (modal) modal.remove();
            });
        }

        // --- HIDE CONTENT & ADD ICON LOGIC ---
        function handleProtectedContent() {
            const selectors = [
                '.entity-list-item', 
                '.book-content-item', 
                '.page-list-item', 
                '.chapter-list-item',
                '.entity-list-item-component'
            ];
            
            const items = document.querySelectorAll(selectors.join(', '));
            const invisibleMarker = '\u200B'; 
            
            // SVG Icon HTML (No hardcoded size, controlled by CSS)
            const lockIconHtml = `
                <div class="secure-lock-icon" title="PIN Protected">
                    <svg fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                </div>`;

            items.forEach(item => {
                let isLocked = false;

                // Is the Invisible Marker in the Title?
                const titleEl = item.querySelector('h4, .entity-list-item-name, a.text-book, a.text-page, a.text-chapter');
                if (titleEl && titleEl.innerText.includes(invisibleMarker)) {
                    isLocked = true;
                }

                // Is the "Protected" tag present?
                if (!isLocked) {
                    const tags = item.querySelectorAll('.tag-item');
                    tags.forEach(tag => {
                        if (tag.innerText.trim() === 'Protected') isLocked = true;
                    });
                }

                if (isLocked) {
                    // Hide Content
                    const targets = item.querySelectorAll(
                        '.entity-list-item-desc, .entity-list-item-snippet, .book-content-item-snippet, .text-muted, p.text-muted, .entity-list-item-text'
                    );
                    targets.forEach(el => {
                        if (el.querySelector('a') === null && !el.classList.contains('tags')) { 
                            el.style.display = 'none';
                        }
                    });

                    // Add Lock Icon (APPEND TO END)
                    if (!item.querySelector('.secure-lock-icon')) {
                        item.insertAdjacentHTML('beforeend', lockIconHtml);
                    }
                }
            });
            
            // Visually hide "Protected" tags
            document.querySelectorAll('.tag-item').forEach(tag => {
                if(tag.innerText.trim() === 'Protected') tag.style.display = 'none';
            });
        }

        // --- EXECUTION ---
        document.addEventListener("DOMContentLoaded", handleProtectedContent);
        handleProtectedContent();

        const observer = new MutationObserver(function(mutations) {
            handleProtectedContent();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    })();
    </script>
</div>
