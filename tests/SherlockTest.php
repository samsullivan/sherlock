<?php
namespace Sherlock\tests;
use Sherlock\Sherlock;
/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2013-02-07 at 03:12:53.
 */
class SherlockTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Sherlock
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new Sherlock;
        $this->object->addNode('localhost', '9200');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * @covers sherlock\Sherlock::addNode
     */
    public function testAddNode()
    {
        $ret = $this->object->addNode('localhost');
        $this->assertInstanceOf('\sherlock\sherlock', $ret);

    }

    public function assertThrowsException($exception_name, $code)
    {
        $e = null;
        try {
            $code();
        } catch (\Exception $e) {
            // No more code, we only want to catch the exception in $e
        }

        $this->assertInstanceOf($exception_name, $e);
    }

    public function testHashBuilding()
    {

        $req = $this->object->search();
        $req->index("test3")->type("benchmark");
        $req->query(Sherlock::queryBuilder()->Term()->field("field1")->term("town"));

        //First, make sure the ORM query matches what we expect
        $data = $req->toJSON();
        $expectedData = json_encode(array("query" => array("term" => array("field1" => array("value" => "town")))));
        $this->assertEquals($expectedData, $data);

        //Now provide an array hashmap instead of using the ORM, to make sure manual creation works
        $manualData = array("field" => "field1", "term" => "town");
        $req->query(Sherlock::queryBuilder()->Term($manualData));
        $data = $req->toJSON();
        $this->assertEquals($expectedData, $data);

    }



    public function testIndexSettings()
    {
        //no index
        $req = $this->object->index();
        $this->assertInstanceOf('\sherlock\requests\IndexRequest', $req);

        //provide an index
        $req = $this->object->index('testnewindex');
        $this->assertInstanceOf('\sherlock\requests\IndexRequest', $req);

        //provide incorrect class, should throw error
        $this->assertThrowsException('\sherlock\common\exceptions\BadMethodCallException', function () use ($req) {
            $req->settings(Sherlock::queryBuilder());
        });
        //provide incorrect class with merge, should throw error
        $this->assertThrowsException('\sherlock\common\exceptions\BadMethodCallException', function () use ($req) {
            $req->settings(Sherlock::queryBuilder(), true);
        });

        $settings = sherlock::indexSettingsBuilder()->refresh_interval("1s");
        $req = $req->settings($settings);
        $this->assertInstanceOf('\sherlock\requests\IndexRequest', $req);

        $req = $req->settings($settings, true);
        $this->assertInstanceOf('\sherlock\requests\IndexRequest', $req);

        $settings = array("refresh_interval"=>"1s");
        $req = $req->settings($settings);
        $this->assertInstanceOf('\sherlock\requests\IndexRequest', $req);

        $req = $req->settings($settings, true);
        $this->assertInstanceOf('\sherlock\requests\IndexRequest', $req);

    }

    /**
     * @covers sherlock\Sherlock::index
     * @todo make this test actually assert things
     */
    public function testIndexOperations()
    {
        $sherlock = $this->object;

        //Create the index
        $index = $sherlock->index('testnewindex');
        $this->assertInstanceOf('\sherlock\requests\IndexRequest', $index);
        $response = $index->create();
        $this->assertInstanceOf('\sherlock\responses\IndexResponse', $response);
        $this->assertEquals(true, $response->ok);

        //set a setting
        $index->settings(Sherlock::indexSettingsBuilder()->refresh_interval("1s"));
        $this->assertInstanceOf('\sherlock\requests\IndexRequest', $index);
        $response = $index->updateSettings();
        $this->assertInstanceOf('\sherlock\responses\IndexResponse', $response);
        $this->assertEquals(true, $response->ok);

        //Delete the index first
        $response = $sherlock->index('testnewindex')->delete();
        $this->assertInstanceOf('\sherlock\responses\IndexResponse', $response);
        $this->assertEquals(true, $response->ok);

    }

}
