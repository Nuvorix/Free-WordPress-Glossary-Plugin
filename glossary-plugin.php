<?php
/*
Plugin Name: Glossary Plugin
Description: A custom glossary plugin with modal tooltip functionality, archive support, and demand-driven caching.
Version: 1.2
Author: ChatGPT & Nuvorix.com
Changes:
- Removed the old hover-style tooltip for better mobile usability. The tooltip system now has a centered tooltip box.
- Added functionality to exclude specific text from glossary replacement using the `[gloss_ign]...[/gloss_ign]` shortcode.
- Glossary terms will no longer replace text within `<pre>` or `<code>` tags.
- Added a centered tooltip box that displays the 300-character text from the "Add New Glossary" editor. The background dims, and you can exit the tooltip by:
  1. Pressing the "X"
  2. Clicking on the background
  3. Pressing "Escape" on your keyboard.
- Implemented on-demand caching for glossary terms when viewed for the first time.
- Glossary Cache Log displays the newest entries at the top.
- Glossary Cache and Glossary Cache Log pages have buttons at the top.
- Optimized caching to use WordPress's native caching more effectively.
- Reduced redundant caching actions.
- Caching occurs only if a glossary term appears on a page and isn't already cached.
- All essential functionality, changelog entries, and admin pages retained as requested.
- Limited the Glossary Cache Log to a maximum of 1000 entries. The oldest entries will be removed automatically when new ones are added beyond this limit.
- Updated the script to include numbers in glossary terms (e.g., "RJ45") by modifying the `str_word_count` function.
- Excluded the home page from processing glossary terms by adding a condition in the `glossary_tooltip_filter` function.
- Removed logging of "Cache hit" messages to declutter the cache log.
*/

if (!defined('ABSPATH')) {
    exit;
}

