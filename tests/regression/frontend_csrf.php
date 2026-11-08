<?php
// Real startup, identity, Router/Action and destructive controllers; in-memory DB/model spies.
ob_start();
$root=dirname(__DIR__,2);
define('DIR_SYSTEM',$root.'/system/');define('DIR_APPLICATION',$root.'/application/');define('DIR_LANGUAGE',DIR_APPLICATION.'language/');
foreach(array('DB_DRIVER'=>'mysqli','DB_HOSTNAME'=>'fixture','DB_USERNAME'=>'fixture','DB_PASSWORD'=>'','DB_DATABASE'=>'fixture','DB_PORT'=>'3306') as $k=>$v)define($k,$v);
define('HTTP_SERVER','http://localhost/');define('HTTPS_SERVER',HTTP_SERVER);define('DB_PREFIX','oc_');
foreach (array('registry','controller','model','action','router') as $f) require $root.'/system/engine/'.$f.'.php';
foreach (array('config','language','url','encryption','cart/member','cart/currency') as $f) require $root.'/system/library/'.$f.'.php';
require DIR_APPLICATION.'controller/startup/startup.php';
require DIR_APPLICATION.'model/account/store.php';
if(is_file(DIR_APPLICATION.'controller/startup/csrf.php')) require DIR_APPLICATION.'controller/startup/csrf.php';
function token($n){return str_repeat('t',$n);}
class CsrfRedirect extends Exception { public $url; function __construct($url){$this->url=$url;parent::__construct('redirect');} }
class CsrfResponse {
 public $headers=array();public $output='';
 function addHeader($s){$this->headers[]=$s;}function setOutput($s){$this->output=$s;}
 function redirect($url){throw new CsrfRedirect($url);}
}
class CsrfDB {
 public $pin='1234';public $verifiedIp=true;
 function escape($s){return addslashes($s);}
 function query($sql){
  $rows=array();
  if(strpos($sql,'FROM `oc_member` WHERE')!==false){
   $row=array_fill_keys(array('username','firstname','lastname','company','tax_id','email','telephone','image','date_added'),'fixture');
   $row+=array('member_id'=>7,'country_id'=>1,'pin'=>$this->pin,'pin_attempts'=>0,'verified'=>1,'notify'=>1,'seller_status'=>1,'partner_status'=>0,'moderator'=>0,'cloud'=>0);
   $rows=array($row);
  }elseif(strpos($sql,'FROM `oc_member_ip`')!==false && $this->verifiedIp){$rows=array(array('member_ip_id'=>1));}
  return (object)array('rows'=>$rows,'row'=>$rows?$rows[0]:array(),'num_rows'=>count($rows));
 }
}
class CsrfLocale {function getLanguages(){return array('en-gb'=>array('language_id'=>1,'status'=>1,'locale'=>'en-gb'));}function getCurrencies(){return array('USD'=>array('status'=>1));}}
class CsrfModels {
 public $writes=array();
 function getExtension($id){return array('extension_id'=>$id,'member_id'=>7);}
 function deleteExtension($id){$this->writes[]=array('extension',$id);}
 function deleteStore($id){$this->writes[]=array('store',$id);}
}
class CsrfLoader {public $routes=array();function model($r){$this->routes[]=$r;}}
function fixture($route,$method,$token='missing',$logged=true){
 $r=new Registry();$config=new Config();
 unset($GLOBALS['__oc_active_member_token'], $GLOBALS['__oc_site_config']);
 $_=array();include dirname(__DIR__,2).'/system/config/application.php';foreach($_ as $k=>$v)$config->set($k,$v);
 foreach(array('config_language'=>'en-gb','config_currency'=>'USD','config_encryption'=>'fixture') as $k=>$v)$config->set($k,$v);
 $get=array('route'=>$route,'extension_id'=>10,'store_id'=>11);
 if($token!=='missing')$get['member_token']=$token==='valid'?str_repeat('a',32):($token==='array'?array('bad'):$token);
 $request=(object)array('get'=>$get,'post'=>array(),'cookie'=>array('language'=>'en-gb','currency'=>'USD'),'server'=>array('REQUEST_METHOD'=>$method,'SERVER_PROTOCOL'=>'HTTP/1.1','HTTPS'=>false,'HTTP_HOST'=>'localhost','SCRIPT_NAME'=>'/index.php','REMOTE_ADDR'=>'127.0.0.1'));
 $session=(object)array('data'=>array('language'=>'en-gb','currency'=>'USD','member_token'=>str_repeat('a',32)));
 if($logged)$session->data['member_id']=7;
 $models=new CsrfModels();
 foreach(array('config'=>$config,'request'=>$request,'session'=>$session,'db'=>new CsrfDB(),'load'=>new CsrfLoader(),'response'=>new CsrfResponse(),'model_localisation_language'=>new CsrfLocale(),'model_localisation_currency'=>new CsrfLocale(),'model_marketplace_extension'=>$models,'model_account_store'=>$models) as $k=>$v)$r->set($k,$v);
 return $r;
}
function startup($r){(new ControllerStartupStartup($r))->index();}
function guard($r){return class_exists('ControllerStartupCsrf')?(new ControllerStartupCsrf($r))->index():null;}
function dispatch($r){
 $router=new Router($r);$router->addPreAction(new Action('startup/startup'));
 if(class_exists('ControllerStartupCsrf'))$router->addPreAction(new Action('startup/csrf'));
 $router->dispatch(new Action($r->get('request')->get['route']),new Action('error/not_found'));
}
function check($ok,$msg){if(!$ok)throw new Exception($msg);}
$tests=0;$failures=0;
function runCase($name,$fn){global $tests,$failures;$tests++;try{$fn();echo "PASS $name\n";}catch(Throwable $e){$failures++;echo "FAIL $name: ".$e->getMessage()."\n";}}
foreach(array('missing','wrong','array','valid') as $token){
 runCase('startup keeps client token '.$token,function()use($token){$r=fixture('common/home','GET',$token);$before=$r->get('request')->get;startup($r);check($r->get('request')->get===$before,'startup manufactured or replaced client token');check(strpos($r->get('url')->link('account/extension'),'member_token='.str_repeat('a',32))!==false,'outgoing authenticated links lost token');});
}
foreach(array('account/extension/delete','account/store/delete') as $route){
 foreach(array('missing','wrong','array','valid') as $token){
  runCase($route.' POST '.$token,function()use($route,$token){$r=fixture($route,'POST',$token);try{dispatch($r);}catch(CsrfRedirect $e){}
   if($token==='valid'){check(count($r->get('model_marketplace_extension')->writes)===1,'valid mutation blocked');}
   else{check(!$r->get('model_marketplace_extension')->writes,'untrusted request reached write');check(in_array('HTTP/1.1 403 Forbidden',$r->get('response')->headers,true),'403 missing');}
  });
 }
 foreach(array('GET','HEAD') as $method){
  runCase($route.' '.$method.' cannot write even with token',function()use($route,$method){$r=fixture($route,$method,'valid');try{dispatch($r);}catch(CsrfRedirect $e){}check(!$r->get('model_marketplace_extension')->writes,'safe HTTP method caused mutation');check(in_array('HTTP/1.1 405 Method Not Allowed',$r->get('response')->headers,true),'405 missing');});
 }
}
foreach(array('account//extension/DELETE/ignored','account/store/delete/ignored') as $route){
 runCase('Action alias cannot bypass POST policy '.$route,function()use($route){$r=fixture($route,'GET','valid');try{dispatch($r);}catch(CsrfRedirect $e){}check(!$r->get('model_marketplace_extension')->writes,'route alias bypassed policy');check(in_array('HTTP/1.1 405 Method Not Allowed',$r->get('response')->headers,true),'alias 405 missing');});
}
foreach(array('checkout/payment/confirm','account/security/reset','cms/blog/addcomment','common/image','support/contact/send') as $route){
 runCase('signed-in POST requires token '.$route,function()use($route){$r=fixture($route,'POST');startup($r);$action=guard($r);check($action instanceof Action,'missing token passed middleware');$action->execute($r);check(in_array('HTTP/1.1 403 Forbidden',$r->get('response')->headers,true),'403 missing');});
}
foreach(array('common/home','marketplace/extension/info','account/pin','account/security/confirm') as $route){
 runCase('read-only/capability GET remains available '.$route,function()use($route){$r=fixture($route,'GET');startup($r);check(guard($r)===null,'public/capability read blocked');});
}
foreach(array('account/extension','account/store','account/subscription') as $route){
 runCase('private read deep link canonicalizes without mutating input '.$route,function()use($route){$r=fixture($route,'GET');$before=$r->get('request')->get;startup($r);$redirect='';try{guard($r);}catch(CsrfRedirect $e){$redirect=$e->url;}check(strpos($redirect,'member_token='.str_repeat('a',32))!==false,'read deep link cannot recover valid navigation');check($before===$r->get('request')->get,'request was changed');});
}
foreach(array('pin','ip') as $gate){
 runCase('PIN/IP gate retained '.$gate,function()use($gate){$r=fixture('account/extension','GET');if($gate==='pin')$r->get('db')->pin='';else$r->get('db')->verifiedIp=false;$redirect='';try{startup($r);}catch(CsrfRedirect $e){$redirect=$e->url;}check(strpos($redirect,'route=account/'.($gate==='pin'?'pin':'security'))!==false,'security gate missing');});
}
runCase('guest login POST still reaches authentication controller',function(){$r=fixture('account/login','POST','missing',false);startup($r);check(guard($r)===null,'guest login blocked');});
runCase('session token is not reflected on rejection',function(){$r=fixture('account/store/delete','POST','wrong');startup($r);$a=guard($r);check($a instanceof Action,'guard absent');$a->execute($r);check(strpos($r->get('response')->output,str_repeat('a',32))===false,'secret reflected');});
runCase('application pre-action runs CSRF after SEO resolution',function(){$_=array();include dirname(__DIR__,2).'/system/config/application.php';$a=$_['action_pre_action'];check(in_array('startup/csrf',$a,true) && array_search('startup/csrf',$a,true)>array_search('startup/seo_url',$a,true),'guard missing or before SEO');});

