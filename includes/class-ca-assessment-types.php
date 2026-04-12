<?php
/**
 * Assessment type identifiers and scale helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Assessment_Types {

	public const MINDSET         = 'mindset';
	public const SOCIAL_FLUENCY  = 'social_fluency';

	/**
	 * @param string $type Raw type from client or DB.
	 * @return string One of the known type constants.
	 */
	public static function normalize( $type ) {
		$t = is_string( $type ) ? sanitize_key( $type ) : '';
		if ( self::SOCIAL_FLUENCY === $t ) {
			return self::SOCIAL_FLUENCY;
		}
		return self::MINDSET;
	}

	/**
	 * @param string $assessment_type Normalized type.
	 * @return int Maximum answer value (inclusive).
	 */
	public static function get_scale_max( $assessment_type ) {
		return self::SOCIAL_FLUENCY === $assessment_type ? 10 : 5;
	}

	/**
	 * @param object $submission Row from ca_submissions.
	 * @return string
	 */
	public static function from_submission( $submission ) {
		if ( $submission && isset( $submission->assessment_type ) && $submission->assessment_type !== '' ) {
			return self::normalize( $submission->assessment_type );
		}
		return self::MINDSET;
	}
}
