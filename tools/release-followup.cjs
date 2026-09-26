// Read-only tracked PHP lint and ownership inventory. No application bootstrap or DB.
const fs=require('node:fs'),cp=require('node:child_process'),path=require('node:path'),crypto=require('node:crypto');
const [clone,output]=process.argv.slice(2);
if(!clone||!output)throw Error('Usage: node tools/release-followup.cjs <clean-clone> <evidence.json>');
const git=(cwd,...args)=>cp.execFileSync('git',args,{cwd,encoding:'utf8',maxBuffer:32*1024*1024}).trim();
const root=path.resolve(__dirname,'..');
const statuses=git(root,'status','--porcelain=v1','-uall').split('\n').filter(Boolean);
const ownership=statuses.map(line=>{
 const file=line.slice(3),area=file.split('/')[0];
 let reference='Inventory only; specific call path not established',packaging='EXCLUDE_PENDING_OWNER_REVIEW';
 if(file.includes('TenantHttpDatabaseLifecycle'))reference='dirty KtSaasTenantBootstrap.php:54 -> lifecycle -> Resolver/DatabaseSwitcher/TenantHttpContext';
 if(file.includes('TenantHttpContext'))reference='TenantHttpDatabaseLifecycle.php:7,59';
 if(file==='application/hooks/KtSaasTenantBootstrap.php')reference='tracked application/hooks/InitHook.php:28-33; dirty lifecycle rewrite';
 if(file==='package.json'||file==='package-lock.json')reference='npm ci/build; local differs from RC';
 if(file.startsWith('assets/'))reference='admin/client views and webpack.mix.js; generated user-owned bundles';
 if(file.startsWith('modules/kt_misa'))reference='local-only MISA family; absent from clean RC';
 if(file.startsWith('docs/')||file.startsWith('_archive'))packaging='NOT_REQUIRED_FOR_BOOT';
 return {file,status:line.slice(0,2),area,owner:'USER_PREEXISTING_OR_UNKNOWN',reason:'Git does not establish authorship or reason; no inferred approval',reference,packaging,required_checks:'owner diff review; secrets/PII; syntax; clean-clone route/dependency/runtime'};
});
const files=git(clone,'ls-files','*.php').split('\n').filter(Boolean);
const result={started_at:new Date().toISOString(),clone,clone_head:git(clone,'rev-parse','HEAD'),worktree_head:git(root,'rev-parse','HEAD'),ownership,lint:[],limits:['No DB or HTTP business runtime','Lint includes all tracked vendor PHP; no exclusion','Secret/history scan separate']};
function lint(file){return new Promise(resolve=>{
 const p=cp.spawn('php',['-l',file],{cwd:clone,windowsHide:true});let output='';
 p.stdout.on('data',b=>output+=b);p.stderr.on('data',b=>output+=b);
 const timer=setTimeout(()=>p.kill(),30000);
 p.on('error',()=>{});p.on('close',exit=>{clearTimeout(timer);resolve({file,exit,output_hash:crypto.createHash('sha256').update(output).digest('hex')});});
});}
(async()=>{let n=0;await Promise.all(Array.from({length:12},async()=>{while(n<files.length)result.lint.push(await lint(files[n++]));}));
result.lint.sort((a,b)=>a.file.localeCompare(b.file));result.finished_at=new Date().toISOString();
result.summary={tracked_php:files.length,pass:result.lint.filter(x=>x.exit===0).length,fail:result.lint.filter(x=>x.exit!==0).length,ownership_rows:ownership.length};
fs.writeFileSync(output,JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result.summary));process.exitCode=result.summary.fail?1:0;
})().catch(e=>{console.error(e.message);process.exitCode=1;});
