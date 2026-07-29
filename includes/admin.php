<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add Admin Menu
function wq_builder_add_admin_menu() {
    // Add Settings Submenu under 'Saved Quotes' (edit.php?post_type=wq_quote)
	add_submenu_page(
		'edit.php?post_type=wq_quote', // Parent slug
		'Quote Builder Settings',
		'Settings',
		'manage_options',
		'wq-builder-settings',
		'wq_builder_settings_page'
	);
    
    // Add PDF Template Submenu
    add_submenu_page(
        'edit.php?post_type=wq_quote',
        'PDF Template Settings',
        'PDF Template',
        'manage_options',
        'wq-pdf-template',
        'wq_pdf_template_page'
    );

    // Add Tour Settings Submenu
    add_submenu_page(
        'edit.php?post_type=wq_quote',
        'Tour Settings',
        'Tour Guide',
        'manage_options',
        'wq-tour-settings',
        'wq_tour_settings_page'
    );

    // Add Email Template Submenu
    add_submenu_page(
        'edit.php?post_type=wq_quote',
        'Email Template Settings',
        'Email Template',
        'manage_options',
        'wq-email-template',
        'wq_email_template_page'
    );

    // Add Formula & Fields Submenu
    add_submenu_page(
        'edit.php?post_type=wq_quote',
        'Formula & Fields',
        'Formula & Fields',
        'manage_options',
        'wq-formula-fields',
        'wq_formula_fields_page'
    );

    // Add Operations Submenu
    add_submenu_page(
        'edit.php?post_type=wq_quote',
        'Operations Settings',
        'Operations',
        'manage_options',
        'wq-operations-settings',
        'wq_operations_settings_page'
    );

    // Add CSS Classes Submenu
    add_submenu_page(
        'edit.php?post_type=wq_quote',
        'CSS Classes & Styles',
        'CSS Classes',
        'manage_options',
        'wq-css-classes',
        'wq_css_classes_page'
    );
}
add_action( 'admin_menu', 'wq_builder_add_admin_menu' );

