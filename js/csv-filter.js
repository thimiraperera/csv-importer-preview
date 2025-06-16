jQuery(function($) {
    $('.csv-display').each(function() {
        const $container = $(this);
        const $table = $container.find('.csv-table');
        const $rows = $table.find('tbody tr');
        const $filterInputs = $container.find('.csv-filter-input');
        const $resetBtn = $container.find('.csv-reset-filters');
        
        // Apply filters
        function applyFilters() {
            const filters = {};
            
            $filterInputs.each(function() {
                const colIndex = $(this).data('column');
                const value = $(this).val().trim().toLowerCase();
                
                if (value) {
                    filters[colIndex] = value;
                }
            });
            
            $rows.each(function() {
                const $row = $(this);
                let showRow = true;
                
                // Check against all filters
                for (const colIndex in filters) {
                    const $cell = $row.find('td[data-col-index="' + colIndex + '"]');
                    const cellValue = $cell.text().toLowerCase();
                    
                    if (!cellValue.includes(filters[colIndex])) {
                        showRow = false;
                        break;
                    }
                }
                
                $row.toggle(showRow);
            });
        }
        
        // Event listeners
        $filterInputs.on('keyup', applyFilters);
        
        $resetBtn.on('click', function() {
            $filterInputs.val('');
            $rows.show();
        });
    });
});