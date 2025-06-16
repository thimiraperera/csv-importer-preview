<?php
/*
Plugin Name: Admin CSV Upload & Frontend Display
Description: Upload multiple CSV files and display them with unique shortcodes
Version: 3.0
Author: Thimira Perera
*/

class CSV_Upload_Display {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_init', array($this, 'handle_csv_upload'));
        add_shortcode('display_csv', array($this, 'frontend_display'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        wp_register_style(
            'csv-importer-preview-css', 
            plugin_dir_url(__FILE__) . 'css/csv-importer-preview.css'
        );
        
        wp_register_script(
            'csv-filter-js',
            plugin_dir_url(__FILE__) . 'js/csv-filter.js',
            array('jquery'),
            '1.1',
            true
        );
    }

    public function add_admin_page() {
        add_menu_page(
            'CSV Upload',
            'CSV Upload',
            'manage_options',
            'csv-upload',
            array($this, 'admin_page_content'),
            'dashicons-media-spreadsheet',
            30
        );
    }

    public function admin_page_content() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $message = get_transient('csv_upload_message');
        $msg_type = get_transient('csv_upload_msg_type');
        $csv_instances = get_option('csv_upload_instances', array());
        
        // Get current instance ID from URL if exists
        $current_instance = isset($_GET['instance']) ? sanitize_key($_GET['instance']) : '';
        $csv_data = $current_instance && isset($csv_instances[$current_instance]) ? $csv_instances[$current_instance] : null;
        
        // Get options for current instance
        $selected_columns = $current_instance ? get_option('csv_selected_columns_' . $current_instance, array()) : array();
        $filterable_columns = $current_instance ? get_option('csv_filterable_columns_' . $current_instance, array()) : array();
        $default_filter_columns = $current_instance ? get_option('csv_default_filter_columns_' . $current_instance, array()) : array();
        ?>
        <div class="wrap">
            <h1>CSV Upload Manager</h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($msg_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php 
                delete_transient('csv_upload_message');
                delete_transient('csv_upload_msg_type');
                ?>
            <?php endif; ?>
            
            <?php if (!empty($csv_instances)) : ?>
                <div class="card" style="margin-bottom:30px; max-width: unset;">
                    <h2>Your CSV Shortcodes</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Shortcode</th>
                                <th>Instance ID</th>
                                <th>Filename</th>
                                <th>Rows</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csv_instances as $id => $instance) : 
                                $date = date_i18n(get_option('date_format'), $instance['timestamp']);
                                $time = date_i18n(get_option('time_format'), $instance['timestamp']);
                            ?>
                                <tr>
                                    <td><code>[display_csv id="<?php echo esc_attr($id); ?>"]</code></td>
                                    <td><?php echo esc_html($id); ?></td>
                                    <td><?php echo esc_html($instance['filename']); ?></td>
                                    <td><?php echo number_format(count($instance['rows'])); ?></td>
                                    <td><?php echo esc_html("$date $time"); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=csv-upload&instance=' . $id)); ?>">
                                            Configure
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <h2><?php echo $current_instance ? 'Configure ' . esc_html($current_instance) : 'Upload New CSV'; ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('csv_upload_nonce', 'csv_nonce'); ?>
                <input type="hidden" name="action" value="csv_upload">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="instance_id">Instance ID</label></th>
                        <td>
                            <input type="text" name="instance_id" id="instance_id" 
                                value="<?php echo $current_instance ? esc_attr($current_instance) : ''; ?>"
                                <?php echo $current_instance ? 'readonly' : 'required'; ?>>
                            <p class="description">Unique identifier (e.g., products, locations). Letters, numbers and underscores only.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="csv_file">CSV File</label></th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv,text/csv" <?php echo $current_instance ? '' : 'required'; ?>>
                            <p class="description">Upload a CSV file (max 2MB)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Header Row</th>
                        <td>
                            <label>
                                <input type="checkbox" name="use_headers" value="1" checked>
                                First row contains headers
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button($current_instance ? 'Update CSV' : 'Upload CSV'); ?>
            </form>
            
            <?php if ($csv_data && !empty($csv_data['rows'])) : 
                $num_columns = count($csv_data['rows'][0]);
                $headers = $csv_data['headers'] ?? array_fill(0, $num_columns, '');
            ?>
                <hr>
                <h2>Column Filter Configuration</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('csv_filter_nonce', 'csv_filter_nonce'); ?>
                    <input type="hidden" name="action" value="save_csv_filters">
                    <input type="hidden" name="instance_id" value="<?php echo esc_attr($current_instance); ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th>Columns to Display</th>
                            <td>
                                <?php for ($i = 0; $i < $num_columns; $i++) : 
                                    $header = !empty($headers[$i]) ? $headers[$i] : 'Column ' . ($i + 1);
                                    $col_id = 'col_' . $i;
                                ?>
                                    <div>
                                        <label>
                                            <input type="checkbox" name="selected_columns[]" 
                                                value="<?php echo $i; ?>" 
                                                <?php checked(in_array($i, $selected_columns)); ?>>
                                            <?php echo esc_html($header); ?>
                                        </label>
                                        
                                        <label style="margin-left: 15px;">
                                            <input type="checkbox" class="filterable-toggle" 
                                                name="filterable_columns[]" 
                                                value="<?php echo $i; ?>" 
                                                <?php checked(in_array($i, $filterable_columns)); ?>>
                                            Make filterable
                                        </label>
                                        
                                        <label style="margin-left: 15px;" class="default-filter-container">
                                            <input type="checkbox" name="default_filter_columns[]" 
                                                value="<?php echo $i; ?>" 
                                                <?php checked(in_array($i, $default_filter_columns)); ?>
                                                <?php if (!in_array($i, $filterable_columns)) echo 'disabled'; ?>>
                                            Apply first item by default
                                        </label>
                                    </div>
                                <?php endfor; ?>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Column Settings'); ?>
                </form>
                
                <div class="card" style="margin-top:20px; max-width: unset;">
                    <h2>Current Data Information</h2>
                    <p><strong>Instance ID:</strong> <?php echo esc_html($current_instance); ?></p>
                    <p><strong>Filename:</strong> <?php echo esc_html($csv_data['filename']); ?></p>
                    <p><strong>Last updated:</strong> 
                        <?php 
                        $date = date_i18n(get_option('date_format'), $csv_data['timestamp']);
                        $time = date_i18n(get_option('time_format'), $csv_data['timestamp']);
                        echo esc_html($date . ' ' . $time); 
                        ?>
                    </p>
                    <p><strong>Rows:</strong> <?php echo number_format(count($csv_data['rows'])); ?></p>
                    <p><strong>Columns:</strong> <?php echo $num_columns; ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(function($) {
            // Function to toggle default filter checkbox
            function toggleDefaultFilter() {
                $('.filterable-toggle').each(function() {
                    var $filterable = $(this);
                    var $defaultContainer = $filterable.closest('div').find('.default-filter-container');
                    var $defaultCheckbox = $defaultContainer.find('input');
                    
                    if ($filterable.is(':checked')) {
                        $defaultContainer.show();
                        $defaultCheckbox.prop('disabled', false);
                    } else {
                        $defaultContainer.hide();
                        $defaultCheckbox.prop('checked', false);
                    }
                });
            }
            
            // Initial state
            toggleDefaultFilter();
            
            // On change
            $('.filterable-toggle').change(toggleDefaultFilter);
        });
        </script>
        <?php
    }

