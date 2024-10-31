<?php


class prar_rating_admin extends prar_rating {

	// private $options;

	var $path; 				// path to plugin dir
	var $lbo_plugin_name; 	// friendly name of this plugin for re-use throughout
	var $lbo_plugin_menu; 	// friendly menu title for re-use throughout
	var $lbo_plugin_slug; 	// slug name of this plugin for re-use throughout
	var $lbo_plugin_ref; 	// reference name of the plugin for re-use throughout
	
	
	function __construct() {		
		$this->path = plugin_dir_path( __FILE__ );
		$this->lbo_plugin_name = "Post Rating and Review";
		$this->lbo_plugin_menu = "Rating and Review";
		$this->lbo_plugin_slug = "prar_rating";
		$this->lbo_plugin_ref = "prar_rating";
		
		// Ajoute une feuille de style spéciale à l'admin
		add_action('admin_enqueue_scripts', array($this, 'prar_rating_admin_load_custom_admin_style'));

		// Menus et options en admin
		add_action( 'admin_init', array($this, 'register_settings_fields') );
		add_action( 'admin_menu', array($this, 'register_settings_page') );

		// Ajoute des metabox en Admin
		add_action('add_meta_boxes', array($this, 'prar_rating_admin_add_metaboxes'));
		
		// Ajoute la liste des notes effectuées dans le profil du user
		add_action('show_user_profile', array($this, 'prar_rating_admin_show_all_notes'));
		add_action('edit_user_profile', array($this, 'prar_rating_admin_show_all_notes'));
		
		// Ajax purge data
		add_action('wp_ajax_prar_rating_admin_purge_data', array($this, 'prar_rating_admin_ajax_purge_data'));

		$this->options = get_option('prar_plugin_options');
		
		if ($this->options['reviews_actif']) {
			// Ajoute une colonne note dans la liste des commentaires en admin
			add_filter( 'manage_edit-comments_columns', array($this, 'prar_rating_admin_comments_list_add_column' ));
			add_action('manage_comments_custom_column', array($this, 'prar_rating_admin_manage_comments_custom_column'), 10, 2);
			
			if ($this->options['reviews_comment_modifiable_admin'] == false) {
				// Filtre les actions possibles sur la liste des commentaires en admin
				add_filter('comment_row_actions', array($this, 'prar_rating_admin_comment_row_actions'), 10, 2);
			}
		}
		
	}

	// Ne permet pas la modification d'un commentaire en admin par un user autre que l'auteur du commentaire
	function prar_rating_admin_comment_row_actions($array_actions, $comment) {
		$id_ligne_table_log = get_comment_meta($comment->comment_ID, PRAR_RATING_COMMENT_META_NAME, true);
		if ($id_ligne_table_log) {
			$user_id = get_current_user_id();
			if ($user_id != $comment->user_id) {
				unset($array_actions['quickedit']);
				unset($array_actions['edit']);
			}
		}
		return $array_actions;
	}

