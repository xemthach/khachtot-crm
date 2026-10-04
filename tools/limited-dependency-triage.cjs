// Classify every entry from preserved audit, without claiming exploitability.
const fs=require('node:fs'),cp=require('node:child_process'),path=require('node:path');
process.chdir(path.resolve(__dirname,'..'));
const prior=JSON.parse(fs.readFileSync('docs/reports/CRM_RC_BUILD_CLOSURE_20261004_EVIDENCE.json'));
const bash=process.env.BASH_EXE||'D:/laragon/bin/git/bin/bash.exe';
const names=prior.audit.packages.map(x=>x.name);
if(names.some(x=>!/^[a-z0-9@/._-]+$/.test(x)))throw Error('unexpected package name');
const r=cp.spawnSync(bash,['-lc','npm explain '+names.join(' ')+' --json'],{encoding:'utf8',maxBuffer:16e6,timeout:120000});
if(r.status!==0)throw Error('npm explain failed');
const explained=JSON.parse(r.stdout);
const devserver=new Set(['body-parser','express','http-proxy-middleware','node-forge','selfsigned','sockjs','webpack-dev-middleware','webpack-dev-server','websocket-driver']);
const uncertain=new Set(['browserify-sign','create-ecdh','crypto-browserify','elliptic','node-libs-browser','uuid']);
const rows=prior.audit.packages.map(p=>{
 let surface=devserver.has(p.name)?'BUILD_DEV_SERVER':uncertain.has(p.name)?'BUNDLE_REACHABILITY_NOT_PROVEN':'BUILD_INPUT_TOOLCHAIN';
 let evidence='Actual npm explain dependents; package participates in build tree. Not a PHP production server dependency. Build/input compromise remains relevant.';
 if(p.name==='bootstrap'){surface='BROWSER_ASSET_RELEVANT';evidence='webpack.mix.js:43,89 copies assets/plugins/bootstrap; assets_helper.php:29,44 serves it. npm package advisory cannot alone prove bundled file identity/exploitability.';}
 return {...p,surface,evidence,dependency_instances:explained.filter(x=>x.name===p.name).map(x=>({version:x.version,location:x.location,dependents:x.dependents})),
   mitigation:surface==='BROWSER_ASSET_RELEVANT'?'Review shipped Bootstrap version, data-attribute/XSS sinks and browser tests; no automatic major-version swap.':
    devserver.has(p.name)?'Never expose npm dev server; build unprivileged with no secrets, no node_modules in runtime artifact; verify no dev-server service at release.':
    'Build only reviewed source/lock in unprivileged isolated builder; review advisory input preconditions; no untrusted SVG/CSS/globs; patch dependencies in a separate tested change.',
   closure:'NOT_CLOSED_BY_DEVDEPENDENCY_CLASSIFICATION'};
});
const out={at:new Date().toISOString(),source_audit:prior.finished_at,source_count:names.length,npm_explain_exit:r.status,rows,
 limitations:['39 entries are not 39 demonstrated exploitable flaws','All advisories/URLs retained from official registry response','Browser bundle identity/sinks and exploit tests NOT VERIFIED','No dependency update or security PASS inferred']};
fs.writeFileSync('docs/reports/CRM_LIMITED_DEPENDENCY_TRIAGE_20261004.json',JSON.stringify(out,null,2)+'\n');
console.log(JSON.stringify({entries:rows.length,with_dependency_evidence:rows.filter(x=>x.dependency_instances.length).length}));
