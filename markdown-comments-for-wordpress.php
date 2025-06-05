<?php
/**
 * Plugin Name: Markdown Comments for WordPress®
 * Description: Allow users to write comments in Markdown format and automatically parse them into HTML.
 * Plugin URI:  https://github.com/robertdevore/markdown-comments-for-wordpress/
 * Version:     1.0.0
 * Author:      Robert DeVore
 * Author URI:  https://robertdevore.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: markdown-comments
 * Domain Path: /languages
 * Update URI:  https://github.com/robertdevore/markdown-comments-for-wordpress/
 */

// Security check to prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

// Include the Plugin Update Checker.
require 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/robertdevore/markdown-comments-for-wordpress/',
    __FILE__,
    'markdown-comments-for-wordpress'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

// Include the autoload for Composer.
if ( ! class_exists( 'RobertDevore\WPComCheck\WPComPluginHandler' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use RobertDevore\WPComCheck\WPComPluginHandler;

// Initialize the WPComPluginHandler with the plugin slug and learn more link.
new WPComPluginHandler( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' );

/**
 * Load the plugin textdomain for translations
 * 
 * @since  1.0.0
 * @return void
 */
function markdown_comments_load_textdomain() {
    load_plugin_textdomain( 
        'markdown-comments', 
        false, 
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'markdown_comments_load_textdomain' );

define( 'MARKDOWN_COMMENTS_VERSION', '1.0.0' );
define( 'MARKDOWN_COMMENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MARKDOWN_COMMENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Activate the plugin and set default option
 * 
 * @since  1.0.0
 * @return void
 */
function markdown_comments_activate_plugin() {
    add_option( 'markdown_comments_enable_markdown_comments', true );
}
register_activation_hook( __FILE__, 'markdown_comments_activate_plugin' );

/**
 * Add settings field to enable/disable markdown for comments
 * 
 * @since  1.0.0
 * @return void
 */
function markdown_comments_register_settings() {
    register_setting( 'discussion', 'markdown_comments_enable_markdown_comments' );
    add_settings_field(
        'markdown_comments_enable_markdown_comments',
        esc_html__( 'Markdown Comments', 'markdown-comments' ),
        'markdown_comments_render_settings_field',
        'discussion',
        'default'
    );
}
add_action( 'admin_init', 'markdown_comments_register_settings' );

/**
 * Render the settings field
 * 
 * @since  1.0.0
 * @return void
 */
function markdown_comments_render_settings_field() {
    $value = get_option( 'markdown_comments_enable_markdown_comments', true );
    ?>
    <input type="checkbox" name="markdown_comments_enable_markdown_comments" value="1" <?php checked( 1, $value, true ); ?> />
    <label for="markdown_comments_enable_markdown_comments"><?php esc_html_e( 'Enable Markdown for Comments', 'markdown-comments' ); ?></label>
    <?php
}

/**
 * Enqueue CSS and JavaScript for Markdown support
 * 
 * @since  1.0.0
 * @return void
 */
function markdown_comments_enqueue_scripts() {
    if ( is_singular() && comments_open() && get_option( 'markdown_comments_enable_markdown_comments', true ) ) {
        // Enqueue CSS.
        wp_enqueue_style(
            'markdown-comments-style',
            plugin_dir_url( __FILE__ ) . 'assets/css/markdown-comments.css',
            [],
            MARKDOWN_COMMENTS_VERSION
        );

        // Enqueue JavaScript.
        wp_enqueue_script(
            'markdown-comments-script',
            plugin_dir_url( __FILE__ ) . 'assets/js/markdown-comments.js',
            [],
            MARKDOWN_COMMENTS_VERSION,
            true
        );

        // Localize script to pass PHP variables to JS, like the help text.
        wp_localize_script(
            'markdown-comments-script',
            'markdownCommentsHelp',
            [
                'text' => esc_js( __( 'You can use Markdown: **bold**, *italic*, `code`, [link](url), # headings, - lists', 'markdown-comments' ) ),
            ]
        );
    }
}
add_action( 'wp_enqueue_scripts', 'markdown_comments_enqueue_scripts' );

/**
 * Apply inline formatting (bold, italic, code, links) to text
 *
 * @param string $text The text to format
 * 
 * @since  1.0.0
 * @return mixed The formatted text
 */
function markdown_comments_apply_inline_formatting( $text ) {
    // Bold **text** or __text__.
    $text = preg_replace_callback( '/\*\*(.*?)\*\*/', function($matches) {
        return '<strong>' . esc_html($matches[1]) . '</strong>';
    }, $text );
    $text = preg_replace_callback( '/__(.*?)__/', function($matches) {
        return '<strong>' . esc_html($matches[1]) . '</strong>';
    }, $text );

    // Italic *text* or _text_ (but not if it's part of list markers or bold formatting).
    // Handle single asterisks for italics (avoid conflicts with list markers and bold).
    $text = preg_replace_callback( '/(?<!\*)(?<!\w)\*([^*\s][^*]*?[^*\s]|\w)\*(?!\*)(?!\w)/', function($matches) {
        return '<em>' . esc_html($matches[1]) . '</em>';
    }, $text );

    // Handle single underscores for italics (avoid conflicts with bold formatting).
    $text = preg_replace_callback( '/(?<!_)(?<!\w)_([^_\s][^_]*?[^_\s]|\w)_(?!_)(?!\w)/', function($matches) {
        return '<em>' . esc_html($matches[1]) . '</em>';
    }, $text );
    $text = preg_replace_callback( '/`(.*?)`/', function($matches) {
        return '<code>' . esc_html($matches[1]) . '</code>';
    }, $text );

    // Links [text](url).
    $text = preg_replace_callback( '/\[([^\]]+)\]\(([^)]+)\)/', function($matches) {
        $url = function_exists('esc_url') ? esc_url($matches[2]) : esc_html($matches[2]);
        return '<a href="' . $url . '" rel="nofollow">' . esc_html($matches[1]) . '</a>';
    }, $text );

    return $text;
}

/**
 * Simple Markdown parser for basic formatting
 *
 * @param string $text The Markdown text to parse
 * 
 * @since  1.0.0
 * @return string The parsed HTML
 */
function markdown_comments_parse_markdown( $text ) {
    // Split into lines for processing.
    $lines     = explode( "\n", $text );
    $output    = [];
    $in_list   = false;
    $list_type = '';

    foreach ( $lines as $line ) {
        $line = trim( $line );

        // Skip empty lines.
        if ( empty( $line ) ) {
            if ( $in_list ) {
                $output[] = $list_type === 'ul' ? '</ul>' : '</ol>';
                $in_list  = false;
            }
            $output[] = '';
            continue;
        }

        // Headings.
        if ( preg_match( '/^(#{1,6})\s+(.+)/', $line, $matches ) ) {
            if ( $in_list ) {
                $output[] = $list_type === 'ul' ? '</ul>' : '</ol>';
                $in_list  = false;
            }
            $level = strlen( $matches[1] );
            // Apply inline formatting to heading content, then wrap in heading tags.
            $heading_text = markdown_comments_apply_inline_formatting( trim( $matches[2] ) );
            $output[] = '<h' . $level . '>' . $heading_text . '</h' . $level . '>';
            continue;
        }

        // Unordered lists (-, *, +, and en-dash –).
        if ( preg_match( '/^[-*+–-]\s+(.+)/', $line, $matches ) ) {
            if ( ! $in_list || $list_type !== 'ul' ) {
                if ( $in_list && $list_type === 'ol' ) {
                    $output[] = '</ol>';
                }
                $output[]  = '<ul>';
                $in_list   = true;
                $list_type = 'ul';
            }
            // Apply inline formatting to list item content.
            $list_content = markdown_comments_apply_inline_formatting( trim( $matches[1] ) );
            $output[] = '<li>' . $list_content . '</li>';
            continue;
        }

        // Ordered lists (1., 2., etc.).
        if ( preg_match( '/^\d+\.\s+(.+)/', $line, $matches ) ) {
            if ( ! $in_list || $list_type !== 'ol' ) {
                if ( $in_list && $list_type === 'ul' ) {
                    $output[] = '</ul>';
                }
                $output[]  = '<ol>';
                $in_list   = true;
                $list_type = 'ol';
            }
            // Apply inline formatting to list item content.
            $list_content = markdown_comments_apply_inline_formatting( trim( $matches[1] ) );
            $output[] = '<li>' . $list_content . '</li>';
            continue;
        }

        // Regular paragraph text - keep it raw for now.
        if ( $in_list ) {
            $output[] = $list_type === 'ul' ? '</ul>' : '</ol>';
            $in_list  = false;
        }
        $output[] = $line; // Keep raw text
    }

    // Close any remaining list.
    if ( $in_list ) {
        $output[] = $list_type === 'ul' ? '</ul>' : '</ol>';
    }

    // Now handle paragraph grouping and final formatting.
    $lines             = $output;
    $final_output      = [];
    $current_paragraph = [];

    foreach ( $lines as $line ) {
        $line = trim( $line );

        // If it's an empty line, finish current paragraph.
        if ( empty( $line ) ) {
            if ( ! empty( $current_paragraph ) ) {
                // Apply inline formatting and escaping to paragraph content
                $paragraph_text    = implode( ' ', $current_paragraph );
                $paragraph_text    = markdown_comments_apply_inline_formatting( $paragraph_text );
                $final_output[]    = '<p>' . $paragraph_text . '</p>';
                $current_paragraph = [];
            }
            continue;
        }

        // If it's a block element (heading, list), add it directly.
        if ( preg_match( '/^<(h[1-6]|ul|ol|li)>/', $line ) || preg_match( '/^<\/(ul|ol)>/', $line ) ) {
            // Finish any current paragraph first.
            if ( ! empty( $current_paragraph ) ) {
                $paragraph_text    = implode( ' ', $current_paragraph );
                $paragraph_text    = markdown_comments_apply_inline_formatting( $paragraph_text );
                $final_output[]    = '<p>' . $paragraph_text . '</p>';
                $current_paragraph = [];
            }
            // Block elements are already formatted, add them directly.
            $final_output[] = $line;
        } else {
            // It's regular text, add to current paragraph.
            $current_paragraph[] = $line;
        }
    }

    // Don't forget the last paragraph.
    if ( ! empty( $current_paragraph ) ) {
        $paragraph_text = implode( ' ', $current_paragraph );
        $paragraph_text = markdown_comments_apply_inline_formatting( $paragraph_text );
        $final_output[] = '<p>' . $paragraph_text . '</p>';
    }

    return implode( "\n", $final_output );
}

/**
 * Convert Markdown to HTML when displaying the comment
 *
 * @param string $comment_text The comment content
 * 
 * @since  1.0.0
 * @return string The parsed HTML
 */
function markdown_comments_filter_comment_text( $comment_text ) {
    if ( get_option( 'markdown_comments_enable_markdown_comments', true ) ) {
        // Convert any smart characters back to regular ones before processing.
        $comment_text = str_replace( [ '–', '—' ], [ '-', '--' ], $comment_text );

        return wp_kses_post( markdown_comments_parse_markdown( $comment_text ) );
    }

    return $comment_text;
}
add_filter( 'comment_text', 'markdown_comments_filter_comment_text', 5 );

/**
 * Remove WordPress default formatting filters for comments when markdown is enabled
 *
 * @since  1.0.0
 * @return void
 */
function markdown_comments_remove_default_filters() {
    if ( get_option( 'markdown_comments_enable_markdown_comments', true ) ) {
        // Remove default WordPress formatting so it doesn't conflict.
        remove_filter( 'comment_text', 'wpautop', 30 );
        remove_filter( 'comment_text', 'wptexturize', 10 );
        remove_filter( 'comment_text', 'convert_chars', 10 );
        remove_filter( 'comment_text', 'convert_smilies', 20 );
    }
}
add_action( 'init', 'markdown_comments_remove_default_filters' );
