<?php
class ControllerErrorCsrf extends Controller {
    public function index() {
        if ($this->registry->get('csrf_status') === 405) {
            $this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
            $this->response->addHeader('Allow: POST');
            $error = 'This action requires a POST request.';
        } else {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            $error = 'Invalid or missing request token. Reload the page before submitting again.';
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->setOutput(json_encode(array('error' => $error)));
    }
}
