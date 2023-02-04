<?php
/*
* Used when a visitor requests a term in tag taxonomy (Only for post type 'post').
*
* You can create a file like tag-{slug}.php
* or tag-{ID}.php if you want touch a specific tag
*
* See: https://developer.wordpress.org/themes/template-files-section/taxonomy-templates/#tag
*/

$term_object = $obj = get_queried_object();

get_header();


get_footer(); ?>