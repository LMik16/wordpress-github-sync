<?php

/**
 * GitHub Export Manager.
 */
class WordPress_GitHub_Sync_Export {

	/**
	 * Current GitHub tree.
	 *
	 * @var WordPress_GitHub_Sync_Tree
	 */
	protected $tree;

	/**
	 * Commit message for export.
	 *
	 * @var string
	 */
	protected $msg;

	/**
	 * Initializes a new export manager.
	 *
	 * @param array|int $source post ID or array of post IDs
	 * @param string $msg commit message
	 */
	public function __construct( $source, $msg ) {
		if ( is_array( $source ) ) {
			$this->ids = $source;
		}

		if ( is_int( $source ) ) {
			$this->ids = array( $source );
		}

		$this->msg  = $msg;
		$this->tree = new WordPress_GitHub_Sync_Tree();
	}

	/**
	 * Runs the export process.
	 *
	 * Passing in true will delete all the posts provided in the constructor.
	 *
	 * @param bool|false $delete
	 */
	public function run( $delete = false ) {
		$this->tree->fetch_last();

		if ( ! $this->tree->ready() ) {
			WordPress_GitHub_Sync::write_log( __( 'Failed getting tree with error: ',
					WordPress_GitHub_Sync::$text_domain ) . $this->tree->last_error() );

			return;
		}

		WordPress_GitHub_Sync::write_log( __( 'Building the tree.', WordPress_GitHub_Sync::$text_domain ) );
		foreach ( $this->ids as $post_id ) {
			$post = new WordPress_GitHub_Sync_Post( $post_id );
			$this->tree->post_to_tree( $post, $delete );
		}

		$result = $this->tree->export( $this->msg );

		if ( ! $result ) {
			$this->no_change();

			return;
		}

		if ( is_wp_error( $result ) ) {
			$this->error( $result );

			return;
		}

		$this->tree->fetch_last();

		// @todo what if we fail?
		if ( $this->tree->ready() ) {
			WordPress_GitHub_Sync::write_log( __( 'Saving the shas.', WordPress_GitHub_Sync::$text_domain ) );
			$this->save_post_shas();
		}

		$this->success();
	}

	/**
	 * Writes out the results of an unchanged export
	 */
	public function no_change() {
		update_option( '_wpghs_export_complete', 'yes' );
		WordPress_GitHub_Sync::write_log( __( 'There were no changes, so no additional commit was added.',
			WordPress_GitHub_Sync::$text_domain ), 'warning' );
	}

	/**
	 * Writes out the results of an error and saves the data
	 *
	 * @param WP_Error $result
	 */
	public function error( $result ) {
		update_option( '_wpghs_export_error', $result->get_error_message() );
		WordPress_GitHub_Sync::write_log( __( 'Error exporting to GitHub. Error: ',
				WordPress_GitHub_Sync::$text_domain ) . $result->get_error_message(), 'error' );
	}

	/**
	 * Use the new tree to save sha data
	 * for all the updated posts
	 *
	 * @param WordPress_GitHub_Sync_Tree $tree
	 */
	public function save_post_shas() {
		foreach ( $this->ids as $post_id ) {
			$post = new WordPress_GitHub_Sync_Post( $post_id );
			$blob = $this->tree->get_blob_for_path( $post->github_path() );

			if ( $blob ) {
				$post->set_sha( $blob->sha );
			} else {
				WordPress_GitHub_Sync::write_log( __( 'No sha matched for post ID ',
						WordPress_GitHub_Sync::$text_domain ) . $post_id );
			}
		}
	}

	/**
	 * Writes out the results of a successful export
	 */
	public function success() {
		update_option( '_wpghs_export_complete', 'yes' );
		update_option( '_wpghs_fully_exported', 'yes' );
		WordPress_GitHub_Sync::write_log( __( 'Export to GitHub completed successfully.',
			WordPress_GitHub_Sync::$text_domain ), 'success' );
	}

}