<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\TypeAbstract;
use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentDataExtractor;
use PhpOffice\PhpWord\Reader\RTF as RtfReader;
use PhpOffice\PhpWord\Writer\HTML as HtmlWriter;
use PhpOffice\PhpWord\PhpWord as PhpWord;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;

/**
 * RTF Resolver Document Type
 */
class Rtf extends TypeAbstract implements DocumentTypeInterface
{
    /**
     * Extracts content from a odt and returns document data.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return array
     *
     * @throws \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     */
    public function getData(Resource $resource)
    {
        $content = $this->extractContentFromResource($resource);

        if (strlen($content) < self::MINIMAL_CONTENT_LENGTH) {
            throw new InvalidContentException(
                sprintf("Rtf didn't contain enough content (minimal chars is %s)", self::MINIMAL_CONTENT_LENGTH)
            );
        }

        $dataExtractor = new DocumentDataExtractor($resource);

        $url = $dataExtractor->getUrl();
        $title = $this->getTitleByUrl($url) ? : '';

        $data = [
            'id' => $dataExtractor->getId(),
            'url' => $url,
            'content' => $content,
            'title' => $title,
            'tstamp' => date('Y-m-d\TH:i:s\Z'),
            'contentLength' => strlen($content),
            'lastModified' => $dataExtractor->getLastModified(),
            'date' => date('Y-m-d\TH:i:s\Z'),
            'publishedDate' => date('Y-m-d\TH:i:s\Z'),
            'updatedDate' => date('Y-m-d\TH:i:s\Z'),
        ];

        return $data;
    }

    /**
     * Extract content from resource
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     */
    public function extractContentFromResource(Resource $resource)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'rtf');

        file_put_contents($tempFile, $resource->getResponse()->getBody());

        $reader = $this->getReader();

        //remove notice from library
        $errorReportingLevel = error_reporting();
        error_reporting($errorReportingLevel ^ E_NOTICE);

        $phpword = $reader->load($tempFile);

        //back error reporting to previous state
        error_reporting($errorReportingLevel);

        unlink($tempFile);

        $writer = $this->getWriter($phpword);

        return strip_tags($this->stripBinaryContent($writer->getContent()));
    }

    /**
     * Return Reader Object
     *
     * @return \PhpOffice\PhpWord\Reader\RTF
     */
    protected function getReader()
    {
        return new RtfReader();
    }

    /**
     * Return Writer Object
     *
     * @param \PhpOffice\PhpWord\PhpWord $phpWord
     *
     * @return \PhpOffice\PhpWord\Writer\HTML
     */
    protected function getWriter(PhpWord $phpWord)
    {
        return new HtmlWriter($phpWord);
    }

}
