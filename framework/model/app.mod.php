<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');


function app_navs($type = 'home', $multiid = 0) {
	global $_W;
	$pos = array();
	$pos['home'] = 1;
	$pos['profile'] = 2;
	$pos['shortcut'] = 3;
	$navs = pdo_fetchall("SELECT id,name, description, url, icon, css, position, module FROM ".tablename('site_nav')." WHERE position = '{$pos[$type]}' AND status = 1 AND uniacid = '{$_W['uniacid']}' AND multiid = '{$multiid}' ORDER BY displayorder DESC");
	if (!empty($navs)) {
		foreach ($navs as &$row) {
			if (!strexists($row['url'], '://') && !strexists($row['url'], 'i=')) {
				$row['url'] .= strexists($row['url'], '?') ?  '&i='.$_W['uniacid'] : '?i='.$_W['uniacid'];
			}
			$row['css'] = unserialize($row['css']);
			if ($row['position'] == '3') {
				if (!empty($row['css'])) {
					unset($row['css']['icon']['font-size']);
				}
			}
			$row['css']['icon']['style'] = "color:{$row['css']['icon']['color']};font-size:{$row['css']['icon']['font-size']}px;";
			$row['css']['name'] = "color:{$row['css']['name']['color']};";
		}
		unset($row);
	}
	return $navs;
}


function app_slide($params = array()) {
	global $_W;
	if(empty($params['limit'])) {
		$params['limit'] = 4;
	}
	$sql = "SELECT * FROM " . tablename('site_slide') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY id DESC LIMIT " . intval($params['limit']);
	$list = pdo_fetchall($sql);
	if(!empty($list)) {
		foreach($list as &$row) {
			$row['url'] = strexists($row['url'], 'http') ? $row['url'] : $_W['siteroot'] . 'app/' . $row['url'];
			$row['thumb'] = tomedia($row['thumb']);
		}
	}
	return $list;
}
