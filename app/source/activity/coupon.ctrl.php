<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
$dos = array('display', 'post', 'mine', 'use');
$do = in_array($_GPC['do'], $dos) ? $_GPC['do'] : 'display';
if($do == 'display') {
	$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '. tablename('activity_coupon'). ' WHERE uniacid = :uniacid AND type = :type' , array(':uniacid' => $_W['uniacid'], ':type' => 1));
	$pindex = max(1, intval($_GPC['page']));
	$psize = 10;
	$lists = pdo_fetchall('SELECT couponid,title,thumb,type,credittype,credit,endtime,description FROM ' . tablename('activity_coupon') . ' WHERE uniacid = :uniacid AND type = :type ORDER BY endtime ASC LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $_W['uniacid'], ':type' => 1));
	$pager = pagination($total, $pindex, $psize);
}
if($do == 'post') {
	$id = intval($_GPC['id']); 
	$coupon = activity_coupon_info($id, $_W['uniacid']);
	if(empty($coupon)){
		message('没有指定的礼品兑换.');
	}
	$credit = mc_credit_fetch($_W['member']['uid'], array($coupon['credittype']));
	if ($credit[$coupon['credittype']] < $coupon['credit']) {
		message('您的' . $creditnames[$coupon['credittype']] . '数量不够,无法兑换.');
	}
	
	$ret = activity_coupon_grant($_W['member']['uid'], $id, 'system', '用户使用' . $coupon['credit'] . $creditnames[$coupon['credittype']] . '兑换');
	if(is_error($ret)) {
		message($ret['message']);
	}
		mc_credit_update($_W['member']['uid'], $coupon['credittype'], -1 * $coupon['credit'], array($_W['member']['uid'], '礼品兑换:' . $coupon['title'] . ' 消耗 ' . $creditnames[$coupon['credittype']] . ':' . $coupon['credit']));
	message("兑换成功,您消费了 {$coupon['credit']} {$creditnames[$coupon['credittype']]}", url('activity/coupon/mine'));
}
if($do == 'mine') {
	$psize = 10;
	$pindex = max(1, intval($_GPC['page']));
	$params = array(':uid' => $_W['member']['uid']);
	$filter['used'] = '1';
	$type = 1;
	if($_GPC['type'] == 'used') {
		$filter['used'] = '2';
		$type = 2;
	}
	$total = pdo_fetchall('SELECT COUNT(*) FROM ' . tablename('activity_coupon_record') . ' AS a LEFT JOIN ' . tablename('activity_coupon') . ' AS b ON a.couponid = b.couponid WHERE b.type = 1 AND a.uid = :uid AND a.status = :status GROUP BY a.couponid', array(':uid' => $_W['member']['uid'], ':status' => $type));
	$coupon = activity_coupon_owned($_W['member']['uid'], $filter, $pindex, $psize);
	if(!empty($coupon['data'])) {
		foreach($coupon['data'] as &$value){
			$value['cototal'] = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('activity_coupon_record') . ' WHERE uid = :uid AND couponid = :couponid AND status = :status', array(':uid' => $_W['member']['uid'], ':couponid' => $value['couponid'], ':status' => $type));
			$value['thumb'] = tomedia($value['thumb']);
			$value['description'] = htmlspecialchars_decode($value['description']);
			$data[$value['couponid']] = $value;
		}
	}
	unset($coupon);
	$pager = pagination(count($total), $pindex, $psize);
}
if($do == 'use') {
	$id = intval($_GPC['id']);
	$data = activity_coupon_owned($_W['member']['uid'], array('couponid' => $id));
	$data = $data['data'][0];
	
	if(checksubmit('submit')) {
		load()->model('user');
		$password = user_hash($_GPC['password'], '');
		$sql = 'SELECT * FROM ' . tablename('activity_coupon_password') . " WHERE `uniacid` = :uniacid AND `password` = :password";
		$clerk = pdo_fetch($sql, array(':uniacid' => $_W['uniacid'], ':password' => $password));
		if(!empty($clerk)) {
			$status = activity_coupon_use($_W['member']['uid'], $id);
			if (!is_error($status)) {
				message('折扣券使用成功！', url('activity/coupon/mine', array('type' => 'used')), 'success');
			}
			message('折扣券使用失败！', url('activity/coupon/mine', array('type' => $_GPC['type'])), 'error');
		}
		message('密码错误！', referer(), 'error');
	}
}
template('activity/coupon');
