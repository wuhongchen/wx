<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
load()->model('app');
$dos = array('display', 'coupons', 'tokens', 'credits', 'address', 'card', 'mycard');
$do = in_array($do, $dos) ? $do : 'display';
load()->func('tpl');
load()->model('user');



$psize = 10;
$pindex = max(1, intval($_GPC['page']));
$params = array(':uid'=>$_W['member']['uid']);
$filter['used'] = '1';
if ($_GPC['type'] == 'used') {
	$filter['used'] = '2';
}


if ($do == 'coupons') {
	$coupon = activity_coupon_owned($_W['member']['uid'], $filter, $pindex, $psize);
	foreach ($coupon['data'] as &$value){
		$value['thumb'] = tomedia($value['thumb']);
		$value['description'] = htmlspecialchars_decode($value['description']);
		$data[$value['couponid']] = $value;
	}
	$pager = pagination(count($coupon['rows']), $pindex, $psize);
	if ($_GPC['op'] == 'use') {
		if (checksubmit('submit')) {
			
			$password = user_hash($_GPC['password'], '');
			$sql = 'SELECT * FROM ' . tablename('activity_coupon_password') . " WHERE `uniacid` = :uniacid AND `password` = :password";
			$clerk = pdo_fetch($sql, array(':uniacid' => $_W['uniacid'], ':password' => $password));
			if (!empty($clerk)) {
				
				$couponid = intval($_GPC['couponid']);
				if (activity_coupon_use($_W['member']['uid'], $couponid)) {
					message('折扣券使用成功！', url('mc/bond/coupons'), 'success');
				}
				message('折扣券使用失败！', url('mc/bond/coupons'), 'error');
			}
			message('密码错误！', referer(), 'error');
		}
		$data = $data[$_GPC['couponid']];
		template('mc/use');
		exit();
	}
}


if ($do == 'tokens') {
	$token = activity_token_owned($_W['member']['uid'], $filter, $pindex, $psize);
	foreach ($token['data'] as $value){
		$data[$value['couponid']] = $value;
	}
	$pager = pagination(count($token['rows']), $pindex, $psize);
	if ($_GPC['op'] == 'use') {
		if (checksubmit('submit')) {
			
			$password = user_hash($_GPC['password'], '');
			$sql = 'SELECT * FROM ' . tablename('activity_coupon_password') . " WHERE `uniacid` = :uniacid AND `password` = :password";
			$clerk = pdo_fetch($sql, array(':uniacid' => $_W['uniacid'], ':password' => $password));
			if (!empty($clerk)) {
				
				$couponid = intval($_GPC['couponid']);
				if (activity_token_use($_W['member']['uid'], $couponid)) {
					message('代金券使用成功！', url('mc/bond/tokens'), 'success');
				}
				message('代金券使用失败！', url('mc/bond/tokens'), 'error');
			}
			message('密码错误！', referer(), 'error');
		}
		$data = $data[$_GPC['couponid']];
		template('mc/use');
		exit();
	}
}


if ($do == 'credits') {
	$where = '';
	
	if (empty($starttime) || empty($endtime)) {
		$starttime =  strtotime('-1 month');
		$endtime = time();
	}
	if ($_GPC['time']) {
		$starttime = strtotime($_GPC['time']['start']);
		$endtime = strtotime($_GPC['time']['end']) + 86399;
		$where = ' AND `createtime` >= :starttime AND `createtime` < :endtime';
		$params[':starttime'] = $starttime;
		$params[':endtime'] = $endtime;
	}
	if ($_GPC['credittype']) {
		
		if ($_GPC['type'] == 'order') {
			$sql = 'SELECT * FROM ' . tablename('mc_credits_recharge') . " WHERE `uid` = :uid $where LIMIT " . ($pindex - 1) * $psize. ',' . $psize;
			$orders = pdo_fetchall($sql, $params);
			foreach ($orders as &$value) {
				$value['createtime'] = date('Y-m-d', $value['createtime']);
				$value['fee'] = number_format($value['fee']);
				if ($value['status'] == 1) {
					$orderspay += $value['fee'];
				}
				unset($value);
			}
			
			$ordersql = 'SELECT COUNT(*) FROM ' .tablename('mc_credits_recharge') . "WHERE `uid` = :uid {$where}";
			$total = pdo_fetchcolumn($ordersql, $params);
			$orderpager = pagination($total, $pindex, $psize, '', array('before' => 0, 'after' => 0, 'ajaxcallback' => ''));
			template('mc/bond');
			exit();
		}
		$where .= " AND `credittype` = '{$_GPC['credittype']}'";
	}
	
	
	$sql = 'SELECT `num` FROM ' . tablename('mc_credits_record') . " WHERE `uid` = :uid $where";
	$nums = pdo_fetchall($sql, $params);
	$pay = $income = 0;
	foreach ($nums as $value) {
		if ($value['num'] > 0) {
			$income += $value['num'];
		} else {
			$pay += abs($value['num']);
		}
	}
	$pay = number_format($pay);
	$income = number_format($income);
	
	$sql = 'SELECT * FROM ' . tablename('mc_credits_record') . " WHERE `uid` = :uid {$where} ORDER BY `createtime` DESC LIMIT " . ($pindex - 1) * $psize.','. $psize;
	$data = pdo_fetchall($sql, $params);
	foreach ($data as $key=>$value) {
		$data[$key]['credittype'] = $creditnames[$data[$key]['credittype']]['title'];
		$data[$key]['createtime'] = date('Y-m-d', $data[$key]['createtime']);
		$data[$key]['num'] = number_format($value['num']);
	}
	
	$sql = 'SELECT `realname`, `avatar` FROM ' . tablename('mc_members') . " WHERE `uid` = :uid";
	$user = pdo_fetch($sql, array(':uid' => $_W['member']['uid']));
	
	$pagesql = 'SELECT COUNT(*) FROM ' .tablename('mc_credits_record') . "WHERE `uid` = :uid {$where}";
	$total = pdo_fetchcolumn($pagesql, $params);
	$pager = pagination($total, $pindex, $psize, '', array('before' => 0, 'after' => 0, 'ajaxcallback' => ''));
}


