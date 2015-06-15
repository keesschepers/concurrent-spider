<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Command;

use PHPUnit_Framework_TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class IndexCommandTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function execute()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('rejectMessage', '__destruct', 'listen'))
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('listen');

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('isUrlIndexed'))
            ->getMock();

        /** @var \Simgroep\ConcurrentSpiderBundle\Command\IndexCommand $command */
        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\IndexCommand')
            ->setConstructorArgs(array($queue, $indexer, []))
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();
        $command->run($input, $output);
    }


    /**
     * @testdox Tests if every 10 documents the index saves them.
     */
    public function testIfEveryTenDocumentsAreSaved()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(array('acknowledge', '__destruct', 'listen'))
            ->getMock();

        $queue
            ->expects($this->exactly(10))
            ->method('acknowledge');

        $queue
            ->expects($this->once())
            ->method('listen');

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $indexer->expects($this->once())
            ->method('addDocuments');

        $command = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Command\IndexCommand')
            ->setConstructorArgs([$queue, $indexer, ['id' => 'id']])
            ->setMethods(null)
            ->getMock();

        $input = new StringInput('');
        $output = new NullOutput();
        $command->run($input, $output);

        for ($i=0; $i<=9; $i++) {
            $body = json_encode(
                array(
                    'document' => array(
                        'id' => rand(0,10),
                        'title' => sha1(rand(0,10)),
                        'tstamp' => date('Y-m-d\TH:i:s\Z'),
                        'date' => date('Y-m-d\TH:i:s\Z'),
                        'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                        'content' => str_repeat(sha1(rand(0,10)), 5),
                        'url' => 'https://www.github.com',
                    )
                )
            );

            $message = new AMQPMessage($body);
            $command->prepareDocument($message);
        }
    }
}
