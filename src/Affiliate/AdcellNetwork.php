<?php
declare(strict_types=1);

namespace JUNU\Affiliate;

use Exception;

class AdcellNetwork
{
    private const API_URL = 'https://www.adcell.de/api/v2/';
    private string $userId;
    private string $apiPassword;
    private ?string $token = null;
    private int $tokenExpires = 0;

    public function __construct(string $userId, string $apiPassword)
    {
        $this->userId      = $userId;
        $this->apiPassword = $apiPassword;
    }

    /**
     * Fetch coupons for the given program IDs.
     *
     * @param array $programIds
     * @return array Normalized coupon data.
     * @throws Exception
     */
    public function fetchCoupons(array $programIds): array
    {
        $this->ensureTokenIsValid();
        $allCoupons = [];

        foreach ($programIds as $programId) {
            $page = 1;
            do {
                $response = $this->makeApiRequest('affiliate/promotion/getPromotionTypeCoupon', [
                    'programIds[]' => $programId,
                    'rows'         => 25,
                    'page'         => $page,
                ]);

                if ($response && isset($response['data']['items'])) {
                    foreach ($response['data']['items'] as $item) {
                        $allCoupons[] = $this->normalizeData($item);
                    }
                    $totalItems = $response['data']['total']['numberItems'] ?? 0;
                    $totalPages = (int)ceil($totalItems / 25);
                    $page++;
                } else {
                    break;
                }
            } while ($page <= $totalPages);
        }

        return $allCoupons;
    }

    /**
     * Fetch transaction data for a coupon.
     *
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function fetchTransactionData(array $params = []): array
    {
        $this->ensureTokenIsValid();
        $response = $this->makeApiRequest('affiliate/statistic/byCommission', $params);
        if ($response && isset($response['data'])) {
            return $response['data'];
        }
        throw new Exception("Failed to fetch transaction data: " . ($response['message'] ?? 'No message'));
    }

    private function ensureTokenIsValid(): void
    {
        if ($this->token === null || time() >= $this->tokenExpires) {
            $this->fetchToken();
        }
    }

    private function fetchToken(): void
    {
        $response = $this->makeApiRequest('user/getToken', [
            'userName' => $this->userId,
            'password' => $this->apiPassword,
        ]);

        if ($response && $response['status'] === 200 && isset($response['data']['token'], $response['data']['expires'])) {
            $this->token        = $response['data']['token'];
            $this->tokenExpires = time() + (int)$response['data']['expires'];
        } else {
            throw new Exception("Failed to fetch or validate API token: " . ($response['message'] ?? 'No message'));
        }
    }

    private function makeApiRequest(string $endpoint, array $params = []): array
    {
        if ($this->token !== null) {
            $params['token'] = $this->token;
        }
        $url = self::API_URL . $endpoint . '?' . http_build_query($params);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("CURL Error: " . curl_error($ch));
        }
        curl_close($ch);
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new Exception("Failed to decode API response: " . $response);
        }
        return $decoded;
    }

    /**
     * Normalizes the coupon data returned by the API.
     *
     * @param array $data
     * @return array
     */
    private function normalizeData(array $data): array
    {
        // Extract a title from the description (first non-empty line).
        $lines = preg_split('/\R/', $data['description'] ?? '');
        $title = '';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $title = trim($line);
                break;
            }
        }

        // Fetch transaction data for additional statistics.
        $transactionData = $this->fetchTransactionData([
            'programId'   => $data['programId'] ?? null,
            'promotionId' => $data['promotionId'] ?? null,
            'rows'        => 1,
            'page'        => 1,
        ]);
        $totalShoppingCart = $transactionData['total']['totalShoppingCart'] ?? null;
        $totalCommission   = $transactionData['total']['totalCommission'] ?? null;

        return [
            'brand_id'            => $data['programId'] ?? null,
            'amount'              => null,
            'amount_sign'         => null,
            'title'               => $title,
            'information'         => $data['information'] ?? '',
            'code'                => $data['promotionCode'] ?? '',
            'url'                 => $data['clickoutLink'] ?? '',
            'start_time'          => $data['startTime'] ?? '',
            'end_time'            => $data['endTime'] ?? null,
            'min_order_value'     => null,
            'exclusive'           => 0,
            'flag_name'           => null,
            'flag_color'          => null,
            'clicked_count'       => 0,
            'imported'            => 1,
            'network_identifier'  => $data['promotionId'] ?? '',
            'total_shopping_cart' => $totalShoppingCart,
            'total_commission'    => $totalCommission,
        ];
    }
}
