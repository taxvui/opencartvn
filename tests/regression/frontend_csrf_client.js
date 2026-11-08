const fs=require('fs'),vm=require('vm');
const policy={post:['account/extension/delete','account/store/delete','account/payment/delete','account/logout/index','account/security/reset','checkout/payment/confirm'],navigation:['account/extension/delete','account/store/delete','account/logout/index']};
function fixture(token='fixture-token') {
 let filter,click;const forms=[];const w={confirm:()=>true};
 const doc={baseURI:'https://shop.example/store/',body:{appendChild:f=>forms.push(f)},querySelector:s=>({getAttribute:()=>s.includes('csrf-policy')?JSON.stringify(policy):token}),addEventListener:(name,fn)=>{if(name==='click')click=fn;},createElement:tag=>({tag,style:{},children:[],appendChild(x){this.children.push(x);},submit(){this.submitted=true;}})};
 const $={ajaxPrefilter:fn=>{filter=fn;}};w.jQuery=$;
 vm.runInNewContext(fs.readFileSync('application/view/javascript/csrf.js','utf8'),{window:w,document:doc,jQuery:$,URL});
 return {filter,click,forms,w};
}
function check(ok,msg){if(!ok)throw Error(msg);}
let count=0;
function run(name,fn){fn();count++;console.log('PASS',name);}
run('same-origin missing POST token is supplied by browser, not server',()=>{const f=fixture();const x={url:'index.php?route=checkout/payment/confirm',type:'POST'};f.filter(x);check(new URL(x.url).searchParams.get('member_token')==='fixture-token','token missing');});
run('explicit wrong token is never replaced',()=>{const f=fixture();const x={url:'index.php?route=checkout/payment/confirm&member_token=wrong',type:'POST'};f.filter(x);check(new URL(x.url).searchParams.get('member_token')==='wrong','token overwritten');});
run('legacy AJAX GET mutation becomes POST, preserving IDs/data',()=>{const f=fixture();const x={url:'index.php?route=account/payment/delete&member_payment_id=44',type:'GET',data:'fixture=1'};f.filter(x);check(x.type==='POST' && new URL(x.url).searchParams.get('member_payment_id')==='44' && x.data==='fixture=1','contract changed');});
for(const url of ['https://other.example/index.php?route=account/store/delete','//other.example/index.php?route=account/store/delete','/admin/index.php?route=account/store/delete','/store/webhook?route=account/store/delete']) {
 run('never leaks token or changes method to '+url,()=>{const f=fixture();const x={url,type:'POST'};f.filter(x);check(x.url===url && !x.url.includes('fixture-token'),'external URL changed');});
}
run('ordinary public GET remains cacheable/token-free',()=>{const f=fixture();const x={url:'index.php?route=marketplace/extension',type:'GET'};f.filter(x);check(!x.url.includes('member_token') && x.type==='GET','read request changed');});
run('method alias handled consistently',()=>{const f=fixture();const x={url:'index.php?route=account//store/DELETE/unused',type:'GET'};f.filter(x);check(x.type==='POST','alias escaped client policy');});
run('navigation delete submits POST with URL arguments',()=>{const f=fixture();let cancelled=false;const element={getAttribute:k=>k==='href'?'index.php?route=account/store/delete&store_id=44':null};f.click({button:0,target:{closest:()=>element},preventDefault(){cancelled=true;},stopImmediatePropagation(){}});check(cancelled && f.forms[0].method==='post' && f.forms[0].submitted && new URL(f.forms[0].action).searchParams.get('store_id')==='44','not a POST navigation');});
run('cancelled confirmation never submits',()=>{const f=fixture();f.w.confirm=()=>false;const element={getAttribute:k=>k==='href'?'index.php?route=account/store/delete':null};f.click({button:0,target:{closest:()=>element},preventDefault(){},stopImmediatePropagation(){}});check(f.forms.length===0,'cancel submitted');});
run('logout submits POST without destructive confirmation',()=>{const f=fixture();f.w.confirm=()=>{throw Error('unexpected prompt');};const element={getAttribute:k=>k==='href'?'index.php?route=account/logout':null};f.click({button:0,target:{closest:()=>element},preventDefault(){},stopImmediatePropagation(){}});check(f.forms[0].submitted,'logout did not POST');});
run('guest pages do not fabricate tokens',()=>{const f=fixture('');check(!f.filter && !f.click,'guest helper active');});
run('PIN-reset AJAX is converted to POST and receives token',()=>{const f=fixture();const x={url:'index.php?route=account/security/reset',type:'GET',crossDomain:true};f.filter(x);check(x.type==='POST' && new URL(x.url).searchParams.get('member_token')==='fixture-token','PIN reset contract not migrated');});
console.log(count+' client scenarios PASS');
