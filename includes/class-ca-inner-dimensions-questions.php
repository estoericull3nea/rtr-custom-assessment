<?php
/**
 * Natural Attributes Cataloging — Yes / No self-assessment (plugin-defined content).
 * Internal assessment type: inner_dimensions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Inner_Dimensions_Questions {

	/**
	 * @param string $category Category title.
	 * @param array  $texts    Question lines in order.
	 * @return array
	 */
	private static function pack( $category, array $texts ) {
		$questions = array();
		$priority  = 1;
		foreach ( $texts as $text ) {
			$questions[] = array(
				'text'     => $text,
				'priority' => $priority++,
			);
		}
		return array(
			'category'  => $category,
			'questions' => $questions,
		);
	}

	/**
	 * @return array Nested categories and questions.
	 */
	public static function get_all() {
		return self::build_all_questions();
	}

	/**
	 * Base catalog before admin overrides and custom rows.
	 *
	 * @return array
	 */
	private static function get_base_all() {
		return array(
			self::pack(
				'Inner World',
				array(
					'I feel things deeply, even if I hide it',
					'I need space to process before reacting',
					'I\'m hard on myself, even after a win',
					'I bounce back quickly when I have time to process',
					'I protect my peace at all costs',
					'I\'ve often been told I\'m "too sensitive" or "too much"',
					'I often sense what others feel before they speak',
					'I retreat when I feel misunderstood',
					'I\'m energized when I know where I stand in a situation or with a person',
					'I tend to hold on to my emotions long after the moment is over',
				)
			),
			self::pack(
				'Mental Wiring',
				array(
					'I create systems to understand the world',
					'I can understand the existence of multiple perspectives at once',
					'I often spot patterns others miss',
					'I love thinking about "why" more than "how"',
					'I need to understand the "why" to perform at my best',
					'I learn fast when I\'m passionate about something; I need to understand the full picture before acting',
					'I struggle with repetitive tasks',
					'I can explain complex things in simple ways',
					'I enjoy abstract thought more than memorization',
					'I naturally break things into systems or frameworks in my head',
				)
			),
			self::pack(
				'Communication & Expression',
				array(
					'I talk out loud to get to a decision',
					'I often say what others are thinking but won\'t say',
					'I\'m more expressive in writing than speaking',
					'I instinctively mirror or adapt to the person I\'m with',
					'I\'ve always been the "talker" in my group',
					'I express myself through movement, design, or visuals',
					'I can command attention without forcing it',
					'I\'ve been called too quiet, too loud, or both',
					'I feel most powerful when I\'m telling a story',
					'I read the room before I speak',
				)
			),
			self::pack(
				'Vision & Intuition',
				array(
					'I\'ve always sensed things before they happen',
					'I have strong "gut instincts" and usually trust them',
					'I naturally connect dots others don\'t see',
					'I feel deeply connected to purpose or something bigger',
					'I\'ve had a vision for my life since I was young',
					'I see potential in people even when they don\'t see it',
					'I make decisions from a deep, intuitive place',
					'I often feel ahead of my time',
					'I sometimes feel like I live between two worlds - what is and what could be',
					'I remember my dreams or imagine vividly and often',
				)
			),
			self::pack(
				'Relationships & Connection',
				array(
					'I connect quickly and deeply with people',
					'I often become the leader in groups',
					'I struggle with small talk but thrive in depth',
					'I protect my energy in social settings',
					'I build trust slowly but fiercely',
					'I tend to give more than I receive',
					'I\'ve been told I\'m too independent or too attached',
					'I intuit what people need before they ask',
					'I withdraw when I don\'t feel emotionally safe',
					'I form long-term bonds that feel like soul contracts',
				)
			),
			self::pack(
				'Motivation & Drive',
				array(
					'I crave flow, not force',
					'I\'m driven by impact more than recognition',
					'I take aligned action when I feel emotionally connected',
					'I have bursts of intensity followed by burnout',
					'I\'m disciplined when the vision is clear',
					'I hate being micromanaged or boxed in',
					'I\'ve always created my own momentum',
					'I need purpose behind my productivity',
					'I work best when my values are activated',
					'I\'ve been told I push too hard - or not hard enough',
				)
			),
			self::pack(
				'Movement & Energy',
				array(
					'I need to feel the vibe in a room before I engage',
					'I\'m sensitive to sounds, lights, and energy shifts',
					'I\'m energized by movement or tactile tasks',
					'I often "feel" my way through decisions',
					'I get drained in noisy or chaotic environments',
					'I ground myself through rituals or physical anchors',
					'I\'ve always been drawn to rhythm, dance, or physical expression',
					'I notice posture, facial expressions, or breathing changes in others',
					'I restore through physical solitude',
					'I can sense when something\'s off - without evidence',
				)
			),
			self::pack(
				'Environments & Systems',
				array(
					'I create order out of chaos',
					'I love the idea of freedom',
					'I struggle with structure unless I build it myself',
					'I see the flaws in how things are organized',
					'I can visualize workflows or processes easily',
					'I thrive in calm, intentional spaces',
					'I often redesign things to make them more efficient',
					'I like planning, but not overplanning',
					'I notice details others miss',
					'I can organize ideas, spaces, or people effortlessly',
				)
			),
		);
	}

	/**
	 * Merge base items with per-index overrides and custom admin-added rows.
	 *
	 * @return array
	 */
	private static function build_all_questions() {
		$base_questions = self::get_base_all();
		$overrides      = get_option( 'ca_inner_question_overrides', array() );
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
					$base_questions[ $cat_idx ]['questions'][ $q_idx ] = $row;
				}
				$base_flat_index++;
			}
		}

		$custom_questions = get_option( 'ca_inner_custom_questions', array() );
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
	 * Categories in the merged set plus empty custom categories from options.
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

		$custom_categories = get_option( 'ca_inner_custom_categories', array() );
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
				$flat[]         = array(
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
