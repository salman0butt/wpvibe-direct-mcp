<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class WPVDMCP_Parity {
	public static function bootstrap(){WPVDMCP_Parity_Fields::bootstrap();}
	public static function definitions(){return array_merge(WPVDMCP_Parity_Fields::definitions(),WPVDMCP_Parity_Skills::definitions(),WPVDMCP_Parity_Insights::definitions());}
	public static function handles($name){return WPVDMCP_Parity_Fields::handles($name)||WPVDMCP_Parity_Skills::handles($name)||WPVDMCP_Parity_Insights::handles($name);}
	public static function merge_definitions($base){$overrides=array('list_skills','load_skill');$out=array();foreach((array)$base as$d){if(!in_array($d['name']??'',$overrides,true))$out[]=$d;}return array_merge($out,self::definitions());}
	public static function execute($name,$args){if(WPVDMCP_Parity_Fields::handles($name))return WPVDMCP_Parity_Fields::execute($name,$args);if(WPVDMCP_Parity_Skills::handles($name))return WPVDMCP_Parity_Skills::execute($name,$args);if(WPVDMCP_Parity_Insights::handles($name))return WPVDMCP_Parity_Insights::execute($name,$args);return new WP_Error('unknown_parity_tool','Unknown parity tool.',array('status'=>404));}
	public static function skill_instructions(){return WPVDMCP_Parity_Skills::instructions();}
}
