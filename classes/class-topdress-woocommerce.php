<?php

/**
 * TOPDRRESS_Woocommerce class
 */
class TOPDRESS_Woocommerce
{

    /**
     * TOPDRESS_Woocommerce::__construct
     */
    public function __construct()
    {
        $this->core = new TOPDRESS_Core();

        add_action('init', array($this, 'add_pages'));
        add_action('woocommerce_account_edit-address/add-addressbook_endpoint', array($this, 'endpoint_content_add_addressbook'));
        add_action('woocommerce_account_edit-address/edit-addressbook_endpoint', array($this, 'endpoint_content_edit_addressbook'));
        add_action("wp_enqueue_scripts", array($this, 'register_assets'));
        add_filter('woocommerce_order_get_formatted_shipping_address', array($this, 'change_address'), 10, 3);
        add_action('template_redirect', array($this, 'save_address'));
        add_action('template_redirect', array($this, 'edit_address'));
        add_filter('woocommerce_checkout_fields', array($this, 'custom_fields'), 999, 1);
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_fields'));

        // edit-address
        // add_action('woocommerce_after_edit_account_address_form', array($this, 'table_address'), 50, 1);
        add_filter('wc_get_template_part', array($this, 'override_woocommerce_template_part'), 10, 3);
        add_filter('woocommerce_locate_template', array($this, 'override_woocommerce_template'), 10, 3);

        // my account
        add_filter('woocommerce_my_account_my_address_formatted_address', array($this, 'format_myaccount_address'), 10, 3);
        add_filter('woocommerce_localisation_address_formats', array($this, 'my_account_address_localisation'), 50, 3);
        add_filter('woocommerce_formatted_address_replacements', array($this, 'my_account_address_replacements'), 50, 2);
    }

