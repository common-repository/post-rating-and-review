<?php
class prar_rating_review extends prar_rating {

	
	function __construct() {		
		
		$this->options = get_option('prar_plugin_options');

		if ($this->options['reviews_actif']) {
			
			// ----------
			// NEW REVIEW
			// ----------
			// Filtre pour empecher les reviews pour les utilisateurs non loggés (intercepte le get_option('comment_registration')
			add_filter('option_comment_registration', array($this, 'prar_rating_review_option_comment_registration'), 999, 2);
			// Filtre pour ajouter le widget rating dans le formulaire de commentaire
			add_filter('comment_form_field_comment', array($this, 'prar_rating_review_custom_comment_form_field_comment'), 999);
			if ($this->options['reviews_rating_obligatoire']) {
				// Filtre pour vérifier si la note a été mise au moment de l'enregistrement du commentaire (uniquement quand la note est mandatory)
				add_filter('pre_comment_approved', array($this, 'prar_rating_review_pre_comment_approved'), 10, 2);
			}
			// Filtre sur le texte du commentaire non obligatoire
			if ($this->options['reviews_comment_obligatoire'] == false) {
				add_filter('allow_empty_comment', array($this, 'prar_rating_review_allow_empty_comment'), 10, 2);
			}			
			// Action ajax pour sauvegarde nouvelle review (évite utilisation wp-comments-post.php)
			add_action('wp_ajax_prar_rating_save_new_review', array($this, 'prar_rating_ajax_save_new_review'));
			// Sauvegarde de la note juste après le save du comment par WP
			add_action('wp_insert_comment', array($this, 'prar_rating_review_wp_insert_comment'), 10, 2);
			// Changement du statut de la note en fonction du statut du commentaire
			add_action('wp_set_comment_status', array($this, 'prar_rating_review_wp_set_comment_status'), 5, 2);	// Priorité 5 pour passer avant l'envoi du mail à l'auteur du post (mail std WP)

			// -------------
			// UPDATE REVIEW
			// -------------
			// Action ajax pour modification review par l'auteur
			if ($this->options['reviews_comment_modifiable_auteur']) {
				add_action('wp_ajax_prar_rating_update_review', array($this, 'prar_rating_ajax_update_review'));
			}
			// Action ajax pour suppression review par l'auteur
			if ($this->options['reviews_comment_supprimable_auteur']) {
				add_action('wp_ajax_prar_rating_delete_review', array($this, 'prar_rating_ajax_delete_review'));
			}

			// ---------------------------------
			// DISPLAY REVIEW (IN COMMENTS LIST)
			// ---------------------------------
			// Template spécifique pour la gestion des reviews (remplace comments.php)
			add_filter('comments_template', array($this, 'prar_rating_review_comments_template'), 999);
			// Filtre pour empecher les réponses sur les reviews par les utilisateurs
			add_filter('comment_reply_link', array($this, 'prar_rating_review_comment_reply_link'), 5, 4);
			// Permet d'afficher la réponse d'un administrateur à une review même si les paramètre des commentaires imbriqués est décoché.
			add_filter('wp_list_comments_args', array($this, 'prar_rating_review_wp_list_comments_args'), 999);
			// Front : affiche de la note dans le pavé de chaque commentaire (wp_list_comments)
			add_filter('comment_text', array($this, 'prar_rating_review_front_comment_text'), 999, 3);
			// Filtre des commentaires en fonction de la note (clic sur le widget synthesis)
			add_filter('comments_template_query_args', array($this, 'prar_rating_review_comments_template_query_args'), 9999);
			
			// ------------------
			// DELETE REVIEW HOOK
			// ------------------
			// Delete du comment = delete de la note (action avant suppression car les meta ne sont pas encore supprimées)
			add_action('delete_comment', array($this, 'prar_rating_review_delete_comment'), 10, 2);
			// Front : n'affiche pas le bloc nouveau commentaire si le user a déjà évalué le post
			// add_filter('comments_open', array($this, 'prar_rating_review_comments_open'), 10, 2);
			
			// ------------------------------------------
			// DISPLAY AUTO WIDGET DISPLAY OVERALL RATING
			// ------------------------------------------
			// Widget display sur les pages archives
			if ($this->options['reviews_display_rating_on_archive']) {
				add_filter('the_title', array($this, 'prar_rating_review_display_rating_on_archive'), 100, 2);
			}
			// Widget display sur les pages single
			if ($this->options['reviews_display_rating_on_single']) {
				add_filter('the_content', array($this, 'prar_rating_review_display_rating_on_single'), 100, 2);
			}
		}
	}
	
