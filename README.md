# qiniu_backup
七牛空间所有文件备份到本地

include './back_up.php';

$backup = new Qiniu_backup();
$backup->backup();
