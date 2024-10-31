<?php
/*
Plugin Name: Post rating and review
Plugin URI: https://fr.wordpress.org/plugins/post-rating-and-review/
Description: Rating and reviews for your posts & custom posts
Version: 1.3.4
Author: Loïc Bourgès
Author URI: https://profiles.wordpress.org/bourgesloic/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly!
}
global $wpdb;

define('PRAR_PLUGIN_VERSION', '1.3.4');
define('PRAR_RATING_TABLE_NAME', $wpdb->prefix . 'prar_ratings_log');
define('PRAR_NONCE_SET_NOTE', 'prar_rating_set_note');
define('PRAR_NONCE_UPDATE_REVIEW', 'prar_rating_update_review');
define('PRAR_NONCE_DELETE_REVIEW', 'prar_rating_delete_review');
define('PRAR_RATING_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PRAR_RATING_COMMENT_META_NAME', 'prar_rating_log_id'); 

require_once __DIR__ . '/prar-rating-functions.php';
require_once __DIR__ . '/prar-rating-template.php';
require_once __DIR__ . '/prar-rating-plugins-compatibility.php';
require_once __DIR__ . '/includes/class/class-prar-rating-database.php';
require_once __DIR__ . '/includes/class/class-prar-rating-admin.php';
require_once __DIR__ . '/includes/class/class-prar-rating-review.php';
require_once __DIR__ . '/includes/class/class-prar-rating-notification.php';

// ==================================================================================================
// TO DO 
// - V1.3 - Créer un shortcode pour afficher le bouton "add a review" à d'autres endroits d'une page (objectif = pouvoir ajouter le bouton dans une page archive)
// - V1.3 - Ajouter propres fonctions pour like / dislike d'une review
// - V1.3 - Actualiser l'affichage du widget chart lorsque la note est changée avec un shortcode set_rating
// - V1.3 - Paramètres dans le plugin pour indiquer une taille mini et/ou une taille maxi du texte de la review
// - V1.3 - Rendre paramétrable le choix du SVG stars
// - V1.4 - Voir possibilité de signaler un abus (cf. zeno https://wordpress.org/plugins/zeno-report-comments/)
// - V1.5 - Ajouter un tooltip au survol de la note avec un commentaire par note (cf. allocine)
// - V1.5 - Créer le shortcode sous forme de widget Wordpress
// - V1.5 - Créer un bloc gutenberg pour héberger les shortcodes
// - Créer les métas schema.org pour les reviews
// - Gestion des reviews : dans la liste des reviews, voir si utile de faire un lien vers la page author de l'auteur de la review

// ==================================================================================================

/* activation du plugin */
register_activation_hook(__FILE__, 'post_rating_and_review_activate');
function post_rating_and_review_activate() {
	$current_version = get_option('prar_plugin_version');
	global $wpdb;
	$table_name = $wpdb->prefix . "prar_ratings_log";
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		// Créé la table des votes dans la base de données
		$wpdb->hide_errors();
		
		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
        
		$lbo_rating_log_table = "
			CREATE TABLE {$wpdb->prefix}prar_ratings_log (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			note decimal(2,1) NOT NULL,
			date datetime NOT NULL,
			external_id bigint(20),
			note_valide boolean,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY user_id (user_id),
			KEY note_valide (note_valide),
			KEY external_id (external_id)
        ) $collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $lbo_rating_log_table );
		
	}
		
	// Enregistre les options par défaut
	$current_options = get_option('prar_plugin_options');
	if ($current_options == '') {
		$options = array(
			'step_set_note'					=> 1,
			// 'step_display_note'				=> 0.1,
			'note_max'						=> 5,
			'taille_stars'					=> 32,
			'note_modifiable_apres_vote'	=> false,
			'texte_apres_note_user'			=> 'My review : {note}',
			'texte_note_user_saved'			=> 'Rating saved',
			'affichage_note_globale'		=> true,
			'affichage_nombre_notes'		=> true,
			'save_note_dans_post'			=> true,
			'nom_champ_nb_notes'			=> 'prar_number_of_ratings',
			'nom_champ_note_moyennne'		=> 'prar_overall_rating',
			'type_champ_dans_post'			=> 'meta',		// peut-être "acf" pour champ ACF ou "meta" pour meta classique WP
			'reviews_actif'					=> false,
			'reviews_post_types'			=> '',
			'reviews_rating_obligatoire'	=> true,
			'reviews_comment_obligatoire'	=> false,
		);
		update_option('prar_plugin_options', $options, false);
	}
	
}

