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
		$categories   = CA_Questions::get_all();
		$flat         = CA_Questions::get_flat();
		$total_score  = 0;
		$cat_scores   = array();

		// Build category totals
		foreach ( $categories as $cat ) {
			$cat_scores[ $cat['category'] ] = array(
				'name'     => $cat['category'],
				'subtotal' => 0,
				'count'    => count( $cat['questions'] ),
				'average'  => 0.00,
			);
		}

		foreach ( $flat as $q ) {
			$idx    = $q['index'];
			$cat    = $q['category'];
			$answer = isset( $answers[ $idx ] ) ? (int) $answers[ $idx ] : 0;
			$total_score += $answer;
			$cat_scores[ $cat ]['subtotal'] += $answer;
		}

		// Calculate averages
		foreach ( $cat_scores as &$cat ) {
			$cat['average'] = $cat['count'] > 0
				? round( $cat['subtotal'] / $cat['count'], 2 )
				: 0.00;
		}
		unset( $cat );

		$total_questions  = count( $flat );
		$average_score    = $total_questions > 0
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
	 * @param float  $average  Category average (0–5).
	 * @return string
	 */
	public static function get_category_summary( $category, $average ) {
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
	 * Get overall profile label based on total average.
	 *
	 * @param float $average Overall average score.
	 * @return string
	 */
	public static function get_overall_profile( $average ) {
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
