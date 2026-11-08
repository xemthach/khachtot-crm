// Run bounded, non-business checks. Output hashes, not raw logs/credentials.
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process'),crypto=require('node:crypto');
const root=path.resolve(__dirname,'..'),clone=path.resolve(process.argv[2]||''),output=process.argv[3];
if(!output||clone===root||!fs.existsSync(path.join(clone,'.git')))throw Error('isolated clone + output required');
if(fs.existsSync(path.join(clone,'application/config/app-config.php')))throw Error('refuse configured clone');
const bash=process.env.BASH_EXE||(process.platform==='win32'?'D:/laragon/bin/git/bin/bash.exe':'bash');
const checks=[];
function run(id,exe,args,cwd=root){
 const r=cp.spawnSync(exe,args,{cwd,encoding:'utf8',timeout:120000,windowsHide:true});
 const out=(r.stdout||'')+(r.stderr||'');
 checks.push({id,command:[exe,...args],scope:cwd===root?'DIRTY_WORKTREE_TEST_ONLY':'CLEAN_RC',exit:r.status,status:r.status===0?'PASS':'FAIL',output_sha256:crypto.createHash('sha256').update(out).digest('hex')});
 console.log(id+': '+checks.at(-1).status);
}
const result={started_at:new Date().toISOString(),clone,checks};
run('UNCONFIGURED_HTTP','node',['tests/release/clean-clone-http.cjs',clone]);
run('RC_SMOKE','node',['tests/release/deployment-smoke.test.cjs'],clone);
run('RC_SETUP_BASELINE','node',['tests/release/setup-live.test.cjs'],clone);
run('SETUP_FRESH_FIX','node',['tests/release/setup-live.test.cjs']);
run('RC_PREFLIGHT_AFTER_BACKUP_INSTALL','php',['scripts/deploy-preflight.php'],clone);
// Composer launcher on Windows is a .bat; invoke through Git Bash, fixed args only.
run('PLATFORM_APPLICATION',bash,['-lc','composer --working-dir=application check-platform-reqs --no-dev'],clone);
run('PLATFORM_BACKUP',bash,['-lc','composer --working-dir=modules/backup check-platform-reqs --no-dev'],clone);
run('RC_ASSET_BUILD',bash,['-lc','npm run build'],clone);
for(const file of ['scripts/setup-live.sh','scripts/live-smoke-check.sh','scripts/live-diagnose.sh'])run('SYNTAX_'+file,bash,['-n',file]);
for(const file of ['tools/release-followup.cjs','tools/release-followup-checks.cjs','tests/release/clean-clone-http.cjs','tests/release/setup-live.test.cjs'])run('SYNTAX_'+file,'node',['--check',file]);
for(const file of ['modules/kt_integration_hub/tests/IntegrationErrorSanitizerTest.php','modules/kt_saas/tests/KtSaasContextRestorationTest.php','modules/kt_saas/tests/DmsSourceOfTruthCopySmokeTest.php','modules/kt_integration_hub/tests/DmsRoutePermissionGovernanceTest.php','modules/kt_integration_hub/tests/DmsRenderedSecurityNoSecretsTest.php','modules/kt_misa_amis_accounting/tests/MisaFoundationContractTest.php','modules/kt_misa_amis_accounting/tests/MisaStateMachineTest.php','modules/kt_misa_amis_accounting/tests/MisaSecurityStaticTest.php'])run(path.basename(file),'php',[file]);
result.finished_at=new Date().toISOString();result.summary={executed:checks.length,passed:checks.filter(x=>x.exit===0).length,failed:checks.filter(x=>x.exit!==0).length};
result.not_run=['configured CRM HTTP/CSRF/browser/tenant/company','DB install/upgrade','cron provider hooks','staging DB backup/restore','Linux/FPM/TLS'];
fs.writeFileSync(output,JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result.summary));process.exitCode=result.summary.failed?1:0;
