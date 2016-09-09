<?php

error_reporting(E_WARNING | E_ERROR | E_PARSE | E_STRICT);

//** 生成查询 SQL **/

require_once __DIR__.'/FetchCase.class.php';
use \Hongs\FetchCase;

$req = array(
    'status' => "abc'''def",
    'xyz' => '123',
    'rb' => 'title,company_*',
    'pn' => 2,
    'rn' => 30,
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

//** 生成更新 SQL **/

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

//** 列表关联 **/

require_once __DIR__.'/MergeMore.class.php';
use \Hongs\MergeMore;

// 手动关联
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
$mm->mixing($list2, $map, 'meeting_id', 'member_');
//$mm->pading($map, 'member_');
print_r($list1); echo PHP_EOL;

//** 数据校验 **/

require_once __DIR__.'/Validator.class.php';
use \Hongs\Validator;
use \Hongs\Validators;
use \Hongs\Wrongs;

$vd = (new Validator())
    ->addRule('name' , function($v, $c, $x) {
        return Validators\isStr($v, array('maxlength'=>10, 'minlength'=>1), $x);
    })
    ->addRule('email', function($v, $c, $x) {
        return Validators\isStr($v, array('pattern'=>'email'), $x);
    })
    ->isPrompt(true);
//  ->addRule('email', Validators\isStr, array('pattern'=>'email'));
$data = array(
    'name'  => 'lsjfljssdfsdfs',
    'email' => 'lsfs@ldsjf.com',
    'abc'   => 'xyz',
);
try {
    print_r($data);
    $cleanData = $vd->verify($data);
    print_r($cleanData);
} catch (Wrongs $ex) {
    print_r($ex->getErrors());
}

