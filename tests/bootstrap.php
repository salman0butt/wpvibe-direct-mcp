<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ABSPATH', __DIR__ . '/');
define('WPVDMCP_VERSION', '1.1.0-test');
define('WPVDMCP_FILE', dirname(__DIR__) . '/wpvibe-direct-mcp.php');
define('WPVIBE_VERSION', '1.20.3');

$GLOBALS['wp_options'] = array(
    'wpvdmcp_enabled' => '1',
    'wpvdmcp_token_hash' => hash('sha256', 'test-token'),
    'wpvdmcp_user_id' => 1,
);
$GLOBALS['wp_routes'] = array();
$GLOBALS['wp_current_user'] = 0;
$GLOBALS['user_can_manage_options'] = true;
$wp_version = '7.1.3';

class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code = '', $message = '', $data = null) { $this->code=$code; $this->message=$message; $this->data=$data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }

class WP_REST_Response {
    private $data; private $status; private $headers = array();
    public function __construct($data = null, $status = 200) { $this->data=$data; $this->status=$status; }
    public function get_data() { return $this->data; }
    public function set_data($data) { $this->data=$data; }
    public function get_status() { return $this->status; }
    public function set_status($status) { $this->status=(int)$status; }
    public function header($name, $value) { $this->headers[strtolower($name)] = $value; }
    public function get_headers() { return $this->headers; }
}
function rest_ensure_response($data) { return $data instanceof WP_REST_Response ? $data : new WP_REST_Response($data, 200); }

class WP_REST_Request {
    protected $headers = array(); protected $query = array(); protected $json = null; protected $params = array(); protected $body = '';
    public function __construct($method='POST', $route='') { $this->params['_method']=$method; $this->params['_route']=$route; }
    public function set_header($name,$value){ $this->headers[strtolower($name)]=$value; }
    public function get_header($name){ return isset($this->headers[strtolower($name)]) ? $this->headers[strtolower($name)] : ''; }
    public function set_query_params($q){$this->query=$q;}
    public function get_query_params(){return $this->query;}
    public function set_json_params($j){$this->json=$j;}
    public function get_json_params(){return $this->json;}
    public function set_param($k,$v){$this->params[$k]=$v;}
    public function get_param($k){return isset($this->params[$k])?$this->params[$k]:null;}
    public function set_body_params($params){foreach($params as $k=>$v){$this->params[$k]=$v;}}
    public function get_params(){return $this->params;}
    public function get_file_params(){return array();}
}

function add_action(){return true;} function add_filter(){return true;} function register_rest_route(){return true;}
function plugin_basename($p){return basename($p);} function admin_url($p=''){return 'https://example.test/wp-admin/'.$p;}
function esc_url($v){return $v;} function esc_url_raw($v){return $v;} function esc_attr($v){return $v;} function esc_html($v){return $v;}
function sanitize_text_field($v){return is_scalar($v)?trim((string)$v):'';} function sanitize_file_name($v){return preg_replace('/[^A-Za-z0-9._-]/','-',(string)$v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/','',(string)$v));}
function absint($v){return abs((int)$v);} function wp_json_encode($v,$flags=0){return json_encode($v,$flags);} function wp_unslash($v){return $v;}
function rest_url($path=''){return 'https://example.test/wp-json/'.ltrim($path,'/');}
function get_option($k,$default=false){return array_key_exists($k,$GLOBALS['wp_options'])?$GLOBALS['wp_options'][$k]:$default;}
function update_option($k,$v,$autoload=null){$GLOBALS['wp_options'][$k]=$v;return true;} function delete_option($k){unset($GLOBALS['wp_options'][$k]);return true;}
function get_user_by($field,$value){if($field==='id' && (int)$value===1){return (object)array('ID'=>1,'user_login'=>'admin');} return false;}
function user_can($user,$cap){if($cap==='manage_options')return !empty($GLOBALS['user_can_manage_options']); if($cap==='upload_files')return true; return true;} function current_user_can($cap){return true;} function get_current_user_id(){return (int)$GLOBALS['wp_current_user'];} function wp_set_current_user($id){$GLOBALS['wp_current_user']=$id;return true;}
function wp_date($f,$t){return gmdate($f,$t);} function checked(){return '';} function wp_nonce_field(){return '';} function wp_safe_redirect(){return true;} function wp_die($m=''){throw new RuntimeException((string)$m);}
function set_transient($k,$v,$ttl){$GLOBALS['wp_options']['transient:'.$k]=array('value'=>$v,'expires'=>time()+$ttl);return true;}
function get_transient($k){$x=get_option('transient:'.$k,false);if(!$x||$x['expires']<time())return false;return $x['value'];}
function delete_transient($k){return delete_option('transient:'.$k);}
function rest_get_server(){return new class { public function get_routes(){return $GLOBALS['wp_routes'];} public function dispatch($r){ if(isset($GLOBALS['rest_dispatch_callback']) && is_callable($GLOBALS['rest_dispatch_callback'])){ return call_user_func($GLOBALS['rest_dispatch_callback'],$r); } return new WP_REST_Response(array('ok'=>true,'route'=>$r->get_param('_route'),'method'=>$r->get_param('_method'),'params'=>$r->get_params(),'query'=>$r->get_query_params()),200);} };}

require_once dirname(__DIR__) . '/includes/class-wpvdmcp-compatibility.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-approvals.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-upload.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-skills.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-tools.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-server.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-admin.php';

function test_request($payload, $version = '') {
    $r = new WP_REST_Request('POST','/wpvibe-direct/v1/mcp');
    $r->set_header('authorization','Bearer test-token');
    $r->set_header('content-type','application/json');
    if ($version !== '') {
        $r->set_header('mcp-protocol-version',$version);
        if ($version === '2026-07-28' && is_array($payload) && !isset($payload[0])) {
            if (isset($payload['method'])) { $r->set_header('mcp-method',(string)$payload['method']); }
            if (isset($payload['params']['name'])) { $r->set_header('mcp-name',(string)$payload['params']['name']); }
            elseif (isset($payload['params']['uri'])) { $r->set_header('mcp-name',(string)$payload['params']['uri']); }
        }
    }
    $r->set_json_params($payload);
    return $r;
}
function response_data($response) { return $response instanceof WP_REST_Response ? $response->get_data() : $response; }
function assert_true($cond,$message){if(!$cond){throw new RuntimeException($message);}}
function assert_same($expected,$actual,$message){if($expected!==$actual){throw new RuntimeException($message.' expected='.var_export($expected,true).' actual='.var_export($actual,true));}}
if (!function_exists('wp_parse_url')) { function wp_parse_url($url,$component=-1){ return parse_url($url,$component); } }
if (!function_exists('rest_do_request')) { function rest_do_request($request){ return rest_get_server()->dispatch($request); } }
function set_test_routes($routes) {
    $GLOBALS['wp_routes']=array();
    foreach($routes as $route=>$methods){
        $method_map=array(); foreach((array)$methods as $method){$method_map[strtoupper($method)]=true;}
        $GLOBALS['wp_routes'][$route]=array(array('methods'=>$method_map));
    }
}
function tool_by_name($name){foreach(WPVDMCP_Tools::definitions() as $tool){if($tool['name']===$name)return $tool;}return null;}
