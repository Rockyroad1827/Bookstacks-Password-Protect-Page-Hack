<div class="print-hidden">
    <footer class="px-xl py-m mt-xl border-top text-muted text-small">
        <div class="container">
        </div>
    </footer>

    <script nonce="{{ $cspNonce }}">
    (function() {
        console.log("Authorized Footer Script Running");

        function hideProtectedTags() {
            const tags = document.querySelectorAll('.tag-item');
            
            tags.forEach(tag => {
                // Check if we have already processed this tag to save performance
                if (tag.dataset.protectedChecked) return;

                const tagText = tag.innerText.trim();

                if (tagText.startsWith('Protected')) {
                    // Hide the tag itself
                    tag.style.display = 'none';
                    tag.dataset.protectedHidden = "true";
                }
                
                // Mark this tag as checked
                tag.dataset.protectedChecked = "true";
            });
        }

        function hideProtectedContent() {
            // The trigger text/symbol to look for in the Title
            const trigger = "🔒"; 

            // Select all page list items (Search results, Lists)
            const items = document.querySelectorAll('.entity-list-item');

            items.forEach(item => {
                // Find the title element inside the item
                const titleElement = item.querySelector('.entity-list-item-name');
                
                if (titleElement && titleElement.textContent.includes(trigger)) {
                    
                    // Hide the explicit description text (if present)
                    const desc = item.querySelector('.entity-list-item-desc');
                    if (desc) desc.style.display = 'none';

                    // Hide the Snippet/Preview ONLY (Preserving the path/breadcrumbs)
                    // We target the specific snippet class instead of generic '.text-muted'
                    const snippet = item.querySelector('.entity-list-item-snippet'); 
                    if (snippet) {
                        snippet.style.display = 'none';
                    } else {
                        // Fallback for older BookStack versions: 
                        // Only hide .text-muted if it is NOT the path
                        const mutedItems = item.querySelectorAll('.text-muted');
                        mutedItems.forEach(el => {
                            // The path usually contains links, the snippet is usually plain text
                            if (el.querySelector('a') === null) { 
                                el.style.display = 'none';
                            }
                        });
                    }
                }
            });
        }

        // Run immediately
        hideProtectedTags();
        hideProtectedContent();

        // Run on standard load
        document.addEventListener("DOMContentLoaded", function() {
            hideProtectedTags();
            hideProtectedContent();
        });

        // Watchdog for dynamic content (search results appearing later)
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
