<?php
/**
 * Assessment questions and category definitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Questions {
	/**
	 * Question overrides for base questions.
	 *
	 * Stored in `ca_question_overrides` as:
	 * - key: base flat index (int)
	 * - value: [ 'category' => string, 'text' => string, 'priority' => int ]
	 *
	 * @return array
	 */
	private static function get_question_overrides() {
		$overrides = get_option( 'ca_question_overrides', array() );
		return is_array( $overrides ) ? $overrides : array();
	}

	/**
	 * Returns all categories with their questions.
	 * Each question has a 0-based global index.
	 *
	 * @return array
	 */
	public static function get_all() {
		$base_questions = array(
			array(
				'category' => 'Growth Mindset',
				'questions' => array(
					array('text' => 'I embrace challenges and view them as opportunities for growth.', 'priority' => 1),
					array('text' => 'I am persistent in the face of obstacles and failures.', 'priority' => 2),
					array('text' => 'I actively seek solutions rather than dwelling on problems.', 'priority' => 3),
					array('text' => 'I maintain a positive attitude even in difficult situations.', 'priority' => 4),
				),
			),
			array(
				'category' => 'Adaptability',
				'questions' => array(
					array('text' => 'I adapt quickly to changes and setbacks.', 'priority' => 1),
					array('text' => 'I am comfortable navigating uncertain situations.', 'priority' => 2),
					array('text' => 'I learn from past experiences to improve future actions.', 'priority' => 3),
					array('text' => 'I adjust my strategies based on feedback and results.', 'priority' => 4),
				),
			),
			array(
				'category' => 'Risk-Taking',
				'questions' => array(
					array('text' => 'I am comfortable taking calculated risks.', 'priority' => 1),
					array('text' => 'I step out of my comfort zone to achieve my goals.', 'priority' => 2),
					array('text' => 'I explore innovative approaches to challenges.', 'priority' => 3),
					array('text' => 'I weigh potential rewards and risks before making decisions.', 'priority' => 4),
				),
			),
			array(
				'category' => 'Accountability',
				'questions' => array(
					array('text' => 'I take responsibility for my actions and their outcomes.', 'priority' => 1),
					array('text' => 'I own up to mistakes and learn from them.', 'priority' => 2),
					array('text' => 'I follow through on commitments and promises.', 'priority' => 3),
					array('text' => 'I set clear expectations for myself and others.', 'priority' => 4),
				),
			),
			array(
				'category' => 'Proactivity',
				'questions' => array(
					array('text' => 'I am proactive in seeking out new opportunities.', 'priority' => 1),
					array('text' => 'I take initiative in situations without waiting for direction.', 'priority' => 2),
					array('text' => 'I prioritize tasks that align with my long-term goals.', 'priority' => 3),
					array('text' => 'I identify potential challenges and address them early.', 'priority' => 4),
				),
			),
			array(
				'category' => 'Vision and Goal-Setting',
				'questions' => array(
					array('text' => 'I have a clear vision for my business and set goals accordingly.', 'priority' => 1),
					array('text' => 'I align my daily actions with my long-term objectives.', 'priority' => 2),
					array('text' => 'I break down big goals into actionable steps.', 'priority' => 3),
				),
			),
			array(
				'category' => 'Confidence and Decision-Making',
				'questions' => array(
					array('text' => 'I trust my judgment when faced with uncertainty.', 'priority' => 1),
					array('text' => 'I handle pressure well when making important decisions.', 'priority' => 2),
					array('text' => 'I seek input when necessary but remain decisive.', 'priority' => 3),
				),
			),
			array(
				'category' => 'Learning and Mentorship',
				'questions' => array(
					array('text' => 'I am open to constructive criticism, willing to learn from others, and seek out mentorship.', 'priority' => 1),
					array('text' => 'I actively network to connect with people who align with my goals.', 'priority' => 2),
					array('text' => 'I seek feedback and use it to improve myself.', 'priority' => 3),
					array('text' => 'I dedicate time to developing new skills and knowledge.', 'priority' => 4),
				),
			),
		);

		// Apply overrides to base questions without changing their ordering/indexes.
		// We store overrides at the base-question flat index (0-based).
		$overrides = self::get_question_overrides();
		$base_flat_index = 0;
		for ( $cat_idx = 0; $cat_idx < count( $base_questions ); $cat_idx++ ) {
			for ( $q_idx = 0; $q_idx < count( $base_questions[ $cat_idx ]['questions'] ); $q_idx++ ) {
				if ( isset( $overrides[ $base_flat_index ] ) && is_array( $overrides[ $base_flat_index ] ) ) {
					$ov = $overrides[ $base_flat_index ];
					// Add question-level category so get_flat() can use overridden category.
					$base_questions[ $cat_idx ]['questions'][ $q_idx ] = array(
						'text'     => isset( $ov['text'] ) ? (string) $ov['text'] : $base_questions[ $cat_idx ]['questions'][ $q_idx ]['text'],
						'priority' => isset( $ov['priority'] ) ? (int) $ov['priority'] : $base_questions[ $cat_idx ]['questions'][ $q_idx ]['priority'],
						'category' => isset( $ov['category'] ) ? (string) $ov['category'] : $base_questions[ $cat_idx ]['category'],
					);
				}
				$base_flat_index++;
			}
		}

		// Add custom questions
		$custom_questions = get_option( 'ca_custom_questions', array() );
		
		// Group custom questions by category
		$custom_categories = array();
		foreach ( $custom_questions as $custom_question ) {
			$category = $custom_question['category'];
			if ( ! isset( $custom_categories[$category] ) ) {
				$custom_categories[$category] = array();
			}
			$custom_categories[$category][] = array(
				'text' => $custom_question['text'],
				'priority' => $custom_question['priority']
			);
		}

		// Add custom categories to the base questions
		foreach ( $custom_categories as $category_name => $questions ) {
			$base_questions[] = array(
				'category' => $category_name,
				'questions' => $questions
			);
		}

		return $base_questions;
	}

	/**
	 * Returns a flat list of questions with their category, global index, and priority.
	 *
	 * @return array  [ [ 'index' => int, 'category' => string, 'text' => string, 'priority' => int ], ... ]
	 */
	public static function get_flat() {
		$flat       = array();
		$categories = self::get_all();
		$index      = 0;

		foreach ( $categories as $cat ) {
			foreach ( $cat['questions'] as $q ) {
				$category_value = isset( $q['category'] ) ? (string) $q['category'] : (string) $cat['category'];
				$flat[] = array(
					'index'    => $index,
					'category' => $category_value,
					'text'     => $q['text'],
					'priority' => $q['priority'],
				);
				$index++;
			}
		}

		return $flat;
	}

	/**
	 * Get total question count.
	 *
	 * @return int
	 */
	public static function get_total_count() {
		return count( self::get_flat() );
	}

	/**
	 * Get a single question by 0-based index.
	 *
	 * @param int $index
	 * @return array|null
	 */
	public static function get_question( $index ) {
		$flat = self::get_flat();
		return isset( $flat[ $index ] ) ? $flat[ $index ] : null;
	}

	/**
	 * Get all categories.
	 *
	 * @return array
	 */
	public static function get_categories() {
		$flat = self::get_flat();
		$categories = array();
		foreach ( $flat as $q ) {
			if ( isset( $q['category'] ) && ! in_array( $q['category'], $categories, true ) ) {
				$categories[] = $q['category'];
			}
		}

		// Add custom categories (even if there are no questions yet in that category).
		$custom_categories = get_option( 'ca_custom_categories', array() );
		if ( is_array( $custom_categories ) ) {
			foreach ( $custom_categories as $custom_category ) {
				if ( ! in_array( $custom_category, $categories, true ) ) {
					$categories[] = $custom_category;
				}
			}
		}

		return $categories;
	}
}
