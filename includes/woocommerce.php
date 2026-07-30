<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Add to Basket AJAX Handler (Moved to form-handler.php)
// The function wq_builder_add_to_basket was causing redeclaration error.
// We remove it from here.

// Helper to get/create placeholder product
function wq_get_placeholder_product_id() {
    $sku = 'WQ_CUSTOM_QUOTE';
    $product_id = wc_get_product_id_by_sku( $sku );
    
    // Check if we found a product ID
    if ( $product_id ) {
        $product = wc_get_product($product_id);
        if ($product) {
            // If in trash, restore it!
            if ( $product->get_status() === 'trash' ) {
                wp_untrash_post( $product_id );
                $product = wc_get_product($product_id); // Reload
            }
            
            // Ensure correct settings (Hidden, Publish, etc.)
            $needs_save = false;
            
            if ($product->get_status() !== 'publish') {
                $product->set_status('publish');
                $needs_save = true;
            }
            
            if ( $product->get_catalog_visibility() !== 'hidden' ) {
                $product->set_catalog_visibility('hidden');
                $needs_save = true;
            }
            
            if ( $needs_save ) {
                $product->save();
            }
            
            return $product_id;
        }
    }
    
    // Fallback: Check by Title (Legacy) or ID 182 specifically if we know it
    // But better to just create new if SKU search failed.
    
    // If ID 182 exists but has no SKU?
    $post_182 = get_post(182);
    if ($post_182 && $post_182->post_type == 'product') {
        if ($post_182->post_status == 'trash') {
            wp_untrash_post(182);
        }
        wp_update_post(array('ID' => 182, 'post_status' => 'publish'));
        update_post_meta(182, '_sku', $sku);
        return 182;
    }

    // Create if not exists
    $post_id = wp_insert_post( array(
        'post_title' => 'Custom Quote Item',
        'post_content' => 'This is a placeholder product for custom quotes. Please do not delete.',
        'post_status' => 'publish',
        'post_type' => 'product',
    ));
    
    if ( $post_id ) {
        wp_set_object_terms( $post_id, 'simple', 'product_type' );
        
        // Set Visibility (Hidden) - Modern WooCommerce
        $terms = array( 'exclude-from-catalog', 'exclude-from-search' );
        wp_set_object_terms( $post_id, $terms, 'product_visibility' );
        
        update_post_meta( $post_id, '_visibility', 'hidden' ); // Legacy fallback
        update_post_meta( $post_id, '_stock_status', 'instock' );
        update_post_meta( $post_id, '_price', '1' ); // Force non-zero
        update_post_meta( $post_id, '_regular_price', '1' );
        update_post_meta( $post_id, '_sold_individually', 'no' ); // Allow multiple quotes
        update_post_meta( $post_id, '_virtual', 'no' );
        update_post_meta( $post_id, '_tax_status', 'taxable' );
        update_post_meta( $post_id, '_sku', $sku );
    }
    
    return $post_id;
}

// Force Placeholder Product to be Purchasable
function wq_force_placeholder_purchasable( $purchasable, $product ) {
    if ( $product->get_sku() === 'WQ_CUSTOM_QUOTE' || $product->get_slug() === 'custom-quote' || $product->get_id() == 182 ) {
        return true;
    }
    return $purchasable;
}
add_filter( 'woocommerce_is_purchasable', 'wq_force_placeholder_purchasable', 9999, 2 ); 
add_filter( 'woocommerce_product_is_in_stock', 'wq_force_placeholder_purchasable', 9999, 2 );

// Override get_price for Product 182
function wq_override_get_price( $price, $product ) {
    if ( $product->get_id() == 182 ) {
        // If we are in the cart/checkout context, do NOT force 1.
        // We want the cart item price to be respected.
        
        // However, this filter runs on the product object itself.
        // The cart item uses $cart_item['data']->set_price().
        // If we force '1' here, does it override the cart item price?
        // YES, because WooCommerce often re-fetches the price from the product object during calculations if not careful.
        
        // We should only force '1' if we are NOT in the cart loop for a specific item with custom price.
        // But this filter doesn't know about the cart item context easily.
        
        // Actually, 'woocommerce_before_calculate_totals' sets the price on the cart item object ($cart_item['data']).
        // That object IS a product object (clone).
        // So if we filter 'woocommerce_product_get_price', we are overriding the value we just set in 'before_calculate_totals'!
        
        // FIX: We must NOT force return 1 if the product instance has a manually set price different from default?
        // Or better: Remove this filter or make it smarter.
        
        // We only needed this to bypass "add to cart validation" (price > 0).
        // Validation checks the default product price.
        // Cart calculation checks the cart item price.
        
        // If we return 1 here, the cart item will show 1.
        
        // Let's remove this aggressive filter and rely on 'woocommerce_is_purchasable' and 'woocommerce_add_to_cart_validation' which we already have.
        // Those should be enough to add it.
        // The "price > 0" check in validation is bypassed by our validation filter.
        
        // So, let's remove this filter entirely.
        return $price;
    }
    return $price;
}
// Remove these filters to allow Cart Price Override to work
// add_filter( 'woocommerce_product_get_price', 'wq_override_get_price', 9999, 2 );
// add_filter( 'woocommerce_product_get_regular_price', 'wq_override_get_price', 9999, 2 );
// add_filter( 'woocommerce_product_get_sale_price', 'wq_override_get_price', 9999, 2 );

// Ensure it is VISIBLE (sometimes hidden products cannot be added)
function wq_force_visible( $visible, $product_id ) {
    if ( $product_id == 182 ) {
        return true;
    }
    return $visible;
}
add_filter( 'woocommerce_product_is_visible', 'wq_force_visible', 9999, 2 );

// Force Product Type to Simple
function wq_force_simple_type( $type, $product ) {
    if ( $product->get_id() == 182 ) {
        return 'simple';
    }
    return $type;
}
add_filter( 'woocommerce_product_get_type', 'wq_force_simple_type', 9999, 2 );

// Bypass Cart Item Validation - AGGRESSIVE
function wq_bypass_cart_validation( $valid, $product_id, $quantity ) {
    // Check ID explicitly
    if ( $product_id == 182 ) {
        return true;
    }
    return $valid;
}
// Use Priority 10 and 9999 to catch all
add_filter( 'woocommerce_add_to_cart_validation', 'wq_bypass_cart_validation', 10, 3 );
add_filter( 'woocommerce_add_to_cart_validation', 'wq_bypass_cart_validation', 9999, 3 );

