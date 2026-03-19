<?php
/**
 * Assessment questions and category definitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Questions {

	/**
	 * Returns all categories with their questions.
	 * Each question has a 0-based global index.
	 *
	 * @return array
	 */
	public static function get_all() {
		return array(
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
				$flat[] = array(
					'index'    => $index,
					'category' => $cat['category'],
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
		$categories = array();
		$all = self::get_all();
		
		// Get base categories from the hardcoded structure
		foreach ( $all as $cat ) {
			$categories[] = $cat['category'];
		}
		
		// Add custom categories from WordPress options
		$custom_categories = get_option( 'ca_custom_categories', array() );
		foreach ( $custom_categories as $custom_category ) {
			if ( ! in_array( $custom_category, $categories ) ) {
				$categories[] = $custom_category;
			}
		}
		
		return $categories;
	}
}
