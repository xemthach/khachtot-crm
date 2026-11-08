<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/KtIntegrationSignedRequestValidator.php');
class DmsWorkspaceRegistryClient
{
    private const PATH='/api/v1/integration/workspaces';
    public function fetch(array $tenant,array $connection): array
    {
        $settings=kt_integration_hub_json_decode((string)($connection['settings_json']??''),[]);
        $base=kt_integration_hub_dms_control_plane_base_url($tenant,$connection)?:trim((string)($settings['base_url']??''));
        $token=trim((string)($connection['public_key']??'')); $secret=kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted']??''); $code=strtoupper(trim((string)($tenant['tenant_code']??'')));
        if($base===''||$token===''||$secret===''||$code==='') throw new RuntimeException('Kết nối registry workspace chưa đầy đủ.');
        $ts=(string)round(microtime(true)*1000); $rid='ws-reg-'.bin2hex(random_bytes(8)); $sig=(new KtIntegrationSignedRequestValidator())->sign('GET',self::PATH,$ts,$code,'',$secret);
        $c=curl_init(); curl_setopt_array($c,[CURLOPT_URL=>rtrim($base,'/').self::PATH,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json','X-Timestamp: '.$ts,'X-Signature: '.$sig,'X-Tenant-Code: '.$code,'X-Request-Id: '.$rid],CURLOPT_SSL_VERIFYPEER=>!str_ends_with((string)parse_url($base,PHP_URL_HOST),'.test')]);
        $body=curl_exec($c); $status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c); $json=json_decode((string)$body,true);
        if($status!==200||!is_array($json)||empty($json['success'])||!is_array($json['data']['workspaces']??null)) throw new RuntimeException('Không thể làm mới registry workspace từ Salesrep.');
        return $json['data']['workspaces'];
    }
}