// Force Stock Status
function wq_force_stock_status( $status, $product ) {
    if ( $product->get_id() == 182 ) {
        return 'instock';
    }
    return $status;
}
add_filter( 'woocommerce_product_get_stock_status', 'wq_force_stock_status', 9999, 2 );

// Force Sold Individually to False
function wq_force_sold_individually( $sold_individually, $product ) {
    if ( $product->get_id() == 182 ) {
        return false;
    }
    return $sold_individually;
}
add_filter( 'woocommerce_product_get_sold_individually', 'wq_force_sold_individually', 9999, 2 );

// 1. Restore Custom Data from Session
function wq_get_cart_item_from_session( $cart_item, $values, $key ) {
    $keys_to_persist = array(
        'wq_quote_id',
        'wq_quote_ref',
        'wq_project',
        'wq_original_product_id',
        'wq_product_name',
        'wq_dimensions',
        'wq_custom_fields',
        'wq_operations',
        'wq_operation',
        'wq_edge_breakdown',
        'wq_pricing',
        'wq_unique_key',
        'wq_quote_data' // Legacy
    );

    foreach ( $keys_to_persist as $custom_key ) {
        if ( isset( $values[ $custom_key ] ) ) {
            $cart_item[ $custom_key ] = $values[ $custom_key ];
        }
    }

    return $cart_item;
}
add_filter( 'woocommerce_get_cart_item_from_session', 'wq_get_cart_item_from_session', 10, 3 );

// 2. Override Cart Price
function wq_override_cart_price( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['wq_pricing'] ) ) {
             // New Logic: Per-Item Price Override
             $price = floatval($cart_item['wq_pricing']['unit_price']);
                          $cart_item['data']->set_price( $price );

             // If a variation was selected, original_product_id holds variation ID
             if (isset($cart_item['wq_original_product_id'])) {
                 $var_product = wc_get_product($cart_item['wq_original_product_id']);
                 if ($var_product && $var_product->is_type('variation')) {
                     $cart_item['data']->set_sku($var_product->get_sku());
                     $cart_item['data']->set_name($var_product->get_name());
                 }
             }
             // Also force price on object directly
             // $cart_item['data']->set_regular_price( $price );
             // $cart_item['data']->set_sale_price( '' );
             
             // Ensure it is purchasable
             $cart_item['data']->set_stock_status('instock');
             $cart_item['data']->set_manage_stock(false);
             
             $cart_item['data']->set_tax_status( 'taxable' );
             
        } elseif ( isset( $cart_item['wq_quote_data'] ) ) {
            // Use Data from Session (Legacy / Saved Quotes)
            $quote_data = $cart_item['wq_quote_data'];
            
            // Calculate Total from Items (re-verify or trust data?)
            // We should trust the data snapshot or re-calculate?
            // Re-calculating is safer but complex (need product prices).
            // Let's rely on the saved total if available, or re-sum.
            // The JSON saved in 'items' has 'price' per row.
            
            $grand_total = 0;
            if (!empty($quote_data['items'])) {
                foreach($quote_data['items'] as $item) {
                     $price = floatval($item['price']);
                     // In old structure, item['price'] might exclude edging?
                     // Let's check how save worked.
                     // It saved total.
                     
                     // But wait, if we add ONE placeholder for the WHOLE quote, we set price to grand total.
                     // Yes.
                }
                // Recalculate Grand Total
                // Actually, if we use the Placeholder for the WHOLE quote, we just grab _wq_grand_total.
                $grand_total = isset($quote_data['grand_total']) ? floatval($quote_data['grand_total']) : 0;
                
                // If grand_total missing, sum up
                if ($grand_total == 0 && !empty($quote_data['items'])) {
                     foreach($quote_data['items'] as $item) {
                         $grand_total += floatval($item['price']);
                     }
                }
            }
            
            // Set Price (Ex VAT)
            $cart_item['data']->set_price( $grand_total );
            $cart_item['data']->set_tax_status( 'taxable' );
            
        } elseif ( isset( $cart_item['wq_quote_id'] ) ) {
            // Fallback to Post Meta logic (Legacy)
            $quote_id = $cart_item['wq_quote_id'];
            
            // Let's try to recalculate Ex-VAT price from the quote data.
            $grand_total = (float) get_post_meta( $quote_id, '_wq_grand_total', true );
            
            // Check if prices entered with tax? No, usually builders are ex tax.
            // If we set price to Ex-VAT, Woo will add tax if configured.
            // If Woo is NOT configured for tax, it will just show Ex-VAT.
            
            // Let's just set the price to the total.
            $cart_item['data']->set_price( $grand_total );
            // Ensure it's not taxed again by Woo (Since we removed VAT logic, this price is Ex VAT. If Woo is configured to add tax, it will.)
            $cart_item['data']->set_tax_status( 'taxable' );
        }
    }
}
add_action( 'woocommerce_before_calculate_totals', 'wq_override_cart_price', 20, 1 );

