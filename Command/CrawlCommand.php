<?php

namespace Simgroep\ConcurrentSpiderBundle\Command;

use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Simgroep\ConcurrentSpiderBundle\Spider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VDB\Uri\Exception\UriSyntaxException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Exception;

class CrawlCommand extends Command
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    private $queue;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Indexer
     */
    private $indexer;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Spider
     */
    private $spider;

    /**
     * @var string
     */
    private $userAgent;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param \Simgroep\ConcurrentSpiderBundle\Spider  $spider
     * @param string                                   $userAgent
     * @param \Monolog\Logger                          $logger
     */
    public function __construct(
        Queue $queue,
        Indexer $indexer,
        Spider $spider,
        $userAgent,
        Logger $logger
    ) {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->spider = $spider;
        $this->userAgent = $userAgent;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * Configure the command options.
     */
    public function configure()
    {
        $this
            ->setName('simgroep:crawl')
            ->setDescription("This command starts listening to the queue and will accept url's to index.");
    }

    /**
     * Starts to listen to the queue and grabs the messages from the queue to crawl url's.
     *
     * It should endless keep listening to the queue, if the listen function stops, something went wrong
     * so a non-zero integer is returned to indicate something went wrong.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->queue->listen(array($this, 'crawlUrl'));

        return 1;
    }

    /**
     * Consume a message, extracts the URL from it and crawls the webpage.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function crawlUrl(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);
        $urlToCrawl = $data['uri'];
        $baseUrl = $data['base_url'];
        $blacklist = $data['blacklist'];
        $logger = $this->logger;

        $this->spider->setBlacklist($blacklist);
        $this->spider->getEventDispatcher()->addListener(
            "spider.crawl.blacklisted",
            function ($event) use ($logger) {
                $logger->info(
                    sprintf("Blacklisted %s", $event->getArgument('uri')->toString())
                );
            }
        );

        if (!$this->areHostsEqual($urlToCrawl, $baseUrl)) {
            $this->queue->rejectMessage($message);

            $this->logger->info(sprintf("Skipped %s", $urlToCrawl));
            return;
        }

        if ($this->indexer->isUrlIndexed($urlToCrawl)) {
            $this->queue->rejectMessage($message);

            $this->logger->info(sprintf("Skipped %s", $urlToCrawl));
            return;
        }

        try {
            $this->spider->getRequestHandler()->getClient()->setUserAgent($this->userAgent);
            $this->spider->crawlUrl($urlToCrawl);

            $this->logger->info(sprintf("Crawling %s", $urlToCrawl));
            $this->queue->acknowledge($message);
        } catch (UriSyntaxException $e) {
            $this->logger->warning(sprintf('URL %s failed', $urlToCrawl));

            $this->queue->rejectMessageAndRequeue($message);
        } catch (ClientErrorResponseException $e) {
            if (in_array($e->getResponse()->getStatusCode(), array(404, 403, 401, 500))) {
                $this->queue->rejectMessage($message);
                $this->logger->warning(sprintf("Skipped %s", $urlToCrawl));
            } else {
                $this->queue->rejectMessageAndRequeue($message);
                $this->logger->emergency(sprintf('URL (%s) %s failed', $e->getResponse()->getStatusCode(), $urlToCrawl));
            }
        } catch (Exception $e) {
            $this->queue->rejectMessage($message);
            $this->logger->emergency(sprintf("Failed (%s) %s", $e->getMessage(), $urlToCrawl));
        }
    }

    /**
     * Indicates whether the hostname parts of two urls are equal.
     *
     * @param string $firstUrl
     * @param string $secondUrl
     *
     * @return boolean
     */
    private function areHostsEqual($firstUrl, $secondUrl)
    {
        $firstUrl = parse_url($firstUrl);
        $secondUrl = parse_url($secondUrl);

        if (!array_key_exists('host', $firstUrl) || !array_key_exists('host', $secondUrl)) {
            return false;
        }

        return ($firstUrl['host'] === $secondUrl['host']);
    }
}