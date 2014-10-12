<?php
function site_article_search($cid, $type = '', $psize = 20, $orderby = 'displayorder DESC, id DESC') {
	global $_GPC, $_W;
	$pindex = max(1, intval($_GPC['page']));
	$result = array();
	$condition = " WHERE uniacid = '{$_W['uniacid']}' AND ";
	if(!empty($cid)) {
		$category = pdo_fetch("SELECT parentid FROM ".tablename('site_category')." WHERE id = '{$cid}'");
		if (!empty($category['parentid'])) {
			$condition .= "ccate = '{$cid}'";
		} else {
			$condition .= "pcate = '{$cid}'";
		}
	}
	if(!empty($cid) && !empty($type)) $condition .= " OR ";
	if (!empty($type)) {
		if ($type == 'f') {
			return site_slide_search(array('limit' => 4));
		}
	}
	$sql = "SELECT * FROM ".tablename('site_article'). $condition. ' ORDER BY '. $orderby;
	$result['list'] = pdo_fetchall($sql . " LIMIT " . ($pindex - 1) * $psize .',' .$psize);
	$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('site_article') . $condition);
	$result['pager'] = pagination($total, $pindex, $psize);
	return $result;
}

function site_article($params = array()) {
	global $_GPC, $_W;
	extract($params);
	$pindex = max(1, intval($_GPC['page']));
	$psize = 20;
	$result = array();

	$condition = " WHERE uniacid = '{$_W['uniacid']}'";
	if (!empty($cid)) {
		$category = pdo_fetch("SELECT parentid FROM ".tablename('site_category')." WHERE id = '{$cid}'");
		if (!empty($category['parentid'])) {
			$condition .= " AND ccate = '{$cid}'";
		} else {
			$condition .= " AND pcate = '{$cid}'";
		}
	}
	if ($iscommend == 'true') {
		$condition .= " AND iscommend = '1'";
	}

	if ($ishot == 'true') {
		$condition .= " AND ishot = '1'";
	}
	$sql = "SELECT * FROM ".tablename('site_article'). $condition. ' ORDER BY displayorder DESC, id DESC';
	$result['list'] = pdo_fetchall($sql . " LIMIT " . ($pindex - 1) * $psize .',' .$psize);
	$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('site_article') . $condition);
	$result['pager'] = pagination($total, $pindex, $psize);
	if (!empty($result['list'])) {
		foreach ($result['list'] as &$row) {
			$row['url'] = murl('site/site/detail', array('id' => $row['id'], 'uniacid' => $_W['uniacid']));
		}
	}
	return $result;
}

function site_category($params = array()) {
	global $_GPC, $_W;
	extract($params);

	if (!isset($parentid)) {
		$condition = "";
	} else {
		$condition = " AND parentid = '$parentid'";
	}
	$category = array();
	$result = pdo_fetchall("SELECT * FROM ".tablename('site_category')." WHERE uniacid = '{$_W['uniacid']}' $condition ORDER BY parentid ASC, displayorder ASC, id ASC ");
	if (!isset($parentid)) {
		if (!empty($result)) {
			foreach ($result as $row) {
				if (empty($row['parentid'])) {
					$category[$row['id']] = $row;
				} else {
					$category[$row['parentid']]['children'][$row['id']] = $row;
				}
			}
		}
	} else {
		$category = $result;
	}
	return $category;
}