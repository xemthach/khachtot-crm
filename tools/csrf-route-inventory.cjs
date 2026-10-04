// STATIC ONLY: candidates for HTTP verification, not a runtime route/security certificate.
const fs=require('fs'),cp=require('child_process');
const out=process.argv[2];if(!out)throw Error('output required');
const files=cp.execFileSync('git',['ls-files','*.php'],{encoding:'utf8'}).trim().split('\n');
const rows=[];
for(const file of files.filter(f=>/^(application|modules)\//.test(f)&&f.includes('/controllers/'))){
 const s=fs.readFileSync(file,'utf8');const methods=[...s.matchAll(/public\s+function\s+(\w+)\s*\(/g)];
 for(let i=0;i<methods.length;i++){const m=methods[i],body=s.slice(m.index,methods[i+1]?.index||s.length);
 if(!/input->post|->(?:insert|update|delete|add)\s*\(|requirePost\(|^(?:create|save|delete|remove|cancel|update|retry|archive|purge|bulk)/.test(body+'\n'+m[1]))continue;
 rows.push({file,line:s.slice(0,m.index).split('\n').length,method:m[1],controller:((s.match(/class\s+(\w+)\s+extends\s+(\w+)/)||[])[1]),parent:((s.match(/class\s+(\w+)\s+extends\s+(\w+)/)||[])[2]),route_status:'CANDIDATE_NOT_HTTP_RESOLVED',auth:'PER_METHOD_AND_PARENT_REVIEW_REQUIRED',csrf:'POST_EXPECTED_UNLESS_CONFIG_OR_ACTIVE_MODULE_EXEMPT; NON_POST_NEEDS_METHOD_REVIEW',direct_post_check:/requirePost\(/.test(body),test_needed:'valid/missing/invalid token; auth and record scope; GET/PUT/PATCH/DELETE side effects',classification:'STATIC ONLY'});
 }
}
fs.writeFileSync(out,JSON.stringify({head:cp.execFileSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).trim(),status:'STATIC ONLY',limitations:'Regex candidate inventory; inheritance/dynamic hooks/routes can change behavior; not exhaustive proof of mutations',rows},null,2)+'\n');console.log('Static mutation candidates: '+rows.length);
