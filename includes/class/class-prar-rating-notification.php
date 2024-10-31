<?php

class prar_rating_notification extends prar_rating {
	
	
	function __construct() {
		$this->options = get_option('prar_plugin_options');
		
		// Gestion des notifications sur les reviews : filter sur la notification standard WP au post author
		add_filter('notify_post_author', array($this, 'prar_rating_notification_new_comment_notify'), 999, 2);
		
		// Gestion des notifications sur les notes en stand-alone
		if ($this->options['send_rating_notif_to_post_author']) {
			// Hook sur la fonction d'envoi d'email schédulée avec wp_schedule_single_event
			add_action('hook_prar_rating_notification_send_email_to_post_author', array($this, 'prar_rating_notification_send_email_to_post_author'));
			// Action quand une note a été sauvegardée via ajax (pas le cas quand il s'agit d'une review)
			add_action('prar_rating_after_save_note', array($this, 'prar_rating_notification_rating_validation'), 10, 6);
		}		
	}

	// -----------------------------------------
	// GESTION DES NOTIFICATIONS SUR LES REVIEWS
	// -----------------------------------------
	
	// Override notification std WP
	function prar_rating_notification_new_comment_notify($maybe_notif, $comment_id) {
		// Surcharge le paramètrage des comments WP pour les post types en review
		$comment = get_comment($comment_id);
		if ($comment) {
			$post_id = $comment->comment_post_ID;
			$review_object = new prar_rating_review();
			if ($review_object->prar_rating_review_is_post_type_to_be_reviewed($post_id)) {
				if ($this->options['send_review_notif_to_post_author']) {
					$maybe_notif = true;
					// filters pour modifier le contenu du mail de notification standard de WP
					add_filter('comment_notification_text', array($this, 'prar_rating_notification_comment_notification_text'), 999, 2);
					add_filter('comment_notification_subject', array($this, 'prar_rating_notification_comment_notification_subject'), 999, 2);
					add_filter('comment_notification_headers', array($this, 'prar_rating_notification_comment_notification_headers'), 999, 2);
				} else {
					$maybe_notif = false;
				}
			}
		}
		return $maybe_notif;		
	}
	
	function prar_rating_notification_comment_notification_text($notification_text, $comment_id) {
		$comment = get_comment($comment_id);
		if ($comment) {
			$id_ligne_table_log = (int) get_comment_meta($comment_id, PRAR_RATING_COMMENT_META_NAME, true);
			$post = $id_ligne_table_log ? get_post( $comment->comment_post_ID ) : false;
			if ($post && ! in_array($comment->comment_type, array('trackback', 'pingback'))) {
				$blogname        = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
				$comment_content = wp_specialchars_decode( $comment->comment_content );
				$template_text = $this->prar_rating_notification_get_email_txt_template('new-review');
				if ($template_text) {
					$tags_a_remplacer = array('{blogname}', '{post_title}', '{review_author}', '{review_author_email}', '{review_rating}', '{review_content}', '{overall_rating}', '{number_of_reviews}', '{link_to_reviews}');
					$valeurs = array($blogname, $post->post_title, $comment->comment_author, $comment->comment_author_email);
					if ($id_ligne_table_log != -1) {
						$tab_note = prar_rating_database::prar_rating_database_get_note_by_log_id($id_ligne_table_log);
						$valeurs[] = strip_tags($this->prar_rating_get_average_note_with_max($tab_note['note']));
					} else {
						$valeurs[] = _x('pas de note', 'Review email content', 'post-rating-and-review');
					}
					$valeurs[] = $comment_content;
					$tab_note_moyenne = prar_rating_database::prar_rating_database_get_note_for_post($post->ID);
					$count_reviews = get_comments(array(
						'post_id'		=> $post->ID,
						'count'			=> true,
						'status'		=> 'approve',
						'type'			=> 'comment',
						'hierarchical'	=> false,
					));
					$valeurs[] = strip_tags($this->prar_rating_get_average_note_with_max($tab_note_moyenne['note'], 'average'));
					$valeurs[] = $count_reviews;
					$valeurs[] = get_permalink( $comment->comment_post_ID ) . '#comments';
					$notification_text = str_replace($tags_a_remplacer, $valeurs, $template_text);					
				}
			}
		}
		return $notification_text;
	}
	
	function prar_rating_notification_comment_notification_subject($notification_subject, $comment_id) {
		$comment = get_comment($comment_id);
		if ($comment) {
			$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			$post = get_post( $comment->comment_post_ID );
			if ($post) {
				$subject = sprintf( _x( '[%1$s] Nouvel avis sur : "%2$s"', 'Review email subject', 'post-rating-and-review' ), $blogname, $post->post_title );
				$notification_subject = $subject;
			}
		}		
		return apply_filters('prar_rating_notification_new_review_email_subject',$notification_subject, $comment);
	}
	
