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
	 * Plugin-shipped Social Fluency items (before admin overrides / custom rows).
	 *
	 * @return array
	 */
	private static function get_base_all() {
		return array(
			array(
				'category'  => 'Authentic Presence',
				'questions' => array(
					array(
						'text'     => 'How comfortable are you sharing your genuine thoughts and feelings with new people?',
						'priority' => 1,
						'endpoints' => array(
							'left'  => __( 'Uncomfortable', 'rtr-custom-assessment' ),
							'right' => __( 'Very comfortable', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'When meeting someone new, how fully do you engage in active listening rather than thinking about what to say next?',
						'priority' => 2,
						'endpoints' => array(
							'left'  => __( 'I rarely engage', 'rtr-custom-assessment' ),
							'right' => __( 'I fully engage', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How often do you ask questions driven by sincere curiosity rather than to fill conversational space?',
						'priority' => 3,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'In group settings, how willing are you to express potentially unpopular but honest opinions?',
						'priority' => 4,
						'endpoints' => array(
							'left'  => __( 'Not very willing', 'rtr-custom-assessment' ),
							'right' => __( 'Extremely willing', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How frequently do people remember specific conversations with you weeks or months later?',
						'priority' => 5,
						'endpoints' => array(
							'left'  => __( 'Rarely, if ever', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
				),
			),
			array(
				'category'  => 'Emotional Resonance',
				'questions' => array(
					array(
						'text'     => 'How accurately can you identify the emotions others are experiencing during conversations?',
						'priority' => 1,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How comfortable are you acknowledging your own vulnerabilities with others?',
						'priority' => 2,
						'endpoints' => array(
							'left'  => __( 'Uncomfortable', 'rtr-custom-assessment' ),
							'mid'   => __( 'Neutral', 'rtr-custom-assessment' ),
							'right' => __( 'Very comfortable', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How often do people seek you out for emotional support or counsel?',
						'priority' => 3,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'When someone shares difficult news or feelings, how effectively do you respond with empathy?',
						'priority' => 4,
						'endpoints' => array(
							'left'  => __( 'Not very effectively', 'rtr-custom-assessment' ),
							'mid'   => __( 'Somewhere in the middle', 'rtr-custom-assessment' ),
							'right' => __( 'I respond with a lot of empathy', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How frequently do you express genuine appreciation for others\' qualities or contributions?',
						'priority' => 5,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
				),
			),
			array(
				'category'  => 'Strategic Visibility',
				'questions' => array(
					array(
						'text'     => 'How regularly do you contribute valuable insights in professional or community settings?',
						'priority' => 1,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How effectively do you leverage digital platforms to showcase your expertise?',
						'priority' => 2,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How often do you receive invitations to participate in industry events or collaborative opportunities?',
						'priority' => 3,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How comfortable are you introducing yourself to influential people in your field?',
						'priority' => 4,
						'endpoints' => array(
							'left'  => __( 'Uncomfortable', 'rtr-custom-assessment' ),
							'mid'   => __( 'I\'m neutral', 'rtr-custom-assessment' ),
							'right' => __( 'Very comfortable', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How clear is your professional narrative or personal brand to those outside your immediate circle?',
						'priority' => 5,
						'endpoints' => array(
							'left'  => __( 'It is unclear', 'rtr-custom-assessment' ),
							'mid'   => __( 'Somewhat clear', 'rtr-custom-assessment' ),
							'right' => __( 'Very clear', 'rtr-custom-assessment' ),
						),
					),
				),
			),
			array(
				'category'  => 'Ecosystem Diversity',
				'questions' => array(
					array(
						'text'     => 'How diverse is your network across industries, backgrounds, and perspectives?',
						'priority' => 1,
						'endpoints' => array(
							'left'  => __( 'Not very diverse', 'rtr-custom-assessment' ),
							'mid'   => __( 'Half and half', 'rtr-custom-assessment' ),
							'right' => __( 'Very diverse', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How often do you seek out connections with people whose experiences differ significantly from yours?',
						'priority' => 2,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How comfortable are you navigating unfamiliar cultural or professional environments?',
						'priority' => 3,
						'endpoints' => array(
							'left'  => __( 'Uncomfortable', 'rtr-custom-assessment' ),
							'mid'   => __( 'Neutral', 'rtr-custom-assessment' ),
							'right' => __( 'Very comfortable', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How frequently do you make introductions between people from different parts of your network?',
						'priority' => 4,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How often do innovative ideas in your work come from exposure to different domains?',
						'priority' => 5,
						'endpoints' => array(
							'left'  => __( 'Not very often', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very often', 'rtr-custom-assessment' ),
						),
					),
				),
			),
			array(
				'category'  => 'Strategic Intentionality',
				'questions' => array(
					array(
						'text'     => 'How clear are you about what qualities and resources you need in your network?',
						'priority' => 1,
						'endpoints' => array(
							'left'  => __( 'Not very clear', 'rtr-custom-assessment' ),
							'mid'   => __( 'I have a vague idea', 'rtr-custom-assessment' ),
							'right' => __( 'I am very clear', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How deliberately do you nurture relationships with people who align with your values and goals?',
						'priority' => 2,
						'endpoints' => array(
							'left'  => __( 'Not very deliberately', 'rtr-custom-assessment' ),
							'right' => __( 'Very deliberately', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How effectively do you maintain connections with potential mentors and allies?',
						'priority' => 3,
						'endpoints' => array(
							'left'  => __( 'Not very effectively', 'rtr-custom-assessment' ),
							'mid'   => __( 'Sometimes', 'rtr-custom-assessment' ),
							'right' => __( 'Very effectively', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How systematically do you follow up after initial meetings with people of strategic importance?',
						'priority' => 4,
						'endpoints' => array(
							'left'  => __( 'Not very systematically', 'rtr-custom-assessment' ),
							'right' => __( 'Very systematically', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How well do you balance organic relationship building with purposeful network development?',
						'priority' => 5,
						'endpoints' => array(
							'left'  => __( 'Not very well', 'rtr-custom-assessment' ),
							'right' => __( 'Very well', 'rtr-custom-assessment' ),
						),
					),
				),
			),
			array(
				'category'  => 'Value Exchange Mindset',
				'questions' => array(
					array(
						'text'     => 'How frequently do you offer help without expectation of immediate return?',
						'priority' => 1,
						'endpoints' => array(
							'left'  => __( 'Not very frequently', 'rtr-custom-assessment' ),
							'right' => __( 'Very frequently', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How attentive are you to identifying others\' needs before they explicitly ask?',
						'priority' => 2,
						'endpoints' => array(
							'left'  => __( 'Not very attentive', 'rtr-custom-assessment' ),
							'right' => __( 'Very attentive', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How creative are you in finding ways to add value to others\' projects or challenges?',
						'priority' => 3,
						'endpoints' => array(
							'left'  => __( 'Not very creative', 'rtr-custom-assessment' ),
							'right' => __( 'Very creative', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How effectively do you leverage your existing network to support others\' goals?',
						'priority' => 4,
						'endpoints' => array(
							'left'  => __( 'Not very effectively', 'rtr-custom-assessment' ),
							'right' => __( 'Very effectively', 'rtr-custom-assessment' ),
						),
					),
					array(
						'text'     => 'How comfortable are you receiving help and support from others in your network?',
						'priority' => 5,
						'endpoints' => array(
							'left'  => __( 'Very uncomfortable', 'rtr-custom-assessment' ),
							'right' => __( 'I am very comfortable', 'rtr-custom-assessment' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Merge base questions with per-index overrides and custom admin-added rows.
	 *
	 * @return array
	 */
	private static function build_all_questions() {
		$base_questions = self::get_base_all();
		$overrides      = get_option( 'ca_sf_question_overrides', array() );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		$base_flat_index = 0;
		for ( $cat_idx = 0, $cat_count = count( $base_questions ); $cat_idx < $cat_count; $cat_idx++ ) {
			$cat_name = isset( $base_questions[ $cat_idx ]['category'] ) ? (string) $base_questions[ $cat_idx ]['category'] : '';
			$qlist    = isset( $base_questions[ $cat_idx ]['questions'] ) && is_array( $base_questions[ $cat_idx ]['questions'] )
				? $base_questions[ $cat_idx ]['questions']
				: array();
			for ( $q_idx = 0, $q_count = count( $qlist ); $q_idx < $q_count; $q_idx++ ) {
				if ( isset( $overrides[ $base_flat_index ] ) && is_array( $overrides[ $base_flat_index ] ) ) {
					$ov   = $overrides[ $base_flat_index ];
					$orig = $base_questions[ $cat_idx ]['questions'][ $q_idx ];
					$row  = array(
						'text'     => isset( $ov['text'] ) ? (string) $ov['text'] : (string) $orig['text'],
						'priority' => isset( $ov['priority'] ) ? (int) $ov['priority'] : (int) $orig['priority'],
						'category' => isset( $ov['category'] ) ? (string) $ov['category'] : ( isset( $orig['category'] ) ? (string) $orig['category'] : $cat_name ),
					);
					if ( isset( $orig['endpoints'] ) && is_array( $orig['endpoints'] ) ) {
						$row['endpoints'] = $orig['endpoints'];
					}
					$base_questions[ $cat_idx ]['questions'][ $q_idx ] = $row;
				}
				$base_flat_index++;
			}
		}

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
			$custom_categories[ $category ][] = array(
				'text'     => isset( $custom_question['text'] ) ? (string) $custom_question['text'] : '',
				'priority' => isset( $custom_question['priority'] ) ? (int) $custom_question['priority'] : 0,
			);
		}

		foreach ( $custom_categories as $category_name => $questions ) {
			$base_questions[] = array(
				'category'  => $category_name,
				'questions' => $questions,
			);
		}

		return $base_questions;
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
