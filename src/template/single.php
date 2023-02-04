<?php
/*
* Used when a visitor requests a single post type.
*
* You can create a file like single-{post-type}.php
* if you want touch a specific custom
*
* OR
*
* You can use is_singular($post_type) in this file.
* See: https://developer.wordpress.org/reference/functions/is_singular/
*/

get_header();
while(have_posts()) : the_post();


endwhile; get_footer(); ?>