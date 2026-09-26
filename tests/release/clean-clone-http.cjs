// Real PHP front-controller smoke before installation. Does not certify authenticated CRM.
const fs=require('node:fs'),cp=require('node:child_process'),http=require('node:http'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(process.argv[2]||'');
assert(fs.existsSync(path.join(root,'.git')),'clone required');
assert(!fs.existsSync(path.join(root,'application/config/app-config.php')),'refuse configured application');
assert(!fs.existsSync(path.join(root,'installed/database.sql')),'only missing-schema reproduction');
(async()=>{
 const reserve=http.createServer();await new Promise(r=>reserve.listen(0,'127.0.0.1',r));const port=reserve.address().port;await new Promise(r=>reserve.close(r));
 const child=cp.spawn('php',['-S',`127.0.0.1:${port}`,'-t',root],{cwd:root,windowsHide:true,stdio:['ignore','pipe','pipe']});
 let errors='';child.stderr.on('data',b=>errors+=b);child.stdout.on('data',()=>{});
 const tests=[];
 try{
  let ready=false;for(let i=0;i<40;i++){try{await fetch(`http://127.0.0.1:${port}/`);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
  assert(ready,'PHP listener failed');
  for(const [url,marker] of [['/','Perfex CRM not installed'],['/installed/','<form']]){
   const response=await fetch(`http://127.0.0.1:${port}${url}`);const body=await response.text();
   tests.push({url,status:response.status,expected_marker:marker,marker_found:body.includes(marker),fatal:/Fatal error|Uncaught/.test(body),bytes:Buffer.byteLength(body)});
  }
  console.log(JSON.stringify({scope:'UNCONFIGURED_FRONT_CONTROLLER_ONLY',tests,no_config:true,no_schema:true}));
  assert(tests.every(t=>t.status===200&&t.marker_found&&!t.fatal),'expected unconfigured root and real installer form');
 }finally{child.kill();await new Promise(r=>child.once('close',r));}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
