<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register Custom Post Types - REMOVE this if it's already in admin.php
// But wait, the user's error says it's declared in admin.php AND cpt.php.
// Let's comment out the registration here and let admin.php handle it, or rename/move it.
// The best practice is to have registration in one place.
// Since I just added it to admin.php, I should remove it from here to avoid conflict.

// 1. Edge Profile (Visuals: Square, Radius)
function wq_register_edge_cpts() {
    $labels_profile = array(
        'name'               => 'Edge Profiles',
        'singular_name'      => 'Edge Profile',
        'menu_name'          => 'Edge Profiles',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Edge Profile',
        'edit_item'          => 'Edit Edge Profile',
        'new_item'           => 'New Edge Profile',
        'view_item'          => 'View Edge Profile',
        'search_items'       => 'Search Edge Profiles',
        'not_found'          => 'No Edge Profiles found',
        'not_found_in_trash' => 'No Edge Profiles found in Trash',
    );

    $args_profile = array(
        'labels'              => $labels_profile,
        'public'              => false, // Not public on frontend
        'show_ui'             => true,  // Show in admin
        'show_in_menu'        => 'edit.php?post_type=product', // Show under Products menu? Or standalone? Let's keep it standalone for now or under a main menu if we had one. Let's put it under Products for cleanliness.
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'supports'            => array( 'title', 'thumbnail' ), // Thumbnail for the visual image
        'menu_icon'           => 'dashicons-format-image',
    );

    register_post_type( 'wq_edge_profile', $args_profile );

    // 2. Edge Service (Materials: K697...)
    $labels_service = array(
        'name'               => 'Edge Services',
        'singular_name'      => 'Edge Service',
        'menu_name'          => 'Edge Services',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Edge Service',
        'edit_item'          => 'Edit Edge Service',
        'new_item'           => 'New Edge Service',
        'view_item'          => 'View Edge Service',
        'search_items'       => 'Search Edge Services',
        'not_found'          => 'No Edge Services found',
        'not_found_in_trash' => 'No Edge Services found in Trash',
    );

    $args_service = array(
        'labels'              => $labels_service,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'edit.php?post_type=product', // Put under Products too
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'supports'            => array( 'title' ),
        'menu_icon'           => 'dashicons-admin-tools',
    );

    register_post_type( 'wq_edge_service', $args_service );
}
add_action( 'init', 'wq_register_edge_cpts' );

// 3. Register Quote CPT and Statuses
function wq_register_quote_cpt() {
    $labels = array(
        'name'               => 'Saved Quotes',
        'singular_name'      => 'Quote',
        'menu_name'          => 'Saved Quotes',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Quote',
        'edit_item'          => 'Edit Quote',
        'new_item'           => 'New Quote',
        'view_item'          => 'View Quote',
        'search_items'       => 'Search Quotes',
        'not_found'          => 'No quotes found',
        'not_found_in_trash' => 'No quotes found in Trash',
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false, // Not public on frontend (handled via shortcode/account)
        'show_ui'             => true,  // Show in admin
        'show_in_menu'        => true,
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'supports'            => array( 'title', 'author' ),
        'menu_icon'           => 'dashicons-list-view',
    );

    register_post_type( 'wq_quote', $args );
    
    // Register Custom Statuses
    register_post_status( 'wq-saved', array(
        'label'                     => 'Saved',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Saved <span class="count">(%s)</span>', 'Saved <span class="count">(%s)</span>' ),
    ) );
    
    register_post_status( 'wq-in-cart', array(
        'label'                     => 'In Cart',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'In Cart <span class="count">(%s)</span>', 'In Cart <span class="count">(%s)</span>' ),
    ) );
    
    register_post_status( 'wq-purchased', array(
        'label'                     => 'Purchased',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Purchased <span class="count">(%s)</span>', 'Purchased <span class="count">(%s)</span>' ),
    ) );
}
add_action( 'init', 'wq_register_quote_cpt' );

// Custom Columns for Saved Quotes
function wq_quote_columns($columns) {
    $new_columns = array(
        'cb' => $columns['cb'],
        'title' => 'Quote Ref',
        'wq_client' => 'Client',
        'wq_project' => 'Project',
        'wq_total' => 'Total Price',
        'wq_status' => 'Status', // e.g., Saved, Ordered
        'date' => $columns['date'],
    );
    return $new_columns;
}
add_filter('manage_wq_quote_posts_columns', 'wq_quote_columns');

function wq_quote_custom_column($column, $post_id) {
    switch ($column) {
        case 'wq_client':
            echo esc_html(get_post_meta($post_id, '_wq_client_name', true));
            break;
        case 'wq_project':
            echo esc_html(get_post_meta($post_id, '_wq_project_name', true));
            break;
        case 'wq_total':
            $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
            echo esc_html($currency . get_post_meta($post_id, '_wq_grand_total', true));
            break;
        case 'wq_status':
            $status = get_post_status($post_id);
            $status_obj = get_post_status_object($status);
            $label = $status_obj ? $status_obj->label : $status;
            
            $color = '#777';
            if ($status === 'wq-saved') $color = '#46b450'; // Green
            if ($status === 'wq-in-cart') $color = '#ffb900'; // Orange
            if ($status === 'wq-purchased') $color = '#0073aa'; // Blue
            
            echo '<span style="color:white; background:'.$color.'; padding:3px 8px; border-radius:3px; font-weight:bold; font-size:0.9em;">' . esc_html($label) . '</span>';
            
            // If purchased, show Order Link
            $order_id = get_post_meta($post_id, '_wq_order_id', true);
            if ($order_id) {
                echo '<br><a href="'.admin_url('post.php?post='.$order_id.'&action=edit').'">Order #'.$order_id.'</a>';
            }
            break;
    }
}
add_action('manage_wq_quote_posts_custom_column', 'wq_quote_custom_column', 10, 2);

// Add Views (Tabs) for Quote Statuses
function wq_quote_register_views($views) {
    // Remove 'All' if we want custom ones, but usually we keep it.
    
    // Saved
    $count_saved = wp_count_posts('wq_quote')->{'wq-saved'};
    $class_saved = ( isset($_GET['post_status']) && $_GET['post_status'] == 'wq-saved' ) ? 'current' : '';
    $views['wq-saved'] = sprintf(
        '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
        admin_url('edit.php?post_type=wq_quote&post_status=wq-saved'),
        $class_saved,
        __('Users Saved Quotes', 'woo-quote-builder'),
        $count_saved
    );
    
    // In Cart
    $count_cart = wp_count_posts('wq_quote')->{'wq-in-cart'};
    $class_cart = ( isset($_GET['post_status']) && $_GET['post_status'] == 'wq-in-cart' ) ? 'current' : '';
    $views['wq-in-cart'] = sprintf(
        '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
        admin_url('edit.php?post_type=wq_quote&post_status=wq-in-cart'),
        $class_cart,
        __('Quotes in Cart', 'woo-quote-builder'),
        $count_cart
    );
    
    // Purchased
    $count_purchased = wp_count_posts('wq_quote')->{'wq-purchased'};
    $class_purchased = ( isset($_GET['post_status']) && $_GET['post_status'] == 'wq-purchased' ) ? 'current' : '';
    $views['wq-purchased'] = sprintf(
        '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
        admin_url('edit.php?post_type=wq_quote&post_status=wq-purchased'),
        $class_purchased,
        __('Purchased Quotes', 'woo-quote-builder'),
        $count_purchased
    );
    
    return $views;
}
add_filter('views_edit-wq_quote', 'wq_quote_register_views');


