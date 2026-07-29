<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Submit Quote Handler
function wq_builder_submit_quote() {
	check_ajax_referer( 'wq_builder_nonce', 'nonce' );

    // Verify user can submit (basic check, can be expanded)
    // if ( ! is_user_logged_in() ) { ... }

	$quote_data = isset( $_POST['quote_data'] ) ? $_POST['quote_data'] : array();
    
    // Check if quote data is empty. Email is now optional.
    if ( empty( $quote_data ) ) {
        wp_send_json_error( array( 'message' => 'Invalid data provided.' ) );
    }
    
    // 1. Save Quote (Create/Update Custom Post Type)
    // Default to 'wq-saved' status for explicitly saved quotes
    $quote_id = wq_builder_create_quote_post( $quote_data, 'wq-saved' );

    if ( $quote_id ) {
        // Send Email Notifications
        wq_builder_send_quote_email( $quote_data, $quote_id );

        // Stop PDF Generation and Email for now (User request: "dont want the client to download the pdf at all")
        // Just return success with quote ID
        
        wp_send_json_success( array( 
            'message' => 'Quote saved to wishlist successfully.',
            'quote_id' => $quote_id
        ) );
    } else {
        wp_send_json_error( array( 'message' => 'Failed to save quote.' ) );
    }
}
add_action( 'wp_ajax_wq_builder_submit_quote', 'wq_builder_submit_quote' );
add_action( 'wp_ajax_nopriv_wq_builder_submit_quote', 'wq_builder_submit_quote' );

// 2. Get Quote Data (For Edit)
function wq_builder_get_quote_data() {
    check_ajax_referer( 'wq_builder_nonce', 'nonce' );
    
    $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
    
    if ( ! $quote_id ) {
        wp_send_json_error( array( 'message' => 'Invalid Quote ID.' ) );
    }
    
    // Check ownership
    $quote = get_post($quote_id);
    if ( ! $quote || $quote->post_type !== 'wq_quote' ) {
         wp_send_json_error( array( 'message' => 'Quote not found.' ) );
    }
    
    // Allow admin or owner
    $user_id = get_current_user_id();
    if ( $quote->post_author != $user_id && ! current_user_can('manage_options') ) {
         wp_send_json_error( array( 'message' => 'Permission denied.' ) );
    }
    
    $quote_data = get_post_meta( $quote_id, '_wq_quote_data', true );
    
    if ( ! $quote_data ) {
        wp_send_json_error( array( 'message' => 'Quote data missing.' ) );
    }
    
    wp_send_json_success( $quote_data );
}
add_action( 'wp_ajax_wq_get_quote_data', 'wq_builder_get_quote_data' );
add_action( 'wp_ajax_nopriv_wq_get_quote_data', 'wq_builder_get_quote_data' );

