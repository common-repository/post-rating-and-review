=== Post Rating and Review ===
Donate link: https://www.paypal.com/donate/?hosted_button_id=M3C8563S6NEJY
Tags: rating, review, vote, rate post, custom post, star rating, testimonial, cookies free, microdata, schema.org, responsive
Contributors: bourgesloic
Requires at least: 5.0
Tested up to: 6.0
Stable tag: 1.3.4
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easy-to-use plugin to manage star rating reviews on your posts or custom posts. Built on Wordpress comments functionalites. Neat and hasslefree.

== Description ==

The "Post Rating and Review" plugin offers the possibility to connected visitors to review posts or types of posts that you have created (custom post types). You may define the rating scale yourself.
##### You can use the plugin in 2 ways: #####
1. Full activation of reviews management (rating + comment): to allow your users to rate **AND** leave a comment
2. Enabling rating only: when you want your users to leave a rating but you don't need to collect their feedback

##### These features are mainly usable with shortcodes, so without development. Three shortcodes are integrated into the plugin allowing the display of 3 different widgets: #####
* the "set rating" widget which allows a visitor to leave a rating for a post
* the "display rating" widget which allows you to display the overall rating of a post
* the "rating chart" widget which displays the overall rating of a post as well as a breakdown by rating scale

Enabling review management will automatically display the "rating chart" widget above the list of comments in a single post page without the need of a shortcode. Additionally, you can integrate other widgets on the same page if you wish (for instance, a widget "display rating" at the top of the page).

The plugin also includes functionalities to manage email notification to posts authors when a new review of their post is submitted.

This plugin integrates schema.org specifications on ratings: microdata will be automatically generated when you display the overall rating of a post ("aggregateRating" type).

An important point: in this first version, the plugin allows rating only for **visitors connected to your site**, which seems to us to be the best choice to manage reviews on a website. Nevertheless, a future update may integrate the management of ratings and reviews by non-connected visitors if needed.

I developed this plugin for my own use and then made it as configurable as possible so that it could be used by others. Do not hesitate to contact me if you are missing certain features. I can't promise you that I will be able to integrate them into a future release for you, but it doesn't hurt to ask!

### Overview of plugin options ###

You can access and customize the following parameters:
#### Rating widget general settings ####
* Rating max (integer): maximum rating that a visitor can assign (usually 5)
* Default star size: size in pixels of the stars displayed in the widgets. This parameter can then be modified when calling a shortcode.
* Step for rating set: precision for the visitor to assign a rating (I recommend 0.5 or 1)
* Color for stars: color of the stars displayed within all widgets
* Color for widget chart bar: color of the chart bar in the detailed rating chart
* Color for widget chart bar (hover): color of the chart bar when cursor hovers in the detailed rating chart
* Color to highlight user owned review: color of border to highlight connected user owned review

#### Rating display settings ####
* Display overall rating after stars: if yes, the shortcode overall rating (prar_display_rating_for_post) will display the rating in plain text after the stars.
* Display number of ratings after stars: if yes, the shortcode overall rating (prar_display_rating_for_post) will display the number of ratings after the stars.
* Text displayed below user's rating: indicates the text displayed below a user's note when the user has already rated (shortcode prar_set_rating_for_post). The text {note} will be replaced by the rating given by the user.

#### Rating recording settings ####
* Text displayed after rating update: this text will be displayed under the widget when the visitor validates his rating (shortcode prar_set_rating_for_post). After a few moments, this text will disappear and be replaced by the text defined in "text displayed below the user note" option field.
* User can change rating after submission?: if yes, the visitor can modify his rating even after submission. If not, the visitor will be able to modify his note as long as he does not leave the page; if he leaves the page and then returns, the rating will no longer be editable.
* Save overall rating and number of ratings in post meta? : if yes, the overall rating as well as the number of ratings will be updated in the meta of the post that the visitor rated. You can then choose the name of the fields that will be saved (meta_key in the postmeta table) and what type of meta you want to use (Meta Wordpress as standard, ACF field if you use ACF and you have created a custom field to store these values ).

