<?php

namespace hongs;

/**
 * 联并工具
 * @author HuangHong<ihongs@live.cn>
 */
class MergeMore {

    private $rows;

    /**
     * 构造方法
     * @param array $rows 基础数据
     */
    function __construct(&$rows) {
        $this->rows = &$rows;
    }

    /**
     * 提取关联映射关系
     * @param string $col 映射列
     * @return array 映射表
     */
    public function maping($col) {
        $map = array();
        foreach ($this->rows as  &$row) {
            $map[$row[$col]][] = &$row;
        }
        return $map;
    }

    /**
     * 填充默认的空数据
     * 注意: $sub 规则同 mixing, 建议在 mixing 后执行, 当需要合并数据时, 必须提供 $def
     * @param array  $map 映射表
     * @param string $sub 下联键
     * @param array  $def 默认值
     */
    public function pading(&$map, $sub, $def = null) {
        $add = substr($sub, -1);
        if ($add == '_' || $add == '') {
            $add = 0; // 合并
        } else
        if ($add == '.') {
            $sub = substr($sub, 0, -1);
            $add = 2; // 一对多
        } else {
            $add = 1; // 一对一
        }

        // 通过 JSON 格式返回数据是目前常用做法
        // 可是空的数组总是被翻译成 []
        // 而一对一时缺省是关系数组而非普通数组
        // 此时默认标准对象便可译成 {}
        if (!isset($def)) {
            $def = $add != 1 ? array() : new \stdClass();
        }

        foreach($map as &$arr) {
            foreach($arr as &$raw) {
                if ($add == 0) {
                    foreach($def as $k => $v ) {
                        $k = $sub . $k ;
                        if (! isset($raw[$k])) {
                            $raw[ $k ] = $v ;
                        }
                    }
                } else {
                    if (! isset($raw[$sub])) {
                        $raw[$sub] = $def;
                    }
                }
            }
        }
    }

    /**
     * 关联混合列表数据
     * 当 $sub 为空或以 _ 结尾时会将数据合并到源数据层级, 以 $sub 为键前缀; 以 . 结尾会任务是一对多关系
     * @param array $rows 关联数据
     * @param array  $map 映射表
     * @param string $col 关联列
     * @param string $sub 下级键
     */
    public function mixing(&$rows, &$map, $col, $sub = '') {
        $add = substr($sub, -1);
        if ($add == '_' || $add == '') {
            $add = 0; // 合并
        } else
        if ($add == '.') {
            $sub = substr($sub, 0, -1);
            $add = 2; // 一对多
        } else {
            $add = 1; // 一对一
        }

        foreach($rows as &$row) {
            $rel = $row[$col];
            $arr = $map[$rel];
            foreach($arr as &$raw) {
                if ($add == 0) {
                    foreach($row as $k => $v ) {
                        $raw[$sub . $k] = $v ;
                    }
                } else
                if ($add == 1) {
                    $raw[$sub] = $row;
                } else {
                    $raw[$sub][]=$row;
                }
            }
        }
    }

}
