<?php
/**
 * Free Divi Child Theme by Pee-Aye Creative
 * Functions.php
 *
 * ===== NOTES ==================================================================
 * 
 * New to Divi? Take our full Divi course: https://www.peeayecreative.com/product/beyond-the-builder-the-ultimate-divi-website-course/
 * 
 * Learn cool tricks and features with our Divi tutorials: https://www.peeayecreative.com/blog/
 * 
 * Discover our premium Divi products: https://www.peeayecreative.com/shop/
 * 
 * =============================================================================== */
 
function divichild_enqueue_scripts() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_script( 'custom-js', get_stylesheet_directory_uri() . '/js/scripts.js', array( 'jquery' ));
}
add_action( 'wp_enqueue_scripts', 'divichild_enqueue_scripts' );


//you can add custom functions below this line:






