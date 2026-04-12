<?php
/**
 * Scoring logic and result summaries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Scoring {

	/**
	 * Calculate full scoring breakdown from saved answers.
	 *
	 * @param array $answers  Keyed by question_index => answer (1-5).
	 * @return array {
	 *   total_score: int,
	 *   average_score: float,
	 *   category_scores: array of { name, subtotal, average, count }
	 * }
	 */
	public static function calculate( $answers ) {
		return self::calculate_for_assessment( CA_Assessment_Types::MINDSET, $answers );
	}

	/**
	 * Calculate scoring for a given assessment type.
	 *
	 * @param string $assessment_type Normalized type.
	 * @param array  $answers         Keyed by question_index => answer.
	 * @return array
	 */
	public static function calculate_for_assessment( $assessment_type, $answers ) {
		$type         = CA_Assessment_Types::normalize( $assessment_type );
		$categories   = CA_Assessment_Registry::get_all( $type );
		$flat         = CA_Assessment_Registry::get_flat( $type );
		$total_score  = 0;
		$cat_scores   = array();
		$yes_no       = CA_Assessment_Types::is_yes_no_assessment( $type );

		foreach ( $categories as $cat ) {
			$cat_scores[ $cat['category'] ] = array(
				'name'     => $cat['category'],
				'subtotal' => 0,
				'count'    => count( $cat['questions'] ),
				'average'  => 0.00,
			);
		}

		foreach ( $flat as $q ) {
			$idx = $q['index'];
			$cat = $q['category'];
			$raw = isset( $answers[ $idx ] ) ? (int) $answers[ $idx ] : 0;
			if ( $yes_no ) {
				$answer = ( 1 === $raw ) ? 1 : 0;
			} else {
				$answer = $raw;
			}
			$total_score += $answer;
			$cat_scores[ $cat ]['subtotal'] += $answer;
		}

		foreach ( $cat_scores as &$cat ) {
			$cat['average'] = $cat['count'] > 0
				? round( $cat['subtotal'] / $cat['count'], 2 )
				: 0.00;
		}
		unset( $cat );

		$total_questions = count( $flat );
		$average_score   = $total_questions > 0
			? round( $total_score / $total_questions, 2 )
			: 0.00;

		return array(
			'total_score'     => $total_score,
			'average_score'   => $average_score,
			'category_scores' => array_values( $cat_scores ),
		);
	}

	/**
	 * Generate a written summary for each category based on average score.
	 *
	 * @param string $category Category name.
	 * @param float  $average         Category average on this assessment's scale.
	 * @param string $assessment_type Optional. Defaults to mindset (1–5 scale).
	 * @return string
	 */
	public static function get_category_summary( $category, $average, $assessment_type = null ) {
		$type = CA_Assessment_Types::normalize( null !== $assessment_type ? $assessment_type : CA_Assessment_Types::MINDSET );

		if ( CA_Assessment_Types::INNER_DIMENSIONS === $type ) {
			return self::get_inner_dimensions_category_summary( $category, (float) $average );
		}

		if ( CA_Assessment_Types::SOCIAL_FLUENCY === $type ) {
			return self::get_social_fluency_category_summary( $category, (float) $average );
		}

		$summaries = array(
			'Growth Mindset' => array(
				'high'   => 'You demonstrate an exceptional growth mindset. You embrace challenges, persist through adversity, and maintain a positive attitude that empowers those around you.',
				'mid'    => 'You show a solid growth mindset with room to further embrace challenges as learning opportunities and develop resilience in difficult situations.',
				'low'    => 'Developing a stronger growth mindset will be key to your entrepreneurial success. Focus on reframing challenges as opportunities and building persistence.',
			),
			'Adaptability' => array(
				'high'   => 'You are highly adaptable and thrive in uncertain, rapidly changing environments — a critical strength for entrepreneurship.',
				'mid'    => 'You handle change reasonably well. Continuing to build comfort with uncertainty and using feedback more systematically will sharpen this skill.',
				'low'    => 'Building greater adaptability is essential. Practice stepping into unfamiliar situations and viewing setbacks as data rather than failures.',
			),
			'Risk-Taking' => array(
				'high'   => 'You are a confident, calculated risk-taker who is willing to step outside your comfort zone to drive innovation and growth.',
				'mid'    => 'You take some risks but may benefit from becoming more deliberate about evaluating and embracing calculated risks in your business decisions.',
				'low'    => 'Risk-taking is a learnable skill. Start small, evaluate the potential rewards, and build your tolerance for uncertainty over time.',
			),
			'Accountability' => array(
				'high'   => 'Your accountability is a major strength. You own your outcomes, learn from mistakes, and inspire trust through consistent follow-through.',
				'mid'    => 'You demonstrate accountability in most situations. Continuing to own your results more fully and setting clearer expectations will strengthen leadership.',
				'low'    => 'Strengthening accountability will significantly impact your business. Focus on owning your outcomes and treating every commitment as a promise.',
			),
			'Proactivity' => array(
				'high'   => 'You are highly proactive — identifying opportunities, addressing challenges early, and setting the pace for your team and business.',
				'mid'    => 'You take initiative in many situations. Building a habit of anticipating challenges earlier and aligning daily actions to long-term goals will amplify your impact.',
				'low'    => 'Developing greater proactivity will unlock new opportunities. Practice identifying the next challenge before it arrives and acting without waiting for direction.',
			),
			'Vision and Goal-Setting' => array(
				'high'   => 'You have a compelling vision and a disciplined goal-setting process that keeps you moving forward with clarity and purpose.',
				'mid'    => 'Your vision is developing. Focusing on translating big goals into specific, actionable steps will improve execution and momentum.',
				'low'    => 'Building a clearer vision and structured goal-setting practice is foundational to entrepreneurial success. Start with a 90-day plan and expand from there.',
			),
			'Confidence and Decision-Making' => array(
				'high'   => 'You make decisions with clarity and composure, even under pressure — a hallmark of effective entrepreneurial leadership.',
				'mid'    => 'You are reasonably confident in your decisions. Practicing under higher-pressure conditions and trusting your judgment more fully will sharpen this skill.',
				'low'    => 'Building decision-making confidence takes practice. Start by making smaller decisions more decisively and using structured frameworks to build trust in your judgment.',
			),
			'Learning and Mentorship' => array(
				'high'   => 'Your commitment to learning, mentorship, and networking is exceptional. You leverage relationships and feedback as powerful growth catalysts.',
				'mid'    => 'You value learning and mentorship. Being more intentional about seeking feedback and dedicating consistent time to skill development will accelerate your growth.',
				'low'    => 'Prioritizing mentorship and continuous learning will be transformative. Seek out one mentor and commit to regular skill development as a non-negotiable habit.',
			),
		);

		$level = 'mid';
		if ( $average >= 4.0 ) {
			$level = 'high';
		} elseif ( $average < 2.5 ) {
			$level = 'low';
		}

		if ( isset( $summaries[ $category ][ $level ] ) ) {
			return $summaries[ $category ][ $level ];
		}

		// Fallback generic summary
		if ( $level === 'high' ) {
			return "You demonstrate excellent " . esc_html( $category ) . " skills. Keep building on this strength.";
		} elseif ( $level === 'mid' ) {
			return "You show moderate " . esc_html( $category ) . " competency. Continued focus will help you grow.";
		} else {
			return "This is an area of opportunity. Investing in " . esc_html( $category ) . " will strengthen your entrepreneurial foundation.";
		}
	}

	/**
	 * Inner Dimensions — averages are share of “Yes” per category (0–1).
	 *
	 * @param string $category Category name.
	 * @param float  $average  Category average (0–1).
	 * @return string
	 */
	private static function get_inner_dimensions_category_summary( $category, $average ) {
		$bodies = array(
			'Inner World'                  => __( 'You have a fairly solid inner world, but there\'s room for deeper self-exploration. Engage in activities that challenge your self-awareness and emotional intelligence.', 'rtr-custom-assessment' ),
			'Mental Wiring'                => __( 'You possess a balanced mental wiring, but enhancing problem-solving skills will further boost your adaptability and innovation.', 'rtr-custom-assessment' ),
			'Communication & Expression'   => __( 'Your communication and expression are solid, yet refining your persuasive abilities will further strengthen your impact.', 'rtr-custom-assessment' ),
			'Vision & Intuition'           => __( 'You have a good sense of vision and intuition. Strengthening your strategic thinking will elevate your long-term planning abilities.', 'rtr-custom-assessment' ),
			'Relationships & Connection'   => __( 'You have a strong foundation in relationships. Further developing your networking skills will enhance your influence and support system.', 'rtr-custom-assessment' ),
			'Motivation & Drive'           => __( 'You have a good level of motivation. Focusing on aligning your goals with your passions will further enhance your drive.', 'rtr-custom-assessment' ),
			'Movement & Energy'            => __( 'You have a decent level of movement and energy. Exploring new physical activities can further enhance your overall well-being.', 'rtr-custom-assessment' ),
			'Environments & Systems'       => __( 'You have a fairly organized environment. Streamlining systems further can enhance your efficiency and effectiveness.', 'rtr-custom-assessment' ),
		);

		$mid = isset( $bodies[ $category ] )
			? $bodies[ $category ]
			: sprintf(
				/* translators: %s: category name */
				__( 'Continue reflecting on how you experience %s — small experiments often reveal the next right step.', 'rtr-custom-assessment' ),
				esc_html( $category )
			);

		if ( $average >= 0.67 ) {
			return __( 'Your answers show strong alignment in this area. ', 'rtr-custom-assessment' ) . $mid;
		}
		if ( $average < 0.34 ) {
			return __( 'There is room to explore this area more deeply. ', 'rtr-custom-assessment' ) . $mid;
		}

		return $mid;
	}

	/**
	 * Social Fluency category copy (scale roughly 1–10 per question; averages near 1–10).
	 *
	 * @param string $category Category name.
	 * @param float  $average  Category average.
	 * @return string
	 */
	private static function get_social_fluency_category_summary( $category, $average ) {
		$summaries = array(
			'Authentic Presence' => array(
				'high' => __( 'You show up with authenticity and presence. Others likely experience you as genuine, curious, and memorable in conversation.', 'rtr-custom-assessment' ),
				'mid'  => __( 'Your authentic presence is developing. Small shifts—deeper listening, sincere curiosity, and honest expression—will compound quickly.', 'rtr-custom-assessment' ),
				'low'  => __( 'This is a growth edge: practice sharing truth kindly, listening fully, and following curiosity so your presence feels grounded and real.', 'rtr-custom-assessment' ),
			),
			'Emotional Resonance' => array(
				'high' => __( 'You tune into emotion skillfully and respond with empathy. People likely feel seen, supported, and appreciated around you.', 'rtr-custom-assessment' ),
				'mid'  => __( 'You have solid emotional intelligence foundations. Continuing to name feelings, acknowledge vulnerability, and respond with care will deepen trust.', 'rtr-custom-assessment' ),
				'low'  => __( 'Focus here on noticing others\' cues, validating feelings, and expressing appreciation— these habits deepen connection fast.', 'rtr-custom-assessment' ),
			),
			'Strategic Visibility' => array(
				'high' => __( 'You are visible in the right rooms—digitally and in person—with a clear narrative and regular valuable contributions.', 'rtr-custom-assessment' ),
				'mid'  => __( 'Your visibility is growing. Clarify your story, share expertise consistently, and widen outreach to influential peers and communities.', 'rtr-custom-assessment' ),
				'low'  => __( 'Invest in how you show expertise online, show up in communities, and introduce yourself with clarity so opportunities can find you.', 'rtr-custom-assessment' ),
			),
			'Ecosystem Diversity' => array(
				'high' => __( 'Your network spans diverse perspectives and domains, fuelling creativity and introductions that cross boundaries.', 'rtr-custom-assessment' ),
				'mid'  => __( 'You are building breadth. Seek more varied contexts, make bridging introductions, and stay curious in unfamiliar settings.', 'rtr-custom-assessment' ),
				'low'  => __( 'Widen your ecosystem intentionally—different industries, backgrounds, and cultures—so ideas and relationships stay fresh.', 'rtr-custom-assessment' ),
			),
			'Strategic Intentionality' => array(
				'high' => __( 'You are deliberate about who is in your circle, how you follow up, and how you balance organic rapport with strategic growth.', 'rtr-custom-assessment' ),
				'mid'  => __( 'You are partly intentional. Sharpen clarity on who you need, systematize follow-up, and invest consistently in mentors and allies.', 'rtr-custom-assessment' ),
				'low'  => __( 'Define the qualities and relationships that matter, then build simple habits for follow-up and nurturing key connections.', 'rtr-custom-assessment' ),
			),
			'Value Exchange Mindset' => array(
				'high' => __( 'You give generously, spot needs early, and receive support with ease— a hallmark of high-trust networks.', 'rtr-custom-assessment' ),
				'mid'  => __( 'You exchange value in pockets. Practice proactive help, creative adds, and graceful receiving to strengthen reciprocity.', 'rtr-custom-assessment' ),
				'low'  => __( 'Lead with generosity: offer specific help, listen for unstated needs, and practice accepting support so the loop stays open.', 'rtr-custom-assessment' ),
			),
		);

		$level = 'mid';
		if ( $average >= 8.0 ) {
			$level = 'high';
		} elseif ( $average < 5.0 ) {
			$level = 'low';
		}

		if ( isset( $summaries[ $category ][ $level ] ) ) {
			return $summaries[ $category ][ $level ];
		}

		if ( 'high' === $level ) {
			return sprintf(
				/* translators: %s: category name */
				__( 'Strong results in %s. Keep reinforcing these behaviours in real conversations and collaborations.', 'rtr-custom-assessment' ),
				esc_html( $category )
			);
		}
		if ( 'low' === $level ) {
			return sprintf(
				/* translators: %s: category name */
				__( '%s is a priority growth area—choose one habit per week to practice in live interactions.', 'rtr-custom-assessment' ),
				esc_html( $category )
			);
		}
		return sprintf(
			/* translators: %s: category name */
			__( 'Solid baseline in %s with room to deepen consistency and impact.', 'rtr-custom-assessment' ),
			esc_html( $category )
		);
	}

	/**
	 * Get overall profile label based on total average.
	 *
	 * @param float  $average         Overall average score.
	 * @param string $assessment_type Optional. Mindset uses 1–5 scale; Social Fluency uses 1–10.
	 * @return string
	 */
	public static function get_overall_profile( $average, $assessment_type = null ) {
		$type = CA_Assessment_Types::normalize( null !== $assessment_type ? $assessment_type : CA_Assessment_Types::MINDSET );

		if ( CA_Assessment_Types::INNER_DIMENSIONS === $type ) {
			if ( $average >= 0.85 ) {
				return __( 'Highly Integrated Profile', 'rtr-custom-assessment' );
			}
			if ( $average >= 0.70 ) {
				return __( 'Strong Multidimensional Profile', 'rtr-custom-assessment' );
			}
			if ( $average >= 0.55 ) {
				return __( 'Balanced Multidimensional Profile', 'rtr-custom-assessment' );
			}
			if ( $average >= 0.40 ) {
				return __( 'Emerging Multidimensional Profile', 'rtr-custom-assessment' );
			}
			return __( 'Developing Multidimensional Profile', 'rtr-custom-assessment' );
		}

		if ( CA_Assessment_Types::SOCIAL_FLUENCY === $type ) {
			if ( $average >= 9.0 ) {
				return __( 'Exceptional Social Fluency', 'rtr-custom-assessment' );
			}
			if ( $average >= 7.5 ) {
				return __( 'Advanced Social Fluency', 'rtr-custom-assessment' );
			}
			if ( $average >= 6.0 ) {
				return __( 'Proficient Social Fluency', 'rtr-custom-assessment' );
			}
			if ( $average >= 4.0 ) {
				return __( 'Developing Social Fluency', 'rtr-custom-assessment' );
			}
			return __( 'Emerging Social Fluency', 'rtr-custom-assessment' );
		}

		if ( $average >= 4.5 ) {
			return 'Exceptional Entrepreneur';
		} elseif ( $average >= 3.75 ) {
			return 'High-Performing Entrepreneur';
		} elseif ( $average >= 3.0 ) {
			return 'Developing Entrepreneur';
		} elseif ( $average >= 2.0 ) {
			return 'Emerging Entrepreneur';
		} else {
			return 'Entrepreneurial Beginner';
		}
	}
}
