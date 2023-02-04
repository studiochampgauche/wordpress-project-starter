<?php
/*
* Used when a visitor requests a term in category taxonomy (Only for post type 'post').
*
* You can create a file like category-{slug}.php
* or category-{ID}.php if you want touch a specific category
*
* See: https://developer.wordpress.org/themes/template-files-section/taxonomy-templates/#category
*/

$term_object = $obj = get_queried_object();

get_header();


get_footer(); ?>