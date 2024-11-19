<?php

/**
 * Plugin Name: FullScope Bulk Page Generator 
 * Description: Bulk Generate pages from a CSV file 
 * Version: 1.0
 * Author: Derek Jubach
 * Author URI:  https://github.com/derekjubach/Full-Scope-Bulk-Page-Creator
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
  exit;
}

class FSBulkPageGenerator
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
      'FullScope Bulk Page Generator',
      'FullScope Bulk Page Generator',
      'manage_options',
      'fs-bulk-page-generator',
      array($this, 'admin_page'),
      'dashicons-text-page',
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
    <style>
      #preview_section img {
        max-width: 100%;
      }
    </style>
    <div class="wrap">
      <h1><?php esc_html_e('Bulk Page Generator', 'fs-bulk-page-generator'); ?></h1>

      <div class="card">
        <h2><?php esc_html_e('Step 1: Select Template Page', 'fs-bulk-page-generator'); ?></h2>
        <p><?php esc_html_e('Choose an existing page to use as your template. The page should include placeholders in the format {{placeholder_name}}.', 'fs-bulk-page-generator'); ?></p>
        <select id="template_page" style="width: 300px;">
          <option value=""><?php esc_html_e('Select a page...', 'fs-bulk-page-generator'); ?></option>
          <?php foreach ($pages as $page): ?>
            <option value="<?php echo esc_attr($page->ID); ?>">
              <?php echo esc_html($page->post_title); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="button button-secondary" id="scan_placeholders"><?php esc_html_e('Scan for Placeholders', 'fs-bulk-page-generator'); ?></button>
      </div>

      <div class="card" style="margin-top: 20px; display: none;" id="mapping_section">
        <h2><?php esc_html_e('Step 2: Map CSV Columns to Placeholders', 'fs-bulk-page-generator'); ?></h2>
        <p><?php esc_html_e('Upload your CSV file and map the columns to the placeholders found in the template.', 'fs-bulk-page-generator'); ?></p>
        <input type="file" id="csv_file" accept=".csv" />
        <div id="mapping_fields" style="margin-top: 20px;">
          <!-- Mapping fields will be populated here -->
        </div>

        <div id="parent_page_section" style="margin-top: 20px;">
          <h3><?php esc_html_e('Parent Page Settings', 'fs-bulk-page-generator'); ?></h3>
          <p><?php esc_html_e('Select a parent page for all generated location pages:', 'fs-bulk-page-generator'); ?></p>
          <select id="parent_page" style="width: 300px;">
            <option value="0"><?php esc_html_e('No parent (top level)', 'fs-bulk-page-generator'); ?></option>
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
          <p><?php esc_html_e('URL slug will be taken from the following CSV column:', 'fs-bulk-page-generator'); ?></p>
          <select id="slug_column">
            <!-- Options will be populated from CSV headers -->
          </select>
        </div>
      </div>

      <div class="card" style="margin-top: 20px; display: none" id="preview_section">
        <h2><?php esc_html_e('Step 3: Preview and Generate', 'fs-bulk-page-generator'); ?></h2>
        <button class="button button-secondary" id="preview_mapping"><?php esc_html_e('Preview First Row', 'fs-bulk-page-generator'); ?></button>
        <div id="preview_content" style="margin-top: 20px;"></div>
        <button class="button button-primary" id="generate_pages" style="margin-top: 20px;"><?php esc_html_e('Generate All Pages', 'fs-bulk-page-generator'); ?></button>
      </div>

      <div id="progress_area" style="display: none; margin-top: 20px;">
        <h3><?php esc_html_e('Generation Progress', 'fs-bulk-page-generator'); ?></h3>
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
    check_ajax_referer('fs_bulk_page_generator_nonce', 'nonce');

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
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
    check_ajax_referer('fs_bulk_page_generator_nonce', 'nonce');

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $mapping = isset($_POST['mapping']) ? array_map('sanitize_text_field', wp_unslash($_POST['mapping'])) : array();
    $sample_data = isset($_POST['sample_data']) ? array_map('sanitize_text_field', wp_unslash($_POST['sample_data'])) : array();

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
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    try {
      check_ajax_referer('fs_bulk_page_generator_nonce', 'nonce');

      if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
      }

      // Add error checking for required parameters
      if (!isset($_POST['template_id']) || !isset($_POST['mapping']) || !isset($_POST['csv_data']) || !isset($_POST['slug_settings'])) {
        wp_send_json_error('Missing required parameters: ' . print_r($_POST, true));
        return;
      }

      $template_id = intval($_POST['template_id']);

      // Sanitize the arrays directly without trying to decode JSON
      $mapping = isset($_POST['mapping']) ? wp_unslash($_POST['mapping']) : array();
      $csv_data = isset($_POST['csv_data']) ? wp_unslash($_POST['csv_data']) : array();
      $slug_settings = isset($_POST['slug_settings']) ? wp_unslash($_POST['slug_settings']) : array();

      // Log the data
      error_log('Mapping: ' . print_r($mapping, true));
      error_log('CSV data: ' . print_r($csv_data, true));
      error_log('Slug settings: ' . print_r($slug_settings, true));

      // Validate data
      if (empty($mapping) || empty($csv_data) || empty($slug_settings)) {
        wp_send_json_error('Invalid data format: Empty required data');
        return;
      }

      $template_page = get_post($template_id);
      if (!$template_page) {
        wp_send_json_error('Template page not found: ' . $template_id);
        return;
      }

      $results = array(
        'success' => 0,
        'failed' => 0,
        'errors' => array()
      );

      foreach ($csv_data as $row) {
        try {
          $content = $template_page->post_content;
          foreach ($mapping as $placeholder => $column) {
            if (isset($row[$column])) {
              $content = str_replace(
                '{{' . $placeholder . '}}',
                wp_kses_post($row[$column]),
                $content
              );
            }
          }

          // Make sure we have a title
          $post_title = isset($row[$mapping['title']]) ? sanitize_text_field($row[$mapping['title']]) : 'New Location';

          // Make sure we have a valid slug
          $post_slug = isset($row[$slug_settings['column']]) ?
            sanitize_title($row[$slug_settings['column']]) :
            sanitize_title($post_title);

          $post_data = array(
            'post_title'   => $post_title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => $post_slug,
            'post_parent'  => isset($slug_settings['parent_id']) ? intval($slug_settings['parent_id']) : 0
          );

          // Log the post data before insertion
          error_log('Attempting to insert post with data: ' . print_r($post_data, true));

          $post_id = wp_insert_post($post_data, true);

          if (!is_wp_error($post_id)) {
            if (isset($row['meta_description']) && !empty($row['meta_description'])) {
              update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($row['meta_description']));
            }
            $results['success']++;
          } else {
            $results['failed']++;
            $results['errors'][] = $post_id->get_error_message();
            error_log('Failed to insert post: ' . $post_id->get_error_message());
          }
        } catch (Exception $e) {
          error_log('Exception processing row: ' . $e->getMessage());
          $results['failed']++;
          $results['errors'][] = $e->getMessage();
        }
      }

      wp_send_json_success($results);
    } catch (Exception $e) {
      error_log('Major exception in process_csv: ' . $e->getMessage());
      wp_send_json_error('Processing error: ' . $e->getMessage());
    }
  }

  public function enqueue_admin_scripts($hook)
  {
    if ($hook != 'toplevel_page_fs-bulk-page-generator') {
      return;
    }

    wp_enqueue_script(
      'fs-bulk-page-generator',
      plugins_url('admin.js', __FILE__),
      array('jquery'),
      '1.0',
      true
    );

    wp_localize_script('fs-bulk-page-generator', 'fsBulkPageGenerator', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('fs_bulk_page_generator_nonce')
    ));
  }
}

// Initialize the plugin
new FSBulkPageGenerator();