// 3. Add to Basket (AJAX)
function wq_builder_add_to_basket() {
    check_ajax_referer( 'wq_builder_nonce', 'nonce' );

    // Ensure WC Cart is loaded
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        } elseif ( defined( 'WC_ABSPATH' ) ) {
            include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            include_once WC_ABSPATH . 'includes/class-wc-cart.php';
            if ( function_exists( 'wc_load_cart' ) ) {
                wc_load_cart();
            }
        }
    }
    
    if ( ! isset( WC()->cart ) ) {
         wp_send_json_error( array( 'message' => 'WooCommerce Cart not available.' ) );
    }

    // Ensure Session exists
    if ( ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
    }

    $quote_data = isset( $_POST['quote_data'] ) ? $_POST['quote_data'] : array();
    
    // DEBUG: Log received data (Full Dump)
    error_log('WQ_DEBUG_PAYLOAD: ' . print_r($_POST, true));
    
    if ( empty( $quote_data ) || empty( $quote_data['items'] ) ) {
        wp_send_json_error( array( 'message' => 'Invalid data provided or empty items.' ) );
    }
    
    // 1. Create a Quote Post with status 'wq-in-cart'
    // This ensures it appears in the Admin "Quotes in Cart" tab
    $quote_id = wq_builder_create_quote_post( $quote_data, 'wq-in-cart' );
    
    // Clear cart? Maybe optional. For now, just add.
    // WC()->cart->empty_cart(); 

    $added_count = 0;
    
    // Get Placeholder Product (Guarantees add_to_cart success)
    $placeholder_id = function_exists('wq_get_placeholder_product_id') ? wq_get_placeholder_product_id() : 0;
    
    if ( ! $placeholder_id ) {
        error_log('WQ Builder Error: Placeholder Product ID not found or could not be created.');
        wp_send_json_error( array( 'message' => 'Configuration Error: Placeholder product missing.' ) );
    }

    foreach ( $quote_data['items'] as $item ) {
        // Use Placeholder ID if available, otherwise fallback to item ID (riskier)
        $add_product_id = ($placeholder_id > 0) ? $placeholder_id : intval( $item['product_id'] );
        $qty = intval( $item['qty'] );
        
        if ( $add_product_id > 0 && $qty > 0 ) {
            // Prepare Custom Data for Cart Item
            // 1. Ensure Edge Breakdown is robust
            $edge_breakdown = array();
            if ( isset($item['edges']) && is_array($item['edges']) ) {
                $edge_breakdown = $item['edges']; // JS sends 'edges' now in new format? 
                // Wait, JS sends 'edges' which IS the breakdown array in new logic.
                // In previous PHP code we accessed $item['edge_breakdown']? 
                // Let's check JS: `items.push({ ... edges: edgeBreakdown ... })`
                // So the key is 'edges'.
            } elseif ( isset($item['edge_breakdown']) && is_array($item['edge_breakdown']) ) {
                $edge_breakdown = $item['edge_breakdown'];
            }
            
            // 2. Ensure Costs are Floats
            foreach ($edge_breakdown as &$edge) {
                if (isset($edge['cost'])) $edge['cost'] = floatval($edge['cost']);
                if (isset($edge['op_cost'])) $edge['op_cost'] = floatval($edge['op_cost']);
            }
            unset($edge);

            // 3. Build Cart Item Data
            $item_operations = array();
            if ( isset($item['operations']) && is_array($item['operations']) ) {
                $item_operations = $item['operations'];
            } elseif ( isset($item['operation']) && ! empty($item['operation']) ) {
                $item_operations = array( $item['operation'] );
            }

            $cart_item_data = array(
                'wq_quote_id' => $quote_id, // Link to the 'In Cart' quote post
                'wq_quote_ref' => isset($quote_data['quote_ref']) ? $quote_data['quote_ref'] : '',
                'wq_project' => isset($quote_data['project']) ? $quote_data['project'] : '',
                'wq_keep_offcuts' => ! empty( $quote_data['keep_offcuts'] ) ? 1 : 0,
                'wq_original_product_id' => $item['product_id'], // Store real product ID
                'wq_product_name' => isset($item['product_name']) ? $item['product_name'] : get_the_title($item['product_id']),
                'wq_dimensions' => array(
                    'length' => $item['length'],
                    'width' => $item['width'],
                    'thickness' => $item['thickness']
                ),
                'wq_custom_fields' => isset($item['custom_fields']) ? $item['custom_fields'] : array(),
                'wq_operations' => $item_operations,
                'wq_operation' => ! empty($item_operations) ? $item_operations[0] : null,
                'wq_edge_breakdown' => $edge_breakdown, // Use unified key
                'wq_pricing' => array(
                    'base_price' => $item['price'], // Line Total
                    'unit_price' => floatval($item['price']) / max(1, $qty)
                ),
                'wq_unique_key' => md5( microtime() . rand() ) // Prevent merging
            );
            
            // Add to Cart
            try {
                // Verify Product before adding
                $product_obj = wc_get_product($add_product_id);
                if (!$product_obj) {
                    throw new Exception("Product object creation failed for ID $add_product_id");
                }
                if (!$product_obj->is_purchasable()) {
                     throw new Exception("Product is not purchasable. Status: " . $product_obj->get_status() . ", Price: " . $product_obj->get_price());
                }
                
                $cart_item_key = WC()->cart->add_to_cart( $add_product_id, $qty, 0, array(), $cart_item_data );
            } catch (Exception $e) {
                error_log('WQ Builder Add to Cart Exception: ' . $e->getMessage());
                $cart_item_key = false;
                $last_error = $e->getMessage();
            }
            
            if ( $cart_item_key ) {
                $added_count++;
            } else {
                if (empty($last_error)) $last_error = "Unknown error (add_to_cart returned false). Product ID: $add_product_id";
                error_log("WQ Builder Error: $last_error");
            }
        }
    }
    
    if ( $added_count > 0 ) {
        wp_send_json_success( array( 
            'message' => 'Quote added to basket successfully.',
            'redirect_url' => wc_get_cart_url()
        ) );
    } else {
        // Retrieve WC Errors
        $errors = wc_get_notices( 'error' );
        $error_msg = 'Failed to add items to basket.';
        
        if ( ! empty( $errors ) ) {
            $msg_list = array();
            foreach ( $errors as $e ) {
                $msg_list[] = strip_tags( $e['notice'] );
            }
            $error_msg = implode( ' | ', $msg_list );
            wc_clear_notices(); // Clear after reading
        } else {
            // Append internal error if no WC notice
            if (!empty($last_error)) {
                $error_msg .= " Debug: " . $last_error;
            }
            
            // EMERGENCY FALLBACK: Manually Add to Session Array (Bypass Cart Object Wrapper?)
            // If standard add_to_cart fails, we can manipulate the session directly?
            // No, that's dangerous.
            
            // But let's check if the product actually exists.
            $check = wc_get_product($add_product_id);
            if (!$check) $error_msg .= " (Product ID $add_product_id does not exist in DB)";
            else $error_msg .= " (Product Exists: " . $check->get_name() . " | Status: " . $check->get_status() . ")";
        }
        
        error_log("WQ Builder Add to Cart Failed. Errors: " . $error_msg);
        wp_send_json_error( array( 'message' => $error_msg ) );
    }
}
add_action( 'wp_ajax_wq_builder_add_to_basket', 'wq_builder_add_to_basket' );
add_action( 'wp_ajax_nopriv_wq_builder_add_to_basket', 'wq_builder_add_to_basket' );

