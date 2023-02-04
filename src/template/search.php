<?php

$search_query = isset($_GET['s']) ? $_GET['s'] : false;

if(!$search_query){
	header('location: ' . get_bloginfo('url'));
	exit;
}


$posts_per_page = 7;
$current_page = isset($_GET['page']) ? $_GET['page'] : 1;

$results = new WP_Query([
    'posts_per_page' => $posts_per_page,
    'paged' => $current_page,
    's' => $search_query
]);

$posts_count = $results->found_posts;
$number_of_pages = (int)ceil($posts_count / $posts_per_page);

?>