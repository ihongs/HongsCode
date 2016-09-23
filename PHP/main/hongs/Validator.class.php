<?php

namespace hongs;

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
     * @param string $name 字段名称
     * @param function|string|array $rule 规则函数
     * @param array  $opts 附件选项
     */
    public function addRule($name, $rule, $opts = array()) {
        if (  !  is_array($rule)) {
            $rule = array($rule);
        }
        foreach ($rule as $func)  {
            $this->_rules[$name][] = array($func, &$opts);
        }
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
        $this->_values = $values;
        $this->_cleans = array();
        $cleans = &$this->cleans;
        $wrongs = array();

        foreach ($this->_rules as $name=>&$rules) {
            $value = $values[$name];
            $this->_name  =  $name ;

            try {
                $value = $this->verily($value, $rules);
            } catch ( \hongs\validator\Wrong $ex) {
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
            throw new \hongs\validator\Wrongs(
                    $wrongs, $this->isPrompt()
                  ? $wrongs[ 0 ]->getMessage()
                  : \hongs\validator\_wrong ('invalid'));
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
        foreach ($rules as $rule ) {
            list($rule, $opts) = $rule;
            if  (is_string($rule)) {
                if (substr($rule, 0, 1) != '\\') {
                $rule  = '\\hongs\\validator\\'.$rule ;
                }
                $value = call_user_func( $rule, $value, $opts, $this );
            } else {
                $value = $rule ( $value, $opts, $this);
            }
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

namespace hongs\validator;
use hongs\Validator;

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

/**
 * 返回空值, 将被忽略
 */
function _blank() {
    return Validator::$_BLANK;
}

/**
 * 中止校验, 立即退出
 */
function _break() {
    return Validator::$_BREAK;
}

/**
 * 错误消息, 可在 $msgs 里增加消息, 需要国际化亦可修改这里
 * @param string $type 错误类型
 * @param array  $reps 消息选项
 * @param array  $opts 外部选项
 */
function _wrong($type, $reps = null, $opts = null) {
    static $msgs = array(
        'invalid'       => '数据校验错误',
        'required'      => '{label} 为必填项',
        'repeat_lt_min' => '{label} 数量最少 {minrepeat} 个',
        'repeat_gt_max' => '{label} 数量最多 {maxrepeat} 个',
        'is_not_string' => '{label} 必须是字串',
        'string_lt_min' => '{label} 长度最少 {minlength}',
        'string_gt_max' => '{label} 长度最多 {maxlength}',
        'is_not_number' => '{label} 必须是数字',
        'number_lt_min' => '{label} 不得小于 {min}',
        'number_gt_max' => '{label} 不得大于 {max}',
        'is_not_date'   => '{label} 日期不合规',
        'date_lt_min'   => '{label} 不得早于 {min}',
        'date_gt_max'   => '{label} 不得晚于 {min}',
        'not_in_enum'   => '{label} 选项不合规',
        'is_not_file'   => '{label} 文件不合规',
        // 字符串格式匹配
        'is_not_match'  => '{label} 格式不匹配',
        'is_not_email'  => '{label} 不是正确的邮箱格式',
        'is_not_url'    => '{label} 不是正确的网址格式',
        'is_not_tel'    => '{label} 不是规范的电话号码',
    );

    if (! isset($reps)) {
        $reps = array();
    }
    if (! isset($opts)) {
        $opts = $reps;
    }

    $msg = $opts['error'] ? $opts['error']
       : ( $msgs[ $type ] ? $msgs[ $type ] : $type );
    $msg = preg_replace_callback('/\{(.*?)\}/',
        function($grps) use ($reps) {
            $grp = $grps[1];
            if (isset($reps[$grp])) {
               return $reps[$grp];
            }
            if (isset($opts[$grp])) {
               return $opts[$grp];
            }
            return $grp;
        }, $msg);

    return $msg ;
}

/**
 * 模式匹配, 可在 $pats 里吗增加模式
 * @param string $patt 模式正则或预置类型
 * @param string $text 测试文本
 * @param array  $opts 外部选项
 */
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

/**
 * 可选的, 未给值将跳过, 注意: 不会跳过空串
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function optional($v, $c, $x) {
    if (!isset($v)) {
        return _blank();
    }
    if (is_array($v) && !$v) {
        return _blank();
    }
    return $v;
}

/**
 * 必要的, 与可选的相反, 未给值将会抛出错误
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function required($v, $c, $x) {
    if (!isset($v)) {
        throw new Wrong(_wrong('required', $c));
    }
    if (is_array($v) && !$v) {
        throw new Wrong(_wrong('required', $c));
    }
    return $v;
}

/**
 * 重复的, 会将此后的校验方法应用于每一项值
 * 选项 defiant 为需要排除的值
 * 选项 diverse 标识需要去重复
 * 选项 minrepeat 为最少数量
 * 选项 maxrepeat 为最大数量
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function repeated($v, $c, $x) {
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

    $r = $x->getOverRules();

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

/**
 * 默认值, 对那些未给定值的字段设置默认值
 * 选项 default 为给定的默认值
 * 选项 default-create 标识添加时写入
 * 选项 default-always 标识总是要写入
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
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

/**
 * 排除值, 对那些方便前端占位的值作排除
 * 选项 default 为给定的排除值, 数组则排除多个
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
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

/**
 * 忽略值, 直接抛弃
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function ignore($v, $c, $x) {
    return _blank();
}

/**
 * 特殊值, 直接通过
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function intant($v, $c, $x) {
    return $v;
}

/**
 * 字串校验
 * 选项 minlength 为最小长度
 * 选项 maxlength 为最大长度
 * 选项 pattern 为匹配模式, _match 函数的 $pats 里定义好的模式只需给出类型名
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function isStr($v, $c, $x) {
    if (!is_string($v)) {
        throw new Wrong(_wrong('is_not_string', $c));
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

/**
 * 数字校验
 * 选项 min 为最小取值
 * 选项 max 为最大取值
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function isNum($v, $c, $x) {
    if (!is_numeric($v)) {
        throw new Wrong(_wrong('is_not_number', $c));
    }

    if ($c['min'] && $c['min'] > $v) {
        throw new Wrong(_wrong('number_lt_min', $c));
    }
    if ($c['max'] && $c['max'] < $v) {
        throw new Wrong(_wrong('number_gt_max', $c));
    }

    return $v;
}

/**
 * 日期校验
 * 选项 min 为最小日期, 单位为秒
 * 选项 max 为最大日期, 单位为秒
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function isDate($v, $c, $x) {
    if (!is_numeric($v)) {
        $v = strtotime ($v);
        if ($v === false || $v < 0 ) {
            throw new Wrong(_wrong('is_not_date', $c));
        }
    }

    if ($c['min'] && $c['min'] > $v) {
        throw new Wrong(_wrong('date_lt_min', $c));
    }
    if ($c['max'] && $c['max'] < $v) {
        throw new Wrong(_wrong('date_lt_min', $c));
    }

    switch ($c['type']) {
        case 'date': $v = date('Y-m-d', $v); break;
        case 'time': $v = date('H:i:s', $v); break;
        case 'datetime': $v = date('Y-m-d H:i:s'); break;
    }

    return $v;
}

/**
 * 枚举校验
 * 选项 enum 为可选值列表, 注意: 多选需要在前面加 repeat
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function isEnum($v, $c, $x) {
    $a = $c['enum'];
    if (!in_array($c , $a)) {
        throw new Wrong(_wrong('not_in_enum', $c));
    }
    return $v;
}

/**
 * 文件校验
 * 注: 此方法尚未完成
 * 选项 path 为存放目录
 * 选项 href 为存放目录对应的 URL
 * 选项 link 为存放目录对应的 URL, 自动补全域名
 * 选项 extn 限定扩展名, 数组表多个, 前面不带点
 * 选项 minsize 为文件最小值
 * 选项 maxsize 为文件最大值
 * @param mixed $v 校验取值
 * @param array $c 外部选项
 * @param Validator $x 校验器对象
 */
function isFile($v, $c, $x) {
    return $v;
}
