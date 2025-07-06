<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command\Helper;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use TikiManager\Application\Instance;
use Exception;

class InstanceValidator
{
    private $httpClient;
    private $logger;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Validates the given instance to ensure it is reachable and not in maintenance mode.
     *
     * @param Instance $instance The instance to validate.
     * @return bool True if the instance passes validation, false otherwise.
     */
    public function validate(Instance $instance, int $timeout = 10): bool
    {
        $instanceUrl = $instance->weburl;

        try {
            $response = $this->httpClient->request('GET', $instanceUrl, [
                'max_redirects' => 5,
                'timeout' => $timeout,
            ]);

            $this->logger->info('Attempting to validate instance at URL: ' . $instanceUrl);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Instance validation failed. URL returned non-200 status.', [
                    'url' => $instanceUrl,
                    'status_code' => $response->getStatusCode()
                ]);
                return false;
            }

            $html = $response->getContent();

            if (strpos($html, 'Maintenance in Progress') !== false) {
                $this->logger->error('Instance is still in maintenance mode.', ['url' => $instanceUrl]);
                return false;
            }

            $this->logger->info('Instance validated successfully at URL: ' . $instanceUrl);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Validation failed for instance: ' . $e->getMessage(), ['url' => $instanceUrl]);
            return false;
        }
    }
}
