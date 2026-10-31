<?php
/**
 * Profile page template of a centro for classic themes.
 *
 * Prints the theme header and footer around the profile only, so the theme's post title,
 * author/date meta and featured image are not repeated above the profile. The profile itself
 * comes from the_content (DirectorioShortcode::render_single_centro_profile()).
 *
 * The profile goes in <main> unless the theme's header already opened one, so the page keeps a
 * single main landmark and the theme's skip link a target (DirectorioShortcode::profile_main_landmark()).
 *
 * Themes can override it with radioedu-conecta/single-radioedu_centro.php.
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

use RadioEdu\Conecta\Frontend\DirectorioShortcode;

defined( 'ABSPATH' ) || exit;

ob_start();
get_header();
$radioedu_conecta_header   = (string) ob_get_clean();
$radioedu_conecta_landmark = DirectorioShortcode::profile_main_landmark( $radioedu_conecta_header );
$radioedu_conecta_tag      = $radioedu_conecta_landmark['main'] ? 'main' : 'div';

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The theme's own header markup, printed unchanged after reading its landmarks.
echo $radioedu_conecta_header;
?>
<<?php echo tag_escape( $radioedu_conecta_tag ); ?> class="radioedu-profile-page"<?php echo '' !== $radioedu_conecta_landmark['id'] ? ' id="' . esc_attr( $radioedu_conecta_landmark['id'] ) . '"' : ''; ?>>
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</<?php echo tag_escape( $radioedu_conecta_tag ); ?>>
<?php
get_footer();
