// Reproducible non-DB checks; no provider/business endpoints.
const fs=require('node:fs'),cp=require('node:child_process'),crypto=require('node:crypto'),path=require('node:path');
process.chdir(path.resolve(__dirname,'..'));
if(fs.existsSync('application/config/app-config.php'))throw Error('Refuse configured CRM for isolated evidence');
const hash=s=>crypto.createHash('sha256').update(s).digest('hex');
const git=(...a)=>cp.execFileSync('git',a,{encoding:'utf8',maxBuffer:8e6}).trim();
const e={started_at:new Date().toISOString(),baseline:git('rev-parse','HEAD'),node:process.version,checks:[],limits:['No installed CRM/schema/DB/browser runtime','Loopback HTTP executes real policy hook only','Network counters apply only to controlled policy tests','No Linux/FPM/TLS/backup-restore certification']};
function run(id,exe,args,expect=0){
  const r=cp.spawnSync(exe,args,{encoding:'utf8',timeout:180000,maxBuffer:8e6,windowsHide:true});
  const output=(r.stdout||'')+(r.stderr||'');
  e.checks.push({id,command:[exe,...args],exit:r.status,expected_exit:expect,result:r.status===expect?'PASS':'FAIL',output:output.slice(0,16000),output_sha256:hash(output)});
  console.log(id+': '+r.status);
}
run('php_version','php',['-v']);
if(process.argv.includes('--build')) {
 const bash=process.env.BASH_EXE||'D:/laragon/bin/git/bin/bash.exe';
 run('clean_ci',bash,['-lc','npm ci --ignore-scripts --no-audit --no-fund']);
 run('production_build',bash,['-lc','npm run build']);
 run('asset_contract','node',['tests/release/asset-contract.test.cjs','--built']);
 run('backup_platform',bash,['-lc','composer --working-dir=modules/backup check-platform-reqs --no-dev']);
 run('application_platform',bash,['-lc','composer --working-dir=application check-platform-reqs --no-dev']);
 run('preflight','php',['scripts/deploy-preflight.php','--fresh-install']);
}
run('profiler_class','php',['tests/release/profiler-compat.php']);
run('policy_model_boundary','php',['tests/release/limited-policy.php']);
run('policy_http_loopback','node',['tests/release/limited-http.test.cjs']);
run('tenant_connection_failure','php',['tests/release/tenant-failclosed.php']);
run('unknown_host','php',['tests/release/tenant-failclosed.php','unknown-host']);
const last=e.checks.at(-1);if(!last.output.includes('"code":"TENANT_RUNTIME_UNAVAILABLE"'))last.result='FAIL';
run('csrf_config','php',['tests/release/csrf-gateway-config.php']);
run('release_smoke','node',['tests/release/deployment-smoke.test.cjs']);
run('setup_regression','node',['tests/release/setup-live.test.cjs']);
run('node_syntax','node',['--check','tests/release/limited-http.test.cjs']);
run('diff_check','git',['diff','--check']);
const changed=git('diff','--name-only').split('\n');
const added=git('ls-files','--others','--exclude-standard').split('\n');
const framework=git('ls-files','system/*.php').split('\n');
e.lint=[];
for(const file of [...new Set([...changed,...added,...framework])].filter(x=>x.endsWith('.php'))){
 const r=cp.spawnSync('php',['-l',file],{encoding:'utf8',windowsHide:true});
 e.lint.push({file,exit:r.status,sha256:hash(fs.readFileSync(file)),output:(r.stdout||'')+(r.stderr||'')});
}
run('vendor_tooling_known_exception','php',['-l','application/vendor/zbateson/stream-decorators/PhpCsFixer.php'],255);
const tooling=e.checks.at(-1);
if(tooling.exit===255 && tooling.output.includes('Parse error'))tooling.result='FAIL_PREEXISTING_TOOLING';
e.finished_at=new Date().toISOString();
e.verdict='BLOCKED';
e.database_access='NOT_RUN';e.production_operations='NOT_RUN';e.misa_business_calls='NOT_RUN';
fs.writeFileSync('docs/reports/CRM_LIMITED_LIVE_20261004_EVIDENCE.json',JSON.stringify(e,null,2)+'\n');
console.log(JSON.stringify({checks:e.checks.map(x=>({id:x.id,result:x.result,exit:x.exit})),lint:e.lint.length,lint_failed:e.lint.filter(x=>x.exit!==0).length}));
process.exitCode=e.checks.some(x=>x.result==='FAIL')||e.lint.some(x=>x.exit!==0)?1:0;
