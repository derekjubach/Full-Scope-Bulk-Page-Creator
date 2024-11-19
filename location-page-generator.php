<?php
/*
Plugin Name: Bulk Page Generator
Description: Generates location pages from CSV data using existing pages as templates
Version: 1.0
Author: FullScope
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
  exit;
}

class LocationPagesGenerator
{
  private $plugin_path;
  private $placeholder_pattern = '/\{\{([^}]+)\}\}/';

  public function __construct()
  {
    $this->plugin_path = plugin_dir_path(__FILE__);
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_process_csv', array($this, 'process_csv'));
    add_action('wp_ajax_get_template_placeholders', array($this, 'get_template_placeholders'));
    add_action('wp_ajax_preview_mapping', array($this, 'preview_mapping'));
  }

  public function add_admin_menu()
  {
    add_menu_page(
      'Location Pages Generator',
      'Location Generator',
      'manage_options',
      'location-generator',
      array($this, 'admin_page'),
      'dashicons-admin-site',
      30
    );
  }

  public function admin_page()
  {
    // Get all pages for template selection
    $pages = get_pages(array(
      'sort_column' => 'post_title',
      'sort_order' => 'ASC'
    ));
?>
    <div class="wrap">
      <h1>Location Pages Generator</h1>

      <div class="card">
        <h2>Step 1: Select Template Page</h2>
        <p>Choose an existing page to use as your template. The page should include placeholders in the format {{placeholder_name}}.</p>
        <select id="template_page" style="width: 300px;">
          <option value="">Select a page...</option>
          <?php foreach ($pages as $page): ?>
            <option value="<?php echo esc_attr($page->ID); ?>">
              <?php echo esc_html($page->post_title); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="button button-secondary" id="scan_placeholders">Scan for Placeholders</button>
      </div>

      <div class="card" style="margin-top: 20px; display: none;" id="mapping_section">
        <h2>Step 2: Map CSV Columns to Placeholders</h2>
        <p>Upload your CSV file and map the columns to the placeholders found in the template.</p>
        <input type="file" id="csv_file" accept=".csv" />
        <div id="mapping_fields" style="margin-top: 20px;">
          <!-- Mapping fields will be populated here -->
        </div>

        <div id="parent_page_section" style="margin-top: 20px;">
          <h3>Parent Page Settings</h3>
          <p>Select a parent page for all generated location pages:</p>
          <select id="parent_page" style="width: 300px;">
            <option value="0">No parent (top level)</option>
            <?php
            foreach ($pages as $page):
            ?>
              <option value="<?php echo esc_attr($page->ID); ?>">
                <?php echo esc_html($page->post_title); ?>
              </option>
            <?php
            endforeach;
            ?>
          </select>
          <p>URL slug will be taken from the following CSV column:</p>
          <select id="slug_column">
            <!-- Options will be populated from CSV headers -->
          </select>
        </div>
      </div>

      <div class="card" style="margin-top: 20px; display: none;" id="preview_section">
        <h2>Step 3: Preview and Generate</h2>
        <button class="button button-secondary" id="preview_mapping">Preview First Row</button>
        <div id="preview_content" style="margin-top: 20px;"></div>
        <button class="button button-primary" id="generate_pages" style="margin-top: 20px;">Generate All Pages</button>
      </div>

      <div id="progress_area" style="display: none; margin-top: 20px;">
        <h3>Generation Progress</h3>
        <div class="progress-bar-wrapper" style="border: 1px solid #ccc; padding: 1px;">
          <div class="progress-bar" style="background-color: #0073aa; height: 20px; width: 0%;"></div>
        </div>
        <div id="progress_text"></div>
      </div>
    </div>
<?php
  }

  public function get_template_placeholders()
  {
    check_ajax_referer('location_generator_nonce', 'nonce');

    $template_id = intval($_POST['template_id']);
    $template_page = get_post($template_id);

    if (!$template_page) {
      wp_send_json_error('Template page not found');
      return;
    }

    $content = $template_page->post_content;
    preg_match_all($this->placeholder_pattern, $content, $matches);

    $placeholders = array_unique($matches[1]);
    wp_send_json_success(array('placeholders' => $placeholders));
  }

  public function preview_mapping()
  {
    check_ajax_referer('location_generator_nonce', 'nonce');

    $template_id = intval($_POST['template_id']);
    $mapping = $_POST['mapping'];
    $sample_data = $_POST['sample_data'];

    $template_page = get_post($template_id);
    if (!$template_page) {
      wp_send_json_error('Template page not found');
      return;
    }

    $content = $template_page->post_content;
    foreach ($mapping as $placeholder => $column) {
      if (isset($sample_data[$column])) {
        $content = str_replace(
          '{{' . $placeholder . '}}',
          $sample_data[$column],
          $content
        );
      }
    }

    wp_send_json_success(array('preview' => $content));
  }

  public function process_csv()
  {
    check_ajax_referer('location_generator_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
      return;
    }

    $template_id = intval($_POST['template_id']);
    $mapping = $_POST['mapping'];
    $csv_data = $_POST['csv_data'];
    $slug_settings = $_POST['slug_settings'];

    $template_page = get_post($template_id);
    if (!$template_page) {
      wp_send_json_error('Template page not found');
      return;
    }

    $results = array(
      'success' => 0,
      'failed' => 0,
      'errors' => array()
    );

    foreach ($csv_data as $row) {
      $content = $template_page->post_content;
      foreach ($mapping as $placeholder => $column) {
        if (isset($row[$column])) {
          $content = str_replace(
            '{{' . $placeholder . '}}',
            $row[$column],
            $content
          );
        }
      }

      $post_data = array(
        'post_title' => $row[$mapping['title']] ?? 'New Location',
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_name' => sanitize_title($row[$slug_settings['column']]),
        'post_parent' => intval($slug_settings['parent_id'])
      );

      $post_id = wp_insert_post($post_data, true);

      if (!is_wp_error($post_id)) {
        // Set Yoast meta description if the column exists in CSV
        if (isset($row['meta_description']) && !empty($row['meta_description'])) {
          update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($row['meta_description']));
        }
        $results['success']++;
      } else {
        $results['failed']++;
        $results['errors'][] = $post_id->get_error_message();
      }
    }

    wp_send_json_success($results);
  }

  public function enqueue_admin_scripts($hook)
  {
    if ($hook != 'toplevel_page_location-generator') {
      return;
    }

    wp_enqueue_script(
      'location-generator-admin',
      plugins_url('admin.js', __FILE__),
      array('jquery'),
      '1.0',
      true
    );

    wp_localize_script('location-generator-admin', 'locationGenerator', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('location_generator_nonce')
    ));
  }
}

// Initialize the plugin
new LocationPagesGenerator();