	function prar_rating_notification_comment_notification_headers($notification_headers, $comment_id) {
		$comment = get_comment($comment_id);
		return apply_filters('prar_rating_notification_new_review_email_header',$notification_headers, $comment);
	}


	// -------------------------------------------------------------------
	// GESTION DES NOTIFICATIONS SUR L'ATTRIBUTION DE NOTES EN STAND-ALONE
	// -------------------------------------------------------------------
	
	// Sauvegarde d'une note en ajax => déconnecté des reviews
	function prar_rating_notification_rating_validation($post_id, $user_id, $note, $external_id, $retour_note_globale, $id_ligne_log) {
		wp_schedule_single_event( time() + 60, 'hook_prar_rating_notification_send_email_to_post_author', array($id_ligne_log));
	}
	
	// Génération de l'email de notification
	function prar_rating_notification_send_email_to_post_author($id_ligne_table_log = null) {
		if (! $id_ligne_table_log) {
			return false;
		}
		$details_note = prar_rating_database::prar_rating_database_get_note_by_log_id($id_ligne_table_log);
		if (! $details_note) {
			return false;
		}
		$post = get_post($details_note['post_id']);
		if (! $post) {
			return false;
		}		
		$template_text = $this->prar_rating_notification_get_email_txt_template('new-rating');
		if ($template_text) {
			$author_data = get_userdata($post->post_author);
			$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			$tab_note_moyenne = prar_rating_database::prar_rating_database_get_note_for_post($post->ID);
			$tags_a_remplacer = array('{blogname}', '{post_title}', '{rating}', '{overall_rating}', '{number_of_ratings}');
			$valeurs = array(
				$blogname, 
				$post->post_title, 
				strip_tags($this->prar_rating_get_average_note_with_max($details_note['note'])),
				strip_tags($this->prar_rating_get_average_note_with_max($tab_note_moyenne['note'], 'average')),
				$tab_note_moyenne['number_of_notes']
			);
			$content_email = str_replace($tags_a_remplacer, $valeurs, $template_text);

			$subject = sprintf( _x( '[%1$s] Nouvelle note sur : "%2$s"', 'Rating email subject', 'post-rating-and-review' ), $blogname, $post->post_title );
			$subject = apply_filters('prar_rating_notification_new_rating_email_subject', $subject, $blogname, $post, $details_note);

			$sender = 'ratings@' . preg_replace( '#^www\.#', '', wp_parse_url( network_home_url(), PHP_URL_HOST ) );
			$sender = apply_filters('prar_rating_notification_new_rating_email_sender', $sender, $blogname, $post, $details_note);

			$from = "From: \"$blogname\" <$sender>";
			$from = apply_filters('prar_rating_notification_new_rating_email_from', $from, $blogname, $post, $details_note);

			$message_headers = "$from\n" . 'Content-Type: text/plain; charset="' . get_option( 'blog_charset' ) . "\"\n";
			$message_headers = apply_filters('prar_rating_notification_new_rating_email_header', $message_headers, $blogname, $post, $details_note);
			
			wp_mail($author_data->user_email, wp_specialchars_decode($subject), $content_email, $message_headers);
			return true;
		}
	}
	
	
	// -----------------------
	// GESTION TEMPLATES EMAIL
	// -----------------------
	
	function prar_rating_notification_get_email_txt_template($template_name) {
		$nom_complet_template = 'email-author-notification-' . $template_name;
		$locale = get_locale();
		$template_avec_path = '';
		$stylesheet_directory = get_stylesheet_directory();
		// Regarde si la version locale existe dans le thème
		if (file_exists($stylesheet_directory . '/prar-rating/' . $nom_complet_template . '_' . $locale . '.txt')) {
			$template_avec_path = $stylesheet_directory . '/prar-rating/' . $nom_complet_template . '_' . $locale . '.txt';
		} elseif (file_exists($stylesheet_directory . '/prar-rating/' . $nom_complet_template . '.txt')) {
			$template_avec_path = $stylesheet_directory . '/prar-rating/' . $nom_complet_template . '.txt';
		} elseif (file_exists(PRAR_RATING_PLUGIN_PATH . 'includes/template/' . $nom_complet_template . '_' . $locale . '.txt')) {
			$template_avec_path = PRAR_RATING_PLUGIN_PATH . 'includes/template/' . $nom_complet_template . '_' . $locale . '.txt';
		} else {
			$template_avec_path = PRAR_RATING_PLUGIN_PATH . 'includes/template/' . $nom_complet_template . '_en_US.txt';
		}
		return file_get_contents($template_avec_path);		
	}
		
	
}