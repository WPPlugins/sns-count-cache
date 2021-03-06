<?php
/*
class-scc-follow-lazy-cache-engine.php

Description: This class is a data cache engine whitch get and cache data using wp-cron at regular intervals
Author: Daisuke Maruyama
Author URI: http://marubon.info/
License: GPL2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

/*
Copyright (C) 2014 - 2017 Daisuke Maruyama

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class SCC_Follow_Lazy_Cache_Engine extends SCC_Follow_Cache_Engine {

	/**
	 * Prefix of cache ID
	 */
	const DEF_TRANSIENT_PREFIX = 'scc_follow_count_';

	/**
	 * Cron name to schedule cache processing
	 */
	const DEF_PRIME_CRON = 'scc_follow_lazycache_prime';

	/**
	 * Cron name to execute cache processing
	 */
	const DEF_EXECUTE_CRON = 'scc_follow_lazycache_exec';

	/**
	 * Schedule name for cache processing
	 */
	const DEF_EVENT_SCHEDULE = 'follow_lazy_cache_event';

	/**
	 * Schedule description for cache processing
	 */
	const DEF_EVENT_DESCRIPTION = '[SCC] Follow Lazy Cache Interval';

	/**
	 * Interval cheking and caching target data
	 */
	private $check_interval = 600;

	/**
	 * Latency suffix
	 */
	private $check_latency = 10;

	/**
	 * Initialization
	 *
	 * @since 0.1.1
	 */
	public function initialize( $options = array() ) {
		SCC_Common_Util::log( '[' . __METHOD__ . '] (line='. __LINE__ . ')' );

		$this->cache_prefix = self::DEF_TRANSIENT_PREFIX;
		$this->prime_cron = self::DEF_PRIME_CRON;
		$this->execute_cron = self::DEF_EXECUTE_CRON;
		$this->event_schedule = self::DEF_EVENT_SCHEDULE;
		$this->event_description = self::DEF_EVENT_DESCRIPTION;

		$this->load_ratio = 0.5;

		if ( isset( $options['delegate'] ) ) $this->delegate = $options['delegate'];
		if ( isset( $options['crawler'] ) ) $this->crawler = $options['crawler'];
		if ( isset( $options['target_sns'] ) ) $this->target_sns = $options['target_sns'];
		if ( isset( $options['check_interval'] ) ) $this->check_interval = $options['check_interval'];
		if ( isset( $options['cache_prefix'] ) ) $this->cache_prefix = $options['cache_prefix'];
		if ( isset( $options['execute_cron'] ) ) $this->execute_cron = $options['execute_cron'];
		if ( isset( $options['check_latency'] ) ) $this->check_latency = $options['check_latency'];
		if ( isset( $options['scheme_migration_mode'] ) ) $this->scheme_migration_mode = $options['scheme_migration_mode'];
		if ( isset( $options['scheme_migration_exclude_keys'] ) ) $this->scheme_migration_exclude_keys = $options['scheme_migration_exclude_keys'];
		if ( isset( $options['cache_retry'] ) ) $this->cache_retry = $options['cache_retry'];
		if ( isset( $options['retry_limit'] ) ) $this->retry_limit = $options['retry_limit'];

		add_action( $this->execute_cron, array( $this, 'execute_cache' ), 10, 0 );
	}

	/**
	 * Register base schedule for this engine
	 *
	 * @since 0.1.0
	 */
	public function register_schedule() {
		SCC_Common_Util::log( '[' . __METHOD__ . '] (line='. __LINE__ . ')' );
	}

	/**
	 * Unregister base schedule for this engine
	 *
	 * @since 0.1.0
	 */
	public function unregister_schedule() {
		SCC_Common_Util::log( '[' . __METHOD__ . '] (line='. __LINE__ . ')' );

		SCC_WP_Cron_Util::clear_scheduled_hook( $this->execute_cron );
	}

	/**
	 * Schedule data retrieval and cache processing
	 *
	 * @since 0.4.0
	 */
	public function prime_cache() {
		SCC_Common_Util::log( '[' . __METHOD__ . '] (line='. __LINE__ . ')' );

		$next_exec_time = (int) current_time( 'timestamp', 1 ) + $this->check_latency;

		SCC_Common_Util::log( '[' . __METHOD__ . '] check_latency: ' . $this->check_latency );
		SCC_Common_Util::log( '[' . __METHOD__ . '] next_exec_time: ' . $next_exec_time );

		wp_schedule_single_event( $next_exec_time, $this->execute_cron, array() );
	}

	/**
	 * Get and cache data of each published post
	 *
	 * @since 0.4.0
	 */
	public function execute_cache() {
		SCC_Common_Util::log( '[' . __METHOD__ . '] (line='. __LINE__ . ')' );

		$cache_expiration = $this->get_cache_expiration();

		SCC_Common_Util::log( '[' . __METHOD__ . '] cache_expiration: ' . $cache_expiration );

		$transient_id = $this->get_cache_key( 'follow' );

		$options = array(
			'cache_key' => $transient_id,
			'target_sns' => $this->target_sns,
			'cache_expiration' => $cache_expiration
		);

		// Primary cache
		$this->cache( $options );

		// Secondary cache
		$this->delegate_order( SCC_Order::ORDER_DO_SECOND_CACHE, $options );
	}

	/**
	 * Get cache expiration based on current number of total post and page
	 *
	 * @since 0.4.0
	 */
	protected function get_cache_expiration() {
		SCC_Common_Util::log( '[' . __METHOD__ . '] (line='. __LINE__ . ')' );

		return 3 * $this->check_interval;
	}

	/**
	 * Initialize meta key for ranking
	 *
	 * @since 0.3.0
	 */
	public function initialize_cache() {
		SCC_Common_Util::log( '[' . __METHOD__ . '] (line='. __LINE__ . ')' );
	}

	/**
	 * Clear meta key for ranking
	 *
	 * @since 0.3.0
	 */
	public function clear_cache() {
		SCC_Common_Util::log( '[' . __METHOD__ . '] (line='. __LINE__ . ')' );
	}

	/**
	 * Get cache
	 *
	 * @since 0.10.1
	 */
	public function get_cache( $options = array() ) {

		$transient_id = $this->get_cache_key( 'follow' );

		$sns_followers = array();

		if ( false !== ( $sns_followers = get_transient( $transient_id ) ) ) {
			return $sns_followers;
		} else {
			return $this->delegate_order( SCC_Order::ORDER_GET_SECOND_CACHE, $options );
		}
	}

}

?>
