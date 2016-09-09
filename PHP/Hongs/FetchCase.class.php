<?php

namespace Hongs;

/**
 * 查询用例
 * @author HuangHong<ihongs@live.cn>
 */
class FetchCase {

    protected $_table;
    protected $_alias;
    protected $_field = array();
    protected $_where = array();
    protected $_group = array();
//  protected $_havin = array(); // 一般应用中意义不大
    protected $_order = array();
    protected $_limit = array();
    protected $_joins = array();
    protected $_saves = array();
    protected $_allow = array();

    //* 查询设置 */

    /**
     * 查询表
     * @param string $table
     * @param string $alias
     * @return \Hongs\FetchCase
     */
    public function from($table, $alias = null) {
        $this->_table = $table;
        $this->_alias = $alias;
        return $this;
    }

    /**
     * 关联表
     * @param stirng $table
     * @param string $alias
     * @param string $on 关联关系, 可以用 ~. 表示当前表, ^. 表示上级表
     * @param string $by 取值 INNER, LEFT, RIGHT, FULL
     * @return \Hongs\FetchCase 当前用例
     */
    public function join($table, $alias = null, $on = '', $by = 'INNER') {
        $this->link($table, $alias, $on, $by);
        return $this;
    }

    /**
     * 关联表
     * @param stirng $table
     * @param string $alias
     * @param string $on 关联关系, 可以用 ~. 表示当前表, ^. 表示上级表
     * @param string $by 取值 INNER, LEFT, RIGHT, FULL
     * @return \Hongs\FetchCase 关联用例
     */
    public function link($table, $alias = null, $on = '', $by = 'INNER') {
        $case = $table;
        if (! ( $table instanceof FetchCase)) {
            $case = new FetchCase();
            $case->_table = $table;
            $case->_alias = $alias;
        } else if ( isset ( $alias)) {
            $case->_alias = $alias;
        }
        $this->_joins[] = array($case, $on, $by);
        return $case;
    }

    /**
     * 字段
     * @param string $field 结构: array(字段, 别名=>字段)
     * @return \Hongs\FetchCase
     */
    public function field($field) {
        if (is_array($field)) {
            $this->_field = array_merge($field);
        } else {
            $this->_field[] = $field;
        }
        return $this;
    }

    /**
     * 条件
     * @param string $where 结构: array(字段=>取值, 字段=>array(关系符号=>取值)), 关系符号可取 =,>,< 等
     * @return \Hongs\FetchCase
     */
    public function where($where) {
        if (is_array($where)) {
            $this->_where = array_merge($where);
        } else {
            $this->_where[] = $where;
        }
        return $this;
    }

    /**
     * 分组
     * @param string $field 结构: array(字段)
     * @return \Hongs\FetchCase
     */
    public function group($field) {
        if (is_array($field)) {
            $this->_group = array_merge($field);
        } else {
            $this->_group[] = $field;
        }
        return $this;
    }

    /**
     * 排序
     * @param string $field 结构: array(字段, 字段=>顺序), 顺序可取值 DESC, ASC
     * @return \Hongs\FetchCase
     */
    public function order($field) {
        if (is_array($field)) {
            $this->_order = array_merge($field);
        } else {
            $this->_order[] = $field;
        }
        return $this;
    }

    /**
     * 限额
     * @param int $start
     * @param int $limit
     * @return \Hongs\FetchCase
     */
    public function limit($start, $limit = null) {
        $this->_limit = array(intval($start));
        if ($limit) {
            $this->_limit[] = intval($limit) ;
        }
        return $this;
    }

    //* 结构转换 */

    /**
     * 设置许可字段
     * @param array $allow
     * @param string $an 许可类型, 取值: field,where,group,order,limit,saves,id
     * @return \Hongs\FetchCase
     * @throws \Exception
     */
    public function allow($allow, $an = 'field') {
        static $ans = array(
            'rb' => 'field', 'field' => 'field',
            'ob' => 'order', 'order' => 'order',
            'wh' => 'where', 'where' => 'where',
            'wd' => 'finds', 'finds' => 'finds',
            'saves' => 'saves',
        );
        $al = $ans[$an];
        if (!isset($al)) {
            throw new \Exception("FetchCase: Illegal param value '$an' for an");
        }
        $this->_allow[$al] = $allow;
        return $this;
    }

