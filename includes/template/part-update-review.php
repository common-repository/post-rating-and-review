<?php
/**
 * The template part to modify a review
 */
$nonce_ajax = wp_create_nonce(PRAR_NONCE_UPDATE_REVIEW);

?>
<div id="prar_update_review_popin" class="comment-form" style="display: none;" title="<?php echo _x('Modifier votre avis', 'Front reviews list - update review title', 'post-rating-and-review') ?>">
	<?php echo $html_widget ?>
	<input type="hidden" name="prar_comment_id" id="prar_comment_id" value="<?php echo $comment->comment_ID ?>">
	<input type="hidden" name="prar_comment_post_id" id="prar_comment_post_id" value="<?php echo $comment->comment_post_ID ?>">
	<input type="hidden" name="prar_nonce_ajax" id="prar_nonce_ajax" value="<?php echo $nonce_ajax ?>">
	<textarea id="prar_comment_content" name="prar_comment_content" cols="45" rows="8" maxlength="65525"><?php echo $comment->comment_content ?></textarea>
	<div class="prar_update_review_popin_actions">
		<?php
		printf('<span><input type="button" id="prar_update_popin_valide" value="%s"></span>', _x('Valider', 'Front - Author actions', 'post-rating-and-review'));
		printf('<span><input type="button" id="prar_update_popin_cancel" value="%s"></span>', _x('Annuler', 'Front - Author actions', 'post-rating-and-review'));	
		?>
	</div>
</div>
<div id="prar_update_review_popin_error" style="display: none;" title="<?php echo _x('Une erreur est survenue', 'Front reviews list - update review title - Error', 'post-rating-and-review') ?>">
	<div id="prar_update_review_popin_error_message"></div>
</div>
