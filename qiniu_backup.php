<?php
require_once './qiniu/rs.php';
require_once './qiniu/io.php';
require_once './qiniu/fop.php';

class Qiniu_backup{

    const SAVE_DIR = '/data';//文件保存目录
    const LOG_FILE = '/tmp/qiniu_error.log';//错误日志文件
    const QINIU_DATA_BUCKET = 'test';//bucket
    const DOMAIN = 'test.qiniudn.com';//访问域名
    const ACCESSKEY = 'WdfdsffdFmffmt';
    const SECRETKEY = 'psdffL16sefeds';

    public function __construct(){
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        Qiniu_SetKeys(self::ACCESSKEY, self::SECRETKEY);
    }

    /**
     * 备份七牛所有文件
     *
     * @param int $limit  每次下载文件数
     * @param bool $overwrite  是否下载已存在文件
     * @return string
     */ 
    public function backup($limit = 100, $overwrite = 'false'){
        $marker = null;

        do{
            $res = $this->listfiles($marker, $limit);

            $marker = isset($res['marker']) ? $res['marker'] : null;

            foreach($res['items'] as $v){

                $url = $this->qiniu_public_link($v['key']);

                $dir = explode('/',$v['key']);

                if(count($dir) > 0){
                    $file_name = end($dir);
                    array_pop($dir);
                    $str_dir = DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$dir).DIRECTORY_SEPARATOR;
                    $this->recursiveMkdirDirectory(self::SAVE_DIR . $str_dir);
                }else{
                    $file_name = $v['key'];
                    $str_dir = '';
                }

                if(!$overwrite && is_file(self::SAVE_DIR.$str_dir.$file_name)){
                    continue;
                }
                $rs = $this->save_data($url, self::SAVE_DIR.$str_dir.$file_name);
                if(!$rs){
                    error_log('error:' . self::SAVE_DIR.$str_dir.$file_name .'\n',3,self::LOG_FILE);
                }
            }
        }while($marker);

        echo 'finish';
    }

    function http_get_data($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        ob_start();
        curl_exec( $ch );
        $return_content = ob_get_contents();
        ob_end_clean();

        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $return_content;
    }

    function save_data($url,$target_file){
        $return_content = $this->http_get_data($url);
        $fp= @fopen($target_file,"w");
        return fwrite($fp,$return_content);
    }

    public function listfiles($marker = null, $limit=1000, $prefix = ''){
        $client = new Qiniu_MacHttpClient(null);
        $sign = "/list?bucket=".self::QINIU_DATA_BUCKET."&marker={$marker}&limit={$limit}&prefix={$prefix}\n";//不能少了backslash + n 否则签名不正确
        $baseUrl = "http://rsf.qbox.me";
        $param['auth'] = $client->Mac->Sign($sign);
        $return = $this->request_post($baseUrl.$sign,$param);
        $res = json_decode($return, true);
        return $res;
    }

    function request_post($url = '', $param = ''){
        if (empty($url) || empty($param)) {return false; }
        $headers = array(
            "Content-type: application/x-www-form-urlencoded",
            "Authorization: QBox ".$param['auth']
        );
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$url);//抓取指定网页
        // curl_setopt($ch, CURLOPT_HEADER, $headers);//false , don't return header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);
        return $data;
    }

    function qiniu_public_link($key){
        $domain = self::DOMAIN;
        $baseUrl = 'http://'.($domain.'/'.$key);
        return $baseUrl;
    }

    function recursiveMkdirDirectory($dir, $mode = 0777){
        if (!is_dir($dir)) {
            if (!$this->recursiveMkdirDirectory(dirname($dir))) {
                return false;
            }
            if (!mkdir($dir, 0777)) {
                return false;
            }
        }
        return true;
    }
}

