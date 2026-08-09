/**
 * Component: Search List Filter
 * Filters visible checkbox items based on search input value with debounce.
 */
export const initSearchList = (container) => {
    const searchInput = container.querySelector('.ppfx-search-box-input');
    const itemsList   = container.querySelector('.ppfx-searchable-items');
    
    if (!searchInput || !itemsList) return;

    const items = itemsList.querySelectorAll('.ppfx-item');
    let searchTimeout;

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();

        // High-performance debounce: delay filtering by 200ms
        clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(() => {
            items.forEach(item => {
                const name = item.dataset.termName || '';
                item.classList.toggle('ppfx-hidden', !name.includes(query));
            });
        }, 200);
    });
};
