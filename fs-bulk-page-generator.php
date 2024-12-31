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

// Main plugin class
class FullScope_Bulk_Page_Generator
{
  private $plugin_path;
  private $placeholder_pattern = '/\{\{([^}]+)\}\}/';
  private const TEXT_DOMAIN = 'fs-bulk-page-generator';

  public function __construct()
  {
    $this->plugin_path = plugin_dir_path(__FILE__);
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_fs_bulk_page_generator_process_csv', array($this, 'process_csv'));
    add_action('wp_ajax_fs_bulk_page_generator_get_placeholders', array($this, 'get_template_placeholders'));
    add_action('wp_ajax_fs_bulk_page_generator_preview_mapping', array($this, 'preview_mapping'));
  }

  // Add admin menu
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
    <div class="wrap fs-bulk-page-generator">
      <h1><?php esc_html_e('Bulk Page Generator', 'fs-bulk-page-generator'); ?></h1>

      <div class="card">
        <div class="card inner">
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
      </div>

      <div class="card" style="margin-top: 20px; display: none;" id="mapping_section">
        <div class="card inner" style="overflow: hidden;">
          <h2><?php esc_html_e('Step 2: Map CSV Columns to Placeholders', 'fs-bulk-page-generator'); ?></h2>
          <p><?php esc_html_e('Upload your CSV file and map the columns to the placeholders found in the template.', 'fs-bulk-page-generator'); ?></p>
          <input type="file" id="csv_file" accept=".csv" />
          <div id="mapping_fields" style="margin-top: 20px;">
            <!-- Mapping fields will be populated here -->
          </div>
        </div>

        <div id="parent_page_section" style="margin-top: 20px;">

          <div class="card">
            <h4><?php esc_html_e('Parent Page Settings', 'fs-bulk-page-generator'); ?></h4>
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

          <div id="yoast_settings" class="card" style="margin-top: 20px;">
            <h4><?php esc_html_e('Yoast SEO Settings', 'fs-bulk-page-generator'); ?></h4>
            <p><?php esc_html_e('Import Meta Titles and Descriptions from CSV into Yoast SEO? You do not need placeholders for this.', 'fs-bulk-page-generator'); ?></p>
            <p class="small"><?php esc_html_e('Be sure to have the Yoast SEO plugin installed and activated. Your CSV columns must have the following names:', 'fs-bulk-page-generator'); ?></p>
            <ul>
              <li>meta_title</li>
              <li>meta_description</li>
            </ul>
            <div class="radio-group">
              <p><?php esc_html_e('Import Meta Titles from CSV?', 'fs-bulk-page-generator'); ?></p>
              <label>
                <input type="radio" name="import_meta_title" value="yes">
                <?php esc_html_e('Yes', 'fs-bulk-page-generator'); ?>
              </label>
              <label>
                <input type="radio" name="import_meta_title" value="no" checked>
                <?php esc_html_e('No', 'fs-bulk-page-generator'); ?>
              </label>
            </div>
            <div class="radio-group">
              <p><?php esc_html_e('Import Meta Descriptions from CSV?', 'fs-bulk-page-generator'); ?></p>
              <label>
                <input type="radio" name="import_meta_desc" value="yes">
                <?php esc_html_e('Yes', 'fs-bulk-page-generator'); ?>
              </label>
              <label>
                <input type="radio" name="import_meta_desc" value="no" checked>
                <?php esc_html_e('No', 'fs-bulk-page-generator'); ?>
              </label>
            </div>
          </div>

        </div>

        <div class="card" style="margin-top: 20px; display: none" id="preview_section">
          <h2><?php esc_html_e('Step 3: Preview and Generate', 'fs-bulk-page-generator'); ?></h2>
          <button class="button button-secondary" id="preview_mapping"><?php esc_html_e('Preview First Row', 'fs-bulk-page-generator'); ?></button>
          <div id="preview_content" style="margin-top: 20px;"></div>
          <button class="button button-primary" id="generate_pages" style="margin-top: 20px;"><?php esc_html_e('Generate All Pages', 'fs-bulk-page-generator'); ?></button>
          <div id="progress_area" style="display: none; margin-top: 20px;">
            <h3><?php esc_html_e('Generation Progress', 'fs-bulk-page-generator'); ?></h3>
            <div class="progress-bar-wrapper" style="border: 1px solid #ccc; padding: 1px;">
              <div class="progress-bar" style="background-color: #0073aa; height: 20px; width: 0%;"></div>
            </div>
            <div id="progress_text"></div>
          </div>
        </div>

