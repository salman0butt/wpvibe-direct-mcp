<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** MCP Apps extension resources for inline approval and media-upload panels. */
final class WPVDMCP_MCP_Apps {
	const EXTENSION_ID = 'io.modelcontextprotocol/ui';
	const MIME_TYPE = 'text/html;profile=mcp-app';
	const APPROVAL_URI = 'ui://wpvibe-direct/approval-panel';
	const UPLOAD_URI = 'ui://wpvibe-direct/upload-panel';
	const PANEL_TTL = 900;

	public static function definitions() {
		return array(
			array(
				'name' => 'approval_decide',
				'description' => 'MCP App-only helper for approving or declining one displayed Direct MCP approval. Requires the opaque panel token delivered only in tool result metadata.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => (object) array(
						'approval_id' => array( 'type' => 'string' ),
						'decision' => array( 'type' => 'string', 'enum' => array( 'approve', 'decline' ) ),
						'panel_token' => array( 'type' => 'string' ),
					),
					'required' => array( 'approval_id', 'decision', 'panel_token' ),
					'additionalProperties' => false,
				),
				'_meta' => array( 'ui' => array( 'visibility' => array( 'app' ) ) ),
			),
		);
	}

	public static function handles( $name ) {
		return in_array( $name, array( 'show_approval_panel', 'approval_decide' ), true );
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		if ( 'show_approval_panel' === $name ) {
			$id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
			$status = WPVDMCP_Approvals::status( $id );
			if ( is_wp_error( $status ) ) { return $status; }
			$token = 'ui_' . bin2hex( random_bytes( 24 ) );
			$owner = (int) get_option( 'wpvdmcp_user_id', 0 );
			set_transient( self::panel_key( $id ), array(
				'token_hash' => hash( 'sha256', $token ),
				'approval_id' => $id,
				'owner_user_id' => $owner,
				'expires_at' => time() + self::PANEL_TTL,
			), self::PANEL_TTL );
			$status['__mcp_meta'] = array( 'wpvdmcpApprovalPanelToken' => $token );
			return $status;
		}
		if ( 'approval_decide' === $name ) {
			return self::decide( $args );
		}
		return new WP_Error( 'unknown_mcp_app_tool', 'Unknown MCP App tool.', array( 'status' => 404 ) );
	}

	public static function decorate_tools( $tools ) {
		$tools = array_values( (array) $tools );
		foreach ( $tools as &$tool ) {
			$name = isset( $tool['name'] ) ? $tool['name'] : '';
			if ( 'show_approval_panel' === $name ) {
				$tool['description'] = 'Open or inspect a Direct MCP approval. Supporting clients get an inline MCP App approval panel; all clients retain the secure browser approval URL fallback.';
				$tool['_meta'] = isset( $tool['_meta'] ) && is_array( $tool['_meta'] ) ? $tool['_meta'] : array();
				$tool['_meta']['ui'] = array( 'resourceUri' => self::APPROVAL_URI, 'visibility' => array( 'model', 'app' ) );
				$tool['_meta']['ui/resourceUri'] = self::APPROVAL_URI;
			}
			if ( 'request_upload' === $name ) {
				$tool['description'] = 'Create a 30-minute media-only one-time upload ticket. Supporting clients get an inline MCP App drag/drop panel; all clients retain the secure browser upload URL fallback.';
				$tool['_meta'] = isset( $tool['_meta'] ) && is_array( $tool['_meta'] ) ? $tool['_meta'] : array();
				$tool['_meta']['ui'] = array( 'resourceUri' => self::UPLOAD_URI, 'visibility' => array( 'model', 'app' ) );
				$tool['_meta']['ui/resourceUri'] = self::UPLOAD_URI;
			}
		}
		unset( $tool );
		return $tools;
	}

	public static function resources() {
		return array(
			array( 'uri' => self::APPROVAL_URI, 'name' => 'WPVibe Direct Approval Panel', 'description' => 'Inline approve/decline UI for a pending Direct MCP operation.', 'mimeType' => self::MIME_TYPE ),
			array( 'uri' => self::UPLOAD_URI, 'name' => 'WPVibe Direct Upload Panel', 'description' => 'Inline drag/drop image upload UI for a Direct MCP upload ticket.', 'mimeType' => self::MIME_TYPE ),
		);
	}

	public static function read_resource( $uri ) {
		if ( self::APPROVAL_URI === $uri ) {
			return array( 'contents' => array( array(
				'uri' => self::APPROVAL_URI,
				'mimeType' => self::MIME_TYPE,
				'text' => self::approval_html(),
				'_meta' => array( 'ui' => array( 'csp' => array( 'resourceDomains' => array( 'https://esm.sh' ) ) ) ),
			) ) );
		}
		if ( self::UPLOAD_URI === $uri ) {
			$ui = array( 'csp' => array( 'resourceDomains' => array( 'https://esm.sh' ) ) );
			$origin = self::site_origin();
			if ( $origin ) { $ui['csp']['connectDomains'] = array( $origin ); }
			return array( 'contents' => array( array(
				'uri' => self::UPLOAD_URI,
				'mimeType' => self::MIME_TYPE,
				'text' => self::upload_html(),
				'_meta' => array( 'ui' => $ui ),
			) ) );
		}
		return new WP_Error( 'resource_not_found', 'Unknown MCP App resource.', array( 'status' => 404, 'uri' => $uri ) );
	}

	private static function decide( $args ) {
		$id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
		$decision = isset( $args['decision'] ) ? sanitize_key( $args['decision'] ) : '';
		$token = isset( $args['panel_token'] ) ? (string) $args['panel_token'] : '';
		if ( ! in_array( $decision, array( 'approve', 'decline' ), true ) ) {
			return new WP_Error( 'invalid_decision', 'Decision must be approve or decline.', array( 'status' => 400 ) );
		}
		$record = get_transient( self::panel_key( $id ) );
		if ( ! is_array( $record ) || empty( $record['token_hash'] ) ) {
			return new WP_Error( 'panel_token_invalid', 'The inline approval panel token is missing, invalid, or expired.', array( 'status' => 403 ) );
		}
		if ( empty( $token ) || ! hash_equals( (string) $record['token_hash'], hash( 'sha256', $token ) ) ) {
			return new WP_Error( 'panel_token_invalid', 'The inline approval panel token is missing, invalid, or expired.', array( 'status' => 403 ) );
		}
		if ( (int) $record['owner_user_id'] !== (int) get_option( 'wpvdmcp_user_id', 0 ) ) {
			return new WP_Error( 'panel_owner_changed', 'The Direct MCP token owner changed after the panel was opened.', array( 'status' => 409 ) );
		}
		delete_transient( self::panel_key( $id ) ); // One UI decision per panel token.
		$user_id = (int) $record['owner_user_id'];
		$result = 'approve' === $decision ? WPVDMCP_Approvals::approve( $id, $user_id ) : WPVDMCP_Approvals::reject( $id, $user_id );
		if ( is_wp_error( $result ) ) { return $result; }
		return WPVDMCP_Approvals::status( $id );
	}

	private static function panel_key( $id ) { return 'wpvdmcp_ui_panel_' . hash( 'sha256', (string) $id ); }

	private static function site_origin() {
		$url = function_exists( 'home_url' ) ? home_url( '/' ) : admin_url( '' );
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) { return ''; }
		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) { $origin .= ':' . (int) $parts['port']; }
		return $origin;
	}

	private static function approval_html() {
		return <<<'HTML'
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WPVibe approval</title><style>body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;color:CanvasText;background:Canvas}main{border:1px solid color-mix(in srgb,CanvasText 18%,transparent);border-radius:12px;padding:16px}pre{white-space:pre-wrap;overflow:auto;background:color-mix(in srgb,CanvasText 6%,Canvas);padding:10px;border-radius:8px}.actions{display:flex;gap:8px;margin-top:12px}button{padding:9px 14px;border-radius:8px;border:1px solid currentColor;background:Canvas;cursor:pointer}button.primary{font-weight:700}#status{margin-top:10px}</style></head><body><main><h2>Review destructive action</h2><p id="summary">Waiting for approval details…</p><pre id="payload"></pre><div class="actions"><button class="primary" id="approve">Approve</button><button id="decline">Decline</button></div><p id="status" aria-live="polite"></p></main><script type="module">import{App}from'https://esm.sh/@modelcontextprotocol/ext-apps@1.1.2';const app=new App({name:'WPVibe Direct Approval',version:'1.0.0'});let record=null,token='';const $=id=>document.getElementById(id);function render(){if(!record)return;$('summary').textContent=(record.operation?record.operation+': ':'')+(record.summary||'Review this operation before it runs.');$('payload').textContent=JSON.stringify(record.display_payload||{},null,2);$('status').textContent='Status: '+(record.status||'pending');const disabled=record.status!=='pending'||!token;$('approve').disabled=disabled;$('decline').disabled=disabled;}app.ontoolresult=(result)=>{record=result.structuredContent||{};token=result._meta?.wpvdmcpApprovalPanelToken||'';render();};async function decide(decision){if(!record||!token)return;$('status').textContent='Submitting…';const res=await app.callServerTool({name:'approval_decide',arguments:{approval_id:record.approval_id,decision,panel_token:token}});if(res?.structuredContent)record=res.structuredContent;token='';render();} $('approve').onclick=()=>decide('approve');$('decline').onclick=()=>decide('decline');await app.connect();</script></body></html>
HTML;
	}

	private static function upload_html() {
		return <<<'HTML'
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WPVibe upload</title><style>body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;color:CanvasText;background:Canvas}main{border:1px solid color-mix(in srgb,CanvasText 18%,transparent);border-radius:12px;padding:16px}.drop{display:block;border:2px dashed color-mix(in srgb,CanvasText 28%,transparent);border-radius:10px;padding:22px;text-align:center}button{margin-top:12px;padding:9px 14px;border-radius:8px;border:1px solid currentColor;background:Canvas;font-weight:700}#status{margin-top:10px}</style></head><body><main><h2>Upload to WordPress</h2><label class="drop">Drop images here or choose files<input id="files" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" multiple></label><button id="upload" disabled>Upload to Media Library</button><p id="status" aria-live="polite">Waiting for an upload ticket…</p></main><script type="module">import{App}from'https://esm.sh/@modelcontextprotocol/ext-apps@1.1.2';const app=new App({name:'WPVibe Direct Upload',version:'1.0.0'});let ticket=null;const files=document.getElementById('files'),button=document.getElementById('upload'),status=document.getElementById('status');app.ontoolresult=(result)=>{ticket=result.structuredContent||{};status.textContent=ticket.upload_id?'Choose images to upload.':'Upload ticket unavailable.';button.disabled=!ticket.upload_id;};files.onchange=()=>{button.disabled=!(ticket?.upload_id&&files.files.length);};button.onclick=async()=>{if(!ticket?.upload_url||!files.files.length)return;button.disabled=true;status.textContent='Uploading…';const body=new FormData();for(const file of files.files)body.append('files[]',file,file.name);try{const form=document.createElement('form');form.method='POST';form.enctype='multipart/form-data';form.action=ticket.upload_url;form.target='wpvdmcp-upload-target';form.style.display='none';for(const file of files.files){const dt=new DataTransfer();dt.items.add(file);const input=document.createElement('input');input.type='file';input.name='files[]';input.files=dt.files;form.appendChild(input);}const frame=document.createElement('iframe');frame.name='wpvdmcp-upload-target';frame.style.display='none';document.body.append(frame,form);form.submit();await new Promise(r=>setTimeout(r,1800));const check=await app.callServerTool({name:'check_upload',arguments:{upload_id:ticket.upload_id}});const data=check?.structuredContent||{};status.textContent=data.status==='ready'?'Uploaded to the Media Library.':'Upload is still processing. Use the browser link fallback if needed.';form.remove();frame.remove();}catch(e){status.textContent='Inline upload failed. Open the browser upload link as a fallback.';}button.disabled=false;};await app.connect();</script></body></html>
HTML;
	}
}
