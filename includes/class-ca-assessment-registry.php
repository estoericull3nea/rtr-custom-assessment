<?php
/**
 * Resolves question sets and totals by assessment type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Assessment_Registry {

	/**
	 * @param string $assessment_type Normalized type.
	 * @return array
	 */
	public static function get_flat( $assessment_type ) {
		$t = CA_Assessment_Types::normalize( $assessment_type );
		if ( CA_Assessment_Types::SOCIAL_FLUENCY === $t ) {
			return CA_Social_Fluency_Questions::get_flat();
		}
		if ( CA_Assessment_Types::INNER_DIMENSIONS === $t ) {
			return CA_Inner_Dimensions_Questions::get_flat();
		}
		return CA_Questions::get_flat();
	}

	/**
	 * @param string $assessment_type Normalized type.
	 * @return array
	 */
	public static function get_all( $assessment_type ) {
		$t = CA_Assessment_Types::normalize( $assessment_type );
		if ( CA_Assessment_Types::SOCIAL_FLUENCY === $t ) {
			return CA_Social_Fluency_Questions::get_all();
		}
		if ( CA_Assessment_Types::INNER_DIMENSIONS === $t ) {
			return CA_Inner_Dimensions_Questions::get_all();
		}
		return CA_Questions::get_all();
	}

	/**
	 * @param string $assessment_type Normalized type.
	 * @param int    $index Zero-based.
	 * @return array|null
	 */
	public static function get_question( $assessment_type, $index ) {
		$t = CA_Assessment_Types::normalize( $assessment_type );
		if ( CA_Assessment_Types::SOCIAL_FLUENCY === $t ) {
			return CA_Social_Fluency_Questions::get_question( $index );
		}
		if ( CA_Assessment_Types::INNER_DIMENSIONS === $t ) {
			return CA_Inner_Dimensions_Questions::get_question( $index );
		}
		return CA_Questions::get_question( $index );
	}

	/**
	 * @param string $assessment_type Normalized type.
	 * @return int
	 */
	public static function get_total_count( $assessment_type ) {
		$t = CA_Assessment_Types::normalize( $assessment_type );
		if ( CA_Assessment_Types::SOCIAL_FLUENCY === $t ) {
			return CA_Social_Fluency_Questions::get_total_count();
		}
		if ( CA_Assessment_Types::INNER_DIMENSIONS === $t ) {
			return CA_Inner_Dimensions_Questions::get_total_count();
		}
		return CA_Questions::get_total_count();
	}

	/**
	 * Question array as used by the frontend / AJAX (scale, label_style, etc.).
	 *
	 * @param string $assessment_type Normalized type.
	 * @param int    $index          Zero-based flat index.
	 * @return array|null
	 */
	public static function get_question_display_payload( $assessment_type, $index ) {
		$t        = CA_Assessment_Types::normalize( $assessment_type );
		$question = self::get_question( $t, (int) $index );
		if ( ! $question ) {
			return null;
		}

		$scale_max = CA_Assessment_Types::get_scale_max( $t );
		$payload   = $question;
		$payload['scale_max'] = $scale_max;

		if ( CA_Assessment_Types::SOCIAL_FLUENCY === $t ) {
			$eps     = isset( $question['endpoints'] ) && is_array( $question['endpoints'] ) ? $question['endpoints'] : array();
			$has_eps = ! empty( $eps['left'] ) || ! empty( $eps['right'] ) || ! empty( $eps['mid'] );
			if ( $has_eps ) {
				$payload['label_style'] = 'endpoints';
				$payload['endpoints']   = $eps;
			} else {
				$payload['label_style'] = 'per_number';
				$payload['endpoints']   = array();
			}
		} elseif ( CA_Assessment_Types::INNER_DIMENSIONS === $t ) {
			$payload['label_style'] = 'yes_no';
			$payload['scale_max']   = 2;
		} else {
			$payload['label_style'] = 'per_number';
		}

		return $payload;
	}

	/**
	 * All display payloads in flat order for wp_localize_script (instant client render).
	 *
	 * @param string $assessment_type Normalized type.
	 * @return array
	 */
	public static function get_question_bank_for_client( $assessment_type ) {
		$total = self::get_total_count( $assessment_type );
		$bank  = array();
		for ( $i = 0; $i < $total; $i++ ) {
			$bank[] = self::get_question_display_payload( $assessment_type, $i );
		}
		return $bank;
	}
}