#### Reviews management settings ####
Enable review management? : if yes, the management of reviews will be activated. Then choose the type or types of post for which you want to activate this management of reviews. Then indicate which fields are mandatory for a visitor to be able to submit their review (N.B. at least one of the 2 fields must be selected as mandatory).
You can also indicate if a review author have the possibility to modify and/or delete its review (N.B. it applies until review has not been answered). Last, you can allow modifying review in WP backoffice by another user that review author.

#### Email notifications settings ####
* Send an email to the post author when a new rating is submitted: useful when you do not use review management functionalities. Selecting "yes" will enable sending a notification email to the post author when a rating is submitted by a visitor through the plugin shortcode.
* Send an email to the post author when a new review is posted: Wordpress includes a notification email to post authors for new comments. If you select "yes", Wordpress standard email is customized and includes the rating submitted by the reviewer.

#### Tools ####
* By clicking on the "Start database reset" button, all datas stored by the plugin will be erased. This can be useful in the development phase to test your site and dump the data from your tests before going live.

### Rating Management Shortcodes ###
Three shortcodes display a Rating widget. You can integrate these shortcodes in several ways: in the Gutenberg editor, in the Classic Editor, in a widget or even directly in your templates.
You can also integrate several rating widgets on the same page: for example, if you integrate the shortcode at the top and bottom of the page, the validation of the rating with one of rating widgets will trigger the update of the other widgets present on the page.

#### 1. Shortcode to give a rating ####
`[prar_set_rating_for_post]`

##### Optional parameters accepted in the shortcode: #####
* post_id (numeric): id of the post that will be rated by the visitor. If the parameter is not specified, the plugin will use the active post.
* size (numeric): size in pixels of the stars displayed. By default, uses the size defined in the plugin options.
* step (numeric > 0 and <= 1): by default, uses the value indicated in the options of the plugin.
* readonly (true/false): displays the read-only widget that cannot be modified by the visitor. This can be useful to show the visitor the rating he gave to a post (for example in a My Account section).
* class (text): css class that will be added to the widget to facilitate formatting customization
* external_id (numeric): this is the id of a post attached to the post_id you specified. Example of use: you have created two types of posts "Books" and "Writers", each book being attached to a writer. You call the shortcode for a book and you provide the writer's post_id in the "external_id" parameter. The plugin will store the writer's id along with book's post id. It will allow you to display the writer's overall rating on his page: this rating will be based on all of his books that have been rated by visitors on your website .
* update_after_vote (false / true): by default filled by the value indicated in the options of the plugin. If false, visitors will not be able to change their rating once they leave the page. When visitors return to the page, they will see the rating they gave but will not be able to modify it. If true, the note will be editable at any time by the visitor.

##### Example: #####
`[prar_set_rating_for_post post_id="153" step="0.5" size="32" update_after_vote="false"]`


#### 2. Shortcode to display overall rating ####
`[prar_display_rating_for_post]`

##### Optional parameters accepted in the shortcode: #####
* post_id (numeric): id of the post for which you want to display the overall rating. If the parameter is not specified, the plugin will use the active post.
* size (numeric): size in pixels of the stars displayed. By default, uses the size defined in the plugin options.
* step (numeric > 0 and <= 1): by default, uses the value indicated in the options of the plugin.
* display_compteurs (true/false): if true, the overall rating as well as the number of ratings will be displayed next to the widget.
* class (text): css class that will be added to the widget to facilitate formatting customization
* user_id (numeric): if the id of a user is indicated then the behavior will depend on the post_id and external_id parameters. Below are the different cases:
user_id filled in alone: ​​the widget will display the overall rating of a given user (average of all his ratings)
user_id with post_id: the widget will display the rating given by the user to the post
user_id with external_id: the widget will display the overall rating of all ratings given by the user and linked to this external_id.
* external_id (numeric): this is the id of a post attached to the post_id you specified (cf. shortcode prar_set_rating_for_post). If filled in alone (without user_id or post_id), then the widget will display the overall rating of all ratings assigned to this external_id.

##### Example: #####
`[prar_display_rating_for_post post_id="153" size="32" display_compteurs="true"]`

#### 3. Shortcode to display overall rating + detailed rating chart ####
`[prar_display_rating_chart_for_post]`