      </div>
  <?php
  }

  // Get placeholders from the template page
  public function get_template_placeholders()
  {
    check_ajax_referer('fs_bulk_page_generator_nonce', 'nonce');

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $template_page = get_post($template_id);

    if (!$template_page) {
      wp_send_json_error('Template page not found');
      return;
    }

    // Get both the raw content and rendered content
    $raw_content = $template_page->post_content;
    $rendered_content = apply_filters('the_content', $raw_content);
    $title_content = $template_page->post_title; // Add title content

    // Search for placeholders in content and title
    $placeholders = array();

    // Search raw content
    preg_match_all($this->placeholder_pattern, $raw_content, $raw_matches);
    if (!empty($raw_matches[1])) {
      $placeholders = array_merge($placeholders, $raw_matches[1]);
    }

    // Search rendered content
    preg_match_all($this->placeholder_pattern, $rendered_content, $rendered_matches);
    if (!empty($rendered_matches[1])) {
      $placeholders = array_merge($placeholders, $rendered_matches[1]);
    }

    // Search title
    preg_match_all($this->placeholder_pattern, $title_content, $title_matches);
    if (!empty($title_matches[1])) {
      $placeholders = array_merge($placeholders, $title_matches[1]);
    }

    // Remove duplicates and clean up
    $placeholders = array_unique($placeholders);
    $placeholders = array_values(array_filter($placeholders));

    // Add debugging information
    $debug_info = array(
      'placeholders' => $placeholders,
      'debug' => array(
        'raw_content_length' => strlen($raw_content),
        'rendered_content_length' => strlen($rendered_content),
        'title_content' => $title_content,
        'placeholder_count' => count($placeholders)
      )
    );

    wp_send_json_success($debug_info);
  }

  // Preview the mapping of CSV rows to the template page
  public function preview_mapping()
  {
    check_ajax_referer('fs_bulk_page_generator_nonce', 'nonce');

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $mapping = isset($_POST['mapping']) ? array_map('sanitize_text_field', wp_unslash($_POST['mapping'])) : array();
    $sample_data = isset($_POST['sample_data']) ? array_map('sanitize_text_field', wp_unslash($_POST['sample_data'])) : array();
    $yoast_settings = isset($_POST['yoast_settings']) ? array_map('sanitize_text_field', wp_unslash($_POST['yoast_settings'])) : array();

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

    // Prepare Yoast preview data
    $yoast_preview = '';
    if (
      isset($yoast_settings['import_meta_title']) && $yoast_settings['import_meta_title'] === 'yes'
      || isset($yoast_settings['import_meta_desc']) && $yoast_settings['import_meta_desc'] === 'yes'
    ) {

      $yoast_preview .= '<div class="yoast-preview-section">';
      $yoast_preview .= '<h4>Yoast SEO Preview</h4>';

      if ($yoast_settings['import_meta_title'] === 'yes' && isset($sample_data['meta_title'])) {
        $yoast_preview .= '<div class="yoast-meta-field">';
        $yoast_preview .= '<strong>Meta Title:</strong> ';
        $yoast_preview .= esc_html($sample_data['meta_title']);
        $yoast_preview .= '</div>';
      }

      if ($yoast_settings['import_meta_desc'] === 'yes' && isset($sample_data['meta_description'])) {
        $yoast_preview .= '<div class="yoast-meta-field">';
        $yoast_preview .= '<strong>Meta Description:</strong> ';
        $yoast_preview .= esc_html($sample_data['meta_description']);
        $yoast_preview .= '</div>';
      }

      $yoast_preview .= '</div>';
    }

    wp_send_json_success(array(
      'preview' => $content,
      'yoast_preview' => $yoast_preview
    ));
  }

  // Process the CSV data and generate pages
  public function process_csv()
  {

    try {
      check_ajax_referer('fs_bulk_page_generator_nonce', 'nonce');

      if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
      }

      // Check required parameters
      $required_params = ['template_id', 'mapping', 'csv_data', 'slug_settings'];
      $missing_params = array();

      foreach ($required_params as $param) {
        if (!isset($_POST[$param])) {
          $missing_params[] = $param;
        }
      }

      if (!empty($missing_params)) {
        wp_send_json_error(array(
          'message' => 'Missing required parameters',
          'missing' => $missing_params
        ));
        return;
      }

      $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

      $mapping = isset($_POST['mapping']) ? array_map('sanitize_text_field', wp_unslash($_POST['mapping'])) : array();

      // Note: wp_kses_post is used instead of sanitize_text_field here to preserve necessary HTML formatting in the CSV data that will be used for page content. Sanitize_text_field would strip HTML tags and break content formatting.
      $csv_data = isset($_POST['csv_data']) ?
        array_map(
          function ($row) {
            return array_map('wp_kses_post', $row);
          },
          wp_unslash($_POST['csv_data'])
        ) :
        array();

      $slug_settings = isset($_POST['slug_settings']) ? array_map('sanitize_text_field', wp_unslash($_POST['slug_settings'])) : array();

      $this->fs_bulk_page_generator_log(['data' => $mapping], 'Mapping');
      $this->fs_bulk_page_generator_log(['data' => $csv_data], 'CSV Data');
      $this->fs_bulk_page_generator_log(['data' => $slug_settings], 'Slug Settings');

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
          $rendered_content = apply_filters('the_content', $content);

          foreach ($mapping as $placeholder => $column) {
            if (isset($row[$column])) {
              $content = str_replace(
                '{{' . $placeholder . '}}',
                $row[$column],
                $content
              );
            }
          }

          // Make sure we have a title
          $post_title = $template_page->post_title;
          // Replace any placeholders in the title
          foreach ($mapping as $placeholder => $column) {
            if (isset($row[$column])) {
              $post_title = str_replace(
                '{{' . $placeholder . '}}',
                $row[$column],
                $post_title
              );
            }
          }

          // Generate the initial slug
          $post_slug = isset($row[$slug_settings['column']]) ?
            sanitize_title($row[$slug_settings['column']]) :
            sanitize_title($post_title);

          // Debug logging
          $this->fs_bulk_page_generator_log("Original slug before insertion: " . $post_slug);

          // Check if a post with this slug exists (even though we're on a clean install)
          $existing_post = get_page_by_path($post_slug, OBJECT, 'page');
          $this->fs_bulk_page_generator_log("Existing post check result: " . ($existing_post ? "Found with ID: {$existing_post->ID}" : "Not found"));

          $post_data = array(
            'post_title'   => $post_title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_name'    => $post_slug,
            'post_parent'  => isset($slug_settings['parent_id']) ? intval($slug_settings['parent_id']) : 0
          );

          // Log the post data before insertion
          $this->fs_bulk_page_generator_log(
            ['attempting_insert' => $post_data],
            'Post Insert'
          );

          $post_id = wp_insert_post($post_data, true);

          // Debug: Check the actual slug after insertion
          if (!is_wp_error($post_id)) {
            // Get Yoast import settings from the request
            $import_meta_title = isset($_POST['yoast_settings']['import_meta_title'])
              ? $_POST['yoast_settings']['import_meta_title'] === 'yes'
              : false;

            $import_meta_desc = isset($_POST['yoast_settings']['import_meta_desc'])
              ? $_POST['yoast_settings']['import_meta_desc'] === 'yes'
              : false;

            // Only import meta title if enabled and exists in CSV
            if ($import_meta_title && isset($row['meta_title']) && !empty($row['meta_title'])) {
              update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($row['meta_title']));
            }

            // Only import meta description if enabled and exists in CSV
            if ($import_meta_desc && isset($row['meta_description']) && !empty($row['meta_description'])) {
              update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($row['meta_description']));
            }

            $created_post = get_post($post_id);
            $this->fs_bulk_page_generator_log("Final slug after insertion: " . $created_post->post_name);
            if ($created_post->post_name !== $post_slug) {
              $this->fs_bulk_page_generator_log("Slug was modified by WordPress from '{$post_slug}' to '{$created_post->post_name}'");
            }
            $results['success']++;
          } else {
            $this->fs_bulk_page_generator_log("Error creating post: " . $post_id->get_error_message());
            $results['failed']++;
            $results['errors'][] = $post_id->get_error_message();
          }
        } catch (Exception $e) {
          $this->fs_bulk_page_generator_log('Exception processing row: ' . $e->getMessage());
          $results['failed']++;
          $results['errors'][] = $e->getMessage();
        }
      }

      wp_send_json_success($results);
    } catch (Exception $e) {
      $this->fs_bulk_page_generator_log('Major exception in process_csv: ' . $e->getMessage());
      wp_send_json_error('Processing error: ' . $e->getMessage());
    }
  }

  // Enqueue admin scripts
  public function enqueue_admin_scripts($hook)
  {
    if ($hook != 'toplevel_page_fs-bulk-page-generator') {
      return;
    }

    // Enqueue styles
    wp_enqueue_style(
      'fs-bulk-page-generator-styles',
      plugins_url('fs-bpg-styles.css', __FILE__),
      array(),
      '1.0'
    );

    // Enqueue scripts
    wp_enqueue_script(
      'fs-bulk-page-generator',
      plugins_url('fs-bpg-scripts.js', __FILE__),
      array('jquery'),
      '1.0',
      true
    );

    wp_localize_script('fs-bulk-page-generator', 'fsBulkPageGenerator', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('fs_bulk_page_generator_nonce'),
      'strings' => array(
        'confirmGenerate' => __('Are you sure you want to generate pages for all CSV rows?', 'fs-bulk-page-generator'),
        'selectTemplate' => __('Please select a template page first', 'fs-bulk-page-generator'),
        'uploadCsv' => __('Please upload a CSV file first', 'fs-bulk-page-generator'),
        'complete' => __('All pages have been generated!', 'fs-bulk-page-generator'),
      )
    ));
  }

  // Log messages to the error log
  public function fs_bulk_page_generator_log($message, $type = 'debug')
  {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
      return;
    }

    $plugin_name = 'FS Bulk Page Generator';
    $datetime    = gmdate('Y-m-d H:i:s');

    // Handle non-string messages
    if (!is_string($message)) {
      $message = wp_json_encode($message, JSON_PRETTY_PRINT);
    }

    $log_message = sprintf(
      '[%s][%s][%s] %s',
      $plugin_name,
      $datetime,
      strtoupper($type),
      $message
    );

    error_log($log_message);
  }
}

// Initialize the plugin
new FullScope_Bulk_Page_Generator();
