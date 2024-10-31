<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly!
}

function prar_rating_get_average_note_for_post($post_id = false, $user_id = false, $external_id = false, $number_of_decimals = 1) {
	return prar_rating_database::prar_rating_database_get_note_for_post($post_id, $user_id, $external_id, $number_of_decimals);
}
	
function prar_rating_get_average_note_for_user($user_id) {
	return prar_rating_database::prar_rating_database_get_note_for_post(false, $user_id, false);
}

function prar_rating_get_average_note_for_external_id($external_id) {
	return prar_rating_database::prar_rating_database_get_note_for_post(false, false, $external_id);
}

function prar_rating_get_all_notes_for_user($user_id) {
	return prar_rating_database::prar_rating_database_get_all_notes(false, $user_id, false);
}

function prar_rating_get_all_notes_for_post($post_id) {
	return prar_rating_database::prar_rating_database_get_all_notes($post_id, false, false);
}

function prar_rating_get_all_notes_for_external_id($external_id) {
	return prar_rating_database::prar_rating_database_get_all_notes(false, false, $external_id);
}

?>