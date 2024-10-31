<?php
class prar_rating_database {

	function __construct() {
		// suppression des votes quand un post est supprimé
		add_action('delete_post', array($this, 'prar_rating_database_delete_votes'));
	}
	
	// Enregistre une note
	static function prar_rating_database_save_note($post_id, $user_id, $note, $external_id = null, $date = null, $note_valide = 1) {
		global $wpdb;
		$id_ligne_log = 0;

		$retour_update = $wpdb->update(
			PRAR_RATING_TABLE_NAME, array(
				'post_id' 		=> $post_id,
				'user_id' 		=> $user_id,
				'note'    		=> $note,
				'date'    		=> ($date ? $date : current_time('mysql')),
				'external_id'	=> $external_id,
				'note_valide'	=> $note_valide
			),
			array(
				'post_id' => $post_id,
				'user_id' => $user_id
			),
			array('%d', '%d', '%f', '%s', '%d'),
			array('%d', '%d')
		);
		
		if (! $retour_update) {
			$retour_insert = $wpdb->insert(
				PRAR_RATING_TABLE_NAME, array(
				'post_id' 		=> $post_id,
				'user_id' 		=> $user_id,
				'note'    		=> $note,
				'date'    		=> ($date ? $date : current_time('mysql')),
				'external_id'	=> $external_id,
				'note_valide'	=> $note_valide				
				), array('%d', '%d', '%f', '%s', '%d')
			);
			if ($retour_insert) {
				$id_ligne_log = $wpdb->insert_id;
			}
		} else {
			// Récupère l'id de la ligne modifiée
			$requete = "SELECT id FROM " . PRAR_RATING_TABLE_NAME . " WHERE post_id=%d AND user_id=%d";
			$result = $wpdb->get_results($wpdb->prepare($requete, array($post_id, $user_id)));
			if ($result) {
				$id_ligne_log = $result[0]->id;
			}
		}
		
		if ($retour_update || $retour_insert) {
			return $id_ligne_log;
		}
		return false;
	}

	// Renvoi la note moyenne pour un post
	static function prar_rating_database_get_note_for_post($post_id = false, $user_id = false, $external_id = false, $include_nonvalide = false, $number_of_decimals = 1) {
		global $wpdb;

		if (! $post_id && ! $user_id && ! $external_id) {
			return array(
				'number_of_notes'	=> 0,
				'sum_notes'			=> 0,
				'note'				=> 0,
				'erreur'			=> _x('Au moins 1 paramètre est nécessaire', 'Internal error message', 'post-rating-and-review'),
			);
		}
		
		$array_where = array();
		
		if ($include_nonvalide == false) {
			// Ne prend que les notes valides
			$array_where[] = 'note_valide=%d';
			$array_parametres[] = true;
		}
		
		$requete = "
			SELECT SUM(note) as total_notes, COUNT(note) as number_of_notes 
			FROM " . PRAR_RATING_TABLE_NAME . "
		";
		if ($post_id) {
			$array_where[] = 'post_id=%d';
			$array_parametres[] = $post_id;
		}
		if ($user_id) {
			$array_where[] = 'user_id=%d';
			$array_parametres[] = $user_id;
		}
		if ($external_id) {
			$array_where[] = 'external_id=%d';
			$array_parametres[] = $external_id;
		}
		$requete .= " WHERE " . implode(' AND ', $array_where);
		
		$result = $wpdb->get_results(
			$wpdb->prepare($requete, $array_parametres)
		);
		
		return array(
			'number_of_notes'	=> (int) $result[0]->number_of_notes,
			'sum_notes'			=> (int) $result[0]->total_notes,
			'note'				=> $result[0]->number_of_notes > 0 ? round((float) $result[0]->total_notes / (int) $result[0]->number_of_notes, $number_of_decimals) : 0
		);	
	}
	
	static function prar_rating_database_get_note_by_log_id($log_id) {
		global $wpdb;

		$requete = "SELECT * FROM " . PRAR_RATING_TABLE_NAME . " WHERE id=%d";
		$result = $wpdb->get_results($wpdb->prepare($requete, array($log_id)));
		if ($result) {
			return array(
				'post_id'		=> (int) $result[0]->post_id,
				'user_id'		=> (int) $result[0]->user_id,
				'note'			=> $result[0]->note,
				'date'			=> $result[0]->date,
				'external_id'	=> $result[0]->external_id,
				'note_valide'	=> $result[0]->note_valide,
			);	
		}
		return false;		
	}
	
	static function prar_rating_database_update_note_valide_by_log_id($log_id, $new_status) {
		global $wpdb;
		$retour_update = $wpdb->update(
			PRAR_RATING_TABLE_NAME, array(
				'note_valide'	=> $new_status
			),
			array(
				'id' => $log_id,
			),
			array('%d'),
			array('%d')
		);
		return $retour_update;		
	}
	
	// Renvoi l'ensemble des notes pour un post / user ...
	static function prar_rating_database_get_all_notes($post_id = false, $user_id = false, $external_id = false) {
		global $wpdb;

		if (! $post_id && ! $user_id && ! $external_id) {
			return array();
		}
		
		$array_where = array();
		
		$requete = "
			SELECT * 
			FROM " . PRAR_RATING_TABLE_NAME . "
		";

		// Ne prend que les notes valides
		$array_where[] = 'note_valide=%d';
		$array_parametres[] = true;

		if ($post_id) {
			$array_where[] = 'post_id=%d';
			$array_parametres[] = $post_id;
		}
		if ($user_id) {
			$array_where[] = 'user_id=%d';
			$array_parametres[] = $user_id;
		}
		if ($external_id) {
			$array_where[] = 'external_id=%d';
			$array_parametres[] = $external_id;
		}
		$requete .= " WHERE " . implode(' AND ', $array_where);
		
		$results = $wpdb->get_results(
			$wpdb->prepare($requete, $array_parametres)
		);
		$tab_retour = array();
		foreach ($results as $result) {
			$tab_retour[] = array(
				'post'			=> get_post($result->post_id),
				'user_id'		=> $result->user_id,
				'note'			=> $result->note,
				'external_id'	=> $result->external_id,
				'date'			=> $result->date,
			);
		}
		return $tab_retour;
	}
	
