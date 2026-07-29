<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Quote Builder Shortcode
function wq_builder_shortcode() {
	ob_start();
    $dim_unit = get_option('wq_dimension_unit', 'mm');
    $dim_unit_upper = 'MM';
    if ($dim_unit === 'cm') $dim_unit_upper = 'CM';
    if ($dim_unit === 'm') $dim_unit_upper = 'M';
    if ($dim_unit === 'in') $dim_unit_upper = 'IN';
    $dim_step = '1';
    $dim_min = '1';
    if ($dim_unit === 'm') { $dim_step = '0.001'; $dim_min = '0.001'; }
    if ($dim_unit === 'cm') { $dim_step = '0.1'; $dim_min = '0.1'; }
    if ($dim_unit === 'in') { $dim_step = '0.01'; $dim_min = '0.01'; }
	?>
	<div class="wq-builder-container">
        <div class="wq-header-tour">
            <?php if ( get_option('wq_tour_enable', 1) ) : ?>
                <button id="wq-show-tour" class="wq-tour-btn">SHOW GUIDED TOUR &rarr;</button>
            <?php endif; ?>
        </div>

<!--         <div class="wq-project-info">
			<div class="wq-form-group required">
				<label for="wq-project-name">Project Name: <span class="dashicons dashicons-info wq-info-icon" title="Enter the name of your project"></span> <span class="required-label" style="float:right; color:#ccc; font-style:italic;">Required</span></label>
				<input type="text" id="wq-project-name" class="wq-input">
			</div>
			<div class="wq-form-group">
				<label for="wq-client-name">Client Name: <span class="dashicons dashicons-info wq-info-icon" title="Enter the client's name"></span></label>
				<input type="text" id="wq-client-name" class="wq-input">
			</div>
            
			<div class="wq-form-group">
				<label for="wq-quote-ref">Quote Ref:</label>
				<div id="wq-quote-ref" class="wq-readonly-display"></div>
			</div>
			<div class="wq-form-group">
				<label>Date:</label>
				<div class="wq-date-display"><?php echo date('j/n/Y'); ?></div>
			</div>
			<div class="wq-form-group wq-notes-group">
				<label>Project Notes: <span class="dashicons dashicons-info wq-info-icon" title="Add any specific notes for this project"></span></label>
				<div class="wq-notes-status">Note Added</div>
				<button class="wq-edit-notes"><span class="dashicons dashicons-edit"></span></button>
				<textarea id="wq-project-notes" class="wq-input wq-hidden-notes" placeholder="Enter project notes..."></textarea>
			</div>
            
		</div> -->
		
    <div class="wq-col-title">Client Details</div>
	<div class="wq-project-info wq-custom-layout">

    <!-- ROW 1 -->
    <div class="wq-project-row-1">
        
        <div class="wq-form-group required">
            <label for="wq-project-name">
                Project Name: <span class="required-asterisk">*</span>
<!--                 <span class="dashicons dashicons-info wq-info-icon" title="Enter the name of your project"></span> -->
                <span class="required-label" style="float:right; color:#ccc; font-style:italic;">Required</span>
            </label>
            <input type="text" id="wq-project-name" class="wq-input" placeholder="Awesome project name" required>
        </div>

        <div class="wq-form-group">
            <label for="wq-client-name">
                Client Name: <span class="required-asterisk">*</span>
<!--                 <span class="dashicons dashicons-info wq-info-icon" title="Enter the client's name"></span> -->
            </label>
            <input type="text" id="wq-client-name" class="wq-input" placeholder="Sheikh oneeb" required>
        </div>

        <div class="wq-form-group">
            <label for="wq-client-email">
                Email: <span class="required-asterisk">*</span>
            </label>
            <input type="email" id="wq-client-email" class="wq-input" placeholder="example@email.com" required>
        </div>

        <div class="wq-form-group">
            <label for="wq-client-phone">
                Phone: <span class="required-asterisk">*</span>
            </label>
            <input type="tel" id="wq-client-phone" class="wq-input" placeholder="+44 7XXX XXX XXX" pattern="^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$"
    required>
        </div>

    </div>

    <!-- ROW 2 -->
    <div class="wq-project-row-2">

        <div class="wq-form-group wq-description-group">
    <label for="wq-project-description">
        Project Description:
<!--         <span class="dashicons dashicons-info wq-info-icon" title="Add project description"></span> -->
    </label>
    
    <textarea 
        id="wq-project-notes"
        class="wq-input" 
        placeholder="Enter project description..."
        rows="1"
    ></textarea>
</div>

        <div class="wq-form-group">
            <label for="wq-quote-ref">Quote Ref:</label>
            <div id="wq-quote-ref" class="wq-readonly-display"></div>
        </div>

        <div class="wq-form-group">
            <label>Date:</label>
            <div class="wq-readonly-display wq-date-display"><?php echo date('j/n/Y'); ?></div>
        </div>

    </div>

</div>
        <div class="wq-col-title">Material Details</div>
		<div class="wq-table-scroll">
		<div class="wq-table-header">
            <div class="wq-col-clipboard"></div>
            <div class="wq-col-num"></div>
			<div class="wq-col-material">MATERIAL CODE & NAME</div>
			<div class="wq-col-thickness vertical-text">
				<div class="wq-v-label-container"><span class="wq-v-label">THICKNESS</span></div>
				<div class="wq-unit-container"><span class="wq-unit">MM</span></div>
			</div>
			<div class="wq-col-length vertical-text">
				<div class="wq-v-label-container"><span class="wq-v-label">LENGTH</span></div>
				<div class="wq-unit-container"><span class="wq-unit"><?php echo esc_html($dim_unit_upper); ?></span></div>
			</div>
			<div class="wq-col-width vertical-text">
				<div class="wq-v-label-container"><span class="wq-v-label">WIDTH</span></div>
				<div class="wq-unit-container"><span class="wq-unit"><?php echo esc_html($dim_unit_upper); ?></span></div>
			</div>
			<div class="wq-col-qty vertical-text">
				<div class="wq-v-label-container"><span class="wq-v-label">QUANTITY</span></div>
			</div>
			<div class="wq-col-edge">
				<div>EDGEBANDING</div>
				<div class="wq-edge-labels">
					<span>L1</span> <span>W1</span> <span>L2</span> <span>W2</span>
				</div>
			</div>
			<div class="wq-col-label vertical-text">
				<div class="wq-v-label-container"><span class="wq-v-label">LABEL</span></div>
			</div>
            <div class="wq-col-action"></div>
		</div>

        <!-- Mobile Header (Visible only on mobile) -->
        <div class="wq-mobile-header">
            <!-- Spacer for Row Num -->
            <div class="wq-mh-col wq-mh-spacer"></div>
            
            <div class="wq-mh-col wq-mh-thk">
                <span class="wq-mh-text">THICKNESS</span>
                <span class="wq-mh-unit">MM</span>
            </div>
            <div class="wq-mh-col wq-mh-len">
                <span class="wq-mh-text">LENGTH HEIGHT</span>
                <span class="wq-mh-unit"><?php echo esc_html($dim_unit_upper); ?></span>
            </div>
            <div class="wq-mh-col wq-mh-wid">
                <span class="wq-mh-text">WIDTH</span>
                <span class="wq-mh-unit"><?php echo esc_html($dim_unit_upper); ?></span>
            </div>
            <div class="wq-mh-col wq-mh-qty">
                <span class="wq-mh-text">QUANTITY</span>
            </div>
             <div class="wq-mh-col wq-mh-edge">
                <span class="wq-mh-text">EDGEBANDING</span>
            </div>
            <div class="wq-mh-col wq-mh-lbl">
                <span class="wq-mh-text">LABELLING</span>
            </div>
        </div>

		<div id="wq-rows-container">
			<!-- Rows will be added here via JS -->
		</div>
		</div>

		<div class="wq-actions">
			<div class="wq-left-actions">
				<button id="wq-add-row" class="wq-btn wq-btn-green">ADD ROW</button>
				<button id="wq-download-template" class="wq-btn wq-btn-white">DOWNLOAD TEMPLATE</button>
				<button id="wq-clear-rows" class="wq-btn wq-btn-white">CLEAR ALL ROWS</button>
			</div>
			<div class="wq-right-actions">
				<button id="wq-get-pricing" class="wq-btn wq-btn-green">GET PRICING</button>
			</div>
		</div>

		<div id="wq-quote-summary" style="display:none; margin-top: 20px; background: white; padding: 20px; border: 1px solid #ddd;">
			<h3>Quote Summary</h3>
			<table class="wq-summary-table" style="width:100%; text-align: left;">
				<thead>
					<tr>
						<th>Product</th>
						<th>Dimensions</th>
						<th>Qty</th>
						<th>Price</th>
					</tr>
				</thead>
				<tbody id="wq-summary-body"></tbody>
				<tfoot>
					<tr>
						<td colspan="3" style="text-align:right;"><strong>Total:</strong></td>
						<td id="wq-summary-total"></td>
					</tr>
				</tfoot>
			</table>
			
			<div class="wq-submission-form" style="margin-top: 20px; display: none;">
				<h4>Submit Quote</h4>
				<div class="wq-form-group">
					<label>Email Address</label>
					<input type="email" id="wq-user-email" class="wq-input" placeholder="Enter your email">
				</div>
				<button id="wq-submit-quote" class="wq-btn wq-btn-green" style="margin-top: 10px;">Submit & Download PDF</button>
				<div id="wq-submission-message"></div>
			</div>
		</div>

		<div class="wq-footer-text">
            <?php
            if ( ! is_user_logged_in() ) {
                $create_account_url = get_option('wq_builder_create_account_url', '#');
                $sign_in_url = get_option('wq_builder_sign_in_url', '#');
                ?>
                To save your cutting list and price quote, <a href="<?php echo esc_url($create_account_url); ?>" class="wq-footer-btn">create an account</a> or <a href="<?php echo esc_url($sign_in_url); ?>" class="wq-footer-btn">Sign in</a> and you'll be able to access all your past quotes in one place.
                <?php
            }
            ?>
		</div>
	</div>

    <!-- Tour Guide HTML -->
    <div id="wq-tour-overlay" style="display:none;"></div>
    
    <div id="wq-tour-modal" style="display:none;">
        <div class="wq-tour-header">
                <span id="wq-tour-title">Tour Step</span>
                <button id="wq-tour-close">&times;</button>
            </div>
            <div class="wq-tour-body">
                <p id="wq-tour-content">Content goes here...</p>
            </div>
            <div class="wq-tour-footer">
                <button id="wq-tour-back">BACK</button>
                <button id="wq-tour-next">NEXT</button>
            </div>
            <div class="wq-tour-arrow"></div>
    </div>

    <!-- Edgebanding Diagram Popup -->
    <div id="wq-edge-diagram-popup" class="wq-popup-overlay" style="display:none;">
        <div class="wq-popup-content">
            <button type="button" class="wq-popup-close-btn wq-desktop-close-btn">CLOSE</button>
            <div class="wq-popup-header-mobile-tabs">
                <button type="button" class="wq-tab-btn active">EDGEBANDING</button>
                <button type="button" class="wq-tab-btn wq-popup-close-btn">CLOSE</button>
            </div>
            
            <div class="wq-popup-body">
                <div class="wq-edge-diagram-container">
                    <!-- Dynamic Material Name in Diagram -->
                    <div class="wq-edge-diagram-title" id="wq-diagram-material-name">Material Name</div>
                    
                    <div class="wq-edge-diagram">
                        <div class="wq-diagram-box">
                            <div class="wq-highlight-l1"></div>
                            <div class="wq-highlight-w1"></div>
                            <div class="wq-highlight-l2"></div>
                            <div class="wq-highlight-w2"></div>
                            
                            <span class="wq-d-label wq-d-l1">L1</span>
                            <span class="wq-d-label wq-d-w1">W1</span>
                            <span class="wq-d-label wq-d-l2">L2</span>
                            <span class="wq-d-label wq-d-w2">W2</span>
                            
                            <div class="wq-d-dim-l1"></div>
                            <div class="wq-d-angle-l1" style="position: absolute; top: 25px; width: 100%; text-align: center; font-size: 12px; color: #666; display: none;"></div>
                            
                            <div class="wq-d-dim-w1"></div>
                            <div class="wq-d-angle-w1" style="position: absolute; left: 25px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #666; display: none;"></div>
                            
                            <div class="wq-d-dim-l2"></div>
                            <div class="wq-d-angle-l2" style="position: absolute; bottom: 25px; width: 100%; text-align: center; font-size: 12px; color: #666; display: none;"></div>
                            
                            <div class="wq-d-dim-w2"></div>
                            <div class="wq-d-angle-w2" style="position: absolute; right: 25px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #666; display: none;"></div>

                            <div class="wq-grain-direction">
                                <span>GRAIN DIRECTION</span>
                                <span class="wq-arrow" id="wq-grain-arrow">&larr; &rarr;</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="wq-edge-controls">
                    <div class="wq-edge-tabs">
                        <div class="wq-edge-tab-container">
                            <button class="wq-edge-tab" data-edge="l1">L1</button>
                            <input type="text" class="wq-edge-code-display wq-code-display-l1" readonly>
                        </div>
                        <div class="wq-edge-tab-container">
                            <button class="wq-edge-tab" data-edge="w1">W1</button>
                            <input type="text" class="wq-edge-code-display wq-code-display-w1" readonly>
                        </div>
                        <div class="wq-edge-tab-container">
                            <button class="wq-edge-tab" data-edge="l2">L2</button>
                            <input type="text" class="wq-edge-code-display wq-code-display-l2" readonly>
                        </div>
                        <div class="wq-edge-tab-container">
                            <button class="wq-edge-tab" data-edge="w2">W2</button>
                            <input type="text" class="wq-edge-code-display wq-code-display-w2" readonly>
                        </div>
                    </div>
                    
                    <div class="wq-edge-selection-area">
                        <div class="wq-current-edge-info" style="margin-bottom: 15px;">
                            <strong>Selected:</strong> <span class="wq-edge-code" style="color: #666; font-weight: normal;">None</span> <span class="wq-edge-thick"></span>
                        </div>

                        <div class="wq-edge-search">
                            <h4>Preferred Edging</h4>
                            <div class="wq-edge-service-selector wq-preferred-edge-selector">
                                <input type="text" class="wq-edge-service-display wq-preferred-edge-display" placeholder="No preferred edging" readonly>
                                <button type="button" class="wq-edge-service-toggle wq-preferred-edge-toggle" aria-label="Toggle preferred edging dropdown">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                <div class="wq-edge-service-dropdown wq-preferred-edge-panel" style="display:none;">
                                    <input type="text" class="wq-edge-service-search wq-preferred-edge-search" placeholder="Search preferred edging..." autocomplete="off">
                                    <ul class="wq-edge-service-results wq-preferred-edge-results"></ul>
                                </div>
                            </div>
                            <h4>Search Edging</h4>
                            <div class="wq-edge-service-selector wq-edge-search-selector">
                                <input type="text" class="wq-edge-service-display wq-edge-search-display" placeholder="Select edging..." readonly>
                                <button type="button" class="wq-edge-service-toggle wq-edge-search-toggle" aria-label="Toggle edging search dropdown">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                <div class="wq-edge-service-dropdown wq-edge-search-panel" style="display:none;">
                                    <input type="text" placeholder="Enter Search Term" class="wq-edge-service-search wq-edge-search-input" autocomplete="off">
                                    <ul class="wq-edge-service-results wq-edge-search-results"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="wq-edge-visuals">
                     <!-- Visual options populated via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Template for a Row -->
    <template id="wq-row-template">
        <div class="wq-row-container" style="position:relative;"> <!-- Wrapper for Row + Popup -->
            <div class="wq-row" data-id="">
                <div class="wq-row-clipboard-cell"></div>
                <div class="wq-row-num">1</div>
            <div class="wq-row-material">
                <span class="wq-mobile-label">Material</span>
                <div class="wq-product-selector">
                    <input type="text" class="wq-product-search" placeholder="Select Material..." readonly>
                    <input type="hidden" class="wq-selected-product-id">
                    <div class="wq-row-clipboard-controls">
                        <div class="wq-copy-status">Copied!</div>
                        <button type="button" class="wq-icon-btn wq-copy-row-btn" title="Copy">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                        <button type="button" class="wq-icon-btn wq-paste-row-btn" title="Paste">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>
                    <div class="wq-product-dropdown">
                            <input type="text" class="wq-search-input" placeholder="Enter Search Term">
                            <div class="wq-category-filters">
                                <!-- Parent Categories -->
                            </div>
                            <div class="wq-subcategory-filters" style="display:none;">
                                <!-- Child Categories -->
                            </div>
                            <div class="wq-product-list">
                                <!-- Products will be injected here -->
                            </div>
                        </div>
                </div>
            </div>
            <div class="wq-row-thickness">
                <span class="wq-mobile-label">Thickness (mm)</span>
                <input type="text" class="wq-input-small wq-readonly" readonly>
            </div>
            <div class="wq-row-length">
                <span class="wq-mobile-label">Length (<?php echo esc_html(strtolower($dim_unit_upper)); ?>)</span>
                <input type="number" class="wq-input-small" min="<?php echo esc_attr($dim_min); ?>" step="<?php echo esc_attr($dim_step); ?>" oninput="validity.valid||(value='');">
            </div>
            <div class="wq-row-width">
                <span class="wq-mobile-label">Width (<?php echo esc_html(strtolower($dim_unit_upper)); ?>)</span>
                <input type="number" class="wq-input-small" min="<?php echo esc_attr($dim_min); ?>" step="<?php echo esc_attr($dim_step); ?>" oninput="validity.valid||(value='');">
            </div>
            <div class="wq-row-qty">
                <span class="wq-mobile-label">Quantity</span>
                <input type="number" class="wq-input-small" value="1" min="1" step="1" oninput="validity.valid||(value='');">
            </div>
            <div class="wq-row-edge">
                <span class="wq-mobile-label">Edgebanding</span>
                <div class="wq-edge-checkboxes">
                    <div class="wq-edge-item">
                        <input type="checkbox" class="wq-checkbox wq-edge-l1" title="L1">
                        <div class="wq-selected-edge-code wq-code-l1" style="font-size:9px; color:#666; min-height:10px;"></div>
                    </div>
                    <div class="wq-edge-item">
                        <input type="checkbox" class="wq-checkbox wq-edge-w1" title="W1">
                        <div class="wq-selected-edge-code wq-code-w1" style="font-size:9px; color:#666; min-height:10px;"></div>
                    </div>
                    <div class="wq-edge-item">
                        <input type="checkbox" class="wq-checkbox wq-edge-l2" title="L2">
                        <div class="wq-selected-edge-code wq-code-l2" style="font-size:9px; color:#666; min-height:10px;"></div>
                    </div>
                    <div class="wq-edge-item">
                        <input type="checkbox" class="wq-checkbox wq-edge-w2" title="W2">
                        <div class="wq-selected-edge-code wq-code-w2" style="font-size:9px; color:#666; min-height:10px;"></div>
                    </div>
                </div>
            </div>
            <div class="wq-row-label">
                <span class="wq-mobile-label">Label</span>
                <button class="wq-icon-btn wq-edit-label">
                    <svg version="1.1" class="edit_svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 122.88 122.88" xml:space="preserve">
                        <g><path d="M61.44,0c16.97,0,32.33,6.88,43.44,18c11.12,11.12,18,26.48,18,43.44c0,16.97-6.88,32.33-18,43.44 c-11.12,11.12-26.48,18-43.44,18S29.11,116,18,104.88C6.88,93.77,0,78.41,0,61.44C0,44.47,6.88,29.11,18,18 C29.11,6.88,44.47,0,61.44,0L61.44,0z M77.05,36.16c-0.6-0.56-1.28-0.85-2.05-0.81c-0.77,0-1.45,0.3-2.01,0.9l-4.53,4.7L81.15,53.2 l4.57-4.78c0.56-0.55,0.77-1.28,0.77-2.05c0-0.77-0.3-1.49-0.85-2.01L77.05,36.16L77.05,36.16L77.05,36.16z M53.31,82.11 c-1.67,0.56-3.37,1.07-5.04,1.62c-1.67,0.56-3.33,1.11-5.04,1.67c-3.97,1.28-6.15,2.01-6.62,2.14c-0.47,0.13-0.17-1.71,0.81-5.55 l3.16-12.09l0.26-0.27L53.31,82.11L53.31,82.11L53.31,82.11L53.31,82.11z M45.45,64.83L65.04,44.5l12.68,12.21L57.92,77.3 L45.45,64.83L45.45,64.83z M101.08,21.8C90.93,11.66,76.92,5.39,61.44,5.39S31.95,11.66,21.8,21.8 C11.66,31.95,5.39,45.96,5.39,61.44c0,15.48,6.27,29.49,16.42,39.64c10.14,10.14,24.16,16.42,39.64,16.42s29.49-6.27,39.64-16.42 c10.14-10.14,16.42-24.16,16.42-39.64C117.49,45.96,111.22,31.95,101.08,21.8L101.08,21.8z"></path></g>
                    </svg>
                </button>
                <div class="wq-label-status" style="display:none; color:green; font-weight:bold; font-size:0.8em; margin-top:2px;">Label Added</div>
                <div class="wq-label-popup" style="display:none;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="min-width:120px;">Predefined Label:</div>
                        <select class="wq-label-predefined-select" style="flex:1; height: 36px;">
                            <option value="">-- Select --</option>
                            <?php
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
                            $label_dropdown_options = array_values(array_unique($label_dropdown_options));
                            foreach ($label_dropdown_options as $opt) {
                                echo '<option value="'.esc_attr($opt).'">'.esc_html($opt).'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <textarea class="wq-input-small wq-label-input" placeholder="Label and Description"></textarea>
                    <button class="wq-btn-small wq-save-label">OK</button>
                </div>
            </div>
            <div class="wq-row-remove">
                <button class="wq-icon-btn wq-remove-btn">
                    <svg data-name="Layer 1" class="remove_svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88"><defs><style>.cls-1{fill-rule:evenodd;}</style></defs><path class="cls-1" d="M61.44,0A61.44,61.44,0,1,1,0,61.44,61.44,61.44,0,0,1,61.44,0ZM74.58,36.8c1.74-1.77,2.83-3.18,5-1l7,7.13c2.29,2.26,2.17,3.58,0,5.69L73.33,61.83,86.08,74.58c1.77,1.74,3.18,2.83,1,5l-7.13,7c-2.26,2.29-3.58,2.17-5.68,0L61.44,73.72,48.63,86.53c-2.1,2.15-3.42,2.27-5.68,0l-7.13-7c-2.2-2.15-.79-3.24,1-5l12.73-12.7L36.35,48.64c-2.15-2.11-2.27-3.43,0-5.69l7-7.13c2.15-2.2,3.24-.79,5,1L61.44,49.94,74.58,36.8Z"></path></svg>
                </button>
            </div>
            <!-- The Popup will be injected here dynamically or cloned -->
            <div class="wq-edge-dropdown-placeholder"></div>
        </div>
    </template>
	<?php
	return ob_get_clean();
}
add_shortcode( 'woo_quote_builder', 'wq_builder_shortcode' );
