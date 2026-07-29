<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Fetch Products via AJAX
function wq_builder_get_products() {
	check_ajax_referer( 'wq_builder_nonce', 'nonce' );

	$allowed_cats = get_option( 'wq_builder_allowed_categories', array() );

	if ( empty( $allowed_cats ) ) {
		wp_send_json_error( array( 'message' => 'No categories allowed.' ) );
	}
    
    // Ensure integers
    $allowed_cats = array_map('intval', $allowed_cats);

	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $allowed_cats,
			),
		),
	);

	$products = new WP_Query( $args );
	$product_list = array();

	if ( $products->have_posts() ) {
        // Get Dynamic Fields
        $custom_fields = get_option('wq_custom_fields', array());
        
		while ( $products->have_posts() ) {
			$products->the_post();
			$product = wc_get_product( get_the_ID() );
            
            // Collect Dynamic Data
            $dynamic_data = array();
            if (!empty($custom_fields)) {
                foreach ($custom_fields as $field) {
                    $slug = $field['slug'];
                    // Get meta
                    $val = get_post_meta(get_the_ID(), $slug, true);
                    $dynamic_data[$slug] = $val;
                }
            } else {
                // Fallback Legacy
                $dynamic_data['thickness'] = get_post_meta(get_the_ID(), 'thickness', true);
                $dynamic_data['wq_pricing_per_mm'] = get_post_meta(get_the_ID(), 'wq_pricing_per_mm', true);
                $dynamic_data['wq_max_length'] = get_post_meta(get_the_ID(), 'wq_max_length', true);
                $dynamic_data['wq_max_width'] = get_post_meta(get_the_ID(), 'wq_max_width', true);
                $dynamic_data['wq_min_length'] = get_post_meta(get_the_ID(), 'wq_min_length', true);
                $dynamic_data['wq_min_width'] = get_post_meta(get_the_ID(), 'wq_min_width', true);
            }
            
            $has_edgebanding = get_post_meta(get_the_ID(), '_wq_has_edgebanding', true);
            $has_operations = get_post_meta(get_the_ID(), '_wq_has_operations', true);
            $operation_indexes = get_post_meta(get_the_ID(), '_wq_operation_indexes', true);
            if (!is_array($operation_indexes)) {
                $legacy_single = get_post_meta(get_the_ID(), '_wq_operation_index', true);
                $operation_indexes = $legacy_single !== '' ? array($legacy_single) : array();
            }
            $has_preferred_edging = get_post_meta(get_the_ID(), '_wq_has_preferred_edging', true);
            $preferred_edge_services = get_post_meta(get_the_ID(), '_wq_preferred_edge_services', true);
            if (!is_array($preferred_edge_services)) {
                $legacy_single = get_post_meta(get_the_ID(), '_wq_preferred_edge_service', true);
                $preferred_edge_services = $legacy_single ? array($legacy_single) : array();
            }

			$cats = get_the_terms( get_the_ID(), 'product_cat' );
			$cat_ids = array();
            $cat_names = array();
			if ( $cats && ! is_wp_error( $cats ) ) {
				foreach ( $cats as $cat ) {
					$cat_ids[] = $cat->term_id;
                    $cat_names[] = $cat->name;
				}
			}

            // Get Image
            $image_url = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
            if (!$image_url) {
                $image_url = wc_placeholder_img_src();
            }

			$product_list[] = array(
				'id'        => get_the_ID(),
				'name'      => get_the_title(),
				'sku'       => $product->get_sku(),
                'price'     => $product->get_price(), // Standard Price
                'custom_attributes' => $dynamic_data, // Pass all custom attributes
                'has_edgebanding' => $has_edgebanding === 'yes',
                'has_operations' => $has_operations === 'yes',
                'operation_indexes' => $operation_indexes,
                'has_preferred_edging' => $has_preferred_edging === 'yes',
                'preferred_edge_services' => $preferred_edge_services,
                'categories'=> $cat_ids,
                'category_names' => $cat_names,
                'image'     => $image_url
			);
		}
		wp_reset_postdata();
	}
    
    // Fetch all edgebanding data (Profiles & Services)
    $edge_data = array();
    
    // 1. Get Profiles (Visuals)
    $profiles = get_posts(array(
        'post_type' => 'wq_edge_profile',
        'numberposts' => -1,
        'post_status' => 'publish',
    ));
    
    $profiles_data = array();
    foreach ($profiles as $p) {
        $img_url = get_the_post_thumbnail_url($p->ID, 'full'); // Use full size for better quality in popup
        $profiles_data[$p->ID] = array(
            'id' => $p->ID,
            'name' => $p->post_title,
            'image' => $img_url ? $img_url : '', // Handle no image case in JS
        );
    }
    
    // 2. Get Services (Materials)
    $services = get_posts(array(
        'post_type' => 'wq_edge_service',
        'numberposts' => -1,
        'post_status' => 'publish',
    ));
    
    $services_data = array();
    foreach ($services as $s) {
        // Fetch new config structure
        $profile_config = get_post_meta($s->ID, '_wq_profile_config', true);
        // Default price as fallback
        $default_price = get_post_meta($s->ID, '_wq_edge_price', true);
        // Fetch Edge Code
        $code = get_post_meta($s->ID, '_wq_edge_code', true);
        
        $services_data[$s->ID] = array(
            'id' => $s->ID,
            'name' => $s->post_title,
            'code' => $code, // Pass code to frontend
            'profiles' => is_array($profile_config) ? $profile_config : array(), // Contains active profiles and their sides
            'default_price' => $default_price
        );
    }
    
    $edge_data = array(
        'profiles' => $profiles_data,
        'services' => $services_data
    );

    // Get all allowed categories for the filter buttons
    $categories_data = array();
    if ( ! empty( $allowed_cats ) ) {
        $terms = get_terms( array(
            'taxonomy' => 'product_cat',
            'include'  => $allowed_cats,
            'hide_empty' => false,
        ) );
        
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                // Only add to root if parent is NOT in allowed list
                if ( in_array($term->parent, $allowed_cats) ) {
                    continue; 
                }
                
                $categories_data[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'children' => wq_get_allowed_children($term->term_id, $allowed_cats)
                );
            }
        }
    }

	wp_send_json_success( array( 'products' => $product_list, 'categories' => $categories_data, 'edge_data' => $edge_data ) );
}

// Helper function moved outside
function wq_get_allowed_children($parent_id, $allowed_cats) {
    $children_terms = get_terms( array(
        'taxonomy' => 'product_cat',
        'parent'   => $parent_id,
        'hide_empty' => false
    ) );
    
    $children_data = array();
    if ( ! is_wp_error( $children_terms ) ) {
        foreach ($children_terms as $child) {
            if ( in_array($child->term_id, $allowed_cats) ) {
                $children_data[] = array(
                    'id' => $child->term_id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'parent' => $parent_id,
                    'children' => wq_get_allowed_children($child->term_id, $allowed_cats)
                );
            }
        }
    }
    return $children_data;
}
add_action( 'wp_ajax_wq_builder_get_products', 'wq_builder_get_products' );
add_action( 'wp_ajax_nopriv_wq_builder_get_products', 'wq_builder_get_products' );