    public function handle_csv_upload() {
        if (!isset($_POST['csv_nonce']) || !wp_verify_nonce($_POST['csv_nonce'], 'csv_upload_nonce')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            $this->set_message('Permission denied', 'error');
            return;
        }
        
        $instance_id = isset($_POST['instance_id']) ? sanitize_key($_POST['instance_id']) : '';
        if (empty($instance_id)) {
            $this->set_message('Instance ID is required', 'error');
            return;
        }
        
        if (empty($_FILES['csv_file']['tmp_name'])) {
            // Allow form submission without file for configuration
            if (isset($_GET['instance']) && $_GET['instance'] === $instance_id) {
                return;
            }
            $this->set_message('Please select a CSV file to upload', 'error');
            return;
        }
        
        $file = $_FILES['csv_file'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if ($file['size'] > $max_size) {
            $this->set_message('File size exceeds 2MB limit', 'error');
            return;
        }
        
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($file_ext) !== 'csv') {
            $this->set_message('Invalid file type. Only CSV files are allowed.', 'error');
            return;
        }
        
        $use_headers = isset($_POST['use_headers']) ? (bool)$_POST['use_headers'] : true;
        
        $csv_data = $this->parse_csv($file['tmp_name']);
        
        if (empty($csv_data)) {
            $this->set_message('Failed to parse CSV file', 'error');
            return;
        }
        
        $instances = get_option('csv_upload_instances', array());
        
        $instances[$instance_id] = array(
            'headers' => $use_headers ? array_shift($csv_data) : array(),
            'rows' => $csv_data,
            'timestamp' => time(),
            'filename' => sanitize_file_name($file['name'])
        );
        
        update_option('csv_upload_instances', $instances);
        
        // Clear column selections when new CSV is uploaded
        $this->clear_instance_settings($instance_id);
        
        $this->set_message('CSV file uploaded successfully!', 'success');
    }
    