    /**
     * 设置代存数据
     * @param array $data
     * @return \Hongs\FetchCase
     */
    public function saves(&$data) {
        $af = $this->transAllow('saves');
        $sd = array();
        foreach ($af as $c => $n) {
            if (isset($data[$c])) {
                $sd[$n] = (string) $data[$c];
            }
        }
        $this->_saves = $sd;
        return $this;
    }

    /**
     * 转入查询数据
     * @param array $data
     * @return \Hongs\FetchCase
     */
    public function trans(&$data) {
        // 字段
        $v = $data['rb'];
        if ($v) {
            $v = explode(',', $v);
            $x = $this->transField($v);
            if ( $x ) {
                 $this->field($x);
            }
        } else {
            $this->field($this->transAllow('field'));
        }

        // 排序
        $v = $data['ob'];
        if ($v) {
            $v = explode(',', $v);
            $x = $this->transOrder($v);
            if ( $x ) {
                 $this->order($x);
            }
        }

        // 搜索
        $v = $data['wd'];
        if ($v) {
            $v = explode(' ', $v);
            $x = $this->transFinds($v);
            if ( $x ) {
                 $this->where($x);
            }
        }

        // 分页
        $p = $data['pn'];
        $r = $data['rn'];
        if ($p || $r) {
            if (! $p) $p = 1 ;
            if (! $r) $r = 20;
            $this->limit(($p - 1) * $r, $r);
        }

        // 过滤
        $w = $this->transWhere($data);
        if ($w) {
            $this->where($w);
        }

        return $this;
    }

    protected function transField($v, $af = null) {
        $ic = array();
        $ec = array();
        if (! isset($af)) {
            $af = $this->transAllow('field');
        }

        foreach($v as $f) {
            if (substr($f, 0, 1) == '-') {
                $f = substr($f, 1);
                $xc = &$ec;
            } else {
                $xc = &$ic;
            }

            // 通配符处理
            if (substr($f, -2) == '_*' ) {
                $f = substr($f , 0 , -1);
                $l = strlen($f);
                foreach($af as $k => $v) {
                    if (substr($k,0, $l) == $f) {
                        $xc[] = $k;
                    }
                }
                continue;
            }

            if ($af[$f]) {
                $xc[] = $f;
            }
        }

        if (! $ic) {
            $ic = array_keys($af);
        }
        if (  $ec) {
            $ic = array_diff($ic, $ec);
        }

        $xf = array();
        foreach ($ic as $f) {
            $n = $af[$f];
            $xf[$f] = $n;
        }

        return $xf;
    }

    protected function transOrder($v, $af = null) {
        $ob = array();
        if (! isset($af)) {
            $af = $this->transAllow('order');
        }

        foreach($v as $c) {
            if (substr($c, 0, 1) == '-') {
                $c = substr($c, 1);
                $e = ' DESC';
            } else {
                $e = ' ASC' ;
            }

                $x = $af[$c];
            if ($x) {
                $ob[$x] = $e;
            }
        }

        return $ob;
    }

    protected function transFinds($v, $af = null) {
        $wd = array();
        if (! isset($af)) {
            $af = $this->transAllow('finds');
        }

        foreach($v as $t) {
            $t = trim($t);
            if (''=== $t) {
                continue ;
            }
            $t = preg_replace(' /[\/%_\[\]]/' , '/$0' , $t );
            $t = $this->escapValue($t);
            foreach ($af as $f) {
                $wd[] = $f.' LIKE \'%'.$t.'%\' ESCAPE \'/\'';
            }
        }

        // 多组搜索为或关系
        $wd = '('.implode(' OR ', $wd).')';

        return $wd;
    }

    protected function transWhere($v, $af = null) {
        $wh = array();
        $ff = $this->transAllow('finds');
        if (! isset($af)) {
            $af = $this->transAllow('where');
        }

        foreach($v as $c=>$d) {
            // 非关键词字段禁止使用模糊查询
            if (is_array($d)) {
                if (!$ff[$c]) {
                    unset($d['!lk']);
                    unset($d['!nl']);
                }
                if (isset($d[0])
                &&  count($d) === 1) {
                    $d  = $d[0];
                }
                if (count($d) === 0) {
                    $d  =  null ;
                }
            }

            // 跳过空值, 如果要查空串可取值: array('!eq' => '')
            if (is_null($d) || $d === '') {
                continue;
            }

                $x = $af[$c];
            if ($x) {
                $wh[$x] = $d;
            }
        }

        return $wh;
    }

