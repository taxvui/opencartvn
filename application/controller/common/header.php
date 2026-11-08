<?php
class ControllerCommonHeader extends Controller {
	public function index() {
		$data['csrf_policy'] = json_encode(array(
			'post' => $this->config->get('csrf_post_routes') ?: array(),
			'navigation' => $this->config->get('csrf_navigation_routes') ?: array()
		));
		$data['csrf_member_token'] = $this->member->isLogged() ? ($this->session->data['member_token'] ?? '') : '';

		$data['title'] = $this->document->getTitle();

		if ($this->request->server['HTTPS']) {
			$data['base'] = $this->config->get('config_ssl');
		} else {
			$data['base'] = $this->config->get('config_url');
		}

		require_once(DIR_SYSTEM . 'library/payment_gateway.php');
		$payment_gateway = new PaymentGateway($this->registry);

		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';
		$site_name = trim((string)$this->config->get('config_name')) ?: 'OpenCart';

		$description = $this->document->getDescription();
		if (!$description) {
			$config_meta_desc = trim((string)$this->config->get('config_meta_description'));
			$default_descriptions = array(
				'common/home'                => $config_meta_desc ?: ($site_name . ' is an open source PHP-based online e-commerce solution. Everything you need to create, scale and run your online store.'),
				'cms/feature'                => 'Explore ' . $site_name . ' features: powerful store management, extensions, themes, payment gateways, shipping methods, and SEO-ready e-commerce tools.',
				'cms/demo'                   => 'Try the official ' . $site_name . ' live demo. Experience the storefront and administration panel before downloading or launching your store.',
				'marketplace/extension'      => 'Browse over 13,000+ ' . $site_name . ' extensions, modules, themes, payment gateways, shipping methods, and translations in the official ' . $site_name . ' Marketplace.',
				'marketplace/extension/info' => 'Download and install verified ' . $site_name . ' extensions, themes, and modules from the official ' . $site_name . ' Marketplace.',
				'cms/blog'                   => 'Read the latest e-commerce news, tutorials, release notes, and merchant tips on the official ' . $site_name . ' Blog.',
				'cms/blog/info'              => 'Read detailed articles, e-commerce guides, and updates on the official ' . $site_name . ' Blog.',
				'cms/download'               => 'Download the latest version of ' . $site_name . ' free and open-source e-commerce platform, or launch your store instantly on ' . $site_name . ' Cloud.',
				'cms/download/download'      => 'Thank you for downloading ' . $site_name . '. Your download will begin shortly.',
				'cms/download/history'       => 'Browse and download previous ' . $site_name . ' releases and historical versions.',
				'cms/showcase'               => 'Discover successful online stores and brands powered by ' . $site_name . ' around the world.',
				'support/partner'            => 'Find certified ' . $site_name . ' partners, developers, and e-commerce agencies for custom development, design, and technical support.',
				'support/support'            => 'Get professional ' . $site_name . ' support, dedicated technical assistance, and community resources for your online store.',
				'support/seller'             => 'Contact the extension developer or seller on ' . $site_name . ' Marketplace for technical support, questions, or order assistance.',
				'support/contact'            => 'Contact the ' . $site_name . ' team for general inquiries, marketplace assistance, partnership opportunities, and technical support.',
				'cloud/landing'              => 'Launch a fully hosted ' . $site_name . ' Cloud store in minutes with automated updates, cloud hosting, and pre-installed tools.',
				'account/login'              => 'Log in to your ' . $site_name . ' account to manage your marketplace extensions, downloads, subscriptions, and store services.',
				'account/register'           => 'Create a free ' . $site_name . ' account to download extensions, access community forums, and manage your e-commerce projects.'
			);

			if (isset($default_descriptions[$route])) {
				$description = $default_descriptions[$route];
			} else {
				$page_title = trim($data['title']);
				$description = ($page_title ? $page_title . ' - ' : '') . ($config_meta_desc ?: ($site_name . ' open-source e-commerce platform, marketplace extensions, cloud hosting, and developer resources.'));
			}
		}

		$keywords = $this->document->getKeywords();
		if (!$keywords) {
			$config_meta_kw = trim((string)$this->config->get('config_meta_keyword'));
			$keywords = $config_meta_kw ?: (strtolower($site_name) . ', ecommerce, open source, shopping cart, online store, marketplace, extensions, themes, php ecommerce');
		}

		$links = $this->document->getLinks();
		$has_canonical = false;
		foreach ($links as $link) {
			if (isset($link['rel']) && strtolower($link['rel']) === 'canonical') {
				$has_canonical = true;
				break;
			}
		}

		if (!$has_canonical) {
			if ($route === 'common/home') {
				$canonical_url = $data['base'];
			} else {
				$canonical_params = array();
				foreach (array('extension_id', 'blog_id', 'blog_category_id', 'partner_id', 'download_id') as $param_key) {
					if (!empty($this->request->get[$param_key])) {
						$canonical_params[] = $param_key . '=' . urlencode((string)$this->request->get[$param_key]);
					}
				}
				$canonical_url = $this->url->link($route, implode('&', $canonical_params));
			}
			$this->document->addLink($canonical_url, 'canonical');
			$links = $this->document->getLinks();
		}

		$data['description'] = $description;
		$data['keywords'] = $keywords;
		$data['robots'] = 'index, follow';
		$data['agentfy_data'] = json_encode($payment_gateway->getFrontendConfig());
		$data['links'] = $links;
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['name'] = $this->config->get('config_name');
		$data['logo'] = $this->config->get('config_name');

		$data['home'] = $this->url->link('common/home');
		$data['feature'] = $this->url->link('cms/feature');
		$data['demo'] = $this->url->link('cms/demo');
		$data['marketplace'] = $this->url->link('marketplace/extension');
		$data['extension'] = $this->url->link('marketplace/extension');
		$data['blog'] = $this->url->link('cms/blog');
		$data['download'] = $this->url->link('cms/download');
		$data['forum'] = 'https://forum.opencart.com';
		$data['documentation'] = 'https://docs.opencart.com';
		$data['book'] = 'https://docs.opencart.com/#additional-reading';
		$data['bug_tracker'] = 'https://github.com/opencart/opencart/issues';
		$data['developer'] = 'https://docs.opencart.com/developer/module/';
		$data['cloud'] = $this->url->link('cloud/landing');
		$data['partner'] = $this->url->link('support/partner');
		$data['support'] = $this->url->link('support/support');
		$data['cart2cart'] = $this->url->link('cms/cart2cart');
		$data['showcase'] = $this->url->link('cms/showcase');
		$data['contact'] = $this->url->link('support/contact');

		$member_token = isset($this->session->data['member_token']) ? 'member_token=' . $this->session->data['member_token'] : '';

		$data['logged'] = $this->member->isLogged();
		$data['login'] = $this->url->link('account/login', '', true);
		$data['register'] = $this->url->link('account/register', '', true);
		$data['account'] = $this->url->link('account/account', $member_token, true);
		$data['logout'] = $this->url->link('account/logout', '', true);

		return $this->load->view('common/header', $data);
	}
}
