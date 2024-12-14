=== FullScope Bulk Page Generator ===
Contributors: derekjubach, FullScope
Author: FullScope
Tags: pages, generator, csv, import, bulk pages, location pages
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Generate multiple WordPress pages from a CSV file using an existing page as a template.

== Description ==

Location Pages Generator allows you to quickly create multiple WordPress pages using an existing page as a template. Perfect for creating location pages, product pages, or any other content that follows a consistent structure.

= Features =
* Use any existing WordPress page as a template
* Import data from CSV files
* Support for custom URL structures through parent pages
* Yoast SEO meta description integration
* Preview before generation
* Batch processing for large datasets
* Progress tracking during page creation

= How it Works =
1. Create a template page with placeholders like {{location_name}}, {{address}}, etc.
2. Prepare your CSV file with columns matching your placeholders
3. Select your template page and map CSV columns to placeholders
4. Choose a parent page (optional)
5. Generate all pages automatically

= CSV Requirements =
Your CSV file should include columns for:
* All placeholders used in your template
* URL slug (optional)
* meta_description (optional, for Yoast SEO)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/location-pages-generator` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Location Generator in the admin menu
4. Follow the step-by-step process to generate your pages

== Frequently Asked Questions ==

= What format should my CSV file be in? =
Your CSV file should be comma-delimited with headers in the first row. The headers should match the placeholders in your template.

= Can I preview the pages before generating them? =
Yes, you can preview how the first row of your CSV data will look when applied to your template.

= Does this work with Yoast SEO? =
Yes, if your CSV includes a column named 'meta_description', it will automatically be used for the Yoast SEO meta description.

= Can I organize pages into a hierarchy? =
Yes, you can select a parent page, and all generated pages will be created as child pages under it.

= What happens if something goes wrong during generation? =
The plugin processes pages in batches and provides detailed error reporting if any issues occur.

== Screenshots ==

1. Main interface showing template selection
2. CSV mapping interface
3. Preview and generation screen

== Changelog ==

= 1.0 =
* Initial release

== Upgrade Notice ==

= 1.0 =
Initial release of Location Pages Generator.