##### Optional parameters accepted in the shortcode: #####
- post_id (numeric): id of the post for which you want to display the widget. If the parameter is not specified, the plugin will use the active post.
- size (numeric): height in pixels of the horizontal bars used to represent the number of notes per note scale.
- class (text): css class that will be added to the widget to facilitate formatting customization

##### Example: #####
`[prar_display_rating_chart_for_post post_id="153" size="20"]`

### Overview of reviews management ###
Managing reviews consists of associating a rating with a comment. The reviews management uses Wordpress commenting functionalities: therefore, comments must be activated for the types of post for which you want to activate reviews.
Thanks to the use of standard WP features, the settings you specify for comments will automatically apply to reviews (manual approval of comments, notification messages, automatic moderation, etc.).
##### The settings that **will not apply** to review management are: #####
1. "Users must be registered and logged in to comment": currently the plugin only accepts reviews for logged-in visitors. Regardless of your choice for this option, only logged-in visitors will be able to leave a review.
2. Enable threaded (nested) comments N levels deep: in the plugin, only administrators will be able to respond to a review via the Wordpress admin screens. Visitors - logged in or not - will not be able to respond to a review.

To enable reviews, indicate "yes" in the plugin options ("Reviews management settings" section) then select the post types for which you want the review management features to be automatically implemented.
When you activate this management of reviews, the standard "Comments" area will be replaced by a plugin owned template ("post-reviews.php") which is located in the "includes/template" folder of the plugin. You can also customize this template by copying it into a "prar-rating" folder in your theme.
This template displays the overall rating, the detailed rating chart, the list of reviews already published as well as a button allowing a visitor to leave a new review. This button is active if the visitor is logged in and has not already left a review on the post. The writing of the review by the visitor is done in a popin which combines the rating attribution and the comment left by the visitor. Via the options of the plugin, it is possible to determine if the note and/or the comment are mandatory to leave a review.
Reviews left by users will be visible in admin in the standard Wordpress comments list as well as on the post editing page.

### Overview of email notifications management ###
You can activate a functionality which will send emails to post authors each time a new rating or a new review of their post is submitted.
#### How it works ####
* Notification of new review: email sending is based on Wordpress standard functionality. Email content and subject are customized through a txt template named `email-author-notification-new-review_en_US.txt` (en_US can be changed with your locale if you want to translate it to your language).
When manual approbation on comments is activated, the notification email is sent to the post author as soon as the review is approved in Wordpress admin.
Important notice: Wordpress does not send the notification email when the user who did approve the review is also the post author. In the same logic, Wordpress does not send the email when the review author is also the post author.
* Notification of new rating: this functionality is almost the same as the notification's for new review. It is useful when you do not use these plugin review management functionalities (i.e. you just use the shortcodes as a standalone). The txt template of this email is `email-author-notification-new-rating_en_US.txt`.

Both emails are in plain-text format. You can customize these emails copying txt template in a "prar-rating" folder in your theme directory, then change the locale if you need (for instance, `email-author-notification-new-rating_de_DE.txt` if your website is in german language). Text between {} shall not be changed: it will be replaced dynamically by values when the email is generated.