    protected function transAllow($n, $pn = '', $pr = false) {
        $tn = $this->_alias ? $this->_alias : (!$pn ? '_' : $this->_table);
        $tx = $this->_joins || $pn ? '`'.$tn.'`.' : '';
        $tz = $pn ? $tn.'_' : '' ;
        $af = $this->_allow[ $n ];

        // 按基准增补和排除字段
        if ($af && $n != 'field') {
            $al = $this->transDiffs($af);
            if ($al != null) {
                return $al ;
            }
        }

        if (! $af && $n != 'field' && $n != 'finds') {
            $af = $this->_allow['field'];
        }
        if (! $af) {
            $af = array();
        }

        // 存储数据仅取当前表的
        if ($n == 'saves') {
            return $this->transSaves($af);
        }
        
        $al = $this->transTrans($af, $tx, $tz);

        // 递归提取所有关联字段
        if (! $pr) {
            foreach ($this->_joins as $join) {
                list($case, $x, $y) = $join;
                $al = array_merge($al,$case->transAllow($n, $tn));
            }
        }

        return $al;
    }

    private function transDiffs(&$af) {
        $ac = array(); // 增补字段
        $ec = array(); // 排除字段

        foreach($af as $c=>$n ) {
            if (is_numeric($c)) {
                $c = str_replace('.', '_', $n);
            }
            if (substr($c,0,1) == '+') {
                $ac[substr($c,1)] = $n;
            } else
            if (substr($c,0,1) == '-') {
                $ec[  ] = substr($c,1);
            }
        }

        if (!$ac && !$ec) {
            return null ;
        }
        
        $al = $this->transAllow('field', '', true);
        foreach($ac as $c=>$n) {
            $al[$c]  = $n ;
        }
        foreach($ec as $c) {
            unset($al[$c]);
        }
        return  $al;
    }

    private function transSaves(&$af) {
        $al = array();

        foreach($af as $c=>$n ) {
            if (is_numeric($c)) {
                $c = str_replace('.', '_', $n);
            }
            if ($this->isStdField($n)
            &&  $this->isStdField($c)) {
                $al[$c]  = $n;
            }
        }

        return  $al;
    }

    private function transTrans(&$af, $tx, $tz) {
        $al = array();
        
        foreach($af as $c=>$n ) {
            if (is_numeric($c)) {
                $c = str_replace('.', '_', $n);
            }
            if ($this->isStdField($n)) {
                $n = $tx .'`'.$n.'`';
            }
            if ($this->isStdField($c)) {
                $al[ $tz . $c ] = $n;
            }
        }
        
        return  $al;
    }
    
    //* 用例结果 */

    /**
     * 获取语句片段
     * @return array 分别为 FROM FIELD WHERE GROUP ORDER LIMIT
     */
    public function parts() {
        $from  = '';
        $field = '';
        $where = '';
        $group = '';
        $order = '';
        $limit = '';
        if ($this->_limit) {
            $limit = implode(',', $this->_limit);
        }
        $this->parse($from, $field, $where, $group, $order,'','','');
        return array($from, $field, $where, $group, $order, $limit );
    }

    protected function parse(&$from, &$field, &$where, &$group, &$order, $pn, $on, $by) {
        $tn = $this->_alias ? $this->_alias : $this->_table;
        $tx = $this->_joins || $pn ? '`'.$tn.'`.' : '';
        $tz = $pn ? $tn.'_' : '' ;

        if ($by) {
            $from .= ' ' . $by .' JOIN';
        }

        $from .= ' `' . $this->_table . '`';
        if ($this->_alias) {
            $from .= ' AS `'.$this->_alias . '`';
        } else if (! $pn ) {
            $from .= ' AS `_`';
            $tx = '`_`.';
        }

        if ($on) {
            // 将 ~. 替换为当前表别名
            // 将 ^. 替换为上级表别名
            $on = str_replace('~.', '`'.$tn.'`.', $on);
            $on = str_replace('^.', '`'.$pn.'`.', $on);
            $from .= ' ON ' . $on ;
        }

        if ($this->_field) {
            $this->parseField($field, $this->_field, $tx, $tz);
        }

        if ($this->_where) {
            $this->parseWhere($where, $this->_where, $tx);
        }

        if ($this->_group) {
            $this->parseGroup($group, $this->_group, $tx);
        }

        if ($this->_order) {
            $this->parseOrder($field, $this->_order, $tx);
        }

        // 递归关联
        if ($this->_joins) {
            foreach($this->_joins as $a) {
                list($case, $on, $by) = $a;
                $case->parse($from, $field, $where, $group, $order, $tn, $on, $by);
            }
        }
    }