runCase('every POST policy entry targets an existing public controller method',function(){
 $r=fixture('common/home','GET','valid');
 foreach($r->get('config')->get('csrf_post_routes') as $route){
  $parts=explode('/',$route);$method=array_pop($parts);$file=DIR_APPLICATION.'controller/'.implode('/',$parts).'.php';
  check(is_file($file),'policy file missing: '.$route);
  check(preg_match('/public\\s+function\\s+'.preg_quote($method,'/').'\\s*\\(/i',file_get_contents($file))===1,'policy method missing: '.$route);
 }
});
runCase('real header renders shared policy and scoped member token',function(){
 require_once DIR_SYSTEM.'storage/vendor/autoload.php';require_once DIR_SYSTEM.'library/document.php';require_once DIR_APPLICATION.'controller/common/header.php';
 $r=fixture('common/home','GET','valid');startup($r);$r->set('document',new Document());
 $r->set('load',new class extends CsrfLoader {function view($route,$data=array()){
  $twig=new \Twig\Environment(new \Twig\Loader\FilesystemLoader(DIR_APPLICATION.'view/template'),array('cache'=>false,'autoescape'=>false));
  return $twig->render($route.'.twig',$data);
 }});
 $html=(new ControllerCommonHeader($r))->index();
 check(strpos($html,'csrf.js')!==false,'client helper not loaded');
 preg_match('/name="csrf-member-token" content="([^"]*)"/',$html,$m);check(html_entity_decode($m[1],ENT_QUOTES,'UTF-8')===str_repeat('a',32),'header token wrong');
 preg_match('/name="csrf-policy" content="([^"]*)"/',$html,$m);$policy=json_decode(html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),true);
 check($policy['post']===$r->get('config')->get('csrf_post_routes'),'browser/server policy mismatch');
});

runCase('PIN reset cannot mutate through GET',function(){
 $r=fixture('account/security/reset','GET','valid');startup($r);$action=guard($r);check($action instanceof Action,'PIN reset GET not rejected');$action->execute($r);check(in_array('HTTP/1.1 405 Method Not Allowed',$r->get('response')->headers,true),'PIN reset 405 missing');
});

runCase('read recovery redirects to resolved route, not raw input',function(){
 $r=fixture("account/extension/index/unused\r\nX-Injected: yes",'GET');startup($r);$url='';try{guard($r);}catch(CsrfRedirect $e){$url=$e->url;}
 check($url!=='' && strpos($url,"\r")===false && strpos($url,"\n")===false && strpos($url,'route=account/extension&amp;')!==false,'raw route echoed in redirect');
});
echo "$tests tests, $failures failures\n";ob_end_flush();exit($failures?1:0);
