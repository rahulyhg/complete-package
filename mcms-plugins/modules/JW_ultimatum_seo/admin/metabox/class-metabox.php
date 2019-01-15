<?php
/**
 * @package MCMSSEO\Admin
 */

/**
 * This class generates the metabox on the edit post / page as well as contains all page analysis functionality.
 */
class MCMSSEO_Metabox extends MCMSSEO_Meta {

	/**
	 * @var array
	 */
	private $options;

	/**
	 * @var MCMSSEO_Social_Admin
	 */
	protected $social_admin;

	/**
	 * @var MCMSSEO_Metabox_Analysis_SEO
	 */
	protected $analysis_seo;

	/**
	 * @var MCMSSEO_Metabox_Analysis_Readability
	 */
	protected $analysis_readability;

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'mcms_insert_post', array( $this, 'save_postdata' ) );
		add_action( 'edit_attachment', array( $this, 'save_postdata' ) );
		add_action( 'add_attachment', array( $this, 'save_postdata' ) );
		add_action( 'post_submitbox_start', array( $this, 'publish_box' ) );
		add_action( 'admin_init', array( $this, 'setup_page_analysis' ) );
		add_action( 'admin_init', array( $this, 'translate_meta_boxes' ) );
		add_action( 'admin_footer', array( $this, 'template_keyword_tab' ) );
		add_action( 'admin_footer', array( $this, 'template_generic_tab' ) );

		$this->options = MCMSSEO_Options::get_options( array( 'mcmsseo', 'mcmsseo_social' ) );

		// Check if one of the social settings is checked in the options, if so, initialize the social_admin object.
		if ( $this->options['opengraph'] === true || $this->options['twitter'] === true ) {
			$this->social_admin = new MCMSSEO_Social_Admin( $this->options );
		}

		$this->editor = new MCMSSEO_Metabox_Editor();
		$this->editor->register_hooks();

		$this->analysis_seo = new MCMSSEO_Metabox_Analysis_SEO();
		$this->analysis_readability = new MCMSSEO_Metabox_Analysis_Readability();
	}

	/**
	 * Translate text strings for use in the meta box
	 *
	 * IMPORTANT: if you want to add a new string (option) somewhere, make sure you add that array key to
	 * the main meta box definition array in the class MCMSSEO_Meta() as well!!!!
	 */
	public static function translate_meta_boxes() {
		self::$meta_fields['general']['snippetpreview']['title']       = __( 'Snippet editor', 'mandarincms-seo' );
		self::$meta_fields['general']['snippetpreview']['help']        = sprintf( __( 'This is a rendering of what this post might look like in Google\'s search results. %sLearn more about the Snippet Preview%s.', 'mandarincms-seo' ), '<a target="_blank" href="https://jiiworks.net/snippet-preview">', '</a>' );
		self::$meta_fields['general']['snippetpreview']['help-button'] = __( 'Show information about the snippet editor', 'mandarincms-seo' );

		self::$meta_fields['general']['pageanalysis']['title']       = __( 'Analysis', 'mandarincms-seo' );
		self::$meta_fields['general']['pageanalysis']['help']        = sprintf( __( 'This is the content analysis, a collection of content checks that analyze the content of your page. %sLearn more about the Content Analysis Tool%s.', 'mandarincms-seo' ), '<a target="_blank" href="https://jiiworks.net/content-analysis">', '</a>' );
		self::$meta_fields['general']['pageanalysis']['help-button'] = __( 'Show information about the content analysis', 'mandarincms-seo' );

		self::$meta_fields['general']['focuskw_text_input']['title']       = __( 'Focus keyword', 'mandarincms-seo' );
		self::$meta_fields['general']['focuskw_text_input']['label']       = __( 'Enter a focus keyword', 'mandarincms-seo' );
		self::$meta_fields['general']['focuskw_text_input']['help']        = sprintf( __( 'Pick the main keyword or keyphrase that this post/page is about. %sLearn more about the Focus Keyword%s.', 'mandarincms-seo' ), '<a target="_blank" href="https://jiiworks.net/focus-keyword">', '</a>' );
		self::$meta_fields['general']['focuskw_text_input']['help-button'] = __( 'Show information about the focus keyword', 'mandarincms-seo' );

		self::$meta_fields['general']['title']['title']       = __( 'SEO title', 'mandarincms-seo' );

		self::$meta_fields['general']['metadesc']['title']       = __( 'Meta description', 'mandarincms-seo' );

		self::$meta_fields['general']['metakeywords']['title']       = __( 'Meta keywords', 'mandarincms-seo' );
		self::$meta_fields['general']['metakeywords']['label']       = __( 'Enter the meta keywords', 'mandarincms-seo' );
		self::$meta_fields['general']['metakeywords']['description'] = __( 'If you type something above it will override your %smeta keywords template%s.', 'mandarincms-seo' );


		self::$meta_fields['advanced']['meta-robots-noindex']['title'] = __( 'Meta robots index', 'mandarincms-seo' );
		if ( '0' == get_option( 'blog_public' ) ) {
			self::$meta_fields['advanced']['meta-robots-noindex']['description'] = '<p class="error-message">' . __( 'Warning: even though you can set the meta robots setting here, the entire site is set to noindex in the sitewide privacy settings, so these settings won\'t have an effect.', 'mandarincms-seo' ) . '</p>';
		}
		self::$meta_fields['advanced']['meta-robots-noindex']['options']['0'] = __( 'Default for this post type, currently: %s', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-noindex']['options']['2'] = 'index';
		self::$meta_fields['advanced']['meta-robots-noindex']['options']['1'] = 'noindex';

		self::$meta_fields['advanced']['meta-robots-nofollow']['title']        = __( 'Meta robots follow', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-nofollow']['options']['0'] = 'follow';
		self::$meta_fields['advanced']['meta-robots-nofollow']['options']['1'] = 'nofollow';

		self::$meta_fields['advanced']['meta-robots-adv']['title']                   = __( 'Meta robots advanced', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-adv']['description']             = __( 'Advanced <code>meta</code> robots settings for this page.', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-adv']['options']['-']            = __( 'Site-wide default: %s', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-adv']['options']['none']         = __( 'None', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-adv']['options']['noodp']        = __( 'NO ODP', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-adv']['options']['noimageindex'] = __( 'No Image Index', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-adv']['options']['noarchive']    = __( 'No Archive', 'mandarincms-seo' );
		self::$meta_fields['advanced']['meta-robots-adv']['options']['nosnippet']    = __( 'No Snippet', 'mandarincms-seo' );

		self::$meta_fields['advanced']['bctitle']['title']       = __( 'Breadcrumbs Title', 'mandarincms-seo' );
		self::$meta_fields['advanced']['bctitle']['description'] = __( 'Title to use for this page in breadcrumb paths', 'mandarincms-seo' );

		self::$meta_fields['advanced']['canonical']['title']       = __( 'Canonical URL', 'mandarincms-seo' );
		self::$meta_fields['advanced']['canonical']['description'] = sprintf( __( 'The canonical URL that this page should point to, leave empty to default to permalink. %sCross domain canonical%s supported too.', 'mandarincms-seo' ), '<a target="_blank" href="http://googlewebmastercentral.blogspot.com/2009/12/handling-legitimate-cross-domain.html">', '</a>' );

		self::$meta_fields['advanced']['redirect']['title']       = __( '301 Redirect', 'mandarincms-seo' );
		self::$meta_fields['advanced']['redirect']['description'] = __( 'The URL that this page should redirect to.', 'mandarincms-seo' );

		do_action( 'mcmsseo_tab_translate' );
	}

	/**
	 * Test whether the metabox should be hidden either by choice of the admin or because
	 * the post type is not a public post type
	 *
	 * @since 1.5.0
	 *
	 * @param  string $post_type (optional) The post type to test, defaults to the current post post_type.
	 *
	 * @return  bool        Whether or not the meta box (and associated columns etc) should be hidden
	 */
	function is_metabox_hidden( $post_type = null ) {
		if ( ! isset( $post_type ) && ( isset( $GLOBALS['post'] ) && ( is_object( $GLOBALS['post'] ) && isset( $GLOBALS['post']->post_type ) ) ) ) {
			$post_type = $GLOBALS['post']->post_type;
		}

		if ( isset( $post_type ) ) {
			// Don't make static as post_types may still be added during the run.
			$cpts    = get_post_types( array( 'public' => true ), 'names' );
			$options = get_option( 'mcmsseo_titles' );

			return ( ( isset( $options[ 'hideeditbox-' . $post_type ] ) && $options[ 'hideeditbox-' . $post_type ] === true ) || in_array( $post_type, $cpts ) === false );
		}
		return false;
	}

	/**
	 * Sets up all the functionality related to the prominence of the page analysis functionality.
	 */
	public function setup_page_analysis() {
		if ( apply_filters( 'mcmsseo_use_page_analysis', true ) === true ) {
			add_action( 'post_submitbox_start', array( $this, 'publish_box' ) );
		}
	}

	/**
	 * Outputs the page analysis score in the Publish Box.
	 */
	public function publish_box() {
		if ( $this->is_metabox_hidden() === true ) {
			return;
		}

		$post = $this->get_metabox_post();
		if ( self::get_value( 'meta-robots-noindex', $post->ID ) === '1' ) {
			$score_label = 'noindex';
			$title       = __( 'Post is set to noindex.', 'mandarincms-seo' );
			$score_title = $title;
		}
		else {
			$score = self::get_value( 'linkdex', $post->ID );
			if ( $score === '' ) {
				$score_label = 'na';
				$title       = __( 'No focus keyword set.', 'mandarincms-seo' );
			}
			else {
				$score_label = MCMSSEO_Utils::translate_score( $score );
			}

			$score_title = MCMSSEO_Utils::translate_score( $score, false );
			if ( ! isset( $title ) ) {
				$title = $score_title;
			}
		}
	}

	/**
	 * Adds the Ultimatum SEO meta box to the edit boxes in the edit post / page  / cpt pages.
	 */
	public function add_meta_box() {
		$post_types = get_post_types( array( 'public' => true ) );

		if ( is_array( $post_types ) && $post_types !== array() ) {
			foreach ( $post_types as $post_type ) {
				if ( $this->is_metabox_hidden( $post_type ) === false ) {
					$product_title = 'Ultimatum SEO';
					if ( file_exists( MCMSSEO_PATH . 'premium/' ) ) {
						$product_title .= ' Premium';
					}

					add_meta_box( 'mcmsseo_meta', $product_title, array(
						$this,
						'meta_box',
					), $post_type, 'normal', apply_filters( 'mcmsseo_metabox_prio', 'high' ) );
				}
			}
		}
	}

	/**
	 * Pass variables to js for use with the post-scraper
	 *
	 * @return array
	 */
	public function localize_post_scraper_script() {
		$post      = $this->get_metabox_post();
		$permalink = '';

		if ( is_object( $post ) ) {
			$permalink = get_sample_permalink( $post->ID );
			$permalink = $permalink[0];
		}

		$post_formatter = new MCMSSEO_Metabox_Formatter(
			new MCMSSEO_Post_Metabox_Formatter( $post, MCMSSEO_Options::get_option( 'mcmsseo_titles' ), $permalink )
		);

		return $post_formatter->get_values();
	}

	/**
	 * Pass some variables to js for replacing variables.
	 */
	public function localize_replace_vars_script() {
		return array(
			'no_parent_text' => __( '(no parent)', 'mandarincms-seo' ),
			'replace_vars'   => $this->get_replace_vars(),
			'scope'          => $this->determine_scope(),
		);
	}

	/**
	 * Determines the scope based on the post type.
	 * This can be used by the replacevar module to determine if a replacement needs to be executed.
	 *
	 * @return string String decribing the current scope.
	 */
	private function determine_scope() {
		$post_type = get_post_type( $this->get_metabox_post() );

		if ( $post_type === 'page' ) {
			return 'page';
		}

		return 'post';
	}

	/**
	 * Pass some variables to js for the edit / post page overview, snippet preview, etc.
	 *
	 * @return  array
	 */
	public function localize_shortcode_module_script() {
		return array(
			'mcmsseo_filter_shortcodes_nonce' => mcms_create_nonce( 'mcmsseo-filter-shortcodes' ),
			'mcmsseo_shortcode_tags'          => $this->get_valid_shortcode_tags(),
		);
	}

	/**
	 * Output a tab in the Ultimatum SEO Metabox
	 *
	 * @param string $id      CSS ID of the tab.
	 * @param string $heading Heading for the tab.
	 * @param string $content Content of the tab. This content should be escaped.
	 */
	public function do_tab( $id, $heading, $content ) {
		?>
		<div id="mcmsseo_<?php echo esc_attr( $id ) ?>" class="mcmsseotab <?php echo esc_attr( $id ) ?>">
			<h4 class="mcmsseo-heading"><?php echo esc_html( $heading ); ?></h4>
			<table class="form-table">
				<?php echo $content ?>
			</table>
		</div>
	<?php
	}

	/**
	 * Output the meta box
	 */
	public function meta_box() {
		$content_sections = $this->get_content_sections();

		$helpcenter_tab = new MCMSSEO_Option_Tab( 'metabox', 'Meta box',
			array( 'video_url' => 'https://jiiworks.net/metabox-screencast' ) );

		$helpcenter = new MCMSSEO_Help_Center( 'metabox', $helpcenter_tab );
		$helpcenter->output_help_center();

		if ( ! defined( 'MCMSSEO_PREMIUM_FILE' ) ) {
			echo $this->get_buy_premium_link();
		}

		echo '<div class="mcmsseo-metabox-sidebar"><ul>';

		foreach ( $content_sections as $content_section ) {
			if ( $content_section->name === 'premium' ) {
				continue;
			}

			$content_section->display_link();
		}

		echo '</ul></div>';

		foreach ( $content_sections as $content_section ) {
			$content_section->display_content();
		}
	}

	/**
	 * Returns the relevant metabox sections for the current view.
	 *
	 * @return MCMSSEO_Metabox_Section[]
	 */
	private function get_content_sections() {
		$content_sections = array( $this->get_content_meta_section() );

		// Check if social_admin is an instance of MCMSSEO_Social_Admin.
		if ( $this->social_admin instanceof MCMSSEO_Social_Admin ) {
			$content_sections[] = $this->social_admin->get_meta_section();
		}

		if ( current_user_can( 'manage_options' ) || $this->options['disableadvanced_meta'] === false ) {
			$content_sections[] = $this->get_advanced_meta_section();
		}

		if ( ! defined( 'MCMSSEO_PREMIUM_FILE' ) ) {
			$content_sections[] = $this->get_buy_premium_section();
		}

		if ( has_action( 'mcmsseo_tab_header' ) || has_action( 'mcmsseo_tab_content' ) ) {
			$content_sections[] = $this->get_addons_meta_section();
		}

		return $content_sections;
	}

	/**
	 * Returns the metabox section for the content analysis.
	 *
	 * @return MCMSSEO_Metabox_Section
	 */
	private function get_content_meta_section() {
		$content = $this->get_tab_content( 'general' );

		$tabs = array();

		$tabs[] = new MCMSSEO_Metabox_Form_Tab(
			'content',
			$content,
			__( '', 'mandarincms-seo' ),
			array(
				'tab_class' => 'ultimatum-seo__remove-tab',
			)
		);

		$tabs[] = new Metabox_Add_Keyword_Tab();

		return new MCMSSEO_Metabox_Tab_Section(
			'content',
			'<span class="screen-reader-text">' . __( 'Content optimization', 'mandarincms-seo' ) . '</span><span class="yst-traffic-light-container">' . $this->traffic_light_svg() . '</span>',
			$tabs,
			array(
				'link_aria_label' => __( 'Content optimization', 'mandarincms-seo' ),
				'link_class'      => 'ultimatum-tooltip ultimatum-tooltip-e',
			)
		);
	}

	/**
	 * Returns the metabox section for the advanced settings.
	 *
	 * @return MCMSSEO_Metabox_Section
	 */
	private function get_advanced_meta_section() {
		$content = $this->get_tab_content( 'advanced' );

		$tab = new MCMSSEO_Metabox_Form_Tab(
			'advanced',
			$content,
			__( 'Advanced', 'mandarincms-seo' ),
			array(
				'single' => true,
			)
		);

		return new MCMSSEO_Metabox_Tab_Section(
			'advanced',
			'<span class="screen-reader-text">' . __( 'Advanced', 'mandarincms-seo' ) . '</span><span class="dashicons dashicons-admin-generic"></span>',
			array( $tab ),
			array(
				'link_aria_label' => __( 'Advanced', 'mandarincms-seo' ),
				'link_class'      => 'ultimatum-tooltip ultimatum-tooltip-e',
			)
		);
	}

	/**
	 * Returns a link to activate the Buy Premium tab.
	 *
	 * @return string
	 */
	private function get_buy_premium_link() {
		
	}

	/**
	 * Returns the metabox section for the Premium section.
	 *
	 * @return MCMSSEO_Metabox_Section
	 */
	private function get_buy_premium_section() {
	}

	/**
	 * Returns a metabox section dedicated to hosting metabox tabs that have been added by other modules through the
	 * `mcmsseo_tab_header` and `mcmsseo_tab_content` actions.
	 *
	 * @return MCMSSEO_Metabox_Section
	 */
	private function get_addons_meta_section() {
		return new MCMSSEO_Metabox_Addon_Tab_Section(
			'addons',
			'<span class="screen-reader-text">' . __( 'Add-ons', 'mandarincms-seo' ) . '</span><span class="dashicons dashicons-admin-modules"></span>',
			array(),
			array(
				'link_aria_label' => __( 'Add-ons', 'mandarincms-seo' ),
				'link_class'      => 'ultimatum-tooltip ultimatum-tooltip-e',
			)
		);
	}

	/**
	 * Gets the table contents for the metabox tab.
	 *
	 * @param string $tab_name Tab for which to retrieve the field definitions.
	 *
	 * @return string
	 */
	private function get_tab_content( $tab_name ) {
		$content = '';
		foreach ( $this->get_meta_field_defs( $tab_name ) as $key => $meta_field ) {
			$content .= $this->do_meta_box( $meta_field, $key );
		}
		unset( $key, $meta_field );

		return $content;
	}

	/**
	 * Adds a line in the meta box
	 *
	 * @todo [JRF] check if $class is added appropriately everywhere
	 *
	 * @param   array  $meta_field_def Contains the vars based on which output is generated.
	 * @param   string $key            Internal key (without prefix).
	 *
	 * @return  string
	 */
	function do_meta_box( $meta_field_def, $key = '' ) {
		$content      = '';
		$esc_form_key = esc_attr( self::$form_prefix . $key );
		$meta_value   = self::get_value( $key, $this->get_metabox_post()->ID );

		$class = '';
		if ( isset( $meta_field_def['class'] ) && $meta_field_def['class'] !== '' ) {
			$class = ' ' . $meta_field_def['class'];
		}

		$placeholder = '';
		if ( isset( $meta_field_def['placeholder'] ) && $meta_field_def['placeholder'] !== '' ) {
			$placeholder = $meta_field_def['placeholder'];
		}

		$aria_describedby = $description = '';
		if ( isset( $meta_field_def['description'] ) ) {
			$aria_describedby = ' aria-describedby="' . $esc_form_key . '-desc"';
			$description = '<p id="' . $esc_form_key . '-desc">' . $meta_field_def['description'] . '</p>';
		}

		switch ( $meta_field_def['type'] ) {
			case 'pageanalysis':
				$content .= '<div id="pageanalysis">';
				$content .= '<section class="ultimatum-section" id="mcmsseo-pageanalysis-section">';
				$content .= '<h3 class="ultimatum-section__heading ultimatum-section__heading-icon ultimatum-section__heading-icon-list">'. __( 'Analysis', 'mandarincms-seo' ) .'</h3>';
				$content .= '<div id="mcmsseo-pageanalysis"></div>';
				$content .= '<div id="ultimatum-seo-content-analysis"></div>';
				$content .= '</section>';
				$content .= '</div>';
				break;
			case 'snippetpreview':
				$content .= '<div id="mcmsseosnippet" class="mcmsseosnippet"></div>';
				break;
			case 'focuskeyword':
				if ( $placeholder !== '' ) {
					$placeholder = ' placeholder="' . esc_attr( $placeholder ) . '"';
				}

				$content .= '<div id="mcmsseofocuskeyword">';
				$content .= '<section class="ultimatum-section" id="mcmsseo-focuskeyword-section">';
				$content .= '<h3 class="ultimatum-section__heading ultimatum-section__heading-icon ultimatum-section__heading-icon-key">' . esc_html( $meta_field_def['title'] ) . '</h3>';
			    $content .= '<label for="' . $esc_form_key . '" class="screen-reader-text">' . esc_html( $meta_field_def['label'] ) . '</label>';
				$content .= '<input type="text"' . $placeholder . ' id="' . $esc_form_key . '" autocomplete="off" name="' . $esc_form_key . '" value="' . esc_attr( $meta_value ) . '" class="large-text' . $class . '"/><br />';
				$content .= '</section>';
				$content .= '</div>';
				break;
			case 'metakeywords':
				$content .= '<div id="mcmsseometakeywords">';
				$content .= '<section class="ultimatum-section" id="mcmsseo-metakeywords-section">';
				$content .= '<h3 class="ultimatum-section__heading ultimatum-section__heading-icon ultimatum-section__heading-icon-edit">' . esc_html( $meta_field_def['title'] ) . '</h3>';
			    $content .= '<label for="' . $esc_form_key . '" class="screen-reader-text">' . esc_html( $meta_field_def['label'] ) . '</label>';
				$content .= '<input type="text" id="' . $esc_form_key . '" name="' . $esc_form_key . '" value="' . esc_attr( $meta_value ) . '" class="large-text' . $class . '"' . $aria_describedby . '/><br />';
				$content .= $description;
				$content .= '</section>';
				$content .= '</div>';
				break;
			case 'text':
				$ac = '';
				if ( isset( $meta_field_def['autocomplete'] ) && $meta_field_def['autocomplete'] === false ) {
					$ac = 'autocomplete="off" ';
				}
				if ( $placeholder !== '' ) {
					$placeholder = ' placeholder="' . esc_attr( $placeholder ) . '"';
				}
				$content .= '<input type="text"' . $placeholder . 'id="' . $esc_form_key . '" ' . $ac . 'name="' . $esc_form_key . '" value="' . esc_attr( $meta_value ) . '" class="large-text' . $class . '"' . $aria_describedby . '/><br />';
				break;

			case 'textarea':
				$rows = 3;
				if ( isset( $meta_field_def['rows'] ) && $meta_field_def['rows'] > 0 ) {
					$rows = $meta_field_def['rows'];
				}
				$content .= '<textarea class="large-text' . $class . '" rows="' . esc_attr( $rows ) . '" id="' . $esc_form_key . '" name="' . $esc_form_key . '"' . $aria_describedby . '>' . esc_textarea( $meta_value ) . '</textarea>';
				break;

			case 'hidden':
				$content .= '<input type="hidden" id="' . $esc_form_key . '" name="' . $esc_form_key . '" value="' . esc_attr( $meta_value ) . '"/><br />';
				break;
			case 'select':
				if ( isset( $meta_field_def['options'] ) && is_array( $meta_field_def['options'] ) && $meta_field_def['options'] !== array() ) {
					$content .= '<select name="' . $esc_form_key . '" id="' . $esc_form_key . '" class="ultimatum' . $class . '">';
					foreach ( $meta_field_def['options'] as $val => $option ) {
						$selected = selected( $meta_value, $val, false );
						$content .= '<option ' . $selected . ' value="' . esc_attr( $val ) . '">' . esc_html( $option ) . '</option>';
					}
					unset( $val, $option, $selected );
					$content .= '</select>';
				}
				break;

			case 'multiselect':
				if ( isset( $meta_field_def['options'] ) && is_array( $meta_field_def['options'] ) && $meta_field_def['options'] !== array() ) {

					// Set $meta_value as $selected_arr.
					$selected_arr = $meta_value;

					// If the multiselect field is 'meta-robots-adv' we should explode on ,.
					if ( 'meta-robots-adv' === $key ) {
						$selected_arr = explode( ',', $meta_value );
					}

					if ( ! is_array( $selected_arr ) ) {
						$selected_arr = (array) $selected_arr;
					}

					$options_count = count( $meta_field_def['options'] );

					// This select now uses Select2.
					$content .= '<select multiple="multiple" size="' . esc_attr( $options_count ) . '" name="' . $esc_form_key . '[]" id="' . $esc_form_key . '" class="ultimatum' . $class . '"' . $aria_describedby . '>';
					foreach ( $meta_field_def['options'] as $val => $option ) {
						$selected = '';
						if ( in_array( $val, $selected_arr ) ) {
							$selected = ' selected="selected"';
						}
						$content .= '<option ' . $selected . ' value="' . esc_attr( $val ) . '">' . esc_html( $option ) . '</option>';
					}
					$content .= '</select>';
					unset( $val, $option, $selected, $selected_arr, $options_count );
				}
				break;

			case 'checkbox':
				$checked = checked( $meta_value, 'on', false );
				$expl    = ( isset( $meta_field_def['expl'] ) ) ? esc_html( $meta_field_def['expl'] ) : '';
				$content .= '<label for="' . $esc_form_key . '"><input type="checkbox" id="' . $esc_form_key . '" name="' . $esc_form_key . '" ' . $checked . ' value="on" class="ultimatum' . $class . '"' . $aria_describedby . '/> ' . $expl . '</label><br />';
				unset( $checked, $expl );
				break;

			case 'radio':
				if ( isset( $meta_field_def['options'] ) && is_array( $meta_field_def['options'] ) && $meta_field_def['options'] !== array() ) {
					foreach ( $meta_field_def['options'] as $val => $option ) {
						$checked = checked( $meta_value, $val, false );
						$content .= '<input type="radio" ' . $checked . ' id="' . $esc_form_key . '_' . esc_attr( $val ) . '" name="' . $esc_form_key . '" value="' . esc_attr( $val ) . '"/> <label for="' . $esc_form_key . '_' . esc_attr( $val ) . '">' . esc_html( $option ) . '</label> ';
					}
					unset( $val, $option, $checked );
				}
				break;

			case 'upload':
				$content .= '<input id="' . $esc_form_key . '" type="text" size="36" class="' . $class . '" name="' . $esc_form_key . '" value="' . esc_attr( $meta_value ) . '"' . $aria_describedby . ' />';
				$content .= '<input id="' . $esc_form_key . '_button" class="mcmsseo_image_upload_button button" type="button" value="' . esc_attr__( 'Upload Image', 'mandarincms-seo' ) . '" />';
				break;
		}


		$html = '';
		if ( $content === '' ) {
			$content = apply_filters( 'mcmsseo_do_meta_box_field_' . $key, $content, $meta_value, $esc_form_key, $meta_field_def, $key );
		}

		if ( $content !== '' ) {

			$label = esc_html( $meta_field_def['title'] );
			if ( in_array( $meta_field_def['type'], array(
					'radio',
					'checkbox',
				), true ) === false
			) {
				$label = '<label for="' . $esc_form_key . '">' . $label . '</label>';
			}

			$help_button = $help_panel = '';
			if ( isset( $meta_field_def['help'] ) && $meta_field_def['help'] !== '' ) {
				$help = new MCMSSEO_Admin_Help_Panel( $key, $meta_field_def['help-button'], $meta_field_def['help'] );
				$help_button = $help->get_button_html();
				$help_panel  = $help->get_panel_html();
			}
			if ( in_array( $meta_field_def['type'], array(
					'snippetpreview',
					'pageanalysis',
					'focuskeyword',
					'metakeywords',
				), true )
			) {
				return $this->create_content_box( $content, $meta_field_def['type'], $help_button, $help_panel );
			}

			if ( $meta_field_def['type'] === 'hidden' ) {
				$html = '<tr class="mcmsseo_hidden"><td colspan="2">' . $content . '</td></tr>';
			}
			else {
				$html = '
					<tr>
						<th scope="row">' . $label . $help_button . '</th>
						<td>' . $help_panel;

				$html .= $content . $description;

				$html .= '
					</td>
				</tr>';
			}
		}

		return $html;
	}

	/**
	 * Creates a sections specific row.
	 *
	 * @param string $content          The content to show.
	 * @param string $hidden_help_name Escaped form key name.
	 * @param string $help_button      The help button.
	 * @param string $help_panel       The help text.
	 *
	 * @return string
	 */
	private function create_content_box( $content, $hidden_help_name, $help_button, $help_panel ) {
		$html = '<tr><td>';
		$html .= $content;
		$html .= '<div class="mcmsseo_hidden" id="help-ultimatum-'. $hidden_help_name. '">' . $help_button . $help_panel . '</div>';
		$html .= '</td></tr>';
		return $html;
	}

	/**
	 * Save the MCMS SEO metadata for posts.
	 *
	 * @internal $_POST parameters are validated via sanitize_post_meta()
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return  bool|void   Boolean false if invalid save post request
	 */
	function save_postdata( $post_id ) {
		// Bail if this is a multisite installation and the site has been switched.
		if ( is_multisite() && ms_is_switched() ) {
			return false;
		}

		if ( $post_id === null ) {
			return false;
		}

		if ( mcms_is_post_revision( $post_id ) ) {
			$post_id = mcms_is_post_revision( $post_id );
		}

		/**
		 * Determine we're not accidentally updating a different post.
		 * We can't use filter_input here as the ID isn't available at this point, other than in the $_POST data.
		 */
		if ( ! isset( $_POST['ID'] ) || $post_id !== (int) $_POST['ID'] ) {
			return false;
		}

		clean_post_cache( $post_id );
		$post = get_post( $post_id );

		if ( ! is_object( $post ) ) {
			// Non-existent post.
			return false;
		}

		do_action( 'mcmsseo_save_compare_data', $post );

		$meta_boxes = apply_filters( 'mcmsseo_save_metaboxes', array() );
		$meta_boxes = array_merge( $meta_boxes, $this->get_meta_field_defs( 'general', $post->post_type ), $this->get_meta_field_defs( 'advanced' ) );

		foreach ( $meta_boxes as $key => $meta_box ) {

			// If analysis is disabled remove that analysis score value from the DB.
			if ( $this->is_meta_value_disabled( $key ) ) {
				self::delete( $key, $post_id );
				continue;
			}

			$data = null;
			if ( 'checkbox' === $meta_box['type'] ) {
				$data = isset( $_POST[ self::$form_prefix . $key ] ) ? 'on' : 'off';
			}
			else {
				if ( isset( $_POST[ self::$form_prefix . $key ] ) ) {
					$data = $_POST[ self::$form_prefix . $key ];
				}
			}
			if ( isset( $data ) ) {
				self::set_value( $key, $data, $post_id );
			}
		}

		do_action( 'mcmsseo_saved_postdata' );
	}

	/**
	 * Determines if the given meta value key is disabled
	 *
	 * @param string $key The key of the meta value.
	 * @return bool Whether the given meta value key is disabled.
	 */
	public function is_meta_value_disabled( $key ) {
		if ( 'linkdex' === $key && ! $this->analysis_seo->is_enabled() ) {
			return true;
		}

		if ( 'content_score' === $key && ! $this->analysis_readability->is_enabled() ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueues all the needed JS and CSS.
	 *
	 * @todo [JRF => whomever] create css/metabox-mp6.css file and add it to the below allowed colors array when done
	 */
	public function enqueue() {
		global $pagenow;

		$asset_manager = new MCMSSEO_Admin_Asset_Manager();

		$is_editor = self::is_post_overview( $pagenow ) || self::is_post_edit( $pagenow );

		/* Filter 'mcmsseo_always_register_metaboxes_on_admin' documented in mcmsseo-main.php */
		if ( ( ! $is_editor && apply_filters( 'mcmsseo_always_register_metaboxes_on_admin', false ) === false ) || $this->is_metabox_hidden() === true
		) {
			return;
		}

		if ( self::is_post_overview( $pagenow ) ) {
			$asset_manager->enqueue_style( 'edit-page' );
		}
		else {

			if ( 0 != get_queried_object_id() ) {
				mcms_enqueue_media( array( 'post' => get_queried_object_id() ) ); // Enqueue files needed for upload functionality.
			}

			$asset_manager->enqueue_style( 'metabox-css' );
			$asset_manager->enqueue_style( 'scoring' );
			$asset_manager->enqueue_style( 'snippet' );
			$asset_manager->enqueue_style( 'select2' );
			$asset_manager->enqueue_style( 'kb-search' );

			$asset_manager->enqueue_script( 'metabox' );
			$asset_manager->enqueue_script( 'admin-media' );

			$asset_manager->enqueue_script( 'post-scraper' );
			$asset_manager->enqueue_script( 'replacevar-module' );
			$asset_manager->enqueue_script( 'shortcode-module' );

			mcms_enqueue_script( 'jquery-ui-autocomplete' );

			mcms_localize_script( MCMSSEO_Admin_Asset_Manager::PREFIX . 'admin-media', 'mcmsseoMediaL10n', $this->localize_media_script() );
			mcms_localize_script( MCMSSEO_Admin_Asset_Manager::PREFIX . 'post-scraper', 'mcmsseoPostScraperL10n', $this->localize_post_scraper_script() );
			mcms_localize_script( MCMSSEO_Admin_Asset_Manager::PREFIX . 'replacevar-module', 'mcmsseoReplaceVarsL10n', $this->localize_replace_vars_script() );
			mcms_localize_script( MCMSSEO_Admin_Asset_Manager::PREFIX . 'shortcode-module', 'mcmsseoShortcodeModuleL10n', $this->localize_shortcode_module_script() );

			mcms_localize_script( MCMSSEO_Admin_Asset_Manager::PREFIX . 'metabox', 'mcmsseoAdminL10n', MCMSSEO_Help_Center::get_translated_texts() );
			mcms_localize_script( MCMSSEO_Admin_Asset_Manager::PREFIX . 'metabox', 'mcmsseoSelect2Locale', MCMSSEO_Utils::get_language( get_locale() ) );

			if ( post_type_supports( get_post_type(), 'thumbnail' ) ) {
				$asset_manager->enqueue_style( 'featured-image' );

				$asset_manager->enqueue_script( 'featured-image' );

				$featured_image_l10 = array( 'featured_image_notice' => __( 'The featured image should be at least 200x200 pixels to be picked up by Facebook and other social media sites.', 'mandarincms-seo' ) );
				mcms_localize_script( MCMSSEO_Admin_Asset_Manager::PREFIX . 'metabox', 'mcmsseoFeaturedImageL10n', $featured_image_l10 );
			}
		}
	}

	/**
	 * Pass some variables to js for upload module.
	 *
	 * @return  array
	 */
	public function localize_media_script() {
		return array(
			'choose_image' => __( 'Use Image', 'mandarincms-seo' ),
		);
	}

	/**
	 * Returns post in metabox context
	 *
	 * @returns MCMS_Post|array
	 */
	protected function get_metabox_post() {
		if ( $post = filter_input( INPUT_GET, 'post' ) ) {
			$post_id = (int) MCMSSEO_Utils::validate_int( $post );

			return get_post( $post_id );
		}


		if ( isset( $GLOBALS['post'] ) ) {
			return $GLOBALS['post'];
		}

		return array();
	}



	/**
	 * Returns an array with shortcode tags for all registered shortcodes.
	 *
	 * @return array
	 */
	private function get_valid_shortcode_tags() {
		$shortcode_tags = array();

		foreach ( $GLOBALS['shortcode_tags'] as $tag => $description ) {
			array_push( $shortcode_tags, $tag );
		}

		return $shortcode_tags;
	}

	/**
	 * Prepares the replace vars for localization.
	 *
	 * @return array replace vars
	 */
	private function get_replace_vars() {
		$post = $this->get_metabox_post();

		$cached_replacement_vars = array();

		$vars_to_cache = array(
			'date',
			'id',
			'sitename',
			'sitedesc',
			'sep',
			'page',
			'currenttime',
			'currentdate',
			'currentday',
			'currentmonth',
			'currentyear',
		);

		foreach ( $vars_to_cache as $var ) {
			$cached_replacement_vars[ $var ] = mcmsseo_replace_vars( '%%' . $var . '%%', $post );
		}

		// Merge custom replace variables with the MandarinCMS ones.
		return array_merge( $cached_replacement_vars, $this->get_custom_replace_vars( $post ) );
	}

	/**
	 * Gets the custom replace variables for custom taxonomies and fields.
	 *
	 * @param MCMS_Post $post The post to check for custom taxonomies and fields.
	 *
	 * @return array Array containing all the replacement variables.
	 */
	private function get_custom_replace_vars( $post ) {
		return array(
			'custom_fields' => $this->get_custom_fields_replace_vars( $post ),
			'custom_taxonomies' => $this->get_custom_taxonomies_replace_vars( $post ),
		 );
	}

	/**
	 * Gets the custom replace variables for custom taxonomies.
	 *
	 * @param MCMS_Post $post The post to check for custom taxonomies.
	 *
	 * @return array Array containing all the replacement variables.
	 */
	private function get_custom_taxonomies_replace_vars( $post ) {
		$taxonomies = get_object_taxonomies( $post, 'objects' );
		$custom_replace_vars = array();

		foreach ( $taxonomies as $taxonomy_name => $taxonomy ) {

			if ( is_string( $taxonomy ) ) { // If attachment, see https://core.trac.mandarincms.com/ticket/37368 .
				$taxonomy_name = $taxonomy;
				$taxonomy      = get_taxonomy( $taxonomy_name );
			}

			if ( $taxonomy->_builtin && $taxonomy->public ) {
				continue;
			}

			$custom_replace_vars[ $taxonomy_name ] = array(
				'name' => $taxonomy->name,
				'description' => $taxonomy->description,
			);
		}

		return $custom_replace_vars;
	}

	/**
	 * Gets the custom replace variables for custom fields.
	 *
	 * @param MCMS_Post $post The post to check for custom fields.
	 *
	 * @return array Array containing all the replacement variables.
	 */
	private function get_custom_fields_replace_vars( $post ) {
		$custom_replace_vars = array();

		// If no post object is passed, return the empty custom_replace_vars array.
		if ( ! is_object( $post ) ) {
			return $custom_replace_vars;
		}

		$custom_fields = get_post_custom( $post->ID );

		foreach ( $custom_fields as $custom_field_name => $custom_field ) {
			if ( substr( $custom_field_name, 0, 1 ) === '_' ) {
				continue;
			}

			$custom_replace_vars[ $custom_field_name ] = $custom_field[0];
		}

		return $custom_replace_vars;
	}


	/**
	 * Return the SVG for the traffic light in the metabox.
	 */
	public function traffic_light_svg() {
		return <<<SVG
<svg class="yst-traffic-light init" version="1.1" xmlns:x="&ns_extend;" xmlns:i="&ns_ai;" xmlns:graph="&ns_graphs;"
	 xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:a="http://ns.adobe.com/AdobeSVGViewerExtensions/3.0/"
	 x="0px" y="0px" viewBox="0 0 30 47" enable-background="new 0 0 30 47" xml:space="preserve">
<g id="BG_1_">
</g>
<g id="traffic_light">
	<g>
		<g>
			<g>
				<path fill="#5B2942" d="M22,0H8C3.6,0,0,3.6,0,7.9v31.1C0,43.4,3.6,47,8,47h14c4.4,0,8-3.6,8-7.9V7.9C30,3.6,26.4,0,22,0z
					 M27.5,38.8c0,3.1-2.6,5.7-5.8,5.7H8.3c-3.2,0-5.8-2.5-5.8-5.7V8.3c0-1.5,0.6-2.9,1.7-4c1.1-1,2.5-1.6,4.1-1.6h13.4
					c1.5,0,3,0.6,4.1,1.6c1.1,1.1,1.7,2.5,1.7,4V38.8z"/>
			</g>
			<g class="traffic-light-color traffic-light-red">
				<ellipse fill="#C8C8C8" cx="15" cy="23.5" rx="5.7" ry="5.6"/>
				<ellipse fill="#DC3232" cx="15" cy="10.9" rx="5.7" ry="5.6"/>
				<ellipse fill="#C8C8C8" cx="15" cy="36.1" rx="5.7" ry="5.6"/>
			</g>
			<g class="traffic-light-color traffic-light-orange">
				<ellipse fill="#F49A00" cx="15" cy="23.5" rx="5.7" ry="5.6"/>
				<ellipse fill="#C8C8C8" cx="15" cy="10.9" rx="5.7" ry="5.6"/>
				<ellipse fill="#C8C8C8" cx="15" cy="36.1" rx="5.7" ry="5.6"/>
			</g>
			<g class="traffic-light-color traffic-light-green">
				<ellipse fill="#C8C8C8" cx="15" cy="23.5" rx="5.7" ry="5.6"/>
				<ellipse fill="#C8C8C8" cx="15" cy="10.9" rx="5.7" ry="5.6"/>
				<ellipse fill="#63B22B" cx="15" cy="36.1" rx="5.7" ry="5.6"/>
			</g>
			<g class="traffic-light-color traffic-light-empty">
				<ellipse fill="#C8C8C8" cx="15" cy="23.5" rx="5.7" ry="5.6"/>
				<ellipse fill="#C8C8C8" cx="15" cy="10.9" rx="5.7" ry="5.6"/>
				<ellipse fill="#C8C8C8" cx="15" cy="36.1" rx="5.7" ry="5.6"/>
			</g>
			<g class="traffic-light-color traffic-light-init">
				<ellipse fill="#5B2942" cx="15" cy="23.5" rx="5.7" ry="5.6"/>
				<ellipse fill="#5B2942" cx="15" cy="10.9" rx="5.7" ry="5.6"/>
				<ellipse fill="#5B2942" cx="15" cy="36.1" rx="5.7" ry="5.6"/>
			</g>
		</g>
	</g>
</g>
</svg>
SVG;

	}

	/**
	 * Generic tab.
	 */
	public function template_generic_tab() {
		// This template belongs to the post scraper so don't echo it if it isn't enqueued.
		if ( ! mcms_script_is( MCMSSEO_Admin_Asset_Manager::PREFIX . 'post-scraper' ) ) {
			return;
		}

		echo '<script type="text/html" id="tmpl-generic_tab">
				<li class="<# if ( data.classes ) { #>{{data.classes}}<# } #><# if ( data.active ) { #> active<# } #>">
					<a class="mcmsseo_tablink" href="#mcmsseo_generic" data-score="{{data.score}}">
						<span class="mcmsseo-score-icon {{data.score}}"></span>
						<span class="mcmsseo-tab-prefix">{{data.prefix}}</span>
						<span class="mcmsseo-tab-label">{{data.label}}</span>
						<span class="screen-reader-text mcmsseo-generic-tab-textual-score">{{data.scoreText}}</span>
					</a>
					<# if ( data.hideable ) { #>
						<button type="button" class="remove-tab" aria-label="{{data.removeLabel}}"><span>x</span></button>
					<# } #>
				</li>
			</script>';
	}

	/**
	 * Keyword tab for enabling analysis of multiple keywords.
	 */
	public function template_keyword_tab() {
		// This template belongs to the post scraper so don't echo it if it isn't enqueued.
		if ( ! mcms_script_is( MCMSSEO_Admin_Asset_Manager::PREFIX . 'post-scraper' ) ) {
			return;
		}

		echo '<script type="text/html" id="tmpl-keyword_tab">
				<li class="<# if ( data.classes ) { #>{{data.classes}}<# } #><# if ( data.active ) { #> active<# } #>">
					<a class="mcmsseo_tablink" href="#mcmsseo_content" data-keyword="{{data.keyword}}" data-score="{{data.score}}">
						<span class="mcmsseo-score-icon {{data.score}}"></span>
						<span class="mcmsseo-tab-prefix">{{data.prefix}}</span>
						<em class="mcmsseo-keyword">{{data.label}}</em>
						<span class="screen-reader-text mcmsseo-keyword-tab-textual-score">{{data.scoreText}}</span>
					</a>
					<# if ( data.hideable ) { #>
						<button type="button" class="remove-keyword" aria-label="{{data.removeLabel}}"><span>x</span></button>
					<# } #>
				</li>
			</script>';
	}

	/**
	 * @param string $page The page to check for the post overview page.
	 *
	 * @return bool Whether or not the given page is the post overview page.
	 */
	public static function is_post_overview( $page ) {
		return 'edit.php' === $page;
	}

	/**
	 * @param string $page The page to check for the post edit page.
	 *
	 * @return bool Whether or not the given page is the post edit page.
	 */
	public static function is_post_edit( $page ) {
		return 'post.php' === $page
			|| 'post-new.php' === $page;
	}

	/********************** DEPRECATED METHODS **********************/

	/**
	 * Adds the Ultimatum SEO box
	 *
	 * @deprecated 1.4.24
	 * @deprecated use MCMSSEO_Metabox::add_meta_box()
	 * @see        MCMSSEO_Meta::add_meta_box()
	 */
	public function add_custom_box() {
		_deprecated_function( __METHOD__, 'MCMSSEO 1.4.24', 'MCMSSEO_Metabox::add_meta_box()' );
		$this->add_meta_box();
	}

	/**
	 * Retrieve the meta boxes for the given post type.
	 *
	 * @deprecated 1.5.0
	 * @deprecated use MCMSSEO_Meta::get_meta_field_defs()
	 * @see        MCMSSEO_Meta::get_meta_field_defs()
	 *
	 * @param  string $post_type The post type for which to get the meta fields.
	 *
	 * @return  array
	 */
	public function get_meta_boxes( $post_type = 'post' ) {
		_deprecated_function( __METHOD__, 'MCMSSEO 1.5.0', 'MCMSSEO_Meta::get_meta_field_defs()' );

		return $this->get_meta_field_defs( 'general', $post_type );
	}

	/**
	 * Pass some variables to js
	 *
	 * @deprecated 1.5.0
	 * @deprecated use MCMSSEO_Meta::localize_script()
	 * @see        MCMSSEO_Meta::localize_script()
	 */
	public function script() {
		_deprecated_function( __METHOD__, 'MCMSSEO 1.5.0', 'MCMSSEO_Meta::localize_script()' );

		return $this->localize_script();
	}

	/**
	 * @deprecated 3.0 Removed, use javascript functions instead
	 *
	 * @param string $string Deprecated.
	 *
	 * @return string
	 */
	public function strtolower_utf8( $string ) {
		_deprecated_function( 'MCMSSEO_Metabox::strtolower_utf8', 'MCMSSEO 3.0', 'use javascript instead' );

		return $string;
	}

	/**
	 * @deprecated 3.0 Removed.
	 *
	 * @return array
	 */
	public function localize_script() {
		_deprecated_function( 'MCMSSEO_Metabox::localize_script', 'MCMSSEO 3.0' );

		return array();
	}

	/**
	 * @deprecated 3.0 Removed, use javascript functions instead.
	 *
	 * @return string
	 */
	public function snippet() {
		_deprecated_function( 'MCMSSEO_Metabox::snippet', 'MCMSSEO 3.0', 'use javascript instead' );

		return '';
	}

	/**
	 * @deprecated 3.0 Use MCMSSEO_Meta_Columns::posts_filter_dropdown instead.
	 */
	public function posts_filter_dropdown() {
		_deprecated_function( 'MCMSSEO_Metabox::posts_filter_dropdown', 'MCMSSEO 3.0', 'MCMSSEO_Metabox_Columns::posts_filter_dropdown' );

		/** @var MCMSSEO_Meta_Columns $meta_columns */
		$meta_columns = $GLOBALS['mcmsseo_meta_columns'];
		$meta_columns->posts_filter_dropdown();
	}

	/**
	 * @deprecated 3.0 Use MCMSSEO_Meta_Columns::column_heading instead.
	 *
	 * @param array $columns Already existing columns.
	 *
	 * @return array
	 */
	public function column_heading( $columns ) {
		_deprecated_function( 'MCMSSEO_Metabox::column_heading', 'MCMSSEO 3.0', 'MCMSSEO_Metabox_Columns::column_heading' );

		/** @var MCMSSEO_Meta_Columns $meta_columns */
		$meta_columns = $GLOBALS['mcmsseo_meta_columns'];
		return $meta_columns->column_heading( $columns );
	}

	/**
	 * @deprecated 3.0 Use MCMSSEO_Meta_Columns::column_content instead.
	 *
	 * @param string $column_name Column to display the content for.
	 * @param int    $post_id     Post to display the column content for.
	 */
	public function column_content( $column_name, $post_id ) {
		_deprecated_function( 'MCMSSEO_Metabox::column_content', 'MCMSSEO 3.0', 'MCMSSEO_Metabox_Columns::column_content' );

		/** @var MCMSSEO_Meta_Columns $meta_columns */
		$meta_columns = $GLOBALS['mcmsseo_meta_columns'];
		$meta_columns->column_content( $column_name, $post_id );
	}

	/**
	 * @deprecated 3.0 Use MCMSSEO_Meta_Columns::column_sort instead.
	 *
	 * @param array $columns appended with their orderby variable.
	 *
	 * @return array
	 */
	public function column_sort( $columns ) {
		_deprecated_function( 'MCMSSEO_Metabox::column_sort', 'MCMSSEO 3.0', 'MCMSSEO_Metabox_Columns::column_sort' );

		/** @var MCMSSEO_Meta_Columns $meta_columns */
		$meta_columns = $GLOBALS['mcmsseo_meta_columns'];
		return $meta_columns->column_sort( $columns );
	}

	/**
	 * @deprecated 3.0 Use MCMSSEO_Meta_Columns::column_sort_orderby instead.
	 *
	 * @param array $vars Query variables.
	 *
	 * @return array
	 */
	public function column_sort_orderby( $vars ) {
		_deprecated_function( 'MCMSSEO_Metabox::column_sort_orderby', 'MCMSSEO 3.0', 'MCMSSEO_Metabox_Columns::column_sort_orderby' );

		/** @var MCMSSEO_Meta_Columns $meta_columns */
		$meta_columns = $GLOBALS['mcmsseo_meta_columns'];
		return $meta_columns->column_sort_orderby( $vars );
	}

	/**
	 * @deprecated 3.0 Use MCMSSEO_Meta_Columns::column_hidden instead.
	 *
	 * @param array|false $result The hidden columns.
	 * @param string      $option The option name used to set which columns should be hidden.
	 * @param MCMS_User     $user The User.
	 *
	 * @return array|false $result
	 */
	public function column_hidden( $result, $option, $user ) {
		_deprecated_function( 'MCMSSEO_Metabox::column_hidden', 'MCMSSEO 3.0', 'MCMSSEO_Metabox_Columns::column_hidden' );

		/** @var MCMSSEO_Meta_Columns $meta_columns */
		$meta_columns = $GLOBALS['mcmsseo_meta_columns'];
		return $meta_columns->column_hidden( $result, $option, $user );
	}

	/**
	 * @deprecated 3.0 Use MCMSSEO_Meta_Columns::seo_score_posts_where instead.
	 *
	 * @param string $where  Where clause.
	 *
	 * @return string
	 */
	public function seo_score_posts_where( $where ) {
		_deprecated_function( 'MCMSSEO_Metabox::seo_score_posts_where', 'MCMSSEO 3.0', 'MCMSSEO_Metabox_Columns::seo_score_posts_where' );

		/** @var MCMSSEO_Meta_Columns $meta_columns */
		$meta_columns = $GLOBALS['mcmsseo_meta_columns'];
		return $meta_columns->seo_score_posts_where( $where );
	}

	/**
	 * @deprecated 3.0 Removed.
	 *
	 * @param int $post_id Post to retrieve the title for.
	 *
	 * @return string
	 */
	public function page_title( $post_id ) {
		_deprecated_function( 'MCMSSEO_Metabox::page_title', 'MCMSSEO 3.0' );

		return '';
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array  $array Array to sort, array is returned sorted.
	 * @param string $key   Key to sort array by.
	 */
	public function aasort( &$array, $key ) {
		_deprecated_function( 'MCMSSEO_Metabox::aasort', 'MCMSSEO 3.0' );

	}

	/**
	 * @deprecated 3.0
	 *
	 * @param object $post Post to output the page analysis results for.
	 *
	 * @return string
	 */
	public function linkdex_output( $post ) {
		_deprecated_function( 'MCMSSEO_Metabox::linkdex_output', 'MCMSSEO 3.0' );

		return '';

	}

	/**
	 * @deprecated 3.0
	 *
	 * @param  object $post Post to calculate the results for.
	 *
	 * @return  array|MCMS_Error
	 */
	public function calculate_results( $post ) {
		_deprecated_function( 'MCMSSEO_Metabox::calculate_results', 'MCMSSEO 3.0' );

		return array();

	}

	/**
	 * @deprecated 3.0
	 *
	 * @param MCMS_Post $post Post object instance.
	 *
	 * @return    array
	 */
	public function get_sample_permalink( $post ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_sample_permalink', 'MCMSSEO 3.0' );

		return array();
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array  $results      The results array used to store results.
	 * @param int    $scoreValue   The score value.
	 * @param string $scoreMessage The score message.
	 * @param string $scoreLabel   The label of the score to use in the results array.
	 * @param string $rawScore     The raw score, to be used by other filters.
	 */
	public function save_score_result( &$results, $scoreValue, $scoreMessage, $scoreLabel, $rawScore = null ) {

		_deprecated_function( 'MCMSSEO_Metabox::save_score_result', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param string $inputString              String to clean up.
	 * @param bool   $removeOptionalCharacters Whether or not to do a cleanup of optional chars too.
	 *
	 * @return string
	 */
	public function strip_separators_and_fold( $inputString, $removeOptionalCharacters ) {
		_deprecated_function( 'MCMSSEO_Metabox::strip_separators_and_f', 'MCMSSEO 3.0' );

		return '';
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array $job     Job data array.
	 * @param array $results Results set by reference.
	 */
	public function check_double_focus_keyword( $job, &$results ) {
		_deprecated_function( 'MCMSSEO_Metabox::check_double_focus_key', 'MCMSSEO 3.0' );

	}

	/**
	 * @deprecated 3.0
	 *
	 * @param string $keyword The keyword to check for stopwords.
	 * @param array  $results The results array.
	 */
	public function score_keyword( $keyword, &$results ) {
		_deprecated_function( 'MCMSSEO_Metabox::score_keyword', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array $job     The job array holding both the keyword and the URLs.
	 * @param array $results The results array.
	 */
	public function score_url( $job, &$results ) {
		_deprecated_function( 'MCMSSEO_Metabox::score_url', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array $job     The job array holding both the keyword versions.
	 * @param array $results The results array.
	 */
	public function score_title( $job, &$results ) {
		_deprecated_function( 'MCMSSEO_Metabox::score_title', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array $job          The job array holding both the keyword versions.
	 * @param array $results      The results array.
	 * @param array $anchor_texts The array holding all anchors in the document.
	 * @param array $count        The number of anchors in the document, grouped by type.
	 */
	public function score_anchor_texts( $job, &$results, $anchor_texts, $count ) {
		_deprecated_function( 'MCMSSEO_Metabox::score_anchor_texts', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param object $xpath An XPATH object of the current document.
	 *
	 * @return array
	 */
	public function get_anchor_texts( &$xpath ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_anchor_texts', 'MCMSSEO 3.0' );

		return array();
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param object $xpath An XPATH object of the current document.
	 *
	 * @return array
	 */
	public function get_anchor_count( &$xpath ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_anchor_count', 'MCMSSEO 3.0' );

		return array();
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array $job     The job array holding both the keyword versions.
	 * @param array $results The results array.
	 * @param array $imgs    The array with images alt texts.
	 */
	public function score_images_alt_text( $job, &$results, $imgs ) {
		_deprecated_function( 'MCMSSEO_Metabox::score_images_alt_text', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param int    $post_id The post to find images in.
	 * @param string $body    The post content to find images in.
	 * @param array  $imgs    The array holding the image information.
	 *
	 * @return array The updated images array.
	 */
	public function get_images_alt_text( $post_id, $body, $imgs ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_images_alt_text', 'MCMSSEO 3.0' );

		return array();
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array $job      The array holding the keywords.
	 * @param array $results  The results array.
	 * @param array $headings The headings found in the document.
	 */
	public function score_headings( $job, &$results, $headings ) {
		_deprecated_function( 'MCMSSEO_Metabox::score_headings', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param string $postcontent Post content to find headings in.
	 *
	 * @return array Array of heading texts.
	 */
	public function get_headings( $postcontent ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_headings', 'MCMSSEO 3.0' );

		return array();
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array  $job         The array holding the keywords.
	 * @param array  $results     The results array.
	 * @param string $description The meta description.
	 * @param int    $maxlength   The maximum length of the meta description.
	 */
	public function score_description( $job, &$results, $description, $maxlength = 155 ) {
		_deprecated_function( 'MCMSSEO_Metabox::score_description', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param array  $job     The array holding the keywords.
	 * @param array  $results The results array.
	 * @param string $body    The body.
	 * @param string $firstp  The first paragraph.
	 */
	public function score_body( $job, &$results, $body, $firstp ) {
		_deprecated_function( 'MCMSSEO_Metabox::score_body', 'MCMSSEO 3.0' );
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param object $post The post object.
	 *
	 * @return string The post content.
	 */
	public function get_body( $post ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_body', 'MCMSSEO 3.0' );

		return '';
	}

	/**
	 * @deprecated 3.0
	 *
	 * @param string $body The post content to retrieve the first paragraph from.
	 *
	 * @return string
	 */
	public function get_first_paragraph( $body ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_first_paragraph', 'MCMSSEO 3.0' );

		return '';
	}

	/**
	 * @deprecated 3.2
	 *
	 * Retrieves the title template.
	 *
	 * @param object $post metabox post.
	 *
	 * @return string
	 */
	public static function get_title_template( $post ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_title_template', 'MCMSSEO 3.2', 'MCMSSEO_Post_Scraper::get_title_template' );

		return '';
	}

	/**
	 * @deprecated 3.2
	 *
	 * Retrieves the metadesc template.
	 *
	 * @param object $post metabox post.
	 *
	 * @return string
	 */
	public static function get_metadesc_template( $post ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_metadesc_template', 'MCMSSEO 3.2', 'MCMSSEO_Post_Scraper::get_metadesc_template' );
		return '';
	}

	/**
	 * @deprecated 3.2
	 * Retrieve a post date when post is published, or return current date when it's not.
	 *
	 * @param MCMS_Post $post The post for which to retrieve the post date.
	 *
	 * @return string
	 */
	public function get_post_date( $post ) {
		_deprecated_function( 'MCMSSEO_Metabox::get_post_date', 'MCMSSEO 3.2', 'MCMSSEO_Post_Scraper::get_post_date' );
		_deprecated_function( 'MCMSSEO_Metabox::get_post_date', 'MCMSSEO 3.2', 'MCMSSEO_Post_Scraper::get_post_date' );

		return '';
	}
}