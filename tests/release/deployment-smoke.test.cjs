// Executes the real shell entrypoint against a loopback HTTP fixture. No CRM DB.
const { spawn } = require('node:child_process');
const http = require('node:http');
const path = require('node:path');
const assert = require('node:assert/strict');
const bash = process.env.BASH_EXE || (process.platform === 'win32' ? 'D:/laragon/bin/git/bin/bash.exe' : 'bash');
function run(url) {
  return new Promise(resolve => {
    const child = spawn(bash, ['scripts/live-smoke-check.sh', url], { cwd: path.resolve(__dirname, '../..'), env: process.env });
    let output = '';
    child.stdout.on('data', b => output += b);
    child.stderr.on('data', b => output += b);
    child.on('close', code => resolve({ code, output }));
    child.on('error', e => resolve({code: -1, output: e.message}));
  });
}
(async () => {
  let status = 200;
  const server = http.createServer((req, res) => { res.writeHead(status); res.end('fixture'); });
  await new Promise(r => server.listen(0, '127.0.0.1', r));
  const url = `http://127.0.0.1:${server.address().port}`;
  const results = [];
  try {
    for (const [code, shouldSucceed] of [[200,true],[302,true],[404,false],[500,false]]) {
      status = code;
      const actual = await run(url);
      results.push({ http:code, exit:actual.code, pass:(actual.code === 0) === shouldSucceed });
    }
  } finally { await new Promise(r => server.close(r)); }
  const offline = await run(url);
  results.push({http:'connection refused',exit:offline.code,pass:offline.code !== 0});
  console.log(JSON.stringify(results, null, 2));
  assert.ok(results.every(x => x.pass), 'Smoke must fail on HTTP/connection errors');
})().catch(e => { console.error(e.message); process.exitCode=1; });
