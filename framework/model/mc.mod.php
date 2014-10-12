<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */


function mc_update($uid, $fields) {
	global $_W;
	$_W['weid'] && $fields['weid'] = $_W['weid'];
	$struct = cache_load('usersfields');
	if (empty($fields)) {
		return false;
	}
	if (empty($struct)) {
		$struct = cache_build_users_struct();
	}
		for($i = 1; $i < 6; $i++) {
		$key = array_search('credit'.$i, $struct);
		unset($struct[$key]);
	}
	foreach ($fields as $field => $value) {
		if (!in_array($field, $struct)) {
			unset($fields[$field]);
		}
	}
	if(!empty($fields['avatar'])) {
		if(strexists($fields['avatar'], 'global/avatars/avatar_')) {
			$fields['avatar'] = str_replace('attachment/', '', $fields['avatar']);
		}
	}
	$isexists = pdo_fetchcolumn("SELECT uid FROM ".tablename('mc_members')." WHERE uid = :uid", array(':uid' => $uid));
		if(!empty($fields['email'])) {
		$emailexists = pdo_fetchcolumn("SELECT email FROM ".tablename('mc_members')." WHERE uniacid = :uniacid AND email = :email", array(':uniacid' => $_W['uniacid'], ':email' => trim($fields['email'])));
		if($emailexists) {
			unset($fields['email']);
		}
	} 
	if(!empty($fields['mobile'])) {
		$mobilexists = pdo_fetchcolumn("SELECT mobile FROM ".tablename('mc_members')." WHERE uniacid = :uniacid AND mobile = :mobile", array(':uniacid' => $_W['uniacid'], ':mobile' => trim($fields['mobile'])));
		if($mobilexists) {
			unset($fields['mobile']);
		}
	}
	
	if (empty($isexists)) {
		$fields['uid'] = $uid;
		$fields['createtime'] = TIMESTAMP;
		foreach ($struct as $field) {
			if ($field != 'uid' && $field != 'follow' && !isset($fields[$field])) {
				$fields[$field] = '';
			}
		}
		return pdo_insert('mc_members', $fields);
	} else {
		return pdo_update('mc_members', $fields, array('uid' => $uid));
	}
}


function mc_fetch($uid, $fields = array()) {
	global $_W;
	$struct = cache_load('usersfields');
	if (empty($fields)) {
		$select = '*';
	} else {
		foreach ($fields as $field) {
			if (!in_array($field, $struct)) {
				unset($fields[$field]);
			}
		}
		$select = '`uid`, `'.implode('`,`', $fields).'`';
	}
	$result = pdo_fetchall("SELECT $select FROM ".tablename('mc_members')." WHERE uid IN ('".implode("','", is_array($uid) ? $uid : array($uid))."')", array(), 'uid');
	if (!empty($result)) {
		if (is_array($uid)) {
			return $result;
		} else {
			return $result[$uid];
		}
	} else {
		return array();
	}
}

function mc_require($uid, $fields, $pre = '') {
	global $_W, $_GPC;
	if(empty($fields) || !is_array($fields)) {
		return false;
	}
		if(isset($fields['birthyear']) || isset($fields['birthmonth']) || isset($fields['birthday'])) {
		unset($fields['birthyear'], $fields['birthmonth'], $fields['birthday']);
		$fields[] = 'birthyear';
		$fields[] = 'birthmonth';
		$fields[] = 'birthday';
	}
	if(isset($fields['resideprovince']) || isset($fields['residecity']) || isset($fields['residedist'])) {
		unset($fields['residedist'], $fields['resideprovince'], $fields['residecity']);
		$fields[] = 'resideprovince';
		$fields[] = 'residecity';
		$fields[] = 'residedist';
	}
	if(!in_array('uniacid', $fields)) {
		$fields[] = 'uniacid';
	}
	if(!empty($pre)) {
		$pre .= '<br/>';
	}
	$profile = mc_fetch($uid, $fields);
	$uniacid = $profile['uniacid'];
	$titles = mc_fields();
	$message = '';
	$ks = array();
	foreach($profile as $k => $v) {
		if(empty($v)) {
			$ks[] = $k;
			$message .= $titles[$k] . ', ';
		}
	}
	if(!empty($message)) {
		$title = '完善资料';
		if (checksubmit('submit')) {
			if(isset($fields['resideprovince'])) {
				$_GPC['resideprovince'] = $_GPC['reside']['province'];
				$_GPC['residecity'] = $_GPC['reside']['city'];
				$_GPC['residedist'] = $_GPC['reside']['district'];
			}
			if(isset($fields['birthyear'])) {
				$_GPC['birthyear'] = $_GPC['birth']['year'];
				$_GPC['birthmonth'] = $_GPC['birth']['month'];
				$_GPC['birthday'] = $_GPC['birth']['day'];
			}
			$record = array_elements($fields, $_GPC);
			foreach ($record as $field => $value) {
				if (in_array($field, array('uniacid'))) {
					unset($record[$field]);
					continue;
				}
				if(empty($value)) {
					message('请填写完整所有资料.', referer(), 'error');
				}
			}
			mc_update($uid, $record);
			message('资料完善成功.', 'refresh');
		}
		load()->func('tpl');
		
		$filter = array();
		$filter['status'] = 1;
		$coupons = activity_coupon_owned($_W['member']['uid'], $filter);
		$tokens = activity_token_owned($_W['member']['uid'], $filter);
		
		$setting = uni_setting($_W['uniacid'], array('creditnames', 'creditbehaviors', 'uc'));
		$behavior = $setting['creditbehaviors'];
		$creditnames = $setting['creditnames'];
		$credits = mc_credit_fetch($_W['member']['uid'], '*');
		include template('mc/require', TEMPLATE_INCLUDEPATH);
		exit;
	}
	return $profile;
}