	static function prar_rating_database_get_log_ids_by_note($note, $post_id = null) {
		global $wpdb;
		
		if (! $post_id) {
			$post_id = get_the_ID();
		}
		if (! $post_id) {
			return array();
		}
		
		$array_where = array();
		
		$requete = "
			SELECT id 
			FROM " . PRAR_RATING_TABLE_NAME . "
		";
		$array_where[] = 'post_id=%d';
		$array_parametres[] = $post_id;
		$array_where[] = 'note>=%d AND note<%d';
		$array_parametres[] = $note;
		$array_parametres[] = $note + 1;
		$requete .= " WHERE " . implode(' AND ', $array_where);
		
		$results = $wpdb->get_results(
			$wpdb->prepare($requete, $array_parametres)
		);
		$tab_retour = array();
		foreach ($results as $result) {
			$tab_retour[] = $result->id;
		}
		return $tab_retour;
	}
	
	function prar_rating_database_delete_votes($post_id) {
		global $wpdb;
		
		// Supprime les notes pour le post dans la table log
		$wpdb->delete(PRAR_RATING_TABLE_NAME,
					array('post_id' => $post_id),
					array('%d')
		);
	}
	
	static function prar_rating_database_delete_vote_by_log_id($log_id) {
		global $wpdb;
		
		// Supprime les notes pour le post dans la table log
		$wpdb->delete(PRAR_RATING_TABLE_NAME,
					array('id' => $log_id),
					array('%d')
		);
	}
	
	static function prar_rating_database_delete_all_votes() {
		global $wpdb;
		
		// sleep(5);
		// return;
		// 1 - delete custom meta in comment_meta
		$wpdb->delete($wpdb->prefix . 'commentmeta',
					array('meta_key' => PRAR_RATING_COMMENT_META_NAME),
					array('%s')
		);
		
		// 2 - delete custom metas in post objects
		$options = get_option('prar_plugin_options');
		$bool_save_note_in_meta = $options['save_note_dans_post'];
		if ($bool_save_note_in_meta) {
			$nom_chp_nb_notes = $options['nom_champ_nb_notes'];
			$nom_chp_average_note = $options['nom_champ_note_moyennne'];
			$type_chp = $options['type_champ_dans_post'];
			if ($type_chp == 'acf') {
				// pour les acfs, supprime les meta avec field (_nomdu champ)
				$wpdb->delete($wpdb->prefix . 'postmeta',
							array('meta_key' => '_' . $nom_chp_nb_notes),
							array('%s')
				);
				$wpdb->delete($wpdb->prefix . 'postmeta',
							array('meta_key' => '_' . $nom_chp_average_note),
							array('%s')
				);
			}
			$wpdb->delete($wpdb->prefix . 'postmeta',
						array('meta_key' => $nom_chp_nb_notes),
						array('%s')
			);
			$wpdb->delete($wpdb->prefix . 'postmeta',
						array('meta_key' => $nom_chp_average_note),
						array('%s')
			);	
		}
		
		// 3 - Supprime toutes les lignes dans la table log note
		$delete = $wpdb->query("TRUNCATE TABLE " . PRAR_RATING_TABLE_NAME);
	}
	
	static function prar_rating_database_get_number_of_vote_by_note($post_id) {
		global $wpdb;

		if (! $post_id ) {
			return array();
		}
		// Utilise la fonction FLOOR pour que 4.9 soit classée dans les 4 au lieu de 5 avec ROUND(note, 0)
        $votes_per_note = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT FLOOR(note) as group_note, COUNT(note) as number_of_notes, SUM(note) as total_notes
                FROM " . PRAR_RATING_TABLE_NAME . "
                WHERE post_id=%d AND note_valide=%d
                GROUP BY group_note
                ORDER BY group_note DESC
                ", $post_id, true
            )
        );
		
		// Ajoute au tableau de résultat les notes non existantes
		$options = get_option('prar_plugin_options');
		$note_max = $options['note_max'];
		$tab_retour = array();
		$note_totale = 0;
		$number_of_notes = 0;
		for ($i = $note_max; $i >= 0; $i--) {
			$bool_trouve = false;
			foreach ($votes_per_note as $vote_per_note) {
				if ($vote_per_note->group_note == $i) {
					$tab_retour[] = array(
						'note'				=> $i,
						'number_of_notes'	=> $vote_per_note->number_of_notes,
						'total_notes'		=> $vote_per_note->total_notes,
					);
					$bool_trouve = true;
					// $note_totale += $vote_per_note->number_of_notes * $vote_per_note->total_notes;
					$note_totale += $vote_per_note->total_notes;
					$number_of_notes += $vote_per_note->number_of_notes;
					continue;
				}
			}
			if (! $bool_trouve) {
				$tab_retour[] = array(
					'note'				=> $i,
					'number_of_notes'	=> 0,
					'total_notes'		=> 0,
				);
			}
		}
		
		$average_note = $number_of_notes == 0 ? 0 : round($note_totale / $number_of_notes, 1);
		
		return array('note_totale' => $note_totale, 'number_of_notes' => $number_of_notes, 'note' => $average_note, 'results' => $tab_retour);
		
	}
}


