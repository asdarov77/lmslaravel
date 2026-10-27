import { chromium } from 'playwright';
const paths = process.argv.slice(2);
const b = await chromium.launch({channel:'chrome', args:['--no-sandbox']});
const ctx = await b.newContext();
const p = await ctx.newPage();
const errs=[]; const apis=[];
p.on('pageerror', e=>errs.push('PE: '+e.message.split('\n')[0].slice(0,100)));
p.on('console', m=>{ if(m.type()==='error' && !/ERR_(TIMED_OUT|NAME|CONNECTION)|avataaars|bulma|jsdelivr|Failed to load resource/.test(m.text())) errs.push('CE: '+m.text().slice(0,100)); });
p.on('response', async r=>{ if(r.url().includes('/api/') && r.status()>=400) { let bd=''; try{ bd=(await r.text()).slice(0,160);}catch{} apis.push('API '+r.status()+' '+decodeURIComponent(r.url().replace('http://127.0.0.1:8080',''))+' :: '+bd.replace(/\s+/g,' ')); } });
const req = await p.request.post('http://127.0.0.1:8080/api/login', {data:{fio:'Администратор',password:'123'}});
const {data} = await req.json();
await ctx.addInitScript(([t,u])=>{localStorage.setItem('token',t);localStorage.setItem('user',u);},[data.token, JSON.stringify(data.user)]);
for (const path of paths) {
  errs.length=0; apis.length=0;
  try { await p.goto('http://127.0.0.1:8080/#'+path, {waitUntil:'domcontentloaded', timeout:20000}); } catch(e){ console.log(path,'GOTO-FAIL'); continue; }
  await p.waitForTimeout(1500);
  console.log('\n=== '+path+' '+(errs.length||apis.length?'FAIL':'ok'));
  [...new Set([...errs,...apis])].forEach(x=>console.log('   '+x));
}
await b.close();
