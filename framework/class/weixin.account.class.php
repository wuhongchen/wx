<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');


class WeiXinAccount extends WeAccount {
	private $account = null;
	
	
	public $apis = array(); 	
	public function __construct($account) {
		$sql = 'SELECT * FROM ' . tablename('account_wechats') . ' WHERE `acid`=:acid';
		$this->account = pdo_fetch($sql, array(':acid' => $account['acid']));
		if(empty($this->account)) {
			trigger_error('error uniAccount id, can not construct ' . __CLASS__, E_USER_WARNING);
		}
		$this->account['access_token'] = iunserializer($this->account['access_token']);
		$this->apis = array(
			'barcode' => array(
				'post' => 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=%s',
				'display' => 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=%s',
			)
		);
	}
	
	
	public function checkSign() {
		$token = $this->account['token'];
		$signkey = array($token, $_GET['timestamp'], $_GET['nonce']);
		sort($signkey, SORT_STRING);
		$signString = implode($signkey);
		$signString = sha1($signString);
		return $signString == $_GET['signature'];
	}

	public function fetchAccountInfo() {
		return $this->account;
	}
	
	public function queryAvailableMessages() {
		$messages = array('text', 'image', 'voice', 'video', 'location', 'link', 'subscribe', 'unsubscribe');
		
		if(!empty($this->account['key']) && !empty($this->account['secret'])) {
			$level = intval($this->account['level']);
			if($level > 0){
				$messages[] = 'click';
				$messages[] = 'view';
			}
			if ($level > 1) {
				$messages[] = 'qr';
				$messages[] = 'trace';
			}
		}
		return $messages;
	}
	
	public function queryAvailablePackets() {
		$packets = array('text', 'music', 'news');
		if(!empty($this->account['key']) && !empty($this->account['secret'])) {
			if (intval($this->account['level']) > 0) {
				$packets[] = 'image';
				$packets[] = 'voice';
				$packets[] = 'video';
			}
		}
		return $packets;
	}

	public function isMenuSupported() {
		return 	!empty($this->account['key']) && 
				!empty($this->account['secret']) && 
				(intval($this->account['level']) > 0);
	}

	private function menuResponseParse($content) {
		if(!is_array($content)) {
			return error(-1, '接口调用失败，请重试！' . (is_string($content) ? "微信公众平台返回元数据: {$content}" : ''));
		}
		$dat = $content['content'];
		$result = @json_decode($dat, true);
		if(is_array($result) && $result['errcode'] == '0') {
			return true;
		} else {
			if(is_array($result)) {
				return error(-1, "微信公众平台返回接口错误. \n错误代码为: {$result['errcode']} \n错误信息为: {$result['errmsg']} \n错误描述为: " . $this->error_code($result['errcode']));
			} else {
				return error(-1, '微信公众平台未知错误');
			}
		}
	}
	
	private function menuBuildMenuSet($menu) {
		$types = array(
			'view', 'click', 'scancode_push', 
			'scancode_waitmsg', 'pic_sysphoto', 'pic_photo_or_album', 
			'pic_weixin', 'location_select'
		);
		$set = array();
		$set['button'] = array();
		foreach($menu as $m) {
			$entry = array();
			$entry['name'] = urlencode($m['title']);
			if(!empty($m['subMenus'])) {
				$entry['sub_button'] = array();
				foreach($m['subMenus'] as $s) {
					$s['forward'] = '测试关键字';
					$e = array();
					if ($s['type'] == 'url') {
						$e['type'] = 'view';
					} elseif (in_array($s['type'], $types)) {
						$e['type'] = $s['type'];
					} else {
						$e['type'] = 'click';
					}
					$e['name'] = urlencode($s['title']);
					if($e['type'] == 'view') {
						$e['url'] = urlencode($s['url']);
					} else {
						$e['key'] = urlencode($s['forward']);
					}
					$entry['sub_button'][] = $e;
				}
			} else {
				$m['forward'] = '测试关键字';
				if ($m['type'] == 'url') {
					$entry['type'] = 'view';
				} elseif (in_array($m['type'], $types)) {
					$entry['type'] = $m['type'];
				} else {
					$entry['type'] = 'click';
				}
				if($entry['type'] == 'view') {
					$entry['url'] = urlencode($m['url']);
				} else {
					$entry['key'] = urlencode($m['forward']);
				}
			}
			$set['button'][] = $entry;
		}
		$dat = json_encode($set);
		$dat = urldecode($dat);
		return $dat;
	}

