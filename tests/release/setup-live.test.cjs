const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const assert = require('node:assert/strict');
const source = path.resolve(__dirname,'../..');
const fixture = fs.mkdtempSync(path.join(os.tmpdir(),'crm-setup-test-'));
const bash = process.env.BASH_EXE || (process.platform==='win32'?'D:/laragon/bin/git/bin/bash.exe':'bash');
try {
 fs.mkdirSync(path.join(fixture,'scripts'));
 for(const name of ['setup-live.sh','deploy-preflight.php'])fs.copyFileSync(path.join(source,'scripts',name),path.join(fixture,'scripts',name));
 const run=()=>spawnSync(bash,['scripts/setup-live.sh'],{cwd:fixture,encoding:'utf8',timeout:30000});
 const missing=run();assert.notEqual(missing.status,0);assert.equal(fs.existsSync(path.join(fixture,'uploads')),false);
 console.log('PASS missing dependencies fail before runtime/config writes');
 for(const name of ['application/vendor/autoload.php','modules/backup/vendor/autoload.php','modules/einvoice/vendor/autoload.php','modules/openai/vendor/autoload.php','modules/surveys/vendor/autoload.php','index.php','application/config/database.php','application/config/config.php','application/config/app-config.sample.php']) {
   fs.mkdirSync(path.dirname(path.join(fixture,name)),{recursive:true});fs.writeFileSync(path.join(fixture,name),'<?php // test fixture\n');
 }
 const first=run();assert.equal(first.status,0,first.stderr);
 const config=path.join(fixture,'application/config/app-config.php');
 fs.writeFileSync(config,'<?php // existing config must survive\n');
 const before=fs.readFileSync(config,'utf8');const second=run();assert.equal(second.status,0,second.stderr);
 assert.equal(fs.readFileSync(config,'utf8'),before);
 console.log('PASS setup rerun preserves existing configuration; no CRM DB used');
} finally {
 if(path.dirname(fixture)!==os.tmpdir()||!path.basename(fixture).startsWith('crm-setup-test-'))throw new Error('Unsafe fixture cleanup target');
 fs.rmSync(fixture,{recursive:true,force:true});
}
