<?php

error_reporting(E_WARNING | E_ERROR | E_PARSE | E_STRICT);

define('PWD', __DIR__.'/../../main/hongs');

//** 生成查询 SQL **/

require_once PWD.'/FetchCase.class.php';
use hongs\FetchCase;

$req = array(
    'status' => "jlsdf```abc'''def",
    'xyz' => '123',
    'rb' => 'title,company_*',
    'pn' => 2,
    'rn' => 30,
    'ob' => 'title',
    'wd' => 'last day',
);
$sql = (new FetchCase())
    ->from  ('a_meeting_base_info')
    ->join  ('a_company_base_info', 'company', '~.id = ^.company_id', 'INNER')
    ->allow (array('meeting_id', 'title', 'summary', 'status', 'company.id', 'company.title', 'company.summary'))
    ->allow (array('title', 'summary'), 'wd')
    ->allow (array('-summary'), 'ob')
    ->trans ($req)
    ->toSelect();
echo $sql.PHP_EOL.PHP_EOL;

//** 操作插入语句 **/

$req = array(
    'title' => 'abc',
    'summary' => 'def',
    'id' => array('123', '456'),
);
$fc  = (new FetchCase())
    ->from  ('a_meeting_base_info')
    ->allow (array('meeting_id', 'title', 'summary'))
    ->allow (array('-meeting_id'), 'saves');
$sql = $fc
    ->saves ($req)
    ->toInsert();
echo $sql.PHP_EOL.PHP_EOL;
$sql = $fc
    ->saves ($req)
    ->where (array('meeting_id'=>$req['id']))
    ->toUpdate();
echo $sql.PHP_EOL.PHP_EOL;
$sql = $fc
    ->where (array('meeting_id'=>$req['id']))
    ->toDelete();
echo $sql.PHP_EOL.PHP_EOL;

//** 手动关联 **/

require_once PWD.'/MergeMore.class.php';
use \hongs\MergeMore;

$list1 = array(
    array('meeting_id'=>'123', 'title'=>'G20 峰会'),
    array('meeting_id'=>'456', 'title'=>'世贸协定'),
    array('meeting_id'=>'789', 'title'=>'军事会议'),
);

$mm  = new MergeMore($list1);
$map = $mm->maping('meeting_id');

$sql = (new FetchCase())
    ->from  ('a_meeting_member_info2')
    ->join  ('a_user_base_info', 'user', '~.user_id = ^.user_id')
    ->field ('_.meeting_id, _.status, user.user_id, user.name AS user_name')
    ->where (array('meeting_id'=>array_keys($map)))
    ->toSelect();
echo $sql.PHP_EOL.PHP_EOL;
$list2 = array(
    array('meeting_id'=>'123', 'status'=>'1', 'user_id'=>'111', 'user_name'=>'张三'),
    array('meeting_id'=>'789', 'status'=>'0', 'user_id'=>'222', 'user_name'=>'李四'),
    array('meeting_id'=>'123', 'status'=>'1', 'user_id'=>'333', 'user_name'=>'王五'),
    array('meeting_id'=>'123', 'status'=>'1', 'user_id'=>'333', 'user_name'=>'赵六'),
    array('meeting_id'=>'789', 'status'=>'1', 'user_id'=>'333', 'user_name'=>'田七'),
);

$mm->mixing($list2, $map, 'meeting_id', 'member.');
$mm->pading($map, 'member.', array ( ));
$mm->mixing($list2, $map, 'meeting_id', 'member_');
$mm->pading($map, 'member_', $list2[0]);
print_r($list1); echo PHP_EOL;

//** 数据校验 **/

require_once PWD.'/Validator.class.php';
use hongs\Validator;
use hongs\validator\Wrong;
use hongs\validator\Wrongs;

$vd = (new Validator())
    ->addRule('name' , 'isStr', array('maxlength'=>10, 'minlength'=>1,'label'=>'名字','error'=>'{label}长度必须为{minlength}到{maxlength}个字符'))
    ->addRule('email', 'isStr', array('pattern'=>'email','lable'=>'邮箱'))
    ->addRule('xyz'  , array('required', 'isStr'), array('pattern'=>'tel','label'=>'xyz'))
    ->addRule('abc'  , function($value, $opts, $vali) {
        throw new Wrong("我说你错就是错");
    })
    ->isPrompt(false);
$data = array(
    'name'  => 'lsjfljssdfsdfs',
    'email' => 'lsfs@ldsjf.com',
    'abc'   => '123',
);
try {
    print_r($data);
    $cleanData = $vd->verify($data);
    print_r($cleanData);
} catch (Wrongs $ex) {
    print_r($ex->getErrors( ));
}