register_deactivation_hook(__FILE__, 'post_rating_and_review_desactivate');
function post_rating_and_review_desactivate() {
	
}

class prar_rating {
	
	static $instance = false;
	private $options;
	private $admin_object = null;
	private $database_object = null;
	private $review_object = null;
	private $notification_object = null;

	function __construct() {		
		// Traduction
		add_action('init', array($this, 'prar_plugin_load_textdomain'));

		// Actions AJAX
		add_action('wp_ajax_prar_rating_save_post_note', array($this, 'prar_rating_ajax_save_post_note'));


		// Ajoute les shortcodes
		add_action('init', array($this, 'prar_rating_initialise_shortcodes'));
		// Charge les scripts et css
		add_action('wp_enqueue_scripts', array($this, 'prar_rating_add_scripts'));
		add_action( 'admin_enqueue_scripts', array($this, 'prar_rating_add_scripts') );
		// Ajoute un css inline dans le head pour la customisation de la couleur des stars
		add_action('wp_head', array($this, 'prar_rating_get_star_svg_inline_style'));
	
		$this->options = get_option('prar_plugin_options');
		
		$current_version = get_option('prar_plugin_version');
		if ($current_version != PRAR_PLUGIN_VERSION) {
			$this->prar_update();
		}
		
		if (is_admin() && ! $this->admin_object) {
			$this->admin_object = new prar_rating_admin();
		}
		if (! $this->database_object) {
			$this->database_object = new prar_rating_database();
		}
		if (! $this->review_object && $this->options['reviews_actif']) {
			$this->review_object = new prar_rating_review();
		}
		if (! $this->notification_object) {
			$this->notification_object = new prar_rating_notification();
		}
	}

	private function prar_update() {
		$options_to_update = false;
		if (! isset($this->options['send_rating_notif_to_post_author'])) {
			$this->options['send_rating_notif_to_post_author'] = false;
			$options_to_update = true;
		}
		if (! isset($this->options['send_review_notif_to_post_author'])) {
			$this->options['send_review_notif_to_post_author'] = false;
			$options_to_update = true;
		}
		if (! isset($this->options['reviews_comment_modifiable_auteur'])) {
			$this->options['reviews_comment_modifiable_auteur'] = false;
			$options_to_update = true;
		}
		if (! isset($this->options['reviews_comment_supprimable_auteur'])) {
			$this->options['reviews_comment_supprimable_auteur'] = false;
			$options_to_update = true;
		}
		if (! isset($this->options['reviews_comment_modifiable_admin'])) {
			$this->options['reviews_comment_modifiable_admin'] = true;
			$options_to_update = true;
		}
		if (! isset($this->options['reviews_display_rating_on_archive'])) {
			$this->options['reviews_display_rating_on_archive'] = false;
			$options_to_update = true;
		}
		if (! isset($this->options['reviews_display_rating_on_single'])) {
			$this->options['reviews_display_rating_on_single'] = false;
			$options_to_update = true;
		}
		if (! isset($this->options['couleur_stars'])) {
			$this->options['couleur_stars'] = "#f1c947";
			$options_to_update = true;
		}
		if (! isset($this->options['couleur_chart_bar'])) {
			$this->options['couleur_chart_bar'] = "#ffd700";
			$options_to_update = true;
		}
		if (! isset($this->options['couleur_chart_bar_hover'])) {
			$this->options['couleur_chart_bar_hover'] = "#ff7f50";
			$options_to_update = true;
		}
		if (! isset($this->options['couleur_border_owned_review'])) {
			$this->options['couleur_border_owned_review'] = "#ffd700";
			$options_to_update = true;
		}
		if ($options_to_update) {
			update_option('prar_plugin_options', $this->options, false);
		}
		// update la version du plugin
		update_option('prar_plugin_version', PRAR_PLUGIN_VERSION, false);		
	}
	