    public function add_pages()
    {
        add_rewrite_endpoint('edit-address/add-addressbook', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('edit-address/edit-addressbook', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }

    public function endpoint_content_add_addressbook()
    {
        if (wc_get_page_permalink('myaccount') . 'edit-address/add-addressbook') {
            $form = new TOPDRESS_Form();
            include_once TOPDRESS_PLUGIN_PATH . 'views/my-account-add-addressbook.php';
        }
    }

    public function endpoint_content_edit_addressbook()
    {
        global $pok_helper;

        if (!isset($_GET['id']) || !$addresses = $this->core->is_addressbook($_GET['id'])) {
            echo "<p>no found what you looking for.</p>";
            return;
        }

        $form = new TOPDRESS_Form();
        $address = $addresses[0];
        include_once TOPDRESS_PLUGIN_PATH . 'views/my-account-edit-addressbook.php';
    }

    /**
     * TOPDROP_Woocommerce::register_assets
     * 
     * register assets
     * 
     * @return  file  
     */
    public function register_assets()
    {
        global $pok_helper;

        if (is_wc_endpoint_url('edit-address')) {
            wp_register_script('datatables', 'https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js', array('jquery'), true);
            wp_register_script('datatables_bootstrap', 'https://cdn.datatables.net/1.10.13/js/dataTables.bootstrap.min.js', array('jquery'), true);
            wp_register_script('datatables_select', 'https://cdn.datatables.net/select/1.3.1/js/dataTables.select.min.js', array('jquery'), true);
            wp_register_style('bootstrap_style', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css');
            wp_register_style('datatables_style', 'https://cdn.datatables.net/1.10.13/css/dataTables.bootstrap.min.css');
            wp_enqueue_script('datatables');
            wp_enqueue_script('datatables_bootstrap');
            wp_enqueue_script('datatables_select');
            wp_enqueue_style('bootstrap_style');
            wp_enqueue_style('datatables_style');
        }

        if (is_wc_endpoint_url('edit-address') || wp_unslash($_SERVER['REQUEST_URI']) == '/my-account/edit-address/add-addressbook') {
            wp_enqueue_style('topdress-edit-address', TOPDRESS_PLUGIN_URI . '/assets/css/edit-address.css', '', TOPDRESS_VERSION);
            wp_enqueue_script('topdress-edit-address', TOPDRESS_PLUGIN_URI . "/assets/js/edit-address.js", array('jquery'), TOPDRESS_VERSION, true);
            wp_localize_script(
                'topdress-edit-address',
                'topdress',
                array(
                    'url' => admin_url('admin-ajax.php'),
                )
            );
            wp_localize_script(
                'topdress-edit-address',
                'nonce',
                array(
                    'delete' => wp_create_nonce('topdress-delete-address-nonce'),
                    'set_default' => wp_create_nonce('topdress-set-default-address-nonce'),
                    'bulk_delete_address' => wp_create_nonce('topdress-bulk-delete-address-nonce'),
                    'search_term' => wp_create_nonce('topdress-search-address-term-nonce'),
                    'datatable' => wp_create_nonce('topdress-addressbook-datatables-nonce')
                )
            );
        }

        if (is_checkout()) {
            ob_start();
            include_once TOPDRESS_PLUGIN_PATH . 'views/checkout-button-load-addressbook.php';
            $button = ob_get_contents();
            ob_end_clean();

            ob_start();
            include_once TOPDRESS_PLUGIN_PATH . 'views/checkout-form-load-addressbook.php';
            $form = ob_get_contents();
            ob_end_clean();

            wp_enqueue_style('topdress-checkout', TOPDRESS_PLUGIN_URI . '/assets/css/checkout.css', '', TOPDRESS_VERSION);
            wp_enqueue_script('topdress-checkout', TOPDRESS_PLUGIN_URI . "/assets/js/checkout.js", array('jquery'), TOPDRESS_VERSION, true);
            wp_localize_script(
                'topdress-checkout',
                'topdress',
                array(
                    'url'                           => admin_url('admin-ajax.php'),
                    'islogin'                       => is_user_logged_in(),
                    'load_button'                   => $button,
                    'load_form'                     => $form,
                    'default_shipping'              => $this->get_default_shipping(),
                    'is_use_simple_address_field'   => $pok_helper->is_use_simple_address_field(),
                )
            );
            wp_localize_script(
                'topdress-checkout',
                'nonce',
                array(
                    'load_addressbook' => wp_create_nonce('topdress-checkout-load-addressbook-nonce'),
                    'search_addressbook' => wp_create_nonce('topdress-search-address-term-nonce'),
                    'get_detail_address' => wp_create_nonce('topdress-get-detail-address-nonce'),
                )
            );
        }
    }

    public function change_address($address, $raw_address, $order)
    {
        $address_tag = get_post_meta($order->get_id(), '_topdress_address_tag', true);

        if (is_wc_endpoint_url('view-order') && !empty($address_tag)) {
            $address .= nl2br("\n($address_tag)");
        }

        return $address;
    }

    public function table_address($array)
    {
        $q = array(
            'id_user'   => array(
                'separator' => '=',
                'value'     => get_current_user_id()
            )
        );

        $user_id = get_current_user_id();
        $address_id = get_user_meta($user_id, 'topdress_address_id', true);

        $limit = 10;
        $paged = 1;
        $offset = ($limit * $paged) - $limit;
        $addresses = $this->core->list_addressbook($q, $limit, $offset);
        include_once TOPDRESS_PLUGIN_PATH . 'views/my-account-address.php';
    }

    /**
     * TOPDRESS_Woocommerce::save_address
     * 
     * Save address form
     * @access  public
     * 
     * @return  html
     */
    public function save_address()
    {
        global $pok_helper;
        $nonce_value = wc_get_var($_REQUEST['topdress_save_address_nonce'], wc_get_var($_REQUEST['_wpnonce'], '')); // @codingStandardsIgnoreLine.

        if (!wp_verify_nonce($nonce_value, 'topdress_save_address')) {
            return;
        }

        if (empty($_POST['action']) || 'topdress_save_address' !== $_POST['action']) {
            return;
        }

        wc_nocache_headers();

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        $required = ['first_name' => 'First Name', 'last_name' => 'Last Name', 'shipping_state' => 'State', 'shipping_city' => 'City', 'shipping_district' => 'District', 'address_1' => 'Address', 'phone' => 'Phone'];
        $errors = array();
        foreach (array_keys($_POST) as $post) {
            if (empty($_POST[$post]) && in_array($post, array_keys($required))) {
                $errors[] = $required[$post];
            }
        }

        if (!empty($errors)) {
            wc_add_notice(__(implode(', ', $errors) . ' is a required field', 'topdress'), 'error');
            update_user_meta(get_current_user_id(), 'error_field_addressbook', $_POST);
            wp_safe_redirect(wc_get_endpoint_url('edit-address/add-addressbook', '', wc_get_page_permalink('myaccount')));
            exit;
        }

        if ($pok_helper->is_use_simple_address_field()) {
            $ship_id = explode('_', isset($_POST['shipping_simple_address']) ? sanitize_text_field(wp_unslash($_POST['shipping_simple_address'])) : '');
            $ship_name = explode(', ', isset($_POST['shipping_simple_address_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_simple_address_name'])) : '');

            $custom_shipping = array(
                'state_id'      => $ship_id[2],
                'state'         => $ship_name[2],
                'city_id'       => $ship_id[1],
                'city'          => $ship_name[1],
                'district_id'   => $ship_id[0],
                'district'      => $ship_name[0],
            );
        } else {
            $custom_shipping = array(
                'state_id'      => isset($_POST['shipping_state']) ? sanitize_text_field(wp_unslash($_POST['shipping_state'])) : 0,
                'state'         => isset($_POST['shipping_state_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_state_name'])) : '',
                'city_id'       => isset($_POST['shipping_city']) ? sanitize_text_field(wp_unslash($_POST['shipping_city'])) : 0,
                'city'          => isset($_POST['shipping_city_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_city_name'])) : '',
                'district_id'   => isset($_POST['shipping_district']) ? sanitize_text_field(wp_unslash($_POST['shipping_district'])) : 0,
                'district'      => isset($_POST['shipping_district_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_district_name'])) : '',
            );
        }

