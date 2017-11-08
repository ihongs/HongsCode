<?php

namespace hongs;

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
     * @return \hongs\FetchCase
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
     * @return \hongs\FetchCase 当前用例
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
     * @return \hongs\FetchCase 关联用例
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
     * @param array|string $field 结构: array(字段, 别名=>字段)
     * @return \hongs\FetchCase
     */
    public function field($field) {
        if (is_array($field)) {
            $this->_field = array_merge($this->_field, $field);
        } else {
            $this->_field[] = $field;
        }
        return $this;
    }

    /**
     * 条件
     * @param array|string $where 结构: array(字段=>取值, 字段=>array(关系符号=>取值)), 关系符号可取 =,>,< 等
     * @return \hongs\FetchCase
     */
    public function where($where) {
        if (is_array($where)) {
            $this->_where = array_merge($this->_where, $where);
        } else {
            $this->_where[] = $where;
        }
        return $this;
    }

    /**
     * 分组
     * @param array|string $field 结构: array(字段)
     * @return \hongs\FetchCase
     */
    public function group($field) {
        if (is_array($field)) {
            $this->_group = array_merge($this->_group, $field);
        } else {
            $this->_group[] = $field;
        }
        return $this;
    }

    /**
     * 排序
     * @param array|string $field 结构: array(字段, 字段=>顺序), 顺序可取值 DESC, ASC
     * @return \hongs\FetchCase
     */
    public function order($field) {
        if (is_array($field)) {
            $this->_order = array_merge($this->_order, $field);
        } else {
            $this->_order[] = $field;
        }
        return $this;
    }

    /**
     * 限额
     * @param int $start
     * @param int $limit
     * @return \hongs\FetchCase
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
     * @return \hongs\FetchCase
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
     * @return \hongs\FetchCase
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
     * @return \hongs\FetchCase
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

        // 过滤
        $w = $this->transWhere($data);
        if ($w) {
            $this->where($w);
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
            if (substr($f, -2 ) == '_*') {
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
                $e = 'DESC' ;
            } else {
                $e =  'ASC' ;
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
        $wp = array();
        if (! isset($af)) {
            $af = $this->transAllow('finds');
        }

        foreach($v as $t) {
            $t = trim($t);
            if ('' == $t) {
                continue ;
            }
            $t = '%'. $this->escapLikes ($t) .'%';
            foreach ($af as $f) {
                $wd[] = $f.' LIKE ? ESCAPE \'/\'';
                $wp[] = $t;
            }
        }

        // 多组搜索为或关系
        $wd = '('.implode(' OR ', $wd).')';
        $wd = new WhereCase($wd , $wp);

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
        $tn = $this->_alias ? $this->_alias : $this->_table;
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
                $al[$c] = '`'.$n.'`';
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

    protected function parse(&$from, &$field, &$where, &$group, &$order, &$param, $pn, $on, $by) {
        $tn = $this->_alias ? $this->_alias : $this->_table;
        $tx = $this->_joins || $pn ? '`'.$tn.'`.' : '';
        $tz = $pn ? $tn.'_' : '' ;

        if ($by) {
            $from .= ' ' . $by .' JOIN';
        }

            $from .=    ' `'.$this->_table.'`';
        if ($this->_alias) {
            $from .= ' AS `'.$this->_alias.'`';
        } else if (! $pn ) {
            $from .= ' AS `_`' ;
            $tx    =     '`_`.';
        }

        if ($on) {
            $on = str_replace('~.', '`'.$tn.'`.', $on); // 将 ~. 替换为当前表别名
            $on = str_replace('^.', '`'.$pn.'`.', $on); // 将 ^. 替换为上级表别名
            $from .= ' ON ' . $on ;
        }

        if ($this->_field) {
            $this->parseField($field, $param, $this->_field, $tx, $tz);
        }

        if ($this->_where) {
            $this->parseWhere($where, $param, $this->_where, $tx);
        }

        if ($this->_group) {
            $this->parseGroup($group, $param, $this->_group, $tx);
        }

        if ($this->_order) {
            $this->parseOrder($order, $param, $this->_order, $tx);
        }

        // 递归关联
        if ($this->_joins) {
            foreach($this->_joins as $a) {
                list($case, $on, $by) = $a;
                $case->parse($from, $field, $where, $group, $order, $param, $tn, $on, $by);
            }
        }
    }

    protected function parseGroup(&$group, &$param, $thisGroup, $tx) {
        foreach($thisGroup as $c=>$n) {
//          if (is_numeric($c)) {
                if ($n instanceof WhereCase) {
                    if ( ! is_null($param) ) {
                        $param = array_merge($param, $n->getParam());
                        $n = $n->getWhere( );
                    }
                } else
                if ($this->isStdField($n)) {
                    $n  = $tx.'`'.$n.'`';
                }
                $group .= ', '.$n;
//          }
        }
    }

    protected function parseOrder(&$order, &$param, $thisOrder, $tx) {
        foreach($thisOrder as $c=>$n) {
            if (is_numeric($c)) {
                if ($n instanceof WhereCase) {
                    if ( ! is_null($param) ) {
                        $param = array_merge($param, $n->getParam());
                        $n = $n->getWhere( );
                    }
                } else
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

    protected function parseField(&$field, &$param, $thisField, $tx, $tz) {
        foreach($thisField as $c=>$n) {
            if (is_numeric($c)) {
                if ($n instanceof WhereCase) {
                    if ( ! is_null($param) ) {
                        $param = array_merge($param, $n->getParam());
                        $n = $n->getWhere( );
                    }
                    $field .= ','.$n;
                    break;
                }

                $c  = $n ;
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

    protected function parseWhere(&$where, &$param, $thisWhere, $tx) {
        foreach($thisWhere as $c=>$v) {
            if (is_numeric($c)) {
                if (! is_null($param) && ($v instanceof WhereCase) ) {print_r($v);
                    $param  = array_merge($param , $v->getParam( ) );
                    $where .= ' AND '.$v->getWhere();
                } else {
                    $where .= ' AND '.$v;
                }
            } else {
                if ($this->isStdField($c)) {
                    $c  = $tx.'`'.$c.'`';
                }

                if ( ! is_array($v)) {
                    if (is_null($v)) {
                        $where .= ' AND '. $c .' IS NULL';
                    } else {
                        $v = $this->quoteValue($v,$param);
                        $where .= ' AND '. $c .' = '. $v ;
                    }
                } else {
                    foreach($v as $r2 => $v2) {
                        $r = $this->escapSigns($r2);
                        if (! $r) continue;
                        unset($v [ $r2 ] );

                        if ($r ==  'IN'  || $r ==  'NOT IN' ) {
                            $v2 = $this->quoteValue($v2, $param);
                            $v2 = '(' .$v2. ')';
                        } else {
                        if ($r == 'LIKE' || $r == 'NOT LIKE') {
                            $v2 = $this->escapLikes($v2);
                            $v2 = '%' .$v2. '%';
                        } else if (is_array($v2)) {
                            $v2 = ( string )$v2;
                        }
                            $v2 = $this->quoteValue($v2, $param);
                        }
                            $where .= ' AND '.$c.' '.$r.' '.$v2 ;
                    }

                        // 剩余的作为 IN
                        if ($v) {
                            $v  = $this->quoteValue($v , $param);
                            $where .= ' AND '.$c.' IN ('.$v.')' ;
                        }
                }
            }
        }
    }

    protected function quoteValue($val, &$pms) {
        // 采用语句与参数分离的方式
        if (!is_null($pms)) {
        if (is_array($val)) {
            if ($val) {
                $val = array_values(      $val);
                $val = array_unique(      $val);
                $pms = array_merge ($pms, $val);
                $val = array_pad   (array(), count($val), '?');
                return implode     (',' , $val);
            }
            $pms[] = null;
            return '?';
        } else {
            $pms[] = $val;
            return '?';
        }
        }

        if (is_null($val)) {
            return 'NULL';
        } elseif (is_numeric($val)) {
            return '\''.$val.'\'' ;
        } elseif (! is_array($val)) {
            $val = $this->escapValue($val);
            return '\''.$val.'\'';
        } else {
            $vx  = array_unique(array_values($val));
            foreach ($vx as &$vxl) {
            $vxl = $this->escapValue($vxl);
            $vxl = '\''.$vxl.'\'';
            }
            if ($vx) {
                return implode( ',', $vx );
            } else {
                return 'NULL'; // 避 IN ()
            }
        }
    }

    protected function escapSigns($rel) {
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
        return $arr[ strtolower($rel) ];
    }

    protected function escapValue($val) {
        return addslashes((string)$val);
    }

    protected function escapLikes($val) {
        return preg_replace(  '/[\/%_\[\]]/'  , '/$0', $val);
    }

    protected function trimsWhere($str) {
        return preg_replace('/^\s*(AND|OR)\s+/' , '' , $str);
    }

    protected function trimsField($str) {
        return trim($str, " \t\n\r\0\x0B,");
    }

    protected function isStdField($key) {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key);
    }

    //** 获取结果 **/

    /**
     * 转换成查询语句
     * @param $toArr true 或 1 返回 array(sql, params), 2 返回 array(sql, params, limits)
     * @return string|array
     */
    public function toSelect($toArr = false) {
        $from  = '';
        $field = '';
        $where = '';
        $group = '';
        $order = '';
        $param = $toArr ? array() : null;
        $this->parse($from, $field, $where, $group, $order, $param, '', '', '');

        if ($field) {
            $sql = 'SELECT '.$this->trimsField($field);
        } else
        if ($this->_joins) {
        if ($this->_alias) {
            $sql = 'SELECT `'.$this->_alias.'`.*';
        } else {
            $sql = 'SELECT `'.$this->_table.'`.*';
        }
        } else {
            $sql = 'SELECT *';
        }

        $sql .= ' FROM '. $from;

        if ($where) {
            $sql .= ' WHERE '    . $this->trimsWhere($where);
        }

        if ($group) {
            $sql .= ' GROUP BY ' . $this->trimsField($group);
        }

        if ($order) {
            $sql .= ' ORDER BY ' . $this->trimsField($order);
        }

        // 仅适合 SQLite,MySQL,PGSql 等支持 LIMIT 的数据库
        if ($toArr != 2 && $this->_limit) {
            $sql .= ' LIMIT ' . implode(',' , $this->_limit);
        }

        if ($toArr == 2) {
            return array($sql, $param, $this->_limit);
        } else
        if ($toArr) {
            return array($sql, $param);
        } else {
            return $sql;
        }
    }

    /**
     * 转换成插入语句
     * @param $toArr true 返回 array(sql, params)
     * @return string|array
     * @throws \Exception
     */
    public function toInsert($toArr = false) {
        if (! $this->_saves) {
            throw new \Exception('FetchCase: value can not be empty in insert');
        }

        $fs = array();
        $vs = array();
        $ps = $toArr ? array() : null ;
        foreach ($this->_saves as  $f => $v) {
            $v = $this->quoteValue($v , $ps);
            $fs[] = $f;
            $vs[] = $v;
        }
        $sq = 'INSERT INTO `'.$this->_table.'` ('.implode(',', $fs).') VALUES ('.implode(',', $vs).')';

        if ($toArr) {
            return array($sq, $ps);
        } else {
            return $sq;
        }
    }

    /**
     * 转换成更新语句
     * @param $toArr true 返回 array(sql, params)
     * @return string|array
     * @throws \Exception
     */
    public function toUpdate($toArr = false) {
        if (!$this->_saves) {
            throw new \Exception('FetchCase: value can not be empty in update');
        }
        if (!$this->_where) {
            throw new \Exception('FetchCase: where can not be empty in update');
        }

        $wh = '';
        $ss = array();
        $ps = $toArr ? array() : null ;
        foreach ($this->_saves as  $f => $v) {
            $v = $this->quoteValue($v , $ps);
            $ss[] = $f .'='. $v;
        }
        $this->parseWhere( $wh , $ps , $this->_where , '');
        $wh = $this->trimsWhere( $wh );
        $sq = 'UPDATE `'.$this->_table.'` SET '.implode(',', $ss).' WHERE '.$wh;

        if ($toArr) {
            return array($sq, $ps);
        } else {
            return $sq;
        }
    }

    /**
     * 转换成删除语句
     * @param $toArr true 返回 array(sql, params)
     * @return string|array
     * @throws \Exception
     */
    public function toDelete($toArr = false) {
        if (!$this->_where) {
            throw new \Exception('FetchCase: where can not be empty in delete');
        }

        $wh = '';
        $ps = $toArr ? array() : null ;
        $this->parseWhere( $wh , $ps , $this->_where , '');
        $wh = $this->trimsWhere( $wh );
        $sq = 'DELETE FROM `'.$this->_table.'` WHERE '.$wh;

        if ($toArr) {
            return array($sq, $ps);
        } else {
            return $sq;
        }
    }

    /**
     * 是否有待存储的数据
     * @return type
     */
    public function hasSaves() {
        return !! $this->_saves;
    }

    /**
     * 是否有待查询的条件
     * @return boolean
     */
    public function hasWhere() {
        return !! $this->_where;
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

/**
 * 条件用例, 可用于支撑复杂嵌套条件
 * @author HuangHong<ihongs@live.cn>
 */
class WhereCase {
    protected $_where;
    protected $_param;

    function __construct($where, $param = array()) {
        $qc = substr_count($where, '?');
        $pc =        count($param     );
        if ($qc != $pc) {
            throw new \Eexception('WhereCase: there are '.$qc.' ? but '.$pc.' values');
        }

        $this->_where = $where;
        $this->_param = $param;
    }

    function getWhere() {
        return $this->_where;
    }

    function getParam() {
        return $this->_param;
    }

    function __toString() {
        $i = 0;
        $j = 0;
        $w = $this->_where;
        while (($i = strpos($w, '?', $i)) !== false) {
            $v  = $this->quote($this->_param[$j ++]);
            $w  = substr($w,0,$i).$v.substr($w,1+$i);
            $i += strlen($v);
        }
        return $w;
    }

    private function quote($v) {
        if (is_numeric($v)) {
            return (string)$v;
        }
        return '\''.addslashes((string)$v).'\'';
    }
}
