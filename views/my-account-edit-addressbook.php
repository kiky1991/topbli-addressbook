<?php global $pok_helper; ?>
<div class="woocommerce-notices-wrapper"></div>

<form method="post">

    <h3><?php esc_html_e('Shipping address book', 'topdress'); ?></h3>
    <div class="woocommerce-address-fields">
        <div class="woocommerce-address-fields__field-wrapper-pok">
            <?php $form->get_fields('form_edit_addressbook'); ?>
            <input type="hidden" name="id_address" id="id_address" value="<?php esc_attr_e($address['id_address']); ?>">
            <?php if (!$pok_helper->is_use_simple_address_field()) : ?>
                <input type="hidden" name="shipping_state_name" id="shipping_state_name" value="<?php esc_attr_e($address['state']); ?>">
                <input type="hidden" name="shipping_city_name" id="shipping_city_name" value="<?php esc_attr_e($address['city']); ?>">
                <input type="hidden" name="shipping_district_name" id="shipping_district_name" value="<?php esc_attr_e($address['district']); ?>">
            <?php else : ?>
                <input type="hidden" name="shipping_simple_address_name" id="shipping_simple_address_name" value="<?php esc_attr_e("{$address['district']}, {$address['city']}, {$address['state']}"); ?>">
            <?php endif; ?>
        </div>
        <p class="submit">
            <?php wp_nonce_field('topdress_edit_address', 'topdress_edit_address_nonce'); ?>
            <input type="submit" name="topdress_submit_address" class="woocommerce-button button" value="<?php esc_attr_e('Save Address', 'topdress'); ?>">
            <input type="hidden" name="action" value="topdress_edit_address" />
        </p>
    </div>
</form>

<script type="text/javascript">
    jQuery(function($) {
        $(document).on('ready', function() {
            $('li.woocommerce-MyAccount-navigation-link--edit-address').addClass('is-active');
            $('#shipping_state').val(<?php echo $address['state_id'] ?>).trigger('change');

            $('#shipping_city').on('options_loaded', function(e) {
                $('#shipping_city').val(<?php echo $address['city_id'] ?>).trigger('change');
            });

            $('#shipping_district').on('options_loaded', function(e) {
                $('#shipping_district').val(<?php echo $address['district_id'] ?>).trigger('change');
            });
        });

        <?php if (!$pok_helper->is_use_simple_address_field()) : ?>
            $('#shipping_state').on('change', function() {
                const state = $('#select2-shipping_state-container').attr('title');
                $('#shipping_state_name').val(state);
            });

            $('#shipping_city').on('change', function() {
                const city = $('#select2-shipping_city-container').attr('title');
                $('#shipping_city_name').val(city);
            });

            $('#shipping_district').on('change', function() {
                const district = $('#select2-shipping_district-container').attr('title');
                $('#shipping_district_name').val(district);
            });
        <?php else : ?>
            $('#shipping_simple_address').on('change', function() {
                const simple_address = $('#select2-shipping_simple_address-container').attr('title');
                $('#shipping_simple_address_name').val(simple_address);
            });
        <?php endif; ?>
    });
</script>