	public function menuCreate($menu) {
		$dat = $this->menuBuildMenuSet($menu);
		$token = $this->fetch_token();
		$url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$token}";
		$content = ihttp_post($url, $dat);
		return $this->menuResponseParse($content);
	}

	public function menuDelete() {
		$token = $this->fetch_token();
		$url = "https://api.weixin.qq.com/cgi-bin/menu/delete?access_token={$token}";
		$content = ihttp_get($url);
		return $this->menuResponseParse($content);
	}

	public function menuModify($menu) {
		return $this->menuCreate($menu);
	}

	public function menuQuery() {
		$token = $this->fetch_token();
		$url = "https://api.weixin.qq.com/cgi-bin/menu/get?access_token={$token}";
		$content = ihttp_get($url);
		if(!is_array($content)) {
			return error(-1, '接口调用失败，请重试！' . (is_string($content) ? "微信公众平台返回元数据: {$content}" : ''));
		}
		$dat = $content['content'];
		$result = @json_decode($dat, true);
		if(is_array($result) && !empty($result['menu'])) {
			$menus = array();
			foreach($result['menu']['button'] as $val) {
				$m = array();
				$m['type'] = $val['type'] == 'click' ? 'forward' : 'url';
				$m['title'] = $val['name'];
				if($m['type'] == 'forward') {
					$m['forward'] = $val['key'];
				} else {
					$m['url'] = $val['url'];
				}
				$m['subMenus'] = array();
				if(!empty($val['sub_button'])) {
					foreach($val['sub_button'] as $v) {
						$s = array();
						$s['type'] = $v['type'] == 'click' ? 'forward' : 'url';
						$s['title'] = $v['name'];
						if($s['type'] == 'forward') {
							$s['forward'] = $v['key'];
						} else {
							$s['url'] = $v['url'];
						}
						$m['subMenus'][] = $s;
					}
				}
				$menus[] = $m;
			}
			return $menus;
		} else {
			if(is_array($result)) {
				if($result['errcode'] == '46003') {
					return array();
				}
				return error($result['errcode'], "微信公众平台返回接口错误. \n错误代码为: {$result['errcode']} \n错误信息为: {$result['errmsg']} \n错误描述为: " . $this->error_code($result['errcode']));
			} else {
				return error(-1, '微信公众平台未知错误');
			}
		}
	}
	
	public function fansQueryInfo($uniid, $isOpen) {
		if($isOpen) {
			$openid = $uniid;
		} else {
			exit('error');
		}
		$token = $this->fetch_token();
		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$token}&openid={$openid}&lang=zh_CN";
		$response = ihttp_get($url);
		if(is_error($response)) {
			return error(-1, "访问公众平台接口失败, 错误: {$response['message']}");
		}
		$result = @json_decode($response['content'], true);
		if(empty($result)) {
			return error(-1, "接口调用失败, 元数据: {$response['meta']}");
		}
		return $result;
	}
	
	public function fansAll() {
		global $_GPC;
		$token = $this->fetch_token();
		$url = 'https://api.weixin.qq.com/cgi-bin/user/get?access_token=' . $token;
		if(!empty($_GPC['next_openid'])) {
			$url .= '&next_openid=' . $_GPC['next_openid'];
		}
		$response = ihttp_get($url);
		if(is_error($response)) {
			return error(-1, "访问公众平台接口失败, 错误: {$response['message']}");
		}
		$result = @json_decode($response['content'], true);
		if(empty($result)) {
			return error(-1, "接口调用失败, 元数据: {$response['meta']}");
		}
		$return = array();
		$return['total'] = $result['total'];
		$return['fans'] = $result['data']['openid'];
		$return['next'] = $result['next_openid'];
		return $return;
	}

	public function queryBarCodeActions() {
		return array('barCodeCreateDisposable', 'barCodeCreateFixed');
	}

	public function barCodeCreateDisposable($barcode) {
		$barcode['expire_seconds'] = empty($barcode['expire_seconds']) ? 1800 : $barcode['expire_seconds'];
		if (empty($barcode['action_info']['scene']['scene_id']) || empty($barcode['action_name'])) {
			return error('1', 'Invalid params');
		}
		$token = account_weixin_token($this->account);
		$url = sprintf($this->apis['barcode']['post'], $token);
		$response = ihttp_request($url, json_encode($barcode));
		if (is_error($response)) {
			return $response;
		}
		$content = @json_decode($response['content'], true);
		if (empty($content)) {
			return error('1', 'Interface communication failed');
		}
		if (!empty($content['errcode'])) {
			return error($content['errcode'], $content['errmsg']);
		}
		return $content;
	}
	
	public function barCodeCreateFixed($barcode) {
		unset($barcode['expire_seconds']);
		if (empty($barcode['action_info']['scene']['scene_id']) || empty($barcode['action_name'])) {
			return error('1', 'Invalid params');
		}
		$token = account_weixin_token($this->account);
		$url = sprintf($this->apis['barcode']['post'], $token);
		$response = ihttp_request($url, json_encode($barcode));
		if (is_error($response)) {
			return $response;
		}
		$content = @json_decode($response['content'], true);
		if (empty($content)) {
			return error('1', 'Interface communication failed');
		}
		if (!empty($content['errcode'])) {
			return error($content['errcode'], $content['errmsg']);
		}
		return $content;
	}

	private function error_code($code) {
		$errors = array(
			'-1' => '系统繁忙',
			'0' => '请求成功',
			'40001' => '获取access_token时AppSecret错误，或者access_token无效',
			'40002' => '不合法的凭证类型',
			'40003' => '不合法的OpenID',
			'40004' => '不合法的媒体文件类型',
			'40005' => '不合法的文件类型',
			'40006' => '不合法的文件大小',
			'40007' => '不合法的媒体文件id',
			'40008' => '不合法的消息类型',
			'40009' => '不合法的图片文件大小',
			'40010' => '不合法的语音文件大小',
			'40011' => '不合法的视频文件大小',
			'40012' => '不合法的缩略图文件大小',
			'40013' => '不合法的APPID',
			'40014' => '不合法的access_token',
			'40015' => '不合法的菜单类型',
			'40016' => '不合法的按钮个数',
			'40017' => '不合法的按钮个数',
			'40018' => '不合法的按钮名字长度',
			'40019' => '不合法的按钮KEY长度',
			'40020' => '不合法的按钮URL长度',
			'40021' => '不合法的菜单版本号',
			'40022' => '不合法的子菜单级数',
			'40023' => '不合法的子菜单按钮个数',
			'40024' => '不合法的子菜单按钮类型',
			'40025' => '不合法的子菜单按钮名字长度',
			'40026' => '不合法的子菜单按钮KEY长度',
			'40027' => '不合法的子菜单按钮URL长度',
			'40028' => '不合法的自定义菜单使用用户',
			'40029' => '不合法的oauth_code',
			'40030' => '不合法的refresh_token',
			'40031' => '不合法的openid列表',
			'40032' => '不合法的openid列表长度',
			'40033' => '不合法的请求字符，不能包含\uxxxx格式的字符',
			'40035' => '不合法的参数',
			'40038' => '不合法的请求格式',
			'40039' => '不合法的URL长度',
			'40050' => '不合法的分组id',
			'40051' => '分组名字不合法',
			'41001' => '缺少access_token参数',
			'41002' => '缺少appid参数',
			'41003' => '缺少refresh_token参数',
			'41004' => '缺少secret参数',
			'41005' => '缺少多媒体文件数据',
			'41006' => '缺少media_id参数',
			'41007' => '缺少子菜单数据',
			'41008' => '缺少oauth code',
			'41009' => '缺少openid',
			'42001' => 'access_token超时',
			'42002' => 'refresh_token超时',
			'42003' => 'oauth_code超时',
			'43001' => '需要GET请求',
			'43002' => '需要POST请求',
			'43003' => '需要HTTPS请求',
			'43004' => '需要接收者关注',
			'43005' => '需要好友关系',
			'44001' => '多媒体文件为空',
			'44002' => 'POST的数据包为空',
			'44003' => '图文消息内容为空',
			'44004' => '文本消息内容为空',
			'45001' => '多媒体文件大小超过限制',
			'45002' => '消息内容超过限制',
			'45003' => '标题字段超过限制',
			'45004' => '描述字段超过限制',
			'45005' => '链接字段超过限制',
			'45006' => '图片链接字段超过限制',
			'45007' => '语音播放时间超过限制',
			'45008' => '图文消息超过限制',
			'45009' => '接口调用超过限制',
			'45010' => '创建菜单个数超过限制',
			'45015' => '回复时间超过限制',
			'45016' => '系统分组，不允许修改',
			'45017' => '分组名字过长',
			'45018' => '分组数量超过上限',
			'46001' => '不存在媒体数据',
			'46002' => '不存在的菜单版本',
			'46003' => '不存在的菜单数据',
			'46004' => '不存在的用户',
			'47001' => '解析JSON/XML内容错误',
			'48001' => 'api功能未授权',
			'50001' => '用户未授权该api',
		);
		$code = strval($code);
		if($code == '40001') {
			$rec = array();
			$rec['access_token'] = '';
			pdo_update('account_wechats', $rec, array('acid' => $this->account['acid']));
			return '微信公众平台授权异常, 系统已修复这个错误, 请刷新页面重试.';
		}
		if($errors[$code]) {
			return $errors[$code];
		} else {
			return '未知错误';
		}
	}
	
	public function changeSend($send) {
		if (empty($send)) {
			return error(-1, 'Invalid params');
		}
		$token = $this->fetch_token();
		$sendapi = 'https://api.weixin.qq.com/pay/delivernotify?access_token='.$token;
		$response = ihttp_request($sendapi, json_encode($send));
		$response = json_decode($response['content'], true);
		if (empty($response)) {
			return error(-1, '发货失败，请检查您的公众号权限或是公众号AppId和公众号AppSecret！');
		}
		if (!empty($response['errcode'])) {
			return error(-1, $response['errmsg']);
		}
		return true;
	}

	private function fetch_token() {
		load()->func('communication');
		if(is_array($this->account['access_token']) && !empty($this->account['access_token']['token']) && !empty($this->account['access_token']['expire']) && $this->account['access_token']['expire'] > TIMESTAMP) {
			return $this->account['access_token']['token'];
		} else {
			if (empty($this->account['key']) || empty($this->account['secret'])) {
				message('请填写公众号的appid及appsecret, (需要你的号码为微信服务号)！', url('account/bind/post', array('acid' => $this->account['acid'], 'uniacid' => $this->account['uniacid'])), 'error');
			}
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->account['key']}&secret={$this->account['secret']}";
			$content = ihttp_get($url);
			if(is_error($content)) {
				message('获取微信公众号授权失败, 请稍后重试！错误详情: ' . $content['message']);
			}
			$token = @json_decode($content['content'], true);
			if(empty($token) || !is_array($token) || empty($token['access_token']) || empty($token['expires_in'])) {
				message('获取微信公众号授权失败, 请稍后重试！ 公众平台返回原始数据为: <br />' . $content['meta']);
			}
			$record = array();
			$record['token'] = $token['access_token'];
			$record['expire'] = TIMESTAMP + $token['expires_in'];
			$row = array();
			$row['access_token'] = iserializer($record);
			pdo_update('account_wechats', $row, array('acid' => $this->account['acid']));
			return $record['token'];
		}
	}
}