// 3. Display Custom Data in Cart
function wq_render_cart_meta( $item_data, $cart_item ) {
    // New Logic: Per-Item Meta
    if ( isset( $cart_item['wq_dimensions'] ) ) {
        $dims = $cart_item['wq_dimensions'];
        $item_data[] = array(
            'key' => 'Dimensions',
            'value' => "{$dims['length']}x{$dims['width']}x{$dims['thickness']}mm",
            'display' => "{$dims['length']}x{$dims['width']}x{$dims['thickness']}mm"
        );
    }

    if ( isset( $cart_item['wq_operations'] ) && is_array( $cart_item['wq_operations'] ) && ! empty( $cart_item['wq_operations'] ) ) {
        $names = array();
        foreach ( $cart_item['wq_operations'] as $op ) {
            $op = (array) $op;
            if ( ! empty( $op['name'] ) ) $names[] = $op['name'];
        }
        if ( ! empty( $names ) ) {
            $item_data[] = array(
                'key' => 'Operations',
                'value' => implode( ', ', $names ),
                'display' => implode( ', ', $names )
            );
        }
    } elseif ( isset( $cart_item['wq_operation'] ) && ! empty( $cart_item['wq_operation'] ) ) {
        $op = (array) $cart_item['wq_operation'];
        if ( ! empty( $op['name'] ) ) {
            $item_data[] = array(
                'key' => 'Operation',
                'value' => $op['name'],
                'display' => $op['name']
            );
        }
    }
    
    // Always show breakdown, even if empty? No, only if exists.
    if ( isset( $cart_item['wq_edge_breakdown'] ) && !empty( $cart_item['wq_edge_breakdown'] ) ) {
        // Group Edging vs Operations
        $edging_lines = array();
        $ops_lines = array();
        
        foreach ( $cart_item['wq_edge_breakdown'] as $edge ) {
            // Edging
            $name = $edge['name'];
            $code = isset($edge['code']) ? $edge['code'] : '';
            if (isset($edge['op_name']) && $edge['op_name']) {
                $name = str_replace(' + ' . $edge['op_name'], '', $name); 
                $ops_lines[] = "{$edge['side']}: {$edge['op_name']}";
            }
            $edging_lines[] = "{$edge['side']}: " . ($code ? ($code . ' - ') : '') . $name;
        }
        
        // Use separate meta entries for cleaner list?
        // Or join with <br> if theme supports it.
        // Standard safe way is one line per key, but if list is long, it's messy.
        
        if (!empty($edging_lines)) {
            $item_data[] = array(
                'key' => 'Edging',
                'value' => implode(', ', $edging_lines),
                'display' => implode(', ', $edging_lines)
            );
        }
        if (!empty($ops_lines)) {
            $item_data[] = array(
                'key' => 'Operations',
                'value' => implode(', ', $ops_lines),
                'display' => implode(', ', $ops_lines)
            );
        }
    }
    
    // Add Labels / Notes if present (was missing)
    if ( isset( $cart_item['wq_custom_fields'] ) && !empty($cart_item['wq_custom_fields']) ) {
         // Assuming custom fields might contain user input like "Label"
         if (isset($cart_item['wq_custom_fields']['label']) && !empty($cart_item['wq_custom_fields']['label'])) {
             $item_data[] = array(
                 'key' => 'Label',
                 'value' => $cart_item['wq_custom_fields']['label'],
                 'display' => $cart_item['wq_custom_fields']['label']
             );
         }
         
         // Add other custom fields if any
         foreach ($cart_item['wq_custom_fields'] as $key => $val) {
             if ($key !== 'label' && !empty($val)) {
                 $item_data[] = array(
                     'key' => ucfirst($key),
                     'value' => $val,
                     'display' => $val
                 );
             }
         }
    }
    
    if ( isset( $cart_item['wq_project'] ) && !empty($cart_item['wq_project']) ) {
        $item_data[] = array(
            'key' => 'Project', 
            'value' => $cart_item['wq_project'],
            'display' => $cart_item['wq_project']
        );
    }
    
    if ( isset( $cart_item['wq_quote_ref'] ) && !empty($cart_item['wq_quote_ref']) ) {
        $item_data[] = array(
            'key' => 'Ref', 
            'value' => $cart_item['wq_quote_ref'],
            'display' => $cart_item['wq_quote_ref']
        );
    }

    if ( ! empty( $cart_item['wq_keep_offcuts'] ) ) {
        $item_data[] = array(
            'key' => 'Keep Offcuts',
            'value' => 'Yes',
            'display' => 'Yes'
        );
    }

    // Check if we have data in the session first (Legacy / Saved Quote)
    $quote_data = array();
    
    // Check various sources for quote data
    if ( isset( $cart_item['wq_quote_data'] ) ) {
        $quote_data = $cart_item['wq_quote_data'];
    } elseif ( isset( $cart_item['wq_quote_id'] ) ) {
        // Fallback to Post Meta if not in session (for legacy or if added differently)
        $quote_id = $cart_item['wq_quote_id'];
        $quote_data = get_post_meta( $quote_id, '_wq_quote_data', true );
    }

    if ( ! empty( $quote_data ) ) {
        // ... (Existing legacy logic) ...
        // Add Quote Ref
        $quote_ref = isset($quote_data['quote_ref']) ? $quote_data['quote_ref'] : 'N/A';
        $item_data[] = array(
            'key'     => 'Quote Ref',
            'value'   => $quote_ref,
        );
        
        // Add Project Info
        if ( ! empty($quote_data['project']) ) {
            $item_data[] = array('key' => 'Project', 'value' => $quote_data['project']);
        }
        if ( ! empty($quote_data['client']) ) {
            $item_data[] = array('key' => 'Client', 'value' => $quote_data['client']);
        }
        if ( ! empty( $quote_data['keep_offcuts'] ) ) {
            $item_data[] = array('key' => 'Keep Offcuts', 'value' => 'Yes');
        }
        
        // Render Line Items
        // FIX: Ensure this loop also displays Edging/Operations for Saved Quotes!
        // The saved quote data structure might be slightly different.
        if ( ! empty( $quote_data['items'] ) ) {
            foreach ( $quote_data['items'] as $index => $item ) {
                $num = $index + 1;
                $dims = "{$item['length']}x{$item['width']}x{$item['thickness']}mm (Qty: {$item['qty']})";
                
                // Item Name & Dims
                $item_name = isset($item['product_name']) ? $item['product_name'] : 'Item';
                $item_data[] = array(
                    'key'     => "Item #$num",
                    'value'   => "{$item_name} - $dims",
                );
                
                // Edging (Saved Quote Structure)
                // In saved quotes, edging is usually in 'edges' array or 'edge_breakdown' depending on version.
                // Let's check for both.
                $edge_lines = array();
                
                // New Structure (edge_breakdown)
                if ( ! empty( $item['edge_breakdown'] ) ) {
                     foreach ( $item['edge_breakdown'] as $edge ) {
                        $name = $edge['name'];
                        if (isset($edge['op_name']) && $edge['op_name']) {
                             $name = str_replace(' + ' . $edge['op_name'], '', $name); 
                             $edge_lines[] = "{$edge['side']}: {$name} + {$edge['op_name']}";
                        } else {
                             $edge_lines[] = "{$edge['side']}: {$name}";
                        }
                     }
                } 
                // Legacy Structure (edges)
                elseif ( ! empty( $item['edges'] ) ) {
                    foreach ( $item['edges'] as $edge ) {
                        $edge_lines[] = "{$edge['side']}: {$edge['name']}";
                    }
                }
                
                if (!empty($edge_lines)) {
                    $item_data[] = array(
                        'key'     => "Item #$num Details",
                        'value'   => implode(', ', $edge_lines),
                    );
                }
            }
        }
    }
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'wq_render_cart_meta', 10, 2 );