    protected function parseGroup(&$group, $thisGroup, $tx) {
        foreach($thisGroup as $c=>$n) {
//          if (is_numeric($c)) {
                if ($this->isStdField($n)) {
                    $n  = $tx.'`'.$n.'`';
                }
                $group .= ', '.$n;
//          }
        }
    }

    protected function parseOrder(&$order, $thisOrder, $tx) {
        foreach($thisOrder as $c=>$n) {
            if (is_numeric($c)) {
                if ($this->isStdField($n)) {
                    $n  = $tx.'`'.$n.'`';
                }
                $order .= ', '.$n;
            } else {
                if ($this->isStdField($c)) {
                    $c  = $tx.'`'.$c.'`';
                }
                $order .= ', '.$c.' '.$n;
            }
        }
    }

    protected function parseField(&$field, $thisField, $tx, $tz) {
        foreach($thisField as $c=>$n) {
            if (is_numeric($c)) {
                $c  = $n;
            }
            if ($this->isStdField($n)) {
                $n  = $tx.'`'.$n.'`';
            }
            if ($this->isStdField($c)) {
                $c  = '`'.$tz.$c.'`';
            }
            if ($tz == '' && substr($n, strlen($n) - strlen($c)) == $c) {
                $field .= ','.$n; // 字段和别名相同则无需别名
            } else {
                $field .= ','.$n.' AS '.$c;
            }
        }
    }

    protected function parseWhere(&$where, $thisWhere, $tx) {
        foreach($thisWhere as $c=>$v) {
            if (is_numeric($c)) {
                $where .= ' AND '.$v;
            } else {
                if ($this->isStdField($c)) {
                    $c  = $tx.'`'.$c.'`';
                }

                if ( ! is_array($v)) {
                    if (is_null($v)) {
                        $where .= ' AND '. $c .' IS NULL';
                    } else {
                        $where .= ' AND '. $c .' = '. $this->quoteValue($v);
                    }
                } else {
                    foreach ($v as $r2 => $v2) {
                        $r = $this->querySigns($r2);
                        if ($r) {
                            unset($v[$r2]);
                            if ($r == 'LIKE' || $r == 'NOT LIKE') {
                                $v2 = '%' .$v2. '%';
                            }
                            $v2 = $this->quoteValue($v2);
                            if ($r ==  'IN'  || $r ==  'NOT IN' ) {
                                $v2 = '(' .$v2. ')';
                            }
                            $where .= ' AND '.$c.' '.$r.' '.$v2;
                        }
                    }
                        if ($v) {
                            $v  = $this->quoteValue($v );
                            $where .= ' AND '.$c.' IN ('.$v.')';
                        }
                }
            }
        }
    }

    protected function querySigns($rel) {
        static $arr = array(
            '=' => '=', '!=' => '!=',
            '>' => '>', '>=' => '>=',
            '<' => '<', '<=' => '<=',
            // 外部扩展
            '!eq' => '=', '!ne' => '!=',
            '!gt' => '>', '!ge' => '>=',
            '!lt' => '<', '!le' => '<=',
            // 特别符号
            '!in' => 'IN',
            '!ni' => 'NOT IN',
            '!lk' => 'LIKE',
            '!nl' => 'NOT LIKE',
        );
        return $arr[strtolower($rel)];
    }

    protected function quoteValue($val) {
        if (is_null($val)) {
            return 'NULL';
        } elseif (is_numeric($val)) {
            return '\''.$val.'\'' ;
        } elseif (! is_array($val)) {
            return '\''.$this->escapValue($val).'\'';
        } else {
            $vx=array_unique($val);
            foreach ($vx as &$vxl) {
                $vxl =  $this->quoteValue($vxl);
            }
            if ($vx) {
                return implode(',',$vx);
            } else {
                return 'NULL';
            }
        }
    }

    protected function escapValue($val) {
        return addslashes((string)$val);
    }

