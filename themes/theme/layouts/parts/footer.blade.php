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

                    // Hide the search snippet container
                    const resultItem = tag.closest('.entity-list-item');
                    if (resultItem) {
                        const snippet = resultItem.querySelector('.entity-item-snippet');
                        if (snippet) {
                            snippet.style.display = 'none';
                        }
                    }
                }
                
                // Mark this tag as checked
                tag.dataset.protectedChecked = "true";
            });
        }

        // Run immediately
        hideProtectedTags();

        //  Run on standard load
        document.addEventListener("DOMContentLoaded", hideProtectedTags);

        // Watchdog for dynamic content (search results appearing later)
        const observer = new MutationObserver(function(mutations) {
            hideProtectedTags();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    })();
    </script>
</div>

<div class="print-hidden">
    <footer class="px-xl py-m mt-xl border-top text-muted text-small">
        <div class="container">
        </div>
    </footer>

    <script nonce="{{ $cspNonce }}">
    // Simple visual cleanup to hide the "Protected" tag pill if it slips through
    document.addEventListener("DOMContentLoaded", function() {
        const tags = document.querySelectorAll('.tag-item');
        tags.forEach(tag => {
            if (tag.innerText.trim() === 'Protected') {
                tag.style.display = 'none';
            }
        });
    });
    document.addEventListener("DOMContentLoaded", function() {
        // The trigger text/symbol to look for in the Title
        // You can change this to "Protected" if you prefer text over the emoji
        const trigger = "🔒"; 

        // Select all page list items
        const items = document.querySelectorAll('.entity-list-item');

        items.forEach(item => {
            // Find the title element inside the item
            const titleElement = item.querySelector('.entity-list-item-name');
            
            if (titleElement) {
                // Check if the title contains our trigger symbol/word
                if (titleElement.textContent.includes(trigger)) {
                    
                    // 1. Hide the explicit description text
                    const desc = item.querySelector('.entity-list-item-desc');
                    if (desc) desc.style.display = 'none';

                    // 2. Hide the auto-generated grey preview text
                    const snippet = item.querySelector('.text-muted'); 
                    if (snippet) snippet.style.display = 'none';
                }
            }
        });
    });
    </script>
</div>
