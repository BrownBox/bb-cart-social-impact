<?php
/*
Plugin Name: BB Cart - Social Impact Addon
Description: Add addon for BB Cart that allows to display popups that represent recent transactions
Version: 0.0.1
Author: Brown Box
Author URI: http://brownbox.net.au
License: GPLv2
Copyright 2016 Brown Box
*/

// Define paths
define( 'BB_CART_SOCIAL_IMPACT_PLUGIN_DIR', plugin_dir_path(__FILE__) );
define( 'BB_CART_SOCIAL_IMPACT_ADMIN_DIR', BB_CART_SOCIAL_IMPACT_PLUGIN_DIR . 'admin/' );
define( 'BB_CART_SOCIAL_IMPACT_VENDOR_DIR', BB_CART_SOCIAL_IMPACT_PLUGIN_DIR . 'vendor/' );
define( 'BB_CART_SOCIAL_IMPACT_VENDOR_IA_DIR', BB_CART_SOCIAL_IMPACT_VENDOR_DIR . 'information-architecture/' );

// Load required files
require_once( BB_CART_SOCIAL_IMPACT_VENDOR_IA_DIR . 'cpt_.php' );
require_once( BB_CART_SOCIAL_IMPACT_VENDOR_IA_DIR . 'meta_.php' );
require_once( BB_CART_SOCIAL_IMPACT_VENDOR_IA_DIR . 'tax_.php' );
require_once( BB_CART_SOCIAL_IMPACT_VENDOR_IA_DIR . 'tax_meta_.php' );
require_once( BB_CART_SOCIAL_IMPACT_VENDOR_IA_DIR . 'tax_meta_.php' );
require_once( BB_CART_SOCIAL_IMPACT_ADMIN_DIR . 'settings.php' );

/**
 * Actions and filters: links
 */

// Run the check for BB Cart existence when the plugins have been loaded
add_action('plugins_loaded', 'bb_cart_social_impact_init');

// Define information architecture of the plugin
add_action( 'init', 'bb_cart_social_impact_ia', 1 );
add_action( 'wp_footer', 'bb_cart_social_impact_run', 20 );

/**
 * Actions and filters: implementations
 */

/**
 * Check that BB Cart plugin is activated
 */
function bb_cart_social_impact_init() {

    if ( !defined('BB_CART_SESSION_ITEM') ) {
        add_action( 'admin_init', 'bb_cart_social_impact_deactivate' );
        add_action( 'admin_notices', 'bb_cart_social_impact_deactivate_notice' );
    }
}

/**
 * Deactivate plugin
 */
function bb_cart_social_impact_deactivate() {
    deactivate_plugins( plugin_basename( __FILE__ ) );
}

/**
 * Display deactivation notice
 */
function bb_cart_social_impact_deactivate_notice() {
    echo '<div class="updated"><p><strong>BB Cart Social Impact Addon</strong> has been <strong>deactivated</strong> as it requires BB Cart.</p></div>';
    if ( isset( $_GET['activate'] ) ) {
        unset( $_GET['activate'] );
    }
}

/**
 * Define information architecture for the plugin
 */
function bb_cart_social_impact_ia() {}

/**
 * Process meta value in case it is a token but not a value
 *
 * @param string $meta_value
 * @param WP_Post $post
 *
 * @return mixed
 */
function bb_cart_social_impact_process_meta_value( $meta_value, WP_Post $post ) {

    switch ($meta_value ) {

        case '%%post_id%%':
            $meta_value = $post->ID;
            break;

    }

    return $meta_value;

}

/**
 * Genreate mark up, CSS and JavaScript for the plugin
 * 
 * @param string $content
 *
 * @return string
 */
