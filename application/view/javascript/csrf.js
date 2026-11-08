(function (window, document, $) {
    'use strict';
    var tokenMeta = document.querySelector('meta[name="csrf-member-token"]');
    var policyMeta = document.querySelector('meta[name="csrf-policy"]');
    var token = tokenMeta && tokenMeta.getAttribute('content');
    if (!token || !policyMeta || !$ || !$.ajaxPrefilter) return;
    var policy;
    try { policy = JSON.parse(policyMeta.getAttribute('content')); } catch (e) { return; }
    var endpoint = new URL('index.php', document.baseURI);
    function localUrl(raw) {
        try {
            var url = new URL(raw, document.baseURI);
            if (url.origin === endpoint.origin && url.pathname === endpoint.pathname) return url;
        } catch (e) {}
        return null;
    }
    function action(url, routes) {
        var route = (url.searchParams.get('route') || '').replace(/[^a-zA-Z0-9_\/]/g, '').split('/').filter(Boolean).join('/').toLowerCase();
        for (var i = 0; i < routes.length; i++) {
            if (route === routes[i] || route + '/index' === routes[i] || route.indexOf(routes[i] + '/') === 0) return routes[i];
        }
        return '';
    }
    function attachToken(url) {
        // Do not conceal a stale/incorrect token explicitly supplied by a caller.
        if (!url.searchParams.has('member_token')) url.searchParams.set('member_token', token);
    }
    $.ajaxPrefilter(function (options) {
        var url = localUrl(options.url || '');
        if (!url) return;
        var method = (options.type || 'GET').toUpperCase();
        var mutation = action(url, policy.post || []);
        if (mutation && method === 'GET') {
            options.type = 'POST';
            options.method = 'POST';
            method = 'POST';
        }
        if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
            attachToken(url);
            options.url = url.href;
        }
    });
    // Intercept only reviewed redirecting mutations. JSON actions retain their
    // existing AJAX callbacks; external links and admin URLs are never touched.
    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        var element = event.target.closest && event.target.closest('a[href], [data-csrf-post]');
        if (!element) return;
        var url = localUrl(element.getAttribute('data-csrf-post') || element.getAttribute('href'));
        if (!url) return;
        var route = action(url, policy.navigation || []);
        if (!route) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (route !== 'account/logout/index' && !window.confirm('Are you sure?')) return;
        attachToken(url);
        var form = document.createElement('form');
        form.method = 'post';
        form.action = url.href;
        form.style.display = 'none';
        document.body.appendChild(form);
        form.submit();
    }, true);
})(window, document, window.jQuery);
