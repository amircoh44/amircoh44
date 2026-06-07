<?php
/**
 * Edition / licensing gate (Free / Pro / Expert).
 *
 * Feature split (no page cap):
 *   - Free   : all audits + manual tools (image/schema/link audits, link editor,
 *              cleaner scan + revert, business profile, image fill, settings).
 *   - Pro    : automation + bulk apply + schema output (display-time internal
 *              linking, permanent bulk apply/revert, content distribution,
 *              bulk/auto content cleaning, new-post auto inbound links, the
 *              JSON-LD schema output in <head>).
 *   - Expert : Pro + export/migration + (future) multisite / white-label.
 *
 * The actual selling + license issuance is expected to be handled by a provider
 * such as Freemius, Lemon Squeezy or Gumroad. This class only resolves the
 * current edition and answers can()/is_pro()/is_expert(); a provider integrates
 * by either defining the SAB_EDITION constant, filtering `sab_edition`, or
 * filtering `sab_validate_license` to turn an entered key into an edition.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Edition
 */
class SAB_Edition {

	const FREE   = 'free';
	const PRO    = 'pro';
	const EXPERT = 'expert';

	/** Option storing the resolved edition. */
	const OPTION = 'sab_edition';

	/** Free tier grace: every feature is unlocked on sites up to this many published items. */
	const FREE_LIMIT = 25;

	/**
	 * Edition ranks (higher unlocks everything below it).
	 *
	 * @var array<string,int>
	 */
	protected static $rank = array(
		self::FREE   => 0,
		self::PRO    => 1,
		self::EXPERT => 2,
	);

	/**
	 * Feature => minimum edition required.
	 *
	 * @var array<string,string>
	 */
	protected static $map = array(
		'auto_linking'   => self::PRO,
		'bulk_apply'     => self::PRO,
		'distribution'   => self::PRO,
		'bulk_clean'     => self::PRO,
		'autosave_clean' => self::PRO,
		'auto_inbound'   => self::PRO,
		'schema_output'  => self::PRO,
		'ai'             => self::PRO,
		'export'         => self::EXPERT,
		'syndication'    => self::EXPERT,
		'multisite'      => self::EXPERT,
	);

	/**
	 * Resolve the active edition.
	 *
	 * Priority: SAB_EDITION constant > stored option > default free, then the
	 * `sab_edition` filter (so a licensing SDK can override).
	 *
	 * @return string
	 */
	public static function current() {
		$edition = self::FREE;

		if ( defined( 'SAB_EDITION' ) && isset( self::$rank[ SAB_EDITION ] ) ) {
			$edition = SAB_EDITION;
		} else {
			$stored = get_option( self::OPTION, self::FREE );
			if ( is_string( $stored ) && isset( self::$rank[ $stored ] ) ) {
				$edition = $stored;
			}
		}

		$edition = apply_filters( 'sab_edition', $edition );
		return isset( self::$rank[ $edition ] ) ? $edition : self::FREE;
	}

	/**
	 * Numeric rank for an edition.
	 *
	 * @param string $edition Edition.
	 * @return int
	 */
	protected static function rank_of( $edition ) {
		return isset( self::$rank[ $edition ] ) ? self::$rank[ $edition ] : 0;
	}

	/**
	 * Is the current edition Pro or higher?
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return self::rank_of( self::current() ) >= self::$rank[ self::PRO ];
	}

	/**
	 * Is the current edition Expert?
	 *
	 * @return bool
	 */
	public static function is_expert() {
		return self::rank_of( self::current() ) >= self::$rank[ self::EXPERT ];
	}

	/**
	 * Can the current edition use a feature?
	 *
	 * @param string $feature Feature key (see $map). Unknown keys are free.
	 * @return bool
	 */
	public static function can( $feature ) {
		$need = isset( self::$map[ $feature ] ) ? self::$map[ $feature ] : self::FREE;
		if ( self::FREE === $need ) {
			return true;
		}
		if ( self::rank_of( self::current() ) >= self::rank_of( $need ) ) {
			return true; // Properly licensed for this tier.
		}
		// Free grace: every premium feature is unlocked while the site is small
		// (up to the free page limit). Beyond it, a paid licence is required.
		return self::within_free_limit();
	}

	/**
	 * The free-tier content limit (number of published items).
	 *
	 * @return int Negative disables the grace entirely.
	 */
	public static function free_limit() {
		return (int) apply_filters( 'sab_free_page_limit', self::FREE_LIMIT );
	}

	/**
	 * Is the site within the free grace limit?
	 *
	 * @return bool
	 */
	public static function within_free_limit() {
		$limit = self::free_limit();
		if ( $limit < 0 ) {
			return false;
		}
		return self::content_count() <= $limit;
	}

	/**
	 * Is an unlicensed site over the free limit (i.e. premium features locked)?
	 *
	 * @return bool
	 */
	public static function over_free_limit() {
		return ! self::is_pro() && ! self::within_free_limit();
	}

	/**
	 * The minimum edition a feature requires (for upsell copy).
	 *
	 * @param string $feature Feature key.
	 * @return string
	 */
	public static function required_for( $feature ) {
		return isset( self::$map[ $feature ] ) ? self::$map[ $feature ] : self::FREE;
	}

	/**
	 * Human label for an edition (defaults to current).
	 *
	 * @param string|null $edition Edition or null for current.
	 * @return string
	 */
	public static function label( $edition = null ) {
		$edition = $edition ? $edition : self::current();
		$labels  = array(
			self::FREE   => __( 'Free', 'seo-article-booster' ),
			self::PRO    => __( 'Pro', 'seo-article-booster' ),
			self::EXPERT => __( 'Expert', 'seo-article-booster' ),
		);
		return isset( $labels[ $edition ] ) ? $labels[ $edition ] : ucfirst( $edition );
	}

	/**
	 * Upgrade URL (filterable — point this at your store / Freemius checkout).
	 *
	 * @return string
	 */
	public static function upgrade_url() {
		return apply_filters( 'sab_upgrade_url', 'https://github.com/amircoh44/amircoh44' );
	}

	/**
	 * Turn an entered license key into an edition and store it.
	 *
	 * With no key (or no provider hooked), the site stays Free. Integrate a real
	 * provider by filtering `sab_validate_license`.
	 *
	 * @param string $key License key.
	 * @return string The resolved edition.
	 */
	public static function activate_key( $key ) {
		$key     = trim( (string) $key );
		$edition = apply_filters( 'sab_validate_license', self::FREE, $key );
		if ( ! is_string( $edition ) || ! isset( self::$rank[ $edition ] ) ) {
			$edition = self::FREE;
		}
		update_option( self::OPTION, $edition );
		return $edition;
	}

	/**
	 * Count of published content (pages + posts + public CPTs) — shown for info.
	 *
	 * @return int
	 */
	public static function content_count() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		$total = 0;
		foreach ( $types as $type ) {
			$counts = wp_count_posts( $type );
			if ( isset( $counts->publish ) ) {
				$total += (int) $counts->publish;
			}
		}
		return $total;
	}
}
