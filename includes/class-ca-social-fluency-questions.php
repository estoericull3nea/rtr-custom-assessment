<?php
/**
 * Social Fluency Assessment — questions (1–10 scale).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Social_Fluency_Questions {

	/**
	 * @return array Nested categories and questions.
	 */
	public static function get_all() {
		return self::build_all_questions();
	}

	/**
	 * Build the active question set from admin-stored rows only (no plugin default catalog).
	 *
	 * @return array Nested categories and questions.
	 */
	private static function build_all_questions() {
		$custom_questions = get_option( 'ca_sf_custom_questions', array() );
		if ( ! is_array( $custom_questions ) ) {
			$custom_questions = array();
		}

		$custom_categories = array();
		foreach ( $custom_questions as $custom_question ) {
			if ( ! is_array( $custom_question ) ) {
				continue;
			}
			$category = isset( $custom_question['category'] ) ? (string) $custom_question['category'] : '';
			if ( ! isset( $custom_categories[ $category ] ) ) {
				$custom_categories[ $category ] = array();
			}
			$row = array(
				'text'     => isset( $custom_question['text'] ) ? (string) $custom_question['text'] : '',
				'priority' => isset( $custom_question['priority'] ) ? (int) $custom_question['priority'] : 0,
			);
			if ( isset( $custom_question['endpoints'] ) && is_array( $custom_question['endpoints'] ) ) {
				$row['endpoints'] = $custom_question['endpoints'];
			}
			$custom_categories[ $category ][] = $row;
		}

		$merged = array();
		foreach ( $custom_categories as $category_name => $questions ) {
			$merged[] = array(
				'category'  => $category_name,
				'questions' => $questions,
			);
		}

		return $merged;
	}

	/**
	 * Categories present in the merged question set, plus empty custom categories.
	 *
	 * @return string[]
	 */
	public static function get_categories() {
		$flat       = self::get_flat();
		$categories = array();
		foreach ( $flat as $q ) {
			if ( isset( $q['category'] ) && ! in_array( $q['category'], $categories, true ) ) {
				$categories[] = $q['category'];
			}
		}

		$custom_categories = get_option( 'ca_sf_custom_categories', array() );
		if ( is_array( $custom_categories ) ) {
			foreach ( $custom_categories as $custom_category ) {
				$c = (string) $custom_category;
				if ( '' !== $c && ! in_array( $c, $categories, true ) ) {
					$categories[] = $c;
				}
			}
		}

		return $categories;
	}

	/**
	 * @return array
	 */
	public static function get_flat() {
		$flat       = array();
		$categories = self::get_all();
		$index      = 0;

		foreach ( $categories as $cat ) {
			foreach ( $cat['questions'] as $q ) {
				$category_value = isset( $q['category'] ) ? (string) $q['category'] : (string) $cat['category'];
				$row            = array(
					'index'    => $index,
					'category' => $category_value,
					'text'     => $q['text'],
					'priority' => $q['priority'],
				);
				if ( isset( $q['endpoints'] ) && is_array( $q['endpoints'] ) ) {
					$row['endpoints'] = $q['endpoints'];
				}
				$flat[] = $row;
				$index++;
			}
		}

		return $flat;
	}

	/**
	 * @return int
	 */
	public static function get_total_count() {
		return count( self::get_flat() );
	}

	/**
	 * @param int $index Zero-based.
	 * @return array|null
	 */
	public static function get_question( $index ) {
		$flat = self::get_flat();
		return isset( $flat[ $index ] ) ? $flat[ $index ] : null;
	}
}
