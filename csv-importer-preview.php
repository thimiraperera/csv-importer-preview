<?php
/*
Plugin Name: Admin CSV Upload & Frontend Display
Description: Upload CSV from admin area and display on front-end with filtering
Version: 2.1
Author: Your Name
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
            '1.0',
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
        $csv_data = get_option('csv_upload_data');
        ?>
        <div class="wrap">
            <h1>Upload CSV File</h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($msg_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php 
                delete_transient('csv_upload_message');
                delete_transient('csv_upload_msg_type');
                ?>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('csv_upload_nonce', 'csv_nonce'); ?>
                <input type="hidden" name="action" value="csv_upload">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="csv_file">CSV File</label></th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv,text/csv" required>
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
                <?php submit_button('Upload CSV'); ?>
            </form>
            
            <?php if ($csv_data && !empty($csv_data['rows'])) : 
                $num_columns = count($csv_data['rows'][0]);
                $headers = $csv_data['headers'] ?? array_fill(0, $num_columns, '');
                $selected_columns = get_option('csv_selected_columns', array());
                $filterable_columns = get_option('csv_filterable_columns', array());
            ?>
                <hr>
                <h2>Column Filter Configuration</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('csv_filter_nonce', 'csv_filter_nonce'); ?>
                    <input type="hidden" name="action" value="save_csv_filters">
                    
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
                                            <input type="checkbox" name="filterable_columns[]" 
                                                value="<?php echo $i; ?>" 
                                                <?php checked(in_array($i, $filterable_columns)); ?>>
                                            Make filterable
                                        </label>
                                    </div>
                                <?php endfor; ?>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Column Settings'); ?>
                </form>
                
                <div class="card" style="margin-top:20px;">
                    <h2>Current Data Information</h2>
                    <p><strong>Last uploaded:</strong> 
                        <?php 
                        if (isset($csv_data['timestamp'])) {
                            $date = date_i18n(get_option('date_format'), $csv_data['timestamp']);
                            $time = date_i18n(get_option('time_format'), $csv_data['timestamp']);
                            echo esc_html($date . ' ' . $time);
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </p>
                    <p><strong>Rows:</strong> <?php echo number_format(count($csv_data['rows'])); ?></p>
                    <p><strong>Columns:</strong> <?php echo $num_columns; ?></p>
                </div>
            <?php endif; ?>
        </div>
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
        
        if (empty($_FILES['csv_file']['tmp_name'])) {
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
        
        update_option('csv_upload_data', array(
            'headers' => $use_headers ? array_shift($csv_data) : array(),
            'rows' => $csv_data,
            'timestamp' => time()
        ));
        
        // Clear column selections when new CSV is uploaded
        delete_option('csv_selected_columns');
        delete_option('csv_filterable_columns');
        
        $this->set_message('CSV file uploaded successfully!', 'success');
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
        
        $csv_data = get_option('csv_upload_data');
        
        if (!$csv_data || empty($csv_data['rows'])) {
            return '<div class="csv-no-data">No CSV data available</div>';
        }
        
        $selected_columns = get_option('csv_selected_columns', array());
        $filterable_columns = get_option('csv_filterable_columns', array());
        $headers = $csv_data['headers'] ?? array();
        $num_columns = count($csv_data['rows'][0]);
        
        // If no columns selected, show all
        if (empty($selected_columns)) {
            $selected_columns = range(0, $num_columns - 1);
        }
        
        $per_page = 10;
        $current_page = isset($_GET['csv_page']) ? max(1, (int)$_GET['csv_page']) : 1;
        
        $total_rows = count($csv_data['rows']);
        $total_pages = ceil($total_rows / $per_page);
        $current_page = min($current_page, $total_pages);
        $offset = ($current_page - 1) * $per_page;
        $rows = array_slice($csv_data['rows'], $offset, $per_page);
        
        $current_url = remove_query_arg('csv_page');
        
        ob_start();
        ?>
        <div class="csv-display">
            <div class="csv-filters">
                <?php foreach ($selected_columns as $col_index) : 
                    if (in_array($col_index, $filterable_columns)) :
                        $header = $headers[$col_index] ?? 'Column ' . ($col_index + 1);
                ?>
                    <div class="csv-filter-group">
                        <label><?php echo esc_html($header); ?></label>
                        <input type="text" class="csv-filter-input" 
                            data-column="<?php echo $col_index; ?>" 
                            placeholder="Filter <?php echo esc_attr($header); ?>">
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
            
            <?php if ($total_pages > 1) : ?>
                <div class="csv-pagination">
                    <?php 
                    echo paginate_links(array(
                        'base' => esc_url(add_query_arg('csv_page', '%#%', $current_url)),
                        'format' => '',
                        'prev_text' => __('« Previous'),
                        'next_text' => __('Next »'),
                        'total' => $total_pages,
                        'current' => $current_page,
                        'mid_size' => 2,
                        'type' => 'list'
                    ));
                    ?>
                </div>
            <?php endif; ?>
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
    
    $selected_columns = isset($_POST['selected_columns']) ? array_map('intval', $_POST['selected_columns']) : [];
    $filterable_columns = isset($_POST['filterable_columns']) ? array_map('intval', $_POST['filterable_columns']) : [];
    
    update_option('csv_selected_columns', $selected_columns);
    update_option('csv_filterable_columns', $filterable_columns);
    
    set_transient('csv_upload_message', 'Column settings saved successfully!', 30);
    set_transient('csv_upload_msg_type', 'success', 30);
    
    wp_safe_redirect(admin_url('admin.php?page=csv-upload'));
    exit;
}
add_action('admin_post_save_csv_filters', 'handle_save_csv_filters');

function csv_upload_display_init() {
    new CSV_Upload_Display();
}
add_action('plugins_loaded', 'csv_upload_display_init');