    protected function isStdField($key) {
        return  preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key);
    }

    //** 获取结果 **/

    /**
     * 转换成查询语句
     * @return string
     */
    public function toSelect() {
        list($from, $field, $where, $group, $order, $limit) = $this->parts();
        $tn = $this->_alias ? $this->_alias : $this->_table;

        if ($field) {
            $sql = 'SELECT '.trim($field, " \t\n\r\0\x0B,");
        } else if ($this->_joins) {
            $sql = 'SELECT `'.$tn.'`.*' ;
        } else {
            $sql = 'SELECT *';
        }

        $sql .= ' FROM '. $from;

        if ($where) {
            $sql .= ' WHERE ' .preg_replace('/^\s*(AND|OR)\s+/', '', $where);
        }

        if ($group) {
            $sql .= ' GROUP BY '.trim($group, " \t\n\r\0\x0B,");
        }

        if ($order) {
            $sql .= ' ORDER BY '.trim($order, " \t\n\r\0\x0B,");
        }

        if ($limit) {
            $sql .= ' LIMIT '.$limit;
        }

        return $sql;
    }

    /**
     * 转换成插入语句
     * @return string
     * @throws \Exception
     */
    public function toInsert() {
        $fs = array();
        $vs = array();
        foreach ($this->_saves as $f=>$v) {
            $fs[] = $f;
            $vs[] = $this->quoteValue($v);
        }
        if (!$fs || !$vs) {
            throw new \Exception('FetchCase: value can not be empty in create.');
        }
        return 'INSERT INTO `'.$this->_table.'` ('.implode(',', $fs).') VALUES ('.implode(',', $vs).')';
    }

    /**
     * 转换成更新语句
     * @return string
     * @throws \Exception
     */
    public function toUpdate() {
        $wh = '';
        $this->parseWhere($wh, $this->_where, '');
        $ss = array();
        foreach ($this->_saves as $f=>$v) {
            $ss[] = $f.'='. $this->quoteValue($v);
        }
        if (!$ss) {
            throw new \Exception('FetchCase: value can not be empty in update.');
        }
        if (!$wh) {
            throw new \Exception('FetchCase: where can not be empty in update.');
        }
        return 'UPDATE `'.$this->_table.'` SET '.implode(',', $ss).' WHERE '.$wh;
    }

    /**
     * 转换成删除语句
     * @return string
     * @throws \Exception
     */
    public function toDelete() {
        $wh = '';
        $this->parseWhere($wh, $this->_where, '');
        if (!$wh) {
            throw new \Exception('FetchCase: where can not be empty in update.');
        }
        return 'DELETE FROM `'.$this->_table.'` WHERE '.$wh;
    }

    //** 对象操作 **/

    /**
     * 设置查询
     * 与 $n 对应的方法不同, $fc->field = 'xxxx' 会设置而非追加查询字段
     * @param string $n 可取值 field, where, group, havin, order, limit
     * @param mixed  $v 对应的结构数据或查询字串
     * @return void
     * @throws \Exception
     */
    public function __set($n, $v) {
        if (in_array($n, array('field', 'where', 'group', 'order', 'limit'))) {
            $n = '_'.$n;
            if (! is_array($v)) {
                $v = array($v);
            }
            $this->$n = $v;
            return;
        }
        throw new \Exception('FetchCase: Can not set '.$n);
    }

    public function __unset($n) {
        if (in_array($n, array('field', 'where', 'group', 'order', 'limit'))) {
            $k = '_'.$n;
            $this->$k = array();
            foreach ($this->_joins as &$join) {
                unset($join[0]->$n);
            }
            return;
        }
        throw new \Exception('FetchCase: Can not unset '.$n);
    }

    public function __isset($n) {
        if (in_array($n, array('field', 'where', 'group', 'order', 'limit'))) {
            $k = '_'.$n;
            if ($this->$k) return true;
            foreach ($this->_joins as &$join) {
                if (isset($join[0]->$k)) return true;
            }
            return  false;
        }
        throw new \Exception('FetchCase: Can not isset '.$n);
    }

    public function __clone() {
        // 深度拷贝关联
        foreach ($this->_joins as &$join) {
            $join[ 0 ] = clone $join[ 0 ];
        }
    }

    /**
     * 获取查询语句
     * @return string
     */
    public function __toString() {
        return $this->toSelect();
    }

}
