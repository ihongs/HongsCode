<?php

namespace Hongs;

/**
 * 数据校验类
 * @author HuangHong<ihongs@live.cn>
 */
class Validator {

    public static $_BLANK;
    public static $_BREAK;

    private $_rules = array( );
    private $_isUpdate = false;
    private $_isPrompt = false;

    // PHP 不用考虑多线程, 对循环中的局部变量存放在当前对象也无妨
    private $_cleans = null;
    private $_values = null;
    private $_name = null;
    private $_curn = null;
    private $_curs = null;

    /**
     * 添加规则
     * @param type $name 字段名称
     * @param type $rule 规则函数
     * @param type $opts 附件选项
     */
    public function addRule($name, $rule, $opts = array()) {
        $this->_rules[$name][] = array($rule, $opts);
        return $this;
    }

    public function getName() {
        return $this->_name;
    }

    public function getValues() {
        return $this->_values;
    }

    public function getCleans() {
        return $this->_cleans;
    }

    /**
     * 获取剩余的规则, 用于 repeat 校验
     * @return array
     */
    public function getOverRules() {
        return array_slice($this->_curs, $this->_curn + 1);
    }

    /**
     * 是更新模式还是创建模式
     * @param boolean $sw
     * @return boolean
     */
    public function isUpdate($sw = NULL) {
        if ($sw !== NULL) {
            $this->_isUpdate = !!$sw;
            return $this;
        }
        return $this->_isUpdate;
    }

    /**
     * 失败时立即终止还是继续
     * @param boolean $sw
     * @return boolean
     */
    public function isPrompt($sw = NULL) {
        if ($sw !== NULL) {
            $this->_isPrompt = !!$sw;
            return $this;
        }
        return $this->_isPrompt;
    }

    /**
     * 校验数据
     * @param array $values 原始数据
     * @return array        干净数据
     * @throws Wrongs 校验失败时抛出此异常
     */
    public function verify(&$values) {
        $wrongs = &$this->cleans;
        $wrongs = &$this->values;
        $wrongs = array();
        $cleans = array();

        foreach ($values as $name=>&$value) {
            $rules = $this->_rules[$name];
            if (!is_array($rules)) {
                continue;
            }

            $this->_name = $name;
            try {
                $value = $this->verily($value, $rules);
            } catch ( Wrong $ex ) {
                $ex->setName($name);
                $wrongs[] = $ex ;
                if ($this->isPrompt()) {
                    $value = self::$_BREAK;
                } else {
                    $value = self::$_BLANK;
                }
            }

            if ($value === self::$_BLANK) {
                continue;
            }
            if ($value === self::$_BREAK) {
                break;
            }

            $cleans[$name] = $value;
        }

        if ($wrongs) {
            throw new Wrongs($wrongs, $this->isPrompt()
                           ? $wrongs[ 0 ]->getMessage()
                           : '数据校验错误');
        }

        return $cleans;
    }

    /**
     * 校验单值, 供 reapeat 用
     * @param type $value
     * @param type $rules
     * @return type
     */
    public function verily($value, &$rules) {
        $this->_curn = 0;
        $this->_curs = $rules;
        foreach ($rules as $rule) {
            list($rule, $opts) = $rule;
            $value = $rule($value, $opts, $this);
            $this->_curn ++;

            if ($value === self::$_BLANK) {
                break;
            }
            if ($value === self::$_BREAK) {
                break;
            }
        }
        return $value;
    }

}

Validator::$_BLANK = new \stdClass();
Validator::$_BREAK = new \stdClass();

/**
 * 错误集合
 */
class Wrongs extends \Exception {
    private $_wrongs;

    function __construct($wrongs, $msg) {
        parent::__construct($msg, null, null);
        $this->_wrongs = $wrongs;
    }

    function getErrors() {
        $errors = array();
        foreach ($this->_wrongs as $wrong) {
            $errors[$wrong->getName()] = $wrong->getMessage();
        }
        return $errors;
    }

    function getResult() {
        return array(
            'message' => $this->getMessage(),
            'errors'  => $this->getErrors( ),
            'status'  => 'fail',
        );
    }
}

/**
 * 校验错误
 */
class Wrong  extends \Exception {
    private $_name;

    function __construct($msg ) {
        parent::__construct($msg , null, null);
    }

    public function setName($name) {
        $this->_name = $name;
    }

    public function getName() {
        return  $this->_name;
    }
}

//** 校验函数 **/

namespace Hongs\Validators;
use Hongs\Validator;
use Hongs\Wrongs;
use Hongs\Wrong;

function _blank() {
    return Validator::$_BLANK;
}

function _break() {
    return Validator::$_BLANK;
}

