<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
$dos = array('installed', 'prepared', 'install', 'refresh', 'uninstall', 'web', 'batch-install');
$do = in_array($do, $dos) ? $do : 'installed';
load()->model('extension');
if($do == 'installed') {
	$_W['page']['title'] = '已安装的微站风格 - 风格主题 - 扩展';
	$templateids = array();
	$templates = pdo_fetchall("SELECT * FROM ".tablename('site_templates'));
	foreach($templates as $tpl) {
		$templateids[] = $tpl['name'];
	}
	template('extension/theme');
}

if($do == 'prepared') {
	$_W['page']['title'] = '安装微站风格 - 风格主题 - 扩展';
	$templateids = array();
	$templates = pdo_fetchall("SELECT * FROM ".tablename('site_templates'));
	foreach($templates as $tpl) {
		$templateids[] = $tpl['name'];
	}
	$uninstallTemplates = array();
	$path = IA_ROOT . '/app/themes/';
	if (is_dir($path)) {
		if ($handle = opendir($path)) {
			while (false !== ($modulepath = readdir($handle))) {
				$manifest = ext_template_manifest($modulepath);
				if(!empty($manifest) && !in_array($manifest['name'], $templateids)) {
					$uninstallTemplates[$manifest['name']] = $manifest;
					$uninstallTemplates_title[$manifest['name']] = $manifest['title'];
					$templateids[] = $manifest['name'];
				}
			}
		}
	}
	$prepare_templates = json_encode(array_keys($uninstallTemplates));
	$prepare_templates_title = json_encode($uninstallTemplates_title);
	template('extension/theme');
}

if($do == 'batch-install') {
	if($_W['ispost']) {
		$id = $_GPC['templateid'];
		$m = ext_template_manifest($id);
		if (empty($m)) {
			exit('error');
		}
		if (pdo_fetchcolumn("SELECT id FROM ".tablename('site_templates')." WHERE name = '{$m['name']}'")) {
			exit('error');
		}
				unset($m['settings']);
		
		if (pdo_insert('site_templates', $m)) {
			exit('success');
		} else {
			exit('error');
		}
	} else {
		exit('error');
	}
}

if($do == 'install') {
	if(empty($_GPC['flag'])) {
		$id = $_GPC['templateid'];
		$m = ext_template_manifest($id);
		if (empty($m)) {
			message('模板安装配置文件不存在或是格式不正确！', '', 'error');
		}
		if (pdo_fetchcolumn("SELECT id FROM ".tablename('site_templates')." WHERE name = '{$m['name']}'")) {
			message('模板已经安装或是唯一标识已存在！', '', 'error');
		}
				unset($m['settings']);
		
		if (pdo_insert('site_templates', $m)) {
			$tid = pdo_insertid();
			$groups = uni_groups();
			template('extension/select-groups');
			exit();
		} else {
			message('模板安装失败, 请联系模板开发者！');
		}
	} else {
		$tid = intval($_GPC['tid']);
		$post_groups = $_GPC['group'];
		if($tid && $post_groups) {
			if (!pdo_fetchcolumn("SELECT id FROM ".tablename('site_templates')." WHERE id = {$tid}")) {
				message('指定模板不存在！', '', 'error');
			}
			
			foreach($post_groups as $post_group) {
				$item = pdo_fetch("SELECT id,name,templates FROM ".tablename('uni_group') . " WHERE id = :id", array(':id' => intval($post_group)));
				if(empty($item)) {
					continue;
				}
				$item['templates'] = iunserializer($item['templates']);
				if(in_array($tid, $item['templates'])) {
					continue;
				}
				$item['templates'][] = $tid;
				$item['templates'] = iserializer($item['templates']);
				pdo_update('uni_group', $item, array('id' => $post_group));
			}
		}
		
		message('模块安装成功, 请按照【公众号服务套餐】【用户组】来分配权限！', url('extension/theme'), 'success');
	}
}

if($do == 'uninstall') {
	$id = $_GPC['templateid'];
	$m = array();
	$m['name'] = $id;
	if (pdo_delete('site_templates', $m)) {
		message('模板移除成功, 你可以重新安装, 或者直接移除文件来安全删除！', referer(), 'success');
	} else {
		message('模板移除失败, 请联系模板开发者！');
	}
}

if($do == 'refresh') {

}

if($do == 'web') {
	$_W['page']['title'] = '管理后台风格 - 风格主题 - 扩展';
	load()->model('setting');
	if(checksubmit('submit')) {
		$data = array(
			'template' => $_GPC['template'],
		);
		setting_save($data, 'basic');
		message('更新设置成功！', 'refresh');
	}
	$path = IA_ROOT . '/web/themes/';
	if(is_dir($path)) {
		if ($handle = opendir($path)) {
			while (false !== ($templatepath = readdir($handle))) {
				if ($templatepath != '.' && $templatepath != '..') {
					if(is_dir($path.$templatepath)){
						$template[] = $templatepath;
					}
				}
			}
		}
	}
	setting_load();
	template('extension/web');
}