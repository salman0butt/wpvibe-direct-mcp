<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expiring, media-only browser transfer for MCP clients that cannot send file
 * bytes in tool arguments. The upload ticket is not WordPress authentication
 * and never contains the Direct MCP bearer token.
 */
final class WPVDMCP_Upload {
	const TTL       = 1800;
	const MAX_FILES = 5;
	const MAX_BYTES = 10485760; // 10 MiB hard cap; WordPress may be lower.
	const ACTION    = 'wpvdmcp_upload';

	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'serve_browser' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'serve_browser' ) );
	}

	/** Create an upload ticket and return only the browser URL secret. */
	public static function request( $args ) {
		$args  = is_array( $args ) ? $args : array();
		$owner = (int) get_option( 'wpvdmcp_user_id', 0 );
		$user  = $owner ? get_user_by( 'id', $owner ) : false;
		if ( ! $owner || ! $user || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'upload_owner_missing', 'Direct MCP has no valid Administrator token owner.', array( 'status' => 401 ) );
		}

		$id     = 'up_' . bin2hex( random_bytes( 16 ) );
		$secret = self::base64url( random_bytes( 32 ) );
		$now    = time();
		$record = array(
			'id'            => $id,
			'secret_hash'   => hash( 'sha256', $secret ),
			'owner_user_id' => $owner,
			'status'        => 'waiting',
			'created_at'    => $now,
			'expires_at'    => $now + self::TTL,
			'post_id'       => isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0,
			'title'         => isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '',
			'alt_text'      => isset( $args['alt_text'] ) ? sanitize_text_field( $args['alt_text'] ) : '',
			'files'         => array(),
		);
		set_transient( self::key( $id ), $record, self::TTL );

		return array(
			'upload_id'     => $id,
			'status'        => 'waiting',
			'expires_at'    => $record['expires_at'],
			'upload_url'    => admin_url( 'admin-post.php?action=' . self::ACTION . '&upload_id=' . rawurlencode( $id ) . '&upload_token=' . rawurlencode( $secret ) ),
			'max_files'     => self::MAX_FILES,
			'max_file_size' => self::max_file_bytes(),
			'allowed_types' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ),
			'note'          => 'Open the upload_url in a browser when the MCP client cannot transfer attachment bytes. The URL expires in 30 minutes and becomes unusable after a successful upload.',
		);
	}

	/** Safe ticket status for the authenticated MCP caller. */
	public static function check( $id ) {
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		if ( (int) get_option( 'wpvdmcp_user_id', 0 ) !== (int) $record['owner_user_id'] ) {
			return new WP_Error( 'upload_owner_changed', 'The Direct MCP token owner changed after this upload was requested.', array( 'status' => 409 ) );
		}
		return self::safe_record( $record );
	}

	/** Validate the public browser secret without granting any other WordPress capability. */
	public static function validate_ticket( $id, $secret ) {
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		if ( 'waiting' !== $record['status'] || empty( $record['secret_hash'] ) ) {
			return new WP_Error( 'upload_not_waiting', 'This upload ticket has already been used or closed.', array( 'status' => 409 ) );
		}
		$hash = hash( 'sha256', (string) $secret );
		if ( ! hash_equals( (string) $record['secret_hash'], $hash ) ) {
			return new WP_Error( 'upload_token_invalid', 'The upload link is invalid.', array( 'status' => 403 ) );
		}
		return true;
	}

	/** Finish a ticket atomically after the media library accepted its files. */
	public static function finalize( $id, $secret, $files ) {
		$valid = self::validate_ticket( $id, $secret );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		$safe_files = array();
		foreach ( (array) $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$safe_files[] = array(
				'attachment_id' => isset( $file['attachment_id'] ) ? absint( $file['attachment_id'] ) : 0,
				'filename'      => isset( $file['filename'] ) ? sanitize_file_name( $file['filename'] ) : '',
				'mime_type'     => isset( $file['mime_type'] ) ? sanitize_text_field( $file['mime_type'] ) : '',
				'url'           => isset( $file['url'] ) ? esc_url_raw( $file['url'] ) : '',
				'width'         => isset( $file['width'] ) ? absint( $file['width'] ) : null,
				'height'        => isset( $file['height'] ) ? absint( $file['height'] ) : null,
			);
		}
		if ( ! $safe_files ) {
			return new WP_Error( 'upload_empty_result', 'No media attachments were created.', array( 'status' => 422 ) );
		}
		$record['files']       = $safe_files;
		$record['status']      = 'ready';
		$record['ready_at']    = time();
		$record['secret_hash'] = ''; // Makes the browser URL non-replayable.
		set_transient( self::key( $id ), $record, max( 1, (int) $record['expires_at'] - time() ) );
		return true;
	}

	public static function is_allowed_raster_mime( $mime ) {
		return in_array( strtolower( trim( (string) $mime ) ), array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true );
	}

	/** Public admin-post handler authenticated only by the narrow upload ticket. */
	public static function serve_browser() {
		self::browser_headers();
		$id     = isset( $_REQUEST['upload_id'] ) && is_string( $_REQUEST['upload_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['upload_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ticket is the authorization.
		$secret = isset( $_REQUEST['upload_token'] ) && is_string( $_REQUEST['upload_token'] ) ? (string) wp_unslash( $_REQUEST['upload_token'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ticket is the authorization.
		$valid  = self::validate_ticket( $id, $secret );
		if ( is_wp_error( $valid ) ) {
			self::render_page( 'Upload unavailable', $valid->get_error_message(), false );
			return;
		}
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			self::render_page( 'Upload unavailable', $record->get_error_message(), false );
			return;
		}
		$user = get_user_by( 'id', (int) $record['owner_user_id'] );
		if ( ! $user || ! user_can( $user, 'manage_options' ) || ! user_can( $user, 'upload_files' ) ) {
			self::render_page( 'Upload unavailable', 'The Direct MCP token owner no longer has permission to upload media.', false );
			return;
		}
		wp_set_current_user( (int) $record['owner_user_id'] );

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'POST' !== $method ) {
			self::render_form( $id, $secret, $record );
			return;
		}

		$result = self::handle_files( $record, isset( $_FILES['files'] ) ? $_FILES['files'] : array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- possession of one-time ticket is the authorization.
		if ( is_wp_error( $result ) ) {
			self::audit( $id, false, $result->get_error_message(), array() );
			self::render_page( 'Upload failed', $result->get_error_message(), false );
			return;
		}
		$done = self::finalize( $id, $secret, $result );
		if ( is_wp_error( $done ) ) {
			self::render_page( 'Upload failed', $done->get_error_message(), false );
			return;
		}
		self::audit( $id, true, 'Media uploaded from browser transfer.', $result );
		self::render_page( 'Upload complete', 'The media is in the WordPress Media Library. You can return to your MCP client; check_upload will now return the attachment details.', true );
	}

	private static function handle_files( $record, $files ) {
		$normalized = self::normalize_files( $files );
		if ( ! $normalized ) {
			return new WP_Error( 'upload_no_file', 'Choose at least one image file.', array( 'status' => 400 ) );
		}
		if ( count( $normalized ) > self::MAX_FILES ) {
			return new WP_Error( 'upload_too_many_files', 'Too many files. Upload at most ' . self::MAX_FILES . ' images at once.', array( 'status' => 413 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$created = array();
		foreach ( $normalized as $file ) {
			if ( (int) $file['error'] !== UPLOAD_ERR_OK ) {
				return new WP_Error( 'upload_php_error', self::php_upload_error( (int) $file['error'] ), array( 'status' => 400 ) );
			}
			if ( empty( $file['tmp_name'] ) || ! is_file( $file['tmp_name'] ) ) {
				return new WP_Error( 'upload_temp_missing', 'PHP did not provide a readable temporary upload file.', array( 'status' => 500 ) );
			}
			$size = (int) filesize( $file['tmp_name'] );
			if ( $size <= 0 || $size > self::max_file_bytes() ) {
				return new WP_Error( 'upload_size', 'The image is empty or exceeds the allowed upload size.', array( 'status' => 413, 'bytes' => $size, 'limit' => self::max_file_bytes() ) );
			}

			$name      = sanitize_file_name( (string) $file['name'] );
			$mime      = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $file['tmp_name'] ) : '';
			$looks_svg = false;
			if ( class_exists( 'WPVibe_REST' ) && method_exists( 'WPVibe_REST', 'is_svg_download' ) ) {
				$looks_svg = (bool) WPVibe_REST::is_svg_download( $file['tmp_name'], $mime );
			} elseif ( 'svg' === strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				$looks_svg = true;
			}

			if ( $looks_svg ) {
				if ( ! class_exists( 'WPVibe_REST' ) || ! method_exists( 'WPVibe_REST', 'sideload_svg' ) || ! class_exists( 'WPVibe_SVG_Sanitizer' ) ) {
					return new WP_Error( 'svg_support_unavailable', 'Safe SVG upload requires a current WPVibe version with its SVG sanitizer. Use PNG or WebP, or update WPVibe.', array( 'status' => 415 ) );
				}
				$svg = WPVibe_REST::sideload_svg( $file['tmp_name'], $name, (int) $record['post_id'], (string) $record['title'] );
				if ( is_wp_error( $svg ) ) {
					return $svg;
				}
				$id = (int) $svg['attachment_id'];
			} else {
				if ( ! self::is_allowed_raster_mime( $mime ) ) {
					return new WP_Error( 'unsupported_media_type', 'Only JPEG, PNG, GIF, WebP, and WPVibe-sanitized SVG images are accepted by this upload link.', array( 'status' => 415, 'detected_type' => $mime ? $mime : 'unknown' ) );
				}
				if ( class_exists( 'WPVibe_REST' ) && method_exists( 'WPVibe_REST', 'check_sideload_file' ) ) {
					$gate = WPVibe_REST::check_sideload_file( $file['tmp_name'], $name, false, $mime );
					if ( is_wp_error( $gate ) ) {
						return $gate;
					}
				}
				$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $file['tmp_name'] ), (int) $record['post_id'], (string) $record['title'] );
				if ( is_wp_error( $id ) ) {
					return ( class_exists( 'WPVibe_REST' ) && method_exists( 'WPVibe_REST', 'sideload_failure_error' ) ) ? WPVibe_REST::sideload_failure_error( $id ) : $id;
				}
			}

			if ( ! empty( $record['alt_text'] ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $record['alt_text'] );
			}
			$metadata = wp_get_attachment_metadata( $id );
			$created[] = array(
				'attachment_id' => $id,
				'filename'      => basename( (string) get_attached_file( $id ) ),
				'mime_type'     => get_post_mime_type( $id ),
				'url'           => wp_get_attachment_url( $id ),
				'width'         => is_array( $metadata ) && isset( $metadata['width'] ) ? (int) $metadata['width'] : null,
				'height'        => is_array( $metadata ) && isset( $metadata['height'] ) ? (int) $metadata['height'] : null,
			);
		}
		return $created;
	}

	private static function normalize_files( $files ) {
		if ( ! is_array( $files ) || ! isset( $files['name'] ) ) {
			return array();
		}
		if ( ! is_array( $files['name'] ) ) {
			return array( $files );
		}
		$out = array();
		foreach ( $files['name'] as $i => $name ) {
			$out[] = array(
				'name'     => $name,
				'type'     => isset( $files['type'][ $i ] ) ? $files['type'][ $i ] : '',
				'tmp_name' => isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '',
				'error'    => isset( $files['error'][ $i ] ) ? $files['error'][ $i ] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $files['size'][ $i ] ) ? $files['size'][ $i ] : 0,
			);
		}
		return $out;
	}

	private static function get_record( $id ) {
		$id = (string) $id;
		if ( ! preg_match( '/^up_[a-f0-9]{32}$/', $id ) ) {
			return new WP_Error( 'invalid_upload_id', 'Invalid upload ID.', array( 'status' => 400 ) );
		}
		$record = get_transient( self::key( $id ) );
		if ( ! is_array( $record ) ) {
			return new WP_Error( 'upload_expired', 'Upload request was not found or has expired.', array( 'status' => 410 ) );
		}
		if ( empty( $record['expires_at'] ) || (int) $record['expires_at'] < time() ) {
			delete_transient( self::key( $id ) );
			return new WP_Error( 'upload_expired', 'Upload request has expired.', array( 'status' => 410 ) );
		}
		return $record;
	}

	private static function safe_record( $record ) {
		return array(
			'upload_id'  => $record['id'],
			'status'     => $record['status'],
			'expires_at' => $record['expires_at'],
			'files'      => isset( $record['files'] ) ? array_values( $record['files'] ) : array(),
		);
	}

	private static function key( $id ) {
		return 'wpvdmcp_upload_' . $id;
	}

	private static function max_file_bytes() {
		$wp = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : self::MAX_BYTES;
		return max( 1, min( self::MAX_BYTES, $wp > 0 ? $wp : self::MAX_BYTES ) );
	}

	private static function base64url( $bytes ) {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	private static function browser_headers() {
		if ( headers_sent() ) {
			return;
		}
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store, max-age=0' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'" );
	}

	private static function render_form( $id, $secret, $record ) {
		$max_mb = round( self::max_file_bytes() / 1048576, 1 );
		$action = admin_url( 'admin-post.php' );
		$title  = $record['title'] ? ' for “' . esc_html( $record['title'] ) . '”' : '';
		echo '<!doctype html><meta charset="utf-8"><title>WPVibe Direct MCP upload</title><style>body{font-family:system-ui,sans-serif;max-width:720px;margin:48px auto;padding:0 20px;line-height:1.5}main{border:1px solid #dcdcde;border-radius:12px;padding:28px}input[type=file]{display:block;margin:20px 0}button{padding:10px 18px;font-weight:600}</style><main>';
		echo '<h1>Upload media' . $title . '</h1><p>This one-time link accepts up to ' . (int) self::MAX_FILES . ' JPEG, PNG, GIF, WebP, or safely sanitized SVG images. Maximum ' . esc_html( $max_mb ) . ' MiB per file.</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $action ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="upload_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="upload_token" value="' . esc_attr( $secret ) . '"><input type="file" name="files[]" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" multiple required><button type="submit">Upload to Media Library</button></form></main>';
	}

	private static function render_page( $title, $message, $success ) {
		$status = $success ? 'Complete' : 'Error';
		echo '<!doctype html><meta charset="utf-8"><title>' . esc_html( $title ) . '</title><style>body{font-family:system-ui,sans-serif;max-width:720px;margin:48px auto;padding:0 20px;line-height:1.5}main{border:1px solid #dcdcde;border-radius:12px;padding:28px}</style><main><h1>' . esc_html( $title ) . '</h1><p><strong>' . esc_html( $status ) . ':</strong> ' . esc_html( $message ) . '</p></main>';
	}

	private static function php_upload_error( $code ) {
		$map = array(
			UPLOAD_ERR_INI_SIZE   => 'The file exceeds the PHP upload_max_filesize limit.',
			UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form upload size limit.',
			UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
			UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
			UPLOAD_ERR_NO_TMP_DIR => 'The server has no PHP temporary upload directory.',
			UPLOAD_ERR_CANT_WRITE => 'The server could not write the temporary upload file to disk.',
			UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : 'The upload failed with PHP error code ' . (int) $code . '.';
	}

	private static function audit( $upload_id, $success, $message, $files ) {
		$log = get_option( 'wpvdmcp_activity', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$summary = array( 'upload_id' => $upload_id, 'attachments' => array() );
		foreach ( (array) $files as $file ) {
			$summary['attachments'][] = array(
				'id'       => isset( $file['attachment_id'] ) ? (int) $file['attachment_id'] : 0,
				'filename' => isset( $file['filename'] ) ? sanitize_file_name( $file['filename'] ) : '',
				'mime'     => isset( $file['mime_type'] ) ? sanitize_text_field( $file['mime_type'] ) : '',
			);
		}
		array_unshift( $log, array( 'time' => time(), 'tool' => 'device_upload', 'success' => (bool) $success, 'message' => self::truncate( $message, 300 ), 'summary' => $summary ) );
		update_option( 'wpvdmcp_activity', array_slice( $log, 0, 100 ), false );
	}

	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, 0, $length ) : substr( (string) $value, 0, $length );
	}
}