	function prar_rating_review_allow_empty_comment($bool, $comment_data) {
		$post_id = isset($comment_data['comment_post_ID']) ? $comment_data['comment_post_ID'] : 0 ;
		if ($post_id && $this->prar_rating_review_is_post_type_to_be_reviewed($post_id)) {
			return true;
		}
	}
	
	function prar_rating_review_wp_list_comments_args($args) {
		$post_id = get_the_ID();
		if ($post_id && $this->prar_rating_review_is_post_type_to_be_reviewed($post_id)) {
			$args['max_depth'] = '2';
		}
		return $args;
	}

	// Permet de filtrer les reviews sur le single post en fonction de la note attribuée
	function prar_rating_review_comments_template_query_args($args_query) {
		$filtre_note = isset($_GET['review_note']) ? sanitize_text_field($_GET['review_note']) : false;
		if ($filtre_note !== false) {
			$liste_id_log = prar_rating_database::prar_rating_database_get_log_ids_by_note($filtre_note);
			// 1 - Récupère les comments parent
			// Pas possible en 1 seule requete avec une clause where sur les meta car la meta n'est stockée qu'au niveau du parent
			$new_args = array(
				'fields'		=> 'ids',
				'status'		=> $args_query['status'],
				'post_id'		=> $args_query['post_id'],
				'meta_query'	=> array(
					array(
						'key'		=> PRAR_RATING_COMMENT_META_NAME,
						'value'		=> count($liste_id_log) ? $liste_id_log : array(0),
						'compare'	=> 'IN'
					)
				)
			);
			$liste_id_parent = get_comments($new_args);

			// 2 - Récupère les commentaires children liés aux parents
			$liste_id_children = array();
			if ($liste_id_parent) {
				$liste_id_children = get_comments(array('fields' => 'ids', 'parent__in' => $liste_id_parent));
			}

			$liste_totale = array_merge($liste_id_parent, $liste_id_children);
			// 3 - Modifie les args pour intégrer la liste
			if (count($liste_totale) == 0) {
				// Aucun commentaire : met un critère que renverra rien
				$liste_totale = array(0);
			}
			$args_query['comment__in'] = $liste_totale;
		}
		return $args_query;
	}
	
	function prar_rating_review_comments_template($template) {
		$post_id = get_the_ID();
		if ($post_id && $this->prar_rating_review_is_post_type_to_be_reviewed($post_id)) {
			$template = $this->prar_rating_get_template('post-reviews');
		}
		return $template;
	}
	
	// Bloque l'ajout d'une review pour un utilisateur non connecté
	function prar_rating_review_option_comment_registration($value, $option) {
		$post_id = get_the_ID();
		if ($post_id && $this->prar_rating_review_is_post_type_to_be_reviewed($post_id)) {
			return '1';
		}
		return $value;
	}
	
	// Bloque la fonctionnalité de réponse sur les reviews (à part en admin)
	function prar_rating_review_comment_reply_link($html_link, $args, $comment, $post) {
		// error_log('html_link = ' . print_r($html_link, true));
		if ($this->prar_rating_review_is_post_type_to_be_reviewed($post->ID)) {
			return '';
		}
		return $html_link;
	}
	
	// Bloque l'ajout d'un commentaire pour un user qui a déjà posté une review sur le post
	function prar_rating_review_comments_open($open, $post_id) {
		if ($this->prar_rating_review_is_post_type_to_be_reviewed($post_id)) {
			// regarde si le user connecté a déjà laissé un commentaire
			if (self::prar_rating_review_has_user_already_commented($post_id)) {
				return false;
			}
		}
		return $open;
	}
	
