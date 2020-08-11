<?php

add_filter( 'wp_get_nav_menu_items', 'goodpill_main_menu', 10, 2 );

function goodpill_main_menu ( $items, $args ) {
    if($args->slug === 'footer')
        return null;

    if($args->slug !== 'primary')
        return $items;
    $items = [];

    $items[] =  custom_nav_menu_item( 'Check Our Stock', home_url('/gp-stock/') , 1 );
    $items[] =  custom_nav_menu_item( 'Providers & Caregivers', home_url('/providers-caregivers/'), 2 );
    $items[] =  custom_nav_menu_item( 'Questions', home_url('/faq/'), 3 );

    if(is_user_logged_in()){
        $items[] =  custom_nav_menu_item( 'My Account', home_url('/account/details'), 4 );
        $items[] =  custom_nav_menu_item( 'Logout', wp_logout_url(), 5 );
    }
    else{
        $items[] =  custom_nav_menu_item( 'Get Started', home_url('/account/?gp-register'), 4 );
        $items[] =  custom_nav_menu_item( 'Login', home_url('/account/?gp-login'), 5 );

    }

    return $items;
}

function custom_nav_menu_item( $title, $url, $order, $parent = 0 ){
    $item = new stdClass();
    $item->ID = 1000000 + $order + $parent;
    $item->db_id = $item->ID;
    $item->title = $title;
    $item->url = $url;
    $item->menu_order = $order;
    $item->menu_item_parent = $parent;
    $item->type = '';
    $item->object = '';
    $item->object_id = '';
    $item->classes = array();
    $item->target = '';
    $item->attr_title = '';
    $item->description = '';
    $item->xfn = '';
    $item->status = '';
    return $item;
}
