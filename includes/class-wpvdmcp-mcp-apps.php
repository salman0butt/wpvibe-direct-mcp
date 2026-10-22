<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** MCP Apps extension resources for inline approval and media-upload panels. */
final class WPVDMCP_MCP_Apps {
	const EXTENSION_ID = 'io.modelcontextprotocol/ui';
	const MIME_TYPE = 'text/html;profile=mcp-app';
	const APPROVAL_URI = 'ui://wpvibe-direct/approval-panel';
	const UPLOAD_URI = 'ui://wpvibe-direct/upload-panel';

	public static function definitions() {
		// A bearer-authenticated MCP client must never receive a credential that
		// allows it to approve its own write, even through MCP Apps metadata.
		return array();
	}

	public static function handles( $name ) {
		return in_array( $name, array( 'show_approval_panel', 'approval_decide' ), true );
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		if ( 'show_approval_panel' === $name ) {
			$id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
			return WPVDMCP_Approvals::status( $id );
		}
		if ( 'approval_decide' === $name ) {
			return new WP_Error( 'approval_requires_browser', 'Approve or reject this operation in the authenticated WordPress admin page. An MCP bearer token cannot authorize its own writes.', array( 'status' => 403 ) );
		}
		return new WP_Error( 'unknown_mcp_app_tool', 'Unknown MCP App tool.', array( 'status' => 404 ) );
	}