if ($do == 'address') {
	if (checksubmit('submit')) {
		
		$data = $_GPC['data'];
		$data['resideprovince'] = $_GPC['dis']['province'];
		$data['residecity'] = $_GPC['dis']['city'];
		$data['residedist'] = $_GPC['dis']['district'];
		pdo_update('mc_members', $data, array('uid'=>$_SESSION['uid']));
		message('修改收货地址成功', url('mc/bond/address'), 'success');
	}
	$sql = 'SELECT * FROM ' . tablename('mc_members') . " WHERE `uid` = :uid";
	$data = pdo_fetch($sql, $params);
	$reside['province'] = $data['resideprovince'];
	$reside['city'] = $data['residecity'];
	$reside['district'] = $data['residedist'];
}


if ($do == 'card') {
	
	$sql = 'SELECT * FROM ' . tablename('mc_card') . "WHERE `uniacid` = :uniacid AND `status` = '1'";
	$setting = pdo_fetch($sql, array(':uniacid' => $_W['uniacid']));
	if (!empty($setting)) {
		$setting['color'] = iunserializer($setting['color']);
		$setting['background'] = iunserializer($setting['background']);
		$setting['fields'] = iunserializer($setting['fields']);
	}
	if (checksubmit('submit')) {
		
		$sql = 'SELECT COUNT(*) AS `count` FROM ' . tablename('mc_card_members') . " WHERE `uid` = :uid AND `cid` = :cid";
		$count = pdo_fetchall($sql, array(':uid' => $_W['member']['uid'], ':cid' => $_GPC['cardid']));
		if ($count[0]['count'] >= 1) {
			message('对不起，您已经领取过该会员卡.', referer(), 'error');
		}
		
 		$cardsn = $_GPC['format'];
		preg_match_all('/(\*+)/', $_GPC['format'], $matchs);
		if (!empty($matchs)) {
			foreach ($matchs[1] as $row) {
				$cardsn = str_replace($row, random(strlen($row), 1), $cardsn);
			}
		}
		preg_match('/(\#+)/', $_GPC['format'], $matchs);
		$length = strlen($matchs[1]);
		$pos = strpos($_GPC['format'], '#');
		$cardsn = str_replace($matchs[1], str_pad($_GPC['snpos']++, $length - strlen($number), '0', STR_PAD_LEFT), $cardsn);
		
		pdo_update('mc_card', array('snpos' => $_GPC['snpos']), array('uniacid' => $_W['uniacid'], 'id' => $_GPC['cardid']));
		
		
		$data = array(
				'uniacid' => $_W['uniacid'],
				'uid' => $_W['member']['uid'],
				'cid' => $_GPC['cardid'],
				'cardsn' => $cardsn,
				'status' => '1',
				'createtime' => TIMESTAMP
		);
		if (pdo_insert('mc_card_members', $data)) {
			
			$data = array();
			if (!empty($setting['fields'])) {
				foreach ($setting['fields'] as $row) {
					if (!empty($row['require']) && empty($_GPC[$row['bind']])) {
						message('请输入'.$row['title'].'！');
					}
					$data[$row['bind']] = $_GPC[$row['bind']];
				}
			}
			mc_update($_W['member']['uid'], $data);
			message('领取会员卡成功.', url('mc/bond/mycard'), 'success');
		} else {
			message('领取会员卡失败.', referer(), 'error');
		}
	}
}


if ($do == 'mycard') {
	$sql = "SELECT `cid`, `cardsn`, `createtime`,c.* FROM " . tablename('mc_card_members') . ' AS m JOIN ' . tablename('mc_card') . " AS c 
					ON m.cid = c.id WHERE `uid` = :uid";
	$list = pdo_fetchall($sql, array(':uid' => $_W['member']['uid']));
	foreach ($list as &$value) {
		$value['color'] = iunserializer($value['color']);
		$value['background'] = iunserializer($value['background']);
		$value['fields'] = iunserializer($value['fields']);
		$value['business'] = iunserializer($value['business']);
	}
	$row = $list[0];
}

if ($do == 'display') {
	
}

template('mc/bond');