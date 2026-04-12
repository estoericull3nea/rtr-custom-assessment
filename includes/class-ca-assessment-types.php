<?php
/**
 * Assessment type identifiers and scale helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Assessment_Types {

	public const MINDSET          = 'mindset';
	public const SOCIAL_FLUENCY   = 'social_fluency';

	/**
	 * Natural Attributes Cataloging (Yes/No). Stored in DB as inner_dimensions for compatibility.
	 */
	public const INNER_DIMENSIONS = 'inner_dimensions';

	/**
	 * @param string $type Raw type from client or DB.
	 * @return string One of the known type constants.
	 */
	public static function normalize( $type ) {
		$t = is_string( $type ) ? sanitize_key( $type ) : '';
		if ( self::INNER_DIMENSIONS === $t ) {
			return self::INNER_DIMENSIONS;
		}
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
		$t = self::normalize( $assessment_type );
		if ( self::INNER_DIMENSIONS === $t ) {
			return 1;
		}
		return self::SOCIAL_FLUENCY === $t ? 10 : 5;
	}

	/**
	 * Yes/No assessments store 1 = Yes, 2 = No in answers; scoring maps to 1 / 0.
	 *
	 * @param string $assessment_type Normalized type.
	 * @return bool
	 */
	public static function is_yes_no_assessment( $assessment_type ) {
		return self::INNER_DIMENSIONS === self::normalize( $assessment_type );
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