// 4. Generate PDF (AJAX)
function wq_builder_ajax_generate_pdf() {
    check_ajax_referer( 'wq_builder_nonce', 'nonce' );

    $quote_data = isset( $_POST['quote_data'] ) ? $_POST['quote_data'] : array();
    
    if ( empty( $quote_data ) ) {
        wp_send_json_error( array( 'message' => 'Invalid data provided.' ) );
    }

    // Save Quote to Admin Only (Create Post but decouple from user if not explicitly saving)
    // Actually, "Saved Quotes" are CPTs. If we create one, it exists.
    // To hide it from "My Account", we just need to ensure the user isn't set as author OR we add a meta flag 'is_temporary' or 'is_pdf_generated'.
    // Or we simply DON'T assign the current user ID if they are generating PDF, effectively making it an "Admin/Guest" quote.
    
    // HOWEVER, the user asked: "saved in the admin side only , but not shown in the my account section"
    // My Account section queries by `post_author = current_user_id`.
    // So if we set `post_author = 0` (or admin ID), it won't show in My Account.
    
    // But if the user IS logged in, we usually want to track it.
    // Let's add a meta field `_wq_is_pdf_only` and filter the My Account query.
    
    // Create Quote Post (Force Author to 0 to hide from My Account, OR use Meta Flag)
    // Using Meta Flag is safer if we want to convert it later.
    
    $quote_id = wq_builder_create_quote_post( $quote_data, true ); // Pass true for 'pdf_only'

    $pdf_url = wq_builder_generate_pdf( $quote_data );

    if ( $pdf_url ) {
        wp_send_json_success( array( 
            'message' => 'PDF generated successfully.',
            'pdf_url' => $pdf_url
        ) );
    } else {
        wp_send_json_error( array( 'message' => 'Failed to generate PDF. TCPDF might be missing.' ) );
    }
}
add_action( 'wp_ajax_wq_builder_generate_pdf', 'wq_builder_ajax_generate_pdf' );
add_action( 'wp_ajax_nopriv_wq_builder_generate_pdf', 'wq_builder_ajax_generate_pdf' );