// Register Custom Post Type
function create_glossary_post_type() {
    $labels = array(
        'name'               => _x('Glossary', 'Post Type General Name', 'text_domain'),
        'singular_name'      => _x('Term', 'Post Type Singular Name', 'text_domain'),
        'menu_name'          => __('Glossary', 'text_domain'),
        'add_new'            => __('Add New Glossary', 'text_domain'),
        'add_new_item'       => __('Add New Glossary Term', 'text_domain'),
        'edit_item'          => __('Edit Glossary Term', 'text_domain'),
        'view_item'          => __('View Glossary Term', 'text_domain'),
        'all_items'          => __('All Glossaries', 'text_domain'),
        'search_items'       => __('Search Glossary Terms', 'text_domain'),
        'not_found'          => __('No Glossary Terms found.', 'text_domain'),
    );

    $args = array(
        'label'                 => __('Glossary', 'text_domain'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor'),
        'public'                => true,
        'show_ui'               => true,
        'has_archive'           => true,
        'rewrite'               => array('slug' => 'glossary'),
    );
    register_post_type('glossary', $args);
}
add_action('init', 'create_glossary_post_type');

// Enqueue CSS and JavaScript
function glossary_enqueue_assets() {
    wp_enqueue_style('glossary-tooltips', plugin_dir_url(__FILE__) . 'css/glossary-tooltip.css');
    wp_enqueue_script('glossary-tooltips-js', plugin_dir_url(__FILE__) . 'js/glossary-tooltip.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'glossary_enqueue_assets');

// Meta Boxes for Tooltip and Abbreviation Full Form
function glossary_add_meta_box() {
    add_meta_box('glossary_tooltip_text', __('Tooltip Text (300 characters)', 'text_domain'), 'glossary_meta_box_callback', 'glossary', 'normal', 'high');
    add_meta_box('glossary_abbreviation_full_form', __('Abbreviation Full Form', 'text_domain'), 'glossary_abbreviation_meta_box_callback', 'glossary', 'normal', 'high');
}
add_action('add_meta_boxes', 'glossary_add_meta_box');

function glossary_meta_box_callback($post) {
    wp_nonce_field('save_tooltip_text', 'glossary_tooltip_nonce');
    $value = get_post_meta($post->ID, '_tooltip_text', true);
    echo '<textarea style="width:100%;height:100px;" id="glossary_tooltip_text" name="glossary_tooltip_text" maxlength="300">' . esc_textarea($value) . '</textarea>';
}

function glossary_abbreviation_meta_box_callback($post) {
    wp_nonce_field('save_abbreviation_full_form', 'glossary_abbreviation_nonce');
    $value = get_post_meta($post->ID, '_abbreviation_full_form', true);
    echo '<input type="text" style="width:100%;" id="glossary_abbreviation_full_form" name="glossary_abbreviation_full_form" value="' . esc_attr($value) . '">';
}

// Save Meta Box Data and Invalidate Cache
function glossary_save_meta_box_data($post_id) {
    if (!isset($_POST['glossary_tooltip_nonce']) || !wp_verify_nonce($_POST['glossary_tooltip_nonce'], 'save_tooltip_text')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (isset($_POST['glossary_tooltip_text'])) {
        $tooltip_text = wp_kses_post($_POST['glossary_tooltip_text']);
        update_post_meta($post_id, '_tooltip_text', $tooltip_text);
        // Delete transient cache for this term
        delete_transient('glossary_term_' . $post_id);
        glossary_log_cache_action("Cache invalidated for glossary term ID: $post_id");
    }

    if (!isset($_POST['glossary_abbreviation_nonce']) || !wp_verify_nonce($_POST['glossary_abbreviation_nonce'], 'save_abbreviation_full_form')) {
        return;
    }
    if (isset($_POST['glossary_abbreviation_full_form'])) {
        $abbreviation_full_form = sanitize_text_field($_POST['glossary_abbreviation_full_form']);
        update_post_meta($post_id, '_abbreviation_full_form', $abbreviation_full_form);
    }
}
add_action('save_post_glossary', 'glossary_save_meta_box_data');

// Shortcode to ignore glossary replacement
function glossary_ignore_shortcode($atts, $content = null) {
    return '<span class="glossary-ignore">' . do_shortcode($content) . '</span>';
}
add_shortcode('gloss_ign', 'glossary_ignore_shortcode');

// Retrieve or cache glossary term on-demand using Transients API
function get_or_cache_glossary_term($term_id) {
    static $processed_terms = array(); // To prevent duplicate processing
    $cache_key = 'glossary_term_' . $term_id;

    if (in_array($term_id, $processed_terms)) {
        // Term already processed in this request
        return get_transient($cache_key);
    }

    $processed_terms[] = $term_id;

    $cached_term = get_transient($cache_key);

    if ($cached_term === false) {
        glossary_log_cache_action("Cache miss for glossary term ID: $term_id");

        $term = get_post($term_id);
        if ($term && $term->post_type === 'glossary') {
            $tooltip_text = get_post_meta($term->ID, '_tooltip_text', true) ?: 'No description available';
            $cached_term = array(
                'title' => $term->post_title,
                'tooltip_text' => $tooltip_text,
                'link' => get_permalink($term->ID),
            );
            // Set transient cache for 1 week (adjust as needed)
            set_transient($cache_key, $cached_term, 168 * HOUR_IN_SECONDS);
            glossary_log_cache_action("Cache set for glossary term ID: $term_id");

            // Update cached terms list
            $cached_terms = get_option('glossary_cached_terms', array());
            if (!in_array($term->post_title, $cached_terms)) {
                $cached_terms[] = $term->post_title;
                update_option('glossary_cached_terms', $cached_terms);
            }
        }
    }

    return $cached_term;
}

// Display Glossary Terms with Tooltip in Content
function glossary_tooltip_filter($content) {
    if (is_post_type_archive('glossary') || is_singular('glossary') || is_front_page()) {
        return $content;
    }

    // Exclude glossary replacement within <pre> and <code> tags and [gloss_ign] shortcode
    $ignored_blocks = [];
    $content = preg_replace_callback('/<pre.*?>.*?<\/pre>|<code.*?>.*?<\/code>|(\[gloss_ign\](.*?)\[\/gloss_ign\])/is', function($matches) use (&$ignored_blocks) {
        $placeholder = '<!--glossary-ignore-placeholder-' . count($ignored_blocks) . '-->';
        $ignored_blocks[$placeholder] = $matches[0];
        return $placeholder;
    }, $content);

    // Extract words including numbers, preserving case sensitivity
    preg_match_all('/\b[A-Za-z0-9-]+\b/', strip_tags($content), $matches);
    $content_words = array_unique($matches[0]);

    // Prepare array for matched terms
    $matched_terms = [];

    // Loop through content words to find matching glossary terms
    if (!empty($content_words)) {
        global $wpdb;

        // Sanitize and prepare words for SQL IN clause (case-sensitive match)
        $escaped_words = array_map(function($word) use ($wpdb) {
            return esc_sql($word); // Do not convert to lowercase to preserve case sensitivity
        }, $content_words);

        // Convert array to comma-separated string for SQL query
        $words_in = "'" . implode("','", $escaped_words) . "'";

        // Custom SQL query to get glossary terms matching the words in the content
        $query = "
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'glossary'
            AND post_status = 'publish'
            AND BINARY post_title IN ($words_in)
        ";

        $matched_terms = $wpdb->get_col($query);
    }

    $terms_to_cache = [];

    foreach ($matched_terms as $term_id) {
        $cached_term = get_or_cache_glossary_term($term_id);
        if ($cached_term) {
            $terms_to_cache[] = $cached_term;
        }
    }

    foreach ($terms_to_cache as $cached_term) {
        $term_title = esc_html($cached_term['title']);
        $tooltip_text = esc_attr(strip_tags($cached_term['tooltip_text']));
        $link = esc_url($cached_term['link']);
        $tooltip = '<span class="glossary-term" data-tooltip-text="' . esc_js($tooltip_text) . '" data-link="' . $link . '">' . $term_title . '</span>';
        $pattern = '/(?<!\w)(' . preg_quote($term_title, '/') . ')(?!\w)(?![^<]*>)/';

        $max_occurrences = 7;
        $count = 0;
        $replacement = function ($match) use ($tooltip, &$count, $max_occurrences) {
            if ($count < $max_occurrences) {
                $count++;
                return $tooltip;
            }
            return esc_html($match[0]);
        };

        $content = preg_replace_callback($pattern, $replacement, $content);
    }

    // Restore ignored blocks
    foreach ($ignored_blocks as $placeholder => $original) {
        $content = str_replace($placeholder, $original, $content);
    }

    return $content;
}
add_filter('the_content', 'glossary_tooltip_filter');

// Glossary Cache Page to manually generate and clear cache
function glossary_cache_page_callback() {
    if (isset($_POST['clear_cache'])) {
        // Delete all transients related to glossary terms
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_glossary_term_%' OR option_name LIKE '_transient_timeout_glossary_term_%'");
        delete_option('glossary_cached_terms');
        glossary_log_cache_action("Glossary cache cleared manually.");
    }

    if (isset($_POST['generate_cache'])) {
        $all_terms_ids = get_posts(array(
            'post_type' => 'glossary',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        foreach ($all_terms_ids as $term_id) {
            get_or_cache_glossary_term($term_id);
        }
        glossary_log_cache_action("Glossary cache generated with " . count($all_terms_ids) . " terms.");

        // Update cached terms list
        $cached_term_titles = array_map(function($term_id) {
            $term = get_post($term_id);
            return $term->post_title;
        }, $all_terms_ids);
        update_option('glossary_cached_terms', $cached_term_titles);
    }

    // Retrieve cached terms from option
    $cached_terms = get_option('glossary_cached_terms', []);
    $cache_count = count($cached_terms);

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Glossary Cache', 'text_domain') . '</h1>';
    echo '<form method="post">';
    submit_button(__('Generate Cache', 'text_domain'), 'primary', 'generate_cache');
    echo '<p style="color: red; font-weight: bold;">' . esc_html__('WARNING: If you have over 1000 glossary terms, pressing "Generate Cache" may cause high server load, slow down your site, or lead to timeout errors. We recommend allowing glossary terms to cache automatically on first use to avoid potential performance issues. This can be done by just browsing your website.', 'text_domain') . '</p>';
    submit_button(__('Clear Cache', 'text_domain'), 'secondary', 'clear_cache');
    echo '<p style="color: red; font-weight: bold;">' . esc_html__('WARNING: If your site is running smoothly, you should not need to press this button. Glossaries are cached for 1 week (168 hours) and will automatically rebuild as users visit pages or posts containing glossaries.', 'text_domain') . '</p>';
    echo '</form>';
    echo '<p>' . sprintf(esc_html__('Number of cached terms: %d', 'text_domain'), $cache_count) . '</p>';
    echo '<ul>';
    foreach ($cached_terms as $term_title) {
        echo '<li>' . esc_html($term_title) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// Log cache-related actions and limit log to 1000 entries
function glossary_log_cache_action($message) {
    static $logged_messages = array(); // To prevent duplicate logging

    if (in_array($message, $logged_messages)) {
        return;
    }

    $logged_messages[] = $message;

    $log = get_option('glossary_cache_log', []);
    array_unshift($log, current_time('mysql') . ' - ' . $message);

    // Limit the log to 1000 entries
    if (count($log) > 1000) {
        $log = array_slice($log, 0, 1000);
    }

    update_option('glossary_cache_log', $log);
}

// Glossary Cache Log Page
function glossary_cache_log_page_callback() {
    if (isset($_POST['clear_log'])) {
        update_option('glossary_cache_log', []);
    }

    $log = get_option('glossary_cache_log', []);
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Glossary Cache Log', 'text_domain') . '</h1>';
    echo '<form method="post">';
    submit_button(__('Clear Log', 'text_domain'), 'primary', 'clear_log');
    echo '</form>';
    echo '<p>' . esc_html__('Caching actions log (Max 1000 entries; oldest entries will be removed when limit is reached. This does not log Cache hits, only when glossaries are created, modified or deleted or cached (miss and set). This is mostly for debugging):', 'text_domain') . '</p>';
    echo '<ul>';
    foreach ($log as $log_entry) {
        echo '<li>' . esc_html($log_entry) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// Add Glossary Info, Cache, and Cache Log Pages
function glossary_add_submenu_pages() {
    add_submenu_page(
        'edit.php?post_type=glossary',
        __('Glossary Info', 'text_domain'),
        __('Glossary Info', 'text_domain'),
        'manage_options',
        'glossary_info',
        'glossary_info_page_callback'
    );
    add_submenu_page(
        'edit.php?post_type=glossary',
        __('Glossary Cache', 'text_domain'),
        __('Glossary Cache', 'text_domain'),
        'manage_options',
        'glossary_cache',
        'glossary_cache_page_callback'
    );
    add_submenu_page(
        'edit.php?post_type=glossary',
        __('Glossary Cache Log', 'text_domain'),
        __('Glossary Cache Log', 'text_domain'),
        'manage_options',
        'glossary_cache_log',
        'glossary_cache_log_page_callback'
    );
}
add_action('admin_menu', 'glossary_add_submenu_pages');

function glossary_info_page_callback() {
    $glossary_count = wp_count_posts('glossary')->publish;
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Glossary Info', 'text_domain') . '</h1>';
    echo '<p>' . esc_html__('To use the Glossary plugin, insert the following shortcode on an archive page:', 'text_domain') . '</p>';
    echo '<code>[glossary_archive]</code>';
    echo '<p>' . esc_html__('To ignore specific glossary terms on certain pages or posts, use the following shortcode:', 'text_domain') . '</p>';
    echo '<code>[gloss_ign]Your Glossary Word[/gloss_ign]</code>';
    echo '<p>' . sprintf(esc_html__('There are currently %s glossary terms available.', 'text_domain'), esc_html($glossary_count)) . '</p>';
    echo '<p>' . esc_html__('Created by ChatGPT &', 'text_domain') . ' <a href="' . esc_url('https://www.nuvorix.com') . '" target="_blank">' . esc_html__('www.Nuvorix.com', 'text_domain') . '</a> ❤️.</p>';
    echo '<p><a href="' . esc_url('https://github.com/Nuvorix/glossary-plugin') . '" target="_blank">' . esc_html__('GitHub', 'text_domain') . '</a></p>';
    echo '</div>';
}
