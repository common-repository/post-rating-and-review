<?php
// Integration of tiers plugin
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// 1- Zeno Report Comments https://wordpress.org/plugins/zeno-report-comments/
// Intègre en front le lien "report" en dessous du comment_content (uniquement sur les commentaires dont le visiteur n'est pas l'auteur)

define('no_autostart_zeno_report_comments', true);
$prar_zeno_report_comments = null;

// add_action('init', 'prar_rating_plugin_zeno_integration');
add_action('plugins_loaded', 'prar_rating_plugin_zeno_integration');
function prar_rating_plugin_zeno_integration() {
	if (is_plugin_active('zeno-report-comments/zeno-report-comments.php')) {
		global $prar_zeno_report_comments;
		$prar_zeno_report_comments = new Zeno_Report_Comments(false);
		add_filter('comment_text', 'prar_add_flagging_link_to_content', 10, 3);
	}
}

function prar_add_flagging_link_to_content( $comment_content, $comment, $args) {
	if ( is_admin() || $comment->user_id == get_current_user_id() || user_can($comment->user_id, 'moderate_comments') || ! $comment ) {
		return $comment_content;
	}
	
	global $prar_zeno_report_comments;
	$comment_id = $comment->comment_ID;
	$class = 'zeno-comments-report-link';
	$already_moderated = $prar_zeno_report_comments->already_moderated( $comment_id );
	if ( $already_moderated ) {
		$class .= ' zcr-already-moderated';
		return $comment_content;
	}
	$flagging_link = $prar_zeno_report_comments->get_flagging_link($comment_id);
	if ( $flagging_link ) {
		$comment_content .= '<br><span class="' . $class . '">' . $flagging_link . '</span>';
	}
	return $comment_content;
}


// 2- Comments Like Dislike https://wordpress.org/plugins/comments-like-dislike/
// Spécificité : bloque le like / dislike sur sa propre review
// add_filter('cld_like_dislike_html', 'prar_rating_cld_like_dislike_html', 10, 2);
function prar_rating_cld_like_dislike_html($like_dislike_html, $cld_settings) {
	$comment_id = get_comment_ID();
	if ($comment_id) {
		$comment = get_comment($comment_id);
		if ($comment) {
			if ($comment->user_id == get_current_user_id()) {
				return '';
			}
		}
	}
	return $like_dislike_html;
}

// 3- Theme Madara https://mangabooth.com/product/wp-manga-theme-madara/
// dans le plugin core, fichier "madara-core/inc/comments/wp-comments.php", une action 'pre_get_comments' ajoute des critères à get_comments avec un critère "relation = OR"
// cela genère un problème dans la fonction prar_rating_review:prar_rating_review_comments_template_query_args() dans laquelle get_comments permet de trouver les commentaires
// liés à une review => hook la même action "pre_get_comments" pour reformater les critères lorsqu'il y a une meta_query sur la meta PRAR_RATING_COMMENT_META_NAME
add_action('pre_get_comments', 'prar_rating_compatibility_pre_get_comments', 9999);
function prar_rating_compatibility_pre_get_comments($comments_query) {
	// Cherche si PRAR_RATING_COMMENT_META_NAME existe dans les meta query
	$boolTrouve = false;
	foreach ($comments_query->query_vars['meta_query'] as $index => $tab) {
		if (isset($tab['key']) && $tab['key'] == PRAR_RATING_COMMENT_META_NAME) {
			$boolTrouve = true;
		}
	}
	// Retire les critères sur chapter_id ajoutés par madara_core
	if ($boolTrouve) {
		foreach ($comments_query->query_vars['meta_query'] as $index => $tab) {
			if (isset($tab['key']) && $tab['key'] == 'chapter_id') {
				unset($comments_query->query_vars['meta_query'][$index]);
			}
		}
	}
}

?>