function mc_credit_update($uid, $credittype, $creditval = 0, $log = array()) {
	global $_W;
	$credittype = trim($credittype);
	$creditval = floatval($creditval);
	$num = $creditval;
	$value = pdo_fetchcolumn("SELECT {$credittype} FROM ".tablename('mc_members')." WHERE `uid` = {$uid} ");
	if($creditval >= 0) {
		 pdo_update('mc_members',array($credittype => $value + $creditval),array('uid' => $uid));
	} else {
		$creditval = abs($creditval);
		if($value >= $creditval) {
			 pdo_update('mc_members',array($credittype => $value - $creditval),array('uid' => $uid));
		} else {
			return error('-1','用户积分类型' . $credittype . '不够');
		}
	}
		if(empty($log) || !is_array($log)) {
		$log = array($uid, '未记录');
	}
	$data = array(
		'uid' => $uid, 
		'credittype' => $credittype, 
		'uniacid' => $_W['uniacid'],
		'num' => $num,
		'createtime' => time(),
		'operator' => intval($log[0]),
		'remark' => $log[1],
	);
	pdo_insert('mc_credits_record', $data);

	return true;
}


function mc_credit_fetch($uid, $types = array()) {
	if(empty($types) || $types == '*') {
		$select = 'credit1,credit2,credit3,credit4,credit5';
	} else {
		$struct = array('credit1','credit2','credit3','credit4','credit5');
		foreach ($types as $key => $type) {
			if (!in_array($type, $struct)) {
				unset($types[$key]);
			}
		}
		$select = '`'.implode('`,`', $types).'`';
	}
	return pdo_fetch("SELECT {$select} FROM ".tablename('mc_members').' WHERE uid = :uid LIMIT 1',array(':uid' => $uid));
}


function mc_groups($uniacid = 0) {
	global $_W;
	$uniacid = intval($uniacid);
	if(empty($uniacid)) {
		$uniacid = $_W['uniacid'];
	}
	$sql = "SELECT * FROM " . tablename('mc_groups') . ' WHERE `uniacid`=:uniacid ORDER BY `orderlist`';
	$groups = pdo_fetchall($sql, array(':uniacid' => $uniacid), 'groupid');
	return $groups;
}

function _mc_login($user) {
	global $_W;
	if(!empty($user) && !empty($user['uid'])) {
		$sql = 'SELECT `uid`,`mobile`,`email` FROM ' . tablename('mc_members') . ' WHERE `uid`=:uid';
		$user = pdo_fetch($sql, array(':uid' => $user['uid']));
		if(!empty($user) && (!empty($user['mobile']) || !empty($user['email']))) {
			$_SESSION['uid'] = $user['uid'];
			$_W['member'] = $user;
			if(empty($_W['openid'])) {
				$sql = 'SELECT * FROM ' . tablename('mc_mapping_fans') . ' WHERE `uid`=:uid LIMIT 2';
				$mappings = pdo_fetchall($sql, array(':uid' => $user['uid']));
				if(count($mappings) == 1) {
					$mapping = $mappings[0];
					$_W['openid'] = $_SESSION['openid'] = $mapping['openid'];
				}
			} else {
				if(!empty($_W['acid'])) {
					$rec = array();
					$rec['uid'] = $user['uid'];
					$filter = array();
					$filter['uniacid'] = $_W['uniacid'];
					$filter['acid'] = $_W['acid'];
					$filter['openid'] = $_W['openid'];
					pdo_update('mc_mapping_fans', $rec, $filter);
				}
			}
						$_W['fans']['from_user'] = $_W['member']['uid'];
			return true;
		}
	}
	return false;
}


