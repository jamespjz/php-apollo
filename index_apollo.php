<?php
require '../vendor/autoload.php'; // autoload
use Org\Multilinguals\Apollo\Client\ApolloClient;

$meta = [
	'DEV' => 'http://dev.apollo-configservice.service.consul:8080',
	'FAT' => 'http://test.apollo-configservice.service.consul:8080',
	'UAT' => 'http://sbx.apollo-configservice.service.consul:8080',
	'PRO' => 'http://apollo.cn-shenzhen-internal.goodcang.net:8080',
];


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$my_env = $dotenv->load();

//定义配置保存目录
define('SAVE_DIR', getenv('SAVE_DIR'));
//文件保存后缀名
define('SUFFIX_NAME', getenv('SUFFIX_NAME'));
//ini文件的session
define('SESSION', getenv('SESSION'));
//指定env模板和文件
define('ENV_DIR', __DIR__.DIRECTORY_SEPARATOR);
define('ENV_TPL', ENV_DIR.DIRECTORY_SEPARATOR.'.env_tpl.php');
define('ENV_FILE', ENV_DIR.DIRECTORY_SEPARATOR.'.env-bak');

//specify address of apollo server
$env = getenv('ASPNETCORE_ENVIRONMENT');
switch($env){
	case 'dev':
		$configServer = $meta['DEV'];
		break;
	case 'test':
		$configServer = $meta['FAT'];
		break;
	case 'sbx':
		$configServer = $meta['UAT'];
		break;
	case 'qas':
		$configServer = $meta['UAT'];
		break;
	case 'live':
		$configServer = $meta['PRO'];
		break;
	default:
		$configServer = $meta['DEV'];
		break;
}
$server = !empty(getenv('CONFIG_SERVER'))?getenv('CONFIG_SERVER'):$configServer;

//specify your appid at apollo config server
$appid = getenv('APPID');// get appid from env-bak

//specify namespaces of appid at apollo config server
$namespaces = getenv('NAMESPACE');
$namespaces = explode(',', $namespaces);

$apollo = new ApolloClient($server, $appid, $namespaces);

$addr = exec("ifconfig eth0 | grep 'inet addr' | awk '{ print $2}' | awk -F: '{print $2}'",$ser_IP);
if ($clientIp = !empty(getenv('CLIENTIP'))?getenv('CLIENTIP'):$addr) {
    $apollo->setClientIp($clientIp);
}

$apollo->save_dir = SAVE_DIR;
$apollo->suffix_name = SUFFIX_NAME;
$apollo->session = SESSION;

ini_set('memory_limit','128M');

$params = [];

if(count($namespaces)>1){
	$apollo->pullConfigBatch($namespaces);
}else{
	$namespaces = implode(",", $namespaces);
	$apollo->pullConfig($namespaces);
}

//$url = $apollo->getConfigFile('company');
//$aa = include $url;



//定义apollo配置变更时的回调函数，动态异步更新.env-bak
$callback = function () {
    $list = glob(SAVE_DIR.DIRECTORY_SEPARATOR.'*');
    $apollo = [];
    foreach ($list as $l) {
        $config = require $l;
        if (is_array($config) && isset($config)) {
            $apollo = array_merge($apollo, $config);
        }
    }
    if (!$apollo) {
        throw new Exception('Load Apollo Config Failed, no config available');
    }
    ob_start();
    include ENV_TPL;
    $env_config = ob_get_contents();
    ob_end_clean();
	echo PHP_EOL."*****".PHP_EOL;var_dump(getenv(ENV_TPL), getenv(ENV_FILE), $env_config);
    file_put_contents(ENV_FILE, $env_config);
};

$restart = false; //失败自动重启
do {
    $error = $apollo->start($callback);
    if ($error) echo('error:'.$error."\n");
}while($error && $restart);
