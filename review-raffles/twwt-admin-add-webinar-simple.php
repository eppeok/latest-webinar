<?php
add_action( 'admin_menu', 'twwt_add_webinar_submenu' );
function twwt_add_webinar_submenu() {
    add_submenu_page(
        'review-raffles',
        'Add Webinar',
        'Add Webinar',
        'manage_options',
        'twwt-add-webinar',
        'twwt_admin_add_webinar_page'
    );
}

function twwt_admin_add_webinar_page() {
    ?>
    <div class="wrap twwt-webinar-admin">
        <h1 class="wp-heading-inline">Add Webinar Product</h1>
        <hr class="wp-header-end">

        <?php
        if (
            isset($_POST['twwt_add_webinar_nonce']) &&
            wp_verify_nonce($_POST['twwt_add_webinar_nonce'], 'twwt_add_webinar_action')
        ) {
            $result = twwt_create_webinar_product($_POST, $_FILES);

            if ( is_wp_error($result) ) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                $pid = intval($result);
                $notification_sent = get_post_meta( $pid, 'twwt_np_notification_sent', true );

                echo '<div class="notice notice-success is-dismissible">
                        <p><strong>Webinar product created successfully!</strong></p>';

                if ( $notification_sent == 1 ) {
                    echo '<p>
                            <span class="twwt-badge twwt-badge-success">
                                ✔ Notification is sent
                            </span>
                        </p>';
                }

                echo '<p>
                        <a href="'. esc_url(get_edit_post_link($pid)) .'" class="button button-secondary" target="_blank">Edit Product</a>
                        <a href="'. esc_url(get_permalink($pid)) .'" class="button button-secondary" target="_blank">View Product</a>
                    </p>
                    </div>';
            }

        }
        ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('twwt_add_webinar_action','twwt_add_webinar_nonce'); ?>

            <div class="twwt-grid">
                <div class="twwt-card">
                    <h2>Basic Information</h2>

                    <p>
                        <label class="twwt-label">Product Name</label>
                        <input type="text" name="twwt_title" class="regular-text" required>
                    </p>
                    <?php
                    $options = get_option('twwt_woo_settings');
                    $default_cat = isset($options['default_webinar_category'])
                        ? intval($options['default_webinar_category'])
                        : '';

                    $categories = get_terms(array(
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false,
                    ));
                    ?>
                    <p>
                        <label class="twwt-label">Category</label>
                        <select name="twwt_category" required>
                            <option value="">— Select Category —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"
                                    <?php selected($default_cat, $cat->term_id); ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label class="twwt-label">Short Description</label>
                        <textarea name="twwt_short_desc" rows="3" class="large-text"></textarea>
                    </p>

                    <p>
                        <label class="twwt-label">Description</label>
                        <textarea name="twwt_description" rows="6" class="large-text"></textarea>
                    </p>
                </div>
                <div class="twwt-card">
                    <h2>Media & Pricing</h2>

                    <p>
                        <label class="twwt-label">Product Image</label>
                        <input type="file" name="twwt_image" accept="image/*">
                    </p>

                    <p>
                        <label class="twwt-label">Price ($)</label>
                        <input type="number" name="twwt_price" step="0.01" min="0" class="regular-text" required>
                    </p>

                    <p>
                        <label class="twwt-label">Maximum Seats</label>
                        <input type="number" name="twwt_max_seats" min="1" class="regular-text" required>
                    </p>
                    <p>
                        <label class="twwt-label">
                            <input type="checkbox" name="twwt_send_notification" value="1">
                            Send Notification
                        </label>
                        <br>
                        <small>Notify your customer about the arrival of new webinar/event.</small>
                    </p>

                </div>

            </div>

            <p class="twwt-submit">
                <button type="submit" class="button button-primary button-hero">
                    🚀 Create Webinar
                </button>
            </p>

        </form>
    </div>

    <style>
        .twwt-webinar-admin .twwt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            max-width: 1100px;
        }

        .twwt-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            padding: 20px;
        }

        .twwt-card h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }

        .twwt-label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
        }

        .twwt-submit {
            margin-top: 25px;
        }

        @media (max-width: 900px) {
            .twwt-webinar-admin .twwt-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
}

