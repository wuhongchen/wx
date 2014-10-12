<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');

class DefaultModuleProcessor extends WeModuleProcessor {
	public function respond() {
		global $_W, $engine;
		$setting = uni_setting($_W['uniacid'], array('default'));
		if(!empty($setting['default'])) {
			$message = $this->message;
			$message['type'] = 'text';
			$message['content'] = $setting['default'];
			$message['redirection'] = true;
			$message['source'] = 'default';
			$pars = $engine->analyzeText($message);
			if(is_array($pars)) {
				return array('params' => $pars);
			}
		}
	}
}