	public static function prar_getInstance() {
		if ( !self::$instance )
			self::$instance = new self();
		return self::$instance;
	}

	private function prar_rating_get_options() {
		if (! $this->options) {
			$this->options = get_option('prar_plugin_options');
		}
		return $this->options;
	}
	
	function prar_plugin_load_textdomain() {
		$chemin = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
		$ret = load_plugin_textdomain( 'post-rating-and-review', false, $chemin );
	}
	
	function prar_rating_initialise_shortcodes() {
		add_shortcode('prar_set_rating_for_post', array($this, 'prar_sc_set_rating_for_post'));
		add_shortcode('prar_display_rating_for_post', array($this, 'prar_sc_display_rating_for_post'));
		add_shortcode('prar_display_rating_chart_for_post', array($this, 'prar_sc_display_rating_chart_for_post'));
	}
	
	function prar_rating_add_scripts() {
		wp_register_script( 'rater-js', plugin_dir_url(__FILE__) . 'lib/rater-js/index.js', array(), '1.0', true );
		wp_enqueue_script('rater-js');
		if (! is_admin()) {
			wp_register_script( 'prar-script-js', plugin_dir_url(__FILE__) . 'includes/js/post-rating-and-review.js', array('jquery-core', 'jquery-ui-dialog'), PRAR_PLUGIN_VERSION, true );
			wp_add_inline_script( 'prar-script-js', 'const prar_rating_ajax_url = ' . json_encode(admin_url('admin-ajax.php')), 'before');	// url ajax WP envoyé au script
			wp_enqueue_script('prar-script-js');
			wp_register_style( 'jquery-ui-css', plugin_dir_url(__FILE__) . '/includes/css/jquery-ui.min.css', array(), '1.1.13' );
			wp_enqueue_style( 'jquery-ui-css' );
			wp_register_style( 'prar-front-css', plugin_dir_url(__FILE__) . '/includes/css/prar-front-style.css', array(), PRAR_PLUGIN_VERSION );
			wp_enqueue_style( 'prar-front-css' );
		} else {
			wp_register_script( 'prar-script-admin-js', plugin_dir_url(__FILE__) . 'includes/js/post-rating-and-review-admin.js', '', PRAR_PLUGIN_VERSION, true );
			wp_enqueue_script('prar-script-admin-js');			
		}
	}
	