function twwt_create_webinar_product($post, $files) {

    $title      = isset($post['twwt_title']) ? sanitize_text_field($post['twwt_title']) : '';
    $category_id = isset($post['twwt_category']) ? intval($post['twwt_category']) : 0;
    $short_desc = isset($post['twwt_short_desc']) ? sanitize_text_field($post['twwt_short_desc']) : '';
    $desc       = isset($post['twwt_description']) ? sanitize_textarea_field($post['twwt_description']) : '';
    $price      = isset($post['twwt_price']) ? floatval($post['twwt_price']) : 0;
    $max_seats  = isset($post['twwt_max_seats']) ? intval($post['twwt_max_seats']) : 0;
    $send_notification = isset($post['twwt_send_notification']) ? 1 : 0;


    if ( empty( $title ) ) {
        return new WP_Error('missing_title', 'Product name is required.');
    }
    if ( $category_id <= 0 ) {
        return new WP_Error('missing_category', 'Please select a product category.');
    }

    if ( $price < 0 ) {
        return new WP_Error('invalid_price', 'Price cannot be negative.');
    }
    if ( $max_seats < 1 ) {
        return new WP_Error('invalid_seats', 'Max seats must be at least 1.');
    }

    $product_id = wp_insert_post(array(
        'post_title'   => $title,
        'post_excerpt' => $short_desc,
        'post_content' => $desc,
        'post_status'  => 'publish',
        'post_type'    => 'product',
        'post_author'  => get_current_user_id(),
    ));

    if ( ! $product_id ) {
        return new WP_Error('insert_fail','Could not create product.');
    }

    wp_set_object_terms($product_id, 'variable', 'product_type');
    wp_set_object_terms($product_id, array($category_id), 'product_cat');

    $attr_label = 'Seat';
    $found_attr = null;
    if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
        $taxes = wc_get_attribute_taxonomies();
        if ( is_array( $taxes ) ) {
            foreach ( $taxes as $t ) {
                if ( isset( $t->attribute_name ) && ( strtolower( $t->attribute_name ) === strtolower( sanitize_title( $attr_label ) ) )
                    || isset( $t->attribute_label ) && ( strtolower( $t->attribute_label ) === strtolower( $attr_label ) ) ) {
                    $found_attr = $t;
                    break;
                }
            }
        }
    }

    $used_taxonomy = false;
    $term_slug = '';
    $attr_key = '';

    if ( $found_attr ) {
        $attr_key = 'pa_' . $found_attr->attribute_name;
        if ( taxonomy_exists( $attr_key ) ) {
            $term = term_exists( $attr_label, $attr_key );
            if ( $term === 0 || $term === null ) {
                $inserted = wp_insert_term( $attr_label, $attr_key, array( 'slug' => sanitize_title( $attr_label ) ) );
                if ( is_wp_error( $inserted ) ) {
                    $term_slug = '';
                } else {
                    $term_slug = isset( $inserted['slug'] ) ? $inserted['slug'] : sanitize_title( $attr_label );
                }
            } else {
                if ( is_array( $term ) ) {
                    $term_slug = isset( $term['slug'] ) ? $term['slug'] : sanitize_title( $attr_label );
                } else {
                    $term_obj = get_term_by( 'id', $term, $attr_key );
                    $term_slug = $term_obj ? $term_obj->slug : sanitize_title( $attr_label );
                }
            }

            if ( $term_slug ) {
                wp_set_object_terms( $product_id, $term_slug, $attr_key, true );

                $attributes = array(
                    $attr_key => array(
                        'name'         => $attr_key,
                        'value'        => $term_slug,
                        'position'     => 0,
                        'is_visible'   => 0,
                        'is_variation' => 1,
                        'is_taxonomy'  => 1,
                    ),
                );
                update_post_meta( $product_id, '_product_attributes', $attributes );

                $used_taxonomy = true;
            }
        }
    }

    if ( ! $used_taxonomy ) {
        $attr_name = 'Seat';
        $attr_key  = sanitize_title( $attr_name );
        $attributes = array(
            $attr_key => array(
                'name'         => $attr_name,
                'value'        => 'Seat',
                'position'     => 0,
                'is_visible'   => 0,
                'is_variation' => 1,
                'is_taxonomy'  => 0,
            ),
        );
        update_post_meta( $product_id, '_product_attributes', $attributes );
    }

    update_post_meta( $product_id, '_virtual', 'yes' );
    update_post_meta( $product_id, '_price', wc_format_decimal( $price ) );
    update_post_meta( $product_id, '_stock_status', 'instock' );
    update_post_meta( $product_id, 'twwt_is_webinar', '1' );
    update_post_meta( $product_id, 'wooticket_status', 'enabled' );

    update_post_meta( $product_id, '_manage_stock', 'no' );
    update_post_meta( $product_id, 'woo_seat_show', '1' );

    $variation_post = array(
        'post_title'  => 'Seat',
        'post_status' => 'publish',
        'post_parent' => $product_id,
        'post_type'   => 'product_variation',
    );
    $variation_id = wp_insert_post( $variation_post );

    if ( ! $variation_id ) {
        return new WP_Error('variation_fail','Could not create variation.');
    }

    if ( $used_taxonomy && ! empty( $attr_key ) ) {
        $variation_attr_key = 'attribute_' . $attr_key;
        update_post_meta( $variation_id, $variation_attr_key, $term_slug );
    } else {
        $variation_attr_key = 'attribute_' . $attr_key;
        update_post_meta( $variation_id, $variation_attr_key, 'Seat' );
    }

    update_post_meta( $variation_id, '_regular_price', wc_format_decimal( $price ) );
    update_post_meta( $variation_id, '_price', wc_format_decimal( $price ) );
    update_post_meta( $variation_id, '_virtual', 'yes' );
    update_post_meta( $variation_id, '_manage_stock', 'yes' );
    update_post_meta( $variation_id, '_backorders', 'no' );
    update_post_meta( $variation_id, '_stock', intval( $max_seats ) );
    update_post_meta( $variation_id, '_stock_status', intval( $max_seats ) > 0 ? 'instock' : 'outofstock' );

    update_post_meta( $variation_id, '_variable_text_field', intval( $max_seats ) );

    update_post_meta( $variation_id, 'woo_seat_show', '1' );

    if ( ! empty( $files['twwt_image']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'twwt_image', $product_id );
        if ( ! is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $product_id, $attachment_id );
        }
    }

    delete_post_meta( $product_id, '_wc_product_children' );

    wc_delete_product_transients( $product_id );

    if ( class_exists( 'WC_Product_Variable' ) ) {
        WC_Product_Variable::sync( $product_id );
    }
    if ( function_exists( 'wc_update_product_lookup_tables' ) ) {
        wc_update_product_lookup_tables( $product_id );
    }

    $product = wc_get_product( $product_id );
    if ( $product ) {
        $product->set_catalog_visibility( 'visible' );
        $product->save();
    }

    if ( $send_notification ) {

        update_post_meta( $product_id, 'twwt_np_notification_sent', 1 );

        $settings = get_option('twwt_woo_settings');
        $mode = isset($settings['notification_mode']) ? $settings['notification_mode'] : 'immediate';

        if ( $mode === 'immediate' ) {
            update_option( 'twwt_np_notification_auto_start_post_id', $product_id );
        } else {
            $queue = get_option('twwt_batch_queue', array());
            if ( ! is_array( $queue ) ) {
                $queue = array();
            }
            if ( ! in_array( $product_id, $queue ) ) {
                $queue[] = $product_id;
                update_option( 'twwt_batch_queue', $queue );
            }
        }
    }

    return intval( $product_id );
}
