// Build dependency/API regression. --built also checks actual generated outputs.
const fs=require('node:fs'),assert=require('node:assert/strict'),path=require('node:path');
process.chdir(path.resolve(__dirname,'../..'));
const pkg=JSON.parse(fs.readFileSync('package.json','utf8'));
const lock=JSON.parse(fs.readFileSync('package-lock.json','utf8'));
for(const m of pkg.scripts.build.matchAll(/\bnode\s+([\w./-]+)/g))assert(fs.existsSync(m[1]),'missing build entrypoint: '+m[1]);
assert.equal(lock.packages['node_modules/webpack'].version,pkg.devDependencies.webpack);
assert.equal(typeof require('webpack/lib/SizeFormatHelpers').formatSize,'function');
assert.equal(require('webpack/lib/SizeFormatHelpers').formatSize(1024),'1 KiB');
const outputs=['assets/builds/app.js','assets/builds/tailwind.css','assets/builds/vendor-admin.js','assets/builds/common.js','modules/einvoice/assets/builds/codemirror.js','modules/einvoice/assets/builds/template.js'];
if(process.argv.includes('--built'))for(const file of outputs)assert(fs.statSync(file).size>0,file);
console.log('PASS build entrypoints, locked webpack API'+(process.argv.includes('--built')?', generated outputs':''));