	static function prar_rating_review_has_user_already_commented($post_id = null) {
		if (! $post_id) {
			$post_id = get_the_ID();
		}
		// regarde si le user connecté a déjà laissé un commentaire
		$user_id = get_current_user_id();
		if ($user_id) {
			$comments = get_comments(array(
				'post_id'	=> $post_id,
				'user_id'	=> $user_id,
				'count'		=> true,
				'status'	=> 'all',
				// 'status'	=> array('hold', 'approved', 'unapproved', 'spam'),
			));
			if ($comments) {
				return true;
			}
		}
		return false;
	}
		
	// Ajoute le widget set_note dans la zone de commentaire (new review)
	function prar_rating_review_custom_comment_form_field_comment($comment_field) {
		$post_id = get_the_ID();
		if ($this->prar_rating_review_is_post_type_to_be_reviewed($post_id)) {
			$html_widget = $this->prar_sc_set_rating_for_post(array(
				'update_after_vote'	=> true,
				'post_id'			=> $post_id,
				'save_immediately' 	=> false,
				'size'				=> $this->options['taille_stars'],
				'step'				=> $this->options['step_set_note'],
				),
				'', PRAR_NONCE_SET_NOTE
			); 
			$html_widget = '<div class="prar-rating-review-widget"><span class="prar-rating-label-note">' . _x('Votre note :', 'Front add review title widget', 'post-rating-and-review') . '</span>' . $html_widget . '</div>';
			$retour = prar_rating_database::prar_rating_database_get_note_for_post($post_id, get_current_user_id(), false, true);
			$html_widget .= '<input type="hidden" name="prar_rating_review_comment_note-' . $post_id . '" value="' . $retour['note'] . '">';
			$comment_field = $html_widget . $comment_field;
		}
		return $comment_field;
	}
	
	// Contrôle lorsque validation du formulaire de comment par l'utilisateur
	function prar_rating_review_pre_comment_approved($approved, $commentdata) {
		$post_id = isset($_POST['comment_post_ID']) ? sanitize_key($_POST['comment_post_ID']) : false;
		if ($post_id && $this->prar_rating_review_is_post_type_to_be_reviewed($post_id) && ( ! is_admin() || wp_doing_ajax())) {
			// Vérifie la présence de l'input hidden avec la note
			$hidden_field = isset($_POST['prar_rating_review_comment_note-' . $post_id]) ? (float) sanitize_text_field($_POST['prar_rating_review_comment_note-' . $post_id]) : 'non-trouve';
			if ($hidden_field == 0 ) {
				return new WP_Error( 'rating_notfound', _x('La sélection d\'une note est nécessaire', 'Front error message add review', 'post-rating-and-review'), 450 );
			}
		}
		return $approved;
	}
	
