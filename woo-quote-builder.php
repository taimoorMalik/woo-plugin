<?php
/**
 * Plugin Name: WooCommerce Quote Builder
 * Plugin URI: #
 * Description: A plugin to build quotes with WooCommerce products.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: #
 * Text Domain: woo-quote-builder
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'WQ_BUILDER_VERSION', '1.0.0' );
define( 'WQ_BUILDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'WQ_BUILDER_URL', plugin_dir_url( __FILE__ ) );

// Include required files.
require_once WQ_BUILDER_PATH . 'vendor/autoload.php';
require_once WQ_BUILDER_PATH . 'includes/admin.php';
require_once WQ_BUILDER_PATH . 'includes/api.php';
require_once WQ_BUILDER_PATH . 'includes/form-handler.php';
require_once WQ_BUILDER_PATH . 'includes/frontend.php';
require_once WQ_BUILDER_PATH . 'includes/cpt.php';
require_once WQ_BUILDER_PATH . 'includes/woocommerce.php';

// Product Page Customizations
function wq_is_quote_product($product_id) {
    $allowed_cats = get_option('wq_builder_allowed_categories', array());
    if (empty($allowed_cats)) return false;
    
    $product_cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    
    // Check if product has any of the allowed categories (or their children could be added here logic wise)
    foreach ($product_cats as $cat_id) {
        if (in_array($cat_id, $allowed_cats)) {
            return true;
        }
        // Also check if any parent of this category is in allowed list (optional, but good practice)
        $ancestors = get_ancestors($cat_id, 'product_cat');
        foreach($ancestors as $ancestor) {
             if (in_array($ancestor, $allowed_cats)) {
                return true;
            }
        }
    }
    return false;
}

// Hide Price and Add to Cart on Single Product Page
add_action('wp', 'wq_product_page_modifications');
function wq_product_page_modifications() {
    if (is_product()) {
        global $post;
        if (wq_is_quote_product($post->ID)) {
            // Remove Price
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
            
            // Remove Add to Cart
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            
            // Add Custom Quote Button
            add_action('woocommerce_single_product_summary', 'wq_add_quote_button', 30);
        }
    }
}

function wq_add_quote_button() {
    $quote_url = get_option('wq_builder_quote_page_url');
    if ($quote_url) {
        echo '<a href="' . esc_url($quote_url) . '" class="button alt wq-quote-button">Quote</a>';
    }
}

// Hide Price on Loop (Shop Page, Categories)
add_filter('woocommerce_get_price_html', 'wq_hide_price_on_loop', 10, 2);
function wq_hide_price_on_loop($price, $product) {
    if (wq_is_quote_product($product->get_id())) {
        return '';
    }
    return $price;
}

// Remove Add to Cart from Loop
add_filter('woocommerce_loop_add_to_cart_link', 'wq_remove_loop_add_to_cart', 10, 2);
function wq_remove_loop_add_to_cart($button, $product) {
    if (wq_is_quote_product($product->get_id())) {
         $quote_url = get_option('wq_builder_quote_page_url');
         if ($quote_url) {
             return '<a href="' . esc_url($quote_url) . '" class="button">Quote</a>';
         }
         return '';
    }
    return $button;
}

// Hide Wishlist Button (TI WooCommerce Wishlist & YITH)
add_action('wp_head', 'wq_hide_wishlist_css');
function wq_hide_wishlist_css() {
    if (is_product()) {
        global $post;
        if (wq_is_quote_product($post->ID)) {
            echo '<style>
                .tinv-wraper.tinv-wishlist, 
                .yith-wcwl-add-to-wishlist, 
                .wishlist_table, 
                .yith-wcwl-add-button,
                .add_to_wishlist,
                .single_add_to_wishlist,
                a[href*="add_to_wishlist"] { 
                    display: none !important; 
                }
            </style>';
        }
    }
}

// Initialize the plugin.
function wq_builder_init() {
	// Initialization code here if needed.
}
add_action( 'plugins_loaded', 'wq_builder_init' );

// Enqueue scripts and styles.
function wq_builder_scripts() {
    // Enqueue Dashicons for frontend (logged-out users don't have it by default)
    wp_enqueue_style( 'dashicons' );

    $wq_style_ver = file_exists( WQ_BUILDER_PATH . 'assets/css/style.css' ) ? filemtime( WQ_BUILDER_PATH . 'assets/css/style.css' ) : WQ_BUILDER_VERSION;
    $wq_script_ver = file_exists( WQ_BUILDER_PATH . 'assets/js/app.js' ) ? filemtime( WQ_BUILDER_PATH . 'assets/js/app.js' ) : WQ_BUILDER_VERSION;

    wp_enqueue_style( 'wq-builder-style', WQ_BUILDER_URL . 'assets/css/style.css', array(), $wq_style_ver );
    
    // Enqueue jQuery UI Sortable
    wp_enqueue_script( 'jquery-ui-sortable' );
    
    wp_enqueue_script( 'wq-builder-script', WQ_BUILDER_URL . 'assets/js/app.js', array( 'jquery', 'jquery-ui-sortable' ), $wq_script_ver, true );

    $wq_portal_fix_ver = file_exists( WQ_BUILDER_PATH . 'assets/js/investor-portal-fix.js' ) ? filemtime( WQ_BUILDER_PATH . 'assets/js/investor-portal-fix.js' ) : WQ_BUILDER_VERSION;
    wp_enqueue_script( 'wq-investor-portal-fix', WQ_BUILDER_URL . 'assets/js/investor-portal-fix.js', array( 'jquery' ), $wq_portal_fix_ver, true );
    
    // Inject Custom CSS
    $custom_css = get_option('wq_custom_css');
    if ( ! empty( $custom_css ) ) {
        wp_add_inline_style( 'wq-builder-style', $custom_css );
    }

    wp_add_inline_style(
        'wq-builder-style',
        '.wq-builder-container .wq-copy-status { display: none !important; } .wq-builder-container .wq-copy-row-btn, .wq-builder-container .wq-paste-row-btn { display: none !important; } .wq-builder-container .wq-row-container.wq-row-has-product:hover .wq-row-clipboard-controls .wq-copy-row-btn { display: flex !important; } body.wq-has-row-clipboard .wq-builder-container .wq-row-container:not(.wq-row-clipboard-source):hover .wq-row-clipboard-controls .wq-paste-row-btn { display: flex !important; } @media (max-width: 768px) { .wq-builder-container .wq-row-container.wq-row-has-product .wq-row-clipboard-controls .wq-copy-row-btn { display: flex !important; } body.wq-has-row-clipboard .wq-builder-container .wq-row-container:not(.wq-row-clipboard-source) .wq-row-clipboard-controls .wq-paste-row-btn { display: flex !important; } }'
    );

    // Localize script for AJAX
    // Pass custom fields and formula
    $custom_fields = get_option('wq_custom_fields', array());
    // Default system fields if not set
    if (empty($custom_fields)) {
        $custom_fields = array(
             array('label' => 'Thickness (mm)', 'slug' => 'thickness', 'type' => 'number', 'placeholder' => '18'),
             array('label' => 'Price per MM²', 'slug' => 'wq_pricing_per_mm', 'type' => 'number', 'placeholder' => '0.00005'),
             array('label' => 'Max Length (mm)', 'slug' => 'wq_max_length', 'type' => 'number', 'placeholder' => '2440'),
             array('label' => 'Max Width (mm)', 'slug' => 'wq_max_width', 'type' => 'number', 'placeholder' => '1220'),
             array('label' => 'Min Length (mm)', 'slug' => 'wq_min_length', 'type' => 'number', 'placeholder' => '100'),
             array('label' => 'Min Width (mm)', 'slug' => 'wq_min_width', 'type' => 'number', 'placeholder' => '100'),
        );
    }
    
    $pricing_formula = get_option('wq_pricing_formula', '({length} * {width}) * {wq_pricing_per_mm} * {qty}');
    $edge_formulas = array(
        'l1' => get_option('wq_edge_formula_l1', ''),
        'l2' => get_option('wq_edge_formula_l2', ''),
        'w1' => get_option('wq_edge_formula_w1', ''),
        'w2' => get_option('wq_edge_formula_w2', ''),
    );
    $edge_unit = get_option('wq_edge_unit_label', 'M');
    $edge_operations = get_option('wq_edge_operations', array());
    $dimension_unit = get_option('wq_dimension_unit', 'mm');
    $label_dropdown_options = get_option('wq_label_dropdown_options', array());
    if ( ! is_array( $label_dropdown_options ) ) {
        $label_dropdown_options = preg_split( '/\R+/', (string) $label_dropdown_options );
    }
    $label_dropdown_options = array_values(
        array_filter(
            array_map( 'trim', (array) $label_dropdown_options ),
            function( $v ) { return $v !== ''; }
        )
    );
    
    // Validation Maps
    $map_min_len = get_option('wq_map_min_len', 'wq_min_length');
    $map_max_len = get_option('wq_map_max_len', 'wq_max_length');
    $map_min_wid = get_option('wq_map_min_wid', 'wq_min_width');
    $map_max_wid = get_option('wq_map_max_wid', 'wq_max_width');
    
    wp_localize_script( 'wq-builder-script', 'wqBuilder', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wq_builder_nonce' ),
        'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$',
        'vat_percentage' => get_option('wq_builder_vat_percentage', '20'),
        'template_pdf_url' => get_option('wq_builder_template_pdf_url', ''),
        'is_user_logged_in' => is_user_logged_in() ? '1' : '0',
        'keep_offcuts_enable' => get_option('wq_keep_offcuts_enable', 1) ? '1' : '0',
        'keep_offcuts_tooltip' => get_option('wq_keep_offcuts_tooltip', "If checked, we'll also include all the larger offcuts from your boards. These might be reduced to a more manageable size of no less than 1500mm wide for transport."),
        'custom_fields' => $custom_fields,
        'pricing_formula' => $pricing_formula,
        'edge_formulas' => $edge_formulas,
        'edge_unit' => $edge_unit,
        'edge_operations' => $edge_operations,
        'label_dropdown_options' => $label_dropdown_options,
        'dimension_unit' => $dimension_unit,
        'field_map' => array(
            'min_len' => $map_min_len,
            'max_len' => $map_max_len,
            'min_wid' => $map_min_wid,
            'max_wid' => $map_max_wid
        ),
        'tour_data' => array(
            'step_1' => array('title' => get_option('wq_tour_step_1_title', 'Context Help'), 'content' => get_option('wq_tour_step_1_content', 'Whenever you see the information icon, select it to view relevant help or information on that item.')),
            'step_2' => array('title' => get_option('wq_tour_step_2_title', 'Your Project Details'), 'content' => get_option('wq_tour_step_2_content', 'Enter in the details to help you identify this project.')),
            'step_3' => array('title' => get_option('wq_tour_step_3_title', 'Overview'), 'content' => get_option('wq_tour_step_3_content', 'On each row add the board and sizes you require to be cut, this can include what edging and edge finish you want. Everything entered is automatically saved temporarily, so you can continue to browse the site, view boards and return here to continue.')),
            'step_4' => array('title' => get_option('wq_tour_step_4_title', 'Select Material'), 'content' => get_option('wq_tour_step_4_content', 'Search for a colour, board name or type or use the category navigation to list boards. Alternatively you can navigate to the product pages on this site and select Add to Cutting List to send the board back here to continue to populate your list.')),
            'step_5' => array('title' => get_option('wq_tour_step_5_title', 'Enter your Required Sizes'), 'content' => get_option('wq_tour_step_5_content', "Enter the dimensions of the board you require, including quantity. Thickness is read only and dependant on the board selected, if you require double board thickness, please contact us. If you're adding edging, we'll calculate this automatically to reduce the board size to allow for the edging thickness.")),
            'step_6' => array('title' => get_option('wq_tour_step_6_title', 'Edging Indicators'), 'content' => get_option('wq_tour_step_6_content', "Select any of the edging boxes to show the edgebanding panel. Once you have edging selected it will be indicated here with a green tick, or if it's red there's an issue with the size, thickness or finish with the board and guidance should be displayed for you to rectify.")),
            'step_7' => array('title' => get_option('wq_tour_step_7_title', 'Edgebanding Panel'), 'content' => get_option('wq_tour_step_7_content', "Edging that matches your chosen board will be listed under Best Matches, alternatively use the search field. Select an edging product from those listed, then select which edges you want to assign it to. Selecting an edge box again or pressing delete will remove it.")),
            'step_8' => array('title' => get_option('wq_tour_step_8_title', 'Edging Finish'), 'content' => get_option('wq_tour_step_8_content', "Depending on the size/thickness of your board and the edging selected, differing edging options are available. Select a finish option from those available.")),
            'step_9' => array('title' => get_option('wq_tour_step_9_title', 'Labelling'), 'content' => get_option('wq_tour_step_9_content', "You can add labelling or notes to this row, to identify the piece. This will be shown on the cutting pattern and labelled on its packaging, so it will be clear what this board is for within your project. And lastly, you can delete the row by selecting on the X icon.")),
            'step_10' => array('title' => get_option('wq_tour_step_10_title', 'Quote pricing'), 'content' => get_option('wq_tour_step_10_content', "When ready, select the Get Pricing button to calculate your cutting pattern and provide a price which will be shown after about 30 seconds. You can then purchase by adding it to your basket and continuing to checkout. Or you can make further additions or changes and refresh the pricing.")),
            'step_11' => array('title' => get_option('wq_tour_step_11_title', 'Saving & Account Creation'), 'content' => get_option('wq_tour_step_11_content', "To save your cutting list and price quote, create an account or sign in. You can then access all your past quotes in one place.")),
        )
    ));
}
add_action( 'wp_enqueue_scripts', 'wq_builder_scripts' );

function wq_dimension_unit_factor($unit) {
    if ($unit === 'm') return 1000.0;
    if ($unit === 'cm') return 10.0;
    if ($unit === 'in') return 25.4;
    return 1.0;
}

function wq_convert_dimension_value_between_units($value, $from_unit, $to_unit) {
    if ($value === '' || $value === null) return $value;
    $v = floatval($value);
    $from_factor = wq_dimension_unit_factor($from_unit);
    $to_factor = wq_dimension_unit_factor($to_unit);
    if ($from_factor <= 0 || $to_factor <= 0) return $value;
    $mm = $v * $from_factor;
    $out = $mm / $to_factor;
    $formatted = rtrim(rtrim(number_format($out, 6, '.', ''), '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}

function wq_on_dimension_unit_change($old_value, $new_value) {
    $old_unit = is_string($old_value) ? $old_value : 'mm';
    $new_unit = is_string($new_value) ? $new_value : 'mm';
    if ($old_unit === $new_unit) return;

    $map_keys = array(
        get_option('wq_map_min_len', 'wq_min_length'),
        get_option('wq_map_max_len', 'wq_max_length'),
        get_option('wq_map_min_wid', 'wq_min_width'),
        get_option('wq_map_max_wid', 'wq_max_width')
    );
    $default_keys = array('wq_min_length', 'wq_max_length', 'wq_min_width', 'wq_max_width');
    $meta_keys = array_values(array_unique(array_filter(array_merge($map_keys, $default_keys), function($v) { return is_string($v) && $v !== ''; })));

    $allowed_cats = get_option('wq_builder_allowed_categories', array());
    $args = array(
        'post_type' => 'product',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    );
    if (!empty($allowed_cats)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => array_map('intval', (array) $allowed_cats),
            )
        );
    }

    $product_ids = get_posts($args);
    if (!empty($product_ids)) {
        foreach ($product_ids as $pid) {
            foreach ($meta_keys as $key) {
                $current = get_post_meta($pid, $key, true);
                if ($current === '' || $current === null) continue;
                $converted = wq_convert_dimension_value_between_units($current, $old_unit, $new_unit);
                update_post_meta($pid, $key, $converted);
            }
        }
    }

    $fields = get_option('wq_custom_fields', array());
    if (is_array($fields) && !empty($fields)) {
        $unit_label = $new_unit;
        foreach ($fields as &$field) {
            if (!is_array($field) || empty($field['slug'])) continue;
            $slug = $field['slug'];
            if (!in_array($slug, $meta_keys, true)) continue;

            if (isset($field['placeholder'])) {
                $field['placeholder'] = wq_convert_dimension_value_between_units($field['placeholder'], $old_unit, $new_unit);
            }
            if (isset($field['label']) && is_string($field['label'])) {
                $field['label'] = preg_replace('/\\((mm|cm|m|in)\\)/i', '(' . $unit_label . ')', $field['label']);
            }
        }
        unset($field);
        update_option('wq_custom_fields', $fields, false);
    }
}
add_action('update_option_wq_dimension_unit', 'wq_on_dimension_unit_change', 10, 2);

function wq_maybe_sync_dimension_unit_products() {
    $current = get_option('wq_dimension_unit', 'mm');
    $last = get_option('wq_dimension_unit_products_unit', 'mm');
    if (!is_string($current)) $current = 'mm';
    if (!is_string($last)) $last = 'mm';
    if ($current === $last) return;
    wq_on_dimension_unit_change($last, $current);
    update_option('wq_dimension_unit_products_unit', $current, false);
}
add_action('admin_init', 'wq_maybe_sync_dimension_unit_products');
