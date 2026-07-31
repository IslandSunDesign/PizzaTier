<?php
namespace PizzaTier\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central hook registry. Collects all add_action / add_filter calls and
 * executes them together via run(), making it easy to audit every hook
 * the plugin registers.
 */
class Loader {

	/** @var array[] */
	private array $actions = [];

	/** @var array[] */
	private array $filters = [];

	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	public function run(): void {
		foreach ( $this->filters as $f ) {
			add_filter( $f['hook'], [ $f['component'], $f['callback'] ], $f['priority'], $f['accepted_args'] );
		}
		foreach ( $this->actions as $a ) {
			add_action( $a['hook'], [ $a['component'], $a['callback'] ], $a['priority'], $a['accepted_args'] );
		}
	}
}