// Add Meta Box for Quote Details
function wq_quote_add_meta_boxes() {
    add_meta_box(
        'wq_quote_details',
        'Quote Details',
        'wq_quote_details_callback',
        'wq_quote',
        'normal',
        'high'
    );
    // Remove default Custom Fields meta box
    remove_meta_box( 'postcustom', 'wq_quote', 'normal' );
}
add_action( 'add_meta_boxes', 'wq_quote_add_meta_boxes' );

// Enqueue Scripts for Admin
function wq_quote_admin_scripts( $hook ) {
    global $post;
    if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
        $post_type = $post ? $post->post_type : (isset($_GET['post_type']) ? $_GET['post_type'] : 'post');
        if ( 'wq_quote' === $post_type ) {
            add_thickbox();
        }
    }
}
add_action( 'admin_enqueue_scripts', 'wq_quote_admin_scripts' );

function wq_quote_details_callback( $post ) {
    wp_nonce_field( 'wq_quote_details_nonce', 'wq_quote_details_nonce' );
    
    $data = get_post_meta( $post->ID, '_wq_quote_data', true );
    $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    
    // Fallback empty data
    if ( empty( $data ) ) {
        $data = array(
            'client' => '',
            'project' => '',
            'email' => '',
            'notes' => '',
            'items' => array()
        );
    }
    
    // CSS for Admin Table
    echo '<style>
        .wq-admin-table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #fff; border: 1px solid #e5e5e5; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .wq-admin-table th, .wq-admin-table td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        .wq-admin-table th { background: #f8f9fa; font-weight: 600; color: #32373c; border-bottom: 2px solid #ddd; }
        .wq-admin-table tr:last-child td { border-bottom: none; }
        .wq-admin-sub-row { background-color: #f9f9f9; color: #666; font-size: 0.9em; }
        .wq-input-edit { width: 100%; max-width: 100px; }
        .wq-input-text { width: 100%; }
        .wq-section-title { font-size: 1.1em; font-weight: bold; margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .wq-edge-block { margin-top: 5px; padding: 5px; background: #fff; border: 1px dashed #ccc; border-radius: 3px; }
        .wq-op-block { margin-top: 5px; padding: 5px; background: #fff; border: 1px dashed #ccc; border-radius: 3px; }
        .wq-cf-block { margin-top: 5px; padding: 5px; background: #f0f0f1; border-radius: 3px; font-size: 0.9em; }
    </style>';
    
    // --- Get Available Edge Services for Dropdown ---
    $edge_services = get_posts(array(
        'post_type' => 'wq_edge_service',
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
    
    // --- Get Available Operations for Dropdown ---
    $operations = get_option('wq_edge_operations', array());
    
    $edge_formulas = array(
        'l1' => get_option('wq_edge_formula_l1', ''),
        'l2' => get_option('wq_edge_formula_l2', ''),
        'w1' => get_option('wq_edge_formula_w1', ''),
        'w2' => get_option('wq_edge_formula_w2', ''),
    );

    // JS for updating edge selection
    ?>
    <script>
    jQuery(document).ready(function($) {
        var edgeFormulas = <?php echo wp_json_encode($edge_formulas); ?>;

        function calculateEdgeCost(side, price, lengthMm, qty) {
            if (!price || price === 0) return 0;
            
            var lengthM = lengthMm / 1000;
            
            // Simple formula replacement
            var sideKey = String(side || '').toLowerCase();
            var perSide = (edgeFormulas && edgeFormulas[sideKey]) ? String(edgeFormulas[sideKey]).trim() : '';
            var formula = perSide !== '' ? perSide : '{side_m} * {price} * {qty}';

            formula = formula.replace(/{side_m}/g, lengthM);
            formula = formula.replace(/{side_mm}/g, lengthMm);
            formula = formula.replace(/{length_m}/g, lengthM);
            formula = formula.replace(/{length_mm}/g, lengthMm);
            formula = formula.replace(/{width_m}/g, lengthM);
            formula = formula.replace(/{width_mm}/g, lengthMm);
            formula = formula.replace(/{price}/g, price);
            formula = formula.replace(/{qty}/g, qty);
            
            // Safe evaluation
            try {
                // Allow only numbers and math operators
                if (/[^0-9\.\+\-\*\/\(\)\s]/.test(formula)) {
                    console.error("Unsafe formula characters");
                    return 0;
                }
                return eval(formula);
            } catch (e) {
                console.error("Formula error", e);
                return 0;
            }
        }

        function updateLineTotal($row) {
             var qty = parseFloat($row.find('.wq-item-qty').val()) || 0;
             var boardUnitPrice = parseFloat($row.find('.wq-board-unit-price').val()) || 0;
             var boardTotal = boardUnitPrice * qty;
             
             var edgesTotal = 0;
             
             // Sum all edge inputs (allows manual overrides)
             $row.find('.wq-edge-cost-input').each(function() {
                 edgesTotal += parseFloat($(this).val()) || 0;
             });
             
             // Sum all op inputs
             $row.find('.wq-op-cost-input').each(function() {
                 edgesTotal += parseFloat($(this).val()) || 0;
             });
             
             var grandTotal = boardTotal + edgesTotal;
             $row.find('.wq-item-total').val(grandTotal.toFixed(2));
             
             updateGrandTotal();
         }

         function recalculateEdgeCosts($row) {
             var qty = parseFloat($row.find('.wq-item-qty').val()) || 0;
             
             $row.find('.wq-edge-block').each(function() {
                 var $block = $(this);
                 var side = $block.data('side');
                 
                 // Edge Cost
                 var $serviceSelect = $block.find('.wq-edge-select');
                 var servicePrice = parseFloat($serviceSelect.find(':selected').data('price')) || 0;
                 var lengthMm = parseFloat($block.data('length')) || 0;
                 
                 var edgeCost = 0;
                 if ($serviceSelect.val()) {
                     edgeCost = calculateEdgeCost(side, servicePrice, lengthMm, qty);
                 }
                 $block.find('.wq-edge-cost-input').val(edgeCost.toFixed(2));
                 
                 // Op Cost
                 var $opSelect = $block.find('.wq-op-select');
                 var opPrice = parseFloat($opSelect.find(':selected').data('price')) || 0;
                 var opType = $opSelect.find(':selected').data('type') || 'fixed';
                 var opCost = 0;
                 
                 if ($opSelect.val()) {
                     if (opType === 'meter') {
                          opCost = (lengthMm / 1000) * opPrice * qty;
                     } else {
                          opCost = opPrice * qty; 
                     }
                 }
                 $block.find('.wq-op-cost-input').val(opCost.toFixed(2));
             });
             
             updateLineTotal($row);
         }
        
        function updateGrandTotal() {
            var total = 0;
            $('.wq-item-total').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            $('input[name="wq_quote_grand_total"]').val(total.toFixed(2));
        }

        // Listeners
        
        // 1. Recalculate Costs on Dropdown or Qty Change
        $(document).on('change', '.wq-edge-select, .wq-op-select, .wq-item-qty', function() {
            var $row = $(this).closest('tr');
            recalculateEdgeCosts($row);
        });
        
        // 2. Update Totals on Manual Cost Change
        $(document).on('input change', '.wq-edge-cost-input, .wq-op-cost-input', function() {
            var $row = $(this).closest('tr');
            updateLineTotal($row);
        });
        
        // Update dimensions listener
        $(document).on('change', '.wq-dim-input', function() {
            var $row = $(this).closest('tr');
            var len = parseFloat($row.find('.wq-dim-l').val()) || 0;
            var wid = parseFloat($row.find('.wq-dim-w').val()) || 0;
            
            // Update data attributes for edges
            $row.find('.wq-edge-block[data-side="l1"], .wq-edge-block[data-side="l2"]').data('length', len);
            $row.find('.wq-edge-block[data-side="w1"], .wq-edge-block[data-side="w2"]').data('length', wid);
            
            recalculateEdgeCosts($row);
        });

        // Initialize Name fields
        $(document).on('change', '.wq-edge-select', function() {
            var name = $(this).find(':selected').text();
            var code = $(this).find(':selected').data('code');
            $(this).siblings('.wq-edge-name-hidden').val(name);
            $(this).siblings('.wq-edge-code-input').val(code);
        });
        
        $(document).on('change', '.wq-op-select', function() {
            var name = $(this).find(':selected').text();
            $(this).siblings('.wq-op-name-hidden').val(name);
        });

        // Add Row
        $('#wq-add-row-btn').click(function() {
            var $table = $('.wq-admin-table tbody');
            var template = $('#wq-row-template').html();
            var newIndex = new Date().getTime(); // Unique ID
            
            // Replace placeholder index
            template = template.replace(/{INDEX}/g, newIndex);
            
            $table.append(template);
        });
        
        // Remove Row
        $(document).on('click', '.wq-remove-row-btn', function() {
            if (confirm('Are you sure you want to remove this item?')) {
                $(this).closest('tr').remove();
                updateGrandTotal();
            }
        });
    });
    </script>
    <?php
    
    // --- Project Details ---
    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
    
    echo '<div>';
    echo '<p><label><strong>Client Name:</strong></label><br>';
    echo '<input type="text" name="wq_quote_client" value="' . esc_attr( isset($data['client']) ? $data['client'] : '' ) . '" class="large-text"></p>';
    
    echo '<p><label><strong>Project Name:</strong></label><br>';
    echo '<input type="text" name="wq_quote_project" value="' . esc_attr( isset($data['project']) ? $data['project'] : '' ) . '" class="large-text"></p>';
    echo '</div>';
    
    echo '<div>';
    echo '<p><label><strong>Client Email:</strong></label><br>';
    echo '<input type="email" name="wq_quote_email" value="' . esc_attr( isset($data['email']) ? $data['email'] : '' ) . '" class="large-text"></p>';
    
    echo '<p><label><strong>Project Notes:</strong></label><br>';
    echo '<textarea name="wq_quote_notes" rows="3" class="large-text">' . esc_textarea( isset($data['notes']) ? $data['notes'] : '' ) . '</textarea></p>';
    echo '</div>';
    
    echo '</div>';
    
    // --- Actions ---
    // echo '<div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">';
    // echo '<button type="button" class="button button-secondary" id="wq-admin-preview-btn"><span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span> Preview Quote (User View)</button>';
    // echo '<span class="description">Click to see how the user sees this quote.</span>';
    // echo '</div>';
    
    // --- Line Items ---
    echo '<h3 class="wq-section-title">Quote Items (Editable)</h3>';
    echo '<table class="wq-admin-table">';
    echo '<thead><tr>
            <th style="width: 25%;">Product / Label</th>
            <th style="width: 25%;">Dimensions (mm)</th>
            <th style="width: 10%;">Qty</th>
            <th style="width: 30%;">Extras (Edging & Operations)</th>
            <th style="width: 10%;">Line Total</th>
            <th style="width: 5%;">Action</th>
          </tr></thead><tbody>';
    
    if ( ! empty( $data['items'] ) && is_array($data['items']) ) {
        foreach ( $data['items'] as $index => $item ) {
            $price = isset($item['price']) ? $item['price'] : 0;
            // Robust Product Name Check
            $product_name = 'Unknown Product';
            if ( ! empty($item['product_name']) ) {
                $product_name = $item['product_name'];
            } elseif ( ! empty($item['product_id']) ) {
                $product_name = get_the_title($item['product_id']);
            }
            
            $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
            $length = isset($item['length']) ? $item['length'] : '';
            $width = isset($item['width']) ? $item['width'] : '';
            $thickness = isset($item['thickness']) ? $item['thickness'] : '';
            $qty = isset($item['qty']) ? $item['qty'] : 1;
            $label = isset($item['label']) ? $item['label'] : '';
            
            echo '<tr>';
            
            // Col 1: Product & Label
            echo '<td>';
            echo '<strong>Product:</strong><br>';
            echo '<input type="text" name="wq_items['.$index.'][product_name]" value="'.esc_attr($product_name).'" class="wq-input-text">';
            echo '<input type="hidden" name="wq_items['.$index.'][product_id]" value="'.esc_attr($product_id).'">';
            
            echo '<br><br><strong>Label / Note:</strong><br>';
            echo '<input type="text" name="wq_items['.$index.'][label]" value="'.esc_attr($label).'" class="wq-input-text" placeholder="Custom label...">';
            
            // Custom Fields
            if ( ! empty($item['custom_fields']) && is_array($item['custom_fields']) ) {
                echo '<div class="wq-cf-block">';
                echo '<strong>Custom Fields:</strong><br>';
                foreach ( $item['custom_fields'] as $cf_key => $cf_val ) {
                    // Use array key for input name to preserve structure
                    echo '<div style="margin-top:3px;">';
                    echo '<label>' . esc_html($cf_key) . ':</label> ';
                    echo '<input type="text" name="wq_items['.$index.'][custom_fields]['.esc_attr($cf_key).']" value="'.esc_attr($cf_val).'" style="width:100%; font-size:0.9em;">';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</td>';
            
            // Col 2: Dimensions
            echo '<td>';
            echo '<div style="display:flex; align-items:center; gap:5px; margin-bottom:5px;">';
            echo '<label style="width:20px;">L:</label> <input type="number" name="wq_items['.$index.'][length]" value="'.esc_attr($length).'" class="wq-input-edit wq-dim-input wq-dim-l"> mm';
            echo '</div>';
            echo '<div style="display:flex; align-items:center; gap:5px; margin-bottom:5px;">';
            echo '<label style="width:20px;">W:</label> <input type="number" name="wq_items['.$index.'][width]" value="'.esc_attr($width).'" class="wq-input-edit wq-dim-input wq-dim-w"> mm';
            echo '</div>';
            echo '<div style="display:flex; align-items:center; gap:5px;">';
            echo '<label style="width:20px;">T:</label> <input type="number" name="wq_items['.$index.'][thickness]" value="'.esc_attr($thickness).'" class="wq-input-edit"> mm';
            echo '</div>';
            echo '</td>';
            
            // Col 3: Qty
            echo '<td>';
            echo '<input type="number" name="wq_items['.$index.'][qty]" value="'.esc_attr($qty).'" style="width:60px;" class="wq-item-qty">';
            echo '</td>';
            
            // Col 4: Extras (Edging & Operations)
            echo '<td>';
            
            // Unified Edge Breakdown (Source of Truth)
            $edge_breakdown = !empty($item['edge_breakdown']) ? $item['edge_breakdown'] : (!empty($item['edges']) ? $item['edges'] : array());
            
            // Index edges by side for easier lookup
            $edges_by_side = array();
            $total_extras_cost = 0;
            if ( ! empty( $edge_breakdown ) ) {
                foreach ( $edge_breakdown as $e ) {
                    $e = (array) $e;
                    if (isset($e['side'])) {
                        $edges_by_side[strtolower($e['side'])] = $e;
                        $total_extras_cost += (isset($e['cost']) ? floatval($e['cost']) : 0);
                        $total_extras_cost += (isset($e['op_cost']) ? floatval($e['op_cost']) : 0);
                    }
                }
            }
            
            // Calculate Board Unit Price (Reverse Engineering)
            // Line Total = (Board Unit Price * Qty) + Extras Total
            // Board Unit Price = (Line Total - Extras Total) / Qty
            $line_total = floatval($price);
            $board_total = $line_total - $total_extras_cost;
            $board_unit_price = ($qty > 0) ? ($board_total / $qty) : 0;
            
            echo '<input type="hidden" class="wq-board-unit-price" value="'.esc_attr($board_unit_price).'">';
            
            $sides = array('l1', 'w1', 'l2', 'w2');
            
            foreach ( $sides as $side_key ) {
                $side_label = ucfirst($side_key);
                $edge_data = isset($edges_by_side[$side_key]) ? $edges_by_side[$side_key] : array();
                
                $name = isset($edge_data['name']) ? $edge_data['name'] : '';
                $cost = isset($edge_data['cost']) ? $edge_data['cost'] : 0;
                $code = isset($edge_data['code']) ? $edge_data['code'] : '';
                $service_id = isset($edge_data['service_id']) ? $edge_data['service_id'] : '';
                
                // If service_id is missing but name exists, try to find ID? 
                // We rely on name mostly for display if ID missing.
                
                $op_name = isset($edge_data['op_name']) ? $edge_data['op_name'] : '';
                $op_cost = isset($edge_data['op_cost']) ? $edge_data['op_cost'] : 0;
                $op_idx = isset($edge_data['op_idx']) ? $edge_data['op_idx'] : '';
                
                // Determine length for this side
                $side_len = (in_array($side_key, ['l1', 'l2'])) ? $length : $width;
                
                echo '<div class="wq-edge-block" data-side="'.esc_attr($side_key).'" data-length="'.esc_attr($side_len).'">';
                // Edge Info
                echo '<div><strong>'.$side_label.':</strong> ';
                
                // Dropdown for Edge Service
                // Note: We need a unique index for the array key to avoid overwriting if we just used side.
                // But typically array is indexed 0,1,2,3.
                // We can use side as key to enforce uniqueness: wq_items[x][edge_breakdown][l1]...
                // But save logic expects numerical array? 
                // The save logic: `foreach($item['edge_breakdown'] as $edge)` -> `$clean_item['edge_breakdown'][]`.
                // So keys don't matter as long as we iterate.
                // Let's use side as key in HTML name for easy access, PHP will treat it as associative array.
                // Update save logic to handle associative array.
                
                echo '<select name="wq_items['.$index.'][edge_breakdown]['.$side_key.'][service_id]" class="wq-edge-select" style="width:150px; font-size:0.9em;">';
                echo '<option value="" data-price="0" data-code="">- None -</option>';
                foreach ($edge_services as $service) {
                    $s_price = get_post_meta($service->ID, '_wq_edge_price', true);
                    $s_code = get_post_meta($service->ID, '_wq_edge_code', true);
                    
                    // Match by ID if available, else Name
                    $selected = '';
                    if ($service_id && $service_id == $service->ID) {
                        $selected = 'selected';
                    } elseif (!$service_id && $name === $service->post_title) {
                        $selected = 'selected';
                    }
                    
                    echo '<option value="'.$service->ID.'" data-price="'.esc_attr($s_price).'" data-code="'.esc_attr($s_code).'" '.$selected.'>'.esc_html($service->post_title).'</option>';
                }
                echo '</select> ';
                
                // Hidden Name Input
                echo '<input type="hidden" name="wq_items['.$index.'][edge_breakdown]['.$side_key.'][name]" value="'.esc_attr($name).'" class="wq-edge-name-hidden">';
                
                echo '$<input type="number" step="0.01" name="wq_items['.$index.'][edge_breakdown]['.$side_key.'][cost]" value="'.esc_attr($cost).'" class="wq-edge-cost-input" style="width:60px; font-size:0.9em;" title="Total Cost for this Side"></div>';
                
                // Operation Info (Inline)
                echo '<div class="wq-op-container" style="margin-top:4px; border-top:1px dashed #ddd; padding-top:4px;">';
                echo '<span style="font-size:0.8em; color:#666;">Op:</span> ';
                
                // Dropdown for Operation
                echo '<select name="wq_items['.$index.'][edge_breakdown]['.$side_key.'][op_idx]" class="wq-op-select" style="width:100px; font-size:0.9em;">';
                echo '<option value="" data-price="0" data-type="fixed">- None -</option>';
                if (!empty($operations)) {
                    foreach ($operations as $op_k => $op_val) {
                        $op_price = isset($op_val['price']) ? $op_val['price'] : 0;
                        $op_type = isset($op_val['type']) ? $op_val['type'] : 'fixed';
                        
                        // Match by Index/Key or Name
                        $op_selected = '';
                        if ($op_idx !== '' && $op_idx == $op_k) {
                            $op_selected = 'selected';
                        } elseif ($op_idx === '' && $op_name === $op_val['name']) {
                             $op_selected = 'selected';
                        }
                        
                        echo '<option value="'.$op_k.'" data-price="'.esc_attr($op_price).'" data-type="'.esc_attr($op_type).'" '.$op_selected.'>'.esc_html($op_val['name']).'</option>';
                    }
                }
                echo '</select> ';
                
                // Hidden Name Input
                echo '<input type="hidden" name="wq_items['.$index.'][edge_breakdown]['.$side_key.'][op_name]" value="'.esc_attr($op_name).'" class="wq-op-name-hidden">';
                
                echo '$<input type="number" step="0.01" name="wq_items['.$index.'][edge_breakdown]['.$side_key.'][op_cost]" value="'.esc_attr($op_cost).'" class="wq-op-cost-input" style="width:50px; font-size:0.9em;" placeholder="Cost">';
                echo '</div>';
                
                // Hidden Fields
                echo '<input type="hidden" name="wq_items['.$index.'][edge_breakdown]['.$side_key.'][side]" value="'.esc_attr($side_key).'">';
                echo '<input type="hidden" name="wq_items['.$index.'][edge_breakdown]['.$side_key.'][code]" value="'.esc_attr($code).'" class="wq-edge-code-input">';
                echo '</div>';
            }
            
            echo '</td>';
            
            // Col 5: Total
            echo '<td>';
            echo '<input type="number" step="0.01" name="wq_items['.$index.'][price]" value="'.esc_attr($price).'" class="wq-input-edit wq-item-total">';
            echo '<p class="description">Total Price</p>';
            echo '</td>';
            
            // Col 6: Action
            echo '<td>';
            echo '<button type="button" class="button wq-remove-row-btn" style="color: #a00; border-color: #a00;">&times;</button>';
            echo '</td>';
            
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">No items found in this quote.</td></tr>';
    }
    
    echo '</tbody></table>';
    
    // Add Row Button
    echo '<div style="margin-top: 10px;">';
    echo '<button type="button" class="button button-primary" id="wq-add-row-btn">+ Add Item</button>';
    echo '</div>';
    
    // Grand Total
    $grand_total = get_post_meta( $post->ID, '_wq_grand_total', true );
    echo '<div style="margin-top: 20px; text-align: right; font-size: 1.2em;">';
    echo '<strong>Grand Total: </strong> ' . $currency . ' <input type="number" step="0.01" name="wq_quote_grand_total" value="'.esc_attr($grand_total).'" style="width:100px; font-weight:bold;">';
    echo '</div>';
    
    // Template Row (Hidden)
    ?>
    <script type="text/template" id="wq-row-template">
        <tr>
            <td>
                <strong>Product:</strong><br>
                <input type="text" name="wq_items[{INDEX}][product_name]" value="" class="wq-input-text" placeholder="Product Name">
                <input type="hidden" name="wq_items[{INDEX}][product_id]" value="0">
                <br><br><strong>Label / Note:</strong><br>
                <input type="text" name="wq_items[{INDEX}][label]" value="" class="wq-input-text" placeholder="Custom label...">
            </td>
            <td>
                <div style="display:flex; align-items:center; gap:5px; margin-bottom:5px;">
                    <label style="width:20px;">L:</label> <input type="number" name="wq_items[{INDEX}][length]" value="" class="wq-input-edit wq-dim-input wq-dim-l"> mm
                </div>
                <div style="display:flex; align-items:center; gap:5px; margin-bottom:5px;">
                    <label style="width:20px;">W:</label> <input type="number" name="wq_items[{INDEX}][width]" value="" class="wq-input-edit wq-dim-input wq-dim-w"> mm
                </div>
                <div style="display:flex; align-items:center; gap:5px;">
                    <label style="width:20px;">T:</label> <input type="number" name="wq_items[{INDEX}][thickness]" value="" class="wq-input-edit"> mm
                </div>
            </td>
            <td>
                <input type="number" name="wq_items[{INDEX}][qty]" value="1" style="width:60px;" class="wq-item-qty">
            </td>
            <td>
                <input type="hidden" class="wq-board-unit-price" value="0">
                <?php
                $sides = array('l1', 'w1', 'l2', 'w2');
                foreach ( $sides as $side_key ) {
                    $side_label = ucfirst($side_key);
                    echo '<div class="wq-edge-block" data-side="'.esc_attr($side_key).'" data-length="0">';
                    echo '<div><strong>'.$side_label.':</strong> ';
                    
                    echo '<select name="wq_items[{INDEX}][edge_breakdown]['.$side_key.'][service_id]" class="wq-edge-select" style="width:150px; font-size:0.9em;">';
                    echo '<option value="" data-price="0" data-code="">- None -</option>';
                    foreach ($edge_services as $service) {
                        $s_price = get_post_meta($service->ID, '_wq_edge_price', true);
                        $s_code = get_post_meta($service->ID, '_wq_edge_code', true);
                        echo '<option value="'.$service->ID.'" data-price="'.esc_attr($s_price).'" data-code="'.esc_attr($s_code).'">'.esc_html($service->post_title).'</option>';
                    }
                    echo '</select> ';
                    
                    echo '<input type="hidden" name="wq_items[{INDEX}][edge_breakdown]['.$side_key.'][name]" value="" class="wq-edge-name-hidden">';
                    
                    echo '$<input type="number" step="0.01" name="wq_items[{INDEX}][edge_breakdown]['.$side_key.'][cost]" value="0" class="wq-edge-cost-input" style="width:60px; font-size:0.9em;" title="Total Cost for this Side"></div>';
                    
                    echo '<div class="wq-op-container" style="margin-top:4px; border-top:1px dashed #ddd; padding-top:4px;">';
                    echo '<span style="font-size:0.8em; color:#666;">Op:</span> ';
                    
                    echo '<select name="wq_items[{INDEX}][edge_breakdown]['.$side_key.'][op_idx]" class="wq-op-select" style="width:100px; font-size:0.9em;">';
                    echo '<option value="" data-price="0" data-type="fixed">- None -</option>';
                    if (!empty($operations)) {
                        foreach ($operations as $op_k => $op_val) {
                            $op_price = isset($op_val['price']) ? $op_val['price'] : 0;
                            $op_type = isset($op_val['type']) ? $op_val['type'] : 'fixed';
                            echo '<option value="'.$op_k.'" data-price="'.esc_attr($op_price).'" data-type="'.esc_attr($op_type).'">'.esc_html($op_val['name']).'</option>';
                        }
                    }
                    echo '</select> ';
                    
                    echo '<input type="hidden" name="wq_items[{INDEX}][edge_breakdown]['.$side_key.'][op_name]" value="" class="wq-op-name-hidden">';
                    
                    echo '$<input type="number" step="0.01" name="wq_items[{INDEX}][edge_breakdown]['.$side_key.'][op_cost]" value="0" class="wq-op-cost-input" style="width:50px; font-size:0.9em;" placeholder="Cost">';
                    echo '</div>';
                    
                    echo '<input type="hidden" name="wq_items[{INDEX}][edge_breakdown]['.$side_key.'][side]" value="'.esc_attr($side_key).'">';
                    echo '<input type="hidden" name="wq_items[{INDEX}][edge_breakdown]['.$side_key.'][code]" value="" class="wq-edge-code-input">';
                    echo '</div>';
                }
                ?>
            </td>
            <td>
                <input type="number" step="0.01" name="wq_items[{INDEX}][price]" value="0" class="wq-input-edit wq-item-total">
                <p class="description">Total Price</p>
            </td>
            <td>
                <button type="button" class="button wq-remove-row-btn" style="color: #a00; border-color: #a00;">&times;</button>
            </td>
        </tr>
    </script>
    <?php
    
    // Preview Modal Script (Removed as per user request)
    /*
    ?>
    <div id="wq-preview-modal" style="display:none;">
        ...
    </div>
    <script>
    ...
    </script>
    <?php
    */
}

// Admin Preview AJAX Handler
function wq_admin_preview_quote_ajax() {
    check_ajax_referer('wq_preview_nonce', 'nonce');
    $quote_id = intval($_POST['quote_id']);
    
    // Use the existing logic from woocommerce.php but adapted for admin output
    // We can include the frontend template file or replicate the loop.
    // Replicating is safer to avoid context issues.
    
    $data = get_post_meta( $quote_id, '_wq_quote_data', true );
    if ( empty($data) ) {
        wp_send_json_error('No data found');
    }
    
    $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    
    ob_start();
    ?>
    <style>
        .wq-preview-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-family: sans-serif; }
        .wq-preview-table th, .wq-preview-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .wq-preview-table th { background: #f2f2f2; }
        .wq-badge { padding: 3px 8px; border-radius: 3px; font-size: 0.85em; font-weight: bold; }
        .wq-badge-blue { background: #e7f1ff; color: #0c63e4; }
    </style>
    
    <div class="wq-quote-preview">
        <p><strong>Ref:</strong> <?php echo esc_html(isset($data['quote_ref']) ? $data['quote_ref'] : ''); ?></p>
        <p><strong>Project:</strong> <?php echo esc_html(isset($data['project']) ? $data['project'] : ''); ?></p>
        <p><strong>Client:</strong> <?php echo esc_html(isset($data['client']) ? $data['client'] : ''); ?></p>
        
        <table class="wq-preview-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Dimensions</th>
                    <th>Qty</th>
                    <th>Extras</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['items'] as $item): 
                    $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                    $product_name = isset($item['product_name']) ? $item['product_name'] : get_the_title($product_id);
                    $img_url = get_the_post_thumbnail_url($product_id, 'thumbnail');
                    if (!$img_url) $img_url = wc_placeholder_img_src();
                ?>
                <tr>
                    <td><img src="<?php echo esc_url($img_url); ?>" style="width:50px; height:50px; object-fit:cover;"></td>
                    <td>
                        <strong><?php echo esc_html($product_name); ?></strong>
                        <?php if(!empty($item['label'])): ?>
                            <br><small style="color:#666;">Note: <?php echo esc_html($item['label']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html($item['length']); ?> x <?php echo esc_html($item['width']); ?> x <?php echo esc_html($item['thickness']); ?> mm
                    </td>
                    <td><?php echo esc_html($item['qty']); ?></td>
                    <td>
                        <?php 
                        // Edging
                        if (!empty($item['edges'])) {
                            foreach($item['edges'] as $edge) {
                                echo '<div style="font-size:0.9em;">';
                                echo '<span class="wq-badge wq-badge-blue">' . esc_html($edge['side']) . '</span> ';
                                echo esc_html($edge['name']);
                                echo ' (' . $currency . esc_html($edge['cost']) . ')';
                                echo '</div>';
                            }
                        }
                        // Operations
                        if (!empty($item['operations'])) {
                            foreach($item['operations'] as $op) {
                                echo '<div style="font-size:0.9em; margin-top:2px;">';
                                echo '<strong>Op:</strong> ' . esc_html($op['name']);
                                echo ' (' . $currency . esc_html($op['cost']) . ')';
                                echo '</div>';
                            }
                        }
                        if (empty($item['edges']) && empty($item['operations'])) echo '-';
                        ?>
                    </td>
                    <td><?php echo $currency . esc_html($item['price']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; text-align: right; font-size: 1.2em; font-weight: bold;">
            Grand Total: <?php echo $currency . esc_html(get_post_meta($quote_id, '_wq_grand_total', true)); ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success($html);
}
add_action('wp_ajax_wq_admin_preview_quote', 'wq_admin_preview_quote_ajax');

// Save Admin Quote Edits
function wq_save_admin_quote_edits( $post_id ) {
    if ( ! isset( $_POST['wq_quote_details_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['wq_quote_details_nonce'], 'wq_quote_details_nonce' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Retrieve existing data
    $data = get_post_meta( $post_id, '_wq_quote_data', true );
    if ( empty( $data ) ) $data = array();

    // Update Top Level
    if ( isset( $_POST['wq_quote_client'] ) ) $data['client'] = sanitize_text_field( $_POST['wq_quote_client'] );
    if ( isset( $_POST['wq_quote_project'] ) ) $data['project'] = sanitize_text_field( $_POST['wq_quote_project'] );
    if ( isset( $_POST['wq_quote_email'] ) ) $data['email'] = sanitize_email( $_POST['wq_quote_email'] );
    if ( isset( $_POST['wq_quote_notes'] ) ) $data['notes'] = sanitize_textarea_field( $_POST['wq_quote_notes'] );
    
    // Update Items
    if ( isset( $_POST['wq_items'] ) && is_array( $_POST['wq_items'] ) ) {
        $new_items = array();
        foreach ( $_POST['wq_items'] as $item ) {
            // Sanitize item
            $clean_item = array(
                'product_id' => sanitize_text_field($item['product_id']),
                'product_name' => sanitize_text_field($item['product_name']),
                'length' => sanitize_text_field($item['length']),
                'width' => sanitize_text_field($item['width']),
                'thickness' => sanitize_text_field($item['thickness']),
                'qty' => sanitize_text_field($item['qty']),
                'price' => sanitize_text_field($item['price']),
                'label' => isset($item['label']) ? sanitize_text_field($item['label']) : '',
            );
            
            // Custom Fields
            if ( isset($item['custom_fields']) && is_array($item['custom_fields']) ) {
                $clean_item['custom_fields'] = array();
                foreach($item['custom_fields'] as $cf_k => $cf_v) {
                    $clean_item['custom_fields'][sanitize_text_field($cf_k)] = sanitize_text_field($cf_v);
                }
            } else {
                 $clean_item['custom_fields'] = array();
            }
            
            // Edging & Operations (Unified)
            if ( isset($item['edge_breakdown']) && is_array($item['edge_breakdown']) ) {
                $clean_item['edge_breakdown'] = array();
                foreach($item['edge_breakdown'] as $edge) {
                    // Filter out empty entries (no service and no operation)
                    $service_id = isset($edge['service_id']) ? sanitize_text_field($edge['service_id']) : '';
                    $op_idx = isset($edge['op_idx']) ? sanitize_text_field($edge['op_idx']) : '';
                    
                    // If both are empty, skip saving this side (unless there's a cost/name manually entered? 
                    // Let's rely on IDs being present for structured data, but also check name for legacy or manual entry support if needed.
                    // Actually, if IDs are empty, it means "None" selected.
                    if ( empty($service_id) && ($op_idx === '') ) {
                        continue;
                    }

                    $clean_item['edge_breakdown'][] = array(
                        'side' => isset($edge['side']) ? sanitize_text_field($edge['side']) : '',
                        'name' => isset($edge['name']) ? sanitize_text_field($edge['name']) : '',
                        'cost' => isset($edge['cost']) ? sanitize_text_field($edge['cost']) : 0,
                        'code' => isset($edge['code']) ? sanitize_text_field($edge['code']) : '',
                        'service_id' => $service_id,
                        'op_name' => isset($edge['op_name']) ? sanitize_text_field($edge['op_name']) : '',
                        'op_cost' => isset($edge['op_cost']) ? sanitize_text_field($edge['op_cost']) : 0,
                        'op_idx' => $op_idx,
                    );
                }
                $clean_item['edges'] = $clean_item['edge_breakdown']; 
            } else {
                $clean_item['edge_breakdown'] = array();
                $clean_item['edges'] = array();
            }
            
            // Clear separate operations array as it's now inside edge_breakdown
            $clean_item['operations'] = array();
            
            $new_items[] = $clean_item;
        }
        $data['items'] = $new_items;
    }

    // Save back
    update_post_meta( $post_id, '_wq_quote_data', $data );
    update_post_meta( $post_id, '_wq_client_name', $data['client'] );
    update_post_meta( $post_id, '_wq_project_name', $data['project'] );
    
    // Update Grand Total explicitly if edited
    if ( isset( $_POST['wq_quote_grand_total'] ) ) {
        update_post_meta( $post_id, '_wq_grand_total', sanitize_text_field( $_POST['wq_quote_grand_total'] ) );
    }
}
add_action( 'save_post_wq_quote', 'wq_save_admin_quote_edits' );

// Add Meta Boxes for Edge Service
function wq_add_edge_service_meta_boxes() {
    // Existing Details Box
    add_meta_box(
        'wq_edge_service_details',
        'Edge Service Details',
        'wq_edge_service_meta_box_callback',
        'wq_edge_service',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'wq_add_edge_service_meta_boxes' );

// Callback for the new Code Box (REMOVED - Using edit_form_after_title instead)

// Add Edge Code Field DIRECTLY AFTER TITLE for maximum visibility
// REMOVE THIS HOOK as it might not be supported well in this theme context
// add_action( 'edit_form_after_title', 'wq_edge_service_code_after_title' );

// Add Code Field to Publish Meta Box (Side)
function wq_edge_service_publish_meta_box() {
    global $post;
    if ( $post->post_type !== 'wq_edge_service' ) return;
    
    $edge_code = get_post_meta( $post->ID, '_wq_edge_code', true );
    if ( empty($edge_code) ) {
        $edge_code = strtoupper(substr(str_shuffle(md5(time())), 0, 5));
    }
    ?>
    <div class="misc-pub-section misc-pub-edge-code">
        <label style="font-weight:bold;">Edge Code:</label>
        <span style="font-weight:bold; margin-left: 5px;"><?php echo esc_html( $edge_code ); ?></span>
    </div>
    <?php
}
add_action( 'post_submitbox_misc_actions', 'wq_edge_service_publish_meta_box' );

function wq_edge_service_meta_box_callback( $post ) {
    wp_nonce_field( 'wq_edge_service_meta_box', 'wq_edge_service_meta_box_nonce' );

    // Get stored data
    $edge_code = get_post_meta( $post->ID, '_wq_edge_code', true );
    if ( empty($edge_code) ) {
        $edge_code = strtoupper(substr(str_shuffle(md5(time())), 0, 5));
    }

    // Force display of this field by outputting it directly without conditional logic
    ?>
    <!-- IMPORTANT: Edge Service Code Field -->
    <div style="background: #fff; border: 2px solid #007cba; padding: 15px; margin-bottom: 20px; display: block !important; visibility: visible !important;">
        <label for="wq_edge_code_input" style="font-size: 1.2em; font-weight: bold; color: #333; display: block; margin-bottom: 5px;">Edge Service Code:</label>
        <input type="text" name="wq_edge_code" id="wq_edge_code_input" value="<?php echo esc_attr( $edge_code ); ?>" maxlength="5" style="width: 100%; max-width: 200px; font-size: 1.5em; text-transform: uppercase; font-weight: bold; text-align: center; border: 1px solid #ccc; height: 40px; line-height: 40px;">
        <p class="description" style="margin-top: 5px;">Unique 5-character alphanumeric code. <strong>You can update this code here.</strong></p>
    </div>
    <!-- END Edge Service Code Field -->

    <?php
    // Get Profiles (Visual Types)
    $profiles = get_posts(array(
        'post_type' => 'wq_edge_profile',
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
    
    // Get stored data
    $profile_config = get_post_meta( $post->ID, '_wq_profile_config', true );
    if (!is_array($profile_config)) $profile_config = array();
    
    $price = get_post_meta( $post->ID, '_wq_edge_price', true );
    
    // Get or Generate Edge Code (Also displayed in side meta box, but kept here for visibility)
    $edge_code = get_post_meta( $post->ID, '_wq_edge_code', true );
    if ( empty($edge_code) ) {
        // Generate 5-char alphanumeric code
        $edge_code = strtoupper(substr(str_shuffle(md5(time())), 0, 5));
    }
    
    // Get Operations
    $operations = get_option('wq_edge_operations', array());

    ?>
    <p><strong>Supported Edge Profiles & Sides:</strong></p>
    <p class="description">Select which Edge Profiles this service belongs to, and which sides are supported for each profile.</p>
    
    <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px;">
        <?php if ( empty($profiles) ) : ?>
            <p>No Edge Profiles found. Please add some Edge Profiles first.</p>
        <?php else : ?>
            <?php foreach ($profiles as $p) : 
                $p_id = $p->ID;
                $is_active = isset($profile_config[$p_id]);
                $sides = isset($profile_config[$p_id]['sides']) ? $profile_config[$p_id]['sides'] : array();
            ?>
                <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <label style="font-weight: bold; font-size: 1.1em;">
                        <input type="checkbox" name="wq_profile_active[<?php echo $p_id; ?>]" value="yes" <?php checked($is_active); ?>>
                        <?php echo esc_html($p->post_title); ?>
                    </label>
                    <div style="margin-left: 25px; margin-top: 5px;">
                        <p style="margin: 5px 0;"><strong>Supported Sides:</strong></p>
                        <label><input type="checkbox" name="wq_profile_sides[<?php echo $p_id; ?>][]" value="l1" <?php checked(in_array('l1', $sides)); ?>> L1</label>
                        <label><input type="checkbox" name="wq_profile_sides[<?php echo $p_id; ?>][]" value="w1" <?php checked(in_array('w1', $sides)); ?>> W1</label>
                        <label><input type="checkbox" name="wq_profile_sides[<?php echo $p_id; ?>][]" value="l2" <?php checked(in_array('l2', $sides)); ?>> L2</label>
                        <label><input type="checkbox" name="wq_profile_sides[<?php echo $p_id; ?>][]" value="w2" <?php checked(in_array('w2', $sides)); ?>> W2</label>
                        
                        <?php
                        $unit_label = get_option('wq_edge_unit_label', 'M');
                        $stored_prices = isset($profile_config[$p_id]['prices']) && is_array($profile_config[$p_id]['prices']) ? $profile_config[$p_id]['prices'] : array();
                        $all_price = '';
                        if (isset($stored_prices['all']) && $stored_prices['all'] !== '') {
                            $all_price = (string) $stored_prices['all'];
                        } else {
                            $l1p = isset($stored_prices['l1']) ? (string) $stored_prices['l1'] : '';
                            $w1p = isset($stored_prices['w1']) ? (string) $stored_prices['w1'] : '';
                            $l2p = isset($stored_prices['l2']) ? (string) $stored_prices['l2'] : '';
                            $w2p = isset($stored_prices['w2']) ? (string) $stored_prices['w2'] : '';
                            $non_empty = array_values(array_filter(array($l1p, $w1p, $l2p, $w2p), function($v) { return $v !== ''; }));
                            if (!empty($non_empty)) {
                                $all_equal = ($l1p !== '' && $l1p === $w1p && $l1p === $l2p && $l1p === $w2p);
                                $all_price = $all_equal ? $l1p : $non_empty[0];
                            }
                        }
                        ?>
                        <p style="margin: 5px 0;"><strong>Price per <?php echo esc_html($unit_label); ?> (L1/L2/W1/W2):</strong></p>
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <div>
                                <label>All Sides Price:</label><br>
                                <input type="number" step="0.01" name="wq_profile_prices[<?php echo $p_id; ?>][all]" value="<?php echo esc_attr($all_price); ?>" placeholder="0.00" style="width: 120px;">
                            </div>
                        </div>
                        
                        <?php 
                        // Always show Operations section, even if $operations is empty, so user knows where it would be
                        // But if empty, show a message
                        ?>
                        <p style="margin: 5px 0;"><strong>Operations:</strong></p>
                        <?php if (empty($operations)) : ?>
                            <p class="description">No operations defined. Go to Quote Builder > Operations Settings to add them.</p>
                        <?php else : ?>
                            <div style="display: flex; gap: 10px;">
                                <?php foreach(['l1', 'w1', 'l2', 'w2'] as $side): ?>
                                <div>
                                    <label><?php echo strtoupper($side); ?> Op:</label><br>
                                    <select name="wq_profile_operations[<?php echo $p_id; ?>][<?php echo $side; ?>]" style="width: 80px;">
                                        <option value="">-- None --</option>
                                        <?php 
                                        $current_op = isset($profile_config[$p_id]['operations'][$side]) ? $profile_config[$p_id]['operations'][$side] : '';
                                        foreach($operations as $op_idx => $op) {
                                            echo '<option value="'.esc_attr($op_idx).'" '.selected($current_op, $op_idx, false).'>'.esc_html($op['name']).'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="display:none;">
    <p>
        <label for="wq_edge_price"><strong>Default Price (Fallback if no profile price set):</strong></label><br>
        <input type="number" step="0.01" name="wq_edge_price" id="wq_edge_price" value="<?php echo esc_attr( $price ); ?>" style="width:100%;">
    </p>
    </div>
    <?php
}

// Save Edge Code specifically (Standalone fallback)
function wq_save_edge_service_code_standalone( $post_id ) {
    // We don't check for nonce here because we want this to save regardless of which meta box is active,
    // assuming the user has capability to edit the post.
    // WordPress 'save_post' hook already implies user has permission to save/update this post.
    // However, to be safe, we should check for autosave.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    // Save Code
    // First, check for the main meta box field (no specific nonce for this field, it's covered by meta box nonce)
    if ( isset( $_POST['wq_edge_code'] ) ) {
        $code = sanitize_text_field( $_POST['wq_edge_code'] );
        // Ensure uppercase and max 5 chars just in case
        $code = strtoupper(substr($code, 0, 5));
        update_post_meta( $post_id, '_wq_edge_code', $code );
    }
}
// Removed independent action to avoid confusion - we'll rely on the main save function or just this one if we keep it simple.
// Actually, let's just make sure this function runs safely.
add_action( 'save_post', 'wq_save_edge_service_code_standalone' );

// Save Meta Box Data
function wq_save_edge_service_meta( $post_id ) {
    // This handles the main details box. 
    // If this nonce is missing, we skip saving details, but the code above might have saved the code.
    if ( ! isset( $_POST['wq_edge_service_meta_box_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['wq_edge_service_meta_box_nonce'], 'wq_edge_service_meta_box' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Process Profile Configuration
    $profile_config = array();
    
    if ( isset( $_POST['wq_profile_active'] ) && is_array( $_POST['wq_profile_active'] ) ) {
        foreach ( $_POST['wq_profile_active'] as $p_id => $val ) {
            // If profile is checked active
            $sides = array();
            if ( isset( $_POST['wq_profile_sides'][$p_id] ) && is_array( $_POST['wq_profile_sides'][$p_id] ) ) {
                $sides = array_map( 'sanitize_text_field', $_POST['wq_profile_sides'][$p_id] );
            }
            
            $prices = array();
            if ( isset( $_POST['wq_profile_prices'][$p_id] ) && is_array( $_POST['wq_profile_prices'][$p_id] ) ) {
                $posted_prices = array_map( 'sanitize_text_field', $_POST['wq_profile_prices'][$p_id] );
                if ( isset($posted_prices['all']) && $posted_prices['all'] !== '' ) {
                    $all = $posted_prices['all'];
                    $prices = array(
                        'l1' => $all,
                        'w1' => $all,
                        'l2' => $all,
                        'w2' => $all,
                        'all' => $all
                    );
                } else {
                    foreach ( array('l1', 'w1', 'l2', 'w2') as $k ) {
                        if ( isset($posted_prices[$k]) && $posted_prices[$k] !== '' ) {
                            $prices[$k] = $posted_prices[$k];
                        }
                    }
                }
            }
            
            $operations = array();
            if ( isset( $_POST['wq_profile_operations'][$p_id] ) && is_array( $_POST['wq_profile_operations'][$p_id] ) ) {
                $operations = array_map( 'sanitize_text_field', $_POST['wq_profile_operations'][$p_id] );
            }
            
            $profile_config[$p_id] = array(
                'active' => true,
                'sides' => $sides,
                'prices' => $prices,
                'operations' => $operations
            );
        }
    }
    
    update_post_meta( $post_id, '_wq_profile_config', $profile_config );

    // Save Price (Global - maybe deprecated if per-profile is used, but keep for fallback?)
    if ( isset( $_POST['wq_edge_price'] ) ) {
        update_post_meta( $post_id, '_wq_edge_price', sanitize_text_field( $_POST['wq_edge_price'] ) );
    }
    
    // Save Edge Code
    if ( isset( $_POST['wq_edge_code'] ) ) {
        $code = sanitize_text_field( $_POST['wq_edge_code'] );
        // Ensure uppercase and max 5 chars just in case
        $code = strtoupper(substr($code, 0, 5));
        update_post_meta( $post_id, '_wq_edge_code', $code );
    }
}
add_action( 'save_post', 'wq_save_edge_service_meta' );

function wq_save_quote_data( $post_id, $data ) {
    if ( ! is_wp_error( $post_id ) ) {
        // Save Meta Data
        update_post_meta( $post_id, '_wq_client_name', sanitize_text_field( $data['client'] ) );
        update_post_meta( $post_id, '_wq_project_name', sanitize_text_field( $data['project'] ) );
        // Email optional
        if ( ! empty( $data['email'] ) ) {
             update_post_meta( $post_id, '_wq_client_email', sanitize_email( $data['email'] ) );
        }
        update_post_meta( $post_id, '_wq_notes', sanitize_textarea_field( $data['notes'] ) );
        
        // Save Full Data JSON
        update_post_meta( $post_id, '_wq_quote_data', $data );
    }
}
