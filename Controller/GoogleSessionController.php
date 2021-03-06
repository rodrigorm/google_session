<?php
App::uses('HttpSocket', 'Network/Http');
App::uses('Xml', 'Utility');

class GoogleSessionController extends GoogleSessionAppController {
	var $uses = null;

	function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('admin_add');
		$this->Auth->allow('admin_callback');
	}

	function admin_add() {
		$Http = new HttpSocket();

		$response = $Http->get('https://www.google.com/accounts/o8/site-xrds?hd=' . $this->__getDomain());

		$response = Set::reverse(Xml::build($response->body));
		$endpoint = $response['XRDS']['XRD']['Service'][0]['URI'];

		$query = array(
			'openid.mode' => 'checkid_setup',
			'openid.ns' => 'http://specs.openid.net/auth/2.0',
			'openid.return_to' => Router::url(array(
				'plugin' => 'google_session',
				'controller' => 'google_session',
				'action' => 'callback',
				'admin' => true
			), true),
			'openid.ui.mode' => 'popup',
			'openid.ns.ax' => 'http://openid.net/srv/ax/1.0',
			'openid.ax.mode' => 'fetch_request',
			'openid.ax.required' => 'email,firstname,lastname',
			'openid.ax.type.email' => 'http://schema.openid.net/contact/email',
			'openid.ax.type.firstname' => 'http://axschema.org/namePerson/first',
			'openid.ax.type.lastname' => 'http://axschema.org/namePerson/last',
			'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
			'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
			'hq' => $this->__getDomain()
		);

		$this->redirect($endpoint . (strpos($endpoint, '?') ? '&' : '?') . http_build_query($query));
	}

	function admin_callback() {
		$endpoint = $this->request->query['openid_op_endpoint'];

		$query = array();
		$keys = array(
			'openid.ns',
			'openid.mode',
			'openid.op_endpoint',
			'openid.response_nonce',
			'openid.return_to',
			'openid.assoc_handle',
			'openid.signed',
			'openid.sig',
			'openid.identity',
			'openid.claimed_id',
			'openid.ns.ext1',
			'openid.ext1.mode',
			'openid.ext1.type.firstname',
			'openid.ext1.value.firstname',
			'openid.ext1.type.email',
			'openid.ext1.value.email',
			'openid.ext1.type.lastname',
			'openid.ext1.value.lastname'
		);
		foreach ($keys as $key) {
			$underscoreKey = str_replace('.', '_', $key);
			if (isset($this->params['url'][$underscoreKey])) {
				$query[$key] = $this->params['url'][$underscoreKey];
			}
		}

		$query['openid.mode'] = 'check_authentication';

		$Http = new HttpSocket();

		$response = $Http->get($this->request->query['openid_op_endpoint'] . '?' . http_build_query($query));

		if (strpos($response, 'is_valid:true') === false) {
			return $this->redirect($this->Auth->loginAction);
		}

		$url = $this->params['url'];

		if (isset($url['openid_mode']) && $url['openid_mode'] == 'cancel') {
			return $this->redirect($this->Auth->redirect());
		}

		$user = array(
			'id' => $url['openid_ext1_value_email'],
			'name' => $url['openid_ext1_value_firstname'] . ' ' . $url['openid_ext1_value_lastname'],
			'email' => $url['openid_ext1_value_email']
		);

		$this->__callback($user);

		$this->Session->write(AuthComponent::$sessionKey, $user);

		$this->set('redirect', $this->Auth->redirect());
	}

	public function admin_delete() {
		$this->redirect($this->Auth->logout());
	}

	private function __getDomain() {
		Configure::load('google_session');
		return Configure::read('GoogleSession.domain');
	}

	private function __callback($arg) {
		$callback = '_afterGoogleSessionAdd';
		if (is_callable(array($this, $callback))) {
			call_user_func(array($this, $callback), $arg);
		}
	}
}