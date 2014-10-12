<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
$dos = array('display', 'post','del');
$do = in_array($do, $dos) ? $do : 'display';
load()->model('mc');

if($do == 'display') {
	$_W['page']['title'] = '会员列表 - 会员 - 会员中心';
	global $_GPC, $_W;
	$groups = mc_groups();
	$pindex = max(1, intval($_GPC['page']));
	$psize = 50;
	$condition = '';
	$condition .= empty($_GPC['mobile']) ? '' : " AND `mobile` LIKE '%".trim($_GPC['mobile'])."%'";
	$condition .= empty($_GPC['email']) ? '' : " AND `email` LIKE '%".trim($_GPC['email'])."%'";
	$condition .= empty($_GPC['username']) ? '' : " AND `realname` LIKE '%".trim($_GPC['username'])."%'";
	$condition .= intval($_GPC['groupid']) > 0 ?  " AND `groupid` = '".intval($_GPC['groupid'])."'" : '';
	$list = pdo_fetchall("SELECT uid, uniacid, groupid, realname, nickname, email, mobile, createtime  FROM ".tablename('mc_members')." WHERE uniacid = '{$_W['uniacid']}' ".$condition." ORDER BY createtime DESC LIMIT " . ($pindex - 1) * $psize . ',' . $psize);
	$total = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename('mc_members')." WHERE uniacid = '{$_W['uniacid']}' ".$condition);
	$pager = pagination($total, $pindex, $psize);
}

if($do == 'post') {
	$_W['page']['title'] = '编辑会员资料 - 会员 - 会员中心';
	$uid = intval($_GPC['uid']);
	
	if (checksubmit('submit')) {
		$uid = intval($_GPC['uid']);
		if (!empty($_GPC)) {
			if (!empty($_GPC['birth'])) {
				$_GPC['birthyear'] = $_GPC['birth']['year'];
				$_GPC['birthmonth'] = $_GPC['birth']['month'];
				$_GPC['birthday'] = $_GPC['birth']['day'];
			}
			if (!empty($_GPC['reside'])) {
				$_GPC['resideprovince'] = $_GPC['reside']['province'];
				$_GPC['residecity'] = $_GPC['reside']['city'];
				$_GPC['residedist'] = $_GPC['reside']['district'];
			}
			unset($_GPC['uid']);
			mc_update($uid, $_GPC);
		}
		message('更新资料成功！', referer(), 'success');
	}
	
	load()->func('tpl');
	$groups = mc_groups($_W['uniacid']);
	$profile = mc_fetch($uid);
}

if($do == 'del') {
	$_W['page']['title'] = '删除会员资料 - 会员 - 会员中心';
	if(checksubmit('submit')) {
		if(!empty($_GPC['uid'])) {
			$instr = implode(',',$_GPC['uid']);
			pdo_query("DELETE FROM ".tablename('mc_members')." WHERE `uniacid` = {$_W['uniacid']} AND `uid` IN ({$instr})");
			message('删除成功！', referer(), 'success');
		}
		message('请选择要删除的项目！', referer(), 'error');
	}
}

template('mc/member');