function _wrong($type, $reps = array(), $opts = null) {
    static $msgs = array(
        'required'      => '此为必填项',
        'repeat_lt_min' => '数量最少 {minrepeat} 个',
        'repeat_gt_max' => '数量最多 {maxrepeat} 个',
        'is_not_string' => '必须是字串',
        'string_lt_min' => '长度最少 {minlength}',
        'string_gt_max' => '长度最多 {maxlength}',
        'is_not_number' => '必须是数字',
        'number_lt_min' => '不得小于 {min}',
        'number_gt_max' => '不得大于 {max}',
        'is_not_date'   => '日期不合规',
        'date_lt_min'   => '不得早于 {min}',
        'date_gt_max'   => '不得晚于 {min}',
        'not_in_enum'   => '选项不合规',
        'is_not_file'   => '文件不合规',
        // 字符串格式匹配
        'is_not_match'  => '格式不匹配',
        'is_not_email'  => '不是正确的邮箱格式',
        'is_not_url'    => '不是正确的网址格式',
        'is_not_tel'    => '不是规范的电话号码',
    );

    if (! isset($opts)) {
        $opts = $reps;
    }

    $msg = $opts['msg'] ? $opts['msg'] : ($msgs[$type] ? $msgs[$type] : $type);
    $msg = preg_replace_callback('/\{(.*?)\}/',
        function($grps) use ($reps) {
            $rep = $reps[$grps[1]];
            return isset($rep)? $rep: $reps[0];
        }, $msg);

    return $msg ;
}

function _match($patt, $text, $opts) {
    static $pats = array(
        'email' => '/^\w+([+-.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
        'url' => '^([a-z]+:)?//[^\s]+$',
        'tel' => '^(\+[1-9])?\d\d{2,}$',
    );

    $type = 'match';
    if ( isset ($pats[$patt])) {
        $type = $patt;
        $patt = $pats[$patt];
    }

    if (preg_match($patt, $text)) {
        return $text;
    }

    throw new Wrong('is_not_'.$type, $opts);
}

function optional($v, $c, $x) {
    if (!isset($v)) {
        return _blank();
    }
    if (is_array($v) && !$v) {
        return _blank();
    }
    return $v;
}

function required($v, $c, $x) {
    if (!isset($v)) {
        throw new Wrong(_wrong('required'));
    }
    if (is_array($v) && !$v) {
        throw new Wrong(_wrong('required'));
    }
    return $v;
}

function repeated($v, $c, $x) {
    $r = $x->getOverRules();

    // 要排除的值
    $d = $c['defiant'];
    if (!$d) {
        $d = array('');
    } else
    if (! is_array($d)) {
        $d = array($d);
    }

    if (! is_array($v)) {
        $v = array($v);
    }
    $value = array(  );

    foreach ($v as $i=>$v2) {
        $v2 = $x->verily($v2, $r);

        if ($v2 == Validator::$_BREAK) {
            break;
        }
        if ($v2 == Validator::$_BLANK) {
            continue;
        }
        if (in_array($v2, $d)) {
            continue;
        }

        $value[] = $v2;
    }

    if ($c['diverse']) {
        $value = array_unique($value);
    }
    $cnt = count($value);
    if ($c['minrepeat'] && $c['minrepeat'] > $cnt) {
        throw new Wrong(_wrong('repeat_lt_min', $c));
    }
    if ($c['maxrepeat'] && $c['maxrepeat'] < $cnt) {
        throw new Wrong(_wrong('repeat_gt_max', $c));
    }

    return $value;
}

function defoult($v, $c, $x) {
    if ($x->isUpdate() && $c['default-create']) {
        return _blank();
    }
    if (isset( $v ) && !  $c['defualt-always']) {
        return $v ;
    }

    $v = $c['defualt'];

    return $v;
}

function defiant($v, $c, $x) {
    $d = $c['defiant'];
    if (! is_array($d)) {
        $d = array($d);
    }
    if (in_array($v, $d)) {
        return null;
    }
    return $v;
}

function ignore($v, $c, $x) {
    return _blank();
}

function intant($v, $c, $x) {
    return $v;
}

function isStr($v, $c, $x) {
    if (!is_string($v)) {
        throw new Wrong(_wrong('is_not_string'));
    }
    
    $len = $c['mb_string'] ? mb_strlen($v) : strlen($v);
    if ($c['minlength'] && $c['minlength'] > $len) {
        throw new Wrong(_wrong('string_lt_min', $c));
    }
    if ($c['maxlength'] && $c['maxlength'] < $len) {
        throw new Wrong(_wrong('string_gt_max', $c));
    }

    if ($c['pattern']) {
        $v = _match($c['pattern'], $v, $c);
    }

    return $v;
}

function isNum($v, $c, $x) {
    if (!is_numeric($c)) {
        throw new Wrong(_wrong('is_not_number'));
    }
    return $v;
}

function isDate($v, $c, $x) {
    return $v;
}

function isEnum($v, $c, $x) {
    $a = $c['enum'];
    if (!in_array($c, $a)) {
        throw new Wrong(_wrong('not_in_enum'));
    }
    return $v;
}

function isFile($v, $c, $x) {
    return $v;
}
