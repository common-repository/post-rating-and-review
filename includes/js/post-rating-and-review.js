function prar_display_rating_stars() {
	let tab_obj_raterjs = [];
	let tab_id_div = [];
	jQuery('.prar-rating-widget').each(function() {
		tab_id_div.push(jQuery(this).attr('id'));
		// Initialise le input hidden si existant (cas de l'integration dans le form comment de WP)
		// jQuery('input[name=prar_rating_review_comment_note-' + jQuery(this).data('post-id') + ']').val(parseFloat(jQuery(this).data('rating')));
		var starRating = raterJs( {
			max: jQuery(this).data('note_max'), 
			rating: parseFloat(jQuery(this).data('rating')),
			starSize: jQuery(this).data('size'),
			step: parseFloat(jQuery(this).data('step')),
			readOnly: jQuery(this).data('readonly'),
			element: this, 
			showToolTip: false, 
			rateCallback:function rateCallback(rating, done) {
				var obj_raterjs = this;
				var post_id = jQuery(this.element).data('post-id');
				// Utilisé lorsque le widget est intégré dans le formulaire comment
				var save_immediately = jQuery(this.element).data('save-immediately');
				if (! save_immediately) {
					obj_raterjs.setRating(rating);					
					// Stocke la note dans un champ Hidden
					jQuery('input[name=prar_rating_review_comment_note-' + jQuery(this.element).data('post-id') + ']').val(rating);					
					done();
				} else {
					obj_raterjs.disable();
					// Enregistre la note via ajax
					jQuery.ajax({
						url: prar_rating_ajax_url,
						type: "POST",
						data: {
							'action': 'prar_rating_save_post_note',
							'post_id': post_id,
							'security': jQuery(this.element).data('security'),
							'external_id': jQuery(this.element).data('external-id'),
							'note': rating,
						}
					}).done(function(response) {
						var retour = JSON.parse(response);
						var code_retour = retour.status;
						if (code_retour == 'ok') {
							obj_raterjs.setRating(rating);
							jQuery(obj_raterjs.element).trigger('prar_rating_saved');
							var nouvelle_note_globale = retour.note;
							var nombre_de_notes = retour.number_of_notes;
							// Parcours le document pour identifier d'autres div prar-rating-widget pour les mettre à jour avec la nouvelle note
							jQuery('.prar-rating-widget[data-post-id=' + post_id + ']').each(function() {
								var index = tab_id_div.indexOf(jQuery(this).attr('id'));
								if (jQuery(obj_raterjs.element).attr('id') !== jQuery(this).attr('id')) {
									if (jQuery(this).parent('.prar-rating').hasClass('prar-rating-display-post-rating')) {
										tab_obj_raterjs[index].setRating(nouvelle_note_globale);
										jQuery(this).parent('.prar-rating').find('.prar-rating-text-after-overall-rating').html(retour.text_after_note_globale);
									} else {
										tab_obj_raterjs[index].setRating(rating);
										jQuery(this).parent('.prar-rating').find('.prar-rating-text-after').html(retour.text_after_note_user);
									}
								}
							});
							// Affiche le message de note enregistrée
							var span_after_note = jQuery(obj_raterjs.element).parent('.prar-rating').find('.prar-rating-text-after');
							// Enlève le message après 1.5 secondes et remplace par le texte Ma note
							span_after_note.html(retour.text_saved_user_note);
							setTimeout(function () {
								span_after_note.html(retour.text_after_note_user);
								obj_raterjs.enable();
							}, 1500);
						}
					});				
					done();
				}
			}
		});
		tab_obj_raterjs.push(starRating);
	});	
}