	function prar_sc_set_rating_for_post($atts, $content=false, $shortcode_name=false) {
		$options = $this->prar_rating_get_options();
		$atts = shortcode_atts( array(
			'size'				=> $options['taille_stars'],
			'step'				=> $options['step_set_note'],
			'post_id'			=> false,
			'readonly'			=> false,
			'class'				=> '',
			'external_id'		=> 0,
			'update_after_vote'	=> $options['note_modifiable_apres_vote'],
			'save_immediately'	=> true,				// utilisé quand le widget est intégré dans le form de comment pour enregistrer au submit du formulaire
		), $atts, $shortcode_name );
		
		if ( $atts['post_id'] === false ) {
			$post_id = get_the_ID();
		} else {
			$post_id = (int) $atts['post_id'];
		}
		if (! get_post($post_id)) {
			return null;
		}
		$atts['post_id'] = $post_id;
		
		$atts = apply_filters('prar_rating_sc_set_note_atts', $atts);

		$unique_id = $post_id . '-' . str_shuffle(uniqid());
		$nonce_ajax = wp_create_nonce(PRAR_NONCE_SET_NOTE);
		$html  = '<div class="prar-rating prar-rating-set-post-rating">';

		if (! is_user_logged_in()) {
			// Le vote est uniquement accessible aux utilisateurs connectés => présente un widget vide avec un lien vers le formulaire de login
			$retour = array('note' => 0, 'number_of_notes' => 0);
			$url_login = wp_login_url(get_permalink());
			$html .= "<a href='$url_login' alt='" . _x('Veuillez vous connecter pour pouvoir noter.', 'Front', 'post-rating-and-review') . "'>";
		} else {
			// Récupère la note déjà mise par l'utilisateur connecté
			$retour = prar_rating_database::prar_rating_database_get_note_for_post($post_id, get_current_user_id(), false, true);
			// Bloque le widget en lecture seule si la note n'est pas modifiable par le user une fois qu'il a noté
			if ($retour['number_of_notes'] > 0 && ($atts['update_after_vote'] == false || $atts['update_after_vote'] === 'false')) {
				$atts['readonly'] = true;
			}
		}

		$html .= sprintf('<div class="prar-rating-widget %s" id="%s" data-post-id="%d" data-size="%d" data-update-after-vote="%s" data-step="%01.1f" data-readonly="%d" data-max="%d"
					data-security="%s" data-rating="%01.1f" data-external-id="%d" data-save-immediately="%d"></div>',
					$atts['class'], $unique_id, $post_id, $atts['size'], (($atts['update_after_vote'] === true || $atts['update_after_vote'] == 'true') ? 1 : 0), $atts['step'],
					(($atts['readonly'] === true || $atts['readonly'] == 'true' || ! is_user_logged_in()) ? 1 : 0), $options['note_max'], $nonce_ajax, $retour['note'],
					$atts['external_id'], (($atts['save_immediately'] === true || $atts['save_immediately'] == 'true') ? 1 : 0));

		if (! is_user_logged_in()) {
			$html .= '</a>';
		}
		
		$html .= '<div class="prar-rating-spinner"><div class="center"><div class="wave"></div><div class="wave"></div><div class="wave"></div><div class="wave"></div><div class="wave"></div><div class="wave"></div>
					<div class="wave"></div><div class="wave"></div><div class="wave"></div><div class="wave"></div></div></div>';
		
		$html .= '<div class="prar-rating-text-after">';
		$html .= $this->prar_rating_get_text_after_user_note($retour, $post_id);
		$html .= '</div>';
		$html .= '</div>';
		
		return $html;
	}
	
	function prar_sc_display_rating_for_post($atts, $content=false, $shortcode_name=false) {
		$options = $this->prar_rating_get_options();
		$atts = shortcode_atts( array(
			'size'				=> $options['taille_stars'],
			// 'step'				=> $options['step_display_note'],
			'step'				=> 0.1,
			'post_id'			=> false,
			'user_id'			=> false,
			'external_id'		=> false,
			'class'				=> '',
			'update_after_vote'	=> false,
			'note'				=> 0,
			'display_compteurs'	=> true,
		), $atts, $shortcode_name );
		
		if ( $atts['post_id'] === false ) {
			if ($atts['external_id'] === false && $atts['user_id'] === false) {
				$post_id = get_the_ID();
			} else {
				$post_id = false;
			}
		} else {
			$post_id = (int) $atts['post_id'];
		}
		if ($atts['note']) {
			$retour = array('note' => $atts['note'], 'number_of_notes' => 1);
		} else {
			$retour = prar_rating_database::prar_rating_database_get_note_for_post($post_id, isset($atts['user_id']) ? $atts['user_id'] : false );
		}
		
		$micro_data = '';
		$itemscope = '';
		if (! $atts['external_id'] && ! is_admin() && $retour['number_of_notes'] > 0) {
			if ($atts['user_id']) {
				$itemscope .= 'itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating"';
			} else {
				$itemscope .= 'itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating"';
				$micro_data .= sprintf('<meta itemprop="ratingCount" content="%d">', $retour['number_of_notes']);
				$post_object = get_post($post_id);
                $micro_data .= '<meta itemprop="itemReviewed" content="'. $post_object->post_title . '">';
			}
			$micro_data .= '<meta itemprop="worstRating" content="0">';
			$micro_data .= sprintf('<meta itemprop="bestRating" content="%d">', $options['note_max']);
			$micro_data .= sprintf('<meta itemprop="ratingValue" content="%01.1f">', $retour['note']);
		}
		
		$unique_id = $post_id . '-' . str_shuffle(uniqid());
		$html = '<div class="prar-rating prar-rating-display-post-rating" ' . $itemscope . '>';
		$html .= sprintf('<div class="prar-rating-widget %s" id="%s" data-post-id="%d" data-size="%d" data-update-after-vote="%s" data-step="%01.1f" data-readonly="1" data-max="%d" data-rating="%01.1f">%s</div>',
					$atts['class'], $unique_id, $post_id, $atts['size'], (($atts['update_after_vote'] === true || $atts['update_after_vote'] == 'true') ? 1 : 0), $atts['step'],
					$options['note_max'], $retour['note'], $micro_data);
		
		if ($atts['display_compteurs']) {
			$html .= '<span class="prar-rating-text-after-overall-rating">' . $this->prar_rating_get_text_after_note_globale($retour, $post_id) . '</span>';
		}
				
		$html .= '</div>';
		
		return $html;
	}
	