// Operations Page
function wq_operations_settings_page() {
    ?>
    <div class="wrap">
        <h1>Operations Settings</h1>
        <p>Define operations (e.g. Saw, Drill) that can be assigned to edging services. These will add extra costs.</p>
        
        <form method="post" action="options.php">
            <?php settings_fields( 'wq_operations_settings_group' ); ?>
            <?php do_settings_sections( 'wq_operations_settings_group' ); ?>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <h2>Manage Operations</h2>
                <table class="widefat" id="wq-operations-table">
                    <thead>
                        <tr>
                            <th>Operation Name</th>
                            <th>Price (<?php echo function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$'; ?>)</th>
                            <th>Cost Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="wq-operations-tbody">
                        <?php
                        $operations = get_option('wq_edge_operations', array());
                        if (empty($operations)) {
                            // Example default
                            // $operations = array(array('name' => 'Saw Cut', 'price' => '5.00', 'type' => 'fixed'));
                        }
                        
                        foreach ($operations as $index => $op) {
                            $type = isset($op['type']) ? $op['type'] : 'fixed';
                            ?>
                            <tr class="wq-op-row">
                                <td><input type="text" name="wq_edge_operations[<?php echo $index; ?>][name]" value="<?php echo esc_attr($op['name']); ?>" required /></td>
                                <td><input type="number" step="0.01" name="wq_edge_operations[<?php echo $index; ?>][price]" value="<?php echo esc_attr($op['price']); ?>" required /></td>
                                <td>
                                    <select name="wq_edge_operations[<?php echo $index; ?>][type]">
                                        <option value="fixed" <?php selected($type, 'fixed'); ?>>Fixed Cost (Per Side)</option>
                                        <option value="meter" <?php selected($type, 'meter'); ?>>Per Meter</option>
                                        <option value="m2" <?php selected($type, 'm2'); ?>>Per m2</option>
                                    </select>
                                </td>
                                <td><button type="button" class="button wq-remove-op">Remove</button></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                <p><button type="button" class="button button-primary" id="wq-add-op">Add New Operation</button></p>
            </div>

            <?php submit_button(); ?>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wq-add-op').click(function() {
                var index = $('#wq-operations-tbody tr').length;
                var row = '<tr class="wq-op-row">' +
                    '<td><input type="text" name="wq_edge_operations[' + index + '][name]" placeholder="Name" required /></td>' +
                    '<td><input type="number" step="0.01" name="wq_edge_operations[' + index + '][price]" placeholder="0.00" required /></td>' +
                    '<td><select name="wq_edge_operations[' + index + '][type]"><option value="fixed">Fixed Cost (Per Side)</option><option value="meter">Per Meter</option><option value="m2">Per m2</option></select></td>' +
                    '<td><button type="button" class="button wq-remove-op">Remove</button></td>' +
                    '</tr>';
                $('#wq-operations-tbody').append(row);
            });
            
            $(document).on('click', '.wq-remove-op', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
    </div>
    <?php
}

function wq_register_operations_settings() {
    register_setting( 'wq_operations_settings_group', 'wq_edge_operations' );
}
add_action( 'admin_init', 'wq_register_operations_settings' );

// CSS Classes Page
function wq_css_classes_page() {
    ?>
    <div class="wrap">
        <h1>CSS Classes & Custom Styles</h1>
        <p>Here you can find a reference list of all CSS classes and IDs used in the Quote Builder frontend. You can use these to customize the appearance.</p>
        
        <form method="post" action="options.php">
            <?php settings_fields( 'wq_css_settings_group' ); ?>
            <?php do_settings_sections( 'wq_css_settings_group' ); ?>
            
            <div style="display:flex; gap:20px; align-items:flex-start;">
                <!-- Left Column: Reference List -->
                <div style="flex:1; background:#fff; padding:20px; border:1px solid #ddd; max-height: 800px; overflow-y: auto;">
                    <h2>CSS Reference Guide</h2>
                    
                    <h3>Main Structure</h3>
                    <ul style="list-style:disc; margin-left:20px;">
                        <li><code>.wq-builder-container</code> - Main wrapper for the entire Quote Builder.</li>
                        <li><code>.wq-header-tour</code> - Header section containing the Tour Guide button.</li>
                        <li><code>.wq-project-info</code> - Container for Project Details (Name, Client, etc.).</li>
                        <li><code>.wq-table-header</code> - Header row of the main product grid (Desktop).</li>
                        <li><code>.wq-mobile-header</code> - Header row for mobile devices (hidden by default).</li>
                        <li><code>#wq-rows-container</code> - Container holding all the product rows.</li>
                        <li><code>.wq-row</code> - Individual product row container.</li>
                        <li><code>.wq-actions</code> - Container for "Add Row" and other main action buttons.</li>
                        <li><code>.wq-summary-container</code> - Container for the Quote Summary section.</li>
                        <li><code>.wq-footer-text</code> - Footer text area (Login/Register prompts).</li>
                    </ul>

                    <h3>Tour Guide</h3>
                    <ul style="list-style:disc; margin-left:20px;">
                        <li><code>.wq-tour-btn</code> - "Take a Tour" button.</li>
                        <li><code>#wq-tour-overlay</code> - Dark overlay background during the tour.</li>
                        <li><code>#wq-tour-modal</code> - The main popup modal for tour steps.</li>
                        <li><code>.wq-tour-header</code> - Header of the tour modal (Green background).</li>
                        <li><code>.wq-tour-body</code> - Content area of the tour modal.</li>
                        <li><code>.wq-tour-footer</code> - Footer area with Back/Next buttons.</li>
                        <li><code>.wq-tour-highlight</code> - Class added to elements being highlighted during the tour.</li>
                        <li><code>.wq-tour-arrow</code> - The directional arrow pointing to elements.</li>
                    </ul>

                    <h3>Inputs & Form Elements</h3>
                    <ul style="list-style:disc; margin-left:20px;">
                        <li><code>.wq-form-group</code> - Wrapper for input fields (label + input).</li>
                        <li><code>.wq-input</code> - Standard text input class.</li>
                        <li><code>.wq-input-small</code> - Smaller inputs used inside the grid rows.</li>
                        <li><code>#wq-project-name</code> - ID for Project Name input.</li>
                        <li><code>#wq-client-name</code> - ID for Client Name input.</li>
                        <li><code>#wq-quote-ref</code> - ID for Quote Reference display.</li>
                        <li><code>.wq-notes-display</code> - Display area for row-specific notes.</li>
                        <li><code>#wq-project-notes</code> - ID for main Project Notes textarea.</li>
                        <li><code>.wq-checkbox</code> - Custom checkbox style (used for Edging).</li>
                        <li><code>.wq-readonly</code> - Applied to readonly inputs (greyed out).</li>
                    </ul>

                    <h3>Product Selection</h3>
                    <ul style="list-style:disc; margin-left:20px;">
                        <li><code>.wq-product-selector</code> - Wrapper for the product dropdown.</li>
                        <li><code>.wq-product-search</code> - The input field acting as the search box.</li>
                        <li><code>.wq-product-dropdown</code> - The dropdown container showing results.</li>
                        <li><code>.wq-search-input</code> - The text input inside the dropdown.</li>
                        <li><code>.wq-category-filters</code> - Container for category filter buttons.</li>
                        <li><code>.wq-cat-btn</code> - Category filter button.</li>
                        <li><code>.wq-product-list</code> - Scrollable list of products.</li>
                        <li><code>.wq-product-item</code> - Individual product item in the list.</li>
                        <li><code>.wq-p-image</code>, <code>.wq-p-name</code>, <code>.wq-p-meta</code> - Product details.</li>
                    </ul>

                    <h3>Grid / Table Rows</h3>
                    <ul style="list-style:disc; margin-left:20px;">
                        <li><code>.wq-row-num</code> - The row number circle (also acts as drag handle).</li>
                        <li><code>.wq-row-thickness</code>, <code>.wq-row-length</code>, <code>.wq-row-width</code>, <code>.wq-row-qty</code> - Wrappers for specific dimensions.</li>
                        <li><code>.wq-edge-labels</code> - Container for Top/Bottom/Left/Right labels.</li>
                        <li><code>.wq-edge-checkboxes</code> - Container for the 4 edging checkboxes.</li>
                        <li><code>.wq-row-label</code> - Container for the custom label/note field.</li>
                        <li><code>.wq-label-popup</code> - The popup for editing custom labels.</li>
                    </ul>

                    <h3>Buttons & Icons</h3>
                    <ul style="list-style:disc; margin-left:20px;">
                        <li><code>.wq-btn</code> - Base class for buttons.</li>
                        <li><code>.wq-btn-green</code> - Green button style (Add Row).</li>
                        <li><code>.wq-btn-white</code> - White button style (Clear All).</li>
                        <li><code>.wq-remove-btn</code> - Circular "X" button to remove a row.</li>
                        <li><code>.wq-edit-label</code> - Pencil icon button for editing labels.</li>
                        <li><code>.wq-icon-btn</code> - General icon button class.</li>
                    </ul>

                    <h3>Summary & Actions</h3>
                    <ul style="list-style:disc; margin-left:20px;">
                        <li><code>.wq-summary-header</code> - "Quote Summary" title.</li>
                        <li><code>.wq-summary-section-title</code> - Subtitles in summary (e.g., "Board Material").</li>
                        <li><code>.wq-summary-table-row</code> - Individual line item in summary.</li>
                        <li><code>.wq-summary-total-row</code> - The Grand Total row.</li>
                        <li><code>.wq-discount-banner</code> - Yellow banner showing discount info.</li>
                        <li><code>.wq-summary-actions</code> - Container for bottom action buttons.</li>
                        <li><code>.wq-btn-basket</code> - "Add to Basket" button.</li>
                        <li><code>.wq-btn-save</code> - "Save Quote" button.</li>
                        <li><code>.wq-btn-pdf</code> - "Download PDF" button.</li>
                        <li><code>.wq-add-saved-to-cart-btn</code> - Button in Saved Quotes list to add to cart.</li>
                    </ul>

                    <h3>Notifications & Modals</h3>
                    <ul style="list-style:disc; margin-left:20px;">
                        <li><code>#wq-notification</code> - The popup notification box.</li>
                        <li><code>.wq-notify-success</code> - Green success notification.</li>
                        <li><code>.wq-notify-error</code> - Red error notification.</li>
                        <li><code>.wq-hidden-notes</code> - The popup for editing project notes.</li>
                    </ul>
                </div>
                
                <!-- Right Column: Custom CSS Editor -->
                <div style="flex:1; background:#fff; padding:20px; border:1px solid #ddd;">
                    <h2>Custom CSS</h2>
                    <p>Enter your custom CSS here. It will be loaded on the Quote Builder page.</p>
                    <textarea name="wq_custom_css" rows="25" class="large-text code" placeholder=".wq-btn-green { background-color: #0073aa; }"><?php echo esc_textarea( get_option('wq_custom_css') ); ?></textarea>
                    <p class="description">Use <code>!important</code> if necessary to override default styles.</p>
                    
                    <?php submit_button(); ?>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function wq_register_css_settings() {
    register_setting( 'wq_css_settings_group', 'wq_custom_css' );
}
add_action( 'admin_init', 'wq_register_css_settings' );

// Formula & Fields Page
function wq_formula_fields_page() {
    ?>
    <div class="wrap">
        <h1>Formula & Fields Configuration</h1>
        <p>Define custom fields for your products and set the pricing formula.</p>
        
        <form method="post" action="options.php">
            <?php settings_fields( 'wq_formula_settings_group' ); ?>
            <?php do_settings_sections( 'wq_formula_settings_group' ); ?>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <h2>1. Define Custom Fields</h2>
                <p class="description">Add fields that will appear in the WooCommerce Product Data tab. Use these fields in your formula.</p>
                
                <table class="widefat" id="wq-fields-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Slug (Variable Name)</th>
                            <th>Type</th>
                            <th>Placeholder</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="wq-fields-tbody">
                        <?php
                        $fields = get_option('wq_custom_fields', array());
                        // Default fields if empty (Length, Width, Thickness, Price/m2)
                        if (empty($fields)) {
                            $fields = array(
                                array('label' => 'Thickness (mm)', 'slug' => 'thickness', 'type' => 'number', 'placeholder' => '18', 'desc' => 'Material thickness'),
                                array('label' => 'Price per MM²', 'slug' => 'wq_pricing_per_mm', 'type' => 'number', 'placeholder' => '0.00005', 'desc' => 'Price per square mm'),
                                array('label' => 'Max Length (mm)', 'slug' => 'wq_max_length', 'type' => 'number', 'placeholder' => '2440', 'desc' => 'Maximum length'),
                                array('label' => 'Max Width (mm)', 'slug' => 'wq_max_width', 'type' => 'number', 'placeholder' => '1220', 'desc' => 'Maximum width'),
                                array('label' => 'Min Length (mm)', 'slug' => 'wq_min_length', 'type' => 'number', 'placeholder' => '100', 'desc' => 'Minimum length'),
                                array('label' => 'Min Width (mm)', 'slug' => 'wq_min_width', 'type' => 'number', 'placeholder' => '100', 'desc' => 'Minimum width'),
                            );
                        }
                        
                        foreach ($fields as $index => $field) {
                            ?>
                            <tr class="wq-field-row">
                                <td><input type="text" name="wq_custom_fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr($field['label']); ?>" /></td>
                                <td><input type="text" name="wq_custom_fields[<?php echo $index; ?>][slug]" value="<?php echo esc_attr($field['slug']); ?>" readonly style="background:#eee;" /></td>
                                <td>
                                    <select name="wq_custom_fields[<?php echo $index; ?>][type]">
                                        <option value="number" <?php selected($field['type'], 'number'); ?>>Number</option>
                                        <option value="text" <?php selected($field['type'], 'text'); ?>>Text</option>
                                    </select>
                                </td>
                                <td><input type="text" name="wq_custom_fields[<?php echo $index; ?>][placeholder]" value="<?php echo esc_attr($field['placeholder']); ?>" /></td>
                                <td><input type="text" name="wq_custom_fields[<?php echo $index; ?>][desc]" value="<?php echo esc_attr($field['desc']); ?>" /></td>
                                <td>
                                    <?php if (!in_array($field['slug'], ['thickness', 'wq_pricing_per_mm', 'wq_max_length', 'wq_max_width', 'wq_min_length', 'wq_min_width'])) : ?>
                                        <button type="button" class="button wq-remove-field">Remove</button>
                                    <?php else: ?>
                                        <span class="description">System Field</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                <p><button type="button" class="button button-primary" id="wq-add-field">Add New Field</button></p>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <h2>2. Label Dropdown Menu</h2>
                <p class="description"><strong>This is a label dropdown menu, these values would be displayed under the select option in the frontend.</strong></p>

                <?php
                $label_options = get_option('wq_label_dropdown_options', array());
                if (!is_array($label_options)) {
                    $label_options = wq_sanitize_label_dropdown_options($label_options);
                }
                $label_options_text = implode("\n", $label_options);
                ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Dropdown Values</th>
                        <td>
                            <textarea name="wq_label_dropdown_options" rows="10" class="large-text code" style="max-width: 900px;"><?php echo esc_textarea($label_options_text); ?></textarea>
                            <p class="description">Enter one value per line.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ddd;">
                <h2>3. Pricing Formula</h2>
                <p class="description">Define how the row total should be calculated. Use the variables below:</p>
                <?php
                $dim_unit = get_option('wq_dimension_unit', 'mm');
                $dim_unit_label = 'mm';
                if ($dim_unit === 'cm') $dim_unit_label = 'cm';
                if ($dim_unit === 'm') $dim_unit_label = 'm';
                if ($dim_unit === 'in') $dim_unit_label = 'in';
                ?>
                <ul style="background:#f9f9f9; padding:10px; border:1px solid #eee;">
                    <li><code>{length}</code> - User Input Length (<?php echo esc_html($dim_unit_label); ?>)</li>
                    <li><code>{width}</code> - User Input Width (<?php echo esc_html($dim_unit_label); ?>)</li>
                    <li><code>{length_mm}</code> - User Input Length (mm)</li>
                    <li><code>{width_mm}</code> - User Input Width (mm)</li>
                    <li><code>{length_m}</code> - User Input Length (m)</li>
                    <li><code>{width_m}</code> - User Input Width (m)</li>
                    <li><code>{area_mm2}</code> - Area (mm²)</li>
                    <li><code>{area_m2}</code> - Area (m²)</li>
                    <li><code>{perimeter_mm}</code> - Perimeter (mm)</li>
                    <li><code>{perimeter_m}</code> - Perimeter (m)</li>
                    <li><code>{price}</code> - WooCommerce product price (fallback variable)</li>
                    <li><code>{price_per_mm2}</code> - Price per mm² (derived from product meta if available)</li>
                    <li><code>{price_per_m2}</code> - Price per m² (derived from product meta if available)</li>
                    <li><code>{qty}</code> - User Input Quantity</li>
                    <!-- Dynamic Fields -->
                    <?php
                    foreach ($fields as $field) {
                        echo '<li><code>{' . esc_html($field['slug']) . '}</code> - ' . esc_html($field['label']) . '</li>';
                    }
                    ?>
                </ul>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Dimension Input Unit</th>
                        <td>
                            <select name="wq_dimension_unit">
                                <option value="mm" <?php selected($dim_unit, 'mm'); ?>>Millimeters (mm)</option>
                                <option value="cm" <?php selected($dim_unit, 'cm'); ?>>Centimeters (cm)</option>
                                <option value="m" <?php selected($dim_unit, 'm'); ?>>Meters (m)</option>
                                <option value="in" <?php selected($dim_unit, 'in'); ?>>Inches (in)</option>
                            </select>
                            <p class="description">Controls what unit the customer enters for Length and Width. The pricing formula uses <code>{length}</code> and <code>{width}</code> in this unit.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Formula Expression</th>
                        <td>
                            <textarea name="wq_pricing_formula" rows="3" class="large-text code"><?php echo esc_textarea( get_option('wq_pricing_formula', '({length} * {width}) * {wq_pricing_per_mm} * {qty}') ); ?></textarea>
                            <p class="description">
                                Example (Price per m²): <code>{area_m2} * {wq_pricing_per_mm} * {qty}</code><br>
                                Example (Using input unit): <code>({length} * {width}) * {wq_pricing_per_mm} * {qty}</code><br>
                                Tip: If <code>{wq_pricing_per_mm}</code> is empty or 0 on a product, the calculation will fallback to the field placeholder value from "Fields Configuration".<br>
                                Note: Basic math operators (+, -, *, /) and parentheses are supported.
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Show "Keep Offcuts"</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wq_keep_offcuts_enable" value="1" <?php checked( 1, get_option( 'wq_keep_offcuts_enable', 1 ), true ); ?> />
                                Show the "Keep Offcuts" option in the quote summary.
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">"Keep Offcuts" Tooltip Text</th>
                        <td>
                            <textarea name="wq_keep_offcuts_tooltip" rows="4" class="large-text code" style="max-width: 900px;"><?php echo esc_textarea( get_option('wq_keep_offcuts_tooltip', "If checked, we'll also include all the larger offcuts from your boards. These might be reduced to a more manageable size of no less than 1500mm wide for transport." ) ); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <h2>4. Edgebanding Configuration</h2>
                <p class="description">Configure the formula and units for edgebanding calculations.</p>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Edgebanding Unit Label</th>
                        <td>
                            <?php $edge_unit_label = (string) get_option('wq_edge_unit_label', 'M'); ?>
                            <select name="wq_edge_unit_label" style="width: 220px;">
                                <?php
                                $edge_unit_options = array('M', 'MM', 'CM', 'FT', 'IN', 'M²', 'M2');
                                $edge_unit_options = array_values(array_unique($edge_unit_options));
                                if ( $edge_unit_label !== '' && ! in_array($edge_unit_label, $edge_unit_options, true) ) {
                                    echo '<option value="'.esc_attr($edge_unit_label).'" selected>'.esc_html($edge_unit_label).'</option>';
                                }
                                foreach ( $edge_unit_options as $opt ) {
                                    echo '<option value="'.esc_attr($opt).'" '.selected($edge_unit_label, $opt, false).'>'.esc_html($opt).'</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Shown in the quote summary for edgebanding amounts (e.g., M, MM, FT).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Edgebanding Pricing Formula (Per Side)</th>
                        <td>
                            <p class="description">Define how edgebanding cost is calculated per side.</p>
                            <p><strong>L1 Formula</strong></p>
                            <textarea name="wq_edge_formula_l1" rows="2" class="large-text code"><?php echo esc_textarea( get_option('wq_edge_formula_l1', '') ); ?></textarea>
                            <p><strong>L2 Formula</strong></p>
                            <textarea name="wq_edge_formula_l2" rows="2" class="large-text code"><?php echo esc_textarea( get_option('wq_edge_formula_l2', '') ); ?></textarea>
                            <p><strong>W1 Formula</strong></p>
                            <textarea name="wq_edge_formula_w1" rows="2" class="large-text code"><?php echo esc_textarea( get_option('wq_edge_formula_w1', '') ); ?></textarea>
                            <p><strong>W2 Formula</strong></p>
                            <textarea name="wq_edge_formula_w2" rows="2" class="large-text code"><?php echo esc_textarea( get_option('wq_edge_formula_w2', '') ); ?></textarea>
                            <p class="description">
                                <strong>Variables (Per-Side formulas):</strong><br>
                                <code>{side_mm}</code> / <code>{side_m}</code> - Current side length (L uses board length, W uses board width)<br>
                                <code>{length_mm}</code> / <code>{length_m}</code> - Board length<br>
                                <code>{width_mm}</code> / <code>{width_m}</code> - Board width<br>
                                <code>{price}</code> - Service price<br>
                                <code>{qty}</code> - Quantity<br>
                                <br>
                                Example L1/L2 (use length): <code>{length_m} * {price} * {qty}</code><br>
                                Example W1/W2 (use width): <code>{width_m} * {price} * {qty}</code><br>
                                Example use side length: <code>{side_m} * {price} * {qty}</code>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <h2>5. Validation & Tooltips Configuration</h2>
                <p class="description">Map your custom fields to the validation logic. These fields will be used to validate inputs and generate tooltips (e.g. "Min: 100mm").</p>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Min Length Field</th>
                        <td>
                            <select name="wq_map_min_len">
                                <option value="">-- None --</option>
                                <?php 
                                $current_min_len = get_option('wq_map_min_len', 'wq_min_length');
                                foreach ($fields as $f) { 
                                    echo '<option value="'.esc_attr($f['slug']).'" '.selected($current_min_len, $f['slug'], false).'>'.esc_html($f['label']).' ('.esc_html($f['slug']).')</option>'; 
                                } 
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Max Length Field</th>
                        <td>
                            <select name="wq_map_max_len">
                                <option value="">-- None --</option>
                                <?php 
                                $current_max_len = get_option('wq_map_max_len', 'wq_max_length');
                                foreach ($fields as $f) { 
                                    echo '<option value="'.esc_attr($f['slug']).'" '.selected($current_max_len, $f['slug'], false).'>'.esc_html($f['label']).' ('.esc_html($f['slug']).')</option>'; 
                                } 
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Min Width Field</th>
                        <td>
                            <select name="wq_map_min_wid">
                                <option value="">-- None --</option>
                                <?php 
                                $current_min_wid = get_option('wq_map_min_wid', 'wq_min_width');
                                foreach ($fields as $f) { 
                                    echo '<option value="'.esc_attr($f['slug']).'" '.selected($current_min_wid, $f['slug'], false).'>'.esc_html($f['label']).' ('.esc_html($f['slug']).')</option>'; 
                                } 
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Max Width Field</th>
                        <td>
                            <select name="wq_map_max_wid">
                                <option value="">-- None --</option>
                                <?php 
                                $current_max_wid = get_option('wq_map_max_wid', 'wq_max_width');
                                foreach ($fields as $f) { 
                                    echo '<option value="'.esc_attr($f['slug']).'" '.selected($current_max_wid, $f['slug'], false).'>'.esc_html($f['label']).' ('.esc_html($f['slug']).')</option>'; 
                                } 
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(); ?>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wq-add-field').click(function() {
                var index = $('#wq-fields-tbody tr').length;
                var row = '<tr class="wq-field-row">' +
                    '<td><input type="text" name="wq_custom_fields[' + index + '][label]" placeholder="Label" required /></td>' +
                    '<td><input type="text" name="wq_custom_fields[' + index + '][slug]" placeholder="slug_name" required /></td>' +
                    '<td><select name="wq_custom_fields[' + index + '][type]"><option value="number">Number</option><option value="text">Text</option></select></td>' +
                    '<td><input type="text" name="wq_custom_fields[' + index + '][placeholder]" /></td>' +
                    '<td><input type="text" name="wq_custom_fields[' + index + '][desc]" /></td>' +
                    '<td><button type="button" class="button wq-remove-field">Remove</button></td>' +
                    '</tr>';
                $('#wq-fields-tbody').append(row);
            });
            
            $(document).on('click', '.wq-remove-field', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
    </div>
    <?php
}

function wq_sanitize_label_dropdown_options($value) {
    if (is_array($value)) {
        $lines = $value;
    } else {
        $lines = preg_split('/\R+/', (string) $value);
    }

    $out = array();
    foreach ($lines as $line) {
        $line = trim(wp_strip_all_tags((string) $line));
        if ($line === '') {
            continue;
        }
        $out[] = $line;
    }

    return array_values(array_unique($out));
}

function wq_register_formula_settings() {
    register_setting( 'wq_formula_settings_group', 'wq_custom_fields' );
    register_setting( 'wq_formula_settings_group', 'wq_pricing_formula' );
    register_setting( 'wq_formula_settings_group', 'wq_label_dropdown_options', array('sanitize_callback' => 'wq_sanitize_label_dropdown_options') );
    register_setting( 'wq_formula_settings_group', 'wq_dimension_unit' );
    register_setting( 'wq_formula_settings_group', 'wq_edge_unit_label' );
    register_setting( 'wq_formula_settings_group', 'wq_edge_formula' );
    register_setting( 'wq_formula_settings_group', 'wq_edge_formula_l1' );
    register_setting( 'wq_formula_settings_group', 'wq_edge_formula_l2' );
    register_setting( 'wq_formula_settings_group', 'wq_edge_formula_w1' );
    register_setting( 'wq_formula_settings_group', 'wq_edge_formula_w2' );
    register_setting( 'wq_formula_settings_group', 'wq_keep_offcuts_enable' );
    register_setting( 'wq_formula_settings_group', 'wq_keep_offcuts_tooltip', array('sanitize_callback' => 'sanitize_textarea_field') );
    
    // Validation Maps
    register_setting( 'wq_formula_settings_group', 'wq_map_min_len' );
    register_setting( 'wq_formula_settings_group', 'wq_map_max_len' );
    register_setting( 'wq_formula_settings_group', 'wq_map_min_wid' );
    register_setting( 'wq_formula_settings_group', 'wq_map_max_wid' );
}
add_action( 'admin_init', 'wq_register_formula_settings' );

// Tour Settings Page
function wq_tour_settings_page() {
    ?>
    <div class="wrap">
        <h1>Guided Tour Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wq_tour_settings_group' ); ?>
            <?php do_settings_sections( 'wq_tour_settings_group' ); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable Tour Guide</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wq_tour_enable" value="1" <?php checked( 1, get_option( 'wq_tour_enable', 1 ), true ); ?> />
                            Show "Show Guided Tour" button on the frontend.
                        </label>
                    </td>
                </tr>
            </table>
            
            <h2>Tour Steps Content</h2>
            <p class="description">Customize the title and content for each step of the tour.</p>
            
            <?php
            $steps = array(
                'step_1' => 'Context Help',
                'step_2' => 'Project Details',
                'step_3' => 'Overview',
                'step_4' => 'Select Material',
                'step_5' => 'Required Sizes',
                'step_6' => 'Edging Indicators',
                'step_7' => 'Edgebanding Panel',
                'step_8' => 'Edging Finish',
                'step_9' => 'Labelling',
                'step_10' => 'Quote Pricing',
                'step_11' => 'Saving & Account'
            );
            
            foreach ($steps as $key => $label) {
                $title_opt = 'wq_tour_' . $key . '_title';
                $content_opt = 'wq_tour_' . $key . '_content';
                
                // Defaults
                $default_title = ''; 
                $default_content = '';
                // We could set defaults here or just rely on JS defaults if empty.
                // Better to let JS have hardcoded defaults and only override if these are set.
                // But user wants to EDIT them. So we should pre-fill with defaults if first time.
                // For now, let's leave empty implies "Use Default".
                
                echo '<h3>' . $label . '</h3>';
                echo '<table class="form-table">
                    <tr valign="top">
                        <th scope="row">Title</th>
                        <td><input type="text" name="' . $title_opt . '" value="' . esc_attr( get_option( $title_opt ) ) . '" class="regular-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Content</th>
                        <td><textarea name="' . $content_opt . '" rows="3" class="large-text">' . esc_textarea( get_option( $content_opt ) ) . '</textarea></td>
                    </tr>
                </table>';
                echo '<hr>';
            }
            ?>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function wq_register_tour_settings() {
    register_setting( 'wq_tour_settings_group', 'wq_tour_enable' );
    
    $steps = range(1, 11);
    foreach ($steps as $i) {
        register_setting( 'wq_tour_settings_group', 'wq_tour_step_' . $i . '_title' );
        register_setting( 'wq_tour_settings_group', 'wq_tour_step_' . $i . '_content' );
    }
}
add_action( 'admin_init', 'wq_register_tour_settings' );

// Email Template Settings Page
function wq_email_template_page() {
    ?>
    <div class="wrap">
        <h1>Email Template Settings</h1>
        <p>Customize the emails sent to the Admin and the User when a quote is saved.</p>
        <p><strong>Available Placeholders:</strong></p>
        <ul style="background: #fff; padding: 15px; border: 1px solid #ddd; display: inline-block;">
            <li><code>{client_name}</code> - Client Name</li>
            <li><code>{project_name}</code> - Project Name</li>
            <li><code>{quote_ref}</code> - Quote Reference</li>
            <li><code>{quote_id}</code> - Quote ID</li>
            <li><code>{admin_quote_url}</code> - Admin Edit URL (Admin only)</li>
            <li><code>{user_quote_url}</code> - User Account URL (User only)</li>
            <li><code>{user_email}</code> - User Email</li>
            <li><code>{user_login}</code> - User Login Name</li>
        </ul>

        <form method="post" action="options.php">
            <?php settings_fields( 'wq_email_settings_group' ); ?>
            <?php do_settings_sections( 'wq_email_settings_group' ); ?>
            
            <hr>
            <h2>Admin Notification Email</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Subject</th>
                    <td><input type="text" name="wq_email_admin_subject" value="<?php echo esc_attr( get_option( 'wq_email_admin_subject', 'New Quote Saved: {project_name} ({quote_ref})' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Heading</th>
                    <td><input type="text" name="wq_email_admin_heading" value="<?php echo esc_attr( get_option( 'wq_email_admin_heading', 'New Quote Received' ) ); ?>" class="regular-text"></td>
                </tr>
            </table>
            <h3>Email Content</h3>
            <?php 
            $admin_content = get_option( 'wq_email_admin_content', '<p>A quote has been saved on the website.</p><p><strong>Project:</strong> {project_name}<br><strong>Reference:</strong> {quote_ref}<br><strong>Client:</strong> {client_name}</p><p>You can view this quote here: <a href="{admin_quote_url}">View Quote</a></p>' );
            wp_editor( $admin_content, 'wq_email_admin_content', array( 'textarea_name' => 'wq_email_admin_content', 'textarea_rows' => 10 ) ); 
            ?>
            
            <hr>
            <h2>User Notification Email</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Subject</th>
                    <td><input type="text" name="wq_email_user_subject" value="<?php echo esc_attr( get_option( 'wq_email_user_subject', 'Quote Saved: {project_name}' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Heading</th>
                    <td><input type="text" name="wq_email_user_heading" value="<?php echo esc_attr( get_option( 'wq_email_user_heading', 'Quote Saved Successfully' ) ); ?>" class="regular-text"></td>
                </tr>
            </table>
            <h3>Email Content</h3>
            <?php 
            $user_content = get_option( 'wq_email_user_content', '<p>Hello {client_name},</p><p>You have successfully saved a quote for project: <strong>{project_name}</strong>.</p><p>Reference: {quote_ref}</p><p>You can view or edit your saved quotes in your account dashboard.</p><p><a href="{user_quote_url}">View My Quotes</a></p><p>Thank you!</p>' );
            wp_editor( $user_content, 'wq_email_user_content', array( 'textarea_name' => 'wq_email_user_content', 'textarea_rows' => 10 ) ); 
            ?>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function wq_register_email_settings() {
    register_setting( 'wq_email_settings_group', 'wq_email_admin_subject' );
    register_setting( 'wq_email_settings_group', 'wq_email_admin_heading' );
    register_setting( 'wq_email_settings_group', 'wq_email_admin_content' );
    
    register_setting( 'wq_email_settings_group', 'wq_email_user_subject' );
    register_setting( 'wq_email_settings_group', 'wq_email_user_heading' );
    register_setting( 'wq_email_settings_group', 'wq_email_user_content' );
}
add_action( 'admin_init', 'wq_register_email_settings' );

// PDF Template Settings Page
function wq_pdf_template_page() {
    ?>
    <div class="wrap">
        <h1>PDF Template Settings</h1>
        <p>Customize the HTML structure of your PDF Quote. Use the following placeholders to insert dynamic data:</p>
        <ul style="background: #fff; padding: 15px; border: 1px solid #ddd; display: inline-block;">
            <li><code>{client_name}</code> - Client Name</li>
            <li><code>{project_name}</code> - Project Name</li>
            <li><code>{client_email}</code> - Client Email</li>
            <li><code>{quote_ref}</code> - Quote Reference</li>
            <li><code>{quote_date}</code> - Date of Quote</li>
            <li><code>{quote_notes}</code> - Customer Notes</li>
            <li><code>{keep_offcuts}</code> - Keep Offcuts (Yes/No)</li>
            <li><code>{keep_offcuts_line}</code> - Keep Offcuts HTML line (blank if not checked)</li>
            <li><code>{quote_table}</code> - The list of items (Product, Dimensions, Qty, Edging, Operations, Total)</li>
            <li><code>{grand_total}</code> - Grand Total (Including Edging & Operations)</li>
        </ul>

        <form method="post" action="options.php">
            <?php settings_fields( 'wq_pdf_template_group' ); ?>
            <?php do_settings_sections( 'wq_pdf_template_group' ); ?>
            
            <h2>Header Content</h2>
            <?php 
            $header_content = get_option( 'wq_pdf_header_content', '<h1>Quote Request</h1>' );
            wp_editor( $header_content, 'wq_pdf_header_content', array( 'textarea_name' => 'wq_pdf_header_content', 'textarea_rows' => 5 ) ); 
            ?>
            
            <h2>Body Content (Before Table)</h2>
            <?php 
            $body_content = get_option( 'wq_pdf_body_content', '<p><strong>Project:</strong> {project_name}</p><p><strong>Client:</strong> {client_name}</p><p><strong>Date:</strong> {quote_date}</p>' );
            wp_editor( $body_content, 'wq_pdf_body_content', array( 'textarea_name' => 'wq_pdf_body_content', 'textarea_rows' => 10 ) ); 
            ?>

            <h2>Footer Content (After Table)</h2>
            <?php 
            $footer_content = get_option( 'wq_pdf_footer_content', '<p>Thank you for your business!</p>' );
            wp_editor( $footer_content, 'wq_pdf_footer_content', array( 'textarea_name' => 'wq_pdf_footer_content', 'textarea_rows' => 5 ) ); 
            ?>
            
            <h2>Custom CSS</h2>
            <textarea name="wq_pdf_custom_css" rows="10" class="large-text code"><?php echo esc_textarea( get_option('wq_pdf_custom_css') ); ?></textarea>
            <p class="description">Add custom CSS for the PDF styling.</p>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function wq_register_pdf_settings() {
    register_setting( 'wq_pdf_template_group', 'wq_pdf_header_content' );
    register_setting( 'wq_pdf_template_group', 'wq_pdf_body_content' );
    register_setting( 'wq_pdf_template_group', 'wq_pdf_footer_content' );
    register_setting( 'wq_pdf_template_group', 'wq_pdf_custom_css' );
}
add_action( 'admin_init', 'wq_register_pdf_settings' );

// Settings Page Content
function wq_builder_settings_page() {
    // Category selection
    $categories = get_terms( 'product_cat', array( 'hide_empty' => false ) );
    ?>
    <div class="wrap">
        <h1>WooCommerce Quote Builder Settings</h1>
        
        <div class="notice notice-info inline" style="margin: 20px 0; padding: 15px; border-left-color: #007cba;">
            <h3 style="margin: 0 0 5px;">Getting Started</h3>
            <p>To display the Quote Builder on any page, simply insert the following shortcode into your page content:</p>
            <p><code style="font-size: 1.4em; display: inline-block; padding: 5px 10px; background: #fff; border: 1px solid #ccc;">[woo_quote_builder]</code></p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields( 'wq_builder_settings_group' ); ?>
            <?php do_settings_sections( 'wq_builder_settings_group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Select Categories for Quote Builder</th>
                    <td>
                        <input type="text" id="wq-cat-search" placeholder="Search Categories..." style="width: 100%; margin-bottom: 10px;">
                        
                        <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 15px; background: #fff; border-radius: 4px;">
                            <?php
                            $selected_cats = get_option('wq_builder_allowed_categories', array());
                            if ( ! is_array( $selected_cats ) ) {
                                $selected_cats = array();
                            }
                            
                            $args = array(
                                'taxonomy'     => 'product_cat',
                                'orderby'      => 'name',
                                'show_count'   => 0,
                                'pad_counts'   => 0,
                                'hierarchical' => 1,
                                'title_li'     => '',
                                'hide_empty'   => 0
                            );
                            
                            $all_categories = get_categories( $args );
                            
                            // Build a cleaner tree structure
                            function wq_render_cat_tree($categories, $parent_id = 0, $selected_cats = array()) {
                                // Filter cats for this parent
                                $children = array();
                                foreach ($categories as $cat) {
                                    if ($cat->parent == $parent_id) {
                                        $children[] = $cat;
                                    }
                                }
                                
                                if (!empty($children)) {
                                    echo '<ul class="wq-cat-tree" style="' . ($parent_id == 0 ? 'margin:0;' : 'margin-left:20px; display:none;') . '">';
                                    foreach ($children as $cat) {
                                        $checked = in_array( $cat->term_id, $selected_cats ) ? 'checked' : '';
                                        
                                        // Check if has sub-children
                                        $has_sub = false;
                                        foreach ($categories as $sub) {
                                            if ($sub->parent == $cat->term_id) {
                                                $has_sub = true;
                                                break;
                                            }
                                        }
                                        
                                        echo '<li>';
                                        echo '<div style="display:flex; align-items:center;">';
                                        if ($has_sub) {
                                            echo '<span class="wq-cat-toggle dashicons dashicons-arrow-right-alt2" style="cursor:pointer; color:#666; font-size:18px; width:18px; height:18px; margin-right:5px;"></span>';
                                        } else {
                                            echo '<span style="width:23px; display:inline-block;"></span>';
                                        }
                                        echo '<label class="wq-cat-label"><input type="checkbox" name="wq_builder_allowed_categories[]" value="' . esc_attr( $cat->term_id ) . '" ' . $checked . ' class="wq-cat-checkbox" data-id="' . $cat->term_id . '"> <span class="wq-cat-name">' . esc_html( $cat->name ) . '</span></label>';
                                        echo '</div>';
                                        
                                        // Recursion
                                        wq_render_cat_tree($categories, $cat->term_id, $selected_cats);
                                        
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                }
                            }
                            
                            wq_render_cat_tree($all_categories, 0, $selected_cats);
                            ?>
                        </div>
                        <p class="description">Select the categories that should use the Quote Builder layout. Click the arrow to expand sub-categories.</p>
                        
                        <style>
                            .wq-cat-tree { list-style: none; padding: 0; }
                            .wq-cat-tree li { margin-bottom: 5px; }
                            .wq-cat-toggle { transition: transform 0.2s; }
                            .wq-cat-toggle.open { transform: rotate(90deg); }
                        </style>
                        
                        <script>
                        jQuery(document).ready(function($) {
                            // Toggle Tree
                            $('.wq-cat-toggle').click(function(e) {
                                e.preventDefault();
                                $(this).toggleClass('open');
                                $(this).parent().next('ul').slideToggle(200);
                            });
                            
                            // Auto-open selected
                            $('.wq-cat-checkbox:checked').each(function() {
                                $(this).parents('ul').show().prev('div').find('.wq-cat-toggle').addClass('open');
                            });
                            
                            // Auto-select children
                            $('.wq-cat-checkbox').change(function() {
                                var isChecked = $(this).is(':checked');
                                var $li = $(this).closest('li');
                                
                                // Downwards: Select/Deselect all children
                                $li.find('input[type="checkbox"]').prop('checked', isChecked);
                                
                                // Upwards: If checked, check all parents
                                if (isChecked) {
                                    $(this).parents('li').each(function() {
                                        $(this).children('div').find('input[type="checkbox"]').prop('checked', true);
                                    });
                                } else {
                                    // Optional: If unchecked, should we uncheck parent?
                                    // Usually NO in "at least one" logic, but user request implies "if single child selected then parent marked".
                                    // This implies parent state reflects "contains selected child".
                                    // But standard WP behavior is separate.
                                    // The user request: "if even the single child is selected... parent should be marked".
                                    // This strictly means: Child Checked -> Parent Checked.
                                    // It does NOT strictly mean: Child Unchecked -> Parent Unchecked (unless all children unchecked?).
                                    // Let's stick to "Child Checked -> Parent Checked" only for now as requested.
                                }
                            });
                            
                            // Search Logic
                            $('#wq-cat-search').on('keyup', function() {
                                var value = $(this).val().toLowerCase();
                                
                                if (value === '') {
                                    // Reset view: hide all sub-levels unless selected or previously open
                                    // For simplicity, just collapse everything except top level, then re-open selected
                                    $('.wq-cat-tree li').show();
                                    $('.wq-cat-toggle').removeClass('open');
                                    $('.wq-cat-tree ul').hide();
                                    
                                    // Re-open selected
                                    $('.wq-cat-checkbox:checked').each(function() {
                                        $(this).parents('ul').show().prev('div').find('.wq-cat-toggle').addClass('open');
                                    });
                                    // Show top level
                                    $('.wq-cat-tree').first().show();
                                } else {
                                    // Hide all first
                                    $('.wq-cat-tree li').hide();
                                    
                                    // Find matches
                                    $('.wq-cat-name').filter(function() {
                                        return $(this).text().toLowerCase().indexOf(value) > -1;
                                    }).each(function() {
                                        var $li = $(this).closest('li');
                                        $li.show();
                                        // Show parents
                                        $li.parents('li').show();
                                        $li.parents('ul').show();
                                        // Open parent toggles
                                        $li.parents('li').children('div').find('.wq-cat-toggle').addClass('open');
                                        
                                        // Show children? Maybe not necessary for pure search, but helpful
                                        // Let's just show the match and its parents path
                                    });
                                }
                            });
                        });
                        </script>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Quote Builder Page URL</th>
                    <td>
                        <input type="text" name="wq_builder_quote_page_url" value="<?php echo esc_attr( get_option('wq_builder_quote_page_url') ); ?>" class="regular-text">
                        <p class="description">Enter the full URL of the page where the [woo_quote_builder] shortcode is placed.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Create Account URL</th>
                    <td>
                        <input type="text" name="wq_builder_create_account_url" value="<?php echo esc_attr( get_option('wq_builder_create_account_url') ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Sign In URL</th>
                    <td>
                        <input type="text" name="wq_builder_sign_in_url" value="<?php echo esc_attr( get_option('wq_builder_sign_in_url') ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Template PDF URL</th>
                    <td>
                        <input type="text" name="wq_builder_template_pdf_url" id="wq_builder_template_pdf_url" value="<?php echo esc_attr( get_option('wq_builder_template_pdf_url') ); ?>" class="regular-text">
                        <button type="button" class="button" id="wq_upload_pdf_btn">Upload PDF</button>
                        <p class="description">Upload a PDF template that users can download.</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($){
        $('#wq_upload_pdf_btn').click(function(e) {
            e.preventDefault();
            var image = wp.media({ 
                title: 'Upload Image',
                // mutiple: true if you want to upload multiple files at once
                multiple: false
            }).open()
            .on('select', function(e){
                // This will return the selected image from the Media Uploader, the result is an object
                var uploaded_image = image.state().get('selection').first();
                // We convert uploaded_image to a JSON object to make accessing it easier
                // Output to the console uploaded_image
                var image_url = uploaded_image.toJSON().url;
                // Let's assign the url value to the input field
                $('#wq_builder_template_pdf_url').val(image_url);
            });
        });
    });
    </script>
    <?php
}

function wq_builder_register_settings() {
    register_setting( 'wq_builder_settings_group', 'wq_builder_allowed_categories' );
    register_setting( 'wq_builder_settings_group', 'wq_builder_quote_page_url' );
    register_setting( 'wq_builder_settings_group', 'wq_builder_create_account_url' );
    register_setting( 'wq_builder_settings_group', 'wq_builder_sign_in_url' );
    register_setting( 'wq_builder_settings_group', 'wq_builder_template_pdf_url' );
}
add_action( 'admin_init', 'wq_builder_register_settings' );

// Enqueue Media Uploader scripts
function wq_admin_enqueue_media() {
    if ( isset($_GET['page']) && $_GET['page'] == 'wq-builder-settings' ) {
        wp_enqueue_media();
    }

    $fix_path = WQ_BUILDER_PATH . 'assets/js/investor-portal-fix.js';
    $fix_url = WQ_BUILDER_URL . 'assets/js/investor-portal-fix.js';
    $fix_ver = file_exists($fix_path) ? filemtime($fix_path) : WQ_BUILDER_VERSION;
    wp_enqueue_script('wq-investor-portal-fix-admin', $fix_url, array('jquery'), $fix_ver, true);
}
add_action('admin_enqueue_scripts', 'wq_admin_enqueue_media');

// Add Custom Fields to Product Data
add_filter( 'woocommerce_product_data_tabs', 'wq_builder_product_data_tab' );
function wq_builder_product_data_tab( $tabs ) {
    $tabs['wq_builder'] = array(
        'label'    => __( 'Quote Builder', 'woo-quote-builder' ),
        'target'   => 'wq_builder_product_data',
        'class'    => array( 'wq_builder_tab_item' ),
    );
    return $tabs;
}

add_action( 'woocommerce_product_data_panels', 'wq_builder_product_data_fields' );
function wq_builder_product_data_fields() {
    echo '<div id="wq_builder_product_data" class="panel woocommerce_options_panel hidden">';
    $fields = get_option('wq_custom_fields', array());
    $dim_unit = get_option('wq_dimension_unit', 'mm');
    $dim_step = '1';
    if ($dim_unit === 'm') $dim_step = '0.001';
    if ($dim_unit === 'cm') $dim_step = '0.1';
    if ($dim_unit === 'in') $dim_step = '0.01';
    $dim_keys = array(
        get_option('wq_map_min_len', 'wq_min_length'),
        get_option('wq_map_max_len', 'wq_max_length'),
        get_option('wq_map_min_wid', 'wq_min_width'),
        get_option('wq_map_max_wid', 'wq_max_width')
    );
    $dim_keys = array_values(array_unique(array_filter($dim_keys, function($v) { return is_string($v) && $v !== ''; })));
    
    // Default system fields if empty
    if (empty($fields)) {
        // ... (Define defaults like in admin page, or just skip as they are usually set)
        // But for consistency, let's render what's in options.
    }
    
    // If we have saved fields, iterate and render
    if (!empty($fields)) {
        foreach ($fields as $field) {
            $type = isset($field['type']) ? $field['type'] : 'text';
            $custom_attributes = array();
            if ($type === 'number' && isset($field['slug']) && in_array($field['slug'], $dim_keys, true)) {
                $custom_attributes = array(
                    'step' => $dim_step,
                    'min' => '0'
                );
            }
            
            // Map 'number' to 'text' input with type='number' attribute if needed, 
            // but woocommerce_wp_text_input handles 'type' arg.
            
            woocommerce_wp_text_input(
                array(
                    'id'          => $field['slug'],
                    'label'       => $field['label'],
                    'placeholder' => $field['placeholder'],
                    'desc_tip'    => 'true',
                    'description' => $field['desc'],
                    'type'        => $type,
                    'custom_attributes' => $custom_attributes
                )
            );
        }
    } else {
        // Fallback for initial load if no settings saved yet (Legacy support)
        woocommerce_wp_text_input(
            array(
                'id'          => 'thickness',
                'label'       => __( 'Thickness (mm)', 'woo-quote-builder' ),
                'placeholder' => '18',
                'desc_tip'    => 'true',
                'description' => __( 'Enter the thickness of the material in mm.', 'woo-quote-builder' ),
            )
        );
        // ... Render other legacy fields manually if not in DB ...
    }

    woocommerce_wp_checkbox(
        array(
            'id'          => '_wq_has_edgebanding',
            'label'       => __( 'Has EDGEBANDING', 'woo-quote-builder' ),
            'description' => __( 'Enable edgebanding options for this product.', 'woo-quote-builder' ),
        )
    );
    
    // ... (Edgebanding Select2 remains same) ...
    // Add Select2 for Edgebanding Services
    // We need to fetch all published edge services
    $edge_services = get_posts(array(
        'post_type' => 'wq_edge_service',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    $options = array();
    foreach ($edge_services as $service) {
        $options[$service->ID] = $service->post_title;
    }
    
    // Get currently selected values
    global $post;
    if ( ! is_object( $post ) ) {
        return;
    }
    $ops = get_option('wq_edge_operations', array());
    $has_operations = get_post_meta($post->ID, '_wq_has_operations', true);
    $selected_ops = get_post_meta($post->ID, '_wq_operation_indexes', true);
    if (!is_array($selected_ops)) {
        $legacy_single = get_post_meta($post->ID, '_wq_operation_index', true);
        $selected_ops = $legacy_single !== '' ? array($legacy_single) : array();
    }
    $has_preferred_edging = get_post_meta($post->ID, '_wq_has_preferred_edging', true);
    $preferred_edge_services = get_post_meta($post->ID, '_wq_preferred_edge_services', true);
    if (!is_array($preferred_edge_services)) {
        $legacy_single = get_post_meta($post->ID, '_wq_preferred_edge_service', true);
        $preferred_edge_services = $legacy_single ? array($legacy_single) : array();
    }

    woocommerce_wp_checkbox(
        array(
            'id'          => '_wq_has_operations',
            'label'       => __( 'Has OPERATION', 'woo-quote-builder' ),
            'description' => __( 'Enable operations for this product.', 'woo-quote-builder' ),
            'value'       => $has_operations === 'yes' ? 'yes' : 'no'
        )
    );
    ?>
    <p class="form-field _wq_operation_indexes_field">
        <label for="_wq_operation_indexes"><?php _e('Operations', 'woo-quote-builder'); ?></label>
        <select id="_wq_operation_indexes" name="_wq_operation_indexes[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;">
            <?php if (is_array($ops)) : ?>
                <?php foreach ($ops as $idx => $op) : ?>
                    <?php
                    $name = isset($op['name']) ? $op['name'] : ('Operation ' . $idx);
                    $price = isset($op['price']) ? $op['price'] : '';
                    $type = isset($op['type']) ? $op['type'] : '';
                    $label = $name;
                    if ($price !== '') {
                        $suffix = '';
                        if ($type === 'meter') $suffix = '/m';
                        if ($type === 'm2') $suffix = '/m2';
                        $label .= ' (' . $price . $suffix . ')';
                    }
                    ?>
                    <option value="<?php echo esc_attr($idx); ?>" <?php echo in_array((string)$idx, array_map('strval', $selected_ops), true) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <span class="description"><?php _e('Select one or more operations for this product.', 'woo-quote-builder'); ?></span>
    </p>

    <?php
    woocommerce_wp_checkbox(
        array(
            'id'          => '_wq_has_preferred_edging',
            'label'       => __( 'Has PREFERRED EDGING', 'woo-quote-builder' ),
            'description' => __( 'Enable preferred edging selector for this product.', 'woo-quote-builder' ),
            'value'       => $has_preferred_edging === 'yes' ? 'yes' : 'no'
        )
    );
    ?>
    <p class="form-field _wq_preferred_edge_service_field">
        <label for="_wq_preferred_edge_services"><?php _e('Preferred Edging', 'woo-quote-builder'); ?></label>
        <select id="_wq_preferred_edge_services" name="_wq_preferred_edge_services[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;">
            <?php foreach ($options as $id => $label) : ?>
                <option value="<?php echo esc_attr($id); ?>" <?php echo in_array((string)$id, array_map('strval', $preferred_edge_services), true) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="description"><?php _e('Preferred edging services shown in the edgebanding popup.', 'woo-quote-builder'); ?></span>
    </p>
    </div>
    <?php
}

// Save Custom Fields
function wq_save_custom_fields( $post_id ) {
    $fields = get_option('wq_custom_fields', array());
    
    if (!empty($fields)) {
        foreach ($fields as $field) {
            $slug = $field['slug'];
            if (isset($_POST[$slug])) {
                update_post_meta($post_id, $slug, sanitize_text_field($_POST[$slug]));
            }
        }
    } else {
        // Fallback Legacy Save
        $thickness = isset( $_POST['thickness'] ) ? sanitize_text_field( $_POST['thickness'] ) : '';
        update_post_meta( $post_id, 'thickness', $thickness );
        // ... (Other fields)
    }

    $has_edgebanding = isset( $_POST['_wq_has_edgebanding'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_wq_has_edgebanding', $has_edgebanding );

    $has_operations = isset( $_POST['_wq_has_operations'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_wq_has_operations', $has_operations );
    $op_idxs = isset($_POST['_wq_operation_indexes']) && is_array($_POST['_wq_operation_indexes']) ? array_map('sanitize_text_field', $_POST['_wq_operation_indexes']) : array();
    if ($has_operations !== 'yes') $op_idxs = array();
    update_post_meta( $post_id, '_wq_operation_indexes', $op_idxs );
    delete_post_meta( $post_id, '_wq_operation_index' );

    $has_preferred_edging = isset( $_POST['_wq_has_preferred_edging'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_wq_has_preferred_edging', $has_preferred_edging );
    $pref_edges = isset($_POST['_wq_preferred_edge_services']) && is_array($_POST['_wq_preferred_edge_services']) ? array_map('sanitize_text_field', $_POST['_wq_preferred_edge_services']) : array();
    if ($has_preferred_edging !== 'yes') $pref_edges = array();
    update_post_meta( $post_id, '_wq_preferred_edge_services', $pref_edges );
    delete_post_meta( $post_id, '_wq_preferred_edge_service' );
    delete_post_meta( $post_id, '_wq_allowed_edge_services' );
}
add_action( 'woocommerce_process_product_meta', 'wq_save_custom_fields' );

function wq_admin_styles() {
    // ... (Keep existing logic for hiding regular price) ...
    // Get allowed categories
    $allowed_cats = get_option('wq_builder_allowed_categories', array());
    if ( ! is_array($allowed_cats) ) $allowed_cats = array();
    $allowed_cats = array_map('intval', $allowed_cats);
    
    // Generate CSS for dynamic fields
    $fields = get_option('wq_custom_fields', array());
    $css_selectors = '';
    if (!empty($fields)) {
        foreach ($fields as $field) {
            $css_selectors .= '.' . $field['slug'] . '_field, ';
        }
        $css_selectors = rtrim($css_selectors, ', ');
    } else {
        // Default selectors
        $css_selectors = '.thickness_field, .wq_pricing_per_mm_field, .wq_max_length_field, .wq_max_width_field, .wq_min_length_field, .wq_min_width_field';
    }
    
    ?>
    <style>
        .wq-hide-price-fields ._regular_price_field, 
        .wq-hide-price-fields ._sale_price_field {
            display: none !important;
        }
        .wq_builder_tab_item {
            display: none;
        }
        

    </style>
    <script type="text/javascript">
    // ... (Keep existing JS logic) ...
        jQuery(document).ready(function($) {
            const allowedCats = <?php echo json_encode($allowed_cats); ?>;
            const categoryParents = <?php 
                $cat_parents = array();
                $all_terms = get_terms('product_cat', array('hide_empty' => false));
                if ( ! is_wp_error( $all_terms ) ) {
                    foreach ($all_terms as $term) {
                        $cat_parents[$term->term_id] = $term->parent;
                    }
                }
                echo json_encode($cat_parents);
            ?>;
            
            function checkCategories() {
                let isQuoteProduct = false;
                $('#product_catdiv input[type="checkbox"]:checked').each(function() {
                    let catId = parseInt($(this).val());
                    if (allowedCats.includes(catId)) {
                        isQuoteProduct = true;
                        return false;
                    }
                    let currentId = catId;
                    while (categoryParents[currentId] && categoryParents[currentId] !== 0) {
                        let parentId = categoryParents[currentId];
                        if (allowedCats.includes(parentId)) {
                            isQuoteProduct = true;
                            return false;
                        }
                        currentId = parentId;
                    }
                    if (isQuoteProduct) return false;
                });
                
                if (isQuoteProduct) {
                    $('#general_product_data').addClass('wq-hide-price-fields');
                    $('.wq_builder_tab_item').show();
                } else {
                    $('#general_product_data').removeClass('wq-hide-price-fields');
                    $('.wq_builder_tab_item').hide();
                }
            }

            $('select#product-type').on('change', function() {
                setTimeout(checkCategories, 50);
            });
            setTimeout(checkCategories, 500); 
            $('#product_catdiv').on('change', 'input[type="checkbox"]', checkCategories);
        });
    </script>
    <?php
}
add_action( 'admin_head', 'wq_admin_styles' );

function wq_register_cpt() {
    // 3. Saved Quotes (Admin Side)
    $labels_quote = array(
        'name'               => 'Saved Quotes',
        'singular_name'      => 'Quote',
        'menu_name'          => 'Saved Quotes',
        'all_items'          => 'All Quotes',
        'search_items'       => 'Search Quotes',
        'not_found'          => 'No Quotes found',
        'not_found_in_trash' => 'No Quotes found in Trash',
    );

    $args_quote = array(
        'labels'              => $labels_quote,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true, // Make it a top-level menu
        'menu_position'       => 56,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'hierarchical'        => false,
        'supports'            => array( 'title', 'author', 'custom-fields' ), // Title = Quote Ref
        'menu_icon'           => 'dashicons-list-view',
        'capabilities' => array(
            'create_posts' => 'do_not_allow', // Only created programmatically
        ),
        // Enable standard pagination and searching
        'query_var'           => true,
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => true, // Exclude from frontend search
    );

    register_post_type( 'wq_quote', $args_quote );
}
add_action( 'init', 'wq_register_cpt' );
