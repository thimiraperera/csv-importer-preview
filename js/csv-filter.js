jQuery(function($) {
    $('.csv-display').each(function() {
        const $container = $(this);
        const $table = $container.find('.csv-table');
        const $rows = $table.find('tbody tr');
        const $filterSelects = $container.find('.csv-filter-select');
        const $resetBtn = $container.find('.csv-reset-filters');
        
        // Apply filters
        function applyFilters() {
            const filters = {};
            
            $filterSelects.each(function() {
                const colIndex = $(this).data('column');
                const value = $(this).val().trim();
                
                if (value) {
                    filters[colIndex] = value.toLowerCase();
                }
            });
            
            $rows.each(function() {
                const $row = $(this);
                let showRow = true;
                
                // Check against all filters
                for (const colIndex in filters) {
                    const $cell = $row.find('td[data-col-index="' + colIndex + '"]');
                    const cellValue = $cell.text().toLowerCase().trim();
                    
                    // Check for exact match
                    if (cellValue !== filters[colIndex]) {
                        showRow = false;
                        break;
                    }
                }
                
                $row.toggle(showRow);
            });
        }
        
        // Event listeners
        $filterSelects.on('change', applyFilters);
        
        $resetBtn.on('click', function() {
            $filterSelects.val('');
            applyFilters();
        });
        
        // Apply filters on initial load if any are selected
        applyFilters();
    });
});