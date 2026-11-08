<?php
class ControllerStartupStartup extends Controller {
	public function index() {
		// Store
		$this->config->set('config_store_id', 0);

		if ($this->request->server['HTTPS']) {
			$this->config->set('config_url', HTTPS_SERVER);
			$this->config->set('config_ssl', HTTPS_SERVER);
		} else {
			$this->config->set('config_url', HTTP_SERVER);
			$this->config->set('config_ssl', HTTPS_SERVER);
		}

		// Settings
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");

		foreach ($query->rows as $result) {
			if (!$result['serialized']) {
				$this->config->set($result['key'], $result['value']);
			} else {
				$this->config->set($result['key'], json_decode($result['value'], true));
			}
		}

		// Ensure config_url and config_ssl always match the active HTTP_HOST (prevents www vs non-www CORS font blocks)
		if (!empty($this->request->server['HTTP_HOST'])) {
			$is_https = (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off')
				|| (isset($this->request->server['SERVER_PORT']) && (int)$this->request->server['SERVER_PORT'] === 443)
				|| (isset($this->request->server['HTTP_X_FORWARDED_PROTO']) && strtolower($this->request->server['HTTP_X_FORWARDED_PROTO']) === 'https')
				|| (isset($this->request->server['HTTP_X_FORWARDED_SSL']) && strtolower($this->request->server['HTTP_X_FORWARDED_SSL']) === 'on')
				|| (isset($this->request->server['REQUEST_SCHEME']) && strtolower($this->request->server['REQUEST_SCHEME']) === 'https')
				|| (isset($this->request->server['HTTP_CF_VISITOR']) && strpos($this->request->server['HTTP_CF_VISITOR'], 'https') !== false);
			$active_proto = $is_https ? 'https://' : 'http://';
			$active_host = $this->request->server['HTTP_HOST'];
			$active_base = isset($this->request->server['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname($this->request->server['SCRIPT_NAME'])), '/') . '/' : '/';
			$dynamic_url = $active_proto . $active_host . $active_base;

			$this->config->set('config_url', $dynamic_url);
			$this->config->set('config_ssl', $dynamic_url);
		}

		$GLOBALS['__oc_site_config'] = $this->config;

		// Set time zone
		if ($this->config->get('config_timezone')) {
			date_default_timezone_set($this->config->get('config_timezone'));

			$this->db->query("SET time_zone = '" . $this->db->escape(date('P')) . "'");
		}

		// Url
		$this->registry->set('url', new Url($this->config->get('config_url'), $this->config->get('config_ssl')));

		// Language
		$code = '';

		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();

		if (isset($this->session->data['language'])) {
			$code = $this->session->data['language'];
		}

		if (isset($this->request->cookie['language']) && !array_key_exists($code, $languages)) {
			$code = $this->request->cookie['language'];
		}

		if (!empty($this->request->server['HTTP_ACCEPT_LANGUAGE']) && !array_key_exists($code, $languages)) {
			$detect = '';

			$browser_languages = explode(',', $this->request->server['HTTP_ACCEPT_LANGUAGE']);

			foreach ($browser_languages as $browser_language) {
				foreach ($languages as $key => $value) {
					if ($value['status']) {
						$locale = explode(',', $value['locale']);

						if (in_array($browser_language, $locale)) {
							$detect = $key;
							break 2;
						}
					}
				}
			}

			if (!$detect) {
				foreach ($browser_languages as $browser_language) {
					if (array_key_exists(strtolower($browser_language), $languages)) {
						$detect = strtolower($browser_language);
						break;
					}
				}
			}

			$code = $detect ? $detect : '';
		}

		if (!array_key_exists($code, $languages)) {
			$code = $this->config->get('config_language');
		}

		if (!isset($this->session->data['language']) || $this->session->data['language'] != $code) {
			$this->session->data['language'] = $code;
		}

		if (!isset($this->request->cookie['language']) || $this->request->cookie['language'] != $code) {
			setcookie('language', $code, time() + 60 * 60 * 24 * 30, '/', $this->request->server['HTTP_HOST']);
		}

		// Overwrite the default language object
		$language = new Language($code);
		$language->load($code);

		$this->registry->set('language', $language);

		// Set the config language_id
		if (isset($languages[$code])) {
			$this->config->set('config_language_id', $languages[$code]['language_id']);
		} else {
			$this->config->set('config_language_id', 1);
		}

		// Member
		$member = new Cart\Member($this->registry);
		$this->registry->set('member', $member);

		// Enforce initial PIN setup and IP security PIN check for logged-in members
		if ($member->isLogged()) {
			if (empty($this->session->data['member_token'])) {
				$this->session->data['member_token'] = token(32);
			}

			$GLOBALS['__oc_active_member_token'] = $this->session->data['member_token'];
			$this->url->setToken('member_token=' . $this->session->data['member_token']);
			// Keep the submitted token unchanged; outbound links may use the session token.

			$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';
			$exempt_routes = array(
				'account/pin',
				'account/security',
				'account/security/reset',
				'account/security/confirm',
				'account/logout',
				'account/verify',
				'account/login',
				'account/register',
				'account/forgotten',
				'account/reset',
				'account/success'
			);

			$requires_security_check = (strpos($route, 'account/') === 0 || strpos($route, 'checkout/') === 0 || $route === 'marketplace/download' || strpos($route, 'support/seller') === 0);

			if ($requires_security_check && !in_array($route, $exempt_routes)) {
				if (!$member->hasPin()) {
					$this->response->redirect($this->url->link('account/pin', '', true));
				} elseif (!$member->isIpVerified()) {
					$this->response->redirect($this->url->link('account/security', '', true));
				}
			}
		}

		// Currency
		$code = '';

		$this->load->model('localisation/currency');

		$currencies = $this->model_localisation_currency->getCurrencies();

		if (isset($this->session->data['currency'])) {
			$code = $this->session->data['currency'];
		}

		if (isset($this->request->cookie['currency']) && !array_key_exists($code, $currencies)) {
			$code = $this->request->cookie['currency'];
		}

		if (!array_key_exists($code, $currencies)) {
			$code = $this->config->get('config_currency');
		}

		if (!isset($this->session->data['currency']) || $this->session->data['currency'] != $code) {
			$this->session->data['currency'] = $code;
		}

		if (!isset($this->request->cookie['currency']) || $this->request->cookie['currency'] != $code) {
			setcookie('currency', $code, time() + 60 * 60 * 24 * 30, '/', $this->request->server['HTTP_HOST']);
		}

		$this->registry->set('currency', new Cart\Currency($this->registry));

		// Encryption
		$this->registry->set('encryption', new Encryption($this->config->get('config_encryption')));
	}
}
