<?php

namespace Gini\PHPUnit\ORM {
 
    class ORM extends \Gini\PHPUnit\TestCase\CLI
    {
        public function setUp()
        {
            parent::setUp();
    
            class_exists('\Gini\Those');
    
            $db = $this->getMockBuilder('\Gini\Database')
                ->setMockClassName('MOBJ_'.uniqid())
                ->setMethods(['query', 'quote', 'quoteIdent'])
                ->disableOriginalConstructor()
                ->getMock();
    
            $db->expects($this->any())
                ->method('quoteIdent')
                ->will($this->returnCallback(function ($s) use ($db) {
                    if (is_array($s)) {
                        foreach ($s as &$i) {
                            $i = $db->quoteIdent($i);
                        }
    
                        return implode(',', $s);
                    }
    
                    return '"'.addslashes($s).'"';
                }));
    
            $db->expects($this->any())
                ->method('quote')
                ->will($this->returnCallback(function ($s) use ($db) {
                    if (is_array($s)) {
                        foreach ($s as &$i) {
                            $i = $db->quote($i);
                        }
    
                        return implode(',', $s);
                    } elseif (is_null($s)) {
                        return 'NULL';
                    } elseif (is_bool($s)) {
                        return $s ? 1 : 0;
                    } elseif (is_int($s) || is_float($s)) {
                        return $s;
                    }
    
                    return '\''.addslashes($s).'\'';
                }));
    
            \Gini\IoC::bind('\Gini\ORM\UTSample', function ($criteria = null) use ($db) {
                $o = $this->getMockBuilder('\Gini\ORM\Object')
                    ->setMockClassName('MOBJ_'.uniqid())
                    ->setMethods(['db', 'ownProperties', 'name', 'tableName'])
                    ->disableOriginalConstructor()
                    ->getMock();
    
                $o->expects($this->any())
                    ->method('db')
                    ->will($this->returnValue($db));
    
                $o->expects($this->any())
                    ->method('name')
                    ->will($this->returnValue('utsample'));
    
                $o->expects($this->any())
                    ->method('tableName')
                    ->will($this->returnValue('utsample'));
    
                $o->expects($this->any())
                    ->method('ownProperties')
                    ->will($this->returnValue([
                    'id' => 'bigint,primary,serial',
                    '_extra' => 'array',
                    'object' => 'object',
                    'sample' => 'object:utsample',
                    'number' => 'int',
                    'boolean' => 'bool',
                    'datetime' => 'datetime',
                    'timestamp' => 'timestamp',
                    'text' => 'string',
                    ]));
    
                unset($o->id);
                unset($o->_extra);
                if (isset($criteria)) {
                    $criteria = $o->normalizeCriteria($o->criteria($criteria));
                    $o->setData($criteria);
                }
    
                return $o;
            });
        }
    
        public function tearDown()
        {
            \Gini\IoC::clear('\Gini\ORM\UTSample');
            parent::tearDown();
        }
    
        public function testSaveObject()
        {
            $o1 = a('utsample');
            $o2 = a('utsample');
    
            $o2->id = 10;
            
            $o1->object = $o2;
            $o1->db()
                ->expects($this->any())
                ->method('query')
                ->will($this->returnCallback(function ($SQL) {
                    $this->assertEquals(
                        $SQL,
                        'INSERT INTO "utsample" SET "_extra"=\'{}\',"object_name"=\'utsample\',"object_id"=10,"sample_id"=NULL,"number"=0,"boolean"=0,"datetime"=\'0000-00-00 00:00:00\',"timestamp"=NOW(),"text"=\'\''
                    );
                }));
    
            $o1->save();
        }

        public function testORMSchema()
        {
            $schema = a('utsample')->ormSchema();
            $fields = $schema['fields'];
            $this->assertEquals($fields['id'], ['type'=>'bigint','serial'=>true,'default'=>0]);
            $this->assertEquals($fields['_extra'], ['type'=>'text','default'=>'{}']);
            $this->assertEquals($fields['object_name'], ['type'=>'varchar(120)']);
            $this->assertEquals($fields['object_id'], ['type'=>'bigint','null'=>true]);
            $this->assertEquals($fields['sample_id'], ['type'=>'bigint','null'=>true]);
            $this->assertEquals($fields['number'], ['type'=>'int','default'=>0]);
            $this->assertEquals($fields['boolean'], ['type'=>'int','default'=>0]);
            $this->assertEquals($fields['datetime'], ['type'=>'datetime','default'=>'0000-00-00 00:00:00']);
            $this->assertEquals($fields['timestamp'], ['type'=>'timestamp','default'=>'CURRENT_TIMESTAMP']);
            $this->assertEquals($fields['text'], ['type'=>'varchar(255)','default'=>'']);
        }
    
        public function testGet()
        {
            $o1 = a('utsample', 2);
            $o1->setData([
               'id' => 10,
               'object_name' => 'utsample',
               'object_id' => 2,
               'sample_id' => 4,
            ]);
    
            $this->assertEquals($o1->id, 10);
    
            $this->assertEquals($o1->object->id, 2);
            $this->assertEquals($o1->sample->id, 4);
    
            $this->assertEquals($o1->object_id, 2);
            $this->assertEquals($o1->sample_id, 4);
        }
    
        public function testInjection()
        {
            $o1 = a('utsample', 2);
            $o1->inject([
                'properties' => [
                    'inject_prop1' => 'string:*',
                    'inject_prop2' => 'int'
                ],
                'indexes' => [
                    'inject_prop1',
                    'unique:inject_prop2'
                ],
                'relations' => [
                    'sample' => 'update:cascade,delete:cascade'
                ]
            ]);

            $props = $o1->properties();
            $this->assertEquals($props['inject_prop1'], 'string:*');
            $this->assertEquals($props['inject_prop2'], 'int');
            $this->assertEquals($o1->ormIndexes(), ['inject_prop1', 'unique:inject_prop2']);
            $this->assertEquals($o1->ormRelations(), ['sample' => ['update' => 'cascade', 'delete' => 'cascade']]);
        }
    }
    
}
