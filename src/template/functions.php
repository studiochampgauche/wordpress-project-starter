<?php
	
	class helloChamp{

		function __construct(){

			add_action('wp_enqueue_scripts', function(){

				wp_localize_script('scg-main', 'SYSTEM', [
					'ajaxurl' => admin_url('admin-ajax.php'),
					'lang' => 'fr'
				]);
				
			}, 11);

		}

	}

	new helloChamp();
?>