	public static function decorate_tools( $tools ) {
		$tools = array_values( (array) $tools );
		foreach ( $tools as &$tool ) {
			$name = isset( $tool['name'] ) ? $tool['name'] : '';
			if ( 'show_approval_panel' === $name ) {
				$tool['description'] = 'Inspect a pending Direct MCP approval in a read-only MCP App panel. The site administrator must approve or reject using the authenticated WordPress admin URL.';
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
			array( 'uri' => self::APPROVAL_URI, 'name' => 'WPVibe Direct Approval Review', 'description' => 'Read-only summary and authenticated WordPress admin approval link.', 'mimeType' => self::MIME_TYPE ),
			array( 'uri' => self::UPLOAD_URI, 'name' => 'WPVibe Direct Upload Panel', 'description' => 'Inline drag/drop image upload UI for a Direct MCP upload ticket.', 'mimeType' => self::MIME_TYPE ),
		);
	}

	public static function read_resource( $uri ) {
		if ( self::APPROVAL_URI === $uri ) {
			return array( 'contents' => array( array(
				'uri' => self::APPROVAL_URI,
				'mimeType' => self::MIME_TYPE,
				'text' => self::approval_html(),
				'_meta' => array( 'ui' => array( 'csp' => (object) array() ) ),
			) ) );
		}
		if ( self::UPLOAD_URI === $uri ) {
			// An empty PHP array encodes as JSON [], but the MCP Apps CSP
			// contract requires a dictionary/object even with no domains.
			$csp = (object) array();
			$origin = self::site_origin();
			if ( $origin ) { $csp->connectDomains = array( $origin ); }
			$ui = array( 'csp' => $csp );
			return array( 'contents' => array( array(
				'uri' => self::UPLOAD_URI,
				'mimeType' => self::MIME_TYPE,
				'text' => self::upload_html(),
				'_meta' => array( 'ui' => $ui ),
			) ) );
		}
		return new WP_Error( 'resource_not_found', 'Unknown MCP App resource.', array( 'status' => 404, 'uri' => $uri ) );
	}



	private static function site_origin() {
		$url = function_exists( 'home_url' ) ? home_url( '/' ) : admin_url( '' );
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) { return ''; }
		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) { $origin .= ':' . (int) $parts['port']; }
		return $origin;
	}

	/**
	 * Small self-contained MCP Apps bridge for the two inline Direct panels.
	 *
	 * The panels only need the Apps initialize handshake, tool-result delivery,
	 * server tool calls, ping/teardown replies, and resize notifications. Keeping
	 * that bounded subset here avoids executing third-party CDN JavaScript inside
	 * the approval boundary while preserving the postMessage protocol used by the
	 * official MCP Apps client.
	 */
	private static function app_client_js() {
		return <<<'JS'
function createDirectMcpApp(appInfo){
  let requestId=0;
  let connected=false;
  let resizeObserver=null;
  let resizeScheduled=false;
  let lastWidth=0;
  let lastHeight=0;
  const requestTimeoutMs=60000;
  const pending=new Map();
  const app={
    ontoolresult:null,
    callServerTool(params){return request('tools/call',params);},
    async connect(){
      if(connected)return;
      connected=true;
      window.addEventListener('message',onMessage);
      try{
        await request('ui/initialize',{appCapabilities:{},appInfo,protocolVersion:'2026-01-26'});
        notify('ui/notifications/initialized',{});
        startResizeNotifications();
      }catch(error){
        close();
        throw error;
      }
    }
  };
  function send(message){window.parent.postMessage(message,'*');}
  function notify(method,params){send({jsonrpc:'2.0',method,params:params||{}});}
  function request(method,params){
    const id='wpvdmcp-app-'+(++requestId);
    return new Promise((resolve,reject)=>{
      const timer=setTimeout(()=>{
        const waiter=pending.get(id);
        if(!waiter)return;
        pending.delete(id);
        waiter.reject(new Error('MCP App request timed out.'));
      },requestTimeoutMs);
      pending.set(id,{resolve,reject,timer});
      send({jsonrpc:'2.0',id,method,params:params||{}});
    });
  }
  function reply(id,result){send({jsonrpc:'2.0',id,result:result||{}});}
  function onMessage(event){
    if(event.source!==window.parent)return;
    const message=event.data;
    if(!message||typeof message!=='object'||message.jsonrpc!=='2.0')return;
    if(Object.prototype.hasOwnProperty.call(message,'id')&&!message.method&&pending.has(message.id)){
      const waiter=pending.get(message.id);
      pending.delete(message.id);
      clearTimeout(waiter.timer);
      if(message.error){
        const text=message.error&&message.error.message?message.error.message:'MCP App request failed.';
        waiter.reject(new Error(text));
      }else{
        waiter.resolve(message.result);
      }
      return;
    }
    if(message.method==='ui/notifications/tool-result'){
      if(typeof app.ontoolresult==='function')app.ontoolresult(message.params||{});
      return;
    }
    if(Object.prototype.hasOwnProperty.call(message,'id')&&message.method==='ping'){
      reply(message.id,{});
      return;
    }
    if(Object.prototype.hasOwnProperty.call(message,'id')&&message.method==='ui/resource-teardown'){
      reply(message.id,{});
      close();
    }
  }
  function reportSize(){
    resizeScheduled=false;
    const root=document.documentElement;
    if(!root)return;
    const previousWidth=root.style.width;
    const previousHeight=root.style.height;
    root.style.width='fit-content';
    root.style.height='fit-content';
    const rect=root.getBoundingClientRect();
    root.style.width=previousWidth;
    root.style.height=previousHeight;
    const scrollbarWidth=Math.max(0,(window.innerWidth||0)-(root.clientWidth||0));
    const width=Math.ceil(rect.width+scrollbarWidth);
    const height=Math.ceil(rect.height);
    if(width===lastWidth&&height===lastHeight)return;
    lastWidth=width;
    lastHeight=height;
    notify('ui/notifications/size-changed',{width,height});
  }
  function scheduleSize(){
    if(resizeScheduled)return;
    resizeScheduled=true;
    if(typeof requestAnimationFrame==='function')requestAnimationFrame(reportSize);else setTimeout(reportSize,0);
  }
  function startResizeNotifications(){
    scheduleSize();
    if(typeof ResizeObserver!=='function')return;
    resizeObserver=new ResizeObserver(scheduleSize);
    resizeObserver.observe(document.documentElement);
    if(document.body)resizeObserver.observe(document.body);
  }
  function close(){
    window.removeEventListener('message',onMessage);
    if(resizeObserver){resizeObserver.disconnect();resizeObserver=null;}
    for(const waiter of pending.values()){
      clearTimeout(waiter.timer);
      waiter.reject(new Error('MCP App connection closed.'));
    }
    pending.clear();
    connected=false;
  }
  return app;
}
JS;
	}

	private static function approval_html() {
		$html = <<<'HTML'
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WPVibe approval review</title><style>body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;color:CanvasText;background:Canvas}main{border:1px solid color-mix(in srgb,CanvasText 18%,transparent);border-radius:12px;padding:16px}pre{white-space:pre-wrap;overflow:auto;background:color-mix(in srgb,CanvasText 6%,Canvas);padding:10px;border-radius:8px}a{display:inline-block;padding:9px 14px;border:1px solid currentColor;border-radius:8px;color:inherit}#url{overflow-wrap:anywhere}</style></head><body><main><h2>Review pending operation</h2><p id="summary">Waiting for approval details…</p><pre id="payload"></pre><p id="status" aria-live="polite"></p><p>For security, this MCP App cannot approve its own requested operations. Sign in to WordPress admin to review and decide.</p><p><a id="admin-link" target="_blank" rel="noopener noreferrer" hidden>Review in WordPress admin</a></p><p id="url"></p></main><script>/*__WPVDMCP_APP_CLIENT__*/const app=createDirectMcpApp({name:'WPVibe Direct Approval Review',version:'1.0.0'});const $=id=>document.getElementById(id);app.ontoolresult=result=>{const record=result.structuredContent||{};$('summary').textContent=(record.operation?record.operation+': ':'')+(record.summary||'Review this operation before it runs.');$('payload').textContent=JSON.stringify(record.display_payload||{},null,2);$('status').textContent='Status: '+(record.status||'unknown');const url=record.approval_url||'';const valid=typeof url==='string'&&/^https?:\/\//i.test(url);const link=$('admin-link');link.hidden=!valid;if(valid){link.href=url;$('url').textContent=url;}else{$('url').textContent='Approval URL unavailable.';}};app.connect().catch(()=>{$('status').textContent='Inline review unavailable. Open the WordPress admin approval link supplied by the tool.';});</script></body></html>
HTML;
		return str_replace( '/*__WPVDMCP_APP_CLIENT__*/', self::app_client_js(), $html );
	}

	private static function upload_html() {
		$html = <<<'HTML'
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WPVibe upload</title><style>body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;color:CanvasText;background:Canvas}main{border:1px solid color-mix(in srgb,CanvasText 18%,transparent);border-radius:12px;padding:16px}.drop{display:block;border:2px dashed color-mix(in srgb,CanvasText 28%,transparent);border-radius:10px;padding:22px;text-align:center}button{margin-top:12px;padding:9px 14px;border-radius:8px;border:1px solid currentColor;background:Canvas;font-weight:700}#status{margin-top:10px}</style></head><body><main><h2>Upload to WordPress</h2><label class="drop">Drop images here or choose files<input id="files" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" multiple></label><button id="upload" disabled>Upload to Media Library</button><p id="status" aria-live="polite">Waiting for an upload ticket…</p></main><script>/*__WPVDMCP_APP_CLIENT__*//*__WPVDMCP_UPLOAD_JS__*/</script></body></html>
HTML;
		$html = str_replace( '/*__WPVDMCP_APP_CLIENT__*/', self::app_client_js(), $html );
		return str_replace( '/*__WPVDMCP_UPLOAD_JS__*/', self::upload_js(), $html );
	}

	/** An in-flight multipart POST must retain its target frame until WordPress confirms completion. */
	private static function upload_js() {
		return <<<'JS'
const app=createDirectMcpApp({name:'WPVibe Direct Upload',version:'1.0.0'});
let ticket=null;
let activeTransfer=false;
const files=document.getElementById('files');
const button=document.getElementById('upload');
const status=document.getElementById('status');
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
app.ontoolresult=result=>{
  ticket=result.structuredContent||{};
  status.textContent=ticket.upload_id?'Choose images to upload.':'Upload ticket unavailable.';
  button.disabled=activeTransfer||!ticket.upload_id;
};
files.onchange=()=>{button.disabled=activeTransfer||!(ticket?.upload_id&&files.files.length);};
button.onclick=async()=>{
  if(activeTransfer||!ticket?.upload_url||!files.files.length)return;
  const transferTicket=ticket;
  activeTransfer=true;
  button.disabled=true;
  status.textContent='Uploading…';
  let form=null,frame=null,submitted=false,terminal=false;
  try{
    form=document.createElement('form');
    form.method='POST';
    form.enctype='multipart/form-data';
    form.action=transferTicket.upload_url;
    form.target='wpvdmcp-upload-target';
    form.style.display='none';
    for(const file of files.files){
      const dt=new DataTransfer();
      dt.items.add(file);
      const input=document.createElement('input');
      input.type='file';
      input.name='files[]';
      input.files=dt.files;
      form.appendChild(input);
    }
    frame=document.createElement('iframe');
    frame.name='wpvdmcp-upload-target';
    frame.style.display='none';
    document.body.append(frame,form);
    form.submit();
    submitted=true;
    // HTTP uploads can take far longer than a fixed timer. Never tear down the
    // target frame while WordPress reports waiting/processing or is unreachable.
    for(let attempt=0;attempt<120;attempt++){
      await pause(2000);
      let data={};
      try{
        const check=await app.callServerTool({name:'check_upload',arguments:{upload_id:transferTicket.upload_id}});
        if(check?.isError){
          status.textContent='Upload status unavailable. Keep this panel open or use check_upload in the MCP client.';
          continue;
        }
        data=check?.structuredContent||{};
      }catch(error){
        status.textContent='Upload status temporarily unavailable. Keep this panel open.';
        continue;
      }
      if(data.status==='ready'){
        terminal=true;
        status.textContent='Uploaded to the WordPress Media Library.';
        break;
      }
      if(data.status==='failed'){
        terminal=true;
        const orphaned=Array.isArray(data.orphaned_attachment_ids)?data.orphaned_attachment_ids:[];
        status.textContent=orphaned.length?'Upload failed; Media Library attachment IDs '+orphaned.join(', ')+' require manual cleanup before retrying.':'Upload failed. Inspect the browser upload response or WordPress Media Library before retrying.';
        break;
      }
      if(data.status==='processing'){
        status.textContent='Upload is still processing. Keep this panel open.';
      }else if(data.status==='waiting'){
        status.textContent='Transfer is pending. Keep this panel open.';
      }else{
        status.textContent='Upload state is not confirmed. Keep this panel open and check with the MCP client.';
      }
    }
    if(!terminal){status.textContent='Upload may still be running. Keep this panel open; do not resubmit. Check the upload status in your MCP client.';}
  }catch(error){
    status.textContent='Inline upload could not be confirmed. Keep this panel open if transfer started; otherwise use the browser upload link fallback.';
  }finally{
    if(terminal){
      form.remove();
      frame.remove();
      activeTransfer=false;
      if(ticket===transferTicket)ticket=null; // A consumed/failed one-time ticket must never be resubmitted.
      button.disabled=!(ticket?.upload_id&&files.files.length);
    }else if(!submitted){
      if(form)form.remove();
      if(frame)frame.remove();
      activeTransfer=false;
      button.disabled=!(ticket?.upload_id&&files.files.length);
    }else{
      // A network timeout is not proof the WordPress multipart POST finished.
      // Preserve the iframe/form and disable resubmission to avoid interruption.
      button.disabled=true;
    }
  }
};
app.connect().catch(()=>{status.textContent='Inline upload unavailable. Open the browser upload link as a fallback.';});
JS;
	}
}
