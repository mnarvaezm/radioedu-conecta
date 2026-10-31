<?php
/**
 * Profile page template of a centro for classic themes.
 *
 * Prints the theme header and footer around the profile only, so the theme's post title,
 * author/date meta and featured image are not repeated above the profile. The profile itself
 * comes from the_content (DirectorioShortcode::render_single_centro_profile()).
 *
 * Themes can override it with radioedu-conecta/single-radioedu_centro.php.
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="radioedu-profile-page">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>
<?php
get_footer();
