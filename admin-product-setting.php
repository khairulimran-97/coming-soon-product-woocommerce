<?php

// Step 1: Update the custom table schema
function my_create_custom_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'comingsoon_products';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        selected_products LONGTEXT NOT NULL,
        selected_categories LONGTEXT NOT NULL,
        launching_date datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

my_create_custom_table(); // Call the function to create the table


// Step 2: Add an admin page
function my_custom_admin_page() {
    add_menu_page(
        'Comming Soon',
        'Comming Soon',
        'manage_options',
        'comming_soon_product',
        'comming_soon_product_callback'
    );
}
add_action('admin_menu', 'my_custom_admin_page');

// Step 3: Load necessary CSS and JavaScript libraries
function my_enqueue_admin_scripts($hook) {
    if ($hook == 'toplevel_page_comming_soon_product') {
        wp_enqueue_script('jquery');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
        wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    }
}
add_action('admin_enqueue_scripts', 'my_enqueue_admin_scripts');

// Step 4: Retrieve and Display products with multi-select and save functionality
function comming_soon_product_callback() {
    global $wpdb;

    // Retrieve all products
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
    );
    $products = new WP_Query($args);

    // Retrieve all product categories
    $categories = get_terms('product_cat', array(
        'hide_empty' => false,
    ));

    // Retrieve saved product data
    $saved_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}comingsoon_products WHERE id = 1");

    // Decode the JSON-encoded arrays of product IDs and category IDs
    $saved_products = array();
    $saved_categories = array();
    if ($saved_data) {
        if (!empty($saved_data->selected_products)) {
            $saved_products = json_decode($saved_data->selected_products);
        }
        if (!empty($saved_data->selected_categories)) {
            $saved_categories = json_decode($saved_data->selected_categories);
        }
    }

    // Retrieve the launching date from the database
    $launching_date = isset($saved_data->launching_date) ? $saved_data->launching_date : '';

    echo '
    <style>
        /* Style the product list container */
        .product-list-container {
            max-width: 80%;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }

        /* Style the multi-select dropdown */
        .select2 {
            width: 100%!important;
        }

        .save-status {
            margin-top: 20px;
        }

        .save-button {
            padding: 12px;
        }
    </style>
	<div>
	<h1>Coming Soon Page Settings</h1>
	<p>By Web Impian Sdn Bhd</p>
	</div>
    <div class="product-list-container">
	<h2>Select Specific Product</h2>
    <select id="product-list" multiple="multiple">';

    // Output options with pre-selected saved products
    if ($products->have_posts()) {
        while ($products->have_posts()) {
            $products->the_post();
            $product_id = get_the_ID();
            $selected = in_array($product_id, $saved_products) ? 'selected' : '';
            $product_status = get_post_status() == 'publish' ? ' (Publish)' : ' (Draft)';
            echo '<option value="' . esc_attr($product_id) . '" ' . $selected . '>' . esc_html(get_the_title()) . $product_status . '</option>';
        }
    }
	
	echo'
	 </select>
	 <h4> OR </h4>';

    echo '
        </select>
		<h2>Select Specific Product Category</h2>
        <select id="category-list" multiple="multiple">';

    // Output options with pre-selected saved categories
    if (!empty($categories)) {
        foreach ($categories as $category) {
            $selected = in_array($category->term_id, $saved_categories) ? 'selected' : '';
            echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
        }
    }

    echo '
        </select>
		<div style="margin-top:20px!important">
        <label  for="launching-date">Launch Date:</label>
        <input type="datetime-local" id="launching-date" name="launching-date" value="' . esc_attr($launching_date) . '">
		</div>
        <div class="save-status"></div>
        <button class="save-button">Save Selection</button>
    </div>
    <script>
        jQuery(document).ready(function($) {
            // Initialize Select2 for products and categories
            $("#product-list").select2({
                placeholder: "Select products",
                allowClear: true
            });
            $("#category-list").select2({
                placeholder: "Select categories",
                allowClear: true
            });

            // Save selected products and categories
            $(".save-button").on("click", function() {
                var selectedProducts = $("#product-list").val();
                var selectedCategories = $("#category-list").val();
                var launchingDate = $("#launching-date").val(); // Get the launching date value
                $.ajax({
                    url: ajaxurl,
                    method: "POST",
                    data: {
                        action: "my_save_selected_products",
                        products: selectedProducts,
                        categories: selectedCategories,
                        launchingDate: launchingDate // Pass the launching date to the server
                    },
                    success: function(response) {
                        $(".save-status").html(response);
                    }
                });
            });
        });
    </script>
    ';
}

//Step 5: Enqueue SweetAlert2 JavaScript
function enqueue_sweetalert2() {
    // Enqueue SweetAlert2 JavaScript
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js', array('jquery'), null, true);

    // Enqueue SweetAlert2 CSS
    wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css');
}
add_action('admin_enqueue_scripts', 'enqueue_sweetalert2');

// Step 6: AJAX handler to save selected products and categories to the database
function my_save_selected_products() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'comingsoon_products';

    $products = isset($_POST['products']) ? $_POST['products'] : array();
    $categories = isset($_POST['categories']) ? $_POST['categories'] : array();
    $launching_date = isset($_POST['launchingDate']) ? sanitize_text_field($_POST['launchingDate']) : '';

    // Encode the arrays of selected products and categories as JSON
    $selected_products_json = json_encode($products);
    $selected_categories_json = json_encode($categories);

    // Check if data exists for id = 1
    $existing_data = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1");

    // Prepare the data for insertion or update
    $data = array(
        'selected_products' => $selected_products_json,
        'selected_categories' => $selected_categories_json,
        'launching_date' => $launching_date,
    );

    if ($existing_data) {
        // Update the existing data
        $wpdb->update($table_name, $data, array('id' => 1));
    } else {
        // Insert the new data
        $wpdb->insert($table_name, $data);
    }

    // Use SweetAlert2 to display a success popup
    $popup_script = "
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'Selection saved successfully.',
            position: 'center',
            showConfirmButton: false,
            timer: 1500 // Display for 1.5 seconds
        });
    </script>
    ";

    echo $popup_script;
    wp_die();
}
add_action('wp_ajax_my_save_selected_products', 'my_save_selected_products');
add_action('wp_ajax_nopriv_my_save_selected_products', 'my_save_selected_products');


 // Hide add to cart function

function hide_variations_form_for_selected_products() {
    // Check if we're on a single product page
    if (is_product()) {
        global $post, $wpdb;

        // Replace 'wp_comingsoon_products' with your actual table name
        $table_name = $wpdb->prefix . 'comingsoon_products';

        // Fetch the row with the selected product IDs and launching date from the custom table
        $selected_data = $wpdb->get_row("SELECT selected_products, launching_date FROM $table_name WHERE id = 1");

        // Get the current product's ID
        $current_product_id = $post->ID;

        // Decode the JSON-encoded array of selected product IDs
        $selected_product_ids = array();
        if ($selected_data && !empty($selected_data->selected_products)) {
            $selected_product_ids = json_decode($selected_data->selected_products);
        }

        // Check if the current product's ID is in the selected product IDs array
        if (in_array($current_product_id, $selected_product_ids)) {
            // Check if the current date and time are later than the launching date
            $current_datetime = current_time('mysql');
            $launching_datetime = $selected_data->launching_date;

            if ($current_datetime < $launching_datetime) {
                // Output inline CSS to hide the element with the specified class
                echo '<style>.cart { display: none; }</style>';
                echo '<style>.single_variation_wrap { display: none!important; }</style>';
            }
        }
    }
}

add_action('wp_head', 'hide_variations_form_for_selected_products');