function bb_cart_social_impact_run() {

    global $post;

    $number_of_transactions = get_option('bb_cart_social_impact_setting_number_of_posts_to_read');
    $meta_key = get_option('bb_cart_social_impact_setting_meta_key');
    $meta_value = get_option('bb_cart_social_impact_setting_meta_value');
    $number_to_display = get_option('bb_cart_social_impact_setting_number_of_posts_to_display');
    $popup_template = get_option('bb_cart_social_impact_setting_popup_text');
    $animation_duration = get_option('bb_cart_social_impact_setting_animation_duration');
    $duration = get_option('bb_cart_social_impact_setting_duration') * 1000 + $animation_duration;
    $interval = get_option('bb_cart_social_impact_setting_interval') * 1000 + $duration + $animation_duration;
    $pages = get_option('bb_cart_social_impact_setting_pages');
    $repeat_popups = get_option('bb_cart_social_impact_setting_repeat_popups');

    if ( !empty( $pages ) && !in_array( $post->ID, $pages ) ) {
        return;
    }

    $meta_value = bb_cart_social_impact_process_meta_value( $meta_value, $post );

    $meta_query = null;

    if ( $meta_key ) {
        $meta_query = array(
            array(
                'key' => $meta_key,
                'value' => $meta_value,
            )
        );
    }

    $args = array(
        'post_type' => 'transaction',
        'numberposts' => $number_of_transactions,
        'meta_query' =>  $meta_query
    );

    // Get transactions associated with the campaign
    $campaign_transactions = get_posts( $args );
    $campaign_transactions_keys = array_keys( $campaign_transactions );
    $number_to_display = count( $campaign_transactions_keys ) < $number_to_display ? count( $campaign_transactions_keys ) : $number_to_display;
    $random_transaction_keys = array_rand( $campaign_transactions_keys, $number_to_display);
    $random_transactions = array();

    foreach ( $random_transaction_keys as $random_transaction_key ) {
        $random_transactions[] = $campaign_transactions[ $random_transaction_key ];
    }

    $notices_js_array = array();

    foreach ($random_transactions as $random_transaction ) {

        // Get related form entry
        $entry = unserialize( $random_transaction->post_content );

        // Load settings of the related form
        $form = GFAPI::get_form( $entry['form_id'] );

        foreach ( $form['fields'] as $field ) {

            switch ($field['type']) {
                case 'name':
                    foreach ( $field['inputs'] as $sub_field ) {
                        switch ($sub_field['name']) {
                            case 'bb_cart_first_name':
                                $first_name = $entry[ (string)$sub_field['id'] ];
                                break;
                            case 'bb_cart_last_name':
                                $last_name = $entry[ (string)$sub_field['id'] ];
                                break;
                        }
                    }
                    break;
            }

        }

        $full_name = $first_name . ' ' . $last_name;
        $amount = $random_transaction->donation_amount;
        $notices_js_array[] = '{full_name:"' . $full_name . '", first_name:"' . $first_name . '", last_name:"' . $last_name . ' ' . $last_name . '", amount:"' . $amount . '"}';

    }

    $notices_js = implode( ',', $notices_js_array );
    $notices_js_array_count = count( $notices_js_array );

    $template = <<<MULTI

<style>

.popup-notice  {
    z-index: 999;
    position: fixed;
    bottom: 100px;
    left: 16px;
    background: #007095;
    border-radius: 0px;
    border-left: 6px solid #f86425;
    color: #ffffff;
    width: 335px;
    padding: 12px 17px 10px 10px;
    cursor: pointer;
    transition: opacity .8s linear, -webkit-transform 1s ease;
    transition: transform 1s ease, opacity .8s linear;
    transition: transform 1s ease, opacity .8s linear, -webkit-transform 1s ease;
    -webkit-transform: translateY(100px);
    -ms-transform: translateY(100px);
    transform: translateY(100px);
    opacity: 1;
    display: none;
}

.popup-notice p {
    margin-bottom: 0;
}

</style>

<div class="hide-for-small-only">
    <div class="popup-notice active">
        <p>
            {$popup_template}
        </p>
    </div>
</div>

<script>

    jQuery(document).ready(function() {

        current = 0;
        notices = [{$notices_js}];
        interval_callback = setInterval( toggle_popup_notice, {$interval} );
    
        function toggle_popup_notice( ) {
      
            console.log(notices[current]);
      
            jQuery(".popup-notice .first_name").html( notices[current]["first_name"] );
            jQuery(".popup-notice .last_name").html( notices[current]["last_name"] );
            jQuery(".popup-notice .full_name").html( notices[current]["full_name"] );
            jQuery(".popup-notice .amount").html( notices[current]["amount"] );
            jQuery(".popup-notice").slideDown({$animation_duration}).delay({$duration}).slideUp({$animation_duration});
            
            current ++;
            if (current >= {$notices_js_array_count}) {
                if ({$repeat_popups}) {
                    current = 0; 
                } else {
                    clearInterval( interval_callback );
                }
             
            } 
            
        }
        
    });

</script>

MULTI;

    echo $template;

}