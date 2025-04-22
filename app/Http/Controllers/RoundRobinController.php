<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\Arr;

class RoundRobinController extends Controller {

	public function getStats( Request $request ) {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->sendError( [
				'message' => 'You do not have permission to access this resource'
			], 403 );
		}


		$data        = get_option( 'fluent_smtp_round_robin_data', [ 'index' => 0, 'count' => 0 ] );
		$settings    = fluentMailGetSettings();
		$connections = isset( $settings['connections'] ) ? $settings['connections'] : [];

		// Get daily limits
		$dailyLimits = get_option( 'fluent_smtp_daily_limits', [] );

		// Get today's email count
		$today       = date( 'Y-m-d' );
		$dailyCounts = get_option( 'fluent_smtp_daily_counts', [] );
		$todayCounts = isset( $dailyCounts[ $today ] ) ? $dailyCounts[ $today ] : [];

		$activeConnections = [];
		foreach ( $connections as $key => $connection ) {
			$activeConnections[ $key ] = $connection;

		}

		$connectionKeys    = array_keys( $activeConnections );
		$currentConnection = isset( $connectionKeys[ $data['index'] ] ) ? $connectionKeys[ $data['index'] ] : 'none';

		$currentConnectionDetails = [];
		if ( $currentConnection !== 'none' && isset( $activeConnections[ $currentConnection ] ) ) {
			$conn                     = $activeConnections[ $currentConnection ];
			$currentConnectionDetails = [
				'id'           => $currentConnection,
				'title'        => $conn['title'],
				'sender_email' => $conn['provider_settings']['sender_email'],
				'sender_name'  => $conn['provider_settings']['sender_name'],
				'provider'     => $conn['provider_settings']['provider']
			];
		}
		// Get statistics for each connection
		$emailStats = [];
		foreach ( $activeConnections as $key => $connection ) {
			$dailyLimit = isset( $dailyLimits[ $key ] ) ? intval( $dailyLimits[ $key ] ) : 0;
			$todayCount = isset( $todayCounts[ $key ] ) ? intval( $todayCounts[ $key ] ) : 0;

			$emailStats[ $key ] = [
				'id'            => $key,
				'title'         => $connection['title'],
				'sender_email'  => $connection['provider_settings']['sender_email'],
				'provider'      => $connection['provider_settings']['provider'],
				'total_sent'    => $this->getConnectionEmailCount( $key ),
				'daily_limit'   => $dailyLimit,
				'today_count'   => $todayCount,
				'limit_reached' => ( $dailyLimit > 0 && $todayCount >= $dailyLimit )
			];
		}

		$isRoundRobinActive = get_option('fluent_smtp_round_robin_enabled', 0);

		return $this->send( [
			'data' => [
				'current_index'         => $data['index'],
				'email_count'           => $data['count'],
				'active_connections'    => count( $activeConnections ),
				'current_connection'    => $currentConnectionDetails,
				'connection_stats'      => array_values( $emailStats ),
				'is_round_robin_active' => (bool)$isRoundRobinActive,
				'today'                 => $today,
			]
		] );
	}

	public function resetRoundRobin( Request $request ) {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->sendError( [
				'message' => 'You do not have permission to perform this action'
			], 403 );
		}

		update_option( 'fluent_smtp_round_robin_data', [
			'index' => 0,
			'count' => 0
		] );

		return $this->send( [
			'message' => 'Round-robin status has been reset successfully',
			'status'  => 'success'
		] );
	}

	public function resetDailyCounts( Request $request ) {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->sendError( [
				'message' => 'You do not have permission to perform this action'
			], 403 );
		}

		$today                 = date( 'Y-m-d' );
		$dailyCounts           = get_option( 'fluent_smtp_daily_counts', [] );
		$dailyCounts[ $today ] = [];

		update_option( 'fluent_smtp_daily_counts', $dailyCounts );

		return $this->send( [
			'message' => 'Daily email counts have been reset successfully',
			'status'  => 'success'
		] );
	}

	public function saveDailyLimits( Request $request ) {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->sendError( [
				'message' => 'You do not have permission to perform this action'
			], 403 );
		}

		$limits = $request->get( 'limits', [] );

		// Validate that limits are non-negative integers
		foreach ( $limits as $connectionId => $limit ) {
			$limits[ $connectionId ] = max( 0, intval( $limit ) );
		}

		update_option( 'fluent_smtp_daily_limits', $limits );

		return $this->send( [
			'message' => 'Daily sending limits have been saved successfully',
			'status'  => 'success'
		] );
	}

	private function getConnectionEmailCount( $connectionId ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fluentmail_logs';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE status = 'sent' AND connection_id = %s",
			$connectionId
		) );

		return $count ? intval( $count ) : 0;
	}

	public function changeStatus(Request $request) {
		// Check user capabilities
		if (!current_user_can('manage_options')) {
			return $this->sendError([
				'message' => 'You do not have permission to perform this action'
			], 403);
		}

		// Get status from request
		$status = $request->get('status', false);

		// Convert to boolean if needed
		if (is_string($status)) {
			$status = $status === 'true' || $status === '1' || $status === 'yes' || $status === 'active';
		}

		// Save the status in options
		update_option('fluent_smtp_round_robin_enabled', $status ? 1 : 0);

		return $this->send([
			'message' => $status
				? 'Round-Robin has been activated'
				: 'Round-Robin has been deactivated',
			'status' => $status ? 'active' : 'inactive'
		]);
	}
}