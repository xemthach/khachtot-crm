// Read-only source inventory and safe checks; outputs paths/counts, never matched secrets.
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '..');
const output = process.argv[2];
if (!output) throw new Error('Usage: node tools/release-audit.cjs <evidence.json>');
const git = (...args) => cp.execFileSync('git', args, {cwd:root,encoding:'utf8',maxBuffer:32*1024*1024}).trim();
const files = [];
function walk(dir) {
  for (const entry of fs.readdirSync(path.join(root, dir), {withFileTypes:true})) {
    if (entry.isSymbolicLink()) continue;
    const rel = path.posix.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (['.git','vendor','node_modules','uploads','media','temp','storage','cache','logs','.idea','_archive_old_reports'].includes(entry.name)) continue;
      walk(rel);
    } else files.push(rel);
  }
}
walk('');
const tracked = new Set(git('ls-files').split('\n'));
const php = files.filter(f => /\.php$/i.test(f));
const scan = [];
const rules = [
  ['private_key', /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/],
  ['github_token', /\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,})\b/],
  ['aws_access_key', /\bAKIA[A-Z0-9]{16}\b/],
];
for (const file of files.filter(f=>tracked.has(f))) {
  if (fs.statSync(path.join(root,file)).size > 2*1024*1024 || !/\.(php|json|md|sh|ya?ml|env|example|txt)$/i.test(file)) continue;
  const lines = fs.readFileSync(path.join(root,file),'utf8').split('\n');
  lines.forEach((line,i)=>rules.forEach(([rule,re])=>{if(re.test(line)) scan.push({file,line:i+1,rule,status:'REQUIRES_REVIEW'});}));
}
const modules = fs.readdirSync(path.join(root,'modules'),{withFileTypes:true}).filter(e=>e.isDirectory()).map(e=>{
  const prefix=`modules/${e.name}/`;
  const own=files.filter(f=>f.startsWith(prefix));
  const bootstrap=`${prefix}${e.name}.php`;
  const source=fs.existsSync(path.join(root,bootstrap))?fs.readFileSync(path.join(root,bootstrap),'utf8'):'';
  return {name:e.name,bootstrap:source?bootstrap:null,version:source.match(/Version:\s*([^\r\n]+)/)?.[1]||null,tracked:tracked.has(bootstrap),controllers:own.filter(f=>f.includes('/controllers/')&&f.endsWith('.php')),routes:own.filter(f=>f.endsWith('/config/routes.php')),tests:own.filter(f=>/\/tests\/.*\.php$/.test(f)),status:'STATIC ONLY'};
});
const evidence={started_at:new Date().toISOString(),root,os:`${process.platform}/${process.arch}`,node:process.version,branch:git('branch','--show-current'),head:git('rev-parse','HEAD'),status:git('status','--short','--branch').split('\n'),modules,php_file_count:php.length,lint:[],secret_scan:{scope:'tracked non-vendor text files <=2MiB; high-confidence patterns only; not exhaustive',findings:scan},large_tracked_files: [...tracked].filter(f=>fs.existsSync(path.join(root,f))&&fs.statSync(path.join(root,f)).size>10*1024*1024).map(f=>({file:f,bytes:fs.statSync(path.join(root,f)).size})),checks:[]};
const tests=[
 'modules/kt_integration_hub/tests/IntegrationErrorSanitizerTest.php',
 'modules/kt_saas/tests/KtSaasContextRestorationTest.php',
 'modules/kt_saas/tests/DmsSourceOfTruthCopySmokeTest.php',
 'modules/kt_integration_hub/tests/DmsRoutePermissionGovernanceTest.php',
 'modules/kt_integration_hub/tests/DmsRenderedSecurityNoSecretsTest.php',
 'modules/kt_misa_amis_accounting/tests/MisaFoundationContractTest.php',
 'modules/kt_misa_amis_accounting/tests/MisaStateMachineTest.php',
 'modules/kt_misa_amis_accounting/tests/MisaSecurityStaticTest.php',
];
function run(file,args) {
 return new Promise(resolve=>{
   const child=cp.spawn(file,args,{cwd:root,windowsHide:true});let text='';
   child.stdout.on('data',b=>text+=b);child.stderr.on('data',b=>text+=b);
   const timer=setTimeout(()=>child.kill(),30000);
   child.on('error',()=>{clearTimeout(timer);resolve({exit:null,output_hash:null});});
   child.on('close',code=>{clearTimeout(timer);resolve({exit:code,output_hash:crypto.createHash('sha256').update(text).digest('hex')});});
 });
}
(async()=>{
 let cursor=0;
 await Promise.all(Array.from({length:6},async()=>{while(cursor<php.length){const file=php[cursor++];const r=await run('php',['-l',file]);evidence.lint.push({file,...r});}}));
 for (const file of tests) {
   if(!fs.existsSync(path.join(root,file))){evidence.checks.push({file,status:'NOT FOUND'});continue;}
   const r=await run('php',[file]);evidence.checks.push({file,...r,status:r.exit===0?'VERIFIED':'FAIL_CURRENT_RUN'});
 }
 evidence.lint.sort((a,b)=>a.file.localeCompare(b.file));
 evidence.finished_at=new Date().toISOString();
 fs.writeFileSync(path.resolve(root,output),JSON.stringify(evidence,null,2)+'\n');
 console.log(JSON.stringify({modules:modules.length,php:php.length,lint_failures:evidence.lint.filter(x=>x.exit!==0),checks:evidence.checks,secret_findings:scan.length,large_files:evidence.large_tracked_files},null,2));
 process.exitCode = evidence.lint.some(x=>x.exit!==0) || evidence.checks.some(x=>x.status!=='VERIFIED') || scan.length ? 1 : 0;
})().catch(e=>{console.error(e.message);process.exitCode=1;});
