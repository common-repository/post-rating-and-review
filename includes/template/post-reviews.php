<?php
/**
 * The template for displaying reviews.
 *
 * The area of the page that contains both current reviews
 * and the comment form in popin.
 *
 */

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area prar-rating-reviews-area default-max-width">

	<?php // You can start editing here -- including this comment! ?>


	<header class="comments-header">
		<?php do_action('prar_rating_tpl_reviews_begin_header'); ?>

		<h3 class="comments-title prar-rating-reviews-title">
			<?php echo _x('Avis', 'Front reviews list title', 'post-rating-and-review') ?>
		</h3>
		
		<div class="prar-rating-reviews-header">
			<?php echo do_shortcode('[prar_display_rating_chart_for_post size="20"]') ?>
			<div class="prar-rating-add-review">
				<?php do_action('prar_rating_tpl_reviews_before_add_review'); ?>
				
				<?php
					$disabled = '';
					$message = '';
					$review_ability = prar_rating_review::prar_rating_review_is_user_able_to_review();
					switch ($review_ability) {
						case 'already-commented' :
							$disabled = 'disabled';
							$message = _x('Vous avez déjà attribué une note', 'Front reviews list', 'post-rating-and-review');
							break;
						case 'not-logged-in' :
							$disabled = 'disabled';
							$message = _x('Vous devez être connecté pour donner votre avis.', 'Front reviews list', 'post-rating-and-review');
							$message .= '<br><a href="' . wp_login_url(get_permalink()) . '">' . _x('Cliquez ici pour vous connecter', 'Front reviews list', 'post-rating-and-review') . '</a>';
							break;
					}								
				?>
				<button class="prar-rating-btn-add-review" <?php echo esc_html($disabled) ?> data-target="prar_add_review_popin" ><?php echo _x('Donnez votre avis', 'Front reviews list - add review button', 'post-rating-and-review') ?></button>
				<?php if ($message) : ?>
					<div class="prar-rating-message"><?php echo wp_kses_post($message) ?></div>
				<?php endif; ?>

				<div id="prar_add_review_popin" style="display: none;" title="<?php echo _x('Rédiger un avis', 'Front reviews list - add review title', 'post-rating-and-review') ?>">
				<?php 
					if (! $disabled) {
						// Affiche uniquement le form si l'utilisateur peut poster une review
						comment_form( array(
							'label_submit'	=> _x('Soumettre votre avis', 'Front reviews list - add review submit button label', 'post-rating-and-review'),
							'action'		=> '',
						));
					}
				?>
				</div>
				<div id="prar_add_review_popin_error-<?php echo get_the_ID() ?>" style="display: none;" title="<?php echo _x('Une erreur est survenue', 'Front reviews list - update review title - Error', 'post-rating-and-review') ?>">
					<div id="prar_add_review_popin_error_message-<?php echo get_the_ID() ?>"></div>
				</div>
				
				<?php do_action('prar_rating_tpl_reviews_after_add_review'); ?>
			</div>
		</div>
		<?php do_action('prar_rating_tpl_reviews_end_header'); ?>
	</header><!-- .comment-header -->

	<?php if ( have_comments() ) : ?>
		<?php do_action('prar_rating_tpl_have_reviews_start'); ?>

		<?php the_comments_navigation(); ?>

		<ol class="comment-list prar-review-list">
			<?php
				wp_list_comments( array(
					'style'      => 'ol',
					'short_ping' => true,
					'avatar_size' => 40,
				) );
			?>
		</ol><!-- .comment-list -->

		<?php the_comments_navigation(); ?>

		<?php do_action('prar_rating_tpl_have_reviews_end'); ?>

	<?php else : // Check for have_comments(). ?>
		
		<?php do_action('prar_rating_tpl_no_reviews_start'); ?>
		
		<?php if (isset($_GET['review_note']) && is_numeric($_GET['review_note'])) : ?>
			<div class="no-comments filtre-note">
				<?php
				printf(_x('Aucun avis trouvé avec la note de %d', 'Front reviews list', 'post-rating-and-review'), esc_attr($_GET['review_note']));
				echo ' | ';
				printf('<a href="%s">%s</a>', get_permalink() . '#comments', _x('Réafficher tous les avis', 'Front reviews list', 'post-rating-and-review')); 
				?>
			</div>
		<?php else : ?>
			<div class="no-comments"><?php echo _x('Il n\'y a pas encore d\'avis. Soyez le premier à partager le vôtre.', 'Front reviews list', 'post-rating-and-review') ?></div>		
		<?php endif; ?>

		<?php do_action('prar_rating_tpl_no_reviews_end'); ?>

	<?php endif; ?>	

</div><!-- #comments -->
