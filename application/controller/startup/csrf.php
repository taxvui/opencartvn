<?php
class ControllerStartupCsrf extends Controller {
    public function index() {
        $route = $this->resolvedRoute($this->request->get['route'] ?? 'common/home');
        $method = strtoupper($this->request->server['REQUEST_METHOD'] ?? 'GET');
        $post_only = in_array($route, $this->config->get('csrf_post_routes') ?: array(), true);
        if ($post_only && $method !== 'POST') {
            $this->registry->set('csrf_status', 405);
            return new Action('error/csrf');
        }
        $logged = $this->member->isLogged();
        if ($post_only || ($logged && !in_array($method, array('GET', 'HEAD', 'OPTIONS'), true))) {
            $expected = $this->session->data['member_token'] ?? null;
            $submitted = $this->request->get['member_token'] ?? null;
            if (!$logged || !is_string($expected) || $expected === '' || !is_string($submitted) || !hash_equals($expected, $submitted)) {
                $this->registry->set('csrf_status', 403);
                return new Action('error/csrf');
            }
        }
        // Only explicitly reviewed read-only pages may recover a missing token
        // by redirecting. Never manufacture an inbound token or replay a write.
        if ($logged && $method === 'GET' && !isset($this->request->get['member_token'])
            && in_array($route, $this->config->get('csrf_read_routes') ?: array(), true)) {
            $args = $this->request->get;
            unset($args['route']);
            $args['member_token'] = $this->session->data['member_token'];
            // Every reviewed read route ends in /index. Emit its canonical
            // controller route rather than reflecting the raw route string.
            $this->response->redirect($this->url->link(substr($route, 0, -6), $args, true));
        }
    }

    private function resolvedRoute($route) {
        // Match Action's file/method resolution, including case-insensitive PHP
        // methods, repeated slashes and extra suffixes; raw URL matching alone
        // would let e.g. account/extension/DELETE/extra evade the method policy.
        if (!is_string($route)) { return ''; }
        $parts = explode('/', preg_replace('/[^a-zA-Z0-9_\/]/', '', $route));
        $method = 'index';
        while ($parts) {
            if (is_file(DIR_APPLICATION . 'controller/' . implode('/', $parts) . '.php')) {
                $controller = implode('/', array_filter($parts, 'strlen'));
                return $controller . '/' . strtolower($method);
            }
            $method = array_pop($parts);
        }
        return '';
    }
}
