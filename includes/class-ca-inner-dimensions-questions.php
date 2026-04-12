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
