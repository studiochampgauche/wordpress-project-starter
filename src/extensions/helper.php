<?php
/*
Plugin Name: Champ Gauche Helper
Description: WordPress Handler
Version: 1.0.0
Author: Studio Champ Gauche
Author URI: https://champgauche.studio
Copyright: Studio Champ Gauche
Text Domain: scg-helper
*/


if (!defined('ABSPATH')) exit;

class scg{

	private $acf_path;
	
	function __construct(){

		require_once ABSPATH . 'wp-admin/includes/plugin.php';



		if(!class_exists('ACF')) return;


		/*
		* Remove Admin Bar
		*/
		add_filter('show_admin_bar', '__return_false');


		/*
		* Remove / Register Styles and Scripts
		*/
		add_action('wp_enqueue_scripts', function(){

			/*
			* Remove Basics Styles
			*/

			if(self::field('global_styles') !== 'enable')
				wp_dequeue_style('global-styles');
			
			if(self::field('wp_block_library') !== 'enable')
				wp_dequeue_style('wp-block-library');
			
			if(self::field('classic_theme_styles') !== 'enable')
				wp_dequeue_style('classic-theme-styles');
			

			/*
			* Main Style
			*/
			wp_enqueue_style('scg-main', get_bloginfo('stylesheet_directory').'/assets/css/main.min.css', null, null, null);


			/*
			* Main Javascript
			*/
			wp_enqueue_script('scg-main', get_bloginfo('stylesheet_directory') .'/assets/js/main.js', null, null, true);

		}, 10);


		add_filter('script_loader_tag', function($tag, $handle, $src){
			if ( 'scg-main' !== $handle )
				return $tag;

			$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';

			return $tag;

		} , 10, 3);

		
		/*
		* Clean wp_head
		*/		
		remove_action('wp_head', 'rsd_link');
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'wp_generator');
		remove_action('wp_head', 'start_post_rel_link');
		remove_action('wp_head', 'index_rel_link');
		remove_action('wp_head', 'feed_links', 2);
		remove_action('wp_head', 'feed_links_extra', 3);
		remove_action('wp_head', 'adjacent_posts_rel_link');
		remove_action('wp_head', 'rest_output_link_wp_head');
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('wp_head', 'wp_resource_hints', 2);
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_action('wp_head', 'rel_canonical');
		remove_action('wp_head', 'wp_shortlink_wp_head', 10);
		remove_action('template_redirect', 'wp_shortlink_header', 11);


		/*
		* Remove Upload Resizes
		*/
		add_filter('intermediate_image_sizes_advanced', function($size, $metadata){
			return [];
		}, 10, 2);


		/*
		* Allow SVG to be uploaded
		*/
		add_filter('upload_mimes', function($mimes){
			$mimes['svg'] = 'image/svg+xml';
			return $mimes;
		});

		add_filter('wp_check_filetype_and_ext', function( $data, $file, $filename, $mimes) {
			global $wp_version;

			if($wp_version == '4.7' || ((float)$wp_version < 4.7 )) return $data;

			$filetype = wp_check_filetype($filename, $mimes);

			return [
				'ext' => $filetype['ext'],
				'type' => $filetype['type'],
				'proper_filename' => $data['proper_filename']
			];
			
		}, 10, 4);


		/*
		* Save ACF in JSON
		*/
		$this->acf_path = get_stylesheet_directory() . '/datas/acf';

		if(is_dir($this->acf_path)){
			add_filter('acf/settings/save_json', function($path){
				return $this->acf_path;
			});

			add_filter('acf/settings/load_json', function($paths){
				// Remove original path
				unset( $paths[0] );

				// Append our new path
				$paths[] = $this->acf_path;

				return $paths;
			});
		}


		/*
		* Maintenance Mode
		*/
		add_action('template_redirect', function(){
			$user = wp_get_current_user();
			$roleArray = $user->roles;
			$userRole = isset($roleArray[0]) ? $roleArray[0] : '';
			if(!is_front_page() && self::field('maintenance') === 'enable' && !in_array($userRole, ['administrator'])){
				
				wp_redirect(home_url());

				exit;
			}
		});

		/*
		* WP HEAD
		*/
		add_action('wp_head', function(){

			if(self::field('seo_management') === 'disable') return;

			$html = '';

			if(
				!self::field('index_se')

				||

				(
					is_author()

					&&

					!self::field('index_se', 'user_' . get_queried_object()->ID)
				)

				||

				(
					(is_tax() || is_tag() || is_category())

					&&

					!self::field('index_se', get_queried_object()->taxonomy . '_' . get_queried_object()->term_id)
				)
			)
				$html .= '<meta name="robots" content="noindex, nofollow">';

			$html .= '<meta charset="'. get_bloginfo('charset') .'">';
			$html .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
			$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">';


			$title = get_the_title() . ' - ' . get_bloginfo('name');
			$og_title = null;
			$description = null;
			$og_description = null;
			$og_image = null;

			/*
			* Manage SEO for Authors and Terms
			*/
			if(is_author()){
				$author = get_queried_object();

				$title = $author->first_name . ' ' . $author->last_name . ' - ' . (self::field('global_seo_title_se') ? self::field('global_seo_title_se') : get_bloginfo('name'));

				if(self::field('title_se', 'user_' . $author->ID))
					$title = self::field('title_se', 'user_' . $author->ID);

				if(self::field('description_se', 'user_' . $author->ID))
					$description = self::field('description_se', 'user_' . $author->ID);

				if(self::field('title_sn', 'user_' . $author->ID))
					$og_title = self::field('title_sn', 'user_' . $author->ID);

				if(self::field('description_sn', 'user_' . $author->ID))
					$og_description = self::field('description_sn', 'user_' . $author->ID);

				if(self::field('image_sn', 'user_' . $author->ID))
					$og_image = self::field('image_sn', 'user_' . $author->ID);

			}
			elseif(is_tax() || is_tag() || is_category()){
				$term = get_queried_object();
				
				$title = $term->name . ' - ' . (self::field('global_seo_title_se') ? self::field('global_seo_title_se') : get_bloginfo('name'));

				if(self::field('title_se', $term->taxonomy . '_' . $term->term_id))
					$title = self::field('title_se', $term->taxonomy . '_' . $term->term_id);

				if(self::field('description_se', $term->taxonomy . '_' . $term->term_id))
					$description = self::field('description_se', $term->taxonomy . '_' . $term->term_id);

				if(self::field('title_sn', $term->taxonomy . '_' . $term->term_id))
					$og_title = self::field('title_sn', $term->taxonomy . '_' . $term->term_id);

				if(self::field('description_sn', $term->taxonomy . '_' . $term->term_id))
					$og_description = self::field('description_sn', $term->taxonomy . '_' . $term->term_id);

				if(self::field('image_sn', $term->taxonomy . '_' . $term->term_id))
					$og_image = self::field('image_sn', $term->taxonomy . '_' . $term->term_id);

			}

			$normal = !is_author() && !is_tax() && !is_tag() && !is_category() ? true : false;
			/*
			* Manage SEO For everything else
			*
			* Title
			*/
			if($normal && !empty(self::field('title_se')))
				$title = self::field('title_se');

			elseif($normal && !empty(self::field('global_seo_title_se')))
				$title = get_the_title() . ' - ' . self::field('global_seo_title_se');


			/*
			* Description
			*/
			if($normal && !empty(self::field('description_se')))
				$description = self::field('description_se');

			elseif($normal && !empty(self::field('global_seo_description_se')))
				$description = self::field('global_seo_description_se');


			/*
			* og:title
			*/
			if($normal && !empty(self::field('title_sn')))
				$og_title = self::field('title_sn');

			elseif($normal && !empty(self::field('global_seo_title_sn')))
				$og_title = self::field('global_seo_title_sn');


			/*
			* og:description
			*/
			if($normal && !empty(self::field('description_sn')))
				$og_description = self::field('description_sn');

			elseif($normal && !empty(self::field('global_seo_description_sn')))
				$og_description = self::field('global_seo_description_sn');


			/*
			* og:image
			*/
			if($normal && !empty(self::field('image_sn')))
				$og_image = self::field('image_sn');



			$html .= '<title>'. wp_strip_all_tags($title) .'</title>';

			$html .= '<meta property="og:site_name" content="'. get_bloginfo('name') .'">';

			if($description)
				$html .= '<meta name="description" content="'. $description .'">';

			if($og_title)
				$html .= '<meta name="og:title" content="'. $og_title .'">';

			if($og_description)
				$html .= '<meta name="og:description" content="'. $og_description .'">';

			if($og_image)
				$html .= '<meta property="og:image" content="'. $og_image .'">';

			$html .= '<meta property="og:locale" content="'. get_locale() .'">';

			$og_type = '<meta property="og:type" content="website" />';

			if(is_singular('post')){
				global $post;

				$author = $post->post_author;
				$author_posts_url = get_author_posts_url($author);
				$publish_date = get_the_date('Y-m-d');
				$tags = get_the_tags();
				$recap_tags = [];
				if($tags){
					foreach ($tags as $tag) {
						$recap_tags[] = $tag->name;
					}
				}
				$tags = implode(',', $recap_tags);

				$og_type = '<meta property="og:type" content="article" />';
				$og_type .= '<meta property="article:author" content="'. $author_posts_url .'" />';
				$og_type .= '<meta property="article:published_time" content="'. $publish_date .'" />';
				
				if($recap_tags)
					$og_type .= '<meta property="article:tags" content="'. $tags .'" />';

			}	elseif(is_author()){

				$author = get_queried_object();

				$og_type = '<meta property="og:type" content="profile" />';

				if($author->first_name)
					$og_type .= '<meta property="profile:first_name" content="'. $author->first_name .'" />';

				if($author->last_name)
					$og_type .= '<meta property="profile:last_name" content="'. $author->last_name .'" />';


				$og_type .= '<meta property="profile:username" content="'. $author->user_login .'" />';

			}

			$html .= $og_type;

			if(self::field('favicons_seo_internet_explorer'))
				$html .= '<!--[if IE]><link rel="shortcut icon" href="'. self::field('favicons_seo_internet_explorer') .'"><![endif]-->';

			if(self::field('favicons_seo_apple_touch'))
				$html .= '<link rel="apple-touch-icon" sizes="180x180" href="'. self::field('favicons_seo_apple_touch') .'">';

			if(self::field('favicons_seo_all_browsers_and_android'))	
				$html .= '<link rel="icon" sizes="192x192" href="'. self::field('favicons_seo_all_browsers_and_android') .'">';

			if(self::field('favicons_seo_msapplication_tileimage'))
				$html .= '<meta name="msapplication-TileImage" content="'. self::field('favicons_seo_msapplication_tileimage') .'">';

			echo $html;

		}, 1);

