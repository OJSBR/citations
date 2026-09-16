<?php

/**
 * @file plugins/generic/citations/tests/CitationsTest.php
 *
 * Copyright (c) 2021+ TIB Hannover
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationsTest
 *
 * @brief What the plugin makes of the answers of Crossref, Scopus and Europe
 *        PMC, what it does without credentials, and the requests it sends —
 *        through the application's HTTP client, against a mocked one.
 */

namespace APP\plugins\generic\citations\tests;

use APP\plugins\generic\citations\classes\processor\CrossrefProcessor;
use APP\plugins\generic\citations\classes\processor\EuropePmcProcessor;
use APP\plugins\generic\citations\classes\processor\ScopusProcessor;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\core\Registry;
use PKP\tests\PKPTestCase;

#[CoversClass(CrossrefProcessor::class)]
#[CoversClass(ScopusProcessor::class)]
class CitationsTest extends PKPTestCase
{
    private const DOI = '10.1234/example.2026.1';

    /** The requests the mocked client was asked to make, in order. */
    private array $requests = [];

    /**
     * Answers the plugin's requests with the given responses and records them.
     *
     * @param Response[] $responses
     */
    protected function mockClient(array $responses): void
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));
        $client = new Client(['handler' => $stack]);
        // The second argument is taken by reference: it has to be a variable.
        Registry::set(PKPTestCase::MOCKED_GUZZLE_CLIENT_NAME, $client);
    }

    private function crossrefAnswer(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<crossref_result xmlns="http://www.crossref.org/qrschema/3.0">
  <query_result>
    <body>
      <forward_link doi="' . self::DOI . '">
        <journal_cite>
          <journal_title>Journal of Examples</journal_title>
          <article_title>An article that cites</article_title>
          <contributors><contributor sequence="first"><given_name>Ana</given_name><surname>Silva</surname></contributor></contributors>
          <year>2026</year>
          <doi type="journal_article">10.5555/citing.one</doi>
        </journal_cite>
      </forward_link>
      <forward_link doi="' . self::DOI . '">
        <book_cite>
          <volume_title>A book that cites</volume_title>
          <year>2025</year>
          <doi type="book_content">10.5555/citing.two</doi>
        </book_cite>
      </forward_link>
    </body>
  </query_result>
</crossref_result>';
    }

    public function testCrossrefCountsTheForwardLinksAndListsThemOnlyWhenAsked(): void
    {
        $settings = ['crossrefUser' => 'user@example.org', 'crossrefPwd' => 'secret', 'showList' => false];

        $this->mockClient([new Response(200, [], $this->crossrefAnswer())]);
        $result = (new CrossrefProcessor())->process(self::DOI, $settings);
        $this->assertSame(2, $result['count']);
        $this->assertSame([], $result['citations'], 'the list is only built when the journal asked for it');

        $this->mockClient([new Response(200, [], $this->crossrefAnswer())]);
        $withList = (new CrossrefProcessor())->process(self::DOI, array_merge($settings, ['showList' => true]));
        $this->assertSame(2, $withList['count']);
        $this->assertCount(2, $withList['citations']);
        $flattened = json_encode($withList['citations'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('An article that cites', $flattened);
        $this->assertStringContainsString('A book that cites', $flattened);
        $this->assertStringContainsString('10.5555/citing.one', $flattened);

        // The credentials and the DOI travel in the request, url encoded.
        $this->assertCount(1, $this->requests);
        $uri = (string) $this->requests[0]['request']->getUri();
        $this->assertStringContainsString('doi.crossref.org', $uri);
        $this->assertStringContainsString(rawurlencode(self::DOI), str_replace('%2F', '%2F', $uri));
        $this->assertStringContainsString('user%40example.org', $uri);
    }

    public function testCrossrefWithoutCredentialsAsksNothingAndSaysWhy(): void
    {
        $this->mockClient([]);

        $result = (new CrossrefProcessor())->process(self::DOI, ['showList' => true]);

        $this->assertSame(0, $result['count']);
        $this->assertArrayHasKey('error', $result);
        $this->assertCount(0, $this->requests, 'no request may be made without credentials');
    }

    public function testCrossrefIgnoresAnAnswerThatIsNotItsXml(): void
    {
        $this->mockClient([new Response(200, [], '<html><body>Service unavailable</body></html>')]);

        $result = (new CrossrefProcessor())->process(self::DOI, ['crossrefUser' => 'u', 'crossrefPwd' => 'p', 'showList' => true]);

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['citations']);
    }

    public function testScopusReadsTheCountAndFetchesTheListWithTheEid(): void
    {
        $search = json_encode(['search-results' => ['entry' => [['eid' => '2-s2.0-123456', 'citedby-count' => '7']]]]);
        $citing = json_encode(['search-results' => ['entry' => [
            ['dc:title' => 'A citing article', 'prism:publicationName' => 'Journal of Examples', 'prism:doi' => '10.5555/citing.one'],
        ]]]);

        $this->mockClient([new Response(200, [], $search), new Response(200, [], $citing)]);
        $result = (new ScopusProcessor())->process(self::DOI, ['scopusKey' => 'key-1', 'showList' => true]);

        $this->assertSame(7, $result['count']);
        $this->assertCount(1, $result['citations']);
        $this->assertCount(2, $this->requests, 'the list is a second request, keyed by the eid');
        $this->assertStringContainsString('2-s2.0-123456', urldecode((string) $this->requests[1]['request']->getUri()));
    }

    public function testScopusAsksOnlyOnceWhenTheListIsNotWantedOrThereIsNothingToList(): void
    {
        $search = json_encode(['search-results' => ['entry' => [['eid' => '2-s2.0-123456', 'citedby-count' => '3']]]]);

        $this->mockClient([new Response(200, [], $search)]);
        $result = (new ScopusProcessor())->process(self::DOI, ['scopusKey' => 'key-1', 'showList' => false]);
        $this->assertSame(3, $result['count']);
        $this->assertCount(1, $this->requests);

        // Nothing cited it: no second request either.
        $none = json_encode(['search-results' => ['entry' => [['eid' => '2-s2.0-123456', 'citedby-count' => '0']]]]);
        $this->mockClient([new Response(200, [], $none)]);
        $empty = (new ScopusProcessor())->process(self::DOI, ['scopusKey' => 'key-1', 'showList' => true]);
        $this->assertSame(0, $empty['count']);
        $this->assertCount(1, $this->requests);
    }

    public function testScopusWithoutAKeyAsksNothing(): void
    {
        $this->mockClient([]);

        $result = (new ScopusProcessor())->process(self::DOI, ['showList' => true]);

        $this->assertSame(0, $result['count']);
        $this->assertArrayHasKey('error', $result);
        $this->assertCount(0, $this->requests);
    }

    public function testAnAnswerThatIsNotJsonLeavesTheCountAtZero(): void
    {
        $this->mockClient([new Response(200, [], 'not json at all')]);

        $result = (new ScopusProcessor())->process(self::DOI, ['scopusKey' => 'key-1', 'showList' => true]);

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['citations']);
    }

    public function testEveryRequestGoesThroughTheApplicationClient(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/classes/client/CitationsHttpClient.php');

        $this->assertStringContainsString('Application::get()->getHttpClient()', $source);
        $this->assertStringNotContainsString('curl_', $source);
        $this->assertStringNotContainsString('file_get_contents', $source);

        foreach ([CrossrefProcessor::class, ScopusProcessor::class, EuropePmcProcessor::class] as $class) {
            $file = dirname(__DIR__) . '/classes/processor/' . (new \ReflectionClass($class))->getShortName() . '.php';
            $this->assertStringNotContainsString('curl_', (string) file_get_contents($file));
        }
    }
}
