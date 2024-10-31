<?php
add_action('prar_rating_tpl_have_reviews_start', 'prar_rating_display_filter_result');
function prar_rating_display_filter_result() {
	if (isset($_GET['review_note']) && is_numeric($_GET['review_note'])) {
		echo '<div class="prar-rating-reviews-filtre-title">';
		printf(_x('Avis avec la note de %d', 'Front reviews list - Filter label', 'post-rating-and-review') . ' | ', esc_attr($_GET['review_note']));
		printf('<a href="%s">%s</a>', get_permalink() . '#comments', _x('Réafficher tous les avis', 'Front reviews list - Link to all reviews', 'post-rating-and-review')); 
		echo '</div>';
	}
}

add_action('prar_rating_tpl_have_reviews_start', 'prar_rating_highlight_owned_review');
function prar_rating_highlight_owned_review() {
	$current_user_id = get_current_user_id();
	$nice_name = '';
	if ($current_user_id) {
		$user_data = get_userdata($current_user_id);
		$nice_name = 'comment-author-' . sanitize_html_class( $user_data->user_nicename, $current_user_id );
	}
	?>
		<script>
			var class_comment_user = "<?php echo esc_html($nice_name) ?>";
		</script>
		<style>
			.prar-rating-reviews-area .prar-review-list .comment.prar-rating-user-owned-review::before {content : '<?php echo esc_html_x('Votre avis', 'Front reviews list - owned review label', 'post-rating-and-review') ?>'}
		</style>
	<?php
}

?>
