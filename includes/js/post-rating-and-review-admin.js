jQuery(document).ready(function($) {
	var nb_appels = 0;

	if ($('body.post-php').length > 0) {
		$(document).ajaxStop(function() {
			nb_appels++;
			if (nb_appels < 2) {
				$('.prar-rating-widget').each(function() {
					var starRating = raterJs( {
						max: $(this).data('note_max'), 
						rating: parseFloat($(this).data('rating')),
						starSize: $(this).data('size'),
						step: parseFloat($(this).data('step')),
						readOnly: true,
						element: this, 
						showToolTip: false, 
						rateCallback: ''
					});
				});
			}
		});
	}
});
