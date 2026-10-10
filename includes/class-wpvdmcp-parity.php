<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-wpvdmcp-mcp-apps.php';
require_once __DIR__ . '/class-wpvdmcp-reference-manifest.php';
require_once __DIR__ . '/class-wpvdmcp-reference-parity.php';
require_once __DIR__ . '/class-wpvdmcp-runtime-parity.php';
require_once __DIR__ . '/class-wpvdmcp-worker-bridge.php';
final class WPVDMCP_Parity {
	public static function bootstrap(){WPVDMCP_Parity_Fields::bootstrap();}
	public static function definitions(){return array_merge(WPVDMCP_Parity_Fields::definitions(),WPVDMCP_Parity_Skills::definitions(),WPVDMCP_Parity_Insights::definitions(),WPVDMCP_Reference_Parity::definitions(),WPVDMCP_Runtime_Parity::definitions(),WPVDMCP_Worker_Bridge::definitions(),WPVDMCP_MCP_Apps::definitions());}
	public static function handles($name){return WPVDMCP_Worker_Bridge::handles($name)||WPVDMCP_Runtime_Parity::handles($name)||WPVDMCP_MCP_Apps::handles($name)||WPVDMCP_Parity_Fields::handles($name)||WPVDMCP_Parity_Skills::handles($name)||WPVDMCP_Parity_Insights::handles($name)||WPVDMCP_Reference_Parity::handles($name);}
	public static function merge_definitions($base){
		$ordered=array();$by_name=array();
		foreach(array_merge((array)$base,self::definitions()) as$d){
			$name=isset($d['name'])?(string)$d['name']:'';
			if(''===$name)continue;
			if(!array_key_exists($name,$by_name))$ordered[]=$name;
			$by_name[$name]=$d;
		}
		$out=array();foreach($ordered as$name)$out[]=$by_name[$name];
		return WPVDMCP_MCP_Apps::decorate_tools($out);
	}
	public static function execute($name,$args){if(WPVDMCP_Worker_Bridge::handles($name))return WPVDMCP_Worker_Bridge::execute($name,$args);if(WPVDMCP_Runtime_Parity::handles($name))return WPVDMCP_Runtime_Parity::execute($name,$args);if(WPVDMCP_MCP_Apps::handles($name))return WPVDMCP_MCP_Apps::execute($name,$args);if(WPVDMCP_Parity_Fields::handles($name))return WPVDMCP_Parity_Fields::execute($name,$args);if(WPVDMCP_Parity_Skills::handles($name))return WPVDMCP_Parity_Skills::execute($name,$args);if(WPVDMCP_Parity_Insights::handles($name))return WPVDMCP_Parity_Insights::execute($name,$args);if(WPVDMCP_Reference_Parity::handles($name))return WPVDMCP_Reference_Parity::execute($name,$args);return new WP_Error('unknown_parity_tool','Unknown parity tool.',array('status'=>404));}
	public static function skill_instructions(){return WPVDMCP_Parity_Skills::instructions();}
}
