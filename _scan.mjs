import { chromium } from 'playwright';
const paths = ['/questions','/courses/item/15','/courses/itemmani'];
const b = await chromium.launch({channel:'chrome', args:['--no-sandbox']});
const ctx = await b.newContext();
const p = await ctx.newPage();
const errs=[]; const apis=[];
p.on('pageerror', e=>errs.push('PE:'+e.message.slice(0,60)));
p.on('console', m=>{ if(m.type()==='error' && !/ERR_(TIMED_OUT|NAME|CONNECTION)|avataaars|bulma|jsdelivr/.test(m.text())) errs.push('CE:'+m.text().slice(0,80)); });
p.on('response', r=>{ if(r.url().includes('/api/') && r.status()>=400) apis.push(r.status()+' '+decodeURIComponent(r.url().replace('http://127.0.0.1:8080',''))); });
// login
const req = await p.request.post('http://127.0.0.1:8080/api/login', {data:{fio:'Администратор',password:'123'}});
const {data} = await req.json();
await ctx.addInitScript(([t,u])=>{localStorage.setItem('token',t);localStorage.setItem('user',u);},[data.token, JSON.stringify(data.user)]);
for (const path of paths) {
  errs.length=0; apis.length=0;
  try { await p.goto('http://127.0.0.1:8080/#'+path, {waitUntil:'domcontentloaded', timeout:20000}); } catch(e){ console.log(path.padEnd(24),'GOTO-FAIL'); continue; }
  await p.waitForTimeout(400);
  const body = (await p.textContent('body').catch(()=>'')) || '';
  const flag = errs.length||apis.length ? 'FAIL' : 'ok  ';
  console.log(path.padEnd(24), flag, (errs.join(' | ')+apis.join(' | ')).slice(0,110));
}
await b.close();