	function prar_sc_display_rating_chart_for_post($atts, $content=false, $shortcode_name=false) {
		$atts = shortcode_atts( array(
			'post_id'			=> false,
			'size'				=> 10,
			'class'				=> '',
		), $atts, $shortcode_name );
		
		if ( $atts['post_id'] === false ) {
			$post_id = get_the_ID();
		} else {
			$post_id = (int) $atts['post_id'];
		}
		
		$html = $this->prar_rating_get_html_widget_synthese_note($post_id, $atts['size']);
		
		return $html;
	}

	// Génère le html pour afficher le widget de synthèse d'une note
	function prar_rating_get_html_widget_synthese_note($post_id, $size = 10) {
		$tab_synthese_notes = prar_rating_database::prar_rating_database_get_number_of_vote_by_note($post_id);
		// Récupère le nombre de notes totale pour calculer le pourcentage de chaque progress bar
		$total_votes = $tab_synthese_notes['number_of_notes'];
		$html = '';
		// Génère une balise style si la taille demandée est différente
		if ($size != 10) {
			$html .= '<style>';
			$html .= sprintf('.prar-rating-chart-widget .prar-rating-chart-widget-progress-bar, .prar-rating-chart-widget .prar-rating-chart-widget-progress-bar-value {height: %dpx;}', $size);
			// Calcul les margins
			$margin = round($size / 5, 0);
			$html .= sprintf('.prar-rating-chart-widget a.prar-rating-chart-widget-link, .prar-rating-chart-widget span.prar-rating-chart-widget-link {margin-top: %dpx; margin-bottom: %dpx}', $margin, $margin);
			$html .= '</style>';
		}
		// Parcours les résultats
		$txt_note_attribuee = ($tab_synthese_notes['number_of_notes'] == 0 || $tab_synthese_notes['number_of_notes'] == 1) ? _x('note', 'Widget Synthesis title', 'post-rating-and-review') : _x('notes', 'Widget Synthesis title', 'post-rating-and-review');
		$html .= '<div class="prar-rating-chart-widget">';
		$html .= '<div class="prar-rating-chart-widget-title" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">';
		$html .= $this->prar_rating_get_text_after_note_globale($tab_synthese_notes, $post_id);

		// Ajout micro data schema.org
		$micro_data = '';
		if (! is_admin() && $total_votes > 0) {
			// il s'agit de la note globale : génère les microdata schema.org AggregateRating
			$micro_data .= '<meta itemprop="worstRating" content="0">';
			$micro_data .= sprintf('<meta itemprop="bestRating" content="%d">', $this->options['note_max']);
			$micro_data .= sprintf('<meta itemprop="ratingValue" content="%01.1f">', $tab_synthese_notes['note']);
			$micro_data .= sprintf('<meta itemprop="ratingCount" content="%d">', $total_votes);
			$post_object = get_post($post_id);
			$micro_data .= '<meta itemprop="itemReviewed" content="'. $post_object->post_title . '">';
		}
		
		$html .= $micro_data . '</div>';

		$note_filtre = (isset($_GET['review_note']) && is_numeric($_GET['review_note'])) ? sanitize_text_field($_GET['review_note']) : false;
		foreach ($tab_synthese_notes['results'] as $votes_per_note) {
			$pourcentage = $total_votes != 0 ? ($votes_per_note['number_of_notes'] / $total_votes) * 100 : 0;
			$class_a = sprintf('rating-%d-stars', $votes_per_note['note']);
			$class_a .= ($note_filtre !== false && $votes_per_note['note'] == $note_filtre) ? ' sel_fitre' : '';
			$url_filtre_comment = add_query_arg('review_note', $votes_per_note['note'], get_permalink() . '#comments');
			$class_a .= $pourcentage == 0 ? ' no-rating' : '';
			if ($pourcentage == 0 || is_admin()) {
				$html .= sprintf('<span class="prar-rating-chart-widget-link %s">', $class_a);
			} else {
				$html .= sprintf('<a class="prar-rating-chart-widget-link %s" href="%s">', $class_a, $url_filtre_comment);
			}
			$html .= sprintf('<span class="prar-rating-chart-widget-item"><div class="prar-rating-chart-widget-stars rating-%d-stars"><span>%d</span><span class="prar-rating-star"></span></div></span>', $votes_per_note['note'], $votes_per_note['note'] );
			$html .= sprintf('<span class="prar-rating-chart-widget-item"><span class="prar-rating-chart-widget-progress-bar"><span class="prar-rating-chart-widget-progress-bar-value" style="width: %s"></span></span></span>', $pourcentage . '%');
			$texte_note = $votes_per_note['number_of_notes'] > 1 ? _x('notes', 'Widget synthesis end of progress bar', 'post-rating-and-review') : _x('note', 'Widget synthesis end of progress bar', 'post-rating-and-review');
			$html .= sprintf('<span class="prar-rating-chart-widget-item"><div class="prar-rating-chart-widget-stars-count">%d<span> ' .  $texte_note . '</span></div></span>', $votes_per_note['number_of_notes']);
			if ($pourcentage == 0 || is_admin()) {
				$html .= '</span>';
			} else {
				$html .= '</a>';
			}
		}
		$html .= '</div>';
		return $html;
	}
	