// Helper to create Quote Post
function wq_builder_create_quote_post( $data, $status_or_pdf = 'wq-saved' ) {
    $title = isset($data['quote_ref']) ? $data['quote_ref'] : 'QUOTE-' . time();
    $user_id = get_current_user_id();
    
    // Determine Status
    // Handle legacy boolean argument (if true, it was 'pdf_only')
    $status = 'wq-saved';
    $is_pdf_only = false;
    
    if ( $status_or_pdf === true ) {
        $status = 'publish'; // or 'private'? Legacy behavior.
        $is_pdf_only = true;
    } elseif ( is_string($status_or_pdf) ) {
        $status = $status_or_pdf;
    }
    
    // Check if quote already exists for this user/session
    // We search by Title (Ref)
    $args = array(
        'post_type' => 'wq_quote',
        'title' => $title,
        'posts_per_page' => 1,
        'post_status' => 'any', // Include all statuses
    );
    
    $existing_quote = get_posts($args);
    
    $post_id = 0;
    
    if ( ! empty($existing_quote) ) {
        // Update existing
        $post_id = $existing_quote[0]->ID;
        $post_arr = array(
            'ID'           => $post_id,
            'post_title'   => $title,
            'post_type'    => 'wq_quote',
            'post_status'  => $status, // Update status
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        );
        wp_update_post( $post_arr );
    } else {
        // Create new
        $post_arr = array(
            'post_title'   => $title,
            'post_type'    => 'wq_quote',
            'post_status'  => $status,
            'post_author'  => $user_id, // Default to user
        );
        
        $post_id = wp_insert_post( $post_arr );
    }
    
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
        
        // Calculate Total
        $total = 0;
        if ( ! empty( $data['items'] ) ) {
            foreach ( $data['items'] as $item ) {
                $price = isset($item['price']) ? floatval($item['price']) : 0;
                
                // Note: $item['price'] from JS (since recent update) includes Edge Cost.
                // JS: const totalPrice = basePrice + edgeCost;
                // So we do NOT need to add edge cost again if we trust JS.
                
                // However, for older quotes, price might be base only.
                // We can't easily distinguish without flags.
                // But since we are calculating Grand Total for NEW quotes here, we should trust the input price.
                
                // If we add edge cost, we double count for new logic.
                // Let's assume input 'price' is the LINE TOTAL.
                
                // Compatibility Check:
                // If price seems to match base price logic? Hard to know.
                // Let's check if we have explicit base price? No.
                
                // DECISION: Trust $item['price'] as the total line price.
                // This aligns with standard cart logic where price is final.
                
                $total += $price;
                
                /* 
                // Legacy Logic (Disabled to prevent double counting with new JS)
                $edge_cost = 0;
                if (isset($item['edges']) && is_array($item['edges'])) {
                     foreach ($item['edges'] as $edge) {
                         $edge_cost += floatval($edge['cost']);
                     }
                }
                $total += $edge_cost; 
                */
            }
        }
        
        $grand_total = $total;
        update_post_meta( $post_id, '_wq_grand_total', $grand_total );
        
        // Handle PDF Only / Saved Status
        if ( $is_pdf_only ) {
            // Mark as PDF Generated (Hidden from My Account)
            // But if it was ALREADY saved (not pdf only), don't overwrite that.
            if ( ! get_post_meta( $post_id, '_wq_is_saved_by_user', true ) ) {
                update_post_meta( $post_id, '_wq_is_pdf_only', '1' );
            }
        } else {
            // Explicit Save
            update_post_meta( $post_id, '_wq_is_saved_by_user', '1' );
            delete_post_meta( $post_id, '_wq_is_pdf_only' ); // Remove hidden flag
        }
        
        return $post_id;
    }
    return 0;
}