        $data = array(
            'id_user'   => get_current_user_id(),
            'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
            'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
            'country' => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '',
            'address_1' => isset($_POST['address_1']) ? sanitize_text_field(wp_unslash($_POST['address_1'])) : '',
            'address_2' => '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'postcode' => ($_POST['postcode'] == '') ? null : sanitize_text_field(wp_unslash($_POST['postcode'])),
            'tag' => isset($_POST['tag']) ? sanitize_text_field(wp_unslash($_POST['tag'])) : '',
        );

        $new_data = array_merge($data, $custom_shipping);
        $result = $this->core->update_addressbook($new_data);
        if ($result) {
            wc_add_notice(__('Shipping address has been save.', 'topdress'));
        } else {
            wc_add_notice(__('Cannot save shipping address, call your administrator.', 'topdress'), 'error');
        }

        wp_safe_redirect(wc_get_endpoint_url('edit-address/add-addressbook', '', wc_get_page_permalink('myaccount')));
        exit;
    }

    /**
     * TOPDRESS_Woocommerce::edit_address
     * 
     * Edit address form
     * @access  public
     * 
     * @return  html
     */
    public function edit_address()
    {
        global $pok_helper;
        $nonce_value = wc_get_var($_REQUEST['topdress_edit_address_nonce'], wc_get_var($_REQUEST['_wpnonce'], '')); // @codingStandardsIgnoreLine.

        if (!wp_verify_nonce($nonce_value, 'topdress_edit_address')) {
            return;
        }

        if (empty($_POST['action']) || 'topdress_edit_address' !== $_POST['action']) {
            return;
        }

        wc_nocache_headers();

        if (!isset($_POST['id_address']) || !$this->core->is_addressbook($_POST['id_address'])) {
            return;
        }

        if (!$pok_helper->is_use_simple_address_field()) {
            $required_shipping = [
                'shipping_state_name' => 'State Name',
                'shipping_state' => 'State ID',
                'shipping_city_name' => 'City Name',
                'shipping_city' => 'City ID',
                'shipping_district_name' => 'District Name',
                'shipping_district' => 'District ID',
            ];
        } else {
            $required_shipping = [
                'shipping_simple_address_name' => 'City / Town Name',
                'shipping_simple_address' => 'City / Town',
            ];
        }

        $required = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'address_1' => 'Address',
            'phone' => 'Phone',
            'tag' => 'Tag Address'
        ];

        $requireds = array_merge($required, $required_shipping);
        $errors = array();
        foreach (array_keys($_POST) as $post) {
            if (empty($_POST[$post]) && in_array($post, array_keys($requireds))) {
                $errors[] = $requireds[$post];
            }
        }

        if (!empty($errors)) {
            wc_add_notice(__(implode(', ', $errors) . ' is a required field', 'topdress'), 'error');
            wp_safe_redirect(wc_get_page_permalink('myaccount') . 'edit-address/edit-addressbook?id=' . $_POST['id_address']);
            exit;
        }

        if (!$pok_helper->is_use_simple_address_field()) {
            $data_shipping = array(
                'state_id' => sanitize_text_field(wp_unslash($_POST['shipping_state'])),
                'state' => sanitize_text_field(wp_unslash($_POST['shipping_state_name'])),
                'city_id' => sanitize_text_field(wp_unslash($_POST['shipping_city'])),
                'city' => sanitize_text_field(wp_unslash($_POST['shipping_city_name'])),
                'district_id' => sanitize_text_field(wp_unslash($_POST['shipping_district'])),
                'district' => sanitize_text_field(wp_unslash($_POST['shipping_district_name'])),
            );
        } else {
            $ship_id = explode('_', isset($_POST['shipping_simple_address']) ? sanitize_text_field(wp_unslash($_POST['shipping_simple_address'])) : '');
            $ship_name = explode(', ', isset($_POST['shipping_simple_address_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_simple_address_name'])) : '');

            $data_shipping = array(
                'state_id'      => $ship_id[2],
                'state'         => $ship_name[2],
                'city_id'       => $ship_id[1],
                'city'          => $ship_name[1],
                'district_id'   => $ship_id[0],
                'district'      => $ship_name[0],
            );
        }

        $data = array(
            'id_user'   => get_current_user_id(),
            'id_address' => sanitize_text_field(wp_unslash($_POST['id_address'])),
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'])),
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'])),
            'country' => sanitize_text_field(wp_unslash($_POST['country'])),
            'address_1' => sanitize_text_field(wp_unslash($_POST['address_1'])),
            'address_2' => '',
            'phone' => sanitize_text_field(wp_unslash($_POST['phone'])),
            'postcode' => ($_POST['postcode'] == '') ? null : sanitize_text_field(wp_unslash($_POST['postcode'])),
            'tag' => sanitize_text_field(wp_unslash($_POST['tag'])),
        );

        $datas = array_merge($data, $data_shipping);
        $result = $this->core->update_addressbook($datas, true);
        if ($result) {
            // $user_id = get_current_user_id();
            // $address_id = get_user_meta($user_id, 'topdress_address_id', true);
            // if ($address_id == $datas['id_address']) {
            //     update_user_meta($user_id, 'topdress_address_tag', $datas['tag']);
            //     update_user_meta($user_id, 'shipping_first_name', $datas['first_name']);
            //     update_user_meta($user_id, 'shipping_last_name', $datas['last_name']);
            //     update_user_meta($user_id, 'shipping_country', $datas['country']);
            //     update_user_meta($user_id, 'shipping_state_id', $datas['state_id']);
            //     update_user_meta($user_id, 'shipping_state', $datas['state']);
            //     update_user_meta($user_id, 'shipping_city_id', $datas['city_id']);
            //     update_user_meta($user_id, 'shipping_city', $datas['city']);
            //     update_user_meta($user_id, 'shipping_district_id', $datas['district_id']);
            //     update_user_meta($user_id, 'shipping_district', $datas['district']);
            //     update_user_meta($user_id, 'shipping_address_1', $datas['address_1']);
            //     update_user_meta($user_id, 'shipping_postcode', $datas['postcode']);
            //     update_user_meta($user_id, 'shipping_phone', $datas['phone']);
            // }

            wc_add_notice(__('Shipping address has been edit.', 'topdress'));
        } else {
            wc_add_notice(__('Cannot edit shipping address, call your administrator.', 'topdress'), 'error');
        }

        wp_safe_redirect(wc_get_page_permalink('myaccount') . 'edit-address/edit-addressbook?id=' . $_POST['id_address']);
        exit;
    }

    /**
     * Template Part's
     *
     * @param  string $template Default template file path.
     * @param  string $slug     Template file slug.
     * @param  string $name     Template file name.
     * @return string           Return the template part from plugin.
     */
    function override_woocommerce_template_part($template, $slug, $name)
    {
        // UNCOMMENT FOR @DEBUGGING
        // echo '<pre>';
        // echo 'template: ' . $template . '<br/>';
        // echo 'slug: ' . $slug . '<br/>';
        // echo 'name: ' . $name . '<br/>';
        // echo '</pre>';
        // Template directory.
        // E.g. /wp-content/plugins/my-plugin/woocommerce/

        $template_directory = TOPDRESS_PLUGIN_PATH . '/woocommerce/';
        if ($name) {
            $path = $template_directory . "{$slug}-{$name}.php";
        } else {
            $path = $template_directory . "{$slug}.php";
        }
        return file_exists($path) ? $path : $template;
    }

    /**
     * Template File
     *
     * @param  string $template      Default template file  path.
     * @param  string $template_name Template file name.
     * @param  string $template_path Template file directory file path.
     * @return string                Return the template file from plugin.
     */
    function override_woocommerce_template($template, $template_name, $template_path)
    {
        // UNCOMMENT FOR @DEBUGGING
        // echo '<pre>';
        // echo 'template: ' . $template . '<br/>';
        // echo 'template_name: ' . $template_name . '<br/>';
        // echo 'template_path: ' . $template_path . '<br/>';
        // echo '</pre>';
        // Template directory.
        // E.g. /wp-content/plugins/my-plugin/woocommerce/

        // var_dump($template);

        $template_directory = TOPDRESS_PLUGIN_PATH . 'woocommerce/';
        $path = $template_directory . $template_name;
        // die($template_directory);
        // file_exists($template_directory) ? die($path) : $template;
        return file_exists($path) ? $path : $template;
    }

    /**
     * Fix name formatting on myaccount page
     *
     * @param  array  $address     Address data.
     * @param  int    $customer_id Customer ID.
     * @param  string $name        Billing/Shipping.
     * @return array               Address data.
     */
    public function format_myaccount_address($address, $customer_id, $name)
    {
        $address['tag'] = get_user_meta($customer_id, $name . '_shipping_tag', true);
        return $address;
    }

    public function my_account_address_localisation($formats)
    {
        if (isset($formats['ID'])) {
            $formats['ID'] .= "\n{address_tag}";
        }

        return $formats;
    }

    public function my_account_address_replacements($replacements, $args)
    {
        $address_tag = get_user_meta(get_current_user_id(), 'topdress_address_tag', true);

        if (is_wc_endpoint_url('edit-address') && !empty($address_tag) && !isset($args['phone'])) {
            $replacements['{address_tag}'] = "($address_tag)";
        } else {
            $replacements['{address_tag}'] = '';
        }

        return $replacements;
    }

    function custom_fields($fields)
    {
        $fields['shipping']['shipping_phone']['label'] = 'Phone';
        $fields['shipping']['shipping_phone']['required'] = true;
        $fields['shipping']['shipping_phone']['class'] = array('form-row-wide');
        $fields['shipping']['shipping_phone']['priority'] = 110;

        $default_address = $this->get_default_shipping();

        if (!empty($default_address)) {
            $options = array(
                'default' => $default_address,
                'add_new' => 'Add New Address'
            );
        } else {
            $options = array(
                'add_new' => 'Add New Address'
            );
        }

        if (is_user_logged_in()) {
            $fields['shipping']['shipping_load_address'] = array(
                'label'                => __('Address Book', 'topdress'),
                'placeholder'          => __('Diana Modar, Jl Cengkareng, Kota Jakarta Barat, DKI Jakarta ', 'topdress'),
                'type'                 => 'select',
                'required'             => false,
                'options'              => $options,
                'class'                => array('form-row-wide', 'topdress-search-addressbook', 'select2-ajax'),
                'custom_attributes'    => array(
                    'data-action'       => 'topdress_search_addressbook',
                    'data-nonce'        => wp_create_nonce('topdress-search-addressbook-nonce')
                ),
                'priority'             => 8,
            );

            $fields['shipping']['shipping_tag']['placeholder'] = 'E.g: Home, Workplace, Customer, etc.';
            $fields['shipping']['shipping_tag']['label'] = 'Address Tag';
            $fields['shipping']['shipping_tag']['class'] = array('form-row-wide');
            $fields['shipping']['shipping_tag']['priority'] = 9;
        }

        return $fields;
    }

    public function save_fields($order_id)
    {
        global $pok_helper;
        if (!is_user_logged_in()) {
            return;
        }

        if (isset($_POST['shipping_tag'])) {
            $tag = sanitize_text_field(wp_unslash($_POST['shipping_tag']));   // WPCS: Input var okay, CSRF ok.
            update_post_meta($order_id, '_topdress_address_tag', $tag);
        }

        if (isset($_POST['shipping_is_add_new']) && $_POST['shipping_is_add_new'] == true) {
            // $district = sanitize_text_field(wp_unslash($_POST['shipping_district']));   // WPCS: Input var okay, CSRF ok.

            $user_id = get_current_user_id();
            // $q = array(
            //     'id_user'   => array(
            //         'separator' => '=',
            //         'value'     => $user_id
            //     ),
            //     'district_id'   => array(
            //         'separator' => '=',
            //         'value'     => $district
            //     ),
            // );

            // $addresses = $this->core->list_addressbook($q);
            // if (is_array($addresses) && count($addresses) < 1) {
            if (!$pok_helper->is_use_simple_address_field()) {
                $data_shipping = array(
                    'state_id' => sanitize_text_field(wp_unslash($_POST['shipping_state'])),
                    'state' => sanitize_text_field(wp_unslash($_POST['shipping_state_name'])),
                    'city_id' => sanitize_text_field(wp_unslash($_POST['shipping_city'])),
                    'city' => sanitize_text_field(wp_unslash($_POST['shipping_city_name'])),
                    'district_id' => sanitize_text_field(wp_unslash($_POST['shipping_district'])),
                    'district' => sanitize_text_field(wp_unslash($_POST['shipping_district_name'])),
                );
            } else {
                $ship_id = explode('_', isset($_POST['shipping_simple_address']) ? sanitize_text_field(wp_unslash($_POST['shipping_simple_address'])) : '');
                $data_shipping = array(
                    'state_id'      => $ship_id[2],
                    'state'         => sanitize_text_field(wp_unslash($_POST['shipping_state_name'])),
                    'city_id'       => $ship_id[1],
                    'city'          => sanitize_text_field(wp_unslash($_POST['shipping_city_name'])),
                    'district_id'   => $ship_id[0],
                    'district'      => sanitize_text_field(wp_unslash($_POST['shipping_district_name'])),
                );
            }

            $data = array(
                'id_user'   => $user_id,
                'first_name' => isset($_POST['shipping_first_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_first_name'])) : '',
                'last_name' => isset($_POST['shipping_last_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_last_name'])) : '',
                'country' => isset($_POST['shipping_country']) ? sanitize_text_field(wp_unslash($_POST['shipping_country'])) : '',
                'address_1' => isset($_POST['shipping_address_1']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_1'])) : '',
                'address_2' => '',
                'phone' => isset($_POST['shipping_phone']) ? sanitize_text_field(wp_unslash($_POST['shipping_phone'])) : '',
                'postcode' => isset($_POST['shipping_postcode']) ? sanitize_text_field(wp_unslash($_POST['shipping_postcode'])) : 0,
                'tag' => isset($_POST['shipping_tag']) ? sanitize_text_field(wp_unslash($_POST['shipping_tag'])) : '',
            );

            $datas = array_merge($data, $data_shipping);
            $this->core->update_addressbook($datas);
            // }
        }
    }

    public function get_default_shipping()
    {
        $user_id = get_current_user_id();

        $first_name = get_user_meta($user_id, 'shipping_first_name', true);
        if (!empty($first_name)) {
            $last_name = get_user_meta($user_id, 'shipping_last_name', true);
            $address_1 = get_user_meta($user_id, 'shipping_address_1', true);
            $district = get_user_meta($user_id, 'shipping_district', true);
            $city = get_user_meta($user_id, 'shipping_city', true);
            $state = get_user_meta($user_id, 'shipping_state', true);

            return "{$first_name} {$last_name}, {$address_1}, {$district}, {$city}, {$state}";
        }

        return;
    }
}
