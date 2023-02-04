<?php
/*
* Used when a visitor requests a term in a custom taxonomy.
*
* You can create a file like taxonomy-{taxonomy}.php if you want touch terms in specific taxonomy
* or taxonomy-{taxonomy}-{term}.php if you want touch a specific term
*
* See: https://developer.wordpress.org/themes/template-files-section/taxonomy-templates/#custom-taxonomy
*/

$term_object = $obj = get_queried_object();

get_header();


get_footer(); ?>