<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseClient;
use Redking\ParseBundle\Tests\TestCase;

/**
 * The Parse SDK opens a new cURL handle for each request: the object manager makes them share their
 * connections so that a request does not negotiate TLS again.
 */
class CurlConnectionSharingTest extends TestCase
{
    public function testSdkRequestsUseTheSameShareHandle(): void
    {
        $share = $this->getSdkShareHandle();
        $this->assertInstanceOf(\CurlShareHandle::class, $share);

        // Another object manager of the same process keeps the connections already opened
        $this->createTestObjectManager();
        $this->assertSame($share, $this->getSdkShareHandle());
    }

    public function testConnectionOpenedBySdkIsReused(): void
    {
        $health = ParseClient::getServerHealth();
        $this->assertSame(200, $health['status']);

        // A handle sharing the same cache finds the connection the SDK left open
        $url = (new \ReflectionMethod(ParseClient::class, 'createRequestUrl'))->invoke(null, 'health');
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_SHARE => $this->getSdkShareHandle(),
        ]);
        curl_exec($curl);

        $this->assertSame(200, curl_getinfo($curl, \CURLINFO_HTTP_CODE));
        $this->assertSame(0, curl_getinfo($curl, \CURLINFO_NUM_CONNECTS), 'The request opened a new connection');
    }

    private function getSdkShareHandle(): mixed
    {
        $options = (new \ReflectionProperty(ParseClient::class, 'httpOptions'))->getValue();

        return $options[\CURLOPT_SHARE] ?? null;
    }
}
