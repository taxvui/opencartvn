"""Actual Chromium + bundled jQuery + CSRF helper. All HTTP is intercepted; no app writes."""
import json
from pathlib import Path
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright
root=Path(__file__).resolve().parents[2]
token='synthetic-member-token'
policy={'post':['account/store/delete','account/payment/delete','account/logout/index'], 'navigation':['account/store/delete','account/logout/index']}
html='<!doctype html><meta name="csrf-member-token" content="'+token+'"><meta name="csrf-policy" content=\''+json.dumps(policy)+'\'><a id="delete" href="index.php?route=account/store/delete&store_id=44">Delete</a>'
requests=[]
with sync_playwright() as pw:
    browser=pw.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    page=browser.new_page()
    def intercept(route):
        req=route.request
        requests.append({'url':req.url,'method':req.method,'body':req.post_data})
        route.fulfill(status=200,content_type='text/html' if req.is_navigation_request() else 'application/json',body=html if req.is_navigation_request() else '{"ok":true}',headers={'Access-Control-Allow-Origin':'*'})
    page.route('**/*',intercept)
    page.goto('https://shop.example/store/index.php')
    page.add_script_tag(content=(root/'application/view/javascript/jquery/jquery-1.12.2.min.js').read_text())
    page.add_script_tag(content=(root/'application/view/javascript/csrf.js').read_text())
    page.evaluate('''() => new Promise((resolve,reject) => {
        $.ajax({url:'index.php?route=account/payment/delete&member_payment_id=44',data:'fixture=1'}).done(resolve).fail(reject);
    })''')
    last=requests[-1];args=parse_qs(urlparse(last['url']).query)
    assert last['method']=='POST' and args['member_token']==[token] and args['member_payment_id']==['44'] and last['body']=='fixture=1'
    print('PASS real jQuery legacy mutation GET -> POST with IDs/body preserved')
    page.evaluate('''() => new Promise((resolve,reject) => {
        $.ajax({url:'index.php?route=support/contact/send',type:'POST',data:'message=fixture'}).done(resolve).fail(reject);
    })''')
    assert parse_qs(urlparse(requests[-1]['url']).query)['member_token']==[token]
    print('PASS real jQuery signed-in POST receives token')
    page.evaluate('''() => new Promise((resolve,reject) => {
        $.ajax({url:'https://other.example/index.php?route=account/payment/delete',type:'POST',data:'fixture=1'}).done(resolve).fail(reject);
    })''')
    assert 'member_token' not in requests[-1]['url'] and token not in (requests[-1]['body'] or '')
    print('PASS real browser cross-origin request never receives member token')
    page.once('dialog',lambda d:d.accept())
    with page.expect_navigation(): page.click('#delete')
    last=requests[-1];args=parse_qs(urlparse(last['url']).query)
    assert last['method']=='POST' and args['store_id']==['44'] and args['member_token']==[token]
    print('PASS real browser delete navigation submits POST')
    browser.close()
print('4 browser scenarios PASS; all requests intercepted')
