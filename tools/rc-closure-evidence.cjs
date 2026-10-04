// Isolated clone only: build, tracked lint (including vendor), hashes and bounded scans.
const fs=require('fs'),cp=require('child_process'),crypto=require('crypto'),path=require('path');
const root=path.resolve(__dirname,'..');process.chdir(root);
if(fs.existsSync('application/config/app-config.php'))throw Error('Refuse configured application');
const output=process.argv[2];if(!output)throw Error('evidence output required');
const hash=s=>crypto.createHash('sha256').update(s).digest('hex');
const bash=process.env.BASH_EXE||(process.platform==='win32'?'D:/laragon/bin/git/bin/bash.exe':'bash');
const git=(...a)=>cp.execFileSync('git',a,{encoding:'utf8',maxBuffer:16*1024*1024}).trim();
const e={started_at:new Date().toISOString(),head:git('rev-parse','HEAD'),node:process.version,lock_before:hash(fs.readFileSync('package-lock.json')),checks:[],lint:[],artifacts:[],secret_matches:[]};
function run(id,exe,args){const r=cp.spawnSync(exe,args,{encoding:'utf8',timeout:300000,maxBuffer:16*1024*1024,windowsHide:true});const out=(r.stdout||'')+(r.stderr||'');e.checks.push({id,command:[exe,...args],exit:r.status,output_hash:hash(out)});console.log(id+': '+r.status);return r;}
run('npm_version',bash,['-lc','npm --version']);
run('clean_ci',bash,['-lc','npm ci --ignore-scripts --no-audit --no-fund']);
run('asset_build',bash,['-lc','npm run build']);
run('asset_contract','node',['tests/release/asset-contract.test.cjs','--built']);
run('release_smoke','node',['tests/release/deployment-smoke.test.cjs']);
run('setup_regression','node',['tests/release/setup-live.test.cjs']);
const audit=run('dependency_audit',bash,['-lc','npm audit --json']);
try{const a=JSON.parse(audit.stdout);e.audit={counts:a.metadata?.vulnerabilities,packages:Object.entries(a.vulnerabilities||{}).map(([name,v])=>({name,severity:v.severity,isDirect:v.isDirect,via:v.via,range:v.range,fixAvailable:v.fixAvailable}))};}catch{e.audit={status:'UNAVAILABLE'};}
e.lock_after=hash(fs.readFileSync('package-lock.json'));
e.versions={mix:require('../node_modules/laravel-mix/package.json').version,webpack:require('../node_modules/webpack/package.json').version};
const files=git('ls-files','*.php').split('\n').filter(Boolean);
function lint(file){return new Promise(resolve=>{const p=cp.spawn('php',['-l',file],{windowsHide:true});let s='';p.stdout.on('data',b=>s+=b);p.stderr.on('data',b=>s+=b);const timer=setTimeout(()=>p.kill(),30000);p.on('error',()=>{});p.on('close',exit=>{clearTimeout(timer);resolve({file,exit,hash:hash(s)});});});}
(async()=>{let cursor=0;await Promise.all(Array.from({length:12},async()=>{while(cursor<files.length)e.lint.push(await lint(files[cursor++]));}));
e.lint.sort((a,b)=>a.file.localeCompare(b.file));e.lint_groups={};for(const r of e.lint){const group=r.file.includes('/vendor/')?'vendor':r.file.startsWith('system/')?'framework':r.file.startsWith('application/')?'application':'modules_other';e.lint_groups[group]??={total:0,passed:0,failures:[]};const g=e.lint_groups[group];g.total++;if(r.exit===0)g.passed++;else g.failures.push(r.file);}
const outputs=[...new Set([...git('diff','--name-only').split('\n').filter(f=>f.startsWith('assets/')||f.startsWith('modules/einvoice/assets/')),'assets/builds/vendor-admin.css','assets/builds/tailwind.css','modules/einvoice/assets/builds/codemirror.js','modules/einvoice/assets/builds/template.js','modules/einvoice/assets/builds/codemirror.css'])];
for(const file of outputs){const b=fs.readFileSync(file);e.artifacts.push({file,bytes:b.length,sha256:hash(b)});const s=b.toString('utf8');if(/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\bgh[pousr]_[A-Za-z0-9]{30,}|\bAKIA[A-Z0-9]{16}\b/.test(s))e.secret_matches.push(file);}
e.finished_at=new Date().toISOString();e.limits=['Output pattern scan only, not full secret or Git-history audit','No app config/schema; installed CRM runtime NOT RUN','Dependency advisories require reachability review'];
fs.writeFileSync(output,JSON.stringify(e,null,2)+'\n');console.log(JSON.stringify({checks:e.checks,lint_groups:e.lint_groups,artifacts:e.artifacts.length,secret_matches:e.secret_matches},null,2));process.exitCode=e.checks.some(c=>c.exit!==0)||e.lint.some(r=>r.exit!==0)||e.secret_matches.length?1:0;
})().catch(x=>{console.error(x.message);process.exitCode=1;});
