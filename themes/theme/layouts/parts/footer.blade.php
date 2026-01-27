<div class="print-hidden">
    <footer class="px-xl py-m mt-xl border-top text-muted text-small">
        <div class="container">
            <p>&copy; BookStack</p>
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
                    // A. Hide the tag itself
                    tag.style.display = 'none';
                    tag.dataset.protectedHidden = "true";

                    // B. Hide the search snippet container
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

        // 1. Run immediately
        hideProtectedTags();

        // 2. Run on standard load
        document.addEventListener("DOMContentLoaded", hideProtectedTags);

        // 3. Watchdog for dynamic content (search results appearing later)
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