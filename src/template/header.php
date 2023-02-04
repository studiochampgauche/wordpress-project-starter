<?php
$gate_html_tags[] = scg::field('html_tags_after_open_head');
$gate_html_tags[] = scg::field('html_tags_before_close_head');
$gate_html_tags[] = scg::field('html_tags_after_open_body');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<?php
		if(!empty($gate_html_tags[0]))
			echo $gate_html_tags[0];
		

		wp_head();


		if(!empty($gate_html_tags[1]))
			echo $gate_html_tags[1];
	?>
</head>

<body data-base-url="<?= home_url(); ?>" data-barba="wrapper">

	<?php
		if(!empty($gate_html_tags[2]))
			echo $gate_html_tags[2];
	?>

	<div id="viewport">

		<header></header>
		
		<div id="pageWrapper">
			<div id="pageContent">

				<main data-barba="container" data-barba-namespace="<?= get_post_field('post_name'); ?>">