	function prar_rating_ajax_save_post_note() {
		$options = $this->prar_rating_get_options();
		if (isset($_POST['note'], $_POST['post_id'], $_POST['security']) && is_numeric($_POST['note']) && is_numeric($_POST['post_id']) ) {
			$note			= sanitize_text_field($_POST['note']);
			$post_id		= (int) sanitize_key($_POST['post_id']);
			$external_id	= (isset($_POST['external_id']) && is_numeric($_POST['external_id'])) ? sanitize_key($_POST['external_id']) : null;
			$nonce_ajax 	= sanitize_text_field($_POST['security']);
			$user_id		= get_current_user_id();
		}
		else {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Problème avec les variables POST.', 'Front ajax error message', 'post-rating-and-review')));
			die();
		}
		// Vérifie le nonce_ajax
		if ($erreur = $this->prar_rating_validation_nonce($nonce_ajax, PRAR_NONCE_SET_NOTE) !== true) {
			echo json_encode($erreur);
			die();
		}
		do_action('prar_rating_before_save_note', $post_id, $user_id, $note, $external_id);
		// Sauve dans la base de données
		$id_ligne_log = prar_rating_database::prar_rating_database_save_note($post_id, $user_id, $note, $external_id);
		if ($id_ligne_log) {
			// renvoie la note globale après sauvegarde
			$retour_note_globale = prar_rating_database::prar_rating_database_get_note_for_post($post_id);
			do_action('prar_rating_after_save_note', $post_id, $user_id, $note, $external_id, $retour_note_globale, $id_ligne_log);
			
			// Met à jour les meta liées au post_id avec la note moyenne et le nombre de notes
			$this->prar_rating_save_metas_in_post($post_id, $retour_note_globale['number_of_notes'], $retour_note_globale['note']);
			
			echo json_encode(array_merge(
				array(
					'status' => 'ok',
					'note' => $note,
					'text_after_note_user' => $this->prar_rating_get_text_after_user_note(array('number_of_notes' => 1, 'note' => $note), $post_id),
					'text_after_note_globale' => $this->prar_rating_get_text_after_note_globale($retour_note_globale, $post_id),
					'text_saved_user_note' => apply_filters('prar_rating_text_save_user_note', $options['texte_note_user_saved'], $note, $post_id), 
				),
				$retour_note_globale));
		} else {
			echo json_encode(array('status' => 'erreur', 'message' => _x('Impossible de sauvegarder dans la base de données.', 'Front ajax error message', 'post-rating-and-review')));
		}
		die();
	}
	
