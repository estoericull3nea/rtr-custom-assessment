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
}
