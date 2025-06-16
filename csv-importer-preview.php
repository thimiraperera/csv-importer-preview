<?php
/*
Plugin Name: Admin CSV Upload & Frontend Display
Description: Upload CSV from admin area and display on front-end with pagination
Version: 1.2
Author: Your Name
*/

class CSV_Upload_Display {

    private $css_enqueued = false;

    public function __construct() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_init', array($this, 'handle_csv_upload'));
        
        // Frontend hooks
        add_shortcode('display_csv', array($this, 'frontend_display'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    // Enqueue CSS only when shortcode is used
    public function enqueue_styles() {
        if ($this->css_enqueued) {
            wp_enqueue_style(
                'csv-importer-preview-css', 
                plugin_dir_url(__FILE__) . 'css/csv-importer-preview.css'
            );
        }
    }

    // Add admin menu page
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

    // Admin page content
    public function admin_page_content() {
        // Use transient for admin messages instead of session
        $message = get_transient('csv_upload_message');
        $msg_type = get_transient('csv_upload_msg_type');
        ?>
        <div class="wrap">
            <h1>Upload CSV File</h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($msg_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php 
                // Clear the transient
                delete_transient('csv_upload_message');
                delete_transient('csv_upload_msg_type');
                ?>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('csv_upload_nonce', 'csv_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="csv_file">CSV File</label></th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv" required>
                            <p class="description">Upload a CSV file to display on front-end</p>
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
        </div>
        <?php
    }

    // Handle CSV upload
    public function handle_csv_upload() {
        if (!isset($_POST['csv_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['csv_nonce'], 'csv_upload_nonce') || 
            !current_user_can('manage_options')) {
            return;
        }
        
        if (empty($_FILES['csv_file']['tmp_name'])) {
            set_transient('csv_upload_message', 'Please select a CSV file to upload', 30);
            set_transient('csv_upload_msg_type', 'error', 30);
            return;
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $use_headers = isset($_POST['use_headers']) ? (bool)$_POST['use_headers'] : true;
        
        $csv_data = $this->parse_csv($file);
        
        if (empty($csv_data)) {
            set_transient('csv_upload_message', 'Failed to parse CSV file', 30);
            set_transient('csv_upload_msg_type', 'error', 30);
            return;
        }
        
        // Store in options table
        update_option('csv_upload_data', array(
            'headers' => $use_headers ? array_shift($csv_data) : array(),
            'rows' => $csv_data,
            'timestamp' => time()
        ));
        
        set_transient('csv_upload_message', 'CSV file uploaded successfully!', 30);
        set_transient('csv_upload_msg_type', 'success', 30);
    }

    // Parse CSV file
    private function parse_csv($file) {
        $data = array();
        $handle = fopen($file, 'r');
        
        if ($handle !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        
        return $data;
    }

    // Frontend display with shortcode
    public function frontend_display($atts) {
        $this->css_enqueued = true;
        $csv_data = get_option('csv_upload_data');
        
        if (!$csv_data || empty($csv_data['rows'])) {
            return '<p>No CSV data available</p>';
        }
        
        $per_page = 10;
        // Get current page from URL parameter
        $current_page = isset($_GET['csv_page']) ? (int)$_GET['csv_page'] : 1;
        if ($current_page < 1) {
            $current_page = 1;
        }
        
        $total_rows = count($csv_data['rows']);
        $total_pages = ceil($total_rows / $per_page);
        
        // Adjust current page if it exceeds total pages
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        
        // Paginate data
        $offset = ($current_page - 1) * $per_page;
        $rows = array_slice($csv_data['rows'], $offset, $per_page);
        
        // Get current URL without existing pagination parameters
        $current_url = home_url($_SERVER['REQUEST_URI']);
        $current_url = remove_query_arg('csv_page', $current_url);
        
        ob_start();
        ?>
        <div class="csv-display">
            <?php if (!empty($csv_data['headers'])) : ?>
                <table class="csv-table">
                    <thead>
                        <tr>
                            <?php foreach ($csv_data['headers'] as $header) : ?>
                                <th><?php echo esc_html($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <?php foreach ($row as $cell) : ?>
                                    <td><?php echo esc_html($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <table class="csv-table">
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <?php foreach ($row as $cell) : ?>
                                    <td><?php echo esc_html($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="csv-pagination">
                <?php 
                echo paginate_links(array(
                    'base' => esc_url(add_query_arg('csv_page', '%#%', $current_url)),
                    'format' => '',
                    'prev_text' => __('« Previous'),
                    'next_text' => __('Next »'),
                    'total' => $total_pages,
                    'current' => $current_page,
                    'mid_size' => 2
                ));
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize plugin
function csv_upload_display_init() {
    new CSV_Upload_Display();
}
add_action('plugins_loaded', 'csv_upload_display_init');