function prar_review_popin_management() {
    if (jQuery('#prar_add_review_popin').length > 0) {
		// Review sur single post : popin donner une note
		var dialog = jQuery('#prar_add_review_popin').dialog({
			autoOpen: false,
			modal: true,
		});		
		jQuery('button[data-target="prar_add_review_popin"]').on("click", function() {
			dialog.dialog("open");
		});
		// Style spécifique pour review de l'utilisateur connecté
		if (typeof(class_comment_user) != 'undefined') {
			if (class_comment_user != '') {
				jQuery('.comment.' + class_comment_user).addClass('prar-rating-user-owned-review');
			}
		}
	}
    if (jQuery('#prar_update_review_popin').length > 0) {
		// Review sur single post : popin modification d'un avis
		var dialogUpdate = jQuery('#prar_update_review_popin').dialog({
			autoOpen: false,
			modal: true,
		});		
		jQuery('#prar_review_modify').on("click", function() {
			dialogUpdate.dialog("open");
		});
		jQuery('#prar_review_delete').on("click", function() {
			let comment_id = jQuery(this).data('comment_id');
			console.log(comment_id);
			jQuery('#prar_update_review_popin_confirm_delete').dialog({
				resizable: false,
				height: "auto",
				modal: true,
				buttons: {
					"Yes": function() {
						jQuery.ajax({
							url: prar_rating_ajax_url,
							type: "POST",
							data: {
								'action': 'prar_rating_delete_review',
								'comment_id': comment_id,
								'security': jQuery('#prar_nonce_ajax_delete').val(),
							}
						}).done(function(response) {
							var retour = JSON.parse(response);
							console.log(retour);
							if (retour.status == 'succes') {
								location.reload();
							} else {
								jQuery('#prar_delete_review_popin_error_message').html(retour.message);
								jQuery('#prar_delete_review_popin_error').dialog({
									modal: true,
									buttons: {
										Ok: function() {
										  jQuery(this).dialog("close");
										}
									}
								});								
							}
						});
						jQuery(this).dialog("close");
					},
					"No": function() {
						jQuery(this).dialog("close");
					}
				}
			});
		});
		jQuery('#prar_update_popin_cancel').on("click", function() {
			dialogUpdate.dialog("close");			
		});
		jQuery('#prar_update_popin_valide').on("click", function() {
			// Validation de la modification de la review
			let post_id = jQuery('#prar_comment_post_id').val();
			jQuery.ajax({
				url: prar_rating_ajax_url,
				type: "POST",
				data: {
					'action': 'prar_rating_update_review',
					'comment_id': jQuery('#prar_comment_id').val(),
					'comment': jQuery('#prar_comment_content').val(),
					'note': jQuery('input[name=prar_rating_review_comment_note-' + post_id + ']').val(),
					'security': jQuery('#prar_nonce_ajax').val(),
				}
			}).done(function(response) {
				try {
					var retour = JSON.parse(response);
				} catch (e) {
					var retour = {'status': 'erreur', 'message': response}; 
				}
				if (retour.status == 'succes') {
					location.reload();
				} else {
					jQuery('#prar_update_review_popin_error_message').html(retour.message);
					jQuery('#prar_update_review_popin_error').dialog({
						modal: true,
						buttons: {
							Ok: function() {
							  jQuery(this).dialog("close");
							}
						}
					});
				}
			});
		});
	}
}

function prar_save_new_review() {
	jQuery('#prar_add_review_popin #commentform').submit(function(e) {
		e.preventDefault();
		let element_form = jQuery(this);
		element_form.find('input[type=submit]').prop('disabled', true);
		element_form.find('p.form-submit').addClass('prar_spinner');
		let post_id = element_form.find('#comment_post_ID').val();
		jQuery.ajax({
			url: prar_rating_ajax_url,
			type: "POST",
			data: {
				'action': 'prar_rating_save_new_review',
				'comment_post_ID': element_form.find('#comment_post_ID').val(),
				'comment_parent': element_form.find('#comment_parent').val(),
				'comment': element_form.find('#comment').val(),
				'note': jQuery('input[name=prar_rating_review_comment_note-' + post_id + ']').val(),
				'security': element_form.find('.prar-rating-set-post-rating .prar-rating-widget').data('security'),
			}
		}).done(function(response) {
			element_form.find('p.form-submit').removeClass('prar_spinner');
			try {
				var retour = JSON.parse(response);
			} catch (e) {
				var retour = {'status': 'erreur', 'message': response}; 
			}
			if (retour.status == 'succes') {
				let redirect_to = retour.redirect_to;
				window.location.replace(redirect_to);
				location.reload();
			} else {
				jQuery('#prar_add_review_popin_error_message-' + post_id).html(retour.message);
				jQuery('#prar_add_review_popin_error-' + post_id).dialog({
					modal: true,
					buttons: {
						Ok: function() {
							jQuery(this).dialog("close");
							element_form.find('input[type=submit]').prop('disabled', false);
						}
					}
				});
			}
		});
		
	});
}

jQuery(document).ready(function($) {
	prar_display_rating_stars();
	prar_review_popin_management();
	prar_save_new_review();
});

// AJAX content with shortcode : trigger('prar_rating_display_stars') after ajax call in order to display stars included in ajax return.
jQuery(document).on('prar_rating_display_stars', function() {
	prar_display_rating_stars();
});
