import {mkdtemp,writeFile,readFile,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {fileURLToPath,pathToFileURL} from 'node:url';
import {spawn} from 'node:child_process';
import net from 'node:net';
import assert from 'node:assert/strict';
const root=await mkdtemp(join(tmpdir(),'fluxo-logout-'));
const system=fileURLToPath(new URL('../',import.meta.url));
const quote=v=>"'"+v.replaceAll('\\','\\\\').replaceAll("'","\\'")+"'";
const counter=join(root,'logout-count');
await writeFile(join(root,'router.php'),`<?php
namespace Systopic\\System\\Auth {
 class Session {static function current(){return (object)['name'=>'Test'];}static function logout(){file_put_contents(${quote(counter)},'logout\\n',FILE_APPEND);}}
 class LoginLog {const LOGOUT_BUTTON='button';static function write(...$args){}}
}
namespace {
 require ${quote(system+'lib/class/http/helper/httpHelper.php')};
 class http extends httpHelper {static $root='/project/';static function ajax(){return false;}}
 $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
 if($path==='/project/admin/auth/logout'){echo '<h1>Signed out</h1>';exit;}
 $panel=new class {
  public $href='/project/admin/user/';public $action='logout';
  function controls(){include ${quote(system+'panels/admin/user/controls.tpl.php')};}
  function logout(){include ${quote(system+'panels/admin/user/actions.php')};echo '<h1>EMPTY USER PAGE</h1>';}
 };
 if($path==='/project/admin/user/logout'){$panel->logout();exit;}
 echo '<h1>Profile</h1>';$panel->controls();
}
`);
const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
const server=spawn('php',['-S',`127.0.0.1:${port}`,join(root,'router.php')],{stdio:'ignore'});
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
let browser;
try{
 const base=`http://127.0.0.1:${port}`;
 for(let i=0;i<40;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,50));}}
 browser=await chromium.launch({channel:'chrome',headless:true});const page=await browser.newPage();let userLogoutRequests=0;let sawEmpty=false;
 page.on('request',req=>{if(req.url().endsWith('/user/logout'))userLogoutRequests++;});
 page.on('response',async res=>{if(res.request().resourceType()==='document'){try{if((await res.text()).includes('EMPTY USER PAGE'))sawEmpty=true;}catch{}}});
 await page.goto(base+'/project/admin/user/');
 assert.equal(await page.getByRole('link',{name:'Log out'}).getAttribute('target'),'_self');
 const response=page.waitForResponse(res=>res.url().endsWith('/user/logout'));
 await page.getByRole('link',{name:'Log out'}).click();
 await page.waitForURL('**/admin/auth/logout');
 assert.equal((await response).status(),302);
 assert.equal((await response).headers()['refresh'],undefined,'no delayed render-and-refresh');
 assert.equal(await page.getByRole('heading').innerText(),'Signed out');
 assert.equal(userLogoutRequests,1);assert.equal(sawEmpty,false);
 assert.equal((await readFile(counter,'utf8')).split('logout').length-1,1,'session logout executes once');
 console.log('ok: actual profile logout control follows native session action, one 302 to auth, no empty profile document and no duplicate logout');
}finally{if(browser)await browser.close();server.kill();await rm(root,{recursive:true,force:true});}
