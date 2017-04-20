<?php

main();

function main(){
    //items 旧数据, 模仿从数据库里取出的数据，已经有code编码了，取数据时需要对记录id进行排序，保证code确定
    $oldItems = array(
        array( 'path'=>'省1', 'codePath'=>'1' ),
        array( 'path'=>"省1-市1-区1", 'codePath'=>"1-1-1" ),
        array( 'path'=>"省1-市1-区2", 'codePath'=>"1-1-2" ),
        array( 'path'=>"省1-市2-区1", 'codePath'=>"1-2-1" ),
        array( 'path'=>"省9-市9-区9", 'codePath'=>"9-9-9" ),
        array( 'path'=>"省1-市2-区2", 'codePath'=>"1-2-2" ),
        array( 'path'=>"省2-市1-区1", 'codePath'=>"2-1-1" ),
        array( 'path'=>"省3-市3-区3", 'codePath'=>"3-3-3" ),
        array( 'path'=>"省3-市3", 'codePath'=>"3-3" ),
        array( 'path'=>"省3", 'codePath'=>"3" ),
    );


    //转为树形结构，生成地址编码
//    $tree = TreeNode::translate();
    $tree = TreeNode::translate( $oldItems );


    //items 新增加数据 与旧数据有2条重复 加入树后自动计算code
    $newItems = array(
        array( 'path'=>"省1-市1-区1", 'codePath'=>"" ), //重复
        array( 'path'=>"省1-市1-区2", 'codePath'=>"" ), //重复
        array( 'path'=>"省1-市1-区3", 'codePath'=>"" ),
        array( 'path'=>"省1-市1-区4", 'codePath'=>"" ),
        array( 'path'=>"省1-市3-区1", 'codePath'=>"" ),
    );

    //加入新数据
    $tree->addNodes( $newItems );

    //转换会数组，参数 onlyNew 为true 只返回新添加的数据
    print_r( $tree->translateToList( true ) );

//    echo $tree->toJSON();
//
//    echo "\n";

}

class TreeNode {
    public $codePath=array();
    public $new = 'true';
    public $textPath;
    public $text = '';
    public $children=array();
    public $father=null;

    function __construct( $textPath, $codePath=null )
    {
        $this->textPath = $textPath;
        if( $codePath ) {
            $this->codePath = $codePath;
            $this->new = 'false';
        }

//        $segs = explode('-', $textPath);
        $this->text = array_pop($textPath);
    }

    function addChild( $child ) {
        $this->children[] = $child;
        if( $child->new == 'true' ) {
            $code = count($this->children);
            $child->codePath = array($code);

            if( !$this->isRoot() ) {
                $child->codePath = array_merge( $this->codePath, $child->codePath );
            }
        }
        $child->father = $this;
    }

    static function fetchAllPath( $item ){
        $segs = explode('-', $item['path']);
        $codeSegs = explode('-', $item['codePath']);

        $paths = array();

        for($i=0, $len=count($segs); $i<$len; $i++) {
            $path[] = $segs[$i];
            if( isset($codeSegs[$i]) ) {
                $codePath[] = $codeSegs[$i];
            }
            $paths[] = array('textPath'=>$path, 'codePath'=>$codePath);
        }

        foreach( $paths as &$path ) {
            if( empty($path['codePath'][0]) )
                $path['codePath'] = null;
        }
        unset($path);

        return $paths;
    }

    function isRoot() {
        return ($this->textPath[0] == 'root');
    }

    /**
     * 测试目标路径与当前节点路径的关系
     * 返回值：
     * same 路径相同，direct 是当前节点的直接后继，child 后继, other 目标路径不在当前节点的路径之上
     * @param $textPath
     */
    function testPath( $textPath ) {
        //所有路径都是根节点的后继
        if( $this->isRoot() ) {
            $len = count($textPath);
            if( $len == 1 ){
                return 'direct';
            }
            return 'child';
        }

        $path1Len = count($this->textPath);
        for($i =0; $i < $path1Len; $i++) {
            if( $this->textPath[$i] != $textPath[$i] ) {
                break;
            }
        }

        //i < path1Len other
        //i == path1Len child
        //长度相等 same
        //长度相差1 direct

        if( $i < $path1Len ) {
            return 'other';
        }

        $path2Len = count($textPath);
        if( $path1Len == $path2Len ) {
            return 'same';
        }

        if( $path1Len == $path2Len -1 ) {
            return 'direct';
        }

        return 'child';
    }