		/*
		* On Admin Init
		*/
		add_action('admin_init', function(){

			global $pagenow;


			/*
			* Clean Dashboard
			*/
			if(self::field('clean_dashboard') === 'enable'){
				remove_action('welcome_panel', 'wp_welcome_panel');
				remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
				remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
				remove_meta_box('dashboard_primary', 'dashboard', 'side');
				remove_meta_box('dashboard_secondary', 'dashboard', 'normal');
				remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
				remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
				remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
				remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
				remove_meta_box('dashboard_activity', 'dashboard', 'normal');
				remove_meta_box('woocommerce_dashboard_recent_reviews', 'dashboard', 'normal');
				remove_meta_box('dlm_popular_downloads', 'dashboard', 'normal');
				remove_meta_box('dashboard_site_health', 'dashboard', 'normal');

			}

			if(
				self::field('change_display') === 'enable'

				&&

				!empty(self::field('restrict_scg_tab'))

				&&

				!in_array(wp_get_current_user()->ID, self::field('restrict_scg_tab'))

				&&

				(
					(isset($_GET['page']) && $_GET['page'] === 'scg-settings')

					||

					(isset($_GET['post_type']) && in_array($_GET['post_type'], ['acf-field-group', 'is_scg_cpt']))

					||

					(isset($_GET['post']) && in_array(get_post_type($_GET['post']), ['acf-field-group', 'is_scg_cpt']))

					||

					in_array($pagenow, [
						'import.php',
						'export.php',
						'themes.php',
						'plugins.php',
						'theme-editor.php',
						'plugin-editor.php',
					])
				)
			) {

				wp_redirect(admin_url());

				exit;
			}


			if(self::field('gutenberg') !== 'enable')
				add_filter('use_block_editor_for_post_type', '__return_false', 10);


			return;

		});


		/*
		* Admin Bar Menu
		*/
		add_action('admin_bar_menu', function(){

			if(self::field('change_display') === 'enable'){

				global $wp_admin_bar;

				$admin_url = admin_url();

				/*
				* Remove not wanted elements
				*/
				$wp_admin_bar->remove_node('wp-logo');
				$wp_admin_bar->remove_node('site-name');
				$wp_admin_bar->remove_node('comments');
				$wp_admin_bar->remove_node('new-content');


				if(!current_user_can('update_core') || !current_user_can('update_plugins') || !current_user_can('update_themes'))
					$wp_admin_bar->remove_node( 'updates' );


				/*
				* Add Home Site URL
				*/
				$args = array(
					'id' => 'goto-website',
					'title' => get_bloginfo('name'),
					'href' => home_url(),
					'target' => '_blank',
					'meta' => array(
						'class' => 'goto-website',
						'title' => __('Visit Website')
					)
				);
				$wp_admin_bar->add_node($args);


				/*
				* Add Menus Management
				*/
				$args = array(
					'id' => 'gest-menus',
					'title' => __('Menus'),
					'href' => $admin_url . 'nav-menus.php',
					'meta' => array(
						'class' => 'gest-menus',
						'title' => __('Menus Management')
					)
				);
				if(current_user_can('edit_theme_options') && !empty(self::field('register_nav_menus')))
					$wp_admin_bar->add_node($args);


				/*
				* Add Files Management
				*/
				$args = array(
					'id' => 'gest-files',
					'title' => __('Images & Files'),
					'href' => $admin_url . 'upload.php',
					'meta' => array(
						'class' => 'gest-files',
						'title' => __('Images & Files Management')
					)
				);
				if(current_user_can('upload_files'))
					$wp_admin_bar->add_node($args);


				/*
				* Add Users Management
				*/
				$args = array(
					'id' => 'gest-users-list',
					'title' => __('Users'),
					'href' => $admin_url . 'users.php',
					'meta' => array(
						'class' => 'gest-users-list',
						'title' => __('Manage User List')
					)
				);
				if(current_user_can('list_users'))
					$wp_admin_bar->add_node($args);


				/*
				* Add Profile Management
				*/
				$args = array(
					'id' => 'gest-users-profile',
					'title' => __('Profile'),
					'href' => $admin_url . 'profile.php',
					'parent' => 'gest-users-list',
					'meta' => array(
						'class' => 'gest-users-profile',
						'title' => __('Your Profile')
					)
				);
				$wp_admin_bar->add_node($args);


				if(

					(
						current_user_can('edit_theme_options')

						&&

						empty(self::field('restrict_scg_tab'))
					)

					||

					(
						current_user_can('edit_theme_options')

						&&

						!empty(self::field('restrict_scg_tab'))

						&&

						in_array(wp_get_current_user()->ID, self::field('restrict_scg_tab'))
					)
				) {


					/*
					* Move SCG Menu
					*/
					$args = array(
						'id' => 'is-scg',
						'title' => 'SCG',
						'meta' => array(
							'class' => 'is-scg'
						)
					);
					$wp_admin_bar->add_node($args);

					/*
					* Add General Management
					*/
					$args = array(
						'id' => 'is-scg-general',
						'title' => __('Configurations'),
						'href' => $admin_url . 'admin.php?page=scg-settings',
						'parent' => 'is-scg',
						'meta' => array(
							'class' => 'is-scg-general'
						)
					);
					$wp_admin_bar->add_node($args);


					/*
					* Add Custom Post Type Management
					*/
					$args = array(
						'id' => 'is-scg-cpt',
						'title' => __('Custom post types', 'is-scg-core'),
						'href' => $admin_url . 'edit.php?post_type=is_scg_cpt',
						'parent' => 'is-scg',
						'meta' => array(
							'class' => 'is-scg-cpt'
						)
					);

					if(self::field('cpt_management') !== 'disable')
						$wp_admin_bar->add_node($args);



					/*
					* Add Themes Management
					*/
					$args = array(
						'id' => 'is-scg-themes',
						'title' => __('Themes'),
						'href' => $admin_url . 'themes.php',
						'parent' => 'is-scg',
						'meta' => array(
							'class' => 'is-scg-themes'
						)
					);
					if(current_user_can('switch_themes'))
						$wp_admin_bar->add_node($args);


					/*
					* Add Theme Editor Management
					*/
					$args = array(
						'id' => 'is-scg-themes-editor',
						'title' => __('Editor'),
						'href' => $admin_url . 'theme-editor.php',
						'parent' => 'is-scg-themes',
						'meta' => array(
							'class' => 'is-scg-themes-editor'
						)
					);
					if(current_user_can('edit_themes'))
						$wp_admin_bar->add_node($args);


					/*
					* Add Plugins Management
					*/
					$args = array(
						'id' => 'is-scg-plugins',
						'title' => __('Plugins'),
						'href' => $admin_url . 'plugins.php',
						'parent' => 'is-scg',
						'meta' => array(
							'class' => 'is-scg-plugins'
						)
					);
					if(current_user_can('activate_plugins'))
						$wp_admin_bar->add_node($args);


					/*
					* Add Plugin Editor Management
					*/
					$args = array(
						'id' => 'is-scg-plugin-editor',
						'title' => __('Éditeur', 'is-scg-core'),
						'href' => $admin_url . 'plugin-editor.php',
						'parent' => 'is-scg-plugins',
						'meta' => array(
							'class' => 'is-scg-plugins-editor'
						)
					);
					if(current_user_can('edit_plugins'))
						$wp_admin_bar->add_node($args);


					/*
					* Add ACF PRO Management
					*/
					$args = array(
						'id' => 'is-scg-acf',
						'title' => __('ACF'),
						'href' => $admin_url . 'edit.php?post_type=acf-field-group',
						'parent' => 'is-scg',
						'meta' => array(
							'class' => 'is-scg-acf'
						)
					);
					$wp_admin_bar->add_node($args);


					/*
					* Add Import Management
					*/
					$args = array(
						'id' => 'is-scg-import',
						'title' => __('Import'),
						'href' => $admin_url . 'import.php',
						'parent' => 'is-scg',
						'meta' => array(
							'class' => 'is-scg-import'
						)
					);
					if(current_user_can('import'))
						$wp_admin_bar->add_node($args);

					/*
					* Add Export Management
					*/
					$args = array(
						'id' => 'is-scg-export',
						'title' => __('Export'),
						'href' => $admin_url . 'export.php',
						'parent' => 'is-scg',
						'meta' => array(
							'class' => 'is-scg-export'
						)
					);
					if(current_user_can('export'))
						$wp_admin_bar->add_node($args);

				}

			}

			return;

		}, 99);