	function prar_rating_admin_load_custom_admin_style() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_register_style('prar_admin_css', plugins_url('/css/prar-admin-style.css', dirname(__FILE__)), false, PRAR_PLUGIN_VERSION );
		wp_enqueue_style('prar_admin_css');
	}
	
	function prar_rating_admin_comments_list_add_column($cols) {
		$add_col = array('prar_rating_note' => _x('Note', 'Admin column name in comments list', 'post-rating-and-review'));
		$cols = array_slice( $cols, 0, 3, true ) + $add_col + array_slice( $cols, 3, NULL, true );
		return $cols;
	}
	
	function prar_rating_admin_manage_comments_custom_column($column, $comment_id) {
		switch ($column) {
			case 'prar_rating_note' : 
				$id_ligne_table_log = get_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, true);
				if ($id_ligne_table_log > 0) {
					$tab_log = prar_rating_database::prar_rating_database_get_note_by_log_id($id_ligne_table_log);
					echo '<span class="prar_rating_note">' . esc_html($this->prar_rating_get_user_note_formattee($tab_log['note'])) . '</span>' . '/' . esc_html($this->options['note_max']);
				}
				
				break;
		}
	}
		
	function prar_rating_admin_add_metaboxes() {
		add_meta_box(
			'prar_rating_post_metabox',								// ID de la metabox
			'Post Rating & Review',										// Title metabox
			array($this, 'prar_rating_admin_affiche_metabox_note'),	// Fonction pour afficher la metabox
			'',													// post type
			'side',												// positionnement de la metabox
			'high'												// priorité
		);
	}

	function prar_rating_admin_affiche_metabox_note($post) {
		$note = prar_rating_database::prar_rating_database_get_note_for_post($post->ID);

		if ($note['note'] == 0) {
			echo _x('Ce post n\'a pas été noté', 'Admin edit post screen', 'post-rating-and-review');
		} else {
			$lbo_rating = new prar_rating();
			echo $lbo_rating->prar_sc_display_rating_for_post(
				array(
					'post_id' 			=> $post->ID,
					'size' 				=> 40
				),
				false,
				'prar_display_rating_for_post'
			);
			// echo esc_html_x('Note', 'Admin edit post screen', 'post-rating-and-review') . ' = ' . esc_html($lbo_rating->prar_rating_get_average_note_formattee($note['note'])) . '<br>';
			// echo esc_html_x('Nombre de notes', 'Admin edit post screen', 'post-rating-and-review') . ' = ' . esc_html(number_format_i18n($note['number_of_notes'], 0));
			$synthese_html = $lbo_rating->prar_rating_get_html_widget_synthese_note($post->ID);
			echo $synthese_html;
			?>
			<style>
				.prar-rating-text-after-overall-rating {display: none;}
			</style>
			<?php
		}
	}
	
	function register_settings_page() {
		add_submenu_page(
			'options-general.php',														// Parent menu item slug	
			_x('Réglages Post Rating & Review', 'Admin menu label', 'post-rating-and-review'),		// Page Title
			'Post Rating & Review',										// Menu Title
			'manage_options',									// Capability
			$this->lbo_plugin_ref . '_options',					// Menu Slug
			array( $this, 'show_settings_page' )				// Callback function
		);

	}
	
	function show_settings_page() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( _x('Vous n\'avez pas les permissions nécessaires pour afficher cette page.', 'Admin options', 'post-rating-and-review' ) );
		}
		
		?>
		<script>
			function check_select_for_cf() {
				if (jQuery('#save_note_dans_post option:selected').val() == 0) {
					jQuery('tr.tab_options_save_meta').css('display', 'none');
				} else {
					jQuery('tr.tab_options_save_meta').css('display', 'table-row');
				}
			}
			
			function check_select_for_review_actif() {
				if (jQuery('#reviews_actif option:selected').val() == 0) {
					jQuery('tr.tab_options_review_actif').css('display', 'none');
				} else {
					jQuery('tr.tab_options_review_actif').css('display', 'table-row');
				}
			}
			
			jQuery(document).ready(function($) {
				check_select_for_cf();
				check_select_for_review_actif();
				// load color picker
				$( '.prar-admin-color-picker' ).wpColorPicker();
				
				// Tooltip
				$('.prar-admin-tooltip').on('click', function() {
					if ($(this).next('.prar-admin-tooltip-text').css('display') != 'none') {
						$(this).next('.prar-admin-tooltip-text').css('display', 'none');
					} else {
						$('.prar-admin-tooltip-text').css('display', 'none');
						$(this).next('.prar-admin-tooltip-text').toggle(200);
					}
				});
				
				$('#save_note_dans_post').on('change', function() {
					check_select_for_cf();
				});
				$('#reviews_actif').on('change', function() {
					check_select_for_review_actif();
				});
				$('#reviews_rating_obligatoire').on('change', function() {
					if (($('#reviews_rating_obligatoire option:selected').val() == 0) && ($('#reviews_comment_obligatoire option:selected').val() == 0)) {
						// regarde si le commentaire est obligatoire sinon erreur
						alert("<?php echo _x('Au moins un des 2 items doit être obligatoire.', 'Admin options - error message', 'post-rating-and-review') ?>");
						$('#reviews_comment_obligatoire option[value="1"]').prop('selected', true);
					}
				});
				$('#reviews_comment_obligatoire').on('change', function() {
					if (($('#reviews_rating_obligatoire option:selected').val() == 0) && ($('#reviews_comment_obligatoire option:selected').val() == 0)) {
						// regarde si la note est obligatoire sinon erreur
						alert("<?php echo _x('Au moins un des 2 items doit être obligatoire.', 'Admin options - error message', 'post-rating-and-review') ?>");
						$('#reviews_rating_obligatoire option[value="1"]').prop('selected', true);
					}
				});
				$('#submit_purge').on('click', function(e) {
					e.preventDefault();
					if (confirm('Do you confirm you want to delete all datas?')) {
						$('#submit_purge').prop('disabled', 'disabled');
						$('#prar_rating_spinner').css('display', 'inline-block');
						jQuery.ajax({
							url: ajaxurl,
							type: "POST",
							data: {
								'action': 'prar_rating_admin_purge_data',
							}
						}).done(function(reponse) {
							var retour = JSON.parse(reponse);
							$('#prar_rating_spinner').css('display', 'none');
							$('#submit_purge').prop('disabled', '');
							if (retour.result == 'ok') {
								$('#submit_purge_message').html("<?php echo _x('Purge réalisée', 'Admin options', 'post-rating-and-review') ?>").css('display', 'inline-block');
							} else {
								$('#submit_purge_message').html("<?php echo _x('Une erreur s\'est produite', 'Admin options', 'post-rating-and-review') ?>").css('display', 'inline-block');
							}
						});
					}
				});
			});
		</script>
		<div class="wrap">
			<h1><?php echo _x('Réglages Post Rating & Review', 'Admin options - page title', 'post-rating-and-review') ?></h1>
			<form method="POST" action="options.php" enctype="multipart/form-data" autocomplete="off">
				<?php
					settings_fields( $this->lbo_plugin_ref . '_settings' );
					do_settings_sections( $this->lbo_plugin_ref . '_options' );
					submit_button();
				?>
			</form>
			<h1><?php echo _x('Outils', 'Admin options', 'post-rating-and-review') ?></h1>
			<table class="form-table">
				<tbody>
					<form method="POST" id="form_tools" action="" enctype="multipart/form-data" autocomplete="off">
						<tr class="long_th">
							<th scope="row"><label for="submit_purge"><?php echo _x('Purger l\'ensemble des notes. Attention cette opération est irreversible : toutes les notes seront supprimées ainsi que les metas associées dans les comments et les posts.', 'Admin options', 'post-rating-and-review') ?><label></th>
							<td>
								<input type="button" name="submit_purge" id="submit_purge" value="<?php echo _x('Lancer la purge', 'Admin options', 'post-rating-and-review') ?>">
								<div id="submit_purge_message" style="display: none"></div>
								<div class="lds-spinner" id="prar_rating_spinner"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
							</td>
						</tr>
					</form>
				</tbody>
			</table>
		</div>
		<?php
		
		
	}

	function prar_rating_admin_ajax_purge_data() {
		if (! is_admin()) {
			echo json_encode(array('result' => 'error'));
			die();
		} else {
			// Lance la purge des data
			prar_rating_database::prar_rating_database_delete_all_votes();
			echo json_encode(array('result' => 'ok'));
			die();
		}
	}

	function register_settings_fields() {
		
		register_setting($this->lbo_plugin_ref . '_settings', 'prar_plugin_options');
		add_settings_section(
			$this->lbo_plugin_ref.'_section_general',	 		// ID used to identify this section and with which to register options
			_x('Réglages généraux widget note', 'Admin options', 'post-rating-and-review'), 					// Title to be displayed on the administration page
			array($this,'show_settings_general_section'), 		// Callback used to render the description of the section
			$this->lbo_plugin_ref . '_options' 			// Page on which to add this section of options
		);
		add_settings_field(
			'note_max', 									// ID used to identify the field
			_x('Note maximum', 'Admin options', 'post-rating-and-review'), 				// The label to the left of the option interface element
			array($this,'show_settings_field_note_max'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_general',		// The name of the section to which this field belongs
			array('label_for' => 'note_max', 'class' => 'short_th'),
		);
		// add_settings_field(
			// 'step_display_note', 									// ID used to identify the field
			// _x('Pas affichage note', 'Admin options', 'post-rating-and-review'), 				// The label to the left of the option interface element
			// array($this,'show_settings_field_step_display_note'), 	// The name of the function responsible for rendering the option interface
			// $this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			// $this->lbo_plugin_ref.'_section_general',		// The name of the section to which this field belongs
			// array('label_for' => 'step_display_note', 'class' => 'short_th'),
		// );
		add_settings_field(
			'step_set_note', 									// ID used to identify the field
			_x('Pas note par user', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_step_set_note'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_general',		// The name of the section to which this field belongs
			array('label_for' => 'step_set_note', 'class' => 'short_th'),
		);
		add_settings_field(
			'taille_stars', 									// ID used to identify the field
			_x('Taille par défaut étoiles', 'Admin options', 'post-rating-and-review'),		// The label to the left of the option interface element
			array($this,'show_settings_field_taille_stars'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_general',		// The name of the section to which this field belongs
			array('label_for' => 'taille_stars', 'class' => 'short_th'),
		);
		add_settings_field(
			'couleur_stars', 									// ID used to identify the field
			_x('Couleur des étoiles', 'Admin options', 'post-rating-and-review'),		// The label to the left of the option interface element
			array($this,'show_settings_field_couleur_stars'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_general',		// The name of the section to which this field belongs
			array('label_for' => 'couleur_stars', 'class' => 'short_th'),
		);
		add_settings_field(
			'couleur_chart_bar', 									// ID used to identify the field
			_x('Couleur de la barre widget chart', 'Admin options', 'post-rating-and-review'),		// The label to the left of the option interface element
			array($this,'show_settings_field_couleur_chart_bar'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_general',		// The name of the section to which this field belongs
			array('label_for' => 'couleur_chart_bar', 'class' => 'short_th'),
		);
		add_settings_field(
			'couleur_chart_bar_hover', 									// ID used to identify the field
			_x('Couleur de la barre widget chart (survol)', 'Admin options', 'post-rating-and-review'),		// The label to the left of the option interface element
			array($this,'show_settings_field_couleur_chart_bar_hover'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_general',		// The name of the section to which this field belongs
			array('label_for' => 'couleur_chart_bar_hover', 'class' => 'short_th'),
		);
		add_settings_field(
			'couleur_border_owned_review', 									// ID used to identify the field
			_x('Couleur bordure review du user connecté', 'Admin options', 'post-rating-and-review'),		// The label to the left of the option interface element
			array($this,'show_settings_field_couleur_border_owned_review'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_general',		// The name of the section to which this field belongs
			array('label_for' => 'couleur_border_owned_review', 'class' => 'short_th'),
		);

		add_settings_section(
			$this->lbo_plugin_ref.'_section_display_note',	 		// ID used to identify this section and with which to register options
			_x('Réglages affichage de la note', 'Admin options', 'post-rating-and-review'), 					// Title to be displayed on the administration page
			array($this,'show_settings_display_section'), 		// Callback used to render the description of the section
			$this->lbo_plugin_ref . '_options' 			// Page on which to add this section of options
		);
		add_settings_field(
			'affichage_note_globale', 									// ID used to identify the field
			_x('Afficher la note globale après les étoiles ?', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_affichage_note_globale'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_display_note',		// The name of the section to which this field belongs
			array('label_for' => 'affichage_note_globale', 'class' => 'long_th'),
		);
		add_settings_field(
			'affichage_nombre_notes', 									// ID used to identify the field
			_x('Afficher le nombre de notes après les étoiles ?', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_affichage_nombre_notes'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_display_note',		// The name of the section to which this field belongs
			array('label_for' => 'affichage_nombre_notes', 'class' => 'long_th'),
		);
		add_settings_field(
			'texte_apres_note_user', 									// ID used to identify the field
			_x('Texte affiché en dessous de la note d\'un user', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_texte_apres_note_user'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_display_note',		// The name of the section to which this field belongs
			array('label_for' => 'texte_apres_note_user', 'class' => 'long_th'),
		);

		add_settings_section(
			$this->lbo_plugin_ref.'_section_save_note',	 		// ID used to identify this section and with which to register options
			_x('Réglages sauvegarde note', 'Admin options', 'post-rating-and-review'), 					// Title to be displayed on the administration page
			array($this,'show_settings_sauvegarde_section'), 		// Callback used to render the description of the section
			$this->lbo_plugin_ref . '_options' 			// Page on which to add this section of options
		);
		add_settings_field(
			'texte_note_user_saved', 									// ID used to identify the field
			_x('Texte affiché après modification de la note', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_texte_note_user_saved'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_save_note',		// The name of the section to which this field belongs
			array('label_for' => 'texte_note_user_saved', 'class' => 'long_th'),
		);
		add_settings_field(
			'note_modifiable_apres_vote', 									// ID used to identify the field
			_x('Note modifiable par le user après le vote ?', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_note_modifiable_apres_vote'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_save_note',		// The name of the section to which this field belongs
			array('label_for' => 'note_modifiable_apres_vote', 'class' => 'long_th'),
		);
		add_settings_field(
			'save_note_dans_post', 									// ID used to identify the field
			_x('Sauvegarder la note globale en meta du post ?', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_save_note_dans_post'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_save_note',		// The name of the section to which this field belongs
			array('label_for' => 'save_note_dans_post', 'class' => 'long_th'),
		);
		add_settings_field(
			'nom_champ_nb_notes', 									// ID used to identify the field
			_x('Nom du champ meta nombre de notes', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_nom_champ_nb_notes'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_save_note',		// The name of the section to which this field belongs
			array('label_for' => 'nom_champ_nb_notes', 'class' => 'long_th tab_options_save_meta tab_right'),
		);
		add_settings_field(
			'nom_champ_note_moyennne', 									// ID used to identify the field
			_x('Nom du champ meta note moyenne', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_nom_champ_note_moyennne'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_save_note',		// The name of the section to which this field belongs
			array('label_for' => 'nom_champ_note_moyennne', 'class' => 'long_th tab_options_save_meta tab_right'),
		);
		add_settings_field(
			'type_champ_dans_post', 									// ID used to identify the field
			_x('Type de meta pour sauvegarde dans le post', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_type_champ_dans_post'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_save_note',		// The name of the section to which this field belongs
			array('label_for' => 'type_champ_dans_post', 'class' => 'long_th tab_options_save_meta tab_right'),
		);

		add_settings_section(
			$this->lbo_plugin_ref.'_section_reviews',	 		// ID used to identify this section and with which to register options
			_x('Paramétrage Reviews', 'Admin options', 'post-rating-and-review'), 											// Title to be displayed on the administration page
			array($this,'show_settings_reviews_section'), 		// Callback used to render the description of the section
			$this->lbo_plugin_ref . '_options' 			// Page on which to add this section of options
		);
		add_settings_field(
			'reviews_actif', 									// ID used to identify the field
			_x('Activer la gestion des reviews ?', 'Admin options', 'post-rating-and-review'),					// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_actif'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_actif', 'class' => 'long_th'),
		);
		add_settings_field(
			'reviews_post_types', 									// ID used to identify the field
			_x('Post types sur lesquels activer les reviews', 'Admin options', 'post-rating-and-review'),			// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_post_types'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_post_types', 'class' => 'long_th tab_right tab_options_review_actif'),
		);
		add_settings_field(
			'reviews_display_rating_on_archive', 									// ID used to identify the field
			_x('Afficher automatiquement le widget note sur les pages archives ?', 'Admin options', 'post-rating-and-review'),			// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_display_rating_on_archive'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_display_rating_on_archive', 'class' => 'long_th tab_right tab_options_review_actif'),
		);
		add_settings_field(
			'reviews_display_rating_on_single', 									// ID used to identify the field
			_x('Afficher automatiquement le widget note sur les pages single ?', 'Admin options', 'post-rating-and-review'),			// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_display_rating_on_single'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_display_rating_on_single', 'class' => 'long_th tab_right tab_options_review_actif'),
		);
		add_settings_field(
			'reviews_rating_obligatoire', 									// ID used to identify the field
			_x('Note obligatoire pour valider la review ?', 'Admin options', 'post-rating-and-review'),			// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_rating_obligatoire'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_rating_obligatoire', 'class' => 'long_th tab_right tab_options_review_actif'),
		);
		add_settings_field(
			'reviews_comment_obligatoire', 									// ID used to identify the field
			_x('Commentaire obligatoire pour valider la review ?', 'Admin options', 'post-rating-and-review'),			// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_comment_obligatoire'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_comment_obligatoire', 'class' => 'long_th tab_right tab_options_review_actif'),
		);
		add_settings_field(
			'reviews_comment_modifiable_auteur', 									// ID used to identify the field
			_x('Commentaire modifiable en front par l\'auteur de l\'avis ?', 'Admin options', 'post-rating-and-review'),			// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_comment_modifiable_auteur'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_comment_modifiable', 'class' => 'long_th tab_right tab_options_review_actif'),
		);
		add_settings_field(
			'reviews_comment_supprimable_auteur', 									// ID used to identify the field
			_x('Commentaire supprimable en front par l\'auteur de l\'avis ?', 'Admin options', 'post-rating-and-review'),			// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_comment_supprimable_auteur'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_comment_supprimable', 'class' => 'long_th tab_right tab_options_review_actif'),
		);
		add_settings_field(
			'reviews_comment_modifiable_admin', 									// ID used to identify the field
			_x('Commentaire modifiable dans l\'admin WP (user autre que l\'auteur) ?', 'Admin options', 'post-rating-and-review'),			// The label to the left of the option interface element
			array($this,'show_settings_field_reviews_comment_modifiable_admin'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_reviews',		// The name of the section to which this field belongs
			array('label_for' => 'reviews_comment_supprimable', 'class' => 'long_th tab_right tab_options_review_actif'),
		);

		add_settings_section(
			$this->lbo_plugin_ref.'_section_notification',	 		// ID used to identify this section and with which to register options
			_x('Paramétrage notifications email', 'Admin options', 'post-rating-and-review'), 											// Title to be displayed on the administration page
			array($this,'show_settings_notification_section'), 		// Callback used to render the description of the section
			$this->lbo_plugin_ref . '_options' 			// Page on which to add this section of options
		);
		add_settings_field(
			'send_rating_notif_to_post_author', 									// ID used to identify the field
			_x('Envoyer un mail à l\'auteur du post à chaque nouvelle note soumise ?', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_send_rating_notif_to_post_author'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_notification',		// The name of the section to which this field belongs
			array('label_for' => 'send_rating_notif_to_post_author', 'class' => 'long_th'),
		);
		add_settings_field(
			'send_review_notif_to_post_author', 									// ID used to identify the field
			_x('Envoyer un mail à l\'auteur du post à chaque nouvel avis soumis ?', 'Admin options', 'post-rating-and-review'),								// The label to the left of the option interface element
			array($this,'show_settings_field_send_review_notif_to_post_author'), 	// The name of the function responsible for rendering the option interface
			$this->lbo_plugin_ref . '_options',				// The page on which this option will be displayed
			$this->lbo_plugin_ref.'_section_notification',		// The name of the section to which this field belongs
			array('label_for' => 'send_review_notif_to_post_author', 'class' => 'long_th tab_options_review_actif'),
		);

	}
	
	function show_settings_general_section() {
		echo '<p>' . _x('Réglages généraux sur le plugin. Certains règlages peuvent être surchargés au moment de l\'appel des shortcodes.', 'Admin options', 'post-rating-and-review') . '</p>';
	}
	
	function show_settings_display_section() {
		echo '<p>' . _x('Paramètres d\'affichage de la note (note globale et note de l\'utilisateur)', 'Admin options', 'post-rating-and-review') . '</p>';
	}
	
	function show_settings_sauvegarde_section() {
		echo '<p>' . _x('Comportement du plugin au moment de l\'enregistrement d\'une note par l\'utilisateur', 'Admin options', 'post-rating-and-review') . '</p>';
	}
	
	function show_settings_reviews_section() {
		echo '<p>' . _x('Attention, la gestion des reviews repose sur les commentaires natifs Wordpress. Le plugin viendra se substituer aux commentaires natifs sur les types de post que vous aurez sélectionnés. Il faut donc que les commentaires soient activés sur les posts existants pour que le widget de rating soit visible.<br>Vous pouvez lire le fichier readme pour plus de détails.', 'Admin options', 'post-rating-and-review') . '</p>';
	}
	
	function show_settings_notification_section() {
		echo '<p>' . _x('Des emails de notification peuvent être envoyées à l\'auteur du post noté, que la gestion des avis soit activée ou non.', 'Admin options', 'post-rating-and-review') . '</p>';
	}
	
	function show_settings_field_integer($field_id) {
		printf(
			'<input type="number" id="%s" name="prar_plugin_options[%s]" value="%s" />',
			esc_attr($field_id), esc_attr($field_id),
			isset( $this->options[$field_id] ) ? esc_attr( $this->options[$field_id]) : ''
		);		
	}
	function show_settings_field_float($field_id) {
		printf(
			'<input type="number" id="%s" name="prar_plugin_options[%s]" value="%s" min="0.1" max="1" step="0.1" />',
			esc_attr($field_id), esc_attr($field_id),
			isset( $this->options[$field_id] ) ? esc_attr( $this->options[$field_id]) : ''
		);		
	}
	function show_settings_field_string($field_id) {
		printf(
			'<input type="text" id="%s" name="prar_plugin_options[%s]" value="%s" />',
			esc_attr($field_id), esc_attr($field_id),
			isset( $this->options[$field_id] ) ? esc_attr( $this->options[$field_id]) : ''
		);		
	}
	function show_settings_field_color($field_id) {
		printf(
			'<input type="text" id="%s" name="prar_plugin_options[%s]" value="%s" class="prar-admin-color-picker" />',
			esc_attr($field_id), esc_attr($field_id),
			isset( $this->options[$field_id] ) ? esc_attr( $this->options[$field_id]) : ''
		);
		$texte = _x('Cliquez sur le bouton Effacer pour revenir à la couleur par défaut', 'Admin options', 'post-rating-and-review');
		echo $this->prar_rating_admin_get_tooltip($texte);
	}
	function show_settings_field_booleen($field_id) {
		$valeur = $this->options[$field_id];
		printf(
			'<select id="%s" name="prar_plugin_options[%s]" /><option %s value="1">' . _x('Oui', 'Admin options', 'post-rating-and-review') . '</option><option %s value="0">' . _x('Non', 'Admin options', 'post-rating-and-review') . '</option></select>',
			esc_attr($field_id), esc_attr($field_id), $valeur == 1 ? 'selected':'', $valeur == 0 ? 'selected':''
		);		
	}
	
	public function show_settings_field_note_max() {
		$this->show_settings_field_integer('note_max');
	}
	public function show_settings_field_taille_stars() {
		$this->show_settings_field_integer('taille_stars');
	}
	public function show_settings_field_couleur_stars() {
		$this->show_settings_field_color('couleur_stars');
	}
	public function show_settings_field_couleur_chart_bar() {
		$this->show_settings_field_color('couleur_chart_bar');		
	}
	public function show_settings_field_couleur_chart_bar_hover() {
		$this->show_settings_field_color('couleur_chart_bar_hover');		
	}
	public function show_settings_field_couleur_border_owned_review() {
		$this->show_settings_field_color('couleur_border_owned_review');		
	}
	// public function show_settings_field_step_display_note() {
		// $this->show_settings_field_float('step_display_note');
		// $texte = _x('Correspond à la précision d\'affichage de la note moyenne. Par exemple, 0.1 affichera la note moyenne avec 1 décimale.', 'Admin options', 'post-rating-and-review');
		// echo $this->prar_rating_admin_get_tooltip($texte);
	// }
	public function show_settings_field_step_set_note() {
		$this->show_settings_field_float('step_set_note');
		$texte = _x('Correspond à la précision de la note que peut attribuer un utilisateur (conseil: 0.5 ou 1)', 'Admin options', 'post-rating-and-review');
		echo $this->prar_rating_admin_get_tooltip($texte);
	}
	public function show_settings_field_texte_note_user_saved() {
		$this->show_settings_field_string('texte_note_user_saved');
	}
	public function show_settings_field_affichage_note_globale() {
		$this->show_settings_field_booleen('affichage_note_globale');
	}
	public function show_settings_field_affichage_nombre_notes() {
		$this->show_settings_field_booleen('affichage_nombre_notes');
	}
	public function show_settings_field_texte_apres_note_user() {
		$this->show_settings_field_string('texte_apres_note_user');
	}
	public function show_settings_field_note_modifiable_apres_vote() {
		$this->show_settings_field_booleen('note_modifiable_apres_vote');
	}
	public function show_settings_field_save_note_dans_post() {
		$this->show_settings_field_booleen('save_note_dans_post');
	}
	public function show_settings_field_nom_champ_nb_notes() {
		$this->show_settings_field_string('nom_champ_nb_notes');
	}
	public function show_settings_field_nom_champ_note_moyennne() {
		$this->show_settings_field_string('nom_champ_note_moyennne');
	}
	public function show_settings_field_type_champ_dans_post() {
		$field_id = 'type_champ_dans_post';
		$valeur = $this->options[$field_id];
		printf(
			'<select id="%s" name="prar_plugin_options[%s]" /><option %s value="meta">' . _x('Meta Wordpress', 'Admin options', 'post-rating-and-review') . '</option><option %s value="acf">' . _x('Champ ACF', 'Admin options', 'post-rating-and-review') . '</option></select>',
			esc_attr($field_id), esc_attr($field_id), $valeur == 'meta' ? 'selected':'', $valeur == 'acf' ? 'selected':''
		);		
	}
	public function show_settings_field_send_rating_notif_to_post_author() {
		$this->show_settings_field_booleen('send_rating_notif_to_post_author');
		$texte = _x('Permet d\'envoyer un mail à l\'auteur du post noté via le shortcode "prar_set_rating_for_post", même si la gestion des avis n\'est pas activée.', 'Admin options', 'post-rating-and-review');
		echo $this->prar_rating_admin_get_tooltip($texte);
	}
	public function show_settings_field_send_review_notif_to_post_author() {
		$this->show_settings_field_booleen('send_review_notif_to_post_author');
		$texte = _x('Le mail standard WP de notification pour un nouveau commentaire sera personnalisé. Il incluera la note attribuée.', 'Admin options', 'post-rating-and-review');
		echo $this->prar_rating_admin_get_tooltip($texte);
	}
	public function show_settings_field_reviews_actif() {
		$this->show_settings_field_booleen('reviews_actif');
	}
	public function show_settings_field_reviews_post_types() {
		// Liste des post types sous forme de select
		$valeur = $this->options['reviews_post_types'];
		$valeur = $valeur ? $valeur : array();
		$liste_post_types = get_post_types(array('public' => true), 'objects', 'and');
		printf('<select name="prar_plugin_options[%s][]" id="%s" multiple size="8" style="min-width:300px;">', 'reviews_post_types', 'reviews_post_types');
		if ($liste_post_types) {
			foreach ($liste_post_types as $post_type) {
				if ($post_type->name != 'page') {
					$selected = in_array($post_type->name, $valeur) ? 'selected' : '';
					printf('<option %s value="%s">%s</option>', $selected, $post_type->name, $post_type->label);
				}
			}
		}
		echo '</select><br>';
		echo _x('Vous pouvez sélectionner plusieurs items en maintenant la touche CTRL enfoncée', 'Admin options', 'post-rating-and-review');
	}
	public function show_settings_field_reviews_display_rating_on_archive() {
		$this->show_settings_field_booleen('reviews_display_rating_on_archive');
		$texte = _x('Le widget Display note s\'affichera automatiquement juste après le titre du post pour chaque post sur la page archive.', 'Admin options', 'post-rating-and-review');
		echo $this->prar_rating_admin_get_tooltip($texte);
	}
	public function show_settings_field_reviews_display_rating_on_single() {
		$this->show_settings_field_booleen('reviews_display_rating_on_single');
		$texte = _x('Le widget Display note s\'affichera automatiquement juste après le titre du post sur la page single.', 'Admin options', 'post-rating-and-review');
		echo $this->prar_rating_admin_get_tooltip($texte);
	}
	public function show_settings_field_reviews_comment_modifiable_auteur() {
		$this->show_settings_field_booleen('reviews_comment_modifiable_auteur');
	}
	public function show_settings_field_reviews_comment_supprimable_auteur() {
		$this->show_settings_field_booleen('reviews_comment_supprimable_auteur');
	}
	public function show_settings_field_reviews_comment_modifiable_admin() {
		$this->show_settings_field_booleen('reviews_comment_modifiable_admin');
	}
	public function show_settings_field_reviews_rating_obligatoire() {
		$this->show_settings_field_booleen('reviews_rating_obligatoire');
		$texte = _x('Vous devez avoir au moins la note ou le commentaire indiqué comme obligatoire. Vous pouvez bien sûr avoir les deux obligatoires.', 'Admin options', 'post-rating-and-review');
		echo $this->prar_rating_admin_get_tooltip($texte);
	}
	public function show_settings_field_reviews_comment_obligatoire() {
		$this->show_settings_field_booleen('reviews_comment_obligatoire');
		$texte = _x('Vous devez avoir au moins la note ou le commentaire indiqué comme obligatoire. Vous pouvez bien sûr avoir les deux obligatoires.', 'Admin options', 'post-rating-and-review');
		echo $this->prar_rating_admin_get_tooltip($texte);
	}

	
	function prar_rating_admin_show_all_notes($user) {
		echo '<h2>' . _x('Notes attribuées par l\'utilisateur (Post Rating & Review)', 'Admin edit user screen - Title', 'post-rating-and-review') . '</h2>';
		echo '<div class="prar-rating-profile">';
		$tab_notes = prar_rating_get_all_notes_for_user($user->ID);
		if (! $tab_notes) {
			echo '<div>' . _x('Aucune note attribuée par l\'utilisateur', 'Admin edit user screen', 'post-rating-and-review') . '</div>';
		} else {
			$note_totale = 0;
			echo '<table><thead><tr><th>' . _x('Date', 'Admin edit user screen', 'post-rating-and-review') . '</th><th>' . _x('# Post', 'Admin edit user screen', 'post-rating-and-review') . '</th><th>' . _x('Titre post', 'Admin edit user screen', 'post-rating-and-review') . '</th><th>' . _x('Note', 'Admin edit user screen', 'post-rating-and-review') . '</th></tr></thead><tbody>';
			foreach ($tab_notes as $note) {
				printf('<tr><td>%s</td><td>%d</td><td>%s</td><td>%01.1f</td></tr>', date_i18n(get_option('date_format') . ' à ' .get_option('time_format'), strtotime($note['date'])), $note['post']->ID, $note['post']->post_title, $note['note']);
				$note_totale += $note['note'];
			}
			echo '</tbody></table>';
			printf(_x('Nombre de notes : %d','Admin edit user screen' , 'post-rating-and-review') . ' - ' . _x('Note moyenne : %01.1f', 'Admin edit user screen', 'post-rating-and-review'), count($tab_notes), $note_totale / count($tab_notes));
		}
		echo '</div>';
	}
	
	function prar_rating_admin_get_tooltip($texte) {
		$retour = '<div class="prar-admin-tooltip-div"><span class="dashicons dashicons-editor-help prar-admin-tooltip"></span>';
		$retour .= '<span class="prar-admin-tooltip-text">';
		$retour .= $texte;
		$retour .= '</span></div>';
		return $retour;
	}
	
}