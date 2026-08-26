<?php
/**
 * Pause / resume history resolver.
 *
 * The live `_wcfmu_pause_dates` / `_wcfmu_pause_resume` meta only ever holds
 * today + future dates — it is pruned when a pause window passes or the
 * subscription resumes. The permanent record of every pause block lives in the
 * subscription's ORDER NOTES, e.g.:
 *
 *   "Pause set from admin — paused only on: 2026-08-05 (resumes 2026-08-06);
 *    2026-08-11 to 2026-08-12 (resumes 2026-08-13)."
 *
 * This helper unions both sources so the pause / resume reports can show any
 * date — past, present or future.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Reads pause + resume dates from live meta and historical order notes.
 */
class Pause_History {

	/**
	 * Per-request cache of a subscription's pause-schedule notes.
	 *
	 * @var array<int,string[]>
	 */
	private static $note_cache = array();

	/**
	 * The pause-schedule order notes for a subscription (those with a resumes
	 * block), each as an object with the note text and its GMT timestamp.
	 *
	 * @param int $sub_id Subscription id.
	 * @return array<int,object{content:string,ts:int}>
	 */
	private static function notes_for( $sub_id ) {
		$sub_id = (int) $sub_id;
		if ( isset( self::$note_cache[ $sub_id ] ) ) {
			return self::$note_cache[ $sub_id ];
		}
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT comment_content AS content, comment_date_gmt AS dt FROM {$wpdb->comments}
				 WHERE comment_type = 'order_note' AND comment_post_ID = %d AND comment_content LIKE %s",
				$sub_id,
				'%(resumes %'
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = (object) array(
				'content' => (string) $r->content,
				'ts'      => $r->dt ? (int) strtotime( $r->dt . ' UTC' ) : 0,
			);
		}
		self::$note_cache[ $sub_id ] = $out;
		return $out;
	}

	/**
	 * Extract "<pause> (resumes <resume>)" pairs from a note.
	 *
	 * @param string $text Note content.
	 * @return array<int,array{pause:string,resume:string}>
	 */
	private static function parse_pairs( $text ) {
		$pairs = array();
		if ( preg_match_all(
			'/(\d{4}-\d{2}-\d{2}(?:\s*to\s*\d{4}-\d{2}-\d{2})?)\s*\(\s*resumes\s+(\d{4}-\d{2}-\d{2})\s*\)/i',
			(string) $text,
			$m,
			PREG_SET_ORDER
		) ) {
			foreach ( $m as $hit ) {
				$pairs[] = array(
					'pause'  => preg_replace( '/\s+/', ' ', trim( $hit[1] ) ),
					'resume' => trim( $hit[2] ),
				);
			}
		}
		return $pairs;
	}

	/**
	 * Expand a pause token ("X" or "X to Y") into individual Y-m-d dates.
	 *
	 * @param string $token Pause token.
	 * @return string[]
	 */
	private static function expand_range( $token ) {
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})\s*to\s*(\d{4}-\d{2}-\d{2})$/i', $token, $mm ) ) {
			$out   = array();
			$cur   = strtotime( $mm[1] . ' UTC' );
			$end   = strtotime( $mm[2] . ' UTC' );
			$guard = 0;
			while ( $cur && $end && $cur <= $end && $guard++ < 400 ) {
				$out[] = gmdate( 'Y-m-d', $cur );
				$cur  += DAY_IN_SECONDS;
			}
			return $out;
		}
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $token ) ? array( $token ) : array();
	}

	/**
	 * Live pause dates from meta.
	 *
	 * @param int $sub_id Subscription id.
	 * @return string[]
	 */
	private static function meta_pause_dates( $sub_id ) {
		if ( class_exists( __NAMESPACE__ . '\\Subscription_Delivery' ) ) {
			return Subscription_Delivery::read_pause_dates( (int) $sub_id );
		}
		return class_exists( __NAMESPACE__ . '\\Subscription_API' )
			? Subscription_API::parse_pause_dates( get_post_meta( (int) $sub_id, '_wcfmu_pause_dates', true ) )
			: array();
	}

	/**
	 * The current pause dates (Y-m-d) for a subscription.
	 *
	 * Uses only the latest pause log — the durable plugin meta (`_aaraa_pause_dates`)
	 * unioned with the live `_wcfmu_pause_dates` — NOT historical order notes. This
	 * way a date that was removed from a pause (or superseded by a later edit) no
	 * longer appears in the report, even though an older order note still mentions it.
	 *
	 * @param int $sub_id Subscription id.
	 * @return string[]
	 */
	public static function pause_dates_for( $sub_id ) {
		return self::meta_pause_dates( $sub_id );
	}

	/**
	 * Every resume date (Y-m-d) for a subscription — meta-derived + historical notes.
	 *
	 * A resume date is the day after a pause date that is not itself a pause date
	 * (the day delivery restarts).
	 *
	 * @param int $sub_id Subscription id.
	 * @return string[]
	 */
	public static function resume_dates_for( $sub_id ) {
		$set  = array();
		$meta = self::meta_pause_dates( $sub_id );
		$flip = array_flip( $meta );
		foreach ( $meta as $d ) {
			$next = gmdate( 'Y-m-d', strtotime( $d . ' +1 day' ) );
			if ( ! isset( $flip[ $next ] ) ) {
				$set[ $next ] = 1;
			}
		}
		$resume_meta = (string) get_post_meta( (int) $sub_id, '_wcfmu_pause_resume', true );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $resume_meta ) ) {
			$set[ $resume_meta ] = 1;
		}
		// Latest log only: derived from current pause meta above — historical order
		// notes are intentionally not merged in, so removed/superseded resume dates
		// don't linger.
		return array_keys( $set );
	}

	/**
	 * When the pause covering $date was actioned (max order-note GMT timestamp
	 * whose block includes $date). 0 when unknown.
	 *
	 * @param int    $sub_id Subscription id.
	 * @param string $date   Y-m-d.
	 * @return int
	 */
	public static function pause_action_time( $sub_id, $date ) {
		$best = 0;
		foreach ( self::notes_for( $sub_id ) as $note ) {
			foreach ( self::parse_pairs( $note->content ) as $p ) {
				if ( in_array( $date, self::expand_range( $p['pause'] ), true ) && $note->ts > $best ) {
					$best = $note->ts;
				}
			}
		}
		return $best;
	}

	/**
	 * When the resume on $date was actioned (max order-note GMT timestamp whose
	 * resume equals $date). 0 when unknown.
	 *
	 * @param int    $sub_id Subscription id.
	 * @param string $date   Y-m-d.
	 * @return int
	 */
	public static function resume_action_time( $sub_id, $date ) {
		$best = 0;
		foreach ( self::notes_for( $sub_id ) as $note ) {
			foreach ( self::parse_pairs( $note->content ) as $p ) {
				if ( $p['resume'] === $date && $note->ts > $best ) {
					$best = $note->ts;
				}
			}
		}
		return $best;
	}

	/**
	 * Candidate subscription ids to test for a pause date.
	 *
	 * Sourced from the current pause meta only — the durable plugin key
	 * (`_aaraa_pause_dates`) and the live `_wcfmu_pause_dates` — so removed /
	 * superseded dates recorded only in old order notes are not picked up.
	 *
	 * @param string $date Y-m-d.
	 * @return int[]
	 */
	public static function candidates_for_pause( $date ) {
		global $wpdb;
		$durable_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_aaraa_pause_dates' AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $date ) . '%'
			)
		);
		$meta_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wcfmu_pause_dates' AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $date ) . '%'
			)
		);
		return self::merge_ids( $durable_ids, $meta_ids );
	}

	/**
	 * Candidate subscription ids to test for a resume date.
	 *
	 * Sourced from the current pause meta only — the durable plugin key
	 * (`_aaraa_pause_dates`, previous day) plus the live `_wcfmu_pause_dates`
	 * (previous day) and `_wcfmu_pause_resume` — so removed / superseded resume
	 * dates that survive only in old order notes are not picked up.
	 *
	 * @param string $date Y-m-d (the resume day).
	 * @return int[]
	 */
	public static function candidates_for_resume( $date ) {
		global $wpdb;
		$prev = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );

		$durable_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_aaraa_pause_dates' AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $prev ) . '%'
			)
		);
		$meta_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wcfmu_pause_dates' AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $prev ) . '%'
			)
		);
		$resume_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wcfmu_pause_resume' AND meta_value = %s",
				$date
			)
		);
		return self::merge_ids( $durable_ids, $meta_ids, $resume_ids );
	}

	/**
	 * Merge several id lists into a unique int list.
	 *
	 * @param array ...$lists Id lists.
	 * @return int[]
	 */
	private static function merge_ids( ...$lists ) {
		$out = array();
		foreach ( $lists as $list ) {
			foreach ( (array) $list as $id ) {
				$id = (int) $id;
				if ( $id ) {
					$out[ $id ] = 1;
				}
			}
		}
		return array_keys( $out );
	}
}