	function prar_rating_save_metas_in_post($post_id, $number_of_notes, $average_note) {
		$options = $this->prar_rating_get_options();
		
		if ($options['save_note_dans_post'] && $options['nom_champ_nb_notes'] && $options['nom_champ_note_moyennne']) {
			if ($options['type_champ_dans_post'] == 'acf') {
				update_field($options['nom_champ_nb_notes'], $number_of_notes, $post_id);
				update_field($options['nom_champ_note_moyennne'], $average_note, $post_id);
			} else {
				update_post_meta($post_id, $options['nom_champ_nb_notes'], $number_of_notes);
				update_post_meta($post_id, $options['nom_champ_note_moyennne'], $average_note);					
			}
		}
		
	}
	
	function prar_rating_get_text_after_user_note($tab_note, $post_id) {
		$options = $this->prar_rating_get_options();
		$retour_texte = '';
		if ($options['texte_apres_note_user'] && $tab_note['number_of_notes'] > 0) {
			
			$retour_texte = str_replace('{note}', $this->prar_rating_get_user_note_formattee($tab_note['note']), apply_filters('prar_rating_text_user_note', $options['texte_apres_note_user'], $tab_note['note'], $post_id));
		}
		if ($tab_note['number_of_notes'] == 0 && ! is_user_logged_in()) {
			$retour_texte = _x('Vous devez être connecté pour noter', 'Front', 'post-rating-and-review');
		}
		return apply_filters('prar_rating_html_block_user_note', $retour_texte, $tab_note['note'], $post_id);
	}

	function prar_rating_get_text_after_note_globale($tab_note_globale, $post_id) {
		$options = $this->prar_rating_get_options();
		$html = '';
		if ($tab_note_globale) {
			if ($options['affichage_note_globale'] && $tab_note_globale['number_of_notes'] > 0) {
				$html .= $this->prar_rating_get_average_note_with_max($tab_note_globale['note'], 'average');
			}
			if ($options['affichage_nombre_notes']) {
				$txt_note_attribuee = ($tab_note_globale['number_of_notes'] == 0 || $tab_note_globale['number_of_notes'] == 1) ? _x('note', 'Widget Rating number of reviews', 'post-rating-and-review') : _x('notes', 'Widget Rating number of reviews', 'post-rating-and-review');
				if ($tab_note_globale['number_of_notes'] > 0) {
					$html .= sprintf('<span class="prar-rating-number-of-votes">(%d<span> %s</span>)</span>', number_format_i18n($tab_note_globale['number_of_notes'], 0), $txt_note_attribuee);
				} else {
					$html .= '<span class="prar-rating-text-after aucune-note">' . _x('Pas encore de note', 'Widget Rating number of reviews', 'post-rating-and-review') . '</span>';
				}
			}
		}
		return apply_filters('prar_rating_html_block_display_note', $html, $tab_note_globale, $post_id);
	}
	
	function prar_rating_get_user_note_formattee($note) {
		$options = $this->prar_rating_get_options();
		$step = (float) $options['step_set_note'];
		if ($step < 1) {
			return number_format_i18n($note, 1);
		} else {
			return number_format_i18n($note, 0);
		}
	}
	
	function prar_rating_get_average_note_formattee($note) {
		if (intval($note) == $note) {
			return number_format_i18n($note, 0);
		} else {
			return number_format_i18n($note, 1);
		}
	}
	
	function prar_rating_get_average_note_with_max($note, $type_note = 'user') {
		$options = $this->prar_rating_get_options();
		$note_formatee = ($type_note == 'user') ? $this->prar_rating_get_user_note_formattee($note) : $this->prar_rating_get_average_note_formattee($note);
		$note_texte = sprintf('<span class="prar-rating-texte-note"><span class="prar-rating-note">%s</span><span class="prar-rating-note-max">/%d</span></span>',
								$note_formatee, $options['note_max']);
		return $note_texte;		
	}
	
	
    public function prar_rating_validation_nonce($nonce, $action_name) {
        if (!wp_verify_nonce($nonce, $action_name)) {
			$erreur = _x('Erreur sur le nonce. L\'opération n\'a pas réussi. Veuillez réessayer plus tard.', 'Front ajax error message', 'post-rating-and-review');
            $error_nonce = array(
                'status'	=> 'error',
                'message'	=> $erreur
            );
            return $error_nonce;
        }
        return true;
    }