### For Developers ###
Several hooks are available for developers as well as callable functions:
#### Actions ####
- `prar_rating_before_save_note`
Called before saving rating to database
Parameters: `post_id, user_id, note, external_id`
- `prar_rating_after_save_note`
Called right after saving rating to database
Parameters: `post_id, user_id, note, external_id`
- `prar_rating_tpl_reviews_begin_header`
Called in the post-reviews.php template just before the section title
Parameter: none
- `prar_rating_tpl_reviews_before_add_review`
Called in the post-reviews.php template just before the "Add a review" button
Parameter: none
- `prar_rating_tpl_reviews_after_add_review`
Called in the post-reviews.php template just after the "Add a review" button
Parameter: none
- `prar_rating_tpl_reviews_end_header`
Called in the post-reviews.php template at the end of the header
Parameter: none
- `prar_rating_tpl_have_reviews_start`
Called in the post-reviews.php template just before the list of reviews (when there are reviews)
Parameter: none
- `prar_rating_tpl_have_reviews_end`
Called in the post-reviews.php template just after the list of reviews (when there are reviews)
Parameter: none
- `prar_rating_tpl_no_reviews_start`
Called in the post-reviews.php template just before the list of reviews (when there are no reviews for the post yet)
Parameter: none
- `prar_rating_tpl_no_reviews_end`
Called in the post-reviews.php template just before the list of reviews (when there are no reviews for the post yet)
Parameter: none
#### Filters ####
- `prar_rating_sc_set_note_atts`
Allows to override the parameters indicated during the call to the shortcode prar_set_rating_for_post
Parameter: `array atts`
- `prar_rating_text_save_user_note`
Allows to override the text displayed to the visitor when the rating is saved with the prar_set_rating_for_post shortcode. Default text is set in plugin options
Parameters: `initial text, note, post_id`
- `prar_rating_text_user_note`
Allows to override the text displayed to the visitor next to his rating in the prar_set_rating_for_post shortcode. Default text is set in plugin options
Parameters: `initial text, note, post_id`
- `prar_rating_html_block_user_note`
Allows to modify the html block which is after the rating in the prar_set_rating_for_post shortcode.
Parameters: `initial html block, note, post_id`
- `prar_rating_html_block_display_note`
Allows to modify the html block displayed after the widget in the shortcode prar_display_rating_for_post
Parameters: `initial html block, array with overall note information (number_of_notes, note, sum_notes)`
- `prar_rating_notification_new_review_email_subject`
Allows to customize email subject of new review notification email sent to post author
Parameters: `subject, comment (object)`
- `prar_rating_notification_new_review_email_header`
Allows to customize email headers of new review notification email sent to post author
Parameters: `header, comment (object)`
- `prar_rating_notification_new_rating_email_subject`
Allows to customize email subject of new rating notification email sent to post author
Parameters: `subject, blogname, post (object), rating (array)`
- `prar_rating_notification_new_rating_email_sender`
Allows to customize email sender of new rating notification email sent to post author
Parameters: `sender, blogname, post (object), rating (array)`
- `prar_rating_notification_new_rating_email_from`
Allows to customize email from (in header) of new rating notification email sent to post author
Parameters: `from, blogname, post (object), rating (array)`
- `prar_rating_notification_new_rating_email_header`
Allows to customize email header of new rating notification email sent to post author
Parameters: `header, blogname, post (object), rating (array)`
- `prar_rating_can_review_be_updated`
Indicate if a review can be updated by the review author
Parameters: `can_be_uptdated (boolean), comment (object)`
#### Functions ####
- `prar_rating_get_average_note_for_post`
Returns the overall rating for a post, user, external_id as an array (number_of_notes, note, sum_notes)
Parameters: `post_id, user_id, external_id`
- `prar_rating_get_average_note_for_user`
Returns the overall rating given by a user as an array (number_of_notes, rating, sum_notes)
Parameter: `user_id`
- `prar_rating_get_average_note_for_external_id`
Returns the overall rating for an external_id as an array (number_of_notes, rating, sum_notes)
Parameter: `external_id`
- `prar_rating_get_all_notes_for_user`
Returns an array with one line per rating assigned by a user (post, user_id, rating, external_id, date)
Parameter: `user_id`
- `prar_rating_get_all_notes_for_post`
Returns an array with one row per rating assigned for a post (post, user_id, rating, external_id, date)
Parameter: `post_id`
- `prar_rating_get_all_notes_for_external_id`
Returns an array with one row per rating assigned to an external_id (post, user_id, rating, external_id, date)
Parameter: `external_id`
#### JavaScript ####
- `trigger("prar_rating_saved")`
An 'prar_rating_saved' event is generated related to the DOM element that carries the widget. This allows you, if necessary, to trigger specific actions in your website when assigning a rating. N.B. this event is generated when the note is saved in a `"prar_set_rating_for_post"` widget. It is not generated as part of the management of reviews for which the note is recorded after validation of the complete form.
- event listener `prar_rating_display_stars`
If your page is generated through ajax and includes the plugin shortcode, you must trigger this event in order to display the stars icons. Trigger the event after having made changes in DOM through your ajax function.
### Special thanks ###
The development of this plugin was greatly facilitated by "RaterJS" javascript library developed by Fredrik Olsson. Many thanks also to [Eric](https://www.ericbourges.com) for his valuable design advices and feature ideas.

== Screenshots ==

1. Example of review management on a single post page
2. Shortcodes set and display note on a single post page
3. Settings page in admin
4. Ratings display in admin on post page
5. Admin rating integrated on comments lists


== Frequently Asked Questions ==

= Do I need programing skills to use the plugin? =
No. You just need to have a global understanding of the way Wordpress works, and to know how to use shortcodes. If you need further information on shortcodes in Wordpress, find below links to articles on wordpress.com website:
[What Is a Shortcode?](https://wordpress.com/go/website-building/what-is-a-shortcode/)
[WordPress Shortcodes: What They Are and When to Use Them](https://wordpress.com/go/how-to/wordpress-shortcodes-what-they-are-when-to-use/)

= Does the plugin works with caching plugins? =
Logically yes. This plugin is based on ajax development which should not be taken into account by caching plugins.
However let me know through support page if you meet any difficulty with caching plugin.

= May I be able to open reviews submission to non-logged-in users? =
No. Reviews and rating are open to logged-in users. It allows to avoid cookie usage which could be complex within GDPR regulation in Europe.
I will consider to include this functionaly if the demand is growing. 

= Do I need to pay something to use the plugin? =
No! This plugin is not a freemium. Everything comes for free. I just developed it because I was able to (very humbly!) and because I like it.
However, I accept donations if you love this plugin ;-)


== Installation ==

1. Navigate to Dashboard -> Plugins -> Add New and search for the plugin
2. Click on "Install Now" and then "Activate"

== Upgrade Notice ==
First relase . Not applicable.

== Changelog ==

= 1.3.4 - Released on 14 july 2022 =
* FIX: compatibility with mandara theme
* FIX: stars alignement in detailed chart widget
= 1.3.3 - Released on 12 july 2022 =
* FIX: missing closing <div> in detailed rating chart widget
= 1.3.2 - Released on 11 july 2022 =
* TWEAK: add possibility to change border color for connected user owned review
= 1.3.1 - Released on 11 july 2022 =
* FIX: in rare cases, multiple notes for the same user might occur through multiple clicks
* FIX: round issue when calculating average note (taking into account half point notes)
= 1.3.0 - Released on 8 july 2022 =
* ADD: widget display note may be automatically integrated in archive page (after post title)
* ADD: widget display note may be automatically integrated in single page (at the beginning of post content)
* ADD: star color may be customized in admin, available also for chart bar in the widget detailed rating chart
= 1.2.2 - Released on 7 july 2022 =
* FIX: round issue when calculating average note
* FIX: files part-update-review.php and post-rating-and-review-admin.js were missing in the plugin files
* TWEAK: better management of microdata generation
= 1.2.1 - Released on 19 april 2022 =
* FIX: file prar-rating-plugins-compatibility.php was missing in the plugin files
= 1.2.0 - Released on 8 March 2022 =
* ADD: reviews can be modified by review author if enable in plugin options
* ADD: option in admin to forbid review modification in WP admin by another user that review author
* ADD: new review validation now handled through ajax
* TWEAK: in post edit page in admin, add rating given within list of reviews metabox
* TWEAK: in front, management of Wordpress error message through ajax when creating a review
* FIX: add compatibility with "Zeno Report Comments" plugin
= 1.1.1 - Released on 19 February 2022 =
* FIX: widget chart display was broken in post edit admin page due to prar-admin-style.css
= 1.1.0 - Released on 18 February 2022 =
* ADD: functionalities for email notification management to the post authors
* FIX: in the shortcode to display chart, rating could be present twice due to half notes
* FIX: in review management, comment text remained mandatory even if it was specified as optional in plugin options.
= 1.0.1 - Released on 15 February 2022 =
* TWEAK: added javascript event listener (`prar_rating_display_stars`) in order to display stars icons when a content is generated through ajax.
= 1.0.0 - Released on 13 February 2022 =
* Initial release.