		/*
		* Clean Left Menus
		*/
		add_action('admin_menu', function(){

			if(self::field('change_display') === 'enable'){
				/*
				* Clean left menu
				*/
				remove_menu_page('tools.php');
				remove_menu_page('upload.php');
				remove_menu_page('themes.php');
				remove_menu_page('plugins.php');
				remove_menu_page('edit-comments.php');
				remove_menu_page('users.php');
				remove_menu_page('edit.php?post_type=acf-field-group');

				remove_submenu_page('options-general.php', 'options-privacy.php');
				remove_submenu_page('options-general.php', 'options-media.php');
				remove_submenu_page('options-general.php', 'options-writing.php');
				remove_submenu_page('options-general.php', 'options-discussion.php');

			}

			return;

		});

		/*
		* Admin Head
		*/
		add_action('admin_head', function(){
			
			if(self::field('change_display') === 'enable' || !empty(self::field('restrict_scg_tab')))
				echo '<style type="text/css">#toplevel_page_scg-settings{display: none !important;}</style>';

			
			return;

		});


		/*
		* Init
		*/
		add_action('init', function(){

			if(self::field('cpt_management') !== 'disable'){
				$change_display = self::field('change_display');
				/*
				* Add CPT Manager
				*/
				$labels = array(
					'name' => __('Custom post types'),
			        'singular_name' => __('Custom post type')
				);

				$args  = array(
					'labels' => $labels,
					'description' => '',
			        'public' => true,
			        'publicly_queryable' => true,
			        'show_ui' => true,
			        'show_in_menu' => $change_display !== 'enable' ? true : false,
			        'show_in_nav_menus' => $change_display !== 'enable' ? true : false,
			        'query_var' => false,
			        'capability_type' => 'post',
			        'has_archive' => false,
			        'hierarchical' => false,
			        'menu_position' => null,
			        'supports' => array('title'),
				);

				register_post_type("is_scg_cpt", $args);

				acf_add_local_field_group(array(
					'key' => 'group_618202e0e6d21',
					'title' => 'SCG CPT',
					'fields' => array(
						array(
							'key' => 'field_6182031923c5d',
							'label' => 'Labels',
							'name' => '',
							'type' => 'tab',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'placement' => 'top',
							'endpoint' => 0,
						),
						array(
							'key' => 'field_6182033123c5e',
							'label' => 'Labels',
							'name' => 'labels',
							'type' => 'group',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'layout' => 'row',
							'sub_fields' => array(
								array(
									'key' => 'field_618206c123c62',
									'label' => 'Paramètres',
									'name' => '',
									'type' => 'message',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Les paramètres avec une étoile rouge sont obligatoires. Les autres utilisent un texte par défaut pouvant être changé.
				Pour avoir la description de chacun des paramètres: https://developer.wordpress.org/reference/functions/get_post_type_labels/#description',
									'new_lines' => 'wpautop',
									'esc_html' => 0,
								),
								array(
									'key' => 'field_6182039a23c5f',
									'label' => 'name',
									'name' => 'name',
									'type' => 'text',
									'instructions' => 'Nom générale du type de publication. généralement au pluriel.',
									'required' => 1,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618204b823c60',
									'label' => 'singular_name',
									'name' => 'singular_name',
									'type' => 'text',
									'instructions' => 'Nom général, mais au singulier. Utilisé par plusieurs plugins dont ACF. <br />
				Si aucun nom au singulier est donné, le nom général sera prit.',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_6182055f23c61',
									'label' => 'add_new',
									'name' => 'add_new',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_6182080e23c63',
									'label' => 'add_new_item',
									'name' => 'add_new_item',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618209e423c64',
									'label' => 'edit_item',
									'name' => 'edit_item',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618209f223c65',
									'label' => 'new_item',
									'name' => 'new_item',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618209fb23c66',
									'label' => 'view_item',
									'name' => 'view_item',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a2523c67',
									'label' => 'view_items',
									'name' => 'view_items',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a3423c68',
									'label' => 'search_items',
									'name' => 'search_items',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a3e23c69',
									'label' => 'not_found',
									'name' => 'not_found',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a4a23c6a',
									'label' => 'not_found_in_trash',
									'name' => 'not_found_in_trash',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a5923c6b',
									'label' => 'parent_item_colon',
									'name' => 'parent_item_colon',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a6823c6c',
									'label' => 'all_items',
									'name' => 'all_items',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a7223c6d',
									'label' => 'archives',
									'name' => 'archives',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a7a23c6e',
									'label' => 'attributes',
									'name' => 'attributes',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a8623c6f',
									'label' => 'insert_into_item',
									'name' => 'insert_into_item',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a9823c70',
									'label' => 'uploaded_to_this_item',
									'name' => 'uploaded_to_this_item',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820a9f23c71',
									'label' => 'featured_image',
									'name' => 'featured_image',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820af923c72',
									'label' => 'set_featured_image',
									'name' => 'set_featured_image',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b0b23c73',
									'label' => 'remove_featured_image',
									'name' => 'remove_featured_image',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b0f23c74',
									'label' => 'use_featured_image',
									'name' => 'use_featured_image',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b1623c75',
									'label' => 'menu_name',
									'name' => 'menu_name',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b3623c76',
									'label' => 'filter_items_list',
									'name' => 'filter_items_list',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b3923c77',
									'label' => 'filter_by_date',
									'name' => 'filter_by_date',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b4623c78',
									'label' => 'items_list_navigation',
									'name' => 'items_list_navigation',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b5c23c79',
									'label' => 'items_list',
									'name' => 'items_list',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b6823c7a',
									'label' => 'item_published',
									'name' => 'item_published',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b7023c7b',
									'label' => 'item_published_privately',
									'name' => 'item_published_privately',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b7323c7c',
									'label' => 'item_reverted_to_draft',
									'name' => 'item_reverted_to_draft',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b7923c7d',
									'label' => 'item_scheduled',
									'name' => 'item_scheduled',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b8023c7e',
									'label' => 'item_updated',
									'name' => 'item_updated',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b8d23c7f',
									'label' => 'item_link',
									'name' => 'item_link',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61820b9523c80',
									'label' => 'item_link_description',
									'name' => 'item_link_description',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
							),
						),
						array(
							'key' => 'field_61820e685e3fa',
							'label' => 'Arguments',
							'name' => '',
							'type' => 'tab',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'placement' => 'top',
							'endpoint' => 0,
						),
						array(
							'key' => 'field_61820e965e3fb',
							'label' => 'Arguments',
							'name' => 'args',
							'type' => 'group',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'layout' => 'row',
							'sub_fields' => array(
								array(
									'key' => 'field_61820ea65e3fc',
									'label' => 'Paramètres',
									'name' => '',
									'type' => 'message',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Les paramètres avec une étoile rouge sont obligatoires.
				Pour avoir la description de chacun des paramètres: https://developer.wordpress.org/reference/functions/register_post_type/#parameters',
									'new_lines' => 'wpautop',
									'esc_html' => 0,
								),
								array(
									'key' => 'field_61820fe65e3fd',
									'label' => 'post_type',
									'name' => 'post_type',
									'type' => 'text',
									'instructions' => '',
									'required' => 1,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618210cb5e3fe',
									'label' => 'description',
									'name' => 'description',
									'type' => 'textarea',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'maxlength' => '',
									'rows' => 3,
									'new_lines' => '',
								),
								array(
									'key' => 'field_6182111e5e3ff',
									'label' => 'public',
									'name' => 'public',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_618211345e400',
									'label' => 'hierarchical',
									'name' => 'hierarchical',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_6182120d5e401',
									'label' => 'exclude_from_search',
									'name' => 'exclude_from_search',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_618212165e402',
									'label' => 'publicly_queryable',
									'name' => 'publicly_queryable',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_6182124e5e403',
									'label' => 'show_ui',
									'name' => 'show_ui',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_618212655e405',
									'label' => 'show_in_menu',
									'name' => 'show_in_menu',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_6182133a5e406',
									'label' => 'show_in_menu (custom)',
									'name' => 'show_in_menu_custom',
									'type' => 'text',
									'instructions' => 'Si ce paramètre est rempli, show_in_menu juste au dessus ne sera pas utilisé. Pour comprendre le paramètre custom de show_in_menu, veuillez regarder la description des paramètres proposée plus haut.',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618213da5e407',
									'label' => 'show_in_nav_menus',
									'name' => 'show_in_nav_menus',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_6182144f5e409',
									'label' => 'show_in_admin_bar',
									'name' => 'show_in_admin_bar',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_6182145d5e40a',
									'label' => 'show_in_rest',
									'name' => 'show_in_rest',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_618214675e40b',
									'label' => 'rest_base',
									'name' => 'rest_base',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618214795e40c',
									'label' => 'rest_controller_class',
									'name' => 'rest_controller_class',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => 'WP_REST_Posts_Controller',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618214a65e40d',
									'label' => 'menu_position',
									'name' => 'menu_position',
									'type' => 'number',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => 0,
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'min' => 0,
									'max' => '',
									'step' => '',
								),
								array(
									'key' => 'field_618214cb5e40e',
									'label' => 'menu_icon',
									'name' => 'menu_icon',
									'type' => 'text',
									'instructions' => 'https://developer.wordpress.org/resource/dashicons/',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => 'dashicons-chart-pie',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618215425e40f',
									'label' => 'capability_type',
									'name' => 'capability_type',
									'type' => 'button_group',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'choices' => array(
										'post' => 'post',
										'page' => 'page',
									),
									'allow_null' => 0,
									'default_value' => '',
									'layout' => 'horizontal',
									'return_format' => 'value',
								),
								array(
									'key' => 'field_618217345e410',
									'label' => 'capabilities',
									'name' => 'capabilities',
									'type' => 'text',
									'instructions' => 'Séparez chaque résultat par une virgule.',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => 'manage_options',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_6182175c5e411',
									'label' => 'map_meta_cap',
									'name' => 'map_meta_cap',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 1,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_618217d85e412',
									'label' => 'supports',
									'name' => 'supports',
									'type' => 'checkbox',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'choices' => array(
										'title' => 'title',
										'editor' => 'editor',
										'comments' => 'comments',
										'revisions' => 'revisions',
										'trackbacks' => 'trackbacks',
										'author' => 'author',
										'excerpt' => 'excerpt',
										'page-attributes' => 'page-attributes',
										'thumbnail' => 'thumbnail',
										'custom-fields' => 'custom-fields',
										'post-formats' => 'post-formats',
									),
									'allow_custom' => 0,
									'default_value' => array(
										0 => 'title',
									),
									'layout' => 'vertical',
									'toggle' => 0,
									'return_format' => 'value',
									'save_custom' => 0,
								),
								array(
									'key' => 'field_618218715e413',
									'label' => 'register_meta_box_cb',
									'name' => 'register_meta_box_cb',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_618218d65e414',
									'label' => 'has_archive',
									'name' => 'has_archive',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_618218f75e415',
									'label' => 'has_archive (custom)',
									'name' => 'has_archive_custom',
									'type' => 'text',
									'instructions' => 'Si ce paramètre est rempli, has_archive juste au dessus ne sera pas utilisé. Pour comprendre le paramètre custom de has_archive, veuillez regarder la description des paramètres proposée plus haut.',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61821a5e5e416',
									'label' => 'rewrite',
									'name' => 'rewrite',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 1,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_61821a6b5e417',
									'label' => 'rewrite (custom)',
									'name' => 'rewrite_custom',
									'type' => 'group',
									'instructions' => 'Si ce paramètre est rempli, rewrite juste au dessus ne sera pas utilisé. Pour comprendre le paramètre custom de rewrite, veuillez regarder la description des paramètres proposée plus haut.',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'layout' => 'table',
									'sub_fields' => array(
										array(
											'key' => 'field_61821abc5e418',
											'label' => 'slug',
											'name' => 'slug',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61821ad55e419',
											'label' => 'with_front',
											'name' => 'with_front',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => 'Oui',
											'default_value' => 1,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61821ae45e41a',
											'label' => 'feeds',
											'name' => 'feeds',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => 'Oui',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61821aea5e41b',
											'label' => 'pages',
											'name' => 'pages',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => 'Oui',
											'default_value' => 1,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
									),
								),
								array(
									'key' => 'field_61821b565e41c',
									'label' => 'query_var',
									'name' => 'query_var',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_61821b865e41d',
									'label' => 'query_var (custom)',
									'name' => 'query_var_custom',
									'type' => 'text',
									'instructions' => 'Si ce paramètre est rempli, query_var juste au dessus ne sera pas utilisé. Pour comprendre le paramètre custom de query_var, veuillez regarder la description des paramètres proposée plus haut.',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61821bbd5e41e',
									'label' => 'can_export',
									'name' => 'can_export',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 1,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_61821c295e41f',
									'label' => 'delete_with_user',
									'name' => 'delete_with_user',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_61821c405e420',
									'label' => 'template',
									'name' => 'template',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61821c8b5e421',
									'label' => 'template_lock',
									'name' => 'template_lock',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
								array(
									'key' => 'field_61821c9b5e422',
									'label' => '_builtin',
									'name' => '_builtin',
									'type' => 'true_false',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'message' => 'Oui',
									'default_value' => 0,
									'ui' => 0,
									'ui_on_text' => '',
									'ui_off_text' => '',
								),
								array(
									'key' => 'field_61821caf5e424',
									'label' => '_edit_link',
									'name' => '_edit_link',
									'type' => 'text',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'maxlength' => '',
								),
							),
						),
						array(
							'key' => 'field_618238e0d943f',
							'label' => 'Taxonomies',
							'name' => '',
							'type' => 'tab',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'placement' => 'top',
							'endpoint' => 0,
						),
						array(
							'key' => 'field_61823b2e166ad',
							'label' => 'Taxonomies',
							'name' => 'taxonomies',
							'type' => 'repeater',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'collapsed' => 'field_6182392fd9442',
							'min' => 0,
							'max' => 0,
							'layout' => 'block',
							'button_label' => 'Ajouter une taxonomie',
							'sub_fields' => array(
								array(
									'key' => 'field_6182392fd9442',
									'label' => 'Labels',
									'name' => '',
									'type' => 'tab',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'placement' => 'top',
									'endpoint' => 0,
								),
								array(
									'key' => 'field_618238f7d9440',
									'label' => 'Labels',
									'name' => 'labels',
									'type' => 'group',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'layout' => 'row',
									'sub_fields' => array(
										array(
											'key' => 'field_61823900d9441',
											'label' => 'Paramètres',
											'name' => '',
											'type' => 'message',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => 'Les paramètres avec une étoile rouge sont obligatoires.
				Pour avoir la description de chacun des paramètres: https://developer.wordpress.org/reference/functions/get_taxonomy_labels/#return',
											'new_lines' => 'wpautop',
											'esc_html' => 0,
										),
										array(
											'key' => 'field_61823992d9443',
											'label' => 'name',
											'name' => 'name',
											'type' => 'text',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_6182399ed9444',
											'label' => 'singular_name',
											'name' => 'singular_name',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239a3d9445',
											'label' => 'search_items',
											'name' => 'search_items',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239a7d9446',
											'label' => 'popular_items',
											'name' => 'popular_items',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239add9447',
											'label' => 'all_items',
											'name' => 'all_items',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239b2d9448',
											'label' => 'parent_item',
											'name' => 'parent_item',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239b7d9449',
											'label' => 'parent_item_colon',
											'name' => 'parent_item_colon',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239cbd944a',
											'label' => 'edit_item',
											'name' => 'edit_item',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239cfd944b',
											'label' => 'view_item',
											'name' => 'view_item',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239d5d944c',
											'label' => 'update_item',
											'name' => 'update_item',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239d8d944d',
											'label' => 'add_new_item',
											'name' => 'add_new_item',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618239ff926c7',
											'label' => 'new_item_name',
											'name' => 'new_item_name',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a12926c8',
											'label' => 'separate_items_with_commas',
											'name' => 'separate_items_with_commas',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a1c926c9',
											'label' => 'add_or_remove_items',
											'name' => 'add_or_remove_items',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a20926ca',
											'label' => 'choose_from_most_used',
											'name' => 'choose_from_most_used',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a2e926cb',
											'label' => 'not_found',
											'name' => 'not_found',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a34926cc',
											'label' => 'no_terms',
											'name' => 'no_terms',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a37926cd',
											'label' => 'filter_by_item',
											'name' => 'filter_by_item',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a3e926ce',
											'label' => 'items_list_navigation',
											'name' => 'items_list_navigation',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a43926cf',
											'label' => 'items_list',
											'name' => 'items_list',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a47926d0',
											'label' => 'most_used',
											'name' => 'most_used',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a4b926d1',
											'label' => 'back_to_items',
											'name' => 'back_to_items',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a50926d2',
											'label' => 'item_link',
											'name' => 'item_link',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823a58926d3',
											'label' => 'item_link_description',
											'name' => 'item_link_description',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
									),
								),
								array(
									'key' => 'field_61823a8c33ccc',
									'label' => 'Arguments',
									'name' => '',
									'type' => 'tab',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'placement' => 'top',
									'endpoint' => 0,
								),
								array(
									'key' => 'field_61823c0d166ae',
									'label' => 'Arguments',
									'name' => 'args',
									'type' => 'group',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'layout' => 'row',
									'sub_fields' => array(
										array(
											'key' => 'field_61823a8f33ccd',
											'label' => 'Paramètres',
											'name' => '',
											'type' => 'message',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => 'Les paramètres avec une étoile rouge sont obligatoires.
				Pour avoir la description de chacun des paramètres: https://developer.wordpress.org/reference/functions/register_taxonomy/#parameters',
											'new_lines' => 'wpautop',
											'esc_html' => 0,
										),
										array(
											'key' => 'field_61823d64be236',
											'label' => 'Callback',
											'name' => 'callback',
											'type' => 'text',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => 32,
										),
										array(
											'key' => 'field_61823d8dbe237',
											'label' => 'description',
											'name' => 'description',
											'type' => 'textarea',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'maxlength' => '',
											'rows' => 3,
											'new_lines' => '',
										),
										array(
											'key' => 'field_61823db8be238',
											'label' => 'public',
											'name' => 'public',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823dfebe239',
											'label' => 'publicly_queryable',
											'name' => 'publicly_queryable',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823e07be23a',
											'label' => 'hierarchical',
											'name' => 'hierarchical',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823e0ebe23b',
											'label' => 'show_ui',
											'name' => 'show_ui',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 1,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823e2abe23c',
											'label' => 'show_in_menu',
											'name' => 'show_in_menu',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 1,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823e56be23d',
											'label' => 'show_in_nav_menus',
											'name' => 'show_in_nav_menus',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 1,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823e65be23e',
											'label' => 'show_in_rest',
											'name' => 'show_in_rest',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823e79be23f',
											'label' => 'rest_base',
											'name' => 'rest_base',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823e7ebe240',
											'label' => 'rest_controller_class',
											'name' => 'rest_controller_class',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => 'WP_REST_Terms_Controller',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823e8ebe241',
											'label' => 'show_tagcloud',
											'name' => 'show_tagcloud',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 1,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823eacbe242',
											'label' => 'show_in_quick_edit',
											'name' => 'show_in_quick_edit',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 1,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823eb3be243',
											'label' => 'show_admin_column',
											'name' => 'show_admin_column',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823f7fbe247',
											'label' => 'capabilities',
											'name' => 'capabilities',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61823fb0be248',
											'label' => 'rewrite',
											'name' => 'rewrite',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 1,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61823fd9be249',
											'label' => 'rewrite (custom)',
											'name' => 'rewrite_custom',
											'type' => 'group',
											'instructions' => 'Si ce paramètre est rempli, rewrite juste au dessus ne sera pas utilisé. Pour comprendre le paramètre custom de rewrite, veuillez regarder la description des paramètres proposée plus haut.',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'layout' => 'table',
											'sub_fields' => array(
												array(
													'key' => 'field_61823fd9be24a',
													'label' => 'slug',
													'name' => 'slug',
													'type' => 'text',
													'instructions' => '',
													'required' => 0,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '',
														'class' => '',
														'id' => '',
													),
													'default_value' => '',
													'placeholder' => '',
													'prepend' => '',
													'append' => '',
													'maxlength' => '',
												),
												array(
													'key' => 'field_61823fd9be24b',
													'label' => 'with_front',
													'name' => 'with_front',
													'type' => 'true_false',
													'instructions' => '',
													'required' => 0,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '',
														'class' => '',
														'id' => '',
													),
													'message' => 'Oui',
													'default_value' => 1,
													'ui' => 0,
													'ui_on_text' => '',
													'ui_off_text' => '',
												),
												array(
													'key' => 'field_61823fd9be24c',
													'label' => 'hierarchical',
													'name' => 'hierarchical',
													'type' => 'true_false',
													'instructions' => '',
													'required' => 0,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '',
														'class' => '',
														'id' => '',
													),
													'message' => 'Oui',
													'default_value' => 0,
													'ui' => 0,
													'ui_on_text' => '',
													'ui_off_text' => '',
												),
											),
										),
										array(
											'key' => 'field_61824054be24e',
											'label' => 'query_var',
											'name' => 'query_var',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => 'Oui',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_61824057be24f',
											'label' => 'query_var (custom)',
											'name' => 'query_var_custom',
											'type' => 'text',
											'instructions' => 'Si ce paramètre est rempli, query_var juste au dessus ne sera pas utilisé. Pour comprendre le paramètre custom de query_var, veuillez regarder la description des paramètres proposée plus haut.',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_6182407bbe250',
											'label' => 'update_count_callback',
											'name' => 'update_count_callback',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_61824099be251',
											'label' => 'default_term',
											'name' => 'default_term',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618240e6be252',
											'label' => 'sort',
											'name' => 'sort',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => 'Oui',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
										array(
											'key' => 'field_618240fdbe254',
											'label' => 'args',
											'name' => 'args',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'maxlength' => '',
										),
										array(
											'key' => 'field_618240f9be253',
											'label' => '_builtin',
											'name' => '_builtin',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => 'Oui',
											'default_value' => 0,
											'ui' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
										),
									),
								),
							),
						),
					),
					'location' => array(
						array(
							array(
								'param' => 'post_type',
								'operator' => '==',
								'value' => 'is_scg_cpt',
							),
						),
					),
					'menu_order' => 0,
					'position' => 'normal',
					'style' => 'seamless',
					'label_placement' => 'top',
					'instruction_placement' => 'label',
					'hide_on_screen' => '',
					'active' => true,
					'description' => '',
				));
			}


			/*
				* Register CPT
				*/
				$cpt = new WP_Query([
					'post_type' => 'is_scg_cpt',
					'posts_per_page' => -1
				]);

				if($cpt->have_posts()){
					while($cpt->have_posts()) : $cpt->the_post();

						if(get_field('labels')){
							/*
							* Get Labels and arguments without empties
							*/
							$labels = array_filter(get_field('labels'));
							$args = get_field('args');
							unset($args['']);


							/*
							* Add Labels in Arguments
							*/
							$args['labels'] = $labels;


							/*
							* Get post_type from Arguments
							*/
							$post_type = $args['post_type'];


							/*
							* Now you have the post_type, remove it from Arguments
							* Because it's not an arguments
							*/
							unset($args['post_type']);


							/*
							* Transform show_in_menu
							*/
							if(!empty($args['show_in_menu_custom']))
								$args['show_in_menu'] = $args['show_in_menu_custom'];
							
							unset($args['show_in_menu_custom']);


							/*
							* Transform has_archive
							*/
							if(!empty($args['has_archive_custom']))
								$args['has_archive'] = $args['has_archive_custom'];
							
							unset($args['has_archive_custom']);


							/*
							* Transform rewrite
							*/
							if(!empty($args['rewrite_custom']['slug']))
								$args['rewrite'] = $args['rewrite_custom'];

							unset($args['rewrite_custom']);


							/*
							* Transform query_var
							*/
							if(!empty($args['query_var_custom']))
								$args['query_var'] = $args['query_var_custom'];
							
							unset($args['query_var_custom']);


							/*
							* Remove cap
							*/
							if(empty($args['capabilities']))
								unset($args['capabilities']);
							else
								$args['capabilities'] = str_replace(', ', ',', explode(',', $args['capabilities']));


							/*
							* Remove register_meta_box_cb
							*/
							if(empty($args['register_meta_box_cb']))
								unset($args['register_meta_box_cb']);


							/*
							* Remove _edit_link
							*/
							if(empty($args['_edit_link']))
								unset($args['_edit_link']);


							/*
							* Remove template
							*/
							if(empty($args['template']))
								unset($args['template']);


							/*
							* Remove rest_base
							*/
							if(empty($args['rest_base']))
								unset($args['rest_base']);


							/*
							* Transform supports
							*/
							if(empty($args['supports']))
								$args['supports'] = false;


							/*
							* Transform template_lock
							*/
							if(empty($args['template_lock']))
								$args['template_lock'] = false;


							/*
							* Now all is set, register the post type
							* and their taxonomies
							*/
							register_post_type($post_type, $args);
							

							// Taxonomy
							if(get_field('taxonomies', get_the_ID())){
								foreach(get_field('taxonomies', get_the_ID()) as $tax){
									/*
									* Get Labels and arguments without empties
									*/
									$labels = array_filter($tax['labels']);
									$args = $tax['args'];
									unset($args['']);


									/*
									* Add Labels in Arguments
									*/
									$args['labels'] = $labels;


									/*
									* Get post_type from Arguments
									*/
									$callback = $args['callback'];


									/*
									* Now you have the post_type, remove it from Arguments
									* Because it's not an arguments
									*/
									unset($args['callback']);



									/*
									* Transform rewrite
									*/
									if(!empty($args['rewrite_custom']['slug']))
										$args['rewrite'] = $args['rewrite_custom'];

									unset($args['rewrite_custom']);


									/*
									* Transform query_var
									*/
									if(!empty($args['query_var_custom']))
										$args['query_var'] = $args['query_var_custom'];
									
									unset($args['query_var_custom']);


									/*
									* Remove cap
									*/
									if(empty($args['capabilities']))
										unset($args['capabilities']);
									else
										$args['capabilities'] = str_replace(', ', ',', explode(',', $args['capabilities']));


									/*
									* Remove rest_base
									*/
									if(empty($args['rest_base']))
										unset($args['rest_base']);


									/*
									* Remove update_count_callback
									*/
									if(empty($args['update_count_callback']))
										unset($args['update_count_callback']);


									/*
									* Remove default_term
									*/
									if(empty($args['default_term']))
										unset($args['default_term']);


									/*
									* Remove args
									*/
									if(empty($args['args']))
										unset($args['args']);


									/*
									* Now all is set, register taxonomy
									*/
									register_taxonomy($callback, array($post_type), $args);
								}
							}
						}

					endwhile; wp_reset_postdata();
				}

			/*
			* Add Theme Management
			*
			* SCG Management
			*/
			acf_add_options_page([
				'page_title'    => __('SCG Management'),
				'menu_title'    => __('SCG'),
				'menu_slug'     => 'scg-settings',
				'capability'    => 'edit_themes',
				'redirect'      => false
			]);

			/*
			* Theme Tab
			*/
			acf_add_local_field_group([
				'key' => 'group_637141e2601c7',
				'title' => __('Theme Management'),
				'fields' => [],
				'location' => [
					[
						[
							'param' => 'options_page',
							'operator' => '==',
							'value' => 'scg-settings',
						],
					],
				],
				'menu_order' => 0,
				'position' => 'normal',
				'style' => 'seamless',
				'label_placement' => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen' => '',
				'active' => true,
				'description' => '',
				'show_in_rest' => 0,
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371502242c21',
				'label' => 'Theme',
				'name' => '',
				'aria-label' => '',
				'type' => 'tab',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'placement' => 'top',
				'endpoint' => 0,
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_637141e24cb06',
				'label' => 'Maintenance',
				'name' => 'maintenance',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'disable',
				'return_format' => 'value',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => '',
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_637168726e495',
				'label' => 'Register Nav Menus',
				'name' => 'register_nav_menus',
				'aria-label' => '',
				'type' => 'repeater',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'layout' => 'block',
				'pagination' => 0,
				'min' => 0,
				'max' => 0,
				'collapsed' => '',
				'button_label' => 'Add Menu Location',
				'rows_per_page' => 20,
				'sub_fields' => [
					[
						'key' => 'field_637168da6e496',
						'label' => 'Name',
						'name' => 'name',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '50',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_637168726e495',
					],
					[
						'key' => 'field_637168e56e497',
						'label' => 'Slug',
						'name' => 'slug',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '50',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_637168726e495',
					],
				],
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371696a891d2',
				'label' => 'Register Options Pages',
				'name' => 'register_options_pages',
				'aria-label' => '',
				'type' => 'repeater',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'layout' => 'block',
				'pagination' => 0,
				'min' => 0,
				'max' => 0,
				'collapsed' => 'field_6371696a891d3',
				'button_label' => 'Add Options Page',
				'rows_per_page' => 20,
				'sub_fields' => [
					[
						'key' => 'field_6371696a891d3',
						'label' => 'Page Title',
						'name' => 'page_title',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_637169a6891d5',
						'label' => 'Menu Title',
						'name' => 'menu_title',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_637169b2891d6',
						'label' => 'Menu Slug',
						'name' => 'menu_slug',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_637169c7891d7',
						'label' => 'Capability',
						'name' => 'capability',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => 'edit_posts',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_637169d5891d8',
						'label' => 'Position',
						'name' => 'position',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_637169e4891d9',
						'label' => 'Parent Slug',
						'name' => 'parent_slug',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_637169f9891da',
						'label' => 'Icon URL',
						'name' => 'icon_url',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_63716a58891db',
						'label' => 'Redirect',
						'name' => 'redirect',
						'aria-label' => '',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'message' => 'Yes',
						'default_value' => 1,
						'ui' => 0,
						'ui_on_text' => '',
						'ui_off_text' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_63716a8d891dc',
						'label' => 'Post ID',
						'name' => 'post_id',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => 'options',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_63716b1d891dd',
						'label' => 'Autoload',
						'name' => 'autoload',
						'aria-label' => '',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'message' => 'Yes',
						'default_value' => 0,
						'ui' => 0,
						'ui_on_text' => '',
						'ui_off_text' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_63716b41891de',
						'label' => 'Update Button',
						'name' => 'update_button',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => 'Update',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
					[
						'key' => 'field_63716b4e891df',
						'label' => 'Update Message',
						'name' => 'update_message',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '33.3333333333',
							'class' => '',
							'id' => '',
						],
						'default_value' => 'Options Updated',
						'maxlength' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'parent_repeater' => 'field_6371696a891d2',
					],
				],
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_637184da2d215',
				'label' => 'HTML Tags',
				'name' => 'html_tags',
				'aria-label' => '',
				'type' => 'group',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'layout' => 'block',
				'sub_fields' => [
					[
						'key' => 'field_637185442d216',
						'label' => 'After &lt;head&gt;',
						'name' => 'after_open_head',
						'aria-label' => '',
						'type' => 'textarea',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '50',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'rows' => 12,
						'placeholder' => '',
						'new_lines' => '',
					],
					[
						'key' => 'field_637185b12d217',
						'label' => 'Before &lt;/head&gt;',
						'name' => 'before_close_head',
						'aria-label' => '',
						'type' => 'textarea',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '50',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'rows' => 12,
						'placeholder' => '',
						'new_lines' => '',
					],
					[
						'key' => 'field_637185f42d219',
						'label' => 'After &lt;body&gt;',
						'name' => 'after_open_body',
						'aria-label' => '',
						'type' => 'textarea',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '50',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'rows' => 12,
						'placeholder' => '',
						'new_lines' => '',
					],
					[
						'key' => 'field_637185f02d218',
						'label' => 'Before &lt;/body&gt;',
						'name' => 'before_close_body',
						'aria-label' => '',
						'type' => 'textarea',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '50',
							'class' => '',
							'id' => '',
						],
						'default_value' => '',
						'maxlength' => '',
						'rows' => 12,
						'placeholder' => '',
						'new_lines' => '',
					],
				],
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371503842c22',
				'label' => 'Cleaner',
				'name' => '',
				'aria-label' => '',
				'type' => 'tab',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'placement' => 'top',
				'endpoint' => 0,
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_637192feggwxh',
				'label' => 'Notice',
				'name' => '',
				'aria-label' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'message' => 'You need to save a first time to get options working.',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_63715d8300da6',
				'label' => 'Restrict SCG Tab',
				'name' => 'restrict_scg_tab',
				'aria-label' => '',
				'type' => 'user',
				'instructions' => 'Work only if you set "Change Admin Panel Display" to "Enable"',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'role' => '',
				'return_format' => '',
				'multiple' => 1,
				'allow_null' => 1,
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371508a42c24',
				'label' => 'Clean Dashboard',
				'name' => 'clean_dashboard',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'disable',
				'return_format' => '',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => '',
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_637152493e9be',
				'label' => 'Change Admin Panel Display',
				'name' => 'change_display',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'disable',
				'return_format' => 'value',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => '',
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371a10d4fd26',
				'label' => 'Gutenberg',
				'name' => 'gutenberg',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'disable',
				'return_format' => 'value',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => '',
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371a16c4fd27',
				'label' => 'Front-end Global Styles',
				'name' => 'global_styles',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'disable',
				'return_format' => 'value',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => '',
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371a1ab4fd28',
				'label' => 'Front-end WP Block Library',
				'name' => 'wp_block_library',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'disable',
				'return_format' => 'value',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => '',
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371a1c54fd29',
				'label' => 'Front-end Classic Theme Styles',
				'name' => 'classic_theme_styles',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'disable',
				'return_format' => 'value',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => '',
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371508a42c24fr4',
				'label' => 'SEO Management',
				'name' => 'seo_management',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => 'Disable this option if you want use a SEO Plugin.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'enable',
				'return_format' => '',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => ''
			]);

			acf_add_local_field([
				'parent' => 'group_637141e2601c7',
				'key' => 'field_6371508a42jh5wdgf',
				'label' => 'Custom Post Types Management',
				'name' => 'cpt_management',
				'aria-label' => '',
				'type' => 'select',
				'instructions' => 'Disable this option if you want use an other CPT Plugin.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '33.3333333333',
					'class' => '',
					'id' => '',
				],
				'choices' => [
					'enable' => 'Enable',
					'disable' => 'Disable',
				],
				'default_value' => 'enable',
				'return_format' => '',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => ''
			]);

			if(self::field('seo_management') !== 'disable') {

				/*
				* SEO Tab
				*/
				$post_types = get_post_types();

				//print_r($post_types);

				$unsets = [
					'post',
					'page',
					'attachment',
					'revision',
					'nav_menu_item',
					'custom_css',
					'customize_changeset',
					'oembed_cache',
					'user_request',
					'wp_block',
					'wp_template',
					'wp_template_part',
					'wp_global_styles',
					'wp_navigation',
					'acf-field',
					'acf-field-group',
					'acf-field',
					'is_scg_cpt'
				];

				foreach ($unsets as $unset) {
					unset($post_types[$unset]);
				}

				acf_add_local_field([
					'parent' => 'group_637141e2601c7',
					'key' => 'field_637192c94f715',
					'label' => 'SEO',
					'name' => '',
					'aria-label' => '',
					'type' => 'tab',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'placement' => 'top',
					'endpoint' => 0,
				]);

				acf_add_local_field([
					'parent' => 'group_637141e2601c7',
					'key' => 'field_6371ef026d947',
					'label' => 'Favicons',
					'name' => 'favicons_seo',
					'aria-label' => '',
					'type' => 'group',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'layout' => 'block',
					'sub_fields' => [
						[
							'key' => 'field_6371ef1c6d948',
							'label' => 'Internet Explorer.ico (32x32)',
							'name' => 'internet_explorer',
							'aria-label' => '',
							'type' => 'image',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '50',
								'class' => '',
								'id' => '',
							],
							'return_format' => 'url',
							'library' => 'all',
							'min_width' => 32,
							'min_height' => 32,
							'min_size' => '',
							'max_width' => 32,
							'max_height' => 32,
							'max_size' => '',
							'mime_types' => '.ico',
							'preview_size' => 'medium',
						],
						[
							'key' => 'field_6371ef9c6d949',
							'label' => 'Apple Touch (180x180)',
							'name' => 'apple_touch',
							'aria-label' => '',
							'type' => 'image',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '50',
								'class' => '',
								'id' => '',
							],
							'return_format' => 'url',
							'library' => 'all',
							'min_width' => 180,
							'min_height' => 180,
							'min_size' => '',
							'max_width' => 180,
							'max_height' => 180,
							'max_size' => '',
							'mime_types' => '.jpg,.jpeg,.png',
							'preview_size' => 'medium',
						],
						[
							'key' => 'field_6371f0646d94a',
							'label' => 'All Browsers and Android (192x192)',
							'name' => 'all_browsers_and_android',
							'aria-label' => '',
							'type' => 'image',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '50',
								'class' => '',
								'id' => '',
							],
							'return_format' => 'url',
							'library' => 'all',
							'min_width' => 192,
							'min_height' => 192,
							'min_size' => '',
							'max_width' => 192,
							'max_height' => 192,
							'max_size' => '',
							'mime_types' => '.jpg,.jpeg,.png',
							'preview_size' => 'medium',
						],
						[
							'key' => 'field_6371f2d86d94b',
							'label' => 'msapplication-TileImage (270x270)',
							'name' => 'msapplication_tileimage',
							'aria-label' => '',
							'type' => 'image',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '50',
								'class' => '',
								'id' => '',
							],
							'return_format' => 'url',
							'library' => 'all',
							'min_width' => 270,
							'min_height' => 270,
							'min_size' => '',
							'max_width' => 270,
							'max_height' => 270,
							'max_size' => '',
							'mime_types' => '.jpg,.jpeg,.png',
							'preview_size' => 'medium',
						],
					],
				]);

				acf_add_local_field([
					'parent' => 'group_637141e2601c7',
					'key' => 'field_637192ff9b17c',
					'label' => 'Notice',
					'name' => '',
					'aria-label' => '',
					'type' => 'message',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '66.6666666667',
						'class' => '',
						'id' => '',
					],
					'message' => 'Use HTML Tag boxes in Theme Tab for add analytic scripts.',
					'new_lines' => 'wpautop',
					'esc_html' => 0,
				]);

				acf_add_local_field([
					'parent' => 'group_637141e2601c7',
					'key' => 'field_637195565c37f',
					'label' => 'Global',
					'name' => 'global_seo',
					'aria-label' => '',
					'type' => 'group',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'layout' => 'block',
					'sub_fields' => [
						[
							'key' => 'field_637195cb5c380',
							'label' => 'Search Engines',
							'name' => '',
							'aria-label' => '',
							'type' => 'tab',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'placement' => 'top',
							'endpoint' => 0,
						],
						[
							'key' => 'field_637195e95c381',
							'label' => 'Title',
							'name' => 'title_se',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'default_value' => '',
							'maxlength' => 65,
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						],
						[
							'key' => 'field_637196a45c382',
							'label' => 'Description',
							'name' => 'description_se',
							'aria-label' => '',
							'type' => 'textarea',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'default_value' => '',
							'maxlength' => 300,
							'rows' => '',
							'placeholder' => '',
							'new_lines' => '',
						],
						[
							'key' => 'field_637197225c383',
							'label' => 'Social Networks',
							'name' => '',
							'aria-label' => '',
							'type' => 'tab',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'placement' => 'top',
							'endpoint' => 0,
						],
						[
							'key' => 'field_637197265c384',
							'label' => 'Title',
							'name' => 'title_sn',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'default_value' => '',
							'maxlength' => 65,
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						],
						[
							'key' => 'field_6371972d5c385',
							'label' => 'Description',
							'name' => 'description_sn',
							'aria-label' => '',
							'type' => 'textarea',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'default_value' => '',
							'maxlength' => 300,
							'rows' => '',
							'placeholder' => '',
							'new_lines' => '',
						],
						[
							'key' => 'field_6371976e5c386',
							'label' => 'Image',
							'name' => 'image_sn',
							'aria-label' => '',
							'type' => 'image',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'return_format' => 'url',
							'library' => 'all',
							'min_width' => 1200,
							'min_height' => 630,
							'min_size' => '',
							'max_width' => 1200,
							'max_height' => 630,
							'max_size' => '',
							'mime_types' => '.jpg,.jpg',
							'preview_size' => 'full',
						],
					],
				]);

				acf_add_local_field([
					'parent' => 'group_637141e2601c7',
					'key' => 'field_6371949d5c37e',
					'label' => 'Post Types',
					'name' => 'post_types_seo',
					'aria-label' => '',
					'type' => 'checkbox',
					'instructions' => 'Add SEO Module on these Post Types. Page, Post, Terms and Authors has the module.',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'choices' => $post_types,
					'default_value' => [
					],
					'return_format' => 'value',
					'allow_custom' => 0,
					'layout' => 'horizontal',
					'toggle' => 0,
					'save_custom' => 0,
				]);


				/*
				* SEO Module Everywhere
				*/
				$post_types = !empty(self::field('post_types_seo')) ? self::field('post_types_seo') : [];

				$__post_types = null;
				if($post_types){
					foreach ($post_types as $pt) {
						$__post_types[] = [
							[
								'param' => 'post_type',
								'operator' => '==',
								'value' => $pt,
							]
						];
					}
				}

				$__post_types[] = [
					[
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'post',
					]
				];

				$__post_types[] = [
					[
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'page',
					]
				];

				$__post_types[] = [
					[
						'param' => 'taxonomy',
						'operator' => '==',
						'value' => 'all',
					]
				];

				$__post_types[] = [
					[
						'param' => 'user_form',
						'operator' => '==',
						'value' => 'all',
					]
				];

				acf_add_local_field_group([
					'key' => 'group_6371c77346f80',
					'title' => __('SEO'),
					'fields' => [],
					'location' => $__post_types,
					'menu_order' => 0,
					'position' => 'normal',
					'style' => 'default',
					'label_placement' => 'top',
					'instruction_placement' => 'label',
					'hide_on_screen' => '',
					'active' => true,
					'description' => '',
					'show_in_rest' => 0,
				]);

				acf_add_local_field([
					'parent' => 'group_6371c77346f80',
					'key' => 'field_6371c773c2454',
					'label' => 'Search Engines',
					'name' => '',
					'aria-label' => '',
					'type' => 'tab',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'placement' => 'top',
					'endpoint' => 0,
				]);

				acf_add_local_field([
					'parent' => 'group_6371c77346f80',
					'key' => 'field_6371c8e9c245e',
					'label' => 'Index',
					'name' => 'index_se',
					'aria-label' => '',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'message' => '',
					'default_value' => 1,
					'ui' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
				]);

				acf_add_local_field([
					'parent' => 'group_6371c77346f80',
					'key' => 'field_6371c813c2458',
					'label' => 'Title',
					'name' => 'title_se',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'default_value' => '',
					'maxlength' => 65,
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
				]);

				acf_add_local_field([
					'parent' => 'group_6371c77346f80',
					'key' => 'field_6371c86fc2459',
					'label' => 'Description',
					'name' => 'description_se',
					'aria-label' => '',
					'type' => 'textarea',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'default_value' => '',
					'maxlength' => 300,
					'rows' => '',
					'placeholder' => '',
					'new_lines' => '',
				]);

				acf_add_local_field([
					'parent' => 'group_6371c77346f80',
					'key' => 'field_6371c8acc245a',
					'label' => 'Social Networks',
					'name' => '',
					'aria-label' => '',
					'type' => 'tab',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'placement' => 'top',
					'endpoint' => 0,
				]);

				acf_add_local_field([
					'parent' => 'group_6371c77346f80',
					'key' => 'field_6371c8afc245b',
					'label' => 'Title',
					'name' => 'title_sn',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'default_value' => '',
					'maxlength' => 65,
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
				]);

				acf_add_local_field([
					'parent' => 'group_6371c77346f80',
					'key' => 'field_6371c90ac245f',
					'label' => 'Description',
					'name' => 'description_sn',
					'aria-label' => '',
					'type' => 'textarea',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'default_value' => '',
					'maxlength' => 300,
					'rows' => '',
					'placeholder' => '',
					'new_lines' => '',
				]);

				acf_add_local_field([
					'parent' => 'group_6371c77346f80',
					'key' => 'field_6371c8bec245d',
					'label' => 'Image',
					'name' => 'image_sn',
					'aria-label' => '',
					'type' => 'image',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'return_format' => 'url',
					'library' => 'all',
					'min_width' => 1200,
					'min_height' => 630,
					'min_size' => '',
					'max_width' => 1200,
					'max_height' => 630,
					'max_size' => '',
					'mime_types' => '.jpg,.jpg',
					'preview_size' => 'full',
				]);
			}


			/*
			* Register Menu Locations
			*/
			$menus = !empty(self::field('register_nav_menus')) ? self::field('register_nav_menus') : [];
			
			if($menus){
				$__menus = [];
				foreach ($menus as $menu) {
					$name = $menu['name'];
					$slug = $menu['slug'];
					$__menus[$slug] = $name;
				}
				register_nav_menus($__menus);
			}

			/*
			* Register Options Pages
			*/
			$options_pages = !empty(self::field('register_options_pages')) ? self::field('register_options_pages') : [];
			if($options_pages){
				foreach ($options_pages as $op) {
					acf_add_options_page($op);
				}
			}
		});

	}


	static function inc($file_path = null, $url = false){
		return self::template_directory('inc/' . $file_path, $url);
	}

	static function tp($file_path = null, $url = false){
		return self::template_directory('inc/template-parts/' . $file_path, $url);
	}

	static function assets($file_path = null, $url = false){
		return self::template_directory('assets/' . $file_path, $url);
	}

	static function template_directory($file_path = null, $url = false){
		$directory_path = new scg();
		
		$directory_path = (get_template_directory() === get_stylesheet_directory() ? get_template_directory() : get_stylesheet_directory()) . '/' . $file_path;

		if($url === true)
			$directory_path = (get_template_directory() === get_stylesheet_directory() ? get_template_directory_uri() : get_stylesheet_directory_uri()) . '/' . $file_path;

		return $directory_path;
	}

	static function field($field_slug = null, $id = null){
		if(!class_exists('ACF')) return;
		if($field_slug && $id)
			return get_field($field_slug, $id);

		elseif($field_slug)
			return !empty(get_field($field_slug, 'options')) ? get_field($field_slug, 'options') : get_field($field_slug);


		return;
	}

	static function cpt($post_type = 'post', $args = []){

		$parameters = array(
			'posts_per_page' => -1,
			'paged' => 1
		);

		if(!empty($args)){
			foreach($args as $arg_key => $arg){
				$parameters[$arg_key] = $arg;
			}
		}

		$parameters['post_type'] = $post_type;

		$result = new WP_Query($parameters);


		return $result;
	}

	static function menu($theme_location = null, $args = []){

		$parameters = array( 
			'menu' => '',
			'container' => false,
			'container_class' => '', 
			'container_id' => '', 
			'menu_class' => '',
			'menu_id' => '',
			'echo' => false, 
			'fallback_cb' => 'wp_page_menu', 
			'before' => '', 
			'after' => '', 
			'link_before' => '',
			'link_after' => '', 
			'items_wrap' => '<ul>%3$s</ul>', 
			'item_spacing' => 'preserve',
			'depth' => 0,
			'walker' => ''
		);

		if(!empty($args)){
			foreach($args as $arg_key => $arg){
				$parameters[$arg_key] = $arg;
			}
		}

		if(isset($parameters['add_mobile_bars']) && (int)$parameters['add_mobile_bars'] > 0){

			$html = '<div class="ham-menu">';
			for ($i=0; $i < (int)$parameters['add_mobile_bars']; $i++) { 
				$html .= '<span></span>';
			}
			$html .= '</div>';

			$parameters['items_wrap'] = $parameters['items_wrap'] . $html;
		}


		$parameters['theme_location'] = $theme_location;


		$result = wp_nav_menu($parameters);


		return $result;
	}

	static function button($text = 'Aucun texte.', $args = ['href' => null, 'class' => null, 'attr' => null, 'before' => null, 'after' => null]){

		$href = !empty($args['href']) ? ' data-href="'. $args['href'] .'"' : null;
		$class = !empty($args['class']) ? ' class="'. $args['class'] .'"' : null;
		$attr = !empty($args['attr']) ? ' '. $args['attr'] : null;
		$before = !empty($args['before']) ? ' '. $args['before'] : null;
		$after = !empty($args['after']) ? ' '. $args['after'] : null;

		$result = '<button'. $class . $href . $attr .'>';
			$result .= $before;

				if($text)
					$result .= '<span>'. $text .'</span>';
				
			$result .= $after;
		$result .= '</button>';

		return $result;
	}

	static function id($code_base = 'abcdefghijABCDEFGHIJ', $substr = [0, 4]){
		
		$shuffle_code = str_shuffle($code_base);
		$code = substr($shuffle_code, $substr[0], $substr[1]);


		return 'g_id-' . $code;
	}

}

new scg();

?>