	// Sauvegarde de la note
	function prar_rating_review_wp_insert_comment($comment_id, $comment) {
		// error_log('prar_rating_review_wp_insert_comment - comment = ' . var_export($comment, true));
		$post_id = (isset($_POST['comment_post_ID']) && is_numeric($_POST['comment_post_ID'])) ? sanitize_key($_POST['comment_post_ID']) : false;
		$id_ligne_table_log = 0;
		if ($post_id && isset($_POST['prar_rating_review_comment_note-' . $post_id])) {
			$note = sanitize_text_field($_POST['prar_rating_review_comment_note-' . $post_id]);
			if ($note > 0) {
				$user_id = $comment->user_id;
				// Enregistre la note dans les logs avec un statut false si comment->comment_approved == false
				$note_valide = ((int) $comment->comment_approved === 1 || $comment->comment_approved === 'approve') ? true : false;
				$id_ligne_table_log = prar_rating_database::prar_rating_database_save_note($post_id, $user_id, $note, null, null, $note_valide);
				if ($id_ligne_table_log) {
					// Sauvegarde l'id en meta du commentaire
					add_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, $id_ligne_table_log);
				}
			} else {
				// pas de note attribuée : crée quand même une méta avec id_ligne_table_log à 0 (permet de savoir que c'est une review et non un comment)
				add_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, -1);
			}
			// Si le commentaire est dans la file d'attente de la modération alors que le comment_content est vide alors valide directement la review
			if ($comment->comment_content == '' && $comment->comment_approved === '0') {
				wp_set_comment_status($comment->comment_ID, 'approve');
			} else {
				// Sauvegarde dans les metas du post si le commentaire est directement approved
				if ((int) $comment->comment_approved === 1 || $comment->comment_approved === 'approve') {
					if ($id_ligne_table_log > 0) {
						$retour_note_globale = prar_rating_database::prar_rating_database_get_note_for_post($post_id);
						$this->prar_rating_save_metas_in_post($post_id, $retour_note_globale['number_of_notes'], $retour_note_globale['note']);							
					}
					do_action('prar_rating_review_after_review_validation', $post_id, $id_ligne_table_log, $comment);
				}
			}
		}
	}
	
	// Valide la note (note_valide = true) lorsque le commentaire est approuvé
	function prar_rating_review_wp_set_comment_status($comment_id, $new_status) {
		// Regarde si une note est liée au comment
		$id_ligne_table_log = (int) get_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, true);
		if ($id_ligne_table_log) {
			$comment = get_comment($comment_id);
			switch ($new_status) {
				case 'approve' :
				case '1' :
					$post_id = (int) $comment->comment_post_ID;
					if ($id_ligne_table_log != -1) {	// -1 si review sans note
						// Passe la ligne note en note_valide = false
						prar_rating_database::prar_rating_database_update_note_valide_by_log_id($id_ligne_table_log, true);
						// met à jour les moyennes et cumul dans le post
						$retour_note_globale = prar_rating_database::prar_rating_database_get_note_for_post($post_id);
						$this->prar_rating_save_metas_in_post($post_id, $retour_note_globale['number_of_notes'], $retour_note_globale['note']);
					}
					do_action('prar_rating_review_after_review_validation', $post_id, $id_ligne_table_log, $comment);
					break;				
				case 'spam' :
				case 'trash' :
				case 'hold' :
				case '0' :
				default :
					if ($id_ligne_table_log != -1) {	// -1 si review sans note
						// Passe la ligne note en note_valide = false
						prar_rating_database::prar_rating_database_update_note_valide_by_log_id($id_ligne_table_log, false);
					}
					break;
				
			}
		}
	}
	
	// Supprime la note lorsque le comment est supprimé
	function prar_rating_review_delete_comment($comment_id, $comment) {
		// Regarde si une note est liée au comment
		$id_ligne_table_log = get_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, true);
		if ($id_ligne_table_log	> 0) {
			// supprime la note dans la table log
			prar_rating_database::prar_rating_database_delete_vote_by_log_id($id_ligne_table_log);
			// met à jour les moyennes et cumul dans le post
			$post_id = $comment->comment_post_ID;
			$retour_note_globale = prar_rating_database::prar_rating_database_get_note_for_post($post_id);
			$this->prar_rating_save_metas_in_post($post_id, $retour_note_globale['number_of_notes'], $retour_note_globale['note']);			
		}
	}
	
	// Ajoute la note attribuée par le user dans la liste des commentaires
	function prar_rating_review_front_comment_text($comment_text, $comment, $args) {
		if (! $comment) {		// comment est null lorsque le filtre est appelé par la fonction check_comments() qui est utilisée lors de la sauvegarde d'un comment
			return $comment_text;
		}
		if (is_admin()) {
			$screen = get_current_screen();
			if ($screen && $screen->base == 'edit-comments') {
				return $comment_text;
			}			
		}
		$id_ligne_table_log = get_comment_meta($comment->comment_ID, PRAR_RATING_COMMENT_META_NAME, true);
		if ($id_ligne_table_log > 0) {
			// Génère le widget display_note avec la note attribuée
			$tab_log = prar_rating_database::prar_rating_database_get_note_by_log_id($id_ligne_table_log);
			if ($tab_log) {
				$html_widget = $this->prar_sc_display_rating_for_post(array(
					'post_id'			=> $tab_log['post_id'],
					'user_id'			=> $tab_log['user_id'],
					'size'				=> 16,
					'note'				=> $tab_log['note'],
					'display_compteurs'	=> false,
					),
					'', 'prar_display_rating_for_post'
				);
				$note_texte = $this->prar_rating_get_average_note_with_max($tab_log['note'], 'user');
				$comment_text = '<div class="prar-rating-comment-list-item">' . $html_widget . $note_texte . '<span class="text_comment">' . $comment_text . '</span></div>';
			}
		}
		// Fonction de modification / suppression d'un avis par l'auteur de l'avis
		if ($comment->user_id == get_current_user_id() && $this->prar_rating_can_review_be_updated($comment) && ! is_admin() ) {
			$comment_text .= '<div class="prar_action_buttons">';
			if ($this->options['reviews_comment_modifiable_auteur']) {
				$comment_text .= sprintf('<span><input type="button" id="prar_review_modify" value="%s"></span>', _x('Modifier', 'Front - Author actions', 'post-rating-and-review'));
			}
			if ($this->options['reviews_comment_supprimable_auteur']) {
				$nonce_ajax = wp_create_nonce(PRAR_NONCE_DELETE_REVIEW);
				$comment_text .= sprintf('<span><input type="button" id="prar_review_delete" value="%s" data-comment_id="%d"></span>', _x('Supprimer', 'Front - Author actions', 'post-rating-and-review'), $comment->comment_ID);
				$comment_text .= sprintf('<input type="hidden" name="prar_nonce_ajax_delete" id="prar_nonce_ajax_delete" value="%s">', $nonce_ajax);
				$comment_text .= sprintf('<div id="prar_update_review_popin_confirm_delete" style="display: none;" title="%s"><div>%s</div></div>', 
					_x('Suppression d\'un avis', 'Front reviews list - delete review title', 'post-rating-and-review'),
					_x('Confirmez-vous la suppression de votre avis ?', 'Front reviews list - delete review confirm message', 'post-rating-and-review'));
				$comment_text .= sprintf('<div id="prar_delete_review_popin_error" style="display: none;" title="%s"><div id="prar_delete_review_popin_error_message"></div></div>',
					_x('Une erreur est survenue', 'Front reviews list - update review title - Error', 'post-rating-and-review'));
			}
			if ($this->options['reviews_comment_modifiable_auteur']) {
				// Popin de modification d'une review
				$comment_text .= $this->prar_rating_get_update_popin($comment);
			}
			$comment_text .= '</div>';
		}
		return $comment_text;
	}
	
	function prar_rating_review_is_post_type_to_be_reviewed($post_id = false) {
		if (! $post_id) {
			$post_id = get_the_ID();
		}
		if (in_array(get_post_type($post_id), $this->options['reviews_post_types'])) {
			return true;
		}
		return false;
	}
	
	static public function prar_rating_review_is_user_able_to_review() {
		if (! is_user_logged_in()) {
			return 'not-logged-in';
		}
		if (self::prar_rating_review_has_user_already_commented()) {
			return 'already-commented';
		}
		return 'yes';
	}
	
	// Une review ne peut être modifiée si une réponse a déjà été apportée
	function prar_rating_can_review_be_updated($comment) {
		$can_be_updated = true;
		$children = $comment->get_children(array('count' => true));
		if ($children) {
			$can_be_updated = false;
		}
		return apply_filters('prar_rating_can_review_be_updated', $can_be_updated, $comment);
	}
	function prar_rating_ajax_delete_review() {
		if (isset($_POST['comment_id'], $_POST['security']) && is_numeric($_POST['comment_id']) ) {
			$comment_id			= (int) sanitize_key($_POST['comment_id']);
			$nonce_ajax 		= sanitize_text_field($_POST['security']);
			$user_id			= get_current_user_id();
		} else {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Problème avec les variables POST.', 'Front ajax error message', 'post-rating-and-review')));
			die();
		}
		// Vérifie le nonce_ajax
		if (($erreur = $this->prar_rating_validation_nonce($nonce_ajax, PRAR_NONCE_DELETE_REVIEW)) !== true) {
			echo json_encode(array('status' => 'erreur', 'message' => $erreur['message']));
			die();
		}
		$comment_data = get_comment($comment_id, ARRAY_A);
		// Vérifie que le user connecté est bien l'auteur du commentaire
		if ($user_id != $comment_data['user_id']) {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Seul l\'auteur de l\'avis peut supprimer son avis.', 'Front ajax error message', 'post-rating-and-review')));
			die();						
		}
		$id_ligne_table_log = (int) get_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, true);
		// Supprime le commentaire (force delete)
		$retour = wp_delete_comment($comment_id, true);
		if ($retour) {
			// Supprime la note
			if ($id_ligne_table_log != -1) {
				prar_rating_database::prar_rating_database_delete_vote_by_log_id($id_ligne_table_log);
			}
			echo json_encode(array('status' => 'succes', 'message' => _x('Votre avis a été supprimé.', 'Front ajax error message', 'post-rating-and-review')));
			die();									
		} else {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Une erreur s\'est produite lors de la suppression. Veuillez réessayer plus tard.', 'Front ajax error message', 'post-rating-and-review')));
			die();									
		}
	}
	
	function prar_rating_get_update_popin($comment) {
		$html_widget = $this->prar_rating_review_custom_comment_form_field_comment('');
		ob_start();
		require $this->prar_rating_get_template('part-update-review');
		$retour = ob_get_clean();
		return $retour;
	}
	
	function prar_rating_ajax_update_review() {
		if (isset($_POST['comment_id'], $_POST['note'], $_POST['comment'], $_POST['security']) && is_numeric($_POST['note']) && is_numeric($_POST['comment_id']) ) {
			$note				= (float) sanitize_text_field($_POST['note']);
			$comment_id			= (int) sanitize_key($_POST['comment_id']);
			$comment_content	= wp_filter_post_kses($_POST['comment']);
			$nonce_ajax 		= sanitize_text_field($_POST['security']);
			$user_id			= get_current_user_id();
		} else {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Problème avec les variables POST.', 'Front ajax error message', 'post-rating-and-review')));
			die();
		}
		// Vérifie le nonce_ajax
		if (($erreur = $this->prar_rating_validation_nonce($nonce_ajax, PRAR_NONCE_UPDATE_REVIEW)) !== true) {
			echo json_encode(array('status' => 'erreur', 'message' => $erreur['message']));
			die();
		}		
		// Vérifie si comment obligatoire et non renseigné
		if ( $this->options['reviews_comment_obligatoire'] && trim($comment_content) == '' ) {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Veuillez saisir un texte.', 'Front ajax error message', 'post-rating-and-review')));
			die();			
		}
		// Vérifie si rating obligatoire et non renseigné
		if ( $this->options['reviews_rating_obligatoire'] && $note == 0 ) {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Veuillez indiquer une note.', 'Front ajax error message', 'post-rating-and-review')));
			die();			
		}

		$comment_data = get_comment($comment_id, ARRAY_A);

		// Vérifie que le user connecté est bien l'auteur du commentaire
		if ($user_id != $comment_data['user_id']) {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Seul l\'auteur de l\'avis peut effectuer une modification.', 'Front ajax error message', 'post-rating-and-review')));
			die();						
		}
		
		// Contrôles OK => effectue la sauvegarde du commentaire
		$comment_data['comment_content'] = $comment_content;
		$comment_data['comment_date'] = current_time('mysql');
		$comment_data['comment_date_gmt'] = get_gmt_from_date($comment_data['comment_date']);
		$comment_data['comment_author_IP'] = $_SERVER['REMOTE_ADDR'];
		$comment_data['comment_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : $comment_data['comment_agent'];
		
		// Ajoute un filtre pour permettre la mise à jour d'une review uniquement en modifiant la note (sinon la fonction wp_allow_comment génère une erreur)
		add_filter('duplicate_comment_id', array($this, 'prar_rating_review_duplicate_comment_id'), 10, 2);
		$approved = wp_allow_comment($comment_data);
		$comment_data['comment_approved'] = $approved;
		
		$retour = wp_update_comment($comment_data);
		
		if ($retour) {
			// Sauvegarde la note
			$id_ligne_table_log = (int) get_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, true);
			
			// Sauvegarde la note (update ou insert géré par la méthode prar_rating_database_save_note
			$update_post_meta = false;
			if ($note != 0) {
				$note_valide = ($approved === 1 || $approved === 'approve') ? true : false;
				$id_ligne_log = prar_rating_database::prar_rating_database_save_note($comment_data['comment_post_ID'], $user_id, $note, null, null, $note_valide);
				if ($id_ligne_log) {
					$update_post_meta = true;
					if ($id_ligne_table_log == -1) {
						// Il n'y avait pas encore de note attribuée par le user => modifie la méta du comment
						update_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, $id_ligne_log);
					}
				} else {
					// Erreur de sauvegarde de la note
					echo json_encode(array('status' => 'erreur', 'message' => _x('Erreur lors de la sauvegarde. Veuillez réessayer plus tard.', 'Front ajax error message', 'post-rating-and-review')));
					die();														
				}
			} else {
				if ($id_ligne_table_log != -1) {
					// Il y avait une note attribuée => la supprime puis modifie la méta du comment
					prar_rating_database::prar_rating_database_delete_vote_by_log_id($id_ligne_table_log);
					update_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, -1);
					$update_post_meta = true;
				}
			}
			if ($update_post_meta) {
				$retour_note_globale = prar_rating_database::prar_rating_database_get_note_for_post($comment_data['comment_post_ID']);
				$this->prar_rating_save_metas_in_post($comment_data['comment_post_ID'], $retour_note_globale['number_of_notes'], $retour_note_globale['note']);
			}
		} else {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Erreur lors de la sauvegarde. Veuillez réessayer plus tard.', 'Front ajax error message', 'post-rating-and-review')));
			die();									
		}
		// Traitement terminé : prépare le retour ajax 
		echo json_encode(array(
			'status'		=> 'succes',
			'message'		=> _x('Votre avis a été modifié.', 'Front ajax error message', 'post-rating-and-review'),
			'redirect_to'	=> get_comment_link( $comment_id )
		));
		die();
	}
	
	function prar_rating_ajax_save_new_review() {
		if (isset($_POST['comment_post_ID'], $_POST['comment_parent'], $_POST['comment'], $_POST['security'], $_POST['note']) && is_numeric($_POST['note']) && is_numeric($_POST['comment_post_ID']) ) {
			$note				= (float) sanitize_text_field($_POST['note']);
			$post_id			= (int) sanitize_key($_POST['comment_post_ID']);
			$nonce_ajax 		= sanitize_text_field($_POST['security']);
		} else {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Problème avec les variables POST.', 'Front ajax error message', 'post-rating-and-review')));
			die();
		}
		// Vérifie le nonce_ajax
		if (($erreur = $this->prar_rating_validation_nonce($nonce_ajax, PRAR_NONCE_SET_NOTE)) !== true) {
			echo json_encode(array('status' => 'erreur', 'message' => $erreur['message']));
			die();
		}
		$_POST['prar_rating_review_comment_note-' . $post_id] = $note;

		$comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
		
		if ( is_wp_error( $comment ) ) {
			$data = (int) $comment->get_error_data();
			$message = $comment->get_error_message();
			echo json_encode(array('status' => 'erreur', 'message' => $message));
			die();
		}
		// Traitement terminé : prépare le retour ajax 
		echo json_encode(array(
			'status'		=> 'succes',
			'message'		=> _x('Votre avis a été enregistré.', 'Front ajax error message', 'post-rating-and-review'),
			'redirect_to'	=> get_comment_link( $comment )
		));
		die();		
	}
	
	function prar_rating_review_duplicate_comment_id($dupe_id, $comment_data) {
		if ($comment_data['comment_ID'] == $dupe_id) {
			// permet un même comment_content quand l'auteur modifie sa review
			return false;
		}
		return $dupe_id;
	}
	
	function prar_rating_review_display_rating_on_archive($title, $post_id) {
		if ($this->prar_rating_review_is_post_type_to_be_reviewed($post_id)) {
			if ( (is_archive() && in_the_loop() ) || ( is_home() && in_the_loop() ) ) {
				// Génère le widget display_note avec la note attribuée
				$html_widget = $this->prar_sc_display_rating_for_post(array(
					'post_id'			=> $post_id,
					'size'				=> 16,
					'display_compteurs'	=> true,
					),
					'', 'prar_display_rating_for_post'
				);
				$title .= $html_widget;
				
			}
		}
		return $title;
	}
	function prar_rating_review_display_rating_on_single($content) {
		if ($this->prar_rating_review_is_post_type_to_be_reviewed()) {
			global $wp_query;
			if ( ! is_admin() && is_single() ) {
				// Génère le widget display_note avec la note attribuée
				$html_widget = $this->prar_sc_display_rating_for_post(array(
					// 'post_id'			=> $post_id,
					'size'				=> 16,
					'display_compteurs'	=> true,
					),
					'', 'prar_display_rating_for_post'
				);
				$content = $html_widget . $content;
				
			}
		}
		return $content;
	}
}