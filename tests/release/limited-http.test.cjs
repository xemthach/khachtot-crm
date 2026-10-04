const {spawn}=require('node:child_process'),assert=require('node:assert/strict'),net=require('node:net');
(async()=>{
  const socket=net.createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));
  const port=socket.address().port;await new Promise(r=>socket.close(r));
  const php=spawn('php',['-S',`127.0.0.1:${port}`,'tests/release/limited-http-router.php'],{cwd:require('node:path').resolve(__dirname,'../..'),stdio:'ignore',windowsHide:true});
  let requests=0;
  try {
    const base=`http://127.0.0.1:${port}`;let ready=false;
    for(let i=0;i<40;i++){try{await fetch(base);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
    assert(ready,'local policy harness start');
    for(const route of ['/misa/callback','/admin/kt_misa_amis_accounting','/api/dms/sso','/kt_integration_hub/cron/process_jobs','/kt_integration_webhooks/receive/dms/key']) {
      for(const method of ['GET','POST','PUT','DELETE']) {
        const res=await fetch(base+route+'?KT_RELEASE_PROFILE=standard&external_transport_allowed=1',{method});requests++;
        assert.equal(res.status,503,method+' '+route);assert.equal(res.headers.get('cache-control'),'no-store');
        assert.deepEqual(await res.json(),{success:false,code:'INTEGRATION_DISABLED_BY_RELEASE_PROFILE'});
      }
    }
    const res=await fetch(base+'/admin/authentication');assert.equal(res.status,200);assert.equal((await res.json()).harness_only,true);
    console.log(JSON.stringify({result:'PASS',denied_http_requests:requests,scope:'actual policy hook via loopback HTTP; NOT installed CRM/login/tenant/CSRF',business_network_calls:0}));
  } finally { php.kill(); }
})().catch(e=>{console.error(e.message);process.exitCode=1;});