function mc_fields() {
	$result = array();
	$fields = pdo_fetchall("SHOW FULL FIELDS FROM ".tablename('mc_members'));
	foreach ($fields as $field) {
		if (in_array($field['Field'], array('uid', 'uniacid', 'password', 'salt', 'groupid', 'credit1', 'credit2', 'credit3', 'credit4', 'credit5', 'createtime'))) {
			continue;
		}
		$result[$field['Field']] = $field['Comment'];
	}
	return $result;
}

function mc_init_uc() {
	global $_W;
	$setting = uni_setting($_W['uniacid'], array('uc'));
	if(is_array($setting['uc']) && $setting['uc']['status'] == '1') {
		$uc = $setting['uc'];
		define('UC_CONNECT', $uc['connect'] == 'mysql' ? 'mysql' : '');

		define('UC_DBHOST', $uc['dbhost']);
		define('UC_DBUSER', $uc['dbuser']);
		define('UC_DBPW', $uc['dbpw']);
		define('UC_DBNAME', $uc['dbname']);
		define('UC_DBCHARSET', $uc['dbcharset']);
		define('UC_DBTABLEPRE', $uc['dbtablepre']);
		define('UC_DBCONNECT', $uc['dbconnect']);

		define('UC_CHARSET', $uc['charset']);
		define('UC_KEY', $uc['key']);
		define('UC_API', $uc['api']);
		define('UC_APPID', $uc['appid']);
		define('UC_IP', $uc['ip']);

		require IA_ROOT . '/framework/library/uc/client.php';
		return true;
	}
	return false;
}


function mc_handsel($touid, $fromuid, $handsel, $uniacid = '') {
	global $_W;
	$touid = intval($touid);
	$fromuid = intval($fromuid);
	if(empty($uniacid)) {
		$uniacid = $_W['uniacid'];
	}
	$touid_exist = mc_fetch($touid, array('uniacid'));
	if(empty($touid_exist)) {
		return error(-1, '赠送积分用户不存在');
	}
	if($fromuid != -1) {
		$fromuid_exist = mc_fetch($fromuid, array('uniacid'));
		if(empty($fromuid_exist)) {
			return error(-1, '赠送积分来源用户不存在');
		}
	}
	if(empty($handsel['module'])) {
		return error(-1, '没有填写模块名称');
	}
	if(empty($handsel['sign'])) {
		return error(-1, '没有填写赠送积分对象信息');
	}
	if(empty($handsel['action'])) {
		return error(-1, '没有填写赠送积分动作');
	}
	$credit_value = intval($handsel['credit_value']);

	$sql = 'SELECT id FROM ' . tablename('mc_handsel') . ' WHERE uniacid = :uniacid AND touid = :touid AND fromuid = :fromuid AND module = :module AND sign = :sign AND action = :action';
	$parm = array(':uniacid' => $uniacid, ':touid' => $touid, ':fromuid' => $fromuid, ':module' => $handsel['module'], ':sign' => $handsel['sign'], ':action' => $handsel['action']);
	$handsel_exists = pdo_fetch($sql, $parm);
	if(!empty($handsel_exists)) {
		return error(-1, '已经赠送过积分,每个用户只能赠送一次');
	}
	
	$creditbehaviors = pdo_fetchcolumn('SELECT creditbehaviors FROM ' . tablename('uni_settings') . ' WHERE uniacid = :uniacid', array(':uniacid' => $uniacid));
	$creditbehaviors = iunserializer($creditbehaviors) ? iunserializer($creditbehaviors) : array();
	if(empty($creditbehaviors['activity'])) {
		return error(-1, '公众号没有配置积分行为参数');
	} else {
		$credittype = $creditbehaviors['activity'];
	}
	
	$data = array(
		'uniacid' => $uniacid,
		'touid' => $touid,
		'fromuid' => $fromuid,
		'module' => $handsel['module'],
		'sign' => $handsel['sign'],
		'action' => $handsel['action'],
		'credit_value' => $credit_value,
		'createtime' => TIMESTAMP
	);
	pdo_insert('mc_handsel', $data);
	$log = array(
		'uid' => $touid,
		'credittype' => $credittype,
		'uniacid' => $uniacid,
		'num' => $credit_value,
		'createtime' => TIMESTAMP,
		'operator' => 0,
		'remark' => '系统赠送积分'
	);
	mc_credit_update($touid, $credittype, $credit_value, $log);
	return true;
}
