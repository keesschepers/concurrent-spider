<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\EventListener;

use Symfony\Component\EventDispatcher\GenericEvent;
use Simgroep\ConcurrentSpiderBundle\EventListener\DiscoverUrlListener;
use PHPUnit_Framework_TestCase;
use VDB\Uri\Uri;

class DiscoverUrlListenerTest extends PHPUnit_Framework_TestCase
{
    public function testIfUrlIsSkippedWhenAlreadyIndexed()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('publish', '__destruct'))
            ->getMock();

        $queue
            ->expects($this->never())
            ->method('publish');

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('isUrlIndexed'))
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(true));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(array('getBlacklist'))
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->any())
            ->method('getBlacklist')
            ->will($this->returnValue([]));
        
        $uri = new Uri('https://github.com');
        $event = new GenericEvent($spider, array('uris' => array($uri)));
        $listener = new DiscoverUrlListener($queue, $indexer);
        $listener->onDiscoverUrl($event);
    }

    public function testIfUrlIsIndexed()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('publish', '__destruct'))
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('isUrlIndexed'))
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(array('getCurrentUri', 'getBlacklist'))
            ->disableOriginalConstructor()
            ->getMock();

        $uri = new Uri('https://github.com');

        $spider
            ->expects($this->once())
            ->method('getCurrentUri')
            ->will($this->returnValue($uri));

        $spider
            ->expects($this->any())
            ->method('getBlacklist')
            ->will($this->returnValue([]));

        $event = new GenericEvent($spider, array('uris' => array($uri)));
        $listener = new DiscoverUrlListener($queue, $indexer);
        $listener->onDiscoverUrl($event);
    }
    
    public function testIfUrlIsBlacklisted()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('publish', '__destruct'))
            ->getMock();

        $queue
            ->expects($this->never())
            ->method('publish');

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('isUrlIndexed'))
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setMethods(array('getBlacklist'))
            ->disableOriginalConstructor()
            ->getMock();

        $spider
            ->expects($this->any())
            ->method('getBlacklist')
            ->will($this->returnValue(['github\.com']));
        
        $uri = new Uri('https://github.com');
        $event = new GenericEvent($spider, array('uris' => array($uri)));
        $listener = new DiscoverUrlListener($queue, $indexer);
        $listener->onDiscoverUrl($event);
    }
}