// 4. Update Order Item Meta
function wq_add_order_item_meta( $item, $cart_item_key, $values, $order ) {
    // New Logic: Per-Item
    if ( isset( $values['wq_dimensions'] ) ) {
        $dims = $values['wq_dimensions'];
        $item->add_meta_data('Dimensions', "{$dims['length']}x{$dims['width']}x{$dims['thickness']}mm");
    }
    
    if ( isset( $values['wq_edge_breakdown'] ) && !empty( $values['wq_edge_breakdown'] ) ) {
        $edging_lines = array();
        $ops_lines = array();
        $currency_symbol = get_woocommerce_currency_symbol();
        
        foreach ( $values['wq_edge_breakdown'] as $edge ) {
            $edge = (array) $edge; // Safe cast
            
            $name = isset($edge['name']) ? $edge['name'] : 'Unknown';
            $side = isset($edge['side']) ? $edge['side'] : '?';
            $code = isset($edge['code']) ? $edge['code'] : '';
            
            $cost = isset($edge['cost']) ? floatval($edge['cost']) : 0; // Total
            $op_cost = isset($edge['op_cost']) ? floatval($edge['op_cost']) : 0;
            
            if (isset($edge['op_name']) && $edge['op_name']) {
                $op_cost_str = ($op_cost > 0) ? " ({$currency_symbol}" . number_format($op_cost, 2) . ")" : "";
                $ops_lines[] = "{$edge['op_name']}{$op_cost_str}"; // Removed side for clean look
                
                // Pure edge cost
                $cost = $cost - $op_cost;
                $name = str_replace(' + ' . $edge['op_name'], '', $name);
            }
            
            $cost_str = ($cost > 0) ? " ({$currency_symbol}" . number_format($cost, 2) . ")" : "";
            $prefix = $side . ': ';
            if ($code) $prefix .= $code . ' - ';
            $edging_lines[] = "{$prefix}{$name}{$cost_str}";
        }
        if (!empty($edging_lines)) $item->add_meta_data('Edging', implode(', ', $edging_lines));
        if (!empty($ops_lines)) $item->add_meta_data('Operations', implode(', ', $ops_lines));
    }

    if ( isset( $values['wq_operations'] ) && is_array( $values['wq_operations'] ) && ! empty( $values['wq_operations'] ) ) {
        $names = array();
        foreach ( $values['wq_operations'] as $op ) {
            $op = (array) $op;
            if ( ! empty( $op['name'] ) ) $names[] = $op['name'];
        }
        if ( ! empty( $names ) ) {
            $item->add_meta_data('Operations (Product)', implode(', ', $names));
        }
    } elseif ( isset( $values['wq_operation'] ) && ! empty( $values['wq_operation'] ) ) {
        $op = (array) $values['wq_operation'];
        if ( ! empty( $op['name'] ) ) {
            $item->add_meta_data('Operation', $op['name']);
        }
    }
    
    // Labels / Custom Fields
    if ( isset( $values['wq_custom_fields'] ) && !empty($values['wq_custom_fields']) ) {
         if (isset($values['wq_custom_fields']['label']) && !empty($values['wq_custom_fields']['label'])) {
             $item->add_meta_data('Label', $values['wq_custom_fields']['label']);
         }
         foreach ($values['wq_custom_fields'] as $key => $val) {
             if ($key !== 'label' && !empty($val)) {
                 $item->add_meta_data(ucfirst($key), $val);
             }
         }
    }
    
    if ( isset( $values['wq_project'] ) ) $item->add_meta_data('Project', $values['wq_project']);
    if ( isset( $values['wq_quote_ref'] ) ) $item->add_meta_data('Quote Ref', $values['wq_quote_ref']);
    if ( ! empty( $values['wq_keep_offcuts'] ) ) $item->add_meta_data('Keep Offcuts', 'Yes');
    if ( isset( $values['wq_product_name'] ) ) $item->add_meta_data('Product Name', $values['wq_product_name']);

    // Legacy (Fallback)
    $quote_data = array();
    if ( isset( $values['wq_quote_data'] ) ) {
        $quote_data = $values['wq_quote_data'];
    } elseif ( isset( $values['wq_quote_id'] ) ) {
         $quote_data = get_post_meta( $values['wq_quote_id'], '_wq_quote_data', true );
    }

    if ( ! empty( $quote_data ) ) {
        // ... Legacy Logic kept for backward compatibility if needed ...
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'wq_add_order_item_meta', 10, 4 );

// 5. Remove Quote (Only if it's a temporary cart quote)
function wq_remove_quote_on_cart_remove( $cart_item_key, $cart ) {
    // Note: This hook fires *before* removal if we use 'woocommerce_remove_cart_item'
    // But if we use 'woocommerce_cart_item_removed', the item is gone.
    // Let's use 'woocommerce_remove_cart_item' instead.
    
    if ( isset( $cart->cart_contents[ $cart_item_key ] ) ) {
        $cart_item = $cart->cart_contents[ $cart_item_key ];
        
        if ( isset( $cart_item['wq_quote_id'] ) ) {
            $quote_id = $cart_item['wq_quote_id'];
            
            // "if the user removes the quote from the cart then move the quote from trash"
            // Assuming "delete permanently" based on previous context.
            
            $status = get_post_status($quote_id);
            if ($status === 'wq-in-cart') {
                 // Delete Permanently
                 wp_delete_post( $quote_id, true );
            }
        }
    }
}
// Changed hook from 'woocommerce_cart_item_removed' to 'woocommerce_remove_cart_item'
add_action( 'woocommerce_remove_cart_item', 'wq_remove_quote_on_cart_remove', 10, 2 );

// 6. Change Cart Item Name (Enhanced Display)
function wq_cart_item_name( $name, $cart_item, $cart_item_key ) {
    $title = $name;
    
    // Prefer actual product name if available
    if ( isset( $cart_item['wq_product_name'] ) ) {
        $title = $cart_item['wq_product_name'];
    } elseif ( isset( $cart_item['wq_quote_ref'] ) ) {
        $title = 'Custom Quote: ' . $cart_item['wq_quote_ref'];
    }
    
    // Append details directly to name if Meta is hidden by theme
    $details = '';
    
    // 1. Dimensions
    if ( isset( $cart_item['wq_dimensions'] ) ) {
        $dims = $cart_item['wq_dimensions'];
        $details .= '<br><small>Dimensions: ' . "{$dims['length']}x{$dims['width']}x{$dims['thickness']}mm" . '</small>';
    }
    
    // 2. Edging
    if ( isset( $cart_item['wq_edge_breakdown'] ) && !empty( $cart_item['wq_edge_breakdown'] ) ) {
        $edging_lines = array();
        $ops_lines = array();
        $currency_symbol = get_woocommerce_currency_symbol();
        
        foreach ( $cart_item['wq_edge_breakdown'] as $edge ) {
            // Check if array or object (sometimes deserialization issues)
            $edge = (array) $edge; 
            
            $name_svc = isset($edge['name']) ? $edge['name'] : 'Unknown';
            $side = isset($edge['side']) ? $edge['side'] : '?';
            
            $cost = isset($edge['cost']) ? floatval($edge['cost']) : 0; // Total
            $op_cost = isset($edge['op_cost']) ? floatval($edge['op_cost']) : 0;
            
            if (isset($edge['op_name']) && $edge['op_name']) {
                $op_cost_str = ($op_cost > 0) ? " ({$currency_symbol}" . number_format($op_cost, 2) . ")" : "";
                $ops_lines[] = "{$edge['op_name']}{$op_cost_str}"; // Removed side
                
                // Pure Edge Cost
                $cost = $cost - $op_cost;
                $name_svc = str_replace(' + ' . $edge['op_name'], '', $name_svc); 
            }
            
            $cost_str = ($cost > 0) ? " ({$currency_symbol}" . number_format($cost, 2) . ")" : "";
            $edging_lines[] = "{$name_svc}{$cost_str}";
        }
        
        if (!empty($edging_lines)) {
            $details .= '<br><small><strong>Edging:</strong> ' . implode(', ', $edging_lines) . '</small>';
        }
        if (!empty($ops_lines)) {
            $details .= '<br><small><strong>Operations:</strong> ' . implode(', ', $ops_lines) . '</small>';
        }
    } else {
        // Fallback: If no breakdown, check if 'edges' exists (Legacy)
        if ( isset($cart_item['wq_pricing']['base_price']) ) {
             // Maybe we can infer something? No.
        }
    }
    
    // 3. Label
    if ( isset( $cart_item['wq_custom_fields']['label']) && !empty($cart_item['wq_custom_fields']['label']) ) {
        $details .= '<br><small><strong>Label:</strong> ' . $cart_item['wq_custom_fields']['label'] . '</small>';
    }

    if ( isset( $cart_item['wq_operations'] ) && is_array( $cart_item['wq_operations'] ) && ! empty( $cart_item['wq_operations'] ) ) {
        $names = array();
        foreach ( $cart_item['wq_operations'] as $op ) {
            $op = (array) $op;
            if ( isset($op['name']) && $op['name'] ) $names[] = $op['name'];
        }
        if ( ! empty( $names ) ) {
            $details .= '<br><small><strong>Operations:</strong> ' . esc_html(implode(', ', $names)) . '</small>';
        }
    } elseif ( isset( $cart_item['wq_operation'] ) && ! empty( $cart_item['wq_operation'] ) ) {
        $op = (array) $cart_item['wq_operation'];
        if ( isset($op['name']) && $op['name'] ) {
            $details .= '<br><small><strong>Operation:</strong> ' . esc_html($op['name']) . '</small>';
        }
    }

    return $title . $details;
}
add_filter( 'woocommerce_cart_item_name', 'wq_cart_item_name', 10, 3 );
add_filter( 'woocommerce_order_item_name', 'wq_cart_item_name', 10, 3 ); // Also hook for Order Table!

// 8. Override Cart Item Thumbnail
function wq_cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
    $quote_data = array();
    if ( isset( $cart_item['wq_quote_data'] ) ) {
        $quote_data = $cart_item['wq_quote_data'];
    } elseif ( isset( $cart_item['wq_quote_id'] ) ) {
        $quote_id = $cart_item['wq_quote_id'];
        $quote_data = get_post_meta( $quote_id, '_wq_quote_data', true );
    }

    if ( ! empty($quote_data['items']) && isset($quote_data['items'][0]['image']) && !empty($quote_data['items'][0]['image']) ) {
        return '<img src="' . esc_url($quote_data['items'][0]['image']) . '" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" alt="Product Image" style="width: 64px; height: auto;">';
    }
    return $thumbnail;
}
add_filter( 'woocommerce_cart_item_thumbnail', 'wq_cart_item_thumbnail', 10, 3 );