	public static function prar_rating_get_microdata_json($post_id) {
		$tab_note_globale = prar_rating_database::prar_rating_database_get_note_for_post($post_id);
		$tab_microdata = array(
			'aggregateRating'	=> array(
				'@type'				=> 'AggregateRating',
				'ratingValue'		=> $tab_note_globale['note'],
				'reviewCount'		=> $tab_note_globale['number_of_notes'],
				'worstRating'		=> '0',
				'bestRating'		=> self::$static_options['note_max'],
			),
		);
		return $tab_microdata;
	}
	
	public function prar_rating_get_template($template_name) {
		if (! $template_name) {
			return false;
		}
		// Regarde si le template est présent dans le thème sinon prend celui par défaut
		if (file_exists(get_stylesheet_directory() . '/prar-rating/' . $template_name . '.php')) {
			$template = get_stylesheet_directory() . '/prar-rating/' . $template_name . '.php';
		} else {
			$template = PRAR_RATING_PLUGIN_PATH . 'includes/template/' . $template_name . '.php';
		}
		return $template;
	}
	
	public function prar_rating_get_star_svg_inline_style() {
		$options = $this->prar_rating_get_options();

		$string_svg = sprintf('
			<svg xmlns="http://www.w3.org/2000/svg" width="108.9" height="103.6" viewBox="0 0 108.9 103.6">
				<defs>
					<style>.cls-1{fill:%s;}</style>
				</defs>
				<title>star1</title>
				<g id="Layer_2" data-name="Layer 2">
					<g id="Layer_1-2" data-name="Layer 1">
						<polygon class="cls-1" points="54.4 0 71.3 34.1 108.9 39.6 81.7 66.1 88.1 103.6 54.4 85.9 20.8 103.6 27.2 66.1 0 39.6 37.6 34.1 54.4 0"/>
					</g>
				</g>
			</svg>', ( $options['couleur_stars'] ? $options['couleur_stars'] : "#f1c947"));
		$svg_base64 = base64_encode($string_svg);
		$inline_css = '<style type="text/css">
						.prar-star-rating-rjs .star-value {background-image: url("data:image/svg+xml;base64,' . $svg_base64 . '") !important }
						.prar-rating-chart-widget .prar-rating-chart-widget-title .prar-rating-note::before {background-image: url("data:image/svg+xml;base64,' . $svg_base64 . '") !important }
						.prar-rating-chart-widget .prar-rating-chart-widget-stars span.prar-rating-star::before {background-image: url("data:image/svg+xml;base64,' . $svg_base64 . '") !important }
						.prar-rating-chart-widget span.prar-rating-chart-widget-progress-bar-value {background-color:' . ( $options['couleur_chart_bar'] ? $options['couleur_chart_bar'] : "#ffd700" ) . '; !important }
						.prar-rating-chart-widget a.prar-rating-chart-widget-link:hover .prar-rating-chart-widget-progress-bar-value {background-color:' . ( $options['couleur_chart_bar_hover'] ? $options['couleur_chart_bar_hover'] : "#ff7f50" ) . '; !important }
						.prar-rating-reviews-area .prar-review-list .comment.prar-rating-user-owned-review {border-color:' . ( $options['couleur_border_owned_review'] ? $options['couleur_border_owned_review'] : "#ffd700" ) . '; !important }
						.prar-rating-reviews-area .prar-review-list .comment.prar-rating-user-owned-review::before {background-color:' . ( $options['couleur_border_owned_review'] ? $options['couleur_border_owned_review'] : "#ffd700" ) . '; !important }
					   </style>';
		echo $inline_css;		
	}
	
}


$prar_rating = prar_rating::prar_getInstance();


?>
