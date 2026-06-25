<?php
/**
 * Minimal $wpdb stand-in used to unit test WebhookLog without a real database.
 *
 * Records every insert()/update()/query() call so tests can assert on what
 * SQL/parameters the plugin sent, and lets tests pre-seed get_results().
 */
class WpdbStub {
	public string $prefix = 'wp_';
	public int $insert_id = 123;

	/** @var array<int,array{table:string,data:array,format:?array}> */
	public array $inserts = array();

	/** @var array<int,array{table:string,data:array,where:array}> */
	public array $updates = array();

	/** @var array<int,string> */
	public array $queries = array();

	/** @var array<int,array<string,mixed>> */
	public array $resultsToReturn = array();

	public function insert( string $table, array $data, $format = null ): bool {
		$this->inserts[] = array(
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		);
		return true;
	}

	public function update( string $table, array $data, array $where, $data_format = null, $where_format = null ): bool {
		$this->updates[] = array(
			'table' => $table,
			'data'  => $data,
			'where' => $where,
		);
		return true;
	}

	public function query( string $sql ) {
		$this->queries[] = $sql;
		return true;
	}

	public function get_results( string $sql, string $output = 'ARRAY_A' ) {
		$this->queries[] = $sql;
		return $this->resultsToReturn;
	}

	public function get_charset_collate(): string {
		return '';
	}

	/**
	 * Minimal stand-in for wpdb::prepare() -- substitutes %d/%s placeholders
	 * in order. Good enough for the simple queries this plugin issues.
	 */
	public function prepare( string $query, ...$args ): string {
		if ( count( $args ) === 1 && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$i = 0;
		return preg_replace_callback(
			'/%[ds]/',
			function ( $matches ) use ( $args, &$i ) {
				$value = $args[ $i ] ?? '';
				++$i;
				return '%d' === $matches[0] ? (string) (int) $value : "'" . $value . "'";
			},
			$query
		);
	}
}
