<?php
/*
* Used when a visitor requests the single post type "page".
* In other words, the page view.
*
* You can create a file like page-{slug}.php
* or page-{ID}.php if you want touch a specific page
*
* OR
*
* You can use is_page($page) in this file.
* See: https://developer.wordpress.org/reference/functions/is_page/
*/

get_header();
while(have_posts()) : the_post();


endwhile; get_footer(); ?>