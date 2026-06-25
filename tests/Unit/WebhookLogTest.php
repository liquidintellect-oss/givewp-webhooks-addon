<?php

use GiveWebhooks\WebhookLog;
use PHPUnit\Framework\TestCase;

class WebhookLogTest extends TestCase {

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new WpdbStub();
	}

	/** @test */
	public function create_inserts_a_pending_row_and_returns_its_id(): void {
		global $wpdb;
		$wpdb->insert_id = 77;

		$id = WebhookLog::create( 5, 'donation.completed', 'https://example.com/hook', 2 );

		$this->assertSame( 77, $id );
		$this->assertCount( 1, $wpdb->inserts );

		$data = $wpdb->inserts[0]['data'];
		$this->assertSame( 5, $data['donation_id'] );
		$this->assertSame( 'donation.completed', $data['event'] );
		$this->assertSame( 'https://example.com/hook', $data['url'] );
		$this->assertSame( 'pending', $data['status'] );
		$this->assertSame( 2, $data['attempt'] );
	}

	/** @test */
	public function record_result_updates_the_matching_row(): void {
		WebhookLog::recordResult( 9, 'success', 200, 'OK' );

		global $wpdb;
		$this->assertCount( 1, $wpdb->updates );
		$this->assertSame( array( 'id' => 9 ), $wpdb->updates[0]['where'] );
		$this->assertSame( 'success', $wpdb->updates[0]['data']['status'] );
		$this->assertSame( 200, $wpdb->updates[0]['data']['response_code'] );
		$this->assertSame( 'OK', $wpdb->updates[0]['data']['response_body'] );
	}

	/** @test */
	public function record_result_truncates_long_response_bodies(): void {
		$long_body = str_repeat( 'x', 6000 );

		WebhookLog::recordResult( 9, 'failed', 500, $long_body );

		global $wpdb;
		$this->assertSame( 5000, strlen( $wpdb->updates[0]['data']['response_body'] ) );
	}

	/** @test */
	public function recent_returns_the_seeded_rows(): void {
		global $wpdb;
		$wpdb->resultsToReturn = array(
			array( 'id' => 2, 'status' => 'success' ),
			array( 'id' => 1, 'status' => 'failed' ),
		);

		$rows = WebhookLog::recent( 10 );

		$this->assertSame( $wpdb->resultsToReturn, $rows );
		$this->assertCount( 1, $wpdb->queries );
		$this->assertStringContainsString( 'LIMIT 10', $wpdb->queries[0] );
	}

	/** @test */
	public function prune_older_than_issues_a_delete_query(): void {
		WebhookLog::pruneOlderThan( 30 );

		global $wpdb;
		$this->assertCount( 1, $wpdb->queries );
		$this->assertStringContainsString( 'DELETE FROM', $wpdb->queries[0] );
	}
}
