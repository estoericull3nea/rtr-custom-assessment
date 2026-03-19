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
					'I embrace challenges and view them as opportunities for growth.',
					'I am persistent in the face of obstacles and failures.',
					'I actively seek solutions rather than dwelling on problems.',
					'I maintain a positive attitude even in difficult situations.',
				),
			),
			array(
				'category' => 'Adaptability',
				'questions' => array(
					'I adapt quickly to changes and setbacks.',
					'I am comfortable navigating uncertain situations.',
					'I learn from past experiences to improve future actions.',
					'I adjust my strategies based on feedback and results.',
				),
			),
			array(
				'category' => 'Risk-Taking',
				'questions' => array(
					'I am comfortable taking calculated risks.',
					'I step out of my comfort zone to achieve my goals.',
					'I explore innovative approaches to challenges.',
					'I weigh potential rewards and risks before making decisions.',
				),
			),
			array(
				'category' => 'Accountability',
				'questions' => array(
					'I take responsibility for my actions and their outcomes.',
					'I own up to mistakes and learn from them.',
					'I follow through on commitments and promises.',
					'I set clear expectations for myself and others.',
				),
			),
			array(
				'category' => 'Proactivity',
				'questions' => array(
					'I am proactive in seeking out new opportunities.',
					'I take initiative in situations without waiting for direction.',
					'I prioritize tasks that align with my long-term goals.',
					'I identify potential challenges and address them early.',
				),
			),
			array(
				'category' => 'Vision and Goal-Setting',
				'questions' => array(
					'I have a clear vision for my business and set goals accordingly.',
					'I align my daily actions with my long-term objectives.',
					'I break down big goals into actionable steps.',
				),
			),
			array(
				'category' => 'Confidence and Decision-Making',
				'questions' => array(
					'I trust my judgment when faced with uncertainty.',
					'I handle pressure well when making important decisions.',
					'I seek input when necessary but remain decisive.',
				),
			),
			array(
				'category' => 'Learning and Mentorship',
				'questions' => array(
					'I am open to constructive criticism, willing to learn from others, and seek out mentorship.',
					'I actively network to connect with people who align with my goals.',
					'I seek feedback and use it to improve myself.',
					'I dedicate time to developing new skills and knowledge.',
				),
			),
		);
	}

	/**
	 * Returns a flat list of questions with their category and global index.
	 *
	 * @return array  [ [ 'index' => int, 'category' => string, 'text' => string ], ... ]
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
					'text'     => $q,
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
		
		foreach ( $all as $cat ) {
			$categories[] = $cat['category'];
		}
		
		return $categories;
	}
}