// Generate PDF using TCPDF
function wq_builder_generate_pdf( $data ) {
    // Check if TCPDF class exists
    if ( ! class_exists( 'TCPDF' ) ) {
        return false;
    }

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Woo Quote Builder');
    $pdf->SetTitle('Quote - ' . $data['project']);
    $pdf->SetSubject('Quote Request');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Content
    $header_content = get_option( 'wq_pdf_header_content', '<h1>Quote Request</h1>' );
    $body_content = get_option( 'wq_pdf_body_content', '<p><strong>Project:</strong> {project_name}</p><p><strong>Client:</strong> {client_name}</p><p><strong>Date:</strong> {quote_date}</p>' );
    $footer_content = get_option( 'wq_pdf_footer_content', '<p>Thank you for your business!</p>' );
    $custom_css = get_option( 'wq_pdf_custom_css', '' );

    $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';

    // Placeholders
    $keep_offcuts = ! empty( $data['keep_offcuts'] );
    $keep_offcuts_line = $keep_offcuts ? '<p><strong>Keep Offcuts:</strong> Yes</p>' : '';
    $placeholders = array(
        '{client_name}' => esc_html( $data['client'] ),
        '{project_name}' => esc_html( $data['project'] ),
        '{client_email}' => esc_html( $data['email'] ),
        '{quote_ref}' => isset($data['quote_ref']) ? esc_html($data['quote_ref']) : 'N/A',
        '{quote_date}' => date('Y-m-d H:i:s'),
        '{quote_notes}' => ! empty( $data['notes'] ) ? nl2br( esc_html( $data['notes'] ) ) : '',
        '{keep_offcuts}' => $keep_offcuts ? 'Yes' : 'No',
        '{keep_offcuts_line}' => $keep_offcuts_line,
    );
    
    $grand_total = 0;
    $board_lines = array();
    $edging_agg = array();
    $ops_agg = array();

    if ( ! empty( $data['items'] ) ) {
        foreach ( $data['items'] as $item ) {
            $qty = isset($item['qty']) ? intval($item['qty']) : 0;
            $line_total = isset($item['price']) ? floatval($item['price']) : 0;
            $grand_total += $line_total;

            $length_mm = isset($item['length']) ? floatval($item['length']) : 0;
            $width_mm = isset($item['width']) ? floatval($item['width']) : 0;
            $thickness = isset($item['thickness']) ? $item['thickness'] : '';
            $dims = $length_mm . ' x ' . $width_mm;
            if ($thickness !== '') $dims .= ' x ' . $thickness . ' mm';

            $product_name = isset($item['product_name']) ? $item['product_name'] : '';
            $label = '';
            if ( isset($item['custom_fields']) && is_array($item['custom_fields']) && !empty($item['custom_fields']['label']) ) {
                $label = $item['custom_fields']['label'];
            }

            $edges_data = array();
            if ( isset($item['edge_breakdown']) && is_array($item['edge_breakdown']) ) $edges_data = $item['edge_breakdown'];
            elseif ( isset($item['edges']) && is_array($item['edges']) ) $edges_data = $item['edges'];

            $pure_edge_total = 0;
            $edge_ops_total = 0;
            if ( ! empty($edges_data) ) {
                foreach ( $edges_data as $edge ) {
                    $edge = (array) $edge;
                    $code = isset($edge['code']) ? (string) $edge['code'] : '';
                    $name = isset($edge['name']) ? (string) $edge['name'] : '';
                    $side = isset($edge['side']) ? strtoupper((string) $edge['side']) : '';
                    $cost = isset($edge['cost']) ? floatval($edge['cost']) : 0;
                    $op_name = isset($edge['op_name']) ? (string) $edge['op_name'] : '';
                    $op_cost = isset($edge['op_cost']) ? floatval($edge['op_cost']) : 0;

                    $edge_ops_total += $op_cost;
                    $pure = $cost - $op_cost;
                    if ($pure < 0) $pure = 0;
                    $pure_edge_total += $pure;

                    $k = $code . '||' . $name;
                    if (!isset($edging_agg[$k])) $edging_agg[$k] = array('code' => $code, 'name' => $name, 'cost' => 0, 'meters' => 0);
                    $edging_agg[$k]['cost'] += $pure;

                    $side_len_mm = 0;
                    if ($side === 'L1' || $side === 'L2') $side_len_mm = $length_mm;
                    elseif ($side === 'W1' || $side === 'W2') $side_len_mm = $width_mm;
                    $edging_agg[$k]['meters'] += ($side_len_mm / 1000) * max(0, $qty);

                    if ($op_name !== '' && $op_cost > 0) {
                        if (!isset($ops_agg[$op_name])) $ops_agg[$op_name] = 0;
                        $ops_agg[$op_name] += $op_cost;
                    }
                }
            }

            $product_ops_total = 0;
            $product_ops = array();
            if ( isset($item['operations']) && is_array($item['operations']) ) $product_ops = $item['operations'];
            elseif ( isset($item['operation']) && is_array($item['operation']) ) $product_ops = array($item['operation']);

            if ( ! empty($product_ops) ) {
                foreach ( $product_ops as $op ) {
                    $op = (array) $op;
                    $op_name = isset($op['name']) ? (string) $op['name'] : '';
                    $op_cost = isset($op['cost']) ? floatval($op['cost']) : 0;
                    if ($op_name !== '' && $op_cost > 0) {
                        if (!isset($ops_agg[$op_name])) $ops_agg[$op_name] = 0;
                        $ops_agg[$op_name] += $op_cost;
                        $product_ops_total += $op_cost;
                    }
                }
            }

            $board_total = $line_total - $pure_edge_total - $edge_ops_total - $product_ops_total;
            if ($board_total < 0) $board_total = 0;

            $desc = $product_name;
            if ($label !== '') $desc .= ' (Label: ' . $label . ')';
            $desc .= ' ' . $dims . ' (' . $qty . ')';

            $board_lines[] = array('label' => $desc, 'cost' => $board_total);
        }
    }

    $format_len = function($m) {
        $s = number_format((float) $m, 3, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    };

    $table_html = '<table border="0" cellpadding="4" cellspacing="0" width="100%">';

    $table_html .= '<tr><td colspan="2" style="font-weight:bold; padding-top:6px;">BOARD</td></tr>';
    foreach ($board_lines as $b) {
        $table_html .= '<tr><td style="width:75%;">' . esc_html($b['label']) . '</td><td style="width:25%; text-align:right;">' . esc_html($currency . number_format((float) $b['cost'], 2)) . '</td></tr>';
    }

    $edging_entries = array_values(array_filter($edging_agg, function($e) { return isset($e['cost']) && (float) $e['cost'] > 0; }));
    if (!empty($edging_entries)) {
        $table_html .= '<tr><td colspan="2" style="font-weight:bold; padding-top:10px;">EDGING</td></tr>';
        usort($edging_entries, function($a, $b) {
            $ka = ($a['code'] ?: $a['name']);
            $kb = ($b['code'] ?: $b['name']);
            return strcmp($ka, $kb);
        });
        foreach ($edging_entries as $e) {
            $label = ($e['code'] ? ($e['code'] . ' - ') : '') . $e['name'];
            if (!empty($e['meters'])) $label .= ' (' . $format_len($e['meters']) . 'm)';
            $table_html .= '<tr><td>' . esc_html($label) . '</td><td style="text-align:right;">' . esc_html($currency . number_format((float) $e['cost'], 2)) . '</td></tr>';
        }
    }

    $ops_entries = array_filter($ops_agg, function($v) { return (float) $v > 0; });
    if (!empty($ops_entries)) {
        $table_html .= '<tr><td colspan="2" style="font-weight:bold; padding-top:10px;">OPERATIONS</td></tr>';
        ksort($ops_entries);
        foreach ($ops_entries as $name => $cost) {
            $table_html .= '<tr><td>' . esc_html($name) . '</td><td style="text-align:right;">' . esc_html($currency . number_format((float) $cost, 2)) . '</td></tr>';
        }
    }

    if ( $keep_offcuts ) {
        $table_html .= '<tr><td colspan="2" style="padding-top:10px;"></td></tr>';
        $table_html .= '<tr><td style="font-weight:bold;">KEEP OFFCUTS</td><td style="text-align:right;">Yes</td></tr>';
    }

    $table_html .= '<tr><td colspan="2" style="border-top:1px solid #ddd; padding-top:10px;"></td></tr>';
    $table_html .= '<tr><td style="text-align:right; font-weight:bold;">TOTAL</td><td style="text-align:right; font-weight:bold;">' . esc_html( $currency . number_format($grand_total, 2) ) . '</td></tr>';
    $table_html .= '</table>';
    
    $placeholders['{quote_table}'] = $table_html;
    $placeholders['{grand_total}'] = $currency . number_format($grand_total, 2);

    // Replace Placeholders
    foreach ( $placeholders as $key => $value ) {
        $header_content = str_replace( $key, $value, $header_content );
        $body_content = str_replace( $key, $value, $body_content );
        $footer_content = str_replace( $key, $value, $footer_content );
    }

    $html = '<style>' . $custom_css . '</style>';
    $html .= '<div class="wq-pdf-header">' . $header_content . '</div>';
    $html .= '<div class="wq-pdf-body">' . $body_content . '</div>';
    $html .= '<div class="wq-pdf-table">' . $table_html . '</div>'; // Use table directly if not in body
    // If user puts {quote_table} in body, we should avoid duplicating it.
    // Let's refine:
    
    // Reset HTML
    $html = '<style>' . $custom_css . '</style>';
    $html .= '<div class="wq-pdf-wrapper">';
    $html .= '<div class="wq-pdf-header">' . $header_content . '</div>';
    
    // Check if table placeholder is used in body
    if ( strpos( $body_content, '{quote_table}' ) === false && strpos( $body_content, '<table' ) === false ) {
        // If not used, append it automatically after body
        $html .= '<div class="wq-pdf-body">' . $body_content . '</div>';
        $html .= '<div class="wq-pdf-table">' . $table_html . '</div>';
    } else {
        // It's inside body
         $html .= '<div class="wq-pdf-body">' . $body_content . '</div>';
    }
    
    $html .= '<div class="wq-pdf-footer">' . $footer_content . '</div>';
    $html .= '</div>';

    $pdf->writeHTML($html, true, false, true, false, '');

    // Save PDF to uploads directory
    $upload_dir = wp_upload_dir();
    $wq_upload_dir = $upload_dir['basedir'] . '/wq_quotes';
    $wq_upload_url = $upload_dir['baseurl'] . '/wq_quotes';

    if ( ! file_exists( $wq_upload_dir ) ) {
        mkdir( $wq_upload_dir, 0755, true );
    }

    $filename = 'quote_' . time() . '_' . uniqid() . '.pdf';
    $file_path = $wq_upload_dir . '/' . $filename;
    
    $pdf->Output( $file_path, 'F' );

    return $wq_upload_url . '/' . $filename;
}

// Send Email Notifications
function wq_builder_send_quote_email( $data, $quote_id ) {
    $admin_email = get_option( 'admin_email' );
    $project = isset($data['project']) ? sanitize_text_field($data['project']) : 'No Project Name';
    $client = isset($data['client']) ? sanitize_text_field($data['client']) : 'Guest';
    $ref = isset($data['quote_ref']) ? sanitize_text_field($data['quote_ref']) : 'Unknown Ref';
    
    // User Info
    $user_login = 'Guest';
    $user_email_addr = '';
    $user_quote_url = '';
    
    if ( is_user_logged_in() ) {
        $user_info = get_userdata(get_current_user_id());
        $user_login = $user_info->user_login;
        $user_email_addr = $user_info->user_email;
        // Assuming My Account endpoint for saved quotes
        $user_quote_url = wc_get_account_endpoint_url( 'saved-quotes' );
    }

    $admin_quote_url = admin_url( 'post.php?post=' . $quote_id . '&action=edit' );

    // Placeholders
    $placeholders = array(
        '{client_name}' => $client,
        '{project_name}' => $project,
        '{quote_ref}' => $ref,
        '{quote_id}' => $quote_id,
        '{admin_quote_url}' => $admin_quote_url,
        '{user_quote_url}' => $user_quote_url,
        '{user_email}' => $user_email_addr,
        '{user_login}' => $user_login
    );

    // --- Admin Email ---
    $admin_subject_tpl = get_option( 'wq_email_admin_subject', 'New Quote Saved: {project_name} ({quote_ref})' );
    $admin_heading = get_option( 'wq_email_admin_heading', 'New Quote Received' );
    $admin_content_tpl = get_option( 'wq_email_admin_content', '<p>A quote has been saved on the website.</p><p><strong>Project:</strong> {project_name}<br><strong>Reference:</strong> {quote_ref}<br><strong>Client:</strong> {client_name}</p><p>You can view this quote here: <a href="{admin_quote_url}">View Quote</a></p>' );
    
    // Replace Placeholders
    $admin_subject = str_replace( array_keys($placeholders), array_values($placeholders), $admin_subject_tpl );
    $admin_content_body = str_replace( array_keys($placeholders), array_values($placeholders), $admin_content_tpl );
    
    // Wrap in Template
    $admin_content = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">';
    if ( ! empty( $admin_heading ) ) {
        $admin_content .= '<h2 style="color: #444; border-bottom: 2px solid #eee; padding-bottom: 10px;">' . esc_html($admin_heading) . '</h2>';
    }
    $admin_content .= '<div style="padding: 15px 0;">' . $admin_content_body . '</div>';
    $admin_content .= '<div style="font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 10px;">Sent from Quote Builder</div>';
    $admin_content .= '</div>';

    // Send Admin Email (HTML)
    add_filter( 'wp_mail_content_type', 'wq_builder_set_html_content_type' );
    wp_mail( $admin_email, $admin_subject, $admin_content );
    remove_filter( 'wp_mail_content_type', 'wq_builder_set_html_content_type' );

    // --- User Email (Only if logged in) ---
    if ( is_user_logged_in() && is_email( $user_email_addr ) ) {
        $user_subject_tpl = get_option( 'wq_email_user_subject', 'Quote Saved: {project_name}' );
        $user_heading = get_option( 'wq_email_user_heading', 'Quote Saved Successfully' );
        $user_content_tpl = get_option( 'wq_email_user_content', '<p>Hello {client_name},</p><p>You have successfully saved a quote for project: <strong>{project_name}</strong>.</p><p>Reference: {quote_ref}</p><p>You can view or edit your saved quotes in your account dashboard.</p><p><a href="{user_quote_url}">View My Quotes</a></p><p>Thank you!</p>' );

        $user_subject = str_replace( array_keys($placeholders), array_values($placeholders), $user_subject_tpl );
        $user_content_body = str_replace( array_keys($placeholders), array_values($placeholders), $user_content_tpl );

        // Wrap in Template
        $user_content = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">';
        if ( ! empty( $user_heading ) ) {
            $user_content .= '<h2 style="color: #444; border-bottom: 2px solid #eee; padding-bottom: 10px;">' . esc_html($user_heading) . '</h2>';
        }
        $user_content .= '<div style="padding: 15px 0;">' . $user_content_body . '</div>';
        $user_content .= '<div style="font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 10px;">Sent from Quote Builder</div>';
        $user_content .= '</div>';

        add_filter( 'wp_mail_content_type', 'wq_builder_set_html_content_type' );
        wp_mail( $user_email_addr, $user_subject, $user_content );
        remove_filter( 'wp_mail_content_type', 'wq_builder_set_html_content_type' );
    }
}

function wq_builder_set_html_content_type() {
    return 'text/html';
}