    function equalTextPath( $textPath ) {
        return 'same' === $this->testPath( $textPath );
    }

    static function textPath2Text( $textPath ) {
        return implode('-', $textPath );
    }

    static function distinctPaths( $paths ) {
        $pathMap = array();
        foreach( $paths as $path ) {
            $key = self::textPath2Text($path['textPath']);
            $pathMap[$key] = $path;
        }
        return array_values($pathMap);
    }

    function findMountPoint( $textPath ) {
        $result = $this->testPath( $textPath );
        //已出现在树中，不用挂载
        if( $result == 'same' ) {
            return null;
        }

        if( $result == 'direct' ) {
            foreach( $this->children as $child ) {
                if( $child->equalTextPath( $textPath ) ) {
                    return null;
                }
            }
            //与所有子节点均无重复路径, 挂载在当前节点下
            return $this;
        }

        if( $result == 'child' ) {
            foreach( $this->children as $child ) {
                $result = $child->findMountPoint( $textPath );
                if( $result ) {
                    return $result;
                }
            }
            return null;
        }

        return null;
    }

    function addNodes( $items ) {
        $paths = array();
        //获取所有路径， '省1-市1-区1' >>> ['省1', ['省1', '区1'], ['省1', '市1', '区1']]
        foreach( $items as $item ) {
            $paths = array_merge( $paths, self::fetchAllPath( $item ) );
        }

        //去除重复路径
        $paths = self::distinctPaths($paths);

        //循环挂载所有路径到树形结构
        echo "##新增路径####################################################\n";
        print_r( $paths );
        echo "##开始建树####################################################\n";

        foreach( $paths as $path ) {
            echo "## 增加一个路径  ".self::textPath2Text($path['textPath'])."  ################################################\n";
            $mountPoint = $this->findMountPoint( $path['textPath'] );
            if( $mountPoint ) {
                $node = new TreeNode( $path['textPath'], $path['codePath']);
                $mountPoint->addChild( $node );
            }
        }

        echo "##完成建树####################################################\n";
        print_r( $this );
    }

    static function translate( $items=array() ) {
        $paths = array();
        //获取所有路径， '省1-市1-区1' >>> ['省1', ['省1', '区1'], ['省1', '市1', '区1']]
        foreach( $items as $item ) {
            $paths = array_merge( $paths, self::fetchAllPath( $item ) );
        }
        //去除重复路径
        $paths = self::distinctPaths($paths);
//        var_dump( $paths );

        //循环挂载所有路径到树形结构
        $tree = new TreeNode( array('root'), array('R') );

        echo "##所有路径####################################################\n";
        print_r( $paths );
        echo "##开始建树####################################################\n";

        foreach( $paths as $path ) {
            echo "## 增加一个路径  ".self::textPath2Text($path['textPath'])."  ################################################\n";
            $mountPoint = $tree->findMountPoint( $path['textPath'] );
            $node = new TreeNode( $path['textPath'], $path['codePath']);
            $mountPoint->addChild( $node );
        }

        echo "##完成建树####################################################\n";
        print_r( $tree );

        return $tree;
    }

    function cloneNoFather() {
        //深拷贝，返回father为NULL的树形结构
        $node = new TreeNode($this->textPath, $this->codePath);
        $node->codePath = $this->codePath;
        $node->textPath = $this->textPath;
        $node->text = $this->text;
        $node->children = array();
        foreach( $this->children as $child ) {
            $node->children[] = $child->cloneNoFather();
        }
        return $node;
    }

    function toJSON() {
        $node = $this->cloneNoFather();
        return json_encode( $node );
    }

    function translateToList( $onlyNew ) {

        $rows = array();
        if( !$this->isRoot() ){
            $row = array(
                'text'=>$this->text,
                'path'=>self::textPath2Text($this->textPath),
                'code'=>$this->codePath,
                'father' => array_slice( $this->codePath, 0, -1 ),
            );
            if( count($row['father']) == 0 )
                $row['father'] = NULL;

            if( $onlyNew ){
                if( $this->new == "true" ) {
                    $rows[] =  $row;
                }
            }else{
                $rows[] =  $row;
            }
        }

        foreach( $this->children as $node ) {
            $childRows = $node->translateToList( $onlyNew );
            $rows = array_merge( $rows, $childRows );
        }

        return $rows;
    }
}