    private function clear_instance_settings($instance_id) {
        delete_option('csv_selected_columns_' . $instance_id);
        delete_option('csv_filterable_columns_' . $instance_id);
        delete_option('csv_default_filter_columns_' . $instance_id);
    }
    
    private function set_message($message, $type = 'success') {
        set_transient('csv_upload_message', $message, 30);
        set_transient('csv_upload_msg_type', $type, 30);
    }

    private function parse_csv($file) {
        $data = array();
        $handle = fopen($file, 'r');
        
        if ($handle === false) {
            return false;
        }
        
        // Check for BOM (UTF-8 BOM is "\xEF\xBB\xBF")
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        while (($row = fgetcsv($handle)) !== false) {
            // Skip completely empty rows
            if (count(array_filter($row, 'strlen')) > 0) {
                $data[] = $row;
            }
        }
        fclose($handle);
        
        return $data;
    }

    public function frontend_display($atts) {
        wp_enqueue_style('csv-importer-preview-css');
        wp_enqueue_script('csv-filter-js');
        
        $atts = shortcode_atts(array(
            'id' => 'default'
        ), $atts);
        
        $instance_id = sanitize_key($atts['id']);
        $instances = get_option('csv_upload_instances', array());
        
        if (!isset($instances[$instance_id])) {
            return '<div class="csv-no-data">CSV data not found for this instance</div>';
        }
        
        $csv_data = $instances[$instance_id];
        
        $selected_columns = get_option('csv_selected_columns_' . $instance_id, array());
        $filterable_columns = get_option('csv_filterable_columns_' . $instance_id, array());
        $default_filter_columns = get_option('csv_default_filter_columns_' . $instance_id, array());
        $headers = $csv_data['headers'] ?? array();
        $num_columns = count($csv_data['rows'][0]);
        
        // If no columns selected, show all
        if (empty($selected_columns)) {
            $selected_columns = range(0, $num_columns - 1);
        }
        
        // Precompute unique values for filterable columns
        $unique_values = array();
        if (!empty($filterable_columns)) {
            foreach ($filterable_columns as $col_index) {
                $unique_values[$col_index] = array();
            }
            
            foreach ($csv_data['rows'] as $row) {
                foreach ($filterable_columns as $col_index) {
                    if (isset($row[$col_index])) {
                        $value = $row[$col_index];
                        if ($value !== '') {
                            // Use the value as key to avoid duplicates
                            $unique_values[$col_index][$value] = $value;
                        }
                    }
                }
            }
            
            // Sort and convert to indexed array
            foreach ($filterable_columns as $col_index) {
                if (isset($unique_values[$col_index])) {
                    $values = array_values($unique_values[$col_index]);
                    usort($values, function($a, $b) {
                        return strcasecmp($a, $b);
                    });
                    $unique_values[$col_index] = $values;
                }
            }
        }
        
        $rows = $csv_data['rows'];
        
        ob_start();
        ?>
        <div class="csv-display">
            <div class="csv-filters">
                <?php foreach ($selected_columns as $col_index) : 
                    if (in_array($col_index, $filterable_columns)) :
                        $header = $headers[$col_index] ?? 'Column ' . ($col_index + 1);
                        $is_default = in_array($col_index, $default_filter_columns);
                        $first_value = isset($unique_values[$col_index][0]) ? $unique_values[$col_index][0] : '';
                ?>
                    <div class="csv-filter-group">
                        <label><?php echo esc_html($header); ?></label>
                        <select class="csv-filter-select" data-column="<?php echo $col_index; ?>">
                            <option value="">All</option>
                            <?php if (isset($unique_values[$col_index])) : ?>
                                <?php foreach ($unique_values[$col_index] as $value) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"
                                        <?php if ($is_default && $value === $first_value) echo 'selected="selected"'; ?>>
                                        <?php echo esc_html($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; endforeach; ?>
                <button type="button" class="csv-reset-filters">Reset Filters</button>
            </div>
            
            <table class="csv-table">
                <?php if (!empty($headers)) : ?>
                    <thead>
                        <tr>
                            <?php foreach ($selected_columns as $col_index) : 
                                $header = $headers[$col_index] ?? 'Column ' . ($col_index + 1);
                            ?>
                                <th><?php echo esc_html($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                <?php endif; ?>
                
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <?php foreach ($selected_columns as $col_index) : ?>
                                <td data-col-index="<?php echo $col_index; ?>">
                                    <?php echo isset($row[$col_index]) ? esc_html($row[$col_index]) : ''; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Handle filter saving
function handle_save_csv_filters() {
    if (!isset($_POST['csv_filter_nonce']) || 
        !wp_verify_nonce($_POST['csv_filter_nonce'], 'csv_filter_nonce') || 
        !current_user_can('manage_options')) {
        wp_die('Invalid request');
    }
    
    $instance_id = isset($_POST['instance_id']) ? sanitize_key($_POST['instance_id']) : '';
    if (empty($instance_id)) {
        wp_die('Instance ID is required');
    }
    
    $selected_columns = isset($_POST['selected_columns']) ? array_map('intval', $_POST['selected_columns']) : [];
    $filterable_columns = isset($_POST['filterable_columns']) ? array_map('intval', $_POST['filterable_columns']) : [];
    $default_filter_columns = isset($_POST['default_filter_columns']) ? array_map('intval', $_POST['default_filter_columns']) : [];
    
    update_option('csv_selected_columns_' . $instance_id, $selected_columns);
    update_option('csv_filterable_columns_' . $instance_id, $filterable_columns);
    update_option('csv_default_filter_columns_' . $instance_id, $default_filter_columns);
    
    set_transient('csv_upload_message', 'Column settings saved successfully!', 30);
    set_transient('csv_upload_msg_type', 'success', 30);
    
    wp_safe_redirect(admin_url('admin.php?page=csv-upload&instance=' . $instance_id));
    exit;
}
add_action('admin_post_save_csv_filters', 'handle_save_csv_filters');

function csv_upload_display_init() {
    new CSV_Upload_Display();
}
add_action('plugins_loaded', 'csv_upload_display_init');