// Update Quote Status on Checkout (Move 'In Cart' -> 'Purchased')
function wq_update_quote_status_on_checkout( $order_id ) {
    $order = wc_get_order( $order_id );
    
    foreach ( $order->get_items() as $item_id => $item ) {
        $quote_id = $item->get_meta('wq_quote_id');
        
        if ( $quote_id ) {
            $quote_post = get_post($quote_id);
            if ( $quote_post && $quote_post->post_type === 'wq_quote' ) {
                if ( $quote_post->post_status === 'wq-in-cart' || $quote_post->post_status === 'wq-saved' ) {
                    wp_update_post(array(
                        'ID' => $quote_id,
                        'post_status' => 'wq-purchased'
                    ));
                    update_post_meta($quote_id, '_wq_order_id', $order_id);
                }
            }
        }
    }
}
add_action( 'woocommerce_checkout_order_processed', 'wq_update_quote_status_on_checkout' );

// Ensure wq_quote_id is saved to order item meta
function wq_save_quote_id_to_order_item( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['wq_quote_id'] ) ) {
        $item->add_meta_data( 'wq_quote_id', $values['wq_quote_id'] );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'wq_save_quote_id_to_order_item', 10, 4 );

// 7. My Account - Saved Quotes
// Register new endpoint
function wq_add_my_quotes_endpoint() {
    add_rewrite_endpoint( 'saved-quotes', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'wq_add_my_quotes_endpoint' );

// Add to My Account menu
function wq_add_my_quotes_link_my_account( $items ) {
    $items['saved-quotes'] = 'Saved Quotes';
    return $items;
}
add_filter( 'woocommerce_account_menu_items', 'wq_add_my_quotes_link_my_account' );

// Content for Saved Quotes
function wq_saved_quotes_content() {
    $user_id = get_current_user_id();
    
    // Exclude PDF Only quotes (Hidden from My Account)
    $args = array(
        'post_type' => 'wq_quote',
        'posts_per_page' => 10,
        'author' => $user_id,
        'post_status' => array('publish', 'wq-saved'), // Include wq-saved
        'meta_query' => array(
            array(
                'key' => '_wq_is_pdf_only',
                'compare' => 'NOT EXISTS' // Show only if NOT PDF only
            )
        )
    );
    
    $quotes = new WP_Query($args);
    
    echo '<h3>Saved Quotes</h3>';
    
    if ( $quotes->have_posts() ) {
        // ... (table header) ...
        // Wait, why does the user see nothing? "cstom quote removed undo ?"
        // Maybe the 'wq_handle_quote_delete' is firing incorrectly or we have a logic issue.
        // Or maybe 'posts_per_page' => 10 is filtering too much? No.
        
        // Check if query is actually working.
        // User says: "when i go to the wishlist , i see 'cstom quote removed undo ?'"
        // This message usually comes from WooCommerce when an item is removed from the CART or Order?
        // Ah, "Undo?" is standard Woo notice.
        // But if the user sees NOTHING in the table, it means the query returned no posts.
        
        // Debug: Is the author ID correct?
        // Is the post status 'publish'?
        // We are using 'wq_quote' post type.
        
        // Let's ensure the query args are robust.
        
        // Also, the user mentioned "when i click on undo button i cannot see the wishlist custom product at all".
        // This implies they might be confusing the Cart with the Wishlist (Saved Quotes tab)?
        // "cstom quote removed undo ?" sounds like a Cart message.
        // If they are in "Saved Quotes" endpoint, they shouldn't see Cart messages unless we triggered something.
        
        // Re-verify the query loop.
        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
        echo '<thead><tr>';
        echo '<th><span class="nobr">Image</span></th>';
        echo '<th><span class="nobr">Quote Ref</span></th>';
        echo '<th><span class="nobr">Project</span></th>';
        echo '<th><span class="nobr">Date</span></th>';
        echo '<th><span class="nobr">Total</span></th>';
        echo '<th><span class="nobr">Actions</span></th>';
        echo '</tr></thead><tbody>';
        
        while ( $quotes->have_posts() ) {
            $quotes->the_post();
            $quote_id = get_the_ID();
            $quote_data = get_post_meta($quote_id, '_wq_quote_data', true);
            $total = get_post_meta($quote_id, '_wq_grand_total', true);
            $project = get_post_meta($quote_id, '_wq_project_name', true);
            $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
            
            // Get Image from first item
            $image_html = '';
            $img_src = '';
            
            if ( ! empty($quote_data['items']) ) {
                $first_item = $quote_data['items'][0];
                if ( ! empty($first_item['image']) ) {
                    $img_src = $first_item['image'];
                } elseif ( ! empty($first_item['product_id']) ) {
                    // Fallback: Get product image
                    $img_src = get_the_post_thumbnail_url($first_item['product_id'], 'thumbnail');
                }
            }
            
            if ( $img_src ) {
                $image_html = '<img src="' . esc_url($img_src) . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" alt="Product Image">';
            } else {
                 $image_html = '<span style="color:#ccc;">No Image</span>';
            }
            
            echo '<tr>';
            echo '<td>' . $image_html . '</td>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($project) . '</td>';
            echo '<td>' . get_the_date() . '</td>';
            echo '<td>' . $currency . esc_html($total) . '</td>';
            
            $delete_url = wp_nonce_url( add_query_arg( array( 'wq_delete_quote' => $quote_id ), wc_get_account_endpoint_url( 'saved-quotes' ) ), 'wq_delete_quote_' . $quote_id );
            
            // Edit URL
            $quote_page_url = get_option('wq_builder_quote_page_url');
            $edit_url = add_query_arg('edit_quote', $quote_id, $quote_page_url);
            
            // Add View Button (trigger modal)
            $view_btn = '<button class="button wq-view-quote-btn" data-quote-id="' . $quote_id . '" style="margin-right:5px;">View</button>';
            // $edit_btn = '<a href="' . esc_url($edit_url) . '" class="button wq-edit-quote-btn" style="margin-right:5px;">Edit</a>';
            
            echo '<td>
                ' . $view_btn . '
                <a href="' . $delete_url . '" class="button delete" onclick="return confirm(\'Are you sure you want to delete this quote?\');">Delete</a>
            </td>';
            echo '</tr>';
            
            // Hidden Data for Modal
            echo '<tr class="wq-quote-details-row" id="wq-quote-details-' . $quote_id . '" style="display:none;">';
            echo '<td colspan="6" style="padding:0;">';
            echo '<div class="wq-quote-details-content" style="padding: 20px; background: #fff; border: 1px solid #ddd; margin: 10px 0;">';
            
            // Header
            echo '<div style="text-align:center; font-size:1.2em; font-weight:bold; margin-bottom:15px; border-bottom:2px solid #eee; padding-bottom:10px;">Quote Pricing: ' . get_the_title() . '</div>';
            
            // Project Info
            echo '<div style="margin-bottom: 15px;">';
            echo '<strong>Project:</strong> ' . esc_html($project) . '<br>';
            echo '<strong>Client:</strong> ' . esc_html(isset($quote_data['client']) ? $quote_data['client'] : '') . '<br>';
            if ( ! empty( $quote_data['keep_offcuts'] ) ) {
                echo '<strong>Keep Offcuts:</strong> Yes<br>';
            }
            if (!empty($quote_data['notes'])) {
                echo '<strong>Notes:</strong> ' . esc_html($quote_data['notes']);
            }
            echo '</div>';
            
            echo '<div style="font-weight:bold; border-bottom:1px solid #333; margin-bottom:10px;">ITEMS</div>';
            
            if (!empty($quote_data['items'])) {
                foreach ($quote_data['items'] as $item) {
                     $dims = "{$item['length']} x {$item['width']} | Qty: {$item['qty']}";
                     if (!empty($item['thickness'])) $dims = "{$item['length']} x {$item['width']} x {$item['thickness']}mm | Qty: {$item['qty']}";
                     
                     // Item Row
                     $product_name = isset($item['product_name']) ? $item['product_name'] : 'Custom Product';
                     echo '<div style="display:flex; justify-content:space-between; margin-bottom:5px;">';
                     echo '<div>';
                     echo '<div style="font-weight:bold;">' . esc_html($product_name) . '</div>';
                     if (isset($item['custom_fields']['label']) && $item['custom_fields']['label']) {
                         echo '<div style="font-size:0.9em; color:#666;">Label: ' . esc_html($item['custom_fields']['label']) . '</div>';
                     }
                     echo '<div style="font-size:0.9em; color:#666;">Dims: ' . esc_html($dims) . '</div>';
                     echo '</div>';
                     echo '<div style="font-weight:bold;">' . $currency . esc_html($item['price']) . '</div>';
                     echo '</div>';
                     
                     // Prepare Edging & Ops
                     $edging_lines = array();
                     $ops_lines = array();
                     
                     // Unified Edge Handling (Supports 'edge_breakdown' and legacy 'edges')
                     $edges_data = !empty($item['edge_breakdown']) ? $item['edge_breakdown'] : (!empty($item['edges']) ? $item['edges'] : array());
                     
                     if ( ! empty( $edges_data ) ) {
                         foreach ( $edges_data as $edge ) {
                             $edge = (array) $edge;
                             $name = isset($edge['name']) ? $edge['name'] : '';
                             $side = isset($edge['side']) ? $edge['side'] : '';
                             $code = isset($edge['code']) ? $edge['code'] : '';
                             $cost = isset($edge['cost']) ? floatval($edge['cost']) : 0; // This is TOTAL cost (Edge + Op)
                             
                             if (isset($edge['op_name']) && $edge['op_name']) {
                                 $op_cost = isset($edge['op_cost']) ? floatval($edge['op_cost']) : 0;
                                 
                                 $ops_lines[] = array(
                                     'label' => $edge['op_name'],
                                     'display' => $edge['op_name'],
                                     'cost' => $op_cost
                                 );
                                 
                                 // Subtract Op Cost from Edge Cost to get pure Edge Cost
                                 $cost = $cost - $op_cost;
                                 
                                 // Strip op name from service name if combined
                                 $name = str_replace(' + ' . $edge['op_name'], '', $name);
                             }
                             
                             $display_name = trim($side . ': ' . ($code ? ($code . ' - ') : '') . $name);
                             $edging_lines[] = array(
                                 'label' => $name,
                                 'display' => $display_name,
                                 'cost' => $cost
                             );
                         }
                     }
                     
                     // EDGING SECTION
                     if (!empty($edging_lines)) {
                         echo '<div style="font-weight:bold; font-size:0.9em; margin-top:10px; border-bottom:1px solid #eee;">EDGING</div>';
                         foreach ($edging_lines as $row) {
                             echo '<div style="display:flex; justify-content:space-between; padding:3px 0; font-size:0.9em; color:#555;">';
                             echo '<div>' . esc_html($row['display']) . '</div>';
                             echo '<div>' . $currency . esc_html($row['cost']) . '</div>';
                             echo '</div>';
                         }
                     }
                     
                     // OPERATIONS SECTION
                     if (!empty($ops_lines)) {
                         echo '<div style="font-weight:bold; font-size:0.9em; margin-top:10px; border-bottom:1px solid #eee;">OPERATIONS</div>';
                         foreach ($ops_lines as $row) {
                             echo '<div style="display:flex; justify-content:space-between; padding:3px 0; font-size:0.9em; color:#555;">';
                             echo '<div>' . esc_html($row['display']) . '</div>';
                             echo '<div>' . $currency . esc_html($row['cost']) . '</div>';
                             echo '</div>';
                         }
                     }

                     $product_ops = array();
                     if (isset($item['operations']) && is_array($item['operations'])) {
                         $product_ops = $item['operations'];
                     } elseif (isset($item['operation']) && !empty($item['operation'])) {
                         $product_ops = array($item['operation']);
                     }

                     if (!empty($product_ops)) {
                         echo '<div style="font-weight:bold; font-size:0.9em; margin-top:10px; border-bottom:1px solid #eee;">OPERATIONS (PRODUCT)</div>';
                         foreach ($product_ops as $op) {
                             $op = (array) $op;
                             $op_name = isset($op['name']) ? $op['name'] : '';
                             $op_cost = isset($op['cost']) ? floatval($op['cost']) : 0;
                             if (!$op_name) continue;
                             echo '<div style="display:flex; justify-content:space-between; padding:3px 0; font-size:0.9em; color:#555;">';
                             echo '<div>' . esc_html($op_name) . '</div>';
                             echo '<div>' . $currency . esc_html(number_format($op_cost, 2)) . '</div>';
                             echo '</div>';
                         }
                     }
                     
                     echo '<hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">';
                }
            }
            
            // Total
            echo '<div style="text-align:right; font-weight:bold; font-size:1.1em; margin-top:10px;">';
            echo 'TOTAL (Excluding VAT) = ' . $currency . esc_html($total);
            echo '</div>';
            
            // Actions
            echo '<div style="margin-top:20px; text-align:right;">';
            echo '<button class="button alt wq-add-saved-to-cart-btn" data-quote-id="' . $quote_id . '" style="margin-right:10px;">ADD QUOTE TO BASKET</button>';
            echo '<button class="button" onclick="jQuery(\'#wq-quote-details-' . $quote_id . '\').hide(); return false;">Close</button>';
            echo '</div>';
            
            echo '</div>';
            echo '</td></tr>';
        }
        
        echo '</tbody></table>';
        
        // Add JS for Toggling View and Adding to Cart
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Toggle Details
            $('.wq-view-quote-btn').on('click', function(e) {
                e.preventDefault();
                var quoteId = $(this).data('quote-id');
                $('#wq-quote-details-' + quoteId).toggle();
            });
            
            // Add Saved Quote to Cart
            $('.wq-add-saved-to-cart-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var quoteId = btn.data('quote-id');
                var originalText = btn.text();
                
                btn.text('Adding...').prop('disabled', true);
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wq_add_saved_quote_to_cart',
                        quote_id: quoteId,
                        nonce: '<?php echo wp_create_nonce('wq_saved_quote_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.text('Added!');
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert('Error: ' + (response.data.message || 'Unknown error'));
                            btn.text(originalText).prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('Server error. Please try again.');
                        btn.text(originalText).prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
        
        wp_reset_postdata();
    } else {
        echo '<p>You have no saved quotes.</p>';
    }
}
add_action( 'woocommerce_account_saved-quotes_endpoint', 'wq_saved_quotes_content' );

// Handle Delete Action in My Account
function wq_handle_quote_delete() {
    if ( isset( $_GET['wq_delete_quote'] ) && is_user_logged_in() ) {
        $quote_id = intval( $_GET['wq_delete_quote'] );
        check_admin_referer( 'wq_delete_quote_' . $quote_id );
        
        $quote = get_post( $quote_id );
        if ( $quote && $quote->post_author == get_current_user_id() && $quote->post_type == 'wq_quote' ) {
            wp_delete_post( $quote_id, true );
            wc_add_notice( 'Quote deleted successfully.', 'success' );
            wp_safe_redirect( wc_get_account_endpoint_url( 'saved-quotes' ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'wq_handle_quote_delete' );

// AJAX Handler for Adding Saved Quote to Cart
function wq_add_saved_quote_to_cart_ajax() {
    check_ajax_referer( 'wq_saved_quote_action', 'nonce' );
    
    $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
    if (!$quote_id) wp_send_json_error(array('message' => 'Invalid ID'));
    
    $quote_data = get_post_meta($quote_id, '_wq_quote_data', true);
    if (!$quote_data) wp_send_json_error(array('message' => 'Quote data not found'));
    
    // UPDATE the quote status to "In Cart"
    // We do NOT clone anymore, as requested.
    // "if the quote is saved and then added to cart ... update the saved quote status incart , there should be no dupblicate data"
    
    $cart_quote_id = $quote_id;
    
    // Update status
    wp_update_post(array(
        'ID' => $quote_id,
        'post_status' => 'wq-in-cart'
    ));
    
    // Get Placeholder Product
    $placeholder_id = function_exists('wq_get_placeholder_product_id') ? wq_get_placeholder_product_id() : 0;
    if ( ! $placeholder_id ) {
        wp_send_json_error( array( 'message' => 'Configuration Error: Placeholder product missing.' ) );
    }

    $added_count = 0;
    
    if ( ! empty($quote_data['items']) ) {
        foreach ( $quote_data['items'] as $item ) {
            $add_product_id = ($placeholder_id > 0) ? $placeholder_id : intval( $item['product_id'] );
            $qty = intval( $item['qty'] );
            
            if ( $add_product_id > 0 && $qty > 0 ) {
                
                // 1. Ensure Edge Breakdown is robust (Unified with form-handler.php logic)
                $edge_breakdown = array();
                if ( isset($item['edges']) && is_array($item['edges']) ) {
                    $edge_breakdown = $item['edges'];
                } elseif ( isset($item['edge_breakdown']) && is_array($item['edge_breakdown']) ) {
                    $edge_breakdown = $item['edge_breakdown'];
                }
                
                // 2. Ensure Costs are Floats
                foreach ($edge_breakdown as &$edge) {
                    if (isset($edge['cost'])) $edge['cost'] = floatval($edge['cost']);
                    if (isset($edge['op_cost'])) $edge['op_cost'] = floatval($edge['op_cost']);
                }
                unset($edge);

                $stored_item_price = isset($item['price']) ? floatval($item['price']) : 0;
                $line_total = $stored_item_price;
                if ($line_total <= 0) {
                    $raw_grand_total = get_post_meta($quote_id, '_wq_grand_total', true);
                    $clean_grand_total = preg_replace('/[^0-9\.]/', '', str_replace(',', '', $raw_grand_total));
                    $quote_grand_total = floatval($clean_grand_total);
                    if ($quote_grand_total > 0 && count($quote_data['items']) === 1) {
                        $line_total = $quote_grand_total;
                    } else {
                        $fallback_total = 0;
                        foreach ($edge_breakdown as $edge) {
                            if (isset($edge['cost'])) $fallback_total += floatval($edge['cost']);
                        }
                        $line_total = $fallback_total;
                    }
                }

                $unit_price = $line_total / max(1, $qty);

                $cart_item_data = array(
                    'wq_quote_id' => $cart_quote_id, // Link to the 'In Cart' quote post (CLONED)
                    'wq_quote_ref' => isset($quote_data['quote_ref']) ? $quote_data['quote_ref'] : '',
                    'wq_project' => isset($quote_data['project']) ? $quote_data['project'] : '',
                    'wq_original_product_id' => $item['product_id'],
                    'wq_product_name' => isset($item['product_name']) ? $item['product_name'] : get_the_title($item['product_id']),
                    'wq_dimensions' => array(
                        'length' => $item['length'],
                        'width' => $item['width'],
                        'thickness' => $item['thickness']
                    ),
                    'wq_custom_fields' => isset($item['custom_fields']) ? $item['custom_fields'] : array(),
                    'wq_operations' => isset($item['operations']) && is_array($item['operations']) ? $item['operations'] : (isset($item['operation']) && !empty($item['operation']) ? array($item['operation']) : array()),
                    'wq_operation' => isset($item['operation']) ? $item['operation'] : null,
                    'wq_edge_breakdown' => $edge_breakdown,
                    'wq_pricing' => array(
                        'base_price' => $line_total,
                        'unit_price' => $unit_price
                    ),
                    'wq_unique_key' => md5( microtime() . rand() )
                );
                
                // Add to Cart
                try {
                    $cart_item_key = WC()->cart->add_to_cart( $add_product_id, $qty, 0, array(), $cart_item_data );
                    if ($cart_item_key) $added_count++;
                } catch (Exception $e) {
                    error_log('WQ Saved Quote Add Error: ' . $e->getMessage());
                }
            }
        }
    }
    
    // We do NOT delete the saved quote here (as discussed).
    
    if ($added_count > 0) {
        wp_send_json_success(array(
            'redirect_url' => wc_get_cart_url()
        ));
    } else {
        wp_send_json_error(array('message' => 'No valid items found in quote.'));
    }
}
add_action('wp_ajax_wq_add_saved_quote_to_cart', 'wq_add_saved_quote_to_cart_ajax');
add_action('wp_ajax_nopriv_wq_add_saved_quote_to_cart', 'wq_add_saved_quote_to